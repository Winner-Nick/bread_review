#!/usr/bin/env php
<?php
/**
 * 从数据库导出数据到JSON文件
 * 用于备份或回滚到JSON版本
 */

define('DB_FILE', __DIR__ . '/bread_review.db');
define('OUTPUT_DIR', __DIR__ . '/../data');
define('POINTS_POOL_FILE', OUTPUT_DIR . '/points_pool.json');
define('DAILY_ASSIGNMENTS_FILE', OUTPUT_DIR . '/daily_assignments.json');

echo "===========================================\n";
echo "数据库导出到JSON工具\n";
echo "===========================================\n\n";

// 检查数据库是否存在
if (!file_exists(DB_FILE)) {
    echo "❌ 数据库文件不存在: " . DB_FILE . "\n";
    exit(1);
}

// 连接数据库
try {
    $db = new PDO('sqlite:' . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✓ 已连接到数据库\n";
} catch (PDOException $e) {
    echo "❌ 无法连接数据库: " . $e->getMessage() . "\n";
    exit(1);
}

// 确保输出目录存在
if (!is_dir(OUTPUT_DIR)) {
    mkdir(OUTPUT_DIR, 0755, true);
    echo "✓ 创建输出目录: $OUTPUT_DIR\n";
}

// 1. 导出配置
echo "\n导出配置数据...\n";
$stmt = $db->query("SELECT key, value FROM config");
$config = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $config[$row['key']] = $row['value'];
}

// 转换为原JSON格式
$poolData = [
    'config' => [
        'startDate' => $config['startDate'] ?? '2025-11-14',
        'totalDays' => (int)($config['totalDays'] ?? 30),
        'avgPointsPerDay' => (int)($config['avgPointsPerDay'] ?? 0),
        'totalPoints' => (int)($config['totalPoints'] ?? 0),
        'createdAt' => $config['createdAt'] ?? date('Y-m-d\TH:i:s'),
        'lastUpdated' => $config['lastUpdated'] ?? date('Y-m-d\TH:i:s')
    ],
    'points' => []
];

echo "  ✓ 配置数据已读取\n";

// 2. 导出知识点
echo "\n导出知识点数据...\n";
$stmt = $db->query("SELECT * FROM points ORDER BY id");
$pointCount = 0;

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // 获取该知识点的历史记录
    $historyStmt = $db->prepare("SELECT * FROM point_history WHERE point_id = ? ORDER BY timestamp");
    $historyStmt->execute([$row['id']]);
    $history = [];
    while ($h = $historyStmt->fetch(PDO::FETCH_ASSOC)) {
        $history[] = [
            'action' => $h['action'],
            'timestamp' => $h['timestamp'],
            'day' => $h['day']
        ];
    }

    // 转换为JSON格式
    $point = [
        'id' => (int)$row['id'],
        '科目' => $row['subject'],
        '章节' => $row['chapter'],
        '考点' => $row['point'],
        '页码' => $row['page'],
        'status' => $row['status'],
        'assignedDay' => $row['assigned_day'] ? (int)$row['assigned_day'] : null,
        'completedAt' => $row['completed_at'],
        'forgottenCount' => (int)$row['forgotten_count'],
        'history' => $history
    ];

    $poolData['points'][] = $point;
    $pointCount++;

    if ($pointCount % 100 == 0) {
        echo "  已导出 $pointCount 个知识点...\n";
    }
}

echo "  ✓ 总共导出 $pointCount 个知识点\n";

// 保存题库文件
file_put_contents(POINTS_POOL_FILE, json_encode($poolData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo "  ✓ 保存到: " . POINTS_POOL_FILE . "\n";

// 3. 导出每日分配
echo "\n导出每日分配数据...\n";
$assignmentsData = [
    'meta' => [
        'lastUpdated' => $config['lastUpdated'] ?? date('Y-m-d\TH:i:s')
    ]
];

$stmt = $db->query("SELECT * FROM daily_assignments ORDER BY day");
$dayCount = 0;

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $day = (int)$row['day'];
    $pointIds = json_decode($row['point_ids'], true);

    // 计算completed和forgotten列表
    $completed = [];
    $forgotten = [];

    if (!empty($pointIds)) {
        $placeholders = implode(',', array_fill(0, count($pointIds), '?'));
        $statusStmt = $db->prepare("SELECT id, status FROM points WHERE id IN ($placeholders)");
        $statusStmt->execute($pointIds);

        while ($p = $statusStmt->fetch(PDO::FETCH_ASSOC)) {
            if ($p['status'] === 'remembered') {
                $completed[] = (int)$p['id'];
            } elseif ($p['status'] === 'forgotten') {
                $forgotten[] = (int)$p['id'];
            }
        }
    }

    $assignmentsData['day_' . $day] = [
        'date' => $row['date'],
        'pointIds' => $pointIds,
        'currentIndex' => (int)$row['current_index'],
        'completed' => $completed,
        'forgotten' => $forgotten
    ];

    $dayCount++;
}

echo "  ✓ 导出了 $dayCount 天的分配数据\n";

// 保存每日分配文件
file_put_contents(DAILY_ASSIGNMENTS_FILE, json_encode($assignmentsData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo "  ✓ 保存到: " . DAILY_ASSIGNMENTS_FILE . "\n";

// 验证导出的文件
echo "\n验证导出结果...\n";
$poolSize = filesize(POINTS_POOL_FILE);
$assignmentsSize = filesize(DAILY_ASSIGNMENTS_FILE);

echo "  文件大小:\n";
echo "    points_pool.json: " . round($poolSize / 1024, 2) . " KB\n";
echo "    daily_assignments.json: " . round($assignmentsSize / 1024, 2) . " KB\n";

// 验证JSON格式
$testPool = json_decode(file_get_contents(POINTS_POOL_FILE), true);
$testAssignments = json_decode(file_get_contents(DAILY_ASSIGNMENTS_FILE), true);

if ($testPool && $testAssignments) {
    echo "  ✓ JSON格式验证通过\n";
} else {
    echo "  ✗ JSON格式验证失败\n";
    exit(1);
}

echo "\n===========================================\n";
echo "✅ 数据导出完成！\n";
echo "===========================================\n\n";

echo "导出文件:\n";
echo "  1. $POINTS_POOL_FILE\n";
echo "  2. $DAILY_ASSIGNMENTS_FILE\n\n";

echo "统计:\n";
echo "  知识点数量: $pointCount\n";
echo "  每日分配: $dayCount 天\n\n";

echo "这些JSON文件可用于:\n";
echo "  - 备份数据\n";
echo "  - 回滚到JSON版本\n";
echo "  - 数据迁移验证\n";

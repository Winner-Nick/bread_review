#!/usr/bin/env php
<?php
/**
 * 数据迁移验证脚本
 * 对比JSON和数据库中的数据是否一致
 */

define('DB_FILE', __DIR__ . '/bread_review.db');
define('JSON_POOL', __DIR__ . '/../data/points_pool.json');
define('JSON_ASSIGNMENTS', __DIR__ . '/../data/daily_assignments.json');

echo "===========================================\n";
echo "数据迁移验证工具\n";
echo "===========================================\n\n";

// 检查文件是否存在
if (!file_exists(DB_FILE)) {
    echo "❌ 数据库文件不存在: " . DB_FILE . "\n";
    echo "请先运行: php database/init_db.php\n";
    exit(1);
}

if (!file_exists(JSON_POOL)) {
    echo "❌ JSON文件不存在: " . JSON_POOL . "\n";
    exit(1);
}

// 读取JSON数据
echo "读取JSON数据...\n";
$jsonPool = json_decode(file_get_contents(JSON_POOL), true);
if (!$jsonPool) {
    echo "❌ 无法解析JSON文件\n";
    exit(1);
}

$jsonAssignments = null;
if (file_exists(JSON_ASSIGNMENTS)) {
    $jsonAssignments = json_decode(file_get_contents(JSON_ASSIGNMENTS), true);
}

// 连接数据库
try {
    $db = new PDO('sqlite:' . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✓ 已连接到数据库\n\n";
} catch (PDOException $e) {
    echo "❌ 无法连接数据库: " . $e->getMessage() . "\n";
    exit(1);
}

$errors = [];

// 1. 验证知识点总数
echo "1. 验证知识点总数...\n";
$jsonPointCount = count($jsonPool['points']);
$dbPointCount = $db->query("SELECT COUNT(*) FROM points")->fetchColumn();
if ($jsonPointCount === $dbPointCount) {
    echo "   ✓ 知识点数量一致: $jsonPointCount\n";
} else {
    $msg = "   ✗ 知识点数量不一致! JSON: $jsonPointCount, DB: $dbPointCount\n";
    echo $msg;
    $errors[] = $msg;
}

// 2. 验证配置信息
echo "\n2. 验证配置信息...\n";
$stmt = $db->query("SELECT key, value FROM config");
$dbConfig = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $dbConfig[$row['key']] = $row['value'];
}

$checks = ['startDate', 'totalDays', 'totalPoints'];
foreach ($checks as $key) {
    $jsonValue = $jsonPool['config'][$key] ?? 'N/A';
    $dbValue = $dbConfig[$key] ?? 'N/A';
    if ($jsonValue == $dbValue) {
        echo "   ✓ $key: $dbValue\n";
    } else {
        $msg = "   ✗ $key 不一致! JSON: $jsonValue, DB: $dbValue\n";
        echo $msg;
        $errors[] = $msg;
    }
}

// 3. 验证知识点状态分布
echo "\n3. 验证知识点状态分布...\n";
$jsonStatus = ['pending' => 0, 'remembered' => 0, 'forgotten' => 0];
foreach ($jsonPool['points'] as $point) {
    $status = $point['status'] ?? 'pending';
    if (isset($jsonStatus[$status])) {
        $jsonStatus[$status]++;
    }
}

$stmt = $db->query("SELECT status, COUNT(*) as count FROM points GROUP BY status");
$dbStatus = ['pending' => 0, 'remembered' => 0, 'forgotten' => 0];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $dbStatus[$row['status']] = (int)$row['count'];
}

foreach ($jsonStatus as $status => $count) {
    $dbCount = $dbStatus[$status];
    if ($count === $dbCount) {
        echo "   ✓ $status: $count\n";
    } else {
        $msg = "   ✗ $status 不一致! JSON: $count, DB: $dbCount\n";
        echo $msg;
        $errors[] = $msg;
    }
}

// 4. 验证每日分配
if ($jsonAssignments) {
    echo "\n4. 验证每日分配...\n";
    $dbAssignmentCount = $db->query("SELECT COUNT(*) FROM daily_assignments")->fetchColumn();
    $jsonDayCount = 0;
    for ($day = 1; $day <= 30; $day++) {
        if (isset($jsonAssignments['day_' . $day])) {
            $jsonDayCount++;
        }
    }
    if ($jsonDayCount === $dbAssignmentCount) {
        echo "   ✓ 每日分配数量一致: $dbAssignmentCount 天\n";
    } else {
        $msg = "   ✗ 每日分配不一致! JSON: $jsonDayCount, DB: $dbAssignmentCount\n";
        echo $msg;
        $errors[] = $msg;
    }
}

// 5. 随机抽样验证
echo "\n5. 随机抽样验证（10个知识点）...\n";
$sampleCount = min(10, count($jsonPool['points']));
$sampleIndices = array_rand($jsonPool['points'], $sampleCount);
if (!is_array($sampleIndices)) {
    $sampleIndices = [$sampleIndices];
}

foreach ($sampleIndices as $index) {
    $jsonPoint = $jsonPool['points'][$index];
    $id = $jsonPoint['id'];

    // 从数据库获取
    $stmt = $db->prepare("SELECT * FROM points WHERE id = ?");
    $stmt->execute([$id]);
    $dbPoint = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($dbPoint &&
        $dbPoint['subject'] === ($jsonPoint['科目'] ?? '') &&
        $dbPoint['chapter'] === ($jsonPoint['章节'] ?? '') &&
        $dbPoint['point'] === ($jsonPoint['考点'] ?? '') &&
        $dbPoint['status'] === ($jsonPoint['status'] ?? 'pending') &&
        $dbPoint['forgotten_count'] == ($jsonPoint['forgottenCount'] ?? 0)) {
        echo "   ✓ ID $id 数据一致\n";
    } else {
        $msg = "   ✗ ID $id 数据不一致!\n";
        echo $msg;
        $errors[] = $msg;

        // 显示差异
        echo "     JSON: 科目={$jsonPoint['科目']}, 状态={$jsonPoint['status']}\n";
        echo "     DB:   科目={$dbPoint['subject']}, 状态={$dbPoint['status']}\n";
    }
}

// 6. 验证关键知识点（已标记的）
echo "\n6. 验证已标记的知识点...\n";
$markedJsonCount = 0;
foreach ($jsonPool['points'] as $point) {
    if (isset($point['status']) && $point['status'] !== 'pending') {
        $markedJsonCount++;
    }
}

$markedDbCount = $db->query("SELECT COUNT(*) FROM points WHERE status != 'pending'")->fetchColumn();
if ($markedJsonCount === $markedDbCount) {
    echo "   ✓ 已标记知识点数量一致: $markedJsonCount\n";
} else {
    $msg = "   ✗ 已标记知识点不一致! JSON: $markedJsonCount, DB: $markedDbCount\n";
    echo $msg;
    $errors[] = $msg;
}

// 总结
echo "\n===========================================\n";
if (empty($errors)) {
    echo "✅ 数据验证完成！所有数据一致。\n";
    echo "===========================================\n";
    exit(0);
} else {
    echo "❌ 数据验证发现 " . count($errors) . " 个问题！\n";
    echo "===========================================\n";
    echo "\n问题列表:\n";
    foreach ($errors as $i => $error) {
        echo ($i + 1) . ". " . trim($error) . "\n";
    }
    exit(1);
}

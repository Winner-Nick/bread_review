#!/usr/bin/env php
<?php
/**
 * JSON数据迁移到数据库
 * 功能：将现有的JSON数据导入到SQLite数据库
 */

// 定义路径
define('DB_FILE', __DIR__ . '/bread_review.db');
define('DATA_DIR', __DIR__ . '/../data');
define('POINTS_POOL_FILE', DATA_DIR . '/points_pool.json');
define('DAILY_ASSIGNMENTS_FILE', DATA_DIR . '/daily_assignments.json');

echo "===========================================\n";
echo "JSON数据迁移工具\n";
echo "===========================================\n\n";

// 检查数据库是否存在
if (!file_exists(DB_FILE)) {
    echo "❌ 错误：数据库文件不存在，请先运行 database/init_db.php\n";
    exit(1);
}

// 检查JSON文件是否存在
if (!file_exists(POINTS_POOL_FILE)) {
    echo "❌ 错误：找不到题库文件: " . POINTS_POOL_FILE . "\n";
    exit(1);
}

// 连接数据库
try {
    $db = new PDO('sqlite:' . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✓ 已连接到数据库\n";
} catch (PDOException $e) {
    echo "❌ 错误：无法连接数据库: " . $e->getMessage() . "\n";
    exit(1);
}

// 读取JSON文件
echo "✓ 正在读取JSON文件...\n";
$poolData = json_decode(file_get_contents(POINTS_POOL_FILE), true);

if (!$poolData) {
    echo "❌ 错误：无法解析题库JSON文件\n";
    exit(1);
}

// 开始事务
$db->beginTransaction();

try {
    // 1. 迁移配置
    echo "\n迁移配置数据...\n";
    if (isset($poolData['config'])) {
        $config = $poolData['config'];

        $stmt = $db->prepare("INSERT OR REPLACE INTO config (key, value, updated_at) VALUES (?, ?, datetime('now'))");

        if (isset($config['startDate'])) {
            $stmt->execute(['startDate', $config['startDate']]);
            echo "  ✓ startDate: " . $config['startDate'] . "\n";
        }
        if (isset($config['totalDays'])) {
            $stmt->execute(['totalDays', $config['totalDays']]);
            echo "  ✓ totalDays: " . $config['totalDays'] . "\n";
        }
        if (isset($config['totalPoints'])) {
            $stmt->execute(['totalPoints', $config['totalPoints']]);
            echo "  ✓ totalPoints: " . $config['totalPoints'] . "\n";
        }
        if (isset($config['avgPointsPerDay'])) {
            $stmt->execute(['avgPointsPerDay', $config['avgPointsPerDay']]);
            echo "  ✓ avgPointsPerDay: " . $config['avgPointsPerDay'] . "\n";
        }
    }

    // 2. 迁移知识点
    echo "\n迁移知识点数据...\n";
    if (isset($poolData['points']) && is_array($poolData['points'])) {
        $stmt = $db->prepare("
            INSERT INTO points (id, subject, chapter, point, page, status, assigned_day, completed_at, forgotten_count, created_at, updated_at)
            VALUES (:id, :subject, :chapter, :point, :page, :status, :assigned_day, :completed_at, :forgotten_count, datetime('now'), datetime('now'))
        ");

        $count = 0;
        foreach ($poolData['points'] as $point) {
            $stmt->execute([
                ':id' => $point['id'],
                ':subject' => $point['科目'] ?? '',
                ':chapter' => $point['章节'] ?? '',
                ':point' => $point['考点'] ?? '',
                ':page' => $point['页码'] ?? '',
                ':status' => $point['status'] ?? 'pending',
                ':assigned_day' => $point['assignedDay'] ?? null,
                ':completed_at' => $point['completedAt'] ?? null,
                ':forgotten_count' => $point['forgottenCount'] ?? 0
            ]);

            // 如果有历史记录，也迁移
            if (isset($point['history']) && is_array($point['history'])) {
                $historyStmt = $db->prepare("
                    INSERT INTO point_history (point_id, action, day, timestamp)
                    VALUES (?, ?, ?, ?)
                ");
                foreach ($point['history'] as $history) {
                    $historyStmt->execute([
                        $point['id'],
                        $history['action'] ?? '',
                        $history['day'] ?? null,
                        $history['timestamp'] ?? date('Y-m-d H:i:s')
                    ]);
                }
            }

            $count++;
            if ($count % 100 == 0) {
                echo "  已迁移 $count 个知识点...\n";
            }
        }
        echo "  ✓ 总共迁移 $count 个知识点\n";
    }

    // 3. 迁移每日分配
    if (file_exists(DAILY_ASSIGNMENTS_FILE)) {
        echo "\n迁移每日分配数据...\n";
        $assignmentsData = json_decode(file_get_contents(DAILY_ASSIGNMENTS_FILE), true);

        if ($assignmentsData && is_array($assignmentsData)) {
            $stmt = $db->prepare("
                INSERT INTO daily_assignments (day, date, point_ids, current_index, created_at, updated_at)
                VALUES (?, ?, ?, ?, datetime('now'), datetime('now'))
            ");

            $dayCount = 0;
            for ($day = 1; $day <= 30; $day++) {
                $dayKey = 'day_' . $day;
                if (isset($assignmentsData[$dayKey])) {
                    $dayData = $assignmentsData[$dayKey];
                    $stmt->execute([
                        $day,
                        $dayData['date'] ?? '',
                        json_encode($dayData['pointIds'] ?? []),
                        $dayData['currentIndex'] ?? 0
                    ]);
                    $dayCount++;
                }
            }
            echo "  ✓ 迁移了 $dayCount 天的分配数据\n";
        }
    }

    // 提交事务
    $db->commit();
    echo "\n✓ 所有数据已成功提交\n";

} catch (Exception $e) {
    $db->rollBack();
    echo "\n❌ 错误：迁移失败: " . $e->getMessage() . "\n";
    exit(1);
}

// 验证迁移结果
echo "\n验证迁移结果:\n";
$pointCount = $db->query("SELECT COUNT(*) FROM points")->fetchColumn();
$assignmentCount = $db->query("SELECT COUNT(*) FROM daily_assignments")->fetchColumn();
$historyCount = $db->query("SELECT COUNT(*) FROM point_history")->fetchColumn();

echo "  知识点数量: $pointCount\n";
echo "  每日分配数量: $assignmentCount\n";
echo "  历史记录数量: $historyCount\n";

echo "\n===========================================\n";
echo "✅ 数据迁移完成！\n";
echo "===========================================\n\n";

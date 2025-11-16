<?php
/**
 * 系统初始化API
 * 功能：
 * 1. 设置学习开始日期
 * 2. 一次性将所有题目平均分配到30天
 * 3. 清空之前的所有学习记录
 */

require_once 'common.php';

// 只接受POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('只支持POST请求', 405);
}

// 获取POST数据
$input = json_decode(file_get_contents('php://input'), true);
$startDate = isset($input['startDate']) ? trim($input['startDate']) : '';

// 验证日期格式
if (empty($startDate)) {
    errorResponse('请提供开始日期', 400);
}

// 验证日期格式 (YYYY-MM-DD)
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
    errorResponse('日期格式错误，请使用 YYYY-MM-DD 格式', 400);
}

// 验证日期有效性
try {
    $dateObj = new DateTime($startDate);
} catch (Exception $e) {
    errorResponse('无效的日期: ' . $startDate, 400);
}

try {
    $db = getDB();

    // 开始事务
    $db->beginTransaction();

    // ========== 执行初始化 ==========

    // 1. 更新配置中的开始日期
    setConfig('startDate', $startDate);
    setConfig('lastUpdated', date('Y-m-d\TH:i:s'));

    // 2. 获取所有题目并重置状态
    $stmt = $db->query("SELECT COUNT(*) as count FROM points");
    $result = $stmt->fetch();
    $totalPoints = (int)$result['count'];

    if ($totalPoints === 0) {
        $db->rollBack();
        errorResponse('题库为空，请先运行 scripts/generate_pool.py 生成题库', 400);
    }

    // 3. 重置所有题目状态
    $db->exec("
        UPDATE points
        SET status = 'pending',
            assigned_day = NULL,
            completed_at = NULL,
            forgotten_count = 0,
            updated_at = datetime('now')
    ");

    // 4. 清空历史记录
    $db->exec("DELETE FROM point_history");

    // 5. 获取所有题目ID
    $stmt = $db->query("SELECT id FROM points ORDER BY id");
    $allPointIds = [];
    while ($row = $stmt->fetch()) {
        $allPointIds[] = (int)$row['id'];
    }

    // 6. 清空之前的每日分配
    $db->exec("DELETE FROM daily_assignments");

    // 7. 平均分配到30天
    $totalDays = 30;
    $avgPointsPerDay = (int)($totalPoints / $totalDays);
    $remainder = $totalPoints % $totalDays;

    $currentIndex = 0;
    for ($day = 1; $day <= $totalDays; $day++) {
        // 计算该天日期
        $dayDate = clone $dateObj;
        $dayDate->modify('+' . ($day - 1) . ' days');

        // 计算该天应分配的题目数（前几天多分配余数）
        $pointsThisDay = $avgPointsPerDay;
        if ($day <= $remainder) {
            $pointsThisDay++;
        }

        // 分配题目ID
        $dayPointIds = array_slice($allPointIds, $currentIndex, $pointsThisDay);
        $currentIndex += $pointsThisDay;

        // 更新题库中的assigned_day
        if (!empty($dayPointIds)) {
            $placeholders = implode(',', array_fill(0, count($dayPointIds), '?'));
            $stmt = $db->prepare("UPDATE points SET assigned_day = ? WHERE id IN ($placeholders)");
            $params = array_merge([$day], $dayPointIds);
            $stmt->execute($params);
        }

        // 创建该天的分配记录
        $stmt = $db->prepare("
            INSERT INTO daily_assignments (day, date, point_ids, current_index, created_at, updated_at)
            VALUES (?, ?, ?, 0, datetime('now'), datetime('now'))
        ");
        $stmt->execute([$day, $dayDate->format('Y-m-d'), json_encode($dayPointIds)]);
    }

    // 8. 更新配置
    setConfig('totalDays', (string)$totalDays);
    setConfig('totalPoints', (string)$totalPoints);
    setConfig('avgPointsPerDay', (string)$avgPointsPerDay);

    // 提交事务
    $db->commit();

    // 9. 返回成功响应
    successResponse([
        'startDate' => $startDate,
        'totalDays' => $totalDays,
        'totalPoints' => $totalPoints,
        'avgPointsPerDay' => $avgPointsPerDay,
        'distributionComplete' => true
    ], '系统初始化成功');

} catch (Exception $e) {
    // 回滚事务
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('初始化失败: ' . $e->getMessage());
    errorResponse('初始化失败: ' . $e->getMessage(), 500);
}

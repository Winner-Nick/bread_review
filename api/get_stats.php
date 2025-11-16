<?php
/**
 * 获取统计信息API
 * 返回：总知识点数、已完成数、完成率、各天统计等
 */

require_once 'common.php';

try {
    $db = getDB();

    // 获取配置
    $config = getAllConfig();
    if (!isset($config['startDate'])) {
        errorResponse('系统尚未初始化，请先完成初始化', 400);
    }

    $totalPoints = (int)($config['totalPoints'] ?? 0);
    $currentDay = getCurrentDay($config['startDate']);

    // 获取整体统计
    $overallStats = getOverallStats();

    $rememberedCount = (int)$overallStats['completed_count'];
    $pendingCount = (int)$overallStats['pending_count'];
    $forgottenCount = (int)$overallStats['forgotten_count'];

    // 计算完成率（记得的题目数 / 总题目数）
    $completionRate = $totalPoints > 0 ? round(($rememberedCount / $totalPoints) * 100, 1) : 0;

    // 获取每天的统计
    $dailyStats = [];
    $stmt = $db->query("
        SELECT
            d.day,
            d.date,
            COUNT(p.id) as total,
            SUM(CASE WHEN p.status = 'remembered' THEN 1 ELSE 0 END) as remembered,
            SUM(CASE WHEN p.status = 'forgotten' THEN 1 ELSE 0 END) as forgotten,
            SUM(CASE WHEN p.status = 'pending' THEN 1 ELSE 0 END) as remaining
        FROM daily_assignments d
        LEFT JOIN points p ON p.assigned_day = d.day
        GROUP BY d.day, d.date
        ORDER BY d.day
    ");

    while ($row = $stmt->fetch()) {
        $day = (int)$row['day'];
        $dailyStats[$day] = [
            'date' => $row['date'],
            'total' => (int)$row['total'],
            'remembered' => (int)$row['remembered'],
            'forgotten' => (int)$row['forgotten'],
            'remaining' => (int)$row['remaining']
        ];
    }

    // 填充未分配的天数（如果有的话）
    for ($day = 1; $day <= 30; $day++) {
        if (!isset($dailyStats[$day])) {
            $dailyStats[$day] = [
                'date' => null,
                'total' => 0,
                'remembered' => 0,
                'forgotten' => 0,
                'remaining' => 0
            ];
        }
    }

    // 返回响应
    successResponse([
        'overview' => [
            'totalPoints' => $totalPoints,
            'completedCount' => $rememberedCount,
            'pendingCount' => $pendingCount,
            'forgottenCount' => $forgottenCount,
            'completionRate' => $completionRate,
            'currentDay' => $currentDay
        ],
        'dailyStats' => $dailyStats,
        'config' => $config
    ]);

} catch (Exception $e) {
    error_log('获取统计信息失败: ' . $e->getMessage());
    errorResponse('获取统计信息失败: ' . $e->getMessage(), 500);
}

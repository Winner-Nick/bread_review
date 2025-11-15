<?php
/**
 * 获取统计信息API
 * 返回：总知识点数、已完成数、完成率、各天统计等
 */

require_once 'common.php';

// ========== 主逻辑 ==========

// 读取题库和分配
$pool = readJsonFile(POINTS_POOL_FILE);
$assignments = readJsonFile(DAILY_ASSIGNMENTS_FILE);

if (!$pool) {
    errorResponse('无法读取题库文件: ' . POINTS_POOL_FILE . ' (请确认文件存在且已运行 generate_pool.py)', 500);
}

$totalPoints = $pool['config']['totalPoints'];
$currentDay = getCurrentDay($pool['config']['startDate']);

// 统计各状态的题目数
$rememberedCount = 0;
$pendingCount = 0;
$forgottenCount = 0;

foreach ($pool['points'] as $point) {
    switch ($point['status']) {
        case STATUS_REMEMBERED:
            $rememberedCount++;
            break;
        case STATUS_PENDING:
            $pendingCount++;
            break;
        case STATUS_FORGOTTEN:
            $forgottenCount++;
            break;
    }
}

// 计算完成率（记得的题目数 / 总题目数）
$completionRate = $totalPoints > 0 ? round(($rememberedCount / $totalPoints) * 100, 1) : 0;

// 统计每天的情况
$dailyStats = [];
if ($assignments) {
    for ($day = 1; $day <= 30; $day++) {
        $dayKey = 'day_' . $day;
        if (isset($assignments[$dayKey])) {
            $dayData = $assignments[$dayKey];
            $dailyStats[$day] = [
                'date' => $dayData['date'],
                'total' => count($dayData['pointIds']),
                'remembered' => count($dayData['completed']),
                'forgotten' => count($dayData['forgotten']),
                'remaining' => count($dayData['pointIds']) - count($dayData['completed'])
            ];
        } else {
            $dailyStats[$day] = [
                'date' => null,
                'total' => 0,
                'remembered' => 0,
                'forgotten' => 0,
                'remaining' => 0
            ];
        }
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
    'config' => $pool['config']
]);

<?php
/**
 * 获取指定天数的知识点API
 * 简化版：直接从每日分配中读取，不再动态分配
 */

require_once 'common.php';

// ========== 主逻辑 ==========

// 读取题库
$pool = readJsonFile(POINTS_POOL_FILE);
if (!$pool) {
    errorResponse('无法读取题库文件: ' . POINTS_POOL_FILE . ' (请先运行初始化)', 500);
}

// 获取参数
$day = isset($_GET['day']) ? intval($_GET['day']) : getCurrentDay($pool['config']['startDate']);

// 验证day参数
if ($day < 1 || $day > 30) {
    errorResponse('天数必须在1-30之间', 400);
}

// 读取每日分配
$assignments = readJsonFile(DAILY_ASSIGNMENTS_FILE);
if (!$assignments) {
    errorResponse('系统尚未初始化，请先完成初始化', 400);
}

$dayKey = 'day_' . $day;

// 检查该天是否有分配
if (!isset($assignments[$dayKey])) {
    errorResponse('该天尚未分配题目，请先完成系统初始化', 400);
}

$dayAssignment = $assignments[$dayKey];

// 获取该天的所有题目详情
$points = [];
foreach ($dayAssignment['pointIds'] as $pointId) {
    $point = getPointById($pointId, $pool);
    if ($point) {
        $points[] = $point;
    }
}

// 计算统计信息
$totalPoints = count($dayAssignment['pointIds']);
$completedCount = count($dayAssignment['completed']);
$forgottenCount = count($dayAssignment['forgotten']);
$remainingCount = $totalPoints - $completedCount;

// 返回响应
successResponse([
    'day' => $day,
    'date' => $dayAssignment['date'],
    'points' => $points,
    'totalPoints' => $totalPoints,
    'completedCount' => $completedCount,
    'forgottenCount' => $forgottenCount,
    'remainingCount' => $remainingCount,
    'currentIndex' => $dayAssignment['currentIndex']
]);

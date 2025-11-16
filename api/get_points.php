<?php
/**
 * 获取指定天数的知识点API
 * 直接从数据库读取
 */

require_once 'common.php';

try {
    // 获取配置
    $startDate = getConfig('startDate');
    if (!$startDate) {
        errorResponse('系统尚未初始化，请先完成初始化', 400);
    }

    // 获取参数
    $day = isset($_GET['day']) ? intval($_GET['day']) : getCurrentDay($startDate);

    // 验证day参数
    if ($day < 1 || $day > 30) {
        errorResponse('天数必须在1-30之间', 400);
    }

    // 获取该天的分配信息
    $assignment = getDailyAssignment($day);
    if (!$assignment) {
        errorResponse('该天尚未分配题目，请先完成系统初始化', 400);
    }

    // 获取该天的所有题目详情
    $points = getPointsByDay($day);

    // 获取统计信息
    $stats = getDailyStats($day);

    // 返回响应
    successResponse([
        'day' => $day,
        'date' => $assignment['date'],
        'points' => $points,
        'totalPoints' => (int)$stats['total_points'],
        'completedCount' => (int)$stats['completed_count'],
        'forgottenCount' => (int)$stats['forgotten_count'],
        'remainingCount' => (int)$stats['remaining_count'],
        'currentIndex' => $assignment['currentIndex']
    ]);

} catch (Exception $e) {
    error_log('获取知识点失败: ' . $e->getMessage());
    errorResponse('获取知识点失败: ' . $e->getMessage(), 500);
}

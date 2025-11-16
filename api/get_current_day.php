<?php
/**
 * 获取当前天数API
 * 根据起始日期计算今天是第几天
 */

require_once 'common.php';

try {
    // 获取配置
    $config = getAllConfig();

    if (!isset($config['startDate'])) {
        errorResponse('系统尚未初始化，请先完成初始化', 400);
    }

    $startDate = $config['startDate'];
    $totalDays = isset($config['totalDays']) ? (int)$config['totalDays'] : 30;

    // 计算当前天数
    $currentDay = getCurrentDay($startDate);

    // 计算日期信息
    $startDateTime = new DateTime($startDate);
    $currentDateTime = clone $startDateTime;
    $currentDateTime->modify('+' . ($currentDay - 1) . ' days');
    $currentDate = $currentDateTime->format('Y-m-d');

    $today = new DateTime('today');
    $isToday = ($today->format('Y-m-d') === $currentDate);

    // 返回响应
    successResponse([
        'currentDay' => $currentDay,
        'currentDate' => $currentDate,
        'startDate' => $startDate,
        'totalDays' => $totalDays,
        'isToday' => $isToday,
        'daysRemaining' => $totalDays - $currentDay
    ]);

} catch (Exception $e) {
    error_log('获取当前天数失败: ' . $e->getMessage());
    errorResponse('获取当前天数失败: ' . $e->getMessage(), 500);
}

<?php
/**
 * 获取当前天数API
 * 根据起始日期计算今天是第几天
 */

require_once 'common.php';

// ========== 主逻辑 ==========

// 读取题库配置
$pool = readJsonFile(POINTS_POOL_FILE);
if (!$pool) {
    errorResponse('无法读取题库文件: ' . POINTS_POOL_FILE . ' (请确认文件存在且已运行 generate_pool.py)', 500);
}

$config = $pool['config'];
$startDate = $config['startDate'];
$totalDays = $config['totalDays'];

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

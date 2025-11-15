<?php
/**
 * 获取系统初始化状态API
 * 检查系统是否已完成初始化
 */

require_once 'common.php';

// 读取每日分配文件
$assignments = readJsonFile(DAILY_ASSIGNMENTS_FILE);

// 检查是否已初始化
$isInitialized = false;
$initInfo = null;

if ($assignments && isset($assignments['meta']) && isset($assignments['day_1'])) {
    $isInitialized = true;
    $initInfo = $assignments['meta'];
}

// 返回响应
successResponse([
    'isInitialized' => $isInitialized,
    'initInfo' => $initInfo
]);

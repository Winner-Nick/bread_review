<?php
/**
 * 获取系统初始化状态API
 * 检查系统是否已完成初始化
 */

require_once 'common.php';

try {
    $db = getDB();

    // 检查是否已初始化（检查是否有每日分配数据）
    $stmt = $db->query("SELECT COUNT(*) as count FROM daily_assignments");
    $result = $stmt->fetch();
    $assignmentCount = (int)$result['count'];

    $isInitialized = $assignmentCount > 0;

    // 获取初始化信息
    $initInfo = null;
    if ($isInitialized) {
        $config = getAllConfig();
        $initInfo = [
            'startDate' => $config['startDate'] ?? null,
            'totalDays' => $config['totalDays'] ?? null,
            'totalPoints' => $config['totalPoints'] ?? null,
            'lastUpdated' => $config['lastUpdated'] ?? null
        ];
    }

    // 返回响应
    successResponse([
        'isInitialized' => $isInitialized,
        'initInfo' => $initInfo
    ]);

} catch (Exception $e) {
    error_log('获取初始化状态失败: ' . $e->getMessage());
    errorResponse('获取初始化状态失败: ' . $e->getMessage(), 500);
}

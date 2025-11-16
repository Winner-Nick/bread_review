<?php
/**
 * 系统重置API
 * 功能：
 * 1. 删除每日分配数据
 * 2. 重置题库中所有题目状态为pending
 * 3. 清空所有学习记录
 *
 * 注意：这是一个危险操作，会清空所有学习进度！
 */

require_once 'common.php';

// 只接受POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('只支持POST请求', 405);
}

// 获取POST数据（用于验证）
$input = json_decode(file_get_contents('php://input'), true);
$confirm = isset($input['confirm']) ? $input['confirm'] : false;

// 验证确认标志
if ($confirm !== true) {
    errorResponse('请确认要执行重置操作', 400);
}

try {
    $db = getDB();

    // 开始事务
    $db->beginTransaction();

    // ========== 执行重置 ==========

    // 1. 清空每日分配
    $db->exec("DELETE FROM daily_assignments");

    // 2. 重置所有题目状态
    $db->exec("
        UPDATE points
        SET status = 'pending',
            assigned_day = NULL,
            completed_at = NULL,
            forgotten_count = 0,
            updated_at = datetime('now')
    ");

    // 3. 清空历史记录
    $db->exec("DELETE FROM point_history");

    // 4. 更新配置
    setConfig('lastUpdated', date('Y-m-d\TH:i:s'));
    setConfig('resetAt', date('Y-m-d\TH:i:s'));

    // 提交事务
    $db->commit();

    // 返回成功响应
    successResponse([
        'resetAt' => date('Y-m-d\TH:i:s'),
        'message' => '系统已成功重置'
    ], '系统重置成功');

} catch (Exception $e) {
    // 回滚事务
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('系统重置失败: ' . $e->getMessage());
    errorResponse('系统重置失败: ' . $e->getMessage(), 500);
}

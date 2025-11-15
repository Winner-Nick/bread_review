<?php
/**
 * 系统重置API
 * 功能：
 * 1. 删除每日分配数据（daily_assignments.json）
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

// ========== 执行重置 ==========

// 1. 重置每日分配文件
$emptyAssignments = [
    'meta' => [
        'lastUpdated' => date('Y-m-d\TH:i:s'),
        'resetAt' => date('Y-m-d\TH:i:s')
    ]
];

if (!writeJsonFile(DAILY_ASSIGNMENTS_FILE, $emptyAssignments)) {
    errorResponse('无法重置每日分配文件，请检查文件权限', 500);
}

// 2. 重置题库中所有题目状态
$pool = readJsonFile(POINTS_POOL_FILE);
if ($pool) {
    // 重置所有题目
    foreach ($pool['points'] as &$point) {
        $point['status'] = STATUS_PENDING;
        $point['assignedDay'] = null;
        $point['completedAt'] = null;
        $point['forgottenCount'] = 0;
        $point['history'] = [];
    }
    unset($point);

    // 更新配置
    $pool['config']['lastUpdated'] = date('Y-m-d\TH:i:s');
    $pool['config']['resetAt'] = date('Y-m-d\TH:i:s');

    // 保存题库
    if (!writeJsonFile(POINTS_POOL_FILE, $pool)) {
        errorResponse('无法重置题库文件，请检查文件权限', 500);
    }
}

// 返回成功响应
successResponse([
    'resetAt' => date('Y-m-d\TH:i:s'),
    'message' => '系统已成功重置'
], '系统重置成功');

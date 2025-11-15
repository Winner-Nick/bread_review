<?php
/**
 * 标记知识点状态API
 * 简化版：仅在JSON中标记状态，不重新分配题目
 * 支持的操作：
 * - remember: 记得（标记为remembered）
 * - forget: 忘记（标记为forgotten）
 */

require_once 'common.php';

// 只接受POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('只支持POST请求', 405);
}

// 获取POST数据
$input = json_decode(file_get_contents('php://input'), true);

$pointId = isset($input['pointId']) ? intval($input['pointId']) : 0;
$action = isset($input['action']) ? $input['action'] : '';
$day = isset($input['day']) ? intval($input['day']) : 0;

// 验证参数
if ($pointId <= 0) {
    errorResponse('无效的题目ID', 400);
}

if (!in_array($action, ['remember', 'forget'])) {
    errorResponse('无效的操作类型（仅支持 remember 和 forget）', 400);
}

// 读取题库和分配
$pool = readJsonFile(POINTS_POOL_FILE);
$assignments = readJsonFile(DAILY_ASSIGNMENTS_FILE);

if (!$pool || !$assignments) {
    errorResponse('无法读取数据文件', 500);
}

// 如果未指定day，使用当前天数
if ($day <= 0) {
    $day = getCurrentDay($pool['config']['startDate']);
}

$dayKey = 'day_' . $day;

// 确保该天的分配存在
if (!isset($assignments[$dayKey])) {
    errorResponse('该天的分配不存在，请先完成系统初始化', 400);
}

// ========== 执行标记操作 ==========

if ($action === 'remember') {
    // 标记"记得"
    // 1. 更新题库状态
    foreach ($pool['points'] as &$point) {
        if ($point['id'] == $pointId) {
            $point['status'] = STATUS_REMEMBERED;
            $point['completedAt'] = date('Y-m-d H:i:s');
            $point['history'][] = [
                'action' => 'remembered',
                'timestamp' => date('Y-m-d H:i:s'),
                'day' => $day
            ];
            break;
        }
    }
    unset($point);

    // 2. 更新每日分配：添加到completed列表
    if (!in_array($pointId, $assignments[$dayKey]['completed'])) {
        $assignments[$dayKey]['completed'][] = $pointId;
    }

    // 3. 从forgotten列表中移除（如果存在）
    $assignments[$dayKey]['forgotten'] = array_values(
        array_diff($assignments[$dayKey]['forgotten'], [$pointId])
    );

} elseif ($action === 'forget') {
    // 标记"忘记"
    // 1. 更新题库状态
    foreach ($pool['points'] as &$point) {
        if ($point['id'] == $pointId) {
            $point['status'] = STATUS_FORGOTTEN;
            $point['forgottenCount']++;
            $point['history'][] = [
                'action' => 'forgotten',
                'timestamp' => date('Y-m-d H:i:s'),
                'day' => $day
            ];
            break;
        }
    }
    unset($point);

    // 2. 更新每日分配：添加到forgotten列表
    if (!in_array($pointId, $assignments[$dayKey]['forgotten'])) {
        $assignments[$dayKey]['forgotten'][] = $pointId;
    }

    // 3. 从completed列表中移除（如果存在）
    $assignments[$dayKey]['completed'] = array_values(
        array_diff($assignments[$dayKey]['completed'], [$pointId])
    );
}

// 更新lastUpdated时间
$pool['config']['lastUpdated'] = date('Y-m-d\TH:i:s');
$assignments['meta']['lastUpdated'] = date('Y-m-d\TH:i:s');

// 保存更新
if (!writeJsonFile(POINTS_POOL_FILE, $pool)) {
    errorResponse('无法保存题库文件，请检查文件权限', 500);
}

if (!writeJsonFile(DAILY_ASSIGNMENTS_FILE, $assignments)) {
    errorResponse('无法保存每日分配文件，请检查文件权限', 500);
}

// 返回成功响应
successResponse([
    'pointId' => $pointId,
    'action' => $action,
    'day' => $day
], '操作成功');

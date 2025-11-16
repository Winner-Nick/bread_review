<?php
/**
 * 标记知识点状态API
 * 在数据库中标记状态
 * 支持的操作：
 * - remember: 记得（标记为remembered）
 * - forget: 忘记（标记为forgotten）
 * - cancel: 取消标记（恢复为pending状态）
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

if (!in_array($action, ['remember', 'forget', 'cancel'])) {
    errorResponse('无效的操作类型（仅支持 remember、forget 和 cancel）', 400);
}

try {
    $db = getDB();

    // 获取知识点以验证存在性
    $point = getPointById($pointId);
    if (!$point) {
        errorResponse('题目不存在', 404);
    }

    // 如果未指定day，使用当前天数
    if ($day <= 0) {
        $startDate = getConfig('startDate');
        if (!$startDate) {
            errorResponse('系统尚未初始化', 400);
        }
        $day = getCurrentDay($startDate);
    }

    // 验证该天的分配存在
    $assignment = getDailyAssignment($day);
    if (!$assignment) {
        errorResponse('该天的分配不存在，请先完成系统初始化', 400);
    }

    // 开始事务
    $db->beginTransaction();

    // ========== 执行标记操作 ==========

    if ($action === 'remember') {
        // 标记"记得"
        updatePointStatus($pointId, STATUS_REMEMBERED);
        addPointHistory($pointId, 'remembered', $day);

    } elseif ($action === 'forget') {
        // 标记"忘记"
        // 获取当前forgottenCount并增加
        $currentCount = $point['forgottenCount'];
        $newCount = $currentCount + 1;
        updatePointStatus($pointId, STATUS_FORGOTTEN, $newCount);
        addPointHistory($pointId, 'forgotten', $day);

    } elseif ($action === 'cancel') {
        // 取消标记，恢复为pending状态
        // 如果之前是forgotten状态，需要减少forgottenCount
        $newCount = null;
        if ($point['status'] === STATUS_FORGOTTEN && $point['forgottenCount'] > 0) {
            $newCount = $point['forgottenCount'] - 1;
        }
        updatePointStatusToPending($pointId, $newCount);
        addPointHistory($pointId, 'cancelled', $day);
    }

    // 提交事务
    $db->commit();

    // 返回成功响应
    successResponse([
        'pointId' => $pointId,
        'action' => $action,
        'day' => $day
    ], '操作成功');

} catch (Exception $e) {
    // 回滚事务
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('标记知识点失败: ' . $e->getMessage());
    errorResponse('标记知识点失败: ' . $e->getMessage(), 500);
}

<?php
/**
 * 系统初始化API
 * 功能：
 * 1. 设置学习开始日期
 * 2. 一次性将所有题目平均分配到30天
 * 3. 清空之前的所有学习记录
 */

require_once 'common.php';

// 只接受POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('只支持POST请求', 405);
}

// 获取POST数据
$input = json_decode(file_get_contents('php://input'), true);
$startDate = isset($input['startDate']) ? trim($input['startDate']) : '';

// 验证日期格式
if (empty($startDate)) {
    errorResponse('请提供开始日期', 400);
}

// 验证日期格式 (YYYY-MM-DD)
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
    errorResponse('日期格式错误，请使用 YYYY-MM-DD 格式', 400);
}

// 验证日期有效性
try {
    $dateObj = new DateTime($startDate);
} catch (Exception $e) {
    errorResponse('无效的日期: ' . $startDate, 400);
}

// 读取题库
$pool = readJsonFile(POINTS_POOL_FILE);
if (!$pool || !isset($pool['points'])) {
    errorResponse('无法读取题库文件，请先运行 generate_pool.py 生成题库', 500);
}

// ========== 执行初始化 ==========

// 1. 更新配置中的开始日期
$pool['config']['startDate'] = $startDate;
$pool['config']['lastUpdated'] = date('Y-m-d\TH:i:s');

// 2. 重置所有题目状态
foreach ($pool['points'] as &$point) {
    $point['status'] = STATUS_PENDING;
    $point['assignedDay'] = null;
    $point['completedAt'] = null;
    $point['forgottenCount'] = 0;
    $point['history'] = [];
}
unset($point);

// 3. 获取所有题目ID并打乱
$allPointIds = array_map(function($p) { return $p['id']; }, $pool['points']);

// 4. 平均分配到30天
$totalDays = 30;
$totalPoints = count($allPointIds);
$avgPointsPerDay = (int)($totalPoints / $totalDays);
$remainder = $totalPoints % $totalDays;

$assignments = [
    'meta' => [
        'lastUpdated' => date('Y-m-d\TH:i:s'),
        'initializedAt' => date('Y-m-d\TH:i:s'),
        'startDate' => $startDate
    ]
];

$currentIndex = 0;
for ($day = 1; $day <= $totalDays; $day++) {
    // 计算该天日期
    $dayDate = clone $dateObj;
    $dayDate->modify('+' . ($day - 1) . ' days');

    // 计算该天应分配的题目数（前几天多分配余数）
    $pointsThisDay = $avgPointsPerDay;
    if ($day <= $remainder) {
        $pointsThisDay++;
    }

    // 分配题目ID
    $dayPointIds = array_slice($allPointIds, $currentIndex, $pointsThisDay);
    $currentIndex += $pointsThisDay;

    // 更新题库中的assignedDay
    foreach ($pool['points'] as &$point) {
        if (in_array($point['id'], $dayPointIds)) {
            $point['assignedDay'] = $day;
        }
    }
    unset($point);

    // 创建该天的分配记录
    $assignments['day_' . $day] = [
        'date' => $dayDate->format('Y-m-d'),
        'pointIds' => $dayPointIds,
        'currentIndex' => 0,
        'completed' => [],
        'forgotten' => []
    ];
}

// 5. 保存更新
if (!writeJsonFile(POINTS_POOL_FILE, $pool)) {
    errorResponse('无法保存题库文件，请检查文件权限', 500);
}

if (!writeJsonFile(DAILY_ASSIGNMENTS_FILE, $assignments)) {
    errorResponse('无法保存每日分配文件，请检查文件权限', 500);
}

// 6. 返回成功响应
successResponse([
    'startDate' => $startDate,
    'totalDays' => $totalDays,
    'totalPoints' => $totalPoints,
    'avgPointsPerDay' => $avgPointsPerDay,
    'distributionComplete' => true
], '系统初始化成功');

<?php
/**
 * API 共用函数库
 * 包含所有API接口的通用工具函数
 */

// 时区设置
@date_default_timezone_set('Asia/Shanghai');

// ========== 文件路径常量 ==========
define('DATA_DIR', __DIR__ . '/../data');
define('POINTS_POOL_FILE', DATA_DIR . '/points_pool.json');
define('DAILY_ASSIGNMENTS_FILE', DATA_DIR . '/daily_assignments.json');

// ========== 状态常量 ==========
define('STATUS_PENDING', 'pending');           // 未学习
define('STATUS_REMEMBERED', 'remembered');     // 记得
define('STATUS_FORGOTTEN', 'forgotten');       // 忘记

// ========== 文件操作函数 ==========

/**
 * 读取JSON文件（带文件锁和权限检查）
 *
 * @param string $filepath 文件路径
 * @return array|null 返回解析后的数组，失败返回null
 */
function readJsonFile($filepath) {
    if (!file_exists($filepath)) {
        error_log("文件不存在: $filepath");
        return null;
    }

    // 检查文件可读性
    if (!is_readable($filepath)) {
        error_log("文件不可读: $filepath, 当前权限: " . decoct(fileperms($filepath) & 0777));
        return null;
    }

    $fp = @fopen($filepath, 'r');
    if (!$fp) {
        error_log("无法打开文件读取: $filepath, 错误: " . error_get_last()['message']);
        return null;
    }

    // 共享锁（读锁）
    flock($fp, LOCK_SH);

    $filesize = filesize($filepath);
    if ($filesize === 0) {
        flock($fp, LOCK_UN);
        fclose($fp);
        return null;
    }

    $content = fread($fp, $filesize);
    flock($fp, LOCK_UN);
    fclose($fp);

    return json_decode($content, true);
}

/**
 * 写入JSON文件（带文件锁和权限检查）
 *
 * @param string $filepath 文件路径
 * @param array $data 要写入的数据
 * @return bool 成功返回true，失败返回false
 */
function writeJsonFile($filepath, $data) {
    // 检查目录权限
    $dir = dirname($filepath);
    if (!is_writable($dir)) {
        error_log("目录不可写: $dir, 当前权限: " . decoct(fileperms($dir) & 0777));
        return false;
    }

    // 如果文件存在，检查文件权限
    if (file_exists($filepath) && !is_writable($filepath)) {
        error_log("文件不可写: $filepath, 当前权限: " . decoct(fileperms($filepath) & 0777));
        return false;
    }

    $fp = @fopen($filepath, 'w');
    if (!$fp) {
        $lastError = error_get_last();
        $errorMsg = $lastError ? $lastError['message'] : '未知错误';
        error_log("无法打开文件写入: $filepath, 错误: $errorMsg");
        return false;
    }

    // 排他锁（写锁）
    flock($fp, LOCK_EX);
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    fwrite($fp, $json);
    flock($fp, LOCK_UN);
    fclose($fp);

    return true;
}

// ========== HTTP响应函数 ==========

/**
 * 返回JSON响应并终止脚本
 *
 * @param array $data 响应数据
 * @param int $statusCode HTTP状态码
 */
function jsonResponse($data, $statusCode = 200) {
    // 清理之前可能的任何输出
    if (ob_get_level()) {
        ob_clean();
    }

    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');

    // 禁用缓存（关键！防止浏览器缓存API响应）
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
    header('Expires: 0');

    echo json_encode($data, JSON_UNESCAPED_UNICODE);

    // 终止脚本，避免后续任何输出
    exit;
}

/**
 * 返回成功响应
 *
 * @param array $data 响应数据
 * @param string $message 成功消息
 */
function successResponse($data = [], $message = 'Success') {
    jsonResponse([
        'success' => true,
        'message' => $message,
        'data' => $data
    ]);
}

/**
 * 返回错误响应
 *
 * @param string $message 错误消息
 * @param int $code HTTP状态码
 */
function errorResponse($message, $code = 400) {
    jsonResponse([
        'success' => false,
        'error' => $message,
        'code' => $code
    ], $code);
}

// ========== 业务逻辑函数 ==========

/**
 * 计算当前是第几天
 *
 * @param string $startDate 开始日期 (格式: Y-m-d)
 * @return int 当前天数 (1-30)
 */
function getCurrentDay($startDate) {
    $start = new DateTime($startDate);
    $today = new DateTime('today');
    $interval = $start->diff($today);

    $dayNumber = $interval->days + 1;
    return max(1, min($dayNumber, 30));
}

/**
 * 根据ID获取知识点
 *
 * @param int $pointId 知识点ID
 * @param array $pool 题库数据
 * @return array|null 知识点数据，未找到返回null
 */
function getPointById($pointId, $pool) {
    if (!$pool || !isset($pool['points'])) {
        return null;
    }

    foreach ($pool['points'] as $point) {
        if ($point['id'] == $pointId) {
            return $point;
        }
    }

    return null;
}

/**
 * 获取题库配置
 *
 * @return array|null 配置数据，失败返回null
 */
function getPoolConfig() {
    $pool = readJsonFile(POINTS_POOL_FILE);
    if (!$pool || !isset($pool['config'])) {
        return null;
    }
    return $pool['config'];
}

/**
 * 更新题库配置
 *
 * @param array $newConfig 新的配置数据
 * @return bool 成功返回true，失败返回false
 */
function updatePoolConfig($newConfig) {
    $pool = readJsonFile(POINTS_POOL_FILE);
    if (!$pool) {
        return false;
    }

    $pool['config'] = array_merge($pool['config'], $newConfig);
    return writeJsonFile(POINTS_POOL_FILE, $pool);
}

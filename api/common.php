<?php
/**
 * API 共用函数库
 * 包含所有API接口的通用工具函数
 * 使用SQLite数据库代替JSON文件
 */

// 时区设置
@date_default_timezone_set('Asia/Shanghai');

// ========== 路径常量 ==========
define('DB_FILE', __DIR__ . '/../database/bread_review.db');

// ========== 状态常量 ==========
define('STATUS_PENDING', 'pending');           // 未学习
define('STATUS_REMEMBERED', 'remembered');     // 记得
define('STATUS_FORGOTTEN', 'forgotten');       // 忘记

// ========== 数据库连接 ==========

/**
 * 获取数据库连接
 *
 * @return PDO 数据库连接对象
 * @throws Exception 连接失败时抛出异常
 */
function getDB() {
    static $db = null;

    if ($db === null) {
        if (!file_exists(DB_FILE)) {
            throw new Exception('数据库文件不存在，请先运行 database/init_db.php 初始化数据库');
        }

        try {
            $db = new PDO('sqlite:' . DB_FILE);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            // 启用外键约束
            $db->exec('PRAGMA foreign_keys = ON');
        } catch (PDOException $e) {
            error_log('数据库连接失败: ' . $e->getMessage());
            throw new Exception('数据库连接失败');
        }
    }

    return $db;
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

// ========== 配置相关函数 ==========

/**
 * 获取配置值
 *
 * @param string $key 配置键名
 * @return string|null 配置值，不存在返回null
 */
function getConfig($key) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT value FROM config WHERE key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        return $result ? $result['value'] : null;
    } catch (Exception $e) {
        error_log("获取配置失败 ($key): " . $e->getMessage());
        return null;
    }
}

/**
 * 设置配置值
 *
 * @param string $key 配置键名
 * @param string $value 配置值
 * @return bool 成功返回true
 */
function setConfig($key, $value) {
    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT OR REPLACE INTO config (key, value, updated_at) VALUES (?, ?, datetime('now'))");
        $stmt->execute([$key, $value]);
        return true;
    } catch (Exception $e) {
        error_log("设置配置失败 ($key): " . $e->getMessage());
        return false;
    }
}

/**
 * 获取所有配置
 *
 * @return array 配置数组
 */
function getAllConfig() {
    try {
        $db = getDB();
        $stmt = $db->query("SELECT key, value FROM config");
        $configs = [];
        while ($row = $stmt->fetch()) {
            $configs[$row['key']] = $row['value'];
        }
        return $configs;
    } catch (Exception $e) {
        error_log("获取所有配置失败: " . $e->getMessage());
        return [];
    }
}

// ========== 知识点相关函数 ==========

/**
 * 根据ID获取知识点
 *
 * @param int $pointId 知识点ID
 * @return array|null 知识点数据，未找到返回null
 */
function getPointById($pointId) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM points WHERE id = ?");
        $stmt->execute([$pointId]);
        $point = $stmt->fetch();

        if (!$point) {
            return null;
        }

        // 转换字段名以兼容旧的JSON格式
        return [
            'id' => (int)$point['id'],
            '科目' => $point['subject'],
            '章节' => $point['chapter'],
            '考点' => $point['point'],
            '页码' => $point['page'],
            'status' => $point['status'],
            'assignedDay' => $point['assigned_day'] ? (int)$point['assigned_day'] : null,
            'completedAt' => $point['completed_at'],
            'forgottenCount' => (int)$point['forgotten_count']
        ];
    } catch (Exception $e) {
        error_log("获取知识点失败 ($pointId): " . $e->getMessage());
        return null;
    }
}

/**
 * 获取指定天数的所有知识点
 *
 * @param int $day 天数 (1-30)
 * @return array 知识点数组
 */
function getPointsByDay($day) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM points WHERE assigned_day = ? ORDER BY id");
        $stmt->execute([$day]);
        $points = [];

        while ($row = $stmt->fetch()) {
            $points[] = [
                'id' => (int)$row['id'],
                '科目' => $row['subject'],
                '章节' => $row['chapter'],
                '考点' => $row['point'],
                '页码' => $row['page'],
                'status' => $row['status'],
                'assignedDay' => (int)$row['assigned_day'],
                'completedAt' => $row['completed_at'],
                'forgottenCount' => (int)$row['forgotten_count']
            ];
        }

        return $points;
    } catch (Exception $e) {
        error_log("获取每日知识点失败 (day=$day): " . $e->getMessage());
        return [];
    }
}

/**
 * 更新知识点状态
 *
 * @param int $pointId 知识点ID
 * @param string $status 状态
 * @param int|null $forgottenCount 忘记次数（可选）
 * @return bool 成功返回true
 */
function updatePointStatus($pointId, $status, $forgottenCount = null) {
    try {
        $db = getDB();

        $sql = "UPDATE points SET status = ?, updated_at = datetime('now')";
        $params = [$status];

        if ($status === STATUS_REMEMBERED) {
            $sql .= ", completed_at = datetime('now')";
        }

        if ($forgottenCount !== null) {
            $sql .= ", forgotten_count = ?";
            $params[] = $forgottenCount;
        }

        $sql .= " WHERE id = ?";
        $params[] = $pointId;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return true;
    } catch (Exception $e) {
        error_log("更新知识点状态失败 ($pointId): " . $e->getMessage());
        return false;
    }
}

/**
 * 添加知识点历史记录
 *
 * @param int $pointId 知识点ID
 * @param string $action 操作类型
 * @param int|null $day 天数
 * @return bool 成功返回true
 */
function addPointHistory($pointId, $action, $day = null) {
    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO point_history (point_id, action, day, timestamp) VALUES (?, ?, ?, datetime('now'))");
        $stmt->execute([$pointId, $action, $day]);
        return true;
    } catch (Exception $e) {
        error_log("添加历史记录失败 ($pointId): " . $e->getMessage());
        return false;
    }
}

// ========== 每日分配相关函数 ==========

/**
 * 获取指定天数的分配信息
 *
 * @param int $day 天数 (1-30)
 * @return array|null 分配信息，未找到返回null
 */
function getDailyAssignment($day) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM daily_assignments WHERE day = ?");
        $stmt->execute([$day]);
        $assignment = $stmt->fetch();

        if (!$assignment) {
            return null;
        }

        // 解析point_ids JSON
        $pointIds = json_decode($assignment['point_ids'], true);

        return [
            'day' => (int)$assignment['day'],
            'date' => $assignment['date'],
            'pointIds' => $pointIds ?: [],
            'currentIndex' => (int)$assignment['current_index']
        ];
    } catch (Exception $e) {
        error_log("获取每日分配失败 (day=$day): " . $e->getMessage());
        return null;
    }
}

/**
 * 更新每日分配的currentIndex
 *
 * @param int $day 天数
 * @param int $currentIndex 当前索引
 * @return bool 成功返回true
 */
function updateDailyCurrentIndex($day, $currentIndex) {
    try {
        $db = getDB();
        $stmt = $db->prepare("UPDATE daily_assignments SET current_index = ?, updated_at = datetime('now') WHERE day = ?");
        $stmt->execute([$currentIndex, $day]);
        return true;
    } catch (Exception $e) {
        error_log("更新currentIndex失败 (day=$day): " . $e->getMessage());
        return false;
    }
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
 * 获取每日统计信息
 *
 * @param int $day 天数
 * @return array 统计信息
 */
function getDailyStats($day) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT
                COUNT(*) as total_points,
                SUM(CASE WHEN status = 'remembered' THEN 1 ELSE 0 END) as completed_count,
                SUM(CASE WHEN status = 'forgotten' THEN 1 ELSE 0 END) as forgotten_count,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as remaining_count
            FROM points
            WHERE assigned_day = ?
        ");
        $stmt->execute([$day]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("获取每日统计失败 (day=$day): " . $e->getMessage());
        return [
            'total_points' => 0,
            'completed_count' => 0,
            'forgotten_count' => 0,
            'remaining_count' => 0
        ];
    }
}

/**
 * 获取整体统计信息
 *
 * @return array 统计信息
 */
function getOverallStats() {
    try {
        $db = getDB();
        $stmt = $db->query("SELECT * FROM overall_stats");
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("获取整体统计失败: " . $e->getMessage());
        return [
            'total_points' => 0,
            'completed_count' => 0,
            'forgotten_count' => 0,
            'pending_count' => 0,
            'total_forgotten_times' => 0
        ];
    }
}

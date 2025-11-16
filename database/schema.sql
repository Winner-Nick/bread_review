-- ===================================
-- 考研知识点背诵系统 - 数据库Schema
-- 数据库类型: SQLite
-- ===================================

-- 系统配置表
CREATE TABLE IF NOT EXISTS config (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    key TEXT UNIQUE NOT NULL,
    value TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 插入默认配置
INSERT OR IGNORE INTO config (key, value) VALUES
    ('startDate', '2025-11-14'),
    ('totalDays', '30'),
    ('totalPoints', '0'),
    ('avgPointsPerDay', '0'),
    ('createdAt', datetime('now')),
    ('lastUpdated', datetime('now'));

-- 知识点表
CREATE TABLE IF NOT EXISTS points (
    id INTEGER PRIMARY KEY,
    subject TEXT NOT NULL,           -- 科目
    chapter TEXT NOT NULL,           -- 章节
    point TEXT NOT NULL,             -- 考点
    page TEXT,                       -- 页码
    status TEXT DEFAULT 'pending',   -- pending, remembered, forgotten
    assigned_day INTEGER,            -- 分配到第几天 (1-30)
    completed_at TIMESTAMP,          -- 完成时间
    forgotten_count INTEGER DEFAULT 0, -- 忘记次数
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 创建索引以提高查询性能
CREATE INDEX IF NOT EXISTS idx_points_status ON points(status);
CREATE INDEX IF NOT EXISTS idx_points_assigned_day ON points(assigned_day);

-- 每日分配表
CREATE TABLE IF NOT EXISTS daily_assignments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    day INTEGER NOT NULL UNIQUE,     -- 天数 (1-30)
    date TEXT NOT NULL,              -- 日期 (YYYY-MM-DD)
    point_ids TEXT NOT NULL,         -- JSON数组，存储该天的题目ID
    current_index INTEGER DEFAULT 0, -- 当前进度索引
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 创建索引
CREATE INDEX IF NOT EXISTS idx_daily_assignments_day ON daily_assignments(day);
CREATE INDEX IF NOT EXISTS idx_daily_assignments_date ON daily_assignments(date);

-- 操作历史表
CREATE TABLE IF NOT EXISTS point_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    point_id INTEGER NOT NULL,
    action TEXT NOT NULL,            -- remembered, forgotten, reset
    day INTEGER,                     -- 操作发生在第几天
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (point_id) REFERENCES points(id)
);

-- 创建索引
CREATE INDEX IF NOT EXISTS idx_point_history_point_id ON point_history(point_id);
CREATE INDEX IF NOT EXISTS idx_point_history_timestamp ON point_history(timestamp);

-- 创建视图：每日统计
CREATE VIEW IF NOT EXISTS daily_stats AS
SELECT
    d.day,
    d.date,
    COUNT(DISTINCT p.id) as total_points,
    SUM(CASE WHEN p.status = 'remembered' THEN 1 ELSE 0 END) as completed_count,
    SUM(CASE WHEN p.status = 'forgotten' THEN 1 ELSE 0 END) as forgotten_count,
    SUM(CASE WHEN p.status = 'pending' THEN 1 ELSE 0 END) as remaining_count
FROM daily_assignments d
LEFT JOIN points p ON p.assigned_day = d.day
GROUP BY d.day, d.date;

-- 创建视图：整体进度统计
CREATE VIEW IF NOT EXISTS overall_stats AS
SELECT
    COUNT(*) as total_points,
    SUM(CASE WHEN status = 'remembered' THEN 1 ELSE 0 END) as completed_count,
    SUM(CASE WHEN status = 'forgotten' THEN 1 ELSE 0 END) as forgotten_count,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
    SUM(forgotten_count) as total_forgotten_times
FROM points;

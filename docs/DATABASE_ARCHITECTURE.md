# 数据库架构设计

## 概述

本系统使用SQLite作为数据库，提供轻量级、高性能的数据存储解决方案。

## 数据库选择：为什么使用SQLite？

1. **零配置** - 无需独立的数据库服务器
2. **轻量级** - 整个数据库就是一个文件
3. **高性能** - 对于中小型应用性能优秀
4. **事务支持** - 完整的ACID支持
5. **跨平台** - 在所有主流平台上运行
6. **易于部署** - 不需要额外的安装和配置

## 表结构设计

### 1. config 表 - 系统配置
存储系统级别的配置信息。

```sql
CREATE TABLE config (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    key TEXT UNIQUE NOT NULL,      -- 配置键
    value TEXT NOT NULL,            -- 配置值
    updated_at TIMESTAMP            -- 更新时间
);
```

**常用配置项**:
- `startDate` - 学习开始日期
- `totalDays` - 总天数 (30天)
- `totalPoints` - 总知识点数
- `avgPointsPerDay` - 平均每天知识点数

### 2. points 表 - 知识点
存储所有知识点的详细信息。

```sql
CREATE TABLE points (
    id INTEGER PRIMARY KEY,              -- 知识点ID
    subject TEXT NOT NULL,               -- 科目
    chapter TEXT NOT NULL,               -- 章节
    point TEXT NOT NULL,                 -- 考点内容
    page TEXT,                           -- 页码
    status TEXT DEFAULT 'pending',       -- 状态: pending/remembered/forgotten
    assigned_day INTEGER,                -- 分配到第几天 (1-30)
    completed_at TIMESTAMP,              -- 完成时间
    forgotten_count INTEGER DEFAULT 0,   -- 忘记次数
    created_at TIMESTAMP,                -- 创建时间
    updated_at TIMESTAMP                 -- 更新时间
);
```

**索引**:
- `idx_points_status` - 状态索引，加速按状态查询
- `idx_points_assigned_day` - 分配天数索引，加速每日查询

**状态说明**:
- `pending` - 未学习
- `remembered` - 记得
- `forgotten` - 忘记

### 3. daily_assignments 表 - 每日分配
存储每天分配的知识点信息。

```sql
CREATE TABLE daily_assignments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    day INTEGER NOT NULL UNIQUE,         -- 天数 (1-30)
    date TEXT NOT NULL,                  -- 日期 (YYYY-MM-DD)
    point_ids TEXT NOT NULL,             -- JSON数组，存储题目ID列表
    current_index INTEGER DEFAULT 0,     -- 当前学习进度索引
    created_at TIMESTAMP,                -- 创建时间
    updated_at TIMESTAMP                 -- 更新时间
);
```

**索引**:
- `idx_daily_assignments_day` - 天数索引
- `idx_daily_assignments_date` - 日期索引

**point_ids格式**:
JSON数组，例如: `[1, 2, 3, 4, 5]`

### 4. point_history 表 - 操作历史
记录知识点的所有操作历史。

```sql
CREATE TABLE point_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    point_id INTEGER NOT NULL,           -- 知识点ID
    action TEXT NOT NULL,                -- 操作类型
    day INTEGER,                         -- 操作发生在第几天
    timestamp TIMESTAMP,                 -- 操作时间
    FOREIGN KEY (point_id) REFERENCES points(id)
);
```

**索引**:
- `idx_point_history_point_id` - 知识点ID索引
- `idx_point_history_timestamp` - 时间索引

**操作类型**:
- `remembered` - 标记为记得
- `forgotten` - 标记为忘记
- `reset` - 重置

## 视图设计

### 1. daily_stats 视图 - 每日统计
提供每天的学习统计信息。

```sql
CREATE VIEW daily_stats AS
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
```

### 2. overall_stats 视图 - 整体统计
提供整体的学习统计信息。

```sql
CREATE VIEW overall_stats AS
SELECT
    COUNT(*) as total_points,
    SUM(CASE WHEN status = 'remembered' THEN 1 ELSE 0 END) as completed_count,
    SUM(CASE WHEN status = 'forgotten' THEN 1 ELSE 0 END) as forgotten_count,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
    SUM(forgotten_count) as total_forgotten_times
FROM points;
```

## 数据流程

### 1. 初始化流程
```
Excel文件 → Python脚本 → points表
↓
API initialize.php → 分配知识点到30天
↓
daily_assignments表 + 更新points.assigned_day
```

### 2. 学习流程
```
用户访问某天 → get_points.php
↓
查询 daily_assignments (获取该天的point_ids)
↓
查询 points (获取知识点详情)
↓
返回给前端展示
```

### 3. 标记流程
```
用户标记知识点 → mark_point.php
↓
更新 points.status
↓
记录 point_history
↓
返回成功
```

## 性能优化

### 1. 索引策略
- 为高频查询字段创建索引
- 避免过多索引影响写入性能
- 定期分析和优化索引

### 2. 查询优化
- 使用预处理语句
- 避免SELECT *，只查询需要的字段
- 使用视图简化复杂查询

### 3. 事务使用
- 批量操作使用事务
- 保证数据一致性
- 错误时自动回滚

### 4. 数据库维护
```sql
-- 优化数据库（回收空间）
VACUUM;

-- 分析查询计划
EXPLAIN QUERY PLAN SELECT * FROM points WHERE status = 'pending';

-- 更新统计信息
ANALYZE;
```

## 扩展性考虑

### 1. 添加新字段
```sql
-- 示例：添加难度等级
ALTER TABLE points ADD COLUMN difficulty TEXT DEFAULT 'medium';

-- 为新字段创建索引
CREATE INDEX idx_points_difficulty ON points(difficulty);
```

### 2. 添加新表
```sql
-- 示例：添加标签表
CREATE TABLE tags (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT UNIQUE NOT NULL
);

-- 添加关联表
CREATE TABLE point_tags (
    point_id INTEGER,
    tag_id INTEGER,
    PRIMARY KEY (point_id, tag_id),
    FOREIGN KEY (point_id) REFERENCES points(id),
    FOREIGN KEY (tag_id) REFERENCES tags(id)
);
```

### 3. 数据迁移
- 使用事务保证迁移安全
- 先备份后迁移
- 验证迁移结果

## 备份策略

### 1. 文件级备份
```bash
# 简单复制
cp database/bread_review.db database/backup_$(date +%Y%m%d).db

# 使用SQLite备份命令
sqlite3 database/bread_review.db ".backup database/backup.db"
```

### 2. 导出SQL
```bash
# 导出为SQL
sqlite3 database/bread_review.db .dump > backup.sql

# 从SQL恢复
sqlite3 database/bread_review.db < backup.sql
```

### 3. 自动备份
```bash
# crontab示例 - 每天凌晨2点备份
0 2 * * * cp /path/to/database/bread_review.db /path/to/backup/bread_review_$(date +\%Y\%m\%d).db
```

## 安全性考虑

### 1. SQL注入防护
- 使用PDO预处理语句
- 永远不要直接拼接SQL

### 2. 文件权限
```bash
# 数据库文件
chmod 666 database/bread_review.db

# 目录权限
chmod 777 database/
```

### 3. Web访问控制
在Web服务器配置中阻止直接访问数据库文件：

**Apache (.htaccess)**:
```apache
<Files "*.db">
    Require all denied
</Files>
```

**Nginx**:
```nginx
location ~* \.db$ {
    deny all;
}
```

## 故障处理

### 1. 数据库锁定
如果遇到 "database is locked" 错误：
- 检查是否有其他进程占用
- 增加事务超时时间
- 使用WAL模式

```sql
PRAGMA journal_mode=WAL;
```

### 2. 数据库损坏
- 从备份恢复
- 使用 `.recover` 命令尝试恢复
- 重新导入数据

### 3. 性能问题
- 检查慢查询
- 优化索引
- 执行VACUUM清理

## 监控建议

### 1. 数据库大小
```bash
ls -lh database/bread_review.db
```

### 2. 表行数
```sql
SELECT COUNT(*) FROM points;
SELECT COUNT(*) FROM point_history;
```

### 3. 查询性能
```sql
.timer ON
SELECT * FROM points WHERE status = 'pending';
```

## 总结

这个数据库架构设计：
- ✅ 简单清晰，易于理解
- ✅ 性能优秀，满足需求
- ✅ 扩展性好，易于维护
- ✅ 数据完整性有保障
- ✅ 备份恢复方便

# 数据库迁移指南

## 概述

本项目已从JSON文件存储迁移到SQLite数据库存储，以提供更好的性能、数据完整性和可扩展性。

## 主要变更

### 1. 数据存储方式
- **之前**: 使用JSON文件 (`data/points_pool.json`, `data/daily_assignments.json`)
- **现在**: 使用SQLite数据库 (`database/bread_review.db`)

### 2. 数据库结构

#### 表结构
- `config` - 系统配置表
- `points` - 知识点表
- `daily_assignments` - 每日分配表
- `point_history` - 操作历史表

#### 视图
- `daily_stats` - 每日统计视图
- `overall_stats` - 整体统计视图

### 3. API变更
所有API端点功能保持不变，但底层实现从JSON文件操作改为数据库操作：
- `api/initialize.php` - 系统初始化
- `api/get_points.php` - 获取知识点
- `api/mark_point.php` - 标记知识点
- `api/get_stats.php` - 获取统计
- `api/get_current_day.php` - 获取当前天数
- `api/get_init_status.php` - 获取初始化状态
- `api/reset.php` - 重置系统

## 迁移步骤

### 新用户（从零开始）

1. **初始化数据库**
   ```bash
   php database/init_db.php
   ```

2. **生成题库（从Excel导入）**
   ```bash
   python3 scripts/generate_pool.py
   ```

3. **初始化系统（通过API）**
   ```bash
   curl -X POST http://your-domain/api/initialize.php \
     -H "Content-Type: application/json" \
     -d '{"startDate": "2025-11-14"}'
   ```

### 现有用户（从JSON迁移）

1. **初始化数据库**
   ```bash
   php database/init_db.php
   ```

2. **从JSON迁移数据**
   ```bash
   php database/migrate_from_json.php
   ```

   这个脚本会：
   - 读取现有的JSON文件
   - 将所有数据导入到数据库
   - 保留所有学习进度和历史记录

3. **验证迁移结果**
   访问系统检查所有数据是否正确迁移

## 优势

### 1. 性能提升
- 数据库索引加速查询
- 减少文件I/O操作
- 支持复杂查询和聚合统计

### 2. 数据完整性
- 事务支持，保证数据一致性
- 外键约束，保证数据关联正确
- 类型约束，避免数据错误

### 3. 更好的并发控制
- SQLite的锁机制优于文件锁
- 减少并发冲突

### 4. 扩展性
- 易于添加新字段和表
- 支持复杂的数据查询
- 便于数据分析和报表

### 5. 代码规范性
- 统一的数据访问接口
- 清晰的数据模型
- 更好的错误处理

## 数据库维护

### 备份数据库
```bash
# 简单备份
cp database/bread_review.db database/bread_review.db.backup

# 带时间戳的备份
cp database/bread_review.db database/bread_review.db.$(date +%Y%m%d_%H%M%S)
```

### 查看数据库
```bash
sqlite3 database/bread_review.db

# 常用命令
.tables          # 查看所有表
.schema points   # 查看表结构
SELECT * FROM config;  # 查询配置
```

### 数据库优化
```bash
sqlite3 database/bread_review.db "VACUUM;"
```

## 兼容性说明

### API接口
所有API接口保持向后兼容，返回的数据格式与之前相同。

### 前端代码
前端代码无需修改，因为API接口保持不变。

## 故障排除

### 数据库文件权限
如果遇到权限问题：
```bash
chmod 666 database/bread_review.db
chmod 777 database/
```

### 数据库损坏
如果数据库损坏，可以：
1. 从备份恢复
2. 重新初始化并从JSON迁移
3. 重新运行generate_pool.py

### 迁移失败
如果迁移失败：
1. 检查JSON文件是否存在且格式正确
2. 查看PHP错误日志
3. 手动检查数据库表结构

## 技术细节

### PDO使用
- 使用PDO进行数据库操作
- 预处理语句防止SQL注入
- 事务支持保证数据一致性

### 数据库文件位置
- 路径: `database/bread_review.db`
- 建议定期备份

### 性能优化
- 已创建必要的索引
- 使用视图简化复杂查询
- 使用事务批量操作

## 开发参考

### 添加新字段
```sql
ALTER TABLE points ADD COLUMN new_field TEXT;
```

### 创建新索引
```sql
CREATE INDEX idx_name ON table_name(column_name);
```

### 查询示例
```php
// 获取数据库连接
$db = getDB();

// 查询示例
$stmt = $db->prepare("SELECT * FROM points WHERE id = ?");
$stmt->execute([1]);
$point = $stmt->fetch();
```

## 联系与支持

如有问题，请查看：
- 项目README.md
- PHP错误日志
- 数据库Schema文件: `database/schema.sql`

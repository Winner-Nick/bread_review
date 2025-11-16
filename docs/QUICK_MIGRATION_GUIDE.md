# 快速迁移指南

## 概述

本指南提供最简化的步骤，帮助你安全地从JSON迁移到SQLite数据库。

## 🚀 快速开始（测试环境）

### 方式1：自动测试脚本（推荐）

```bash
# 在项目根目录运行
bash tests/test_migration.sh
```

这个脚本会自动：
- ✅ 检查所有依赖
- ✅ 备份现有数据
- ✅ 初始化数据库
- ✅ 迁移数据
- ✅ 验证数据完整性
- ✅ 测试所有API

### 方式2：手动步骤

```bash
# 1. 备份数据
cp -r data/ data_backup_$(date +%Y%m%d)/

# 2. 初始化数据库
php database/init_db.php

# 3. 迁移数据
php database/migrate_from_json.php

# 4. 验证数据
php database/verify_migration.php

# 5. 测试
php -S localhost:8000
# 浏览器访问 http://localhost:8000
```

## 📋 生产环境迁移检查清单

### 迁移前（准备阶段）

- [ ] 通知用户即将维护（如果需要停机）
- [ ] 选择低流量时段（如深夜2-4点）
- [ ] 完整备份现有JSON数据
- [ ] 在测试环境验证迁移流程
- [ ] 准备回滚方案
- [ ] 确认服务器有PHP SQLite支持

### 迁移中（执行阶段）

- [ ] 备份生产数据
  ```bash
  cp -r /var/www/html/chenchen/data/ /backup/$(date +%Y%m%d_%H%M%S)/
  ```

- [ ] 拉取新代码
  ```bash
  cd /var/www/html/chenchen
  git fetch origin
  git checkout claude/json-to-database-017ai85zbjzAR53vnSaNN5aq
  ```

- [ ] 初始化数据库
  ```bash
  php database/init_db.php
  ```

- [ ] 迁移数据
  ```bash
  php database/migrate_from_json.php
  ```

- [ ] 验证数据
  ```bash
  php database/verify_migration.php
  ```

- [ ] 设置权限
  ```bash
  chmod 666 database/bread_review.db
  chmod 777 database/
  chown www-data:www-data database/bread_review.db
  ```

- [ ] 重启Web服务器
  ```bash
  sudo systemctl reload nginx
  # 或
  sudo systemctl reload apache2
  ```

### 迁移后（验证阶段）

- [ ] 访问网站，确认可以正常访问
- [ ] 测试获取知识点功能
- [ ] 测试标记功能（记得/忘记）
- [ ] 检查统计数据是否正确
- [ ] 验证学习进度保留
- [ ] 检查所有天数切换正常
- [ ] 监控错误日志
  ```bash
  tail -f /var/log/nginx/error.log
  ```

## 🔄 回滚方案

如果迁移后发现问题，立即回滚：

```bash
# 方式1：代码回滚
cd /var/www/html/chenchen
git checkout <上一个稳定版本的commit>
sudo systemctl reload nginx

# 方式2：恢复备份
cd /var/www/html/chenchen
rm -rf data/
cp -r /backup/20251116_020000/data/ ./
git checkout <上一个稳定版本的commit>
sudo systemctl reload nginx
```

## ✅ 验证数据完整性

### 快速验证
```bash
# 运行验证脚本
php database/verify_migration.php
```

### 手动验证
```bash
# 连接数据库
sqlite3 database/bread_review.db

# 检查知识点数量
SELECT COUNT(*) FROM points;

# 检查已标记的知识点
SELECT status, COUNT(*) FROM points GROUP BY status;

# 检查配置
SELECT * FROM config;

# 退出
.quit
```

### API验证
```bash
# 测试初始化状态
curl https://shenpeng.work/chenchen/api/get_init_status.php | jq .

# 测试获取知识点
curl https://shenpeng.work/chenchen/api/get_points.php?day=1 | jq .

# 测试统计
curl https://shenpeng.work/chenchen/api/get_stats.php | jq .
```

## 🛠 常见问题

### 问题1：PDO驱动未找到

**错误信息**：`could not find driver`

**解决方案**：
```bash
# Ubuntu/Debian
sudo apt-get install php-sqlite3 php-pdo
sudo systemctl restart php7.4-fpm

# 验证
php -m | grep -i pdo
php -m | grep -i sqlite
```

### 问题2：数据库权限错误

**错误信息**：`unable to open database file` 或 `Permission denied`

**解决方案**：
```bash
chmod 666 database/bread_review.db
chmod 777 database/
chown www-data:www-data database/bread_review.db
chown www-data:www-data database/
```

### 问题3：数据验证失败

**解决方案**：
```bash
# 删除数据库重新迁移
rm database/bread_review.db
php database/init_db.php
php database/migrate_from_json.php
php database/verify_migration.php
```

## 📊 迁移前后对比

| 项目 | JSON版本 | 数据库版本 |
|------|---------|-----------|
| 数据存储 | 2个JSON文件 | 1个SQLite文件 |
| 文件大小 | ~500KB | ~200KB |
| 查询速度 | 慢（需解析整个文件） | 快（索引加速） |
| 并发支持 | 文件锁（有限） | 数据库锁（更好） |
| 数据完整性 | 手动保证 | 事务保证 |
| 扩展性 | 困难 | 容易 |

## 📞 紧急联系

如果迁移过程中遇到无法解决的问题：

1. **立即回滚**（见上面回滚方案）
2. 查看详细文档：`docs/PRODUCTION_MIGRATION_PLAN.md`
3. 检查错误日志：`/var/log/nginx/error.log`
4. 联系技术支持

## 🎯 最佳实践

1. **在测试环境先完整测试一遍**
2. **选择流量最低的时段进行迁移**
3. **保留JSON备份至少1个月**
4. **迁移后监控24小时**
5. **准备好快速回滚方案**

## 📚 相关文档

- [详细迁移计划](PRODUCTION_MIGRATION_PLAN.md) - 完整的分阶段迁移方案
- [数据库架构](DATABASE_ARCHITECTURE.md) - 数据库设计说明
- [系统要求](REQUIREMENTS.md) - 环境依赖说明

## ⏱ 预计时间

| 环境 | 预计时间 |
|------|---------|
| 测试环境 | 10-15分钟 |
| 生产环境（小规模） | 20-30分钟 |
| 生产环境（大规模） | 1-2小时 |

**注意**：以上时间包括备份、迁移、验证的总时间。

---

**最后提醒**：
- ✅ 永远先在测试环境验证
- ✅ 必须完整备份数据
- ✅ 准备好回滚方案
- ✅ 选择合适的时间窗口

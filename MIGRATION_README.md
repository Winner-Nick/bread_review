# 生产环境迁移指南

> **针对已上线网站 https://shenpeng.work/chenchen 的安全迁移方案**

## ⚠️ 重要说明

- 你的网站已经上线并有真实用户在使用
- 所有用户的学习进度和标记都必须100%保留
- 迁移过程可以做到**零数据丢失**
- 准备了完整的**回滚方案**

## 🎯 推荐方案：先测试，后部署

### 第一步：在本地/测试服务器验证（必须！）

```bash
# 1. 克隆代码到测试环境
git clone https://github.com/Winner-Nick/bread_review.git test_migration
cd test_migration
git checkout claude/json-to-database-017ai85zbjzAR53vnSaNN5aq

# 2. 复制生产环境的JSON数据到测试环境
# 从生产服务器下载 data/points_pool.json 和 data/daily_assignments.json
# 放到 test_migration/data/ 目录

# 3. 运行自动化测试（一键完成所有验证）
bash tests/test_migration.sh
```

**测试脚本会自动**：
- ✅ 检查PHP和SQLite是否可用
- ✅ 备份你的数据
- ✅ 初始化数据库
- ✅ 迁移所有数据
- ✅ 验证数据100%一致
- ✅ 测试所有API功能

**预期结果**：
```
✅ 迁移测试完成！

测试结果:
  - 数据库文件: database/bread_review.db
  - 备份目录: backup_20251116_143022
  - 所有测试通过
```

### 第二步：生产环境部署

**前提条件**：
- ✅ 测试环境验证通过
- ✅ 选择低流量时段（建议深夜2-4点）
- ✅ 通知用户（如果需要）

#### 方式A：蓝绿部署（推荐，零停机）

```bash
# 1. 在服务器上创建新目录
ssh your-server
cd /var/www
mkdir bread_review_v4
cd bread_review_v4

# 2. 部署新代码
git clone https://github.com/Winner-Nick/bread_review.git .
git checkout claude/json-to-database-017ai85zbjzAR53vnSaNN5aq

# 3. 复制生产数据
cp /var/www/html/chenchen/data/*.json ./data/

# 4. 执行迁移
php database/init_db.php
php database/migrate_from_json.php
php database/verify_migration.php  # 必须通过！

# 5. 设置权限
chmod 666 database/bread_review.db
chmod 777 database/
chown www-data:www-data database/ -R

# 6. 切换流量（修改Nginx配置）
sudo nano /etc/nginx/sites-available/shenpeng.work
# 修改 root 路径为 /var/www/bread_review_v4
sudo nginx -t
sudo nginx -s reload  # 平滑重载，无停机

# 7. 测试新版本
curl https://shenpeng.work/chenchen/api/get_init_status.php
# 应该返回 {"success":true,...}

# 8. 监控5-10分钟，确认无问题

# 9. 如果有问题，立即回滚（30秒内完成）
# sudo nano /etc/nginx/sites-available/shenpeng.work
# 改回 root /var/www/html/chenchen
# sudo nginx -s reload
```

#### 方式B：原地升级（简单，但有短暂停机）

```bash
# 1. 完整备份
ssh your-server
cd /var/www/html/chenchen
cp -r . ../chenchen_backup_$(date +%Y%m%d_%H%M%S)/

# 2. 拉取新代码
git fetch origin
git checkout claude/json-to-database-017ai85zbjzAR53vnSaNN5aq

# 3. 执行迁移
php database/init_db.php
php database/migrate_from_json.php
php database/verify_migration.php  # 必须通过！

# 4. 设置权限
chmod 666 database/bread_review.db
chmod 777 database/
chown www-data:www-data database/ -R

# 5. 重启Web服务器
sudo systemctl reload nginx

# 6. 验证
curl https://shenpeng.work/chenchen/api/get_init_status.php
```

### 第三步：迁移后验证

访问你的网站并测试：

- [ ] 能正常打开
- [ ] 显示正确的天数和日期
- [ ] 能查看知识点
- [ ] 能切换天数
- [ ] 所有之前的标记（记得/忘记）都还在
- [ ] 统计数据正确
- [ ] 能正常标记新的知识点

### 第四步：监控24小时

```bash
# 监控错误日志
tail -f /var/log/nginx/error.log

# 检查数据库大小（应该稳定）
ls -lh /var/www/html/chenchen/database/bread_review.db
# 或
ls -lh /var/www/bread_review_v4/database/bread_review.db
```

## 🔄 快速回滚方案

如果发现任何问题，**立即回滚**（不要犹豫）：

### 蓝绿部署回滚（30秒）
```bash
# 修改Nginx配置
sudo nano /etc/nginx/sites-available/shenpeng.work
# 改回: root /var/www/html/chenchen;
sudo nginx -s reload
```

### 原地升级回滚（2分钟）
```bash
cd /var/www/html/chenchen
git checkout <上一个commit>  # 见下面如何找到
sudo systemctl reload nginx
```

找到上一个稳定版本：
```bash
git log --oneline -5
# 找到迁移之前的commit hash，比如 b574c73
git checkout b574c73
```

## 📊 数据验证命令

### 快速检查
```bash
# 验证知识点数量
sqlite3 database/bread_review.db "SELECT COUNT(*) FROM points;"

# 验证已标记的数量
sqlite3 database/bread_review.db "SELECT status, COUNT(*) FROM points GROUP BY status;"
```

### 完整验证
```bash
php database/verify_migration.php
```

## ❓ 常见问题

### Q1: 迁移会影响用户使用吗？
**A**:
- 蓝绿部署：**零停机**，用户无感知
- 原地升级：可能有1-2分钟短暂停机

### Q2: 用户的学习进度会丢失吗？
**A**: **不会**。迁移脚本会保留所有数据，包括：
- 所有标记（记得/忘记）
- 学习进度
- 历史记录
- 配置信息

### Q3: 如果迁移失败怎么办？
**A**: 立即回滚（见上面回滚方案），你的备份数据完整保留。

### Q4: JSON文件还需要保留吗？
**A**: **必须保留**！至少保留1个月，作为备份。系统已配置不会删除它们。

### Q5: 迁移需要多长时间？
**A**:
- 准备和测试：1小时
- 生产部署：20-30分钟
- 数据验证：5分钟

## 🔧 故障排除

### 问题：PDO驱动未找到
```bash
# Ubuntu/Debian
sudo apt-get install php-sqlite3 php-pdo
sudo systemctl restart php7.4-fpm
```

### 问题：数据库权限错误
```bash
chmod 666 database/bread_review.db
chmod 777 database/
chown www-data:www-data database/ -R
```

### 问题：验证失败
```bash
# 重新迁移
rm database/bread_review.db
php database/init_db.php
php database/migrate_from_json.php
```

## 📚 详细文档

如需更多细节，请查看：

1. **快速指南**（推荐先看）
   - `docs/QUICK_MIGRATION_GUIDE.md`

2. **详细方案**（完整的分阶段计划）
   - `docs/PRODUCTION_MIGRATION_PLAN.md`

3. **数据库架构**
   - `docs/DATABASE_ARCHITECTURE.md`

4. **系统要求**
   - `docs/REQUIREMENTS.md`

## 📞 需要帮助？

如果遇到任何问题：

1. 查看 `docs/QUICK_MIGRATION_GUIDE.md` 的常见问题部分
2. 检查 `/var/log/nginx/error.log` 错误日志
3. 运行 `php database/verify_migration.php` 查看详细错误

## ✅ 迁移成功标志

当你看到以下情况，说明迁移成功：

- ✅ `php database/verify_migration.php` 全部通过
- ✅ 网站可以正常访问
- ✅ 所有标记的知识点状态正确
- ✅ 统计数据准确
- ✅ 能正常标记新知识点
- ✅ 日志无错误

## 🎉 迁移后优势

- ⚡ **更快的查询速度**（数据库索引）
- 🔒 **更好的数据完整性**（事务保护）
- 📈 **更强的扩展性**（易于添加新功能）
- 💪 **更好的并发支持**（多用户同时使用）

---

**重要提醒**：
1. ✅ 必须先在测试环境验证
2. ✅ 必须完整备份数据
3. ✅ 选择合适的时间窗口
4. ✅ 准备好快速回滚
5. ✅ JSON文件不要删除

**祝迁移顺利！** 🚀

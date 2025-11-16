# 生产环境安全迁移方案

## ⚠️ 重要提示
- 网站已上线运行：https://shenpeng.work/chenchen
- 存在真实用户数据和学习进度
- **绝对不能丢失任何用户数据**
- 需要零停机或最小停机时间

## 迁移策略：蓝绿部署

采用蓝绿部署策略，确保安全迁移：
- **蓝环境**（当前）：JSON版本，继续服务用户
- **绿环境**（新）：数据库版本，用于测试
- 验证通过后，流量切换到绿环境

## 阶段一：准备工作（预计30分钟）

### 1.1 完整备份现有数据

```bash
# 创建备份目录
mkdir -p /path/to/backup/$(date +%Y%m%d_%H%M%S)
cd /path/to/backup/$(date +%Y%m%d_%H%M%S)

# 备份所有JSON数据文件
cp -r /path/to/production/data/ ./data_backup/

# 备份整个项目代码
cp -r /path/to/production/ ./code_backup/

# 验证备份完整性
ls -lh data_backup/
cat data_backup/points_pool.json | jq . > /dev/null && echo "✓ points_pool.json 格式正确"
cat data_backup/daily_assignments.json | jq . > /dev/null && echo "✓ daily_assignments.json 格式正确"

# 记录备份信息
echo "备份时间: $(date)" > backup_info.txt
echo "数据路径: $(pwd)" >> backup_info.txt
```

### 1.2 创建测试环境（克隆生产数据）

```bash
# 在本地或测试服务器创建测试目录
mkdir -p /path/to/test_migration
cd /path/to/test_migration

# 拉取新代码
git clone https://github.com/Winner-Nick/bread_review.git
cd bread_review
git checkout claude/json-to-database-017ai85zbjzAR53vnSaNN5aq

# 复制生产环境的JSON数据到测试环境
cp /path/to/backup/*/data_backup/*.json ./data/

# 验证数据完整性
ls -lh data/
```

## 阶段二：测试环境验证（预计1小时）

### 2.1 初始化数据库

```bash
# 确保PHP有SQLite支持
php -m | grep -E "PDO|sqlite"

# 如果没有，安装（Ubuntu/Debian）
# sudo apt-get install php-sqlite3 php-pdo

# 创建数据库
php database/init_db.php
```

预期输出：
```
===========================================
考研知识点背诵系统 - 数据库初始化
===========================================

✓ 数据库文件已创建
✓ Schema执行成功
✓ config
✓ points
✓ daily_assignments
✓ point_history
✓ daily_stats
✓ overall_stats
✓ 数据库文件权限已设置为 0666

✅ 数据库初始化完成！
```

### 2.2 执行数据迁移

```bash
# 运行迁移脚本
php database/migrate_from_json.php
```

预期输出示例：
```
===========================================
JSON数据迁移工具
===========================================

✓ 已连接到数据库
✓ 正在读取JSON文件...

迁移配置数据...
  ✓ startDate: 2025-11-14
  ✓ totalDays: 30
  ✓ totalPoints: 1500
  ✓ avgPointsPerDay: 50

迁移知识点数据...
  已迁移 100 个知识点...
  已迁移 200 个知识点...
  ...
  ✓ 总共迁移 1500 个知识点

迁移每日分配数据...
  ✓ 迁移了 30 天的分配数据

✓ 所有数据已成功提交

验证迁移结果:
  知识点数量: 1500
  每日分配数量: 30
  历史记录数量: 150

✅ 数据迁移完成！
```

### 2.3 数据完整性验证

创建验证脚本：

```bash
# 创建验证脚本
cat > database/verify_migration.php << 'EOF'
#!/usr/bin/env php
<?php
/**
 * 数据迁移验证脚本
 * 对比JSON和数据库中的数据是否一致
 */

define('DB_FILE', __DIR__ . '/bread_review.db');
define('JSON_POOL', __DIR__ . '/../data/points_pool.json');
define('JSON_ASSIGNMENTS', __DIR__ . '/../data/daily_assignments.json');

echo "===========================================\n";
echo "数据迁移验证工具\n";
echo "===========================================\n\n";

// 读取JSON数据
$jsonPool = json_decode(file_get_contents(JSON_POOL), true);
$jsonAssignments = json_decode(file_get_contents(JSON_ASSIGNMENTS), true);

// 连接数据库
$db = new PDO('sqlite:' . DB_FILE);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "1. 验证知识点总数...\n";
$jsonPointCount = count($jsonPool['points']);
$dbPointCount = $db->query("SELECT COUNT(*) FROM points")->fetchColumn();
if ($jsonPointCount === $dbPointCount) {
    echo "   ✓ 知识点数量一致: $jsonPointCount\n";
} else {
    echo "   ✗ 知识点数量不一致! JSON: $jsonPointCount, DB: $dbPointCount\n";
    exit(1);
}

echo "\n2. 验证配置信息...\n";
$stmt = $db->query("SELECT key, value FROM config");
$dbConfig = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $dbConfig[$row['key']] = $row['value'];
}

$checks = ['startDate', 'totalDays', 'totalPoints'];
foreach ($checks as $key) {
    $jsonValue = $jsonPool['config'][$key] ?? 'N/A';
    $dbValue = $dbConfig[$key] ?? 'N/A';
    if ($jsonValue == $dbValue) {
        echo "   ✓ $key: $dbValue\n";
    } else {
        echo "   ✗ $key 不一致! JSON: $jsonValue, DB: $dbValue\n";
    }
}

echo "\n3. 验证知识点状态分布...\n";
$jsonStatus = ['pending' => 0, 'remembered' => 0, 'forgotten' => 0];
foreach ($jsonPool['points'] as $point) {
    $status = $point['status'] ?? 'pending';
    $jsonStatus[$status]++;
}

$stmt = $db->query("SELECT status, COUNT(*) as count FROM points GROUP BY status");
$dbStatus = ['pending' => 0, 'remembered' => 0, 'forgotten' => 0];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $dbStatus[$row['status']] = (int)$row['count'];
}

foreach ($jsonStatus as $status => $count) {
    $dbCount = $dbStatus[$status];
    if ($count === $dbCount) {
        echo "   ✓ $status: $count\n";
    } else {
        echo "   ✗ $status 不一致! JSON: $count, DB: $dbCount\n";
    }
}

echo "\n4. 验证每日分配...\n";
$dbAssignmentCount = $db->query("SELECT COUNT(*) FROM daily_assignments")->fetchColumn();
$jsonDayCount = 0;
for ($day = 1; $day <= 30; $day++) {
    if (isset($jsonAssignments['day_' . $day])) {
        $jsonDayCount++;
    }
}
if ($jsonDayCount === $dbAssignmentCount) {
    echo "   ✓ 每日分配数量一致: $dbAssignmentCount 天\n";
} else {
    echo "   ✗ 每日分配不一致! JSON: $jsonDayCount, DB: $dbAssignmentCount\n";
}

echo "\n5. 随机抽样验证（10个知识点）...\n";
$sampleIds = [];
for ($i = 0; $i < 10; $i++) {
    $sampleIds[] = $jsonPool['points'][array_rand($jsonPool['points'])]['id'];
}

foreach ($sampleIds as $id) {
    // 从JSON获取
    $jsonPoint = null;
    foreach ($jsonPool['points'] as $p) {
        if ($p['id'] == $id) {
            $jsonPoint = $p;
            break;
        }
    }

    // 从数据库获取
    $stmt = $db->prepare("SELECT * FROM points WHERE id = ?");
    $stmt->execute([$id]);
    $dbPoint = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($dbPoint &&
        $dbPoint['subject'] === ($jsonPoint['科目'] ?? '') &&
        $dbPoint['status'] === ($jsonPoint['status'] ?? 'pending') &&
        $dbPoint['forgotten_count'] == ($jsonPoint['forgottenCount'] ?? 0)) {
        echo "   ✓ ID $id 数据一致\n";
    } else {
        echo "   ✗ ID $id 数据不一致!\n";
        print_r(['json' => $jsonPoint, 'db' => $dbPoint]);
    }
}

echo "\n===========================================\n";
echo "✅ 数据验证完成！\n";
echo "===========================================\n";
EOF

chmod +x database/verify_migration.php
```

运行验证：

```bash
php database/verify_migration.php
```

### 2.4 功能测试

```bash
# 启动测试服务器
cd /path/to/test_migration/bread_review
php -S localhost:9000 &

# 记录进程ID
TEST_SERVER_PID=$!
echo "测试服务器PID: $TEST_SERVER_PID"
```

**手动测试清单**：

- [ ] 访问 http://localhost:9000
- [ ] 检查初始化状态显示正确
- [ ] 切换到不同的天数
- [ ] 查看知识点内容
- [ ] 标记"记得"，检查统计更新
- [ ] 标记"忘记"，检查统计更新
- [ ] 刷新页面，确认状态保持
- [ ] 查看统计页面，数据正确
- [ ] 检查所有已标记的知识点状态
- [ ] 验证学习进度百分比

**API测试**：

```bash
# 测试获取初始化状态
curl http://localhost:9000/api/get_init_status.php | jq .

# 测试获取当前天数
curl http://localhost:9000/api/get_current_day.php | jq .

# 测试获取知识点
curl http://localhost:9000/api/get_points.php?day=1 | jq .

# 测试获取统计
curl http://localhost:9000/api/get_stats.php | jq .

# 测试标记知识点
curl -X POST http://localhost:9000/api/mark_point.php \
  -H "Content-Type: application/json" \
  -d '{"pointId": 1, "action": "remember", "day": 1}' | jq .
```

### 2.5 性能测试

```bash
# 创建性能测试脚本
cat > tests/performance_test.sh << 'EOF'
#!/bin/bash

echo "性能测试开始..."

# 测试获取知识点的响应时间
echo "测试 get_points.php..."
for i in {1..10}; do
    time curl -s http://localhost:9000/api/get_points.php?day=1 > /dev/null
done

# 测试标记知识点的响应时间
echo "测试 mark_point.php..."
for i in {1..10}; do
    time curl -s -X POST http://localhost:9000/api/mark_point.php \
      -H "Content-Type: application/json" \
      -d '{"pointId": '$i', "action": "remember", "day": 1}' > /dev/null
done

# 测试统计的响应时间
echo "测试 get_stats.php..."
for i in {1..10}; do
    time curl -s http://localhost:9000/api/get_stats.php > /dev/null
done
EOF

chmod +x tests/performance_test.sh
bash tests/performance_test.sh
```

## 阶段三：生产环境部署（预计30分钟）

### 3.1 部署前最终检查

```bash
# 确认测试环境一切正常
[ -f database/verify_migration.php ] && php database/verify_migration.php

# 确认所有文件准备就绪
ls -lh database/
ls -lh api/

# 确认权限设置
chmod 755 database/
chmod 644 database/schema.sql
chmod 755 database/init_db.php
chmod 755 database/migrate_from_json.php
```

### 3.2 生产环境部署

**方案A：原地升级（推荐用于低流量时段）**

```bash
# 1. 进入生产环境目录
cd /path/to/production

# 2. 再次完整备份
cp -r . ../production_backup_$(date +%Y%m%d_%H%M%S)/

# 3. 拉取新代码
git fetch origin
git checkout claude/json-to-database-017ai85zbjzAR53vnSaNN5aq

# 4. 初始化数据库
php database/init_db.php

# 5. 迁移数据（JSON文件保持不变）
php database/migrate_from_json.php

# 6. 验证迁移
php database/verify_migration.php

# 7. 设置权限
chmod 666 database/bread_review.db
chmod 777 database/

# 8. 重启Web服务器（如果需要）
# sudo systemctl reload nginx
# 或
# sudo systemctl reload apache2
```

**方案B：蓝绿部署（推荐用于高流量场景）**

```bash
# 1. 创建新的部署目录（绿环境）
mkdir -p /var/www/bread_review_v4
cd /var/www/bread_review_v4

# 2. 部署新代码
git clone https://github.com/Winner-Nick/bread_review.git .
git checkout claude/json-to-database-017ai85zbjzAR53vnSaNN5aq

# 3. 复制生产数据
cp /var/www/bread_review/data/*.json ./data/

# 4. 初始化并迁移
php database/init_db.php
php database/migrate_from_json.php
php database/verify_migration.php

# 5. 设置权限
chmod 666 database/bread_review.db
chmod 777 database/

# 6. 配置Web服务器指向新目录
# 编辑 Nginx 配置（示例）
sudo nano /etc/nginx/sites-available/shenpeng.work

# 修改 root 路径：
# root /var/www/bread_review_v4;

# 7. 测试配置
sudo nginx -t

# 8. 平滑重载（无停机）
sudo nginx -s reload

# 9. 监控5-10分钟，确认无问题

# 10. 如果有问题，快速回滚
# sudo sed -i 's|/var/www/bread_review_v4|/var/www/bread_review|g' /etc/nginx/sites-available/shenpeng.work
# sudo nginx -s reload
```

### 3.3 部署后验证

```bash
# 1. 访问生产网站
curl https://shenpeng.work/chenchen/api/get_init_status.php | jq .

# 2. 检查数据库文件
ls -lh /path/to/production/database/bread_review.db

# 3. 检查日志
tail -f /var/log/nginx/error.log
# 或
tail -f /var/log/apache2/error.log

# 4. 验证几个关键功能
curl https://shenpeng.work/chenchen/api/get_current_day.php | jq .
curl https://shenpeng.work/chenchen/api/get_stats.php | jq .
```

## 阶段四：监控和回滚方案

### 4.1 监控清单（部署后24小时）

- [ ] 每小时检查一次错误日志
- [ ] 监控API响应时间
- [ ] 检查数据库文件大小
- [ ] 验证用户操作（标记、切换天数）
- [ ] 确认统计数据准确性

### 4.2 快速回滚方案

**如果发现问题，立即执行回滚**：

```bash
# 方案A回滚（原地升级）
cd /path/to/production
git checkout 上一个稳定版本的commit_hash

# 重启服务
sudo systemctl reload nginx

# 方案B回滚（蓝绿部署）
# 修改Nginx配置指向旧版本
sudo sed -i 's|/var/www/bread_review_v4|/var/www/bread_review|g' /etc/nginx/sites-available/shenpeng.work
sudo nginx -s reload
```

### 4.3 数据同步（如果回滚）

如果回滚后需要保留新数据：

```bash
# 从数据库导出最新状态（创建导出脚本）
cat > database/export_to_json.php << 'EOF'
#!/usr/bin/env php
<?php
// 将数据库数据导出回JSON格式
// 代码见下一个脚本
EOF
```

## 阶段五：清理和文档

### 5.1 部署成功后

```bash
# 1. 保留JSON备份
mv data/ data_json_backup/

# 2. 更新文档
echo "迁移完成时间: $(date)" >> docs/DEPLOYMENT_LOG.md

# 3. 通知团队
echo "✅ 数据库版本部署成功"
```

### 5.2 常见问题处理

**问题1：数据库文件权限错误**
```bash
chmod 666 database/bread_review.db
chmod 777 database/
chown www-data:www-data database/bread_review.db  # Ubuntu/Debian
# 或
chown nginx:nginx database/bread_review.db  # CentOS
```

**问题2：PDO驱动未找到**
```bash
# Ubuntu/Debian
sudo apt-get install php-sqlite3 php-pdo
sudo systemctl restart php7.4-fpm  # 或你的PHP版本

# CentOS
sudo yum install php-pdo php-sqlite
sudo systemctl restart php-fpm
```

**问题3：数据迁移不完整**
```bash
# 重新运行迁移
rm database/bread_review.db
php database/init_db.php
php database/migrate_from_json.php
php database/verify_migration.php
```

## 关键原则

1. ✅ **永远不删除JSON文件** - 保留作为备份
2. ✅ **先测试后部署** - 在测试环境完全验证
3. ✅ **准备回滚方案** - 随时可以回到旧版本
4. ✅ **逐步验证** - 每一步都验证成功
5. ✅ **保持备份** - 多份备份，异地保存

## 时间规划

| 阶段 | 预计时间 | 可以回滚 |
|------|---------|---------|
| 准备和备份 | 30分钟 | ✅ |
| 测试环境验证 | 1小时 | ✅ |
| 生产部署 | 30分钟 | ✅ |
| 监控观察 | 24小时 | ✅ |

**总计：约2小时主动操作 + 24小时监控**

## 紧急联系

- 技术负责人：[联系方式]
- 服务器管理员：[联系方式]
- 备用方案负责人：[联系方式]

---

**最后提醒**：
- 选择低流量时段部署（如深夜或凌晨）
- 部署前告知所有相关人员
- 准备好快速回滚
- 保持冷静，遵循流程

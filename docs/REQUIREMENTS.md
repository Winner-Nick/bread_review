# 系统要求

## PHP要求

### 最低版本
- PHP 7.4 或更高版本
- 推荐 PHP 8.0+

### 必需的PHP扩展
- `pdo` - PDO数据库抽象层
- `pdo_sqlite` - SQLite PDO驱动
- `json` - JSON支持
- `mbstring` - 多字节字符串支持

### 检查PHP扩展
```bash
php -m | grep -E "PDO|sqlite|json|mbstring"
```

### 安装缺失的扩展

#### Ubuntu/Debian
```bash
sudo apt-get update
sudo apt-get install php-sqlite3 php-pdo
```

#### CentOS/RHEL
```bash
sudo yum install php-pdo php-sqlite
```

#### macOS (使用Homebrew)
```bash
brew install php
# SQLite支持通常已包含在PHP中
```

#### Windows
编辑 `php.ini` 文件，取消以下行的注释：
```ini
extension=pdo_sqlite
extension=sqlite3
```

## Python要求

### 最低版本
- Python 3.7 或更高版本

### 必需的Python包
```bash
pip install -r requirements-dev.txt
```

包含：
- `pandas` - 数据处理
- `openpyxl` - Excel文件读取

## 文件权限

### 数据库目录
```bash
chmod 777 database/
```

### 数据库文件（创建后）
```bash
chmod 666 database/bread_review.db
```

### PHP脚本执行权限
```bash
chmod +x database/init_db.php
chmod +x database/migrate_from_json.php
```

### Python脚本执行权限
```bash
chmod +x scripts/generate_pool.py
```

## 验证安装

### 检查PHP配置
```bash
php -i | grep -i sqlite
```

应该看到类似输出：
```
PDO drivers => sqlite
SQLite Library => 3.x.x
```

### 检查Python环境
```bash
python3 -c "import pandas; import openpyxl; print('All required packages installed')"
```

## 常见问题

### PDO驱动未找到
**错误**: `could not find driver`

**解决方案**:
1. 确认安装了 `pdo_sqlite` 扩展
2. 重启Web服务器（Apache/Nginx）
3. 检查 `php.ini` 配置

### 权限被拒绝
**错误**: `Permission denied` 或 `unable to open database file`

**解决方案**:
1. 检查database目录权限
2. 确保Web服务器用户有写权限
3. 在Linux上可能需要设置SELinux

### Python包未安装
**错误**: `ModuleNotFoundError: No module named 'pandas'`

**解决方案**:
```bash
pip3 install pandas openpyxl
```

## Web服务器配置

### Apache
确保 `.htaccess` 文件正确配置（如果使用）

### Nginx
确保PHP-FPM正确配置

### 内置Web服务器（开发用）
```bash
php -S localhost:8000
```

## 开发环境推荐

### VSCode扩展
- PHP Intelephense
- SQLite Viewer
- Python

### 数据库管理工具
- DB Browser for SQLite
- phpLiteAdmin
- SQLite命令行工具

## 生产环境注意事项

1. **数据库备份**
   - 定期备份 `database/bread_review.db`
   - 使用cron任务自动备份

2. **性能优化**
   - 启用PHP OPcache
   - 定期执行 `VACUUM` 命令优化数据库

3. **安全性**
   - 确保数据库文件不可通过Web直接访问
   - 使用HTTPS
   - 限制API访问权限

4. **监控**
   - 监控数据库文件大小
   - 监控API响应时间
   - 记录错误日志

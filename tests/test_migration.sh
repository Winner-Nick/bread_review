#!/bin/bash
#
# 迁移测试脚本
# 用于在测试环境快速验证整个迁移流程
#

set -e  # 遇到错误立即退出

# 颜色定义
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo "==========================================="
echo "数据库迁移测试脚本"
echo "==========================================="
echo ""

# 检查是否在正确的目录
if [ ! -f "database/schema.sql" ]; then
    echo -e "${RED}错误: 请在项目根目录运行此脚本${NC}"
    exit 1
fi

# 1. 检查依赖
echo -e "${YELLOW}步骤 1/7: 检查依赖...${NC}"
echo -n "  检查 PHP... "
if command -v php &> /dev/null; then
    echo -e "${GREEN}✓${NC}"
else
    echo -e "${RED}✗ PHP未安装${NC}"
    exit 1
fi

echo -n "  检查 PHP PDO SQLite 扩展... "
if php -m | grep -q "pdo_sqlite"; then
    echo -e "${GREEN}✓${NC}"
else
    echo -e "${RED}✗ PDO SQLite 扩展未安装${NC}"
    echo "请安装: sudo apt-get install php-sqlite3 php-pdo"
    exit 1
fi

echo -n "  检查 Python3... "
if command -v python3 &> /dev/null; then
    echo -e "${GREEN}✓${NC}"
else
    echo -e "${RED}✗ Python3未安装${NC}"
    exit 1
fi

echo -n "  检查 jq (JSON处理工具)... "
if command -v jq &> /dev/null; then
    echo -e "${GREEN}✓${NC}"
else
    echo -e "${YELLOW}! jq未安装，某些验证将跳过${NC}"
fi

# 2. 检查JSON数据文件
echo ""
echo -e "${YELLOW}步骤 2/7: 检查JSON数据文件...${NC}"
if [ ! -f "data/points_pool.json" ]; then
    echo -e "${RED}错误: data/points_pool.json 不存在${NC}"
    echo "请确保有现有的JSON数据，或运行 python3 scripts/generate_pool.py"
    exit 1
fi

# 统计JSON中的知识点数量
POINT_COUNT=$(cat data/points_pool.json | grep -o '"id"' | wc -l)
echo -e "  ${GREEN}✓${NC} 找到 JSON 文件，包含 $POINT_COUNT 个知识点"

# 3. 备份现有数据
echo ""
echo -e "${YELLOW}步骤 3/7: 备份现有数据...${NC}"
BACKUP_DIR="backup_$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR"
cp -r data/ "$BACKUP_DIR/"
echo -e "  ${GREEN}✓${NC} 数据已备份到: $BACKUP_DIR"

# 4. 初始化数据库
echo ""
echo -e "${YELLOW}步骤 4/7: 初始化数据库...${NC}"

# 如果已存在数据库，先删除
if [ -f "database/bread_review.db" ]; then
    rm database/bread_review.db
    echo "  - 删除旧数据库"
fi

# 运行初始化脚本
echo "y" | php database/init_db.php > /tmp/init_output.txt 2>&1
if [ $? -eq 0 ]; then
    echo -e "  ${GREEN}✓${NC} 数据库初始化成功"
else
    echo -e "${RED}✗ 数据库初始化失败${NC}"
    cat /tmp/init_output.txt
    exit 1
fi

# 5. 执行数据迁移
echo ""
echo -e "${YELLOW}步骤 5/7: 执行数据迁移...${NC}"
php database/migrate_from_json.php > /tmp/migrate_output.txt 2>&1
if [ $? -eq 0 ]; then
    echo -e "  ${GREEN}✓${NC} 数据迁移成功"
else
    echo -e "${RED}✗ 数据迁移失败${NC}"
    cat /tmp/migrate_output.txt
    exit 1
fi

# 6. 验证迁移结果
echo ""
echo -e "${YELLOW}步骤 6/7: 验证迁移结果...${NC}"
php database/verify_migration.php > /tmp/verify_output.txt 2>&1
if [ $? -eq 0 ]; then
    echo -e "  ${GREEN}✓${NC} 数据验证通过"
    cat /tmp/verify_output.txt | grep "✓"
else
    echo -e "${RED}✗ 数据验证失败${NC}"
    cat /tmp/verify_output.txt
    exit 1
fi

# 7. API功能测试
echo ""
echo -e "${YELLOW}步骤 7/7: API功能测试...${NC}"

# 启动测试服务器
php -S localhost:9999 > /tmp/php_server.log 2>&1 &
PHP_PID=$!
echo "  - 启动测试服务器 (PID: $PHP_PID)"
sleep 2  # 等待服务器启动

# 测试函数
test_api() {
    local name=$1
    local url=$2
    local method=${3:-GET}

    echo -n "  测试 $name... "

    if [ "$method" == "GET" ]; then
        response=$(curl -s "http://localhost:9999/$url")
    else
        response=$(curl -s -X POST "http://localhost:9999/$url" \
            -H "Content-Type: application/json" \
            -d '{"confirm": false}')
    fi

    if echo "$response" | grep -q '"success":true'; then
        echo -e "${GREEN}✓${NC}"
        return 0
    else
        echo -e "${RED}✗${NC}"
        echo "    响应: $response"
        return 1
    fi
}

# 执行API测试
test_api "初始化状态" "api/get_init_status.php"
test_api "当前天数" "api/get_current_day.php"
test_api "获取知识点" "api/get_points.php?day=1"
test_api "统计信息" "api/get_stats.php"

# 停止测试服务器
kill $PHP_PID 2>/dev/null
echo "  - 已停止测试服务器"

# 完成
echo ""
echo "==========================================="
echo -e "${GREEN}✅ 迁移测试完成！${NC}"
echo "==========================================="
echo ""
echo "测试结果:"
echo "  - 数据库文件: database/bread_review.db"
echo "  - 备份目录: $BACKUP_DIR"
echo "  - 所有测试通过"
echo ""
echo "下一步:"
echo "  1. 检查数据库文件: sqlite3 database/bread_review.db"
echo "  2. 启动服务器测试: php -S localhost:8000"
echo "  3. 访问: http://localhost:8000"
echo ""
echo "如需回滚:"
echo "  1. 删除数据库: rm database/bread_review.db"
echo "  2. 恢复备份: cp -r $BACKUP_DIR/data/ ."
echo ""

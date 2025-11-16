#!/usr/bin/env php
<?php
/**
 * 数据库初始化脚本
 * 功能：创建SQLite数据库并执行schema
 */

// 定义数据库路径
define('DB_DIR', __DIR__);
define('DB_FILE', DB_DIR . '/bread_review.db');
define('SCHEMA_FILE', DB_DIR . '/schema.sql');

echo "===========================================\n";
echo "考研知识点背诵系统 - 数据库初始化\n";
echo "===========================================\n\n";

// 检查schema文件是否存在
if (!file_exists(SCHEMA_FILE)) {
    echo "❌ 错误：找不到schema文件: " . SCHEMA_FILE . "\n";
    exit(1);
}

// 如果数据库已存在，询问是否覆盖
if (file_exists(DB_FILE)) {
    echo "⚠️  数据库文件已存在: " . DB_FILE . "\n";
    echo "是否删除并重新创建？(y/N): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    fclose($handle);

    if (trim(strtolower($line)) !== 'y') {
        echo "操作已取消\n";
        exit(0);
    }

    // 删除旧数据库
    if (!unlink(DB_FILE)) {
        echo "❌ 错误：无法删除旧数据库文件\n";
        exit(1);
    }
    echo "✓ 已删除旧数据库\n\n";
}

// 创建数据库连接
try {
    $db = new PDO('sqlite:' . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✓ 数据库文件已创建: " . DB_FILE . "\n";
} catch (PDOException $e) {
    echo "❌ 错误：无法创建数据库: " . $e->getMessage() . "\n";
    exit(1);
}

// 读取并执行schema
echo "✓ 正在执行schema...\n";
$schema = file_get_contents(SCHEMA_FILE);

try {
    $db->exec($schema);
    echo "✓ Schema执行成功\n";
} catch (PDOException $e) {
    echo "❌ 错误：执行schema失败: " . $e->getMessage() . "\n";
    exit(1);
}

// 验证表是否创建成功
$tables = ['config', 'points', 'daily_assignments', 'point_history'];
echo "\n验证表结构:\n";
foreach ($tables as $table) {
    $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$table'");
    if ($stmt->fetch()) {
        echo "  ✓ $table\n";
    } else {
        echo "  ❌ $table (未创建)\n";
    }
}

// 验证视图
$views = ['daily_stats', 'overall_stats'];
echo "\n验证视图:\n";
foreach ($views as $view) {
    $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='view' AND name='$view'");
    if ($stmt->fetch()) {
        echo "  ✓ $view\n";
    } else {
        echo "  ❌ $view (未创建)\n";
    }
}

// 设置文件权限（确保Web服务器可以读写）
chmod(DB_FILE, 0666);
echo "\n✓ 数据库文件权限已设置为 0666\n";

echo "\n===========================================\n";
echo "✅ 数据库初始化完成！\n";
echo "===========================================\n\n";
echo "数据库位置: " . DB_FILE . "\n";
echo "下一步:\n";
echo "  1. 运行 scripts/generate_pool.py 生成初始题库\n";
echo "  2. 或运行 database/migrate_from_json.php 从JSON迁移数据\n\n";

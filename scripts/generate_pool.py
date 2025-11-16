#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
生成初始题库脚本
将Excel数据导入到SQLite数据库
"""

import sqlite3
import random
import pandas as pd
from pathlib import Path
from datetime import datetime

# 数据库路径
DB_FILE = Path(__file__).parent.parent / 'database' / 'bread_review.db'

def generate_points_pool():
    """生成初始题库（导入到数据库）"""

    # 检查数据库是否存在
    if not DB_FILE.exists():
        print(f'❌ 数据库文件不存在: {DB_FILE}')
        print('请先运行: php database/init_db.php')
        return False

    # 读取Excel文件
    excel_file = 'psychology_points_grouped_1115.xlsx'
    print(f'正在读取Excel文件: {excel_file}')

    try:
        data = pd.read_excel(excel_file)
        print(f'成功读取 {len(data)} 条知识点')
    except Exception as e:
        print(f'读取Excel文件失败: {e}')
        return False

    # 连接数据库
    try:
        conn = sqlite3.connect(str(DB_FILE))
        cursor = conn.cursor()
        print(f'已连接到数据库: {DB_FILE}')
    except Exception as e:
        print(f'连接数据库失败: {e}')
        return False

    # 准备知识点数据
    points = []
    point_id = 1

    for _, row in data.iterrows():
        point = {
            'id': point_id,
            'subject': str(row['科目']) if pd.notna(row['科目']) else "",
            'chapter': str(row['章节']) if pd.notna(row['章节']) else "",
            'point': str(row['考点']) if pd.notna(row['考点']) else "",
            'page': str(row['页码']) if pd.notna(row['页码']) else "",
        }
        points.append(point)
        point_id += 1

    # 随机打乱知识点
    random.shuffle(points)
    print(f'知识点已随机打乱')

    try:
        # 开始事务
        cursor.execute("BEGIN TRANSACTION")

        # 清空现有数据
        cursor.execute("DELETE FROM points")
        cursor.execute("DELETE FROM daily_assignments")
        cursor.execute("DELETE FROM point_history")
        print('已清空现有数据')

        # 插入知识点数据
        insert_sql = """
            INSERT INTO points (id, subject, chapter, point, page, status, assigned_day, completed_at, forgotten_count, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, 'pending', NULL, NULL, 0, datetime('now'), datetime('now'))
        """

        for point in points:
            cursor.execute(insert_sql, (
                point['id'],
                point['subject'],
                point['chapter'],
                point['point'],
                point['page']
            ))

        print(f'已插入 {len(points)} 条知识点')

        # 更新配置
        total_points = len(points)
        total_days = 30
        avg_points_per_day = total_points // total_days

        config_updates = [
            ('totalPoints', str(total_points)),
            ('totalDays', str(total_days)),
            ('avgPointsPerDay', str(avg_points_per_day)),
            ('createdAt', datetime.now().isoformat()),
            ('lastUpdated', datetime.now().isoformat())
        ]

        for key, value in config_updates:
            cursor.execute(
                "INSERT OR REPLACE INTO config (key, value, updated_at) VALUES (?, ?, datetime('now'))",
                (key, value)
            )

        # 提交事务
        conn.commit()
        print('数据已成功保存到数据库')

    except Exception as e:
        conn.rollback()
        print(f'保存数据失败: {e}')
        return False
    finally:
        conn.close()

    print(f'\n题库已生成到数据库: {DB_FILE}')
    print(f'配置信息:')
    print(f'  - 总天数: {total_days}')
    print(f'  - 总知识点: {total_points}')
    print(f'  - 平均每天: {avg_points_per_day}')

    return True

if __name__ == '__main__':
    print('=' * 60)
    print('考研知识点背诵系统 - 题库生成器 V3 (数据库版)')
    print('=' * 60)
    print()

    success = generate_points_pool()

    if success:
        print('\n✅ 题库生成成功！')
        print('\n下一步：')
        print('  1. 访问 API: api/initialize.php 初始化系统（POST请求）')
        print('  2. 访问 index.html 开始使用')
    else:
        print('\n❌ 题库生成失败！')

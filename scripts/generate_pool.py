#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
生成初始题库脚本
只进行随机排序，不预先分配到每天
"""

import json
import random
import pandas as pd
from pathlib import Path
from datetime import datetime

def generate_points_pool():
    """生成初始题库（仅随机排序）"""

    # 读取Excel文件
    excel_file = 'psychology_points_grouped_1115.xlsx'
    print(f'正在读取Excel文件: {excel_file}')

    try:
        data = pd.read_excel(excel_file)
        print(f'成功读取 {len(data)} 条知识点')
    except Exception as e:
        print(f'读取Excel文件失败: {e}')
        return False

    # 准备知识点数据
    points = []
    point_id = 1

    for _, row in data.iterrows():
        point = {
            "id": point_id,
            "科目": str(row['科目']) if pd.notna(row['科目']) else "",
            "章节": str(row['章节']) if pd.notna(row['章节']) else "",
            "考点": str(row['考点']) if pd.notna(row['考点']) else "",
            "页码": str(row['页码']) if pd.notna(row['页码']) else "",
            "status": "pending",           # pending, completed, forgotten
            "assignedDay": None,            # 分配到第几天
            "completedAt": None,            # 完成时间
            "forgottenCount": 0,            # 忘记次数
            "history": []                   # 操作历史
        }
        points.append(point)
        point_id += 1

    # 随机打乱知识点
    random.shuffle(points)
    print(f'知识点已随机打乱')

    # 创建题库配置
    total_points = len(points)
    total_days = 30
    avg_points_per_day = total_points // total_days

    pool_data = {
        "config": {
            "startDate": "2025-11-14",      # 起始日期
            "totalDays": total_days,
            "avgPointsPerDay": avg_points_per_day,
            "totalPoints": total_points,
            "createdAt": datetime.now().isoformat(),
            "lastUpdated": datetime.now().isoformat()
        },
        "points": points
    }

    # 保存题库文件
    output_file = Path('data') / 'points_pool.json'
    output_file.parent.mkdir(exist_ok=True)

    with open(output_file, 'w', encoding='utf-8') as f:
        json.dump(pool_data, f, ensure_ascii=False, indent=2)

    print(f'\n题库文件已生成: {output_file}')
    print(f'配置信息:')
    print(f'  - 起始日期: {pool_data["config"]["startDate"]}')
    print(f'  - 总天数: {pool_data["config"]["totalDays"]}')
    print(f'  - 总知识点: {pool_data["config"]["totalPoints"]}')
    print(f'  - 平均每天: {pool_data["config"]["avgPointsPerDay"]}')

    # 创建空的每日分配文件
    assignments_file = Path('data') / 'daily_assignments.json'
    initial_assignments = {
        "meta": {
            "lastUpdated": datetime.now().isoformat()
        }
    }

    with open(assignments_file, 'w', encoding='utf-8') as f:
        json.dump(initial_assignments, f, ensure_ascii=False, indent=2)

    print(f'\n每日分配文件已初始化: {assignments_file}')

    return True

if __name__ == '__main__':
    print('=' * 60)
    print('考研知识点背诵系统 - 题库生成器 V2')
    print('=' * 60)
    print()

    success = generate_points_pool()

    if success:
        print('\n✅ 题库生成成功！')
        print('\n下一步：')
        print('  1. 运行 init_database.php 初始化系统')
        print('  2. 访问 index.html 开始使用')
    else:
        print('\n❌ 题库生成失败！')

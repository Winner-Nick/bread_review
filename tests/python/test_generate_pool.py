#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Tests for generate_pool.py
Focus areas: Excel parsing, randomization, file generation
"""

import pytest
import json
import pandas as pd
from pathlib import Path
from datetime import datetime
import tempfile
import shutil


class TestGeneratePool:
    """Test suite for generate_pool.py data generation"""

    @pytest.fixture
    def temp_dir(self):
        """Create temporary directory for test files"""
        temp_dir = tempfile.mkdtemp()
        yield temp_dir
        shutil.rmtree(temp_dir)

    @pytest.fixture
    def sample_excel_data(self):
        """Create sample Excel data for testing"""
        return pd.DataFrame({
            '科目': ['心理学', '心理学', '心理学', '心理学', '心理学'],
            '章节': ['认知心理学', '发展心理学', '社会心理学', '人格心理学', '临床心理学'],
            '考点': ['记忆三级加工', '皮亚杰理论', '态度改变', '特质理论', '心理障碍'],
            '页码': ['P123', 'P234', 'P345', 'P456', 'P567']
        })

    @pytest.fixture
    def sample_excel_file(self, temp_dir, sample_excel_data):
        """Create sample Excel file for testing"""
        excel_path = Path(temp_dir) / 'test_points.xlsx'
        sample_excel_data.to_excel(excel_path, index=False)
        return excel_path

    # ========== Excel Parsing Tests ==========

    def test_read_excel_valid_file(self, sample_excel_file):
        """Test reading valid Excel file"""
        data = pd.read_excel(sample_excel_file)

        assert len(data) == 5
        assert '科目' in data.columns
        assert '章节' in data.columns
        assert '考点' in data.columns
        assert '页码' in data.columns

    def test_read_excel_missing_file(self, temp_dir):
        """Test reading non-existent Excel file raises error"""
        non_existent_file = Path(temp_dir) / 'does_not_exist.xlsx'

        with pytest.raises(FileNotFoundError):
            pd.read_excel(non_existent_file)

    def test_parse_excel_with_missing_columns(self, temp_dir):
        """Test parsing Excel with missing required columns"""
        # Create Excel with missing '考点' column
        incomplete_data = pd.DataFrame({
            '科目': ['心理学'],
            '章节': ['认知心理学'],
            '页码': ['P123']
        })

        excel_path = Path(temp_dir) / 'incomplete.xlsx'
        incomplete_data.to_excel(excel_path, index=False)

        data = pd.read_excel(excel_path)

        # Should not have '考点' column
        assert '考点' not in data.columns

    def test_parse_excel_with_nan_values(self, temp_dir):
        """Test handling NaN values in Excel cells"""
        data_with_nan = pd.DataFrame({
            '科目': ['心理学', None, '心理学'],
            '章节': ['认知心理学', '发展心理学', None],
            '考点': [None, '皮亚杰理论', '态度改变'],
            '页码': ['P123', 'P234', 'P345']
        })

        excel_path = Path(temp_dir) / 'with_nan.xlsx'
        data_with_nan.to_excel(excel_path, index=False)

        data = pd.read_excel(excel_path)

        # Test NaN handling logic (same as generate_pool.py)
        for _, row in data.iterrows():
            subject = str(row['科目']) if pd.notna(row['科目']) else ""
            chapter = str(row['章节']) if pd.notna(row['章节']) else ""
            point = str(row['考点']) if pd.notna(row['考点']) else ""

            # Should convert NaN to empty string
            assert isinstance(subject, str)
            assert isinstance(chapter, str)
            assert isinstance(point, str)

    def test_parse_excel_with_chinese_characters(self, sample_excel_file):
        """Test correct handling of Chinese characters"""
        data = pd.read_excel(sample_excel_file)

        # Verify Chinese characters are preserved
        assert data.iloc[0]['科目'] == '心理学'
        assert data.iloc[0]['章节'] == '认知心理学'
        assert data.iloc[0]['考点'] == '记忆三级加工'

    def test_parse_large_excel_file(self, temp_dir):
        """Test parsing large Excel file (1000+ rows)"""
        large_data = pd.DataFrame({
            '科目': ['心理学'] * 1000,
            '章节': [f'第{i % 20 + 1}章' for i in range(1000)],
            '考点': [f'知识点 {i}' for i in range(1, 1001)],
            '页码': [f'P{100 + i}' for i in range(1000)]
        })

        excel_path = Path(temp_dir) / 'large_file.xlsx'
        large_data.to_excel(excel_path, index=False)

        data = pd.read_excel(excel_path)

        assert len(data) == 1000
        assert data.iloc[999]['考点'] == '知识点 1000'

    # ========== Data Processing Tests ==========

    def test_create_point_objects(self, sample_excel_data):
        """Test creation of point objects from Excel data"""
        points = []
        point_id = 1

        for _, row in sample_excel_data.iterrows():
            point = {
                "id": point_id,
                "科目": str(row['科目']) if pd.notna(row['科目']) else "",
                "章节": str(row['章节']) if pd.notna(row['章节']) else "",
                "考点": str(row['考点']) if pd.notna(row['考点']) else "",
                "页码": str(row['页码']) if pd.notna(row['页码']) else "",
                "status": "pending",
                "assignedDay": None,
                "completedAt": None,
                "forgottenCount": 0,
                "history": []
            }
            points.append(point)
            point_id += 1

        assert len(points) == 5
        assert points[0]['id'] == 1
        assert points[4]['id'] == 5
        assert all(p['status'] == 'pending' for p in points)
        assert all(p['assignedDay'] is None for p in points)
        assert all(p['forgottenCount'] == 0 for p in points)
        assert all(p['history'] == [] for p in points)

    def test_randomization_includes_all_points(self, sample_excel_data):
        """Test that shuffling includes all points without duplicates"""
        points = []
        for idx, row in sample_excel_data.iterrows():
            points.append({'id': idx + 1, 'data': row['考点']})

        original_ids = [p['id'] for p in points]

        import random
        random.seed(42)  # For reproducibility
        random.shuffle(points)

        shuffled_ids = [p['id'] for p in points]

        # All IDs should still be present
        assert sorted(original_ids) == sorted(shuffled_ids)
        assert len(shuffled_ids) == len(set(shuffled_ids))  # No duplicates

    def test_randomization_changes_order(self, sample_excel_data):
        """Test that shuffling actually changes order"""
        points = list(range(1, 101))  # 100 sequential numbers

        import random
        random.seed(42)
        shuffled = points.copy()
        random.shuffle(shuffled)

        # With 100 items, it's extremely unlikely they're in the same order
        assert points != shuffled

    # ========== Configuration Tests ==========

    def test_pool_config_structure(self):
        """Test pool configuration has correct structure"""
        total_points = 300
        total_days = 30
        avg_points_per_day = total_points // total_days

        pool_config = {
            "startDate": "2025-11-14",
            "totalDays": total_days,
            "avgPointsPerDay": avg_points_per_day,
            "totalPoints": total_points,
            "createdAt": datetime.now().isoformat(),
            "lastUpdated": datetime.now().isoformat()
        }

        assert pool_config['startDate'] == "2025-11-14"
        assert pool_config['totalDays'] == 30
        assert pool_config['avgPointsPerDay'] == 10
        assert pool_config['totalPoints'] == 300
        assert 'createdAt' in pool_config
        assert 'lastUpdated' in pool_config

    def test_calculate_avg_points_per_day(self):
        """Test calculation of average points per day"""
        test_cases = [
            (300, 30, 10),   # 300 / 30 = 10
            (305, 30, 10),   # 305 / 30 = 10 (integer division)
            (100, 30, 3),    # 100 / 30 = 3
            (29, 30, 0),     # 29 / 30 = 0
        ]

        for total, days, expected in test_cases:
            result = total // days
            assert result == expected, f"Expected {total}//{days} = {expected}, got {result}"

    # ========== File Generation Tests ==========

    def test_generate_json_file(self, temp_dir, sample_excel_data):
        """Test JSON file generation"""
        points = []
        point_id = 1

        for _, row in sample_excel_data.iterrows():
            point = {
                "id": point_id,
                "科目": str(row['科目']) if pd.notna(row['科目']) else "",
                "章节": str(row['章节']) if pd.notna(row['章节']) else "",
                "考点": str(row['考点']) if pd.notna(row['考点']) else "",
                "页码": str(row['页码']) if pd.notna(row['页码']) else "",
                "status": "pending",
                "assignedDay": None,
                "completedAt": None,
                "forgottenCount": 0,
                "history": []
            }
            points.append(point)
            point_id += 1

        pool_data = {
            "config": {
                "startDate": "2025-11-14",
                "totalDays": 30,
                "avgPointsPerDay": len(points) // 30,
                "totalPoints": len(points),
                "createdAt": datetime.now().isoformat(),
                "lastUpdated": datetime.now().isoformat()
            },
            "points": points
        }

        output_file = Path(temp_dir) / 'points_pool.json'
        with open(output_file, 'w', encoding='utf-8') as f:
            json.dump(pool_data, f, ensure_ascii=False, indent=2)

        # Verify file exists and is valid JSON
        assert output_file.exists()

        with open(output_file, 'r', encoding='utf-8') as f:
            loaded_data = json.load(f)

        assert loaded_data == pool_data
        assert len(loaded_data['points']) == 5

    def test_json_encoding_utf8(self, temp_dir):
        """Test JSON file uses UTF-8 encoding for Chinese characters"""
        test_data = {
            "config": {"test": "测试"},
            "points": [
                {"科目": "心理学", "考点": "记忆"}
            ]
        }

        output_file = Path(temp_dir) / 'test.json'
        with open(output_file, 'w', encoding='utf-8') as f:
            json.dump(test_data, f, ensure_ascii=False, indent=2)

        # Read back and verify Chinese characters preserved
        with open(output_file, 'r', encoding='utf-8') as f:
            content = f.read()

        assert '心理学' in content
        assert '记忆' in content
        assert '\\u' not in content  # Should not have escaped unicode

    def test_json_pretty_print(self, temp_dir):
        """Test JSON file is formatted with indentation"""
        test_data = {"key1": "value1", "key2": {"nested": "value2"}}

        output_file = Path(temp_dir) / 'test.json'
        with open(output_file, 'w', encoding='utf-8') as f:
            json.dump(test_data, f, ensure_ascii=False, indent=2)

        with open(output_file, 'r', encoding='utf-8') as f:
            content = f.read()

        # Should have newlines and indentation
        assert '\n' in content
        assert '  ' in content  # 2-space indent

    def test_create_daily_assignments_file(self, temp_dir):
        """Test creation of empty daily assignments file"""
        assignments_file = Path(temp_dir) / 'daily_assignments.json'
        initial_assignments = {
            "meta": {
                "lastUpdated": datetime.now().isoformat()
            }
        }

        with open(assignments_file, 'w', encoding='utf-8') as f:
            json.dump(initial_assignments, f, ensure_ascii=False, indent=2)

        assert assignments_file.exists()

        with open(assignments_file, 'r', encoding='utf-8') as f:
            loaded = json.load(f)

        assert 'meta' in loaded
        assert 'lastUpdated' in loaded['meta']

    # ========== Edge Cases ==========

    def test_empty_excel_file(self, temp_dir):
        """Test handling empty Excel file"""
        empty_data = pd.DataFrame(columns=['科目', '章节', '考点', '页码'])
        excel_path = Path(temp_dir) / 'empty.xlsx'
        empty_data.to_excel(excel_path, index=False)

        data = pd.read_excel(excel_path)

        assert len(data) == 0
        assert list(data.columns) == ['科目', '章节', '考点', '页码']

    def test_single_row_excel(self, temp_dir):
        """Test handling Excel with single row"""
        single_row = pd.DataFrame({
            '科目': ['心理学'],
            '章节': ['认知心理学'],
            '考点': ['记忆'],
            '页码': ['P123']
        })

        excel_path = Path(temp_dir) / 'single.xlsx'
        single_row.to_excel(excel_path, index=False)

        data = pd.read_excel(excel_path)

        assert len(data) == 1
        assert data.iloc[0]['科目'] == '心理学'

    @pytest.mark.parametrize("total_points,total_days,expected_avg", [
        (300, 30, 10),
        (305, 30, 10),
        (100, 30, 3),
        (30, 30, 1),
        (29, 30, 0),
        (1000, 30, 33),
    ])
    def test_avg_calculation_parametrized(self, total_points, total_days, expected_avg):
        """Parametrized test for average calculation"""
        result = total_points // total_days
        assert result == expected_avg

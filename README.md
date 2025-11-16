# 考研知识点背诵系统

一个简单高效的30天考研冲刺背诵管理系统。

## 快速开始

### 1. 准备工作
```bash
# 确保已安装Python和必要的库
pip install pandas openpyxl
```

### 2. 生成题库
```bash
# 运行题库生成脚本
python scripts/generate_pool.py
```

### 3. 启动服务器
```bash
# 启动PHP内置服务器
php -S localhost:8000
```

### 4. 访问系统
打开浏览器访问：[http://localhost:8000](http://localhost:8000)

### 5. 初始化系统
- 首次访问会显示初始化界面
- 选择学习开始日期（默认今天）
- 点击"开始初始化"按钮
- 等待系统自动分配所有题目到30天
- 初始化完成后自动进入主界面

## 主要功能

### 学习管理
- ✅ **记得**：标记已掌握的知识点（绿色标签）
- ❌ **忘记**：标记需要复习的知识点（橙色标签）
- 📊 **实时统计**：查看学习进度和完成率
- 🗓️ **30天规划**：所有题目均匀分配到30天

### 导航功能
- 切换题目：上一题/下一题
- 切换天数：前一天/后一天/选择指定天
- 查看详情：显示章节和页码信息

### 快捷键
- `Enter` - 标记"记得"
- `←/→` - 切换上一题/下一题
- `D` - 显示/隐藏详情

## 系统特点

### 简单直观
- 界面简洁美观
- 操作流程清晰
- 学习状态一目了然

### 静态分配
- 初始化时一次性分配所有题目
- 用户可提前查看完整30天计划
- 每天题目数量稳定可预期

### 状态可视化
- 每个题目显示"记得"或"忘记"状态
- 绿色/橙色标签直观明了
- 统计信息实时更新

### 智能反馈
- 点击"记得"后今日剩余立即减少
- 自动进入下一题
- 完成当天所有题目后提示

## 数据文件

### data/points_pool.json
题库主文件，包含：
- 所有知识点信息（科目、章节、考点、页码）
- 每个题目的状态（pending/remembered/forgotten）
- 题目分配信息（assignedDay）
- 操作历史记录

### data/daily_assignments.json
每日分配文件，包含：
- 每天分配的题目ID列表
- 已完成的题目ID列表
- 已忘记的题目ID列表
- 元数据（初始化时间、开始日期等）

## 重置系统

### 方法1：使用重置按钮（推荐）

1. 滚动到页面底部
2. 点击"🔄 重置系统"按钮
3. 确认两次警告提示
4. 系统自动清空所有数据并刷新页面
5. 重新选择开始日期并初始化

### 方法2：手动重置

1. 删除 `data/daily_assignments.json`
2. 刷新浏览器
3. 重新选择开始日期并初始化

**⚠️ 重要提示**：
- 重置会清空**所有学习记录和标记**
- 此操作**不可恢复**
- 系统会要求**双重确认**以防误操作
- 重置会清空浏览器缓存（localStorage、sessionStorage、cookies）

## 技术栈

- **前端**：HTML + CSS + JavaScript（原生，无框架）
- **后端**：PHP（无框架）
- **数据**：JSON文件
- **初始化**：Python脚本

## 项目结构

```
bread_review/
├── index.html              # 主界面
├── assets/
│   ├── script.js          # 前端逻辑
│   └── style.css          # 样式文件
├── api/                   # PHP API接口
│   ├── common.php         # 共用函数库
│   ├── initialize.php     # 系统初始化
│   ├── get_init_status.php # 检查初始化状态
│   ├── get_current_day.php # 获取当前天数
│   ├── get_points.php     # 获取题目
│   ├── mark_point.php     # 标记题目状态
│   └── get_stats.php      # 获取统计信息
├── data/                  # 数据文件目录
│   ├── points_pool.json
│   └── daily_assignments.json
├── excel/                 # Excel数据源
├── scripts/               # 脚本文件
│   └── generate_pool.py  # 题库生成脚本
├── tests/                 # 测试文件
│   ├── php/              # PHP测试
│   ├── python/           # Python测试
│   ├── js/               # JavaScript测试
│   └── legacy/           # 旧版测试文件
├── docs/                  # 文档目录
│   ├── README_TEST.md    # 测试说明
│   ├── TESTING.md        # 详细测试文档
│   └── DEBUG_RESET.md    # 调试重置文档
└── README.md             # 使用说明（本文件）
```

## 常见问题

### Q: 初始化失败怎么办？
A: 检查：
1. data目录是否存在且有写权限
2. 是否已运行 `python scripts/generate_pool.py`
3. PHP服务器是否正常运行

### Q: 今日剩余数显示不正确？
A: 这可能是数据不一致导致的，建议重新初始化系统。

### Q: 可以修改每天的题目数量吗？
A: 目前系统自动平均分配。如需自定义，可以修改初始化逻辑或手动编辑 `daily_assignments.json`。

### Q: 支持多用户吗？
A: 当前版本不支持。所有用户共享同一套数据。

### Q: 如何清空之前的学习记录？
A: 点击页面底部的"🔄 重置系统"按钮，系统会清空所有数据并回到初始化界面。

## 测试与质量保证

### 测试统计

![Test Suite](https://img.shields.io/badge/tests-71%20passing-brightgreen)
![Coverage](https://img.shields.io/badge/coverage-90%25-brightgreen)
![Build](https://img.shields.io/badge/build-passing-brightgreen)

**测试覆盖范围**:
- ✅ **PHP 后端**: 42 tests, 633 assertions
- ✅ **Python 数据生成**: 23 tests
- ✅ **JavaScript 前端**: 17 tests
- ✅ **集成测试**: 5 end-to-end scenarios
- ✅ **安全测试**: 18 security tests
- ✅ **性能测试**: 11 performance tests

### 运行测试

```bash
# PHP Tests (PHPUnit)
composer install
./vendor/bin/phpunit                     # All tests
./vendor/bin/phpunit tests/php/Unit     # Unit tests only
./vendor/bin/phpunit --coverage-html tests/coverage/html  # With coverage

# Python Tests (pytest)
pip install -r requirements-dev.txt
python -m pytest                        # All tests
python -m pytest --cov                  # With coverage

# JavaScript Tests (Jest)
npm install
npm test                               # All tests
npm run test:coverage                  # With coverage

# Run all tests
./vendor/bin/phpunit && python -m pytest && npm test
```

### 持续集成

本项目使用 GitHub Actions 进行自动化测试：
- ✅ 每次 push 和 pull request 自动运行测试
- ✅ 代码覆盖率报告上传到 Codecov
- ✅ 安全扫描 (依赖检查、代码分析、秘密扫描)
- ✅ 数据完整性验证

### 测试类型

#### 1. 单元测试
- **文件 I/O 操作**: 文件锁定、并发读写、错误处理
- **状态管理**: pending → remembered → forgotten 状态转换
- **分配算法**: 30天平均分配、余数处理
- **日期验证**: 格式检查、边界条件

#### 2. 集成测试
- 完整工作流: 初始化 → 获取题目 → 标记 → 验证统计
- 忘记/记得循环测试
- 重置和重新初始化
- 多天进度测试

#### 3. 安全测试
- XSS 攻击防护 (脚本标签、事件处理器)
- SQL 注入模式测试
- 路径遍历攻击防护
- 类型混淆测试
- Unicode 和特殊字符处理

#### 4. 性能测试
- 1000+ 知识点加载性能
- 大数据集读写性能
- 并发操作模拟
- 内存使用监控

### 代码质量

- **编码标准**: PSR-12 (PHP), ESLint (JavaScript), PEP 8 (Python)
- **文档**: 所有关键函数都有详细注释
- **错误处理**: 完善的错误日志和用户友好的错误消息
- **安全性**: 输入验证、输出转义、文件权限检查

## 详细文档

- 详细测试说明：`docs/TESTING.md`
- 原有测试文档：`docs/README_TEST.md`
- 调试重置文档：`docs/DEBUG_RESET.md`

## 版本历史

### V3.1 (2025-11-15)
- ✅ 新增系统重置功能
- ✅ 双重确认机制防止误操作
- ✅ 自动清空浏览器缓存
- ✅ 一键回到初始化界面

### V3.0 (2025-11-15)
- ✅ 重构代码，减少36%代码量
- ✅ 新增初始化界面
- ✅ 改为静态分配模式
- ✅ 添加题目状态显示
- ✅ 实现今日剩余实时更新
- ✅ 删除跳过功能
- ✅ 优化用户体验

## 许可证

本项目仅供学习使用。

## 联系方式

如有问题或建议，请联系项目维护者。

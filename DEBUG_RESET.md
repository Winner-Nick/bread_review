# 重置功能调试指南

## 问题现象
点击"重置系统"按钮没有反应，或者重置后无法跳转到初始化界面。

## 调试步骤

### 第1步：打开浏览器控制台
1. 按 `F12` 或右键点击页面选择"检查"
2. 切换到 `Console`（控制台）标签页
3. 清空之前的日志

### 第2步：点击重置按钮
点击页面底部的"🔄 重置系统"按钮，观察控制台输出。

### 第3步：查看控制台日志

#### 正常情况应该看到以下日志：
```
[调试] 重置按钮被点击
[调试] 用户确认重置，开始执行...
[调试] 调用resetSystem API...
[调试] 发送重置请求到后端...
[调试] 收到后端响应，HTTP状态: 200
[调试] 后端返回原始内容: {"success":true...
[调试] 解析后的结果: {success: true, ...}
[调试] 重置成功
[调试] 后端重置成功，开始清空浏览器存储...
[调试] 浏览器存储已清空
[调试] 准备刷新页面...
```

然后页面会自动刷新并显示：
```
[调试] 初始化状态检查结果: {success: true, ...}
[调试] 系统是否已初始化: false
```

## 常见问题排查

### 问题A：点击按钮完全没反应

**症状**：点击"重置系统"按钮，控制台没有任何输出。

**可能原因**：
1. JavaScript加载失败
2. 事件绑定失败

**排查方法**：
```javascript
// 在控制台执行以下命令检查
console.log('重置按钮元素:', document.getElementById('reset-btn'));
console.log('elements对象:', window.elements);
```

**解决方案**：
- 刷新页面（Ctrl+F5 强制刷新）
- 检查是否有JavaScript错误
- 确保PHP服务器正在运行

### 问题B：点击后没有弹出确认对话框

**症状**：看到日志 `[调试] 重置按钮被点击`，但没有弹出确认对话框。

**可能原因**：浏览器阻止了弹窗

**解决方案**：
- 检查浏览器地址栏是否有弹窗拦截提示
- 允许当前网站显示弹窗

### 问题C：确认后没有调用API

**症状**：看到确认日志，但没有看到 `[调试] 调用resetSystem API...`

**可能原因**：JavaScript执行中断

**排查方法**：
查看控制台是否有红色错误信息

### 问题D：API调用失败

**症状**：看到 `[错误] 重置请求失败` 或 HTTP状态不是200

**可能原因**：
1. PHP服务器未启动
2. reset.php文件不存在
3. 文件权限问题

**排查方法**：
```bash
# 1. 检查PHP服务器是否运行
ps aux | grep php  # Linux/Mac
tasklist | findstr php  # Windows

# 2. 检查文件是否存在
ls -la api/reset.php

# 3. 检查文件权限
chmod 644 api/reset.php  # Linux/Mac
```

**解决方案**：
```bash
# 重新启动PHP服务器
php -S localhost:8000
```

### 问题E：后端返回非JSON内容

**症状**：看到 `[错误] 解析JSON失败`

**可能原因**：
1. reset.php有语法错误
2. PHP输出了额外的内容

**排查方法**：
1. 查看控制台中的 `[错误] 原始响应:` 日志
2. 直接访问 `http://localhost:8000/api/reset.php` 查看输出

**解决方案**：
- 检查PHP错误日志
- 确保reset.php中没有echo或print语句
- 确保没有PHP警告或notice

### 问题F：重置成功但刷新后仍显示主界面

**症状**：
- 看到 `[调试] 重置成功`
- 页面刷新了
- 但仍然显示主界面，而不是初始化界面

**可能原因**：
1. 浏览器缓存了API响应
2. daily_assignments.json文件没有被正确重置

**排查方法**：
```bash
# 1. 查看daily_assignments.json内容
cat data/daily_assignments.json

# 应该只包含meta，没有day_1等字段：
# {
#   "meta": {
#     "lastUpdated": "...",
#     "resetAt": "..."
#   }
# }

# 2. 手动访问初始化状态API
curl "http://localhost:8000/api/get_init_status.php?_t=$(date +%s)"
# 或在浏览器中访问：
# http://localhost:8000/api/get_init_status.php?_t=123456789

# 应该返回：
# {"success":true,"message":"Success","data":{"isInitialized":false,"initInfo":null}}
```

**解决方案1：清空浏览器缓存**
```
1. 按 Ctrl+Shift+Delete
2. 选择"缓存的图片和文件"
3. 点击"清除数据"
4. 刷新页面（Ctrl+F5）
```

**解决方案2：手动删除并重试**
```bash
# 1. 删除分配文件
rm data/daily_assignments.json

# 2. 在浏览器中按 Ctrl+Shift+R 强制刷新
```

**解决方案3：使用隐私模式测试**
```
1. 打开浏览器隐私/无痕模式
2. 访问系统
3. 应该直接显示初始化界面
```

### 问题G：文件权限问题

**症状**：看到文件权限相关的错误

**解决方案**：
```bash
# Linux/Mac
chmod 777 data
chmod 666 data/points_pool.json
chmod 666 data/daily_assignments.json

# Windows（在管理员PowerShell中）
icacls data /grant Everyone:(OI)(CI)F
```

## 手动重置步骤

如果以上所有方法都无效，可以尝试手动重置：

```bash
# 1. 停止PHP服务器（Ctrl+C）

# 2. 删除分配文件
rm data/daily_assignments.json

# 3. 重置题库（可选）
python generate_pool.py

# 4. 重新启动服务器
php -S localhost:8000

# 5. 清空浏览器缓存

# 6. 访问系统
# http://localhost:8000
```

## 获取详细日志

如果需要获取更详细的诊断信息，在控制台执行：

```javascript
// 1. 检查当前状态
console.log('当前state:', state);

// 2. 手动测试API
fetch('api/get_init_status.php?_t=' + Date.now(), {cache: 'no-store'})
  .then(r => r.json())
  .then(d => console.log('初始化状态:', d));

// 3. 手动测试重置API
fetch('api/reset.php', {
  method: 'POST',
  headers: {'Content-Type': 'application/json'},
  body: JSON.stringify({confirm: true})
})
.then(r => r.json())
.then(d => console.log('重置结果:', d));
```

## 报告问题

如果以上所有方法都无法解决问题，请提供以下信息：

1. **浏览器信息**：浏览器名称和版本
2. **操作系统**：Windows/Mac/Linux
3. **控制台日志**：完整的控制台输出（截图或文本）
4. **文件内容**：
   ```bash
   # Linux/Mac
   cat data/daily_assignments.json
   head -20 data/points_pool.json

   # Windows
   type data\daily_assignments.json
   ```
5. **PHP版本**：`php -v` 的输出
6. **服务器状态**：PHP服务器是否正在运行

## 快速修复清单

- [ ] PHP服务器正在运行 (`php -S localhost:8000`)
- [ ] 使用HTTP访问，不是file://协议
- [ ] 浏览器控制台没有红色错误
- [ ] data目录有写权限
- [ ] reset.php文件存在于api目录
- [ ] 清空了浏览器缓存（Ctrl+Shift+Delete）
- [ ] 尝试了强制刷新（Ctrl+F5）
- [ ] 尝试了隐私/无痕模式

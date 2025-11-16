/**
 * 考研知识点背诵系统 - 前端交互逻辑 V3
 * 重构版：静态分配 + 状态标记
 */

// ========== 全局状态 ==========
const state = {
    currentDay: 1,              // 当前查看的天数
    actualCurrentDay: 1,        // 实际的当前天数（基于日期）
    dayPoints: [],              // 当天的所有知识点
    currentIndex: 0,            // 当前知识点索引
    stats: null,                // 统计信息
    isInitialized: false        // 系统是否已初始化
};

// ========== API基础URL ==========
const API_BASE = 'api/';

// ========== DOM元素 ==========
const elements = {
    // 初始化面板
    initPanel: document.getElementById('init-panel'),
    mainContainer: document.getElementById('main-container'),
    startDateInput: document.getElementById('start-date-input'),
    initBtn: document.getElementById('init-btn'),

    // 日期选择
    daySelect: document.getElementById('day-select'),
    currentDate: document.getElementById('current-date'),
    prevDayBtn: document.getElementById('prev-day-btn'),
    nextDayBtn: document.getElementById('next-day-btn'),

    // 进度显示
    currentDay: document.getElementById('current-day'),
    remainingPoints: document.getElementById('remaining-points'),
    remainingDays: document.getElementById('remaining-days'),

    // 知识点显示
    subjectBadge: document.getElementById('subject-badge'),
    pointIndex: document.getElementById('point-index'),
    knowledgePoint: document.getElementById('knowledge-point'),
    statusBadge: document.getElementById('status-badge'),
    knowledgeDetails: document.getElementById('knowledge-details'),
    chapterInfo: document.getElementById('chapter-info'),
    pageInfo: document.getElementById('page-info'),

    // 统计
    totalPoints: document.getElementById('total-points'),
    completedPoints: document.getElementById('completed-points'),
    completionRate: document.getElementById('completion-rate'),

    // 按钮
    prevPointBtn: document.getElementById('prev-point-btn'),
    nextPointBtn: document.getElementById('next-point-btn-nav'),
    rememberBtn: document.getElementById('remember-btn'),
    forgetBtn: document.getElementById('forget-btn'),
    showBtn: document.getElementById('show-btn'),
    resetBtn: document.getElementById('reset-btn')
};

// ========== API调用函数 ==========

/**
 * 检查系统初始化状态
 */
async function checkInitStatus() {
    try {
        // 添加时间戳防止缓存
        const timestamp = new Date().getTime();
        const response = await fetch(API_BASE + `get_init_status.php?_t=${timestamp}`, {
            cache: 'no-store'  // 强制不使用缓存
        });
        const result = await response.json();

        console.log('[调试] 初始化状态检查结果:', result);

        if (result.success) {
            state.isInitialized = result.data.isInitialized;
            console.log('[调试] 系统是否已初始化:', state.isInitialized);
            return result.data;
        }
        return { isInitialized: false };
    } catch (error) {
        console.error('检查初始化状态失败:', error);
        return { isInitialized: false };
    }
}

/**
 * 执行系统初始化
 */
async function initialize(startDate) {
    try {
        const response = await fetch(API_BASE + 'initialize.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ startDate })
        });

        const result = await response.json();

        if (result.success) {
            return true;
        } else {
            alert('初始化失败：' + result.error);
            return false;
        }
    } catch (error) {
        console.error('初始化请求失败:', error);
        alert('初始化失败，请检查网络连接');
        return false;
    }
}

/**
 * 获取当前天数
 */
async function getCurrentDay() {
    try {
        const response = await fetch(API_BASE + 'get_current_day.php');

        if (!response.ok) {
            throw new Error(`HTTP错误: ${response.status}`);
        }

        const text = await response.text();

        let result;
        try {
            result = JSON.parse(text);
        } catch (e) {
            console.error('API返回非JSON内容:', text.substring(0, 200));
            alert('错误：API返回了非JSON内容。请确保PHP服务器正在运行。');
            return null;
        }

        if (result.success) {
            state.actualCurrentDay = result.data.currentDay;
            state.currentDay = result.data.currentDay;
            elements.currentDate.textContent = result.data.currentDate;
            return result.data;
        } else {
            console.error('获取当前天数失败:', result.error);
            return null;
        }
    } catch (error) {
        console.error('API请求失败:', error);
        return null;
    }
}

/**
 * 获取指定天数的知识点
 */
async function getPoints(day) {
    try {
        const response = await fetch(API_BASE + `get_points.php?day=${day}`);
        const result = await response.json();

        if (result.success) {
            return result.data;
        } else {
            console.error('获取知识点失败:', result.error);
            if (result.error.includes('初始化')) {
                // 系统未初始化，显示初始化面板
                showInitPanel();
            }
            return null;
        }
    } catch (error) {
        console.error('API请求失败:', error);
        return null;
    }
}

/**
 * 标记知识点状态
 */
async function markPoint(pointId, action, day) {
    try {
        const response = await fetch(API_BASE + 'mark_point.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                pointId: pointId,
                action: action,  // remember, forget
                day: day
            })
        });

        const result = await response.json();

        if (result.success) {
            return true;
        } else {
            console.error('标记失败:', result.error);
            alert('操作失败：' + result.error);
            return false;
        }
    } catch (error) {
        console.error('API请求失败:', error);
        return false;
    }
}

/**
 * 获取统计信息
 */
async function getStats() {
    try {
        const response = await fetch(API_BASE + 'get_stats.php');
        const result = await response.json();

        if (result.success) {
            state.stats = result.data;
            return result.data;
        } else {
            console.error('获取统计失败:', result.error);
            return null;
        }
    } catch (error) {
        console.error('API请求失败:', error);
        return null;
    }
}

/**
 * 重置系统
 */
async function resetSystem() {
    try {
        console.log('[调试] 发送重置请求到后端...');
        const response = await fetch(API_BASE + 'reset.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ confirm: true }),
            cache: 'no-store'
        });

        console.log('[调试] 收到后端响应，HTTP状态:', response.status);

        const text = await response.text();
        console.log('[调试] 后端返回原始内容:', text.substring(0, 500));

        let result;
        try {
            result = JSON.parse(text);
        } catch (e) {
            console.error('[错误] 解析JSON失败:', e);
            console.error('[错误] 原始响应:', text);
            alert('重置失败：服务器返回了无效的响应。\n\n请检查：\n1. PHP服务器是否正常运行\n2. reset.php文件是否存在\n3. 浏览器控制台的详细错误信息');
            return false;
        }

        console.log('[调试] 解析后的结果:', result);

        if (result.success) {
            console.log('[调试] 重置成功');
            return true;
        } else {
            console.error('[错误] 重置失败:', result.error);
            alert('重置失败：' + result.error);
            return false;
        }
    } catch (error) {
        console.error('[错误] 重置请求失败:', error);
        alert('重置失败，请检查网络连接\n\n错误详情: ' + error.message);
        return false;
    }
}

// ========== 界面控制函数 ==========

/**
 * 显示初始化面板
 */
function showInitPanel() {
    elements.initPanel.style.display = 'flex';
    elements.mainContainer.style.display = 'none';

    // 设置默认日期为今天
    const today = new Date().toISOString().split('T')[0];
    elements.startDateInput.value = today;
}

/**
 * 隐藏初始化面板
 */
function hideInitPanel() {
    elements.initPanel.style.display = 'none';
    elements.mainContainer.style.display = 'block';
}

/**
 * 处理初始化按钮点击
 */
async function handleInitialize() {
    const startDate = elements.startDateInput.value;

    if (!startDate) {
        alert('请选择开始日期');
        return;
    }

    // 确认初始化
    const confirmed = confirm(
        `确定要初始化系统吗？\n\n` +
        `开始日期: ${startDate}\n` +
        `这将清空之前的所有学习记录！`
    );

    if (!confirmed) return;

    // 显示加载状态
    elements.initBtn.disabled = true;
    elements.initBtn.textContent = '正在初始化...';

    // 执行初始化
    const success = await initialize(startDate);

    if (success) {
        alert('初始化成功！系统已准备就绪。');
        state.isInitialized = true;
        hideInitPanel();
        await initApp();
    } else {
        elements.initBtn.disabled = false;
        elements.initBtn.textContent = '开始初始化';
    }
}

// ========== 界面更新函数 ==========

/**
 * 加载并显示指定天数的知识点
 */
async function loadDay(day) {
    // 检查是否查看未来的日期
    if (day > state.actualCurrentDay) {
        const confirmed = confirm(
            `注意：您正在查看第${day}天的内容，但今天是第${state.actualCurrentDay}天。\n\n` +
            '提前查看可能影响学习效果，确定要继续吗？'
        );
        if (!confirmed) {
            elements.daySelect.value = state.actualCurrentDay;
            return;
        }
    }

    // 显示加载状态
    elements.knowledgePoint.textContent = '正在加载...';

    // 获取该天的知识点
    const data = await getPoints(day);

    if (!data || !data.points || data.points.length === 0) {
        elements.knowledgePoint.textContent = '该天暂无知识点';
        state.dayPoints = [];
        return;
    }

    // 更新状态
    state.currentDay = day;
    state.dayPoints = data.points;
    state.currentIndex = 0;

    // 更新日期显示（题目所属日期）
    elements.currentDate.textContent = data.date || '';

    // 更新界面
    updateDayInfo(data);
    displayCurrentPoint();
    await updateStats();
}

/**
 * 更新天数相关信息
 */
function updateDayInfo(data) {
    elements.currentDay.textContent = state.currentDay;
    elements.remainingDays.textContent = 30 - state.currentDay;
    elements.remainingPoints.textContent = data.remainingCount || 0;
}

/**
 * 显示当前知识点
 */
function displayCurrentPoint() {
    if (!state.dayPoints || state.dayPoints.length === 0) {
        elements.knowledgePoint.textContent = '暂无知识点';
        elements.statusBadge.textContent = '';
        elements.statusBadge.className = 'status-badge';
        return;
    }

    // 确保索引在范围内
    if (state.currentIndex < 0) {
        state.currentIndex = 0;
    }
    if (state.currentIndex >= state.dayPoints.length) {
        state.currentIndex = state.dayPoints.length - 1;
    }

    const point = state.dayPoints[state.currentIndex];

    // 更新知识点内容
    elements.knowledgePoint.textContent = point.考点 || '无考点信息';
    elements.subjectBadge.textContent = point.科目 || '未知科目';
    elements.pointIndex.textContent = `${state.currentIndex + 1}/${state.dayPoints.length}`;

    // 更新详情
    elements.chapterInfo.textContent = point.章节 || '无章节信息';
    elements.pageInfo.textContent = point.页码 || '无页码信息';
    elements.knowledgeDetails.style.display = 'none';

    // 更新状态标签
    updateStatusBadge(point.status);

    // 更新按钮状态
    updateButtonStates();
}

/**
 * 更新状态标签显示
 */
function updateStatusBadge(status) {
    const badge = elements.statusBadge;

    // 清除之前的类
    badge.className = 'status-badge';

    if (status === 'remembered') {
        badge.textContent = '✅ 记得';
        badge.classList.add('remembered');
    } else if (status === 'forgotten') {
        badge.textContent = '❌ 忘记';
        badge.classList.add('forgotten');
    } else {
        badge.textContent = '';
    }
}

/**
 * 更新按钮启用/禁用状态
 */
function updateButtonStates() {
    // 上一题按钮
    elements.prevPointBtn.disabled = (state.currentIndex <= 0);

    // 下一题按钮
    elements.nextPointBtn.disabled = (state.currentIndex >= state.dayPoints.length - 1);

    // 前一天按钮
    elements.prevDayBtn.disabled = (state.currentDay <= 1);

    // 后一天按钮
    elements.nextDayBtn.disabled = (state.currentDay >= 30);
}

/**
 * 更新统计信息
 */
async function updateStats() {
    const stats = await getStats();
    if (!stats) return;

    elements.totalPoints.textContent = stats.overview.totalPoints;
    elements.completedPoints.textContent = stats.overview.completedCount;
    elements.completionRate.textContent = `${stats.overview.completionRate}%`;
}

// ========== 操作函数 ==========

/**
 * 标记"记得"
 */
async function handleRemember() {
    if (!state.dayPoints || state.currentIndex >= state.dayPoints.length) return;

    const point = state.dayPoints[state.currentIndex];

    // 如果当前已经是"记得"状态，则取消标记
    const action = point.status === 'remembered' ? 'cancel' : 'remember';
    const success = await markPoint(point.id, action, state.currentDay);

    if (success) {
        if (action === 'cancel') {
            // 取消标记，恢复为pending状态
            point.status = 'pending';
            updateStatusBadge('pending');

            // 增加今日剩余（实时更新）
            const currentRemaining = parseInt(elements.remainingPoints.textContent);
            elements.remainingPoints.textContent = currentRemaining + 1;

            // 不自动跳转，停留在当前题目
        } else {
            // 标记为"记得"
            point.status = 'remembered';
            updateStatusBadge('remembered');

            // 减少今日剩余（实时更新）
            const currentRemaining = parseInt(elements.remainingPoints.textContent);
            if (currentRemaining > 0) {
                elements.remainingPoints.textContent = currentRemaining - 1;
            }

            // 自动进入下一题
            if (state.currentIndex < state.dayPoints.length - 1) {
                state.currentIndex++;
                displayCurrentPoint();
            } else {
                alert('恭喜！今天的知识点已全部完成！');
            }
        }

        // 更新统计
        await updateStats();
    }
}

/**
 * 标记"忘记"
 */
async function handleForget() {
    if (!state.dayPoints || state.currentIndex >= state.dayPoints.length) return;

    const point = state.dayPoints[state.currentIndex];

    // 如果当前已经是"忘记"状态，则取消标记
    const action = point.status === 'forgotten' ? 'cancel' : 'forget';
    const success = await markPoint(point.id, action, state.currentDay);

    if (success) {
        if (action === 'cancel') {
            // 取消标记，恢复为pending状态
            point.status = 'pending';
            updateStatusBadge('pending');

            // 重新加载该天数据以更新剩余数量
            const data = await getPoints(state.currentDay);
            if (data) {
                elements.remainingPoints.textContent = data.remainingCount || 0;
            }

            // 不自动跳转，停留在当前题目
        } else {
            // 标记为"忘记"
            point.status = 'forgotten';
            updateStatusBadge('forgotten');

            // 重新加载该天数据以更新剩余数量
            const data = await getPoints(state.currentDay);
            if (data) {
                elements.remainingPoints.textContent = data.remainingCount || 0;
            }

            // 自动进入下一题
            if (state.currentIndex < state.dayPoints.length - 1) {
                state.currentIndex++;
                displayCurrentPoint();
            } else {
                alert('今天的知识点已全部处理！');
            }
        }

        // 更新统计
        await updateStats();
    }
}

/**
 * 上一题
 */
function prevPoint() {
    if (state.currentIndex > 0) {
        state.currentIndex--;
        displayCurrentPoint();
    }
}

/**
 * 下一题
 */
function nextPoint() {
    if (state.currentIndex < state.dayPoints.length - 1) {
        state.currentIndex++;
        displayCurrentPoint();
    }
}

/**
 * 切换详情显示
 */
function toggleDetails() {
    const details = elements.knowledgeDetails;
    if (details.style.display === 'none') {
        details.style.display = 'block';
        elements.showBtn.innerHTML = '<span class="btn-icon">🙈</span>隐藏详情';
    } else {
        details.style.display = 'none';
        elements.showBtn.innerHTML = '<span class="btn-icon">👁️</span>显示详情';
    }
}

/**
 * 切换到指定天数
 */
function switchDay(day) {
    day = parseInt(day);
    if (day < 1 || day > 30) return;

    loadDay(day);
}

/**
 * 处理系统重置
 */
async function handleReset() {
    console.log('[调试] 重置按钮被点击');

    // 第一次确认
    const firstConfirm = confirm(
        '⚠️ 警告：重置系统将会：\n\n' +
        '1. 清空所有学习记录\n' +
        '2. 删除所有题目标记\n' +
        '3. 清空浏览器缓存\n' +
        '4. 回到初始化界面\n\n' +
        '此操作不可恢复！\n\n' +
        '确定要继续吗？'
    );

    if (!firstConfirm) {
        console.log('[调试] 用户取消了第一次确认');
        return;
    }

    // 第二次确认（双重确认，防止误操作）
    const secondConfirm = confirm(
        '⚠️ 最后确认：\n\n' +
        '您即将清空所有学习进度！\n' +
        '这将删除您的所有学习记录和标记！\n\n' +
        '真的要继续吗？'
    );

    if (!secondConfirm) {
        console.log('[调试] 用户取消了第二次确认');
        return;
    }

    console.log('[调试] 用户确认重置，开始执行...');

    // 显示加载状态
    if (elements.resetBtn) {
        elements.resetBtn.disabled = true;
        elements.resetBtn.textContent = '🔄 正在重置...';
    }

    // 执行后端重置
    console.log('[调试] 调用resetSystem API...');
    const success = await resetSystem();

    if (success) {
        console.log('[调试] 后端重置成功，开始清空浏览器存储...');

        // 清空浏览器存储
        try {
            localStorage.clear();
            sessionStorage.clear();

            // 清空所有cookies
            document.cookie.split(";").forEach(function(c) {
                document.cookie = c.replace(/^ +/, "")
                    .replace(/=.*/, "=;expires=" + new Date().toUTCString() + ";path=/");
            });

            console.log('[调试] 浏览器存储已清空');
        } catch (e) {
            console.error('[错误] 清空浏览器存储失败:', e);
        }

        // 提示用户
        alert('✅ 系统重置成功！\n\n页面即将刷新，返回初始化界面。');

        console.log('[调试] 准备刷新页面...');

        // 使用强制刷新，清除所有缓存
        setTimeout(() => {
            window.location.href = window.location.href.split('?')[0] + '?_reset=' + new Date().getTime();
        }, 100);
    } else {
        console.error('[错误] 后端重置失败');
        // 重置失败，恢复按钮状态
        if (elements.resetBtn) {
            elements.resetBtn.disabled = false;
            elements.resetBtn.textContent = '🔄 重置系统';
        }
        alert('❌ 重置失败！请查看控制台了解详细错误信息。');
    }
}

// ========== 初始化 ==========

/**
 * 初始化日期选择器
 */
function initDaySelector() {
    // 填充选项
    for (let i = 1; i <= 30; i++) {
        const option = document.createElement('option');
        option.value = i;
        option.textContent = `第 ${i} 天`;
        elements.daySelect.appendChild(option);
    }

    // 绑定事件
    elements.daySelect.addEventListener('change', (e) => {
        switchDay(e.target.value);
    });

    elements.prevDayBtn.addEventListener('click', () => {
        if (state.currentDay > 1) {
            switchDay(state.currentDay - 1);
            elements.daySelect.value = state.currentDay;
        }
    });

    elements.nextDayBtn.addEventListener('click', () => {
        if (state.currentDay < 30) {
            switchDay(state.currentDay + 1);
            elements.daySelect.value = state.currentDay;
        }
    });
}

/**
 * 绑定事件
 */
function bindEvents() {
    // 初始化按钮
    elements.initBtn.addEventListener('click', handleInitialize);

    // 操作按钮
    elements.rememberBtn.addEventListener('click', handleRemember);
    elements.forgetBtn.addEventListener('click', handleForget);

    // 导航按钮
    elements.prevPointBtn.addEventListener('click', prevPoint);
    elements.nextPointBtn.addEventListener('click', nextPoint);

    // 辅助按钮
    elements.showBtn.addEventListener('click', toggleDetails);

    // 重置按钮
    if (elements.resetBtn) {
        elements.resetBtn.addEventListener('click', handleReset);
    }

    // 键盘快捷键
    document.addEventListener('keydown', (e) => {
        switch(e.key) {
            case 'Enter':
                e.preventDefault();
                handleRemember();
                break;
            case 'ArrowRight':
                e.preventDefault();
                nextPoint();
                break;
            case 'ArrowLeft':
                e.preventDefault();
                prevPoint();
                break;
            case 'd':
            case 'D':
                toggleDetails();
                break;
        }
    });
}

/**
 * 初始化应用
 */
async function initApp() {
    console.log('考研背诵系统V3初始化中...');

    // 检查初始化状态
    const initStatus = await checkInitStatus();

    if (!initStatus.isInitialized) {
        // 未初始化，显示初始化面板
        showInitPanel();
        bindEvents();
        return;
    }

    // 已初始化，继续正常流程
    hideInitPanel();

    // 初始化日期选择器
    initDaySelector();

    // 绑定事件
    bindEvents();

    // 获取当前天数
    await getCurrentDay();

    // 设置选择器
    elements.daySelect.value = state.currentDay;

    // 加载当天的知识点
    await loadDay(state.currentDay);

    // 加载统计信息
    await updateStats();

    console.log('初始化完成！');
}

// ========== 应用启动 ==========
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initApp);
} else {
    initApp();
}

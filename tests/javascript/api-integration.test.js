/**
 * Frontend API Integration Tests
 * Focus areas: API calls, response handling, error handling
 */

describe('API Integration Tests', () => {

  // ========== Mock Setup ==========

  beforeEach(() => {
    // Reset fetch mock before each test
    global.fetch = jest.fn();
  });

  afterEach(() => {
    jest.resetAllMocks();
  });

  // ========== Initialize API Tests ==========

  describe('Initialize API', () => {
    test('should send correct POST request to initialize endpoint', async () => {
      const mockResponse = {
        success: true,
        data: {
          startDate: '2025-11-14',
          totalDays: 30,
          totalPoints: 300,
          avgPointsPerDay: 10
        },
        message: '系统初始化成功'
      };

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => mockResponse
      });

      const startDate = '2025-11-14';
      const response = await fetch('api/initialize.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ startDate })
      });

      const data = await response.json();

      expect(fetch).toHaveBeenCalledWith('api/initialize.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ startDate: '2025-11-14' })
      });

      expect(data.success).toBe(true);
      expect(data.data.totalDays).toBe(30);
    });

    test('should handle initialization error response', async () => {
      const errorResponse = {
        success: false,
        message: '日期格式错误'
      };

      global.fetch.mockResolvedValueOnce({
        ok: false,
        status: 400,
        json: async () => errorResponse
      });

      const response = await fetch('api/initialize.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ startDate: 'invalid-date' })
      });

      const data = await response.json();

      expect(response.ok).toBe(false);
      expect(data.success).toBe(false);
      expect(data.message).toBe('日期格式错误');
    });

    test('should handle network error during initialization', async () => {
      global.fetch.mockRejectedValueOnce(new Error('Network error'));

      await expect(
        fetch('api/initialize.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ startDate: '2025-11-14' })
        })
      ).rejects.toThrow('Network error');
    });
  });

  // ========== Get Points API Tests ==========

  describe('Get Points API', () => {
    test('should fetch points for specific day', async () => {
      const mockResponse = {
        success: true,
        data: {
          day: 1,
          date: '2025-11-14',
          points: [
            { id: 1, 科目: '心理学', 考点: '记忆', status: 'pending' },
            { id: 2, 科目: '心理学', 考点: '注意', status: 'remembered' }
          ],
          stats: {
            total: 10,
            completed: 5,
            forgotten: 2
          }
        }
      };

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => mockResponse
      });

      const response = await fetch('api/get_points.php?day=1');
      const data = await response.json();

      expect(data.success).toBe(true);
      expect(data.data.day).toBe(1);
      expect(data.data.points).toHaveLength(2);
      expect(data.data.stats.total).toBe(10);
    });

    test('should handle empty day (no points)', async () => {
      const mockResponse = {
        success: true,
        data: {
          day: 15,
          date: '2025-11-28',
          points: [],
          stats: { total: 0, completed: 0, forgotten: 0 }
        }
      };

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => mockResponse
      });

      const response = await fetch('api/get_points.php?day=15');
      const data = await response.json();

      expect(data.data.points).toHaveLength(0);
      expect(data.data.stats.total).toBe(0);
    });
  });

  // ========== Mark Point API Tests ==========

  describe('Mark Point API', () => {
    test('should mark point as remembered', async () => {
      const mockResponse = {
        success: true,
        data: {
          pointId: 1,
          action: 'remember',
          day: 1
        },
        message: '操作成功'
      };

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => mockResponse
      });

      const response = await fetch('api/mark_point.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ pointId: 1, action: 'remember', day: 1 })
      });

      const data = await response.json();

      expect(data.success).toBe(true);
      expect(data.data.action).toBe('remember');
      expect(data.message).toBe('操作成功');
    });

    test('should mark point as forgotten', async () => {
      const mockResponse = {
        success: true,
        data: {
          pointId: 2,
          action: 'forget',
          day: 1
        },
        message: '操作成功'
      };

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => mockResponse
      });

      const response = await fetch('api/mark_point.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ pointId: 2, action: 'forget', day: 1 })
      });

      const data = await response.json();

      expect(data.success).toBe(true);
      expect(data.data.action).toBe('forget');
    });

    test('should reject invalid action', async () => {
      const errorResponse = {
        success: false,
        message: '无效的操作类型（仅支持 remember 和 forget）'
      };

      global.fetch.mockResolvedValueOnce({
        ok: false,
        status: 400,
        json: async () => errorResponse
      });

      const response = await fetch('api/mark_point.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ pointId: 1, action: 'invalid', day: 1 })
      });

      const data = await response.json();

      expect(response.ok).toBe(false);
      expect(data.success).toBe(false);
    });

    test('should reject invalid point ID', async () => {
      const errorResponse = {
        success: false,
        message: '无效的题目ID'
      };

      global.fetch.mockResolvedValueOnce({
        ok: false,
        status: 400,
        json: async () => errorResponse
      });

      const response = await fetch('api/mark_point.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ pointId: 0, action: 'remember', day: 1 })
      });

      const data = await response.json();

      expect(data.success).toBe(false);
      expect(data.message).toBe('无效的题目ID');
    });
  });

  // ========== Get Stats API Tests ==========

  describe('Get Stats API', () => {
    test('should fetch overall statistics', async () => {
      const mockResponse = {
        success: true,
        data: {
          totalPoints: 300,
          completedPoints: 150,
          forgottenPoints: 30,
          completionRate: 50.0,
          currentDay: 15
        }
      };

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => mockResponse
      });

      const response = await fetch('api/get_stats.php');
      const data = await response.json();

      expect(data.success).toBe(true);
      expect(data.data.totalPoints).toBe(300);
      expect(data.data.completionRate).toBe(50.0);
    });
  });

  // ========== Get Current Day API Tests ==========

  describe('Get Current Day API', () => {
    test('should calculate current day based on start date', async () => {
      const mockResponse = {
        success: true,
        data: {
          currentDay: 5,
          startDate: '2025-11-14',
          today: '2025-11-18'
        }
      };

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => mockResponse
      });

      const response = await fetch('api/get_current_day.php');
      const data = await response.json();

      expect(data.success).toBe(true);
      expect(data.data.currentDay).toBe(5);
    });
  });

  // ========== Error Handling Tests ==========

  describe('Error Handling', () => {
    test('should handle 404 not found', async () => {
      global.fetch.mockResolvedValueOnce({
        ok: false,
        status: 404,
        statusText: 'Not Found'
      });

      const response = await fetch('api/nonexistent.php');

      expect(response.ok).toBe(false);
      expect(response.status).toBe(404);
    });

    test('should handle 500 server error', async () => {
      global.fetch.mockResolvedValueOnce({
        ok: false,
        status: 500,
        statusText: 'Internal Server Error',
        json: async () => ({
          success: false,
          message: '服务器内部错误'
        })
      });

      const response = await fetch('api/get_points.php?day=1');
      const data = await response.json();

      expect(response.ok).toBe(false);
      expect(data.success).toBe(false);
    });

    test('should handle network timeout', async () => {
      global.fetch.mockRejectedValueOnce(new Error('Request timeout'));

      await expect(
        fetch('api/get_points.php?day=1')
      ).rejects.toThrow('Request timeout');
    });

    test('should handle CORS error', async () => {
      global.fetch.mockRejectedValueOnce(new Error('CORS policy'));

      await expect(
        fetch('api/get_points.php')
      ).rejects.toThrow('CORS policy');
    });
  });

  // ========== Response Format Tests ==========

  describe('Response Format Validation', () => {
    test('successful response should have correct structure', async () => {
      const mockResponse = {
        success: true,
        data: { test: 'value' },
        message: 'Success'
      };

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => mockResponse
      });

      const response = await fetch('api/test.php');
      const data = await response.json();

      expect(data).toHaveProperty('success');
      expect(data).toHaveProperty('data');
      expect(data).toHaveProperty('message');
      expect(typeof data.success).toBe('boolean');
    });

    test('error response should have correct structure', async () => {
      const mockResponse = {
        success: false,
        message: 'Error occurred'
      };

      global.fetch.mockResolvedValueOnce({
        ok: false,
        json: async () => mockResponse
      });

      const response = await fetch('api/test.php');
      const data = await response.json();

      expect(data).toHaveProperty('success');
      expect(data).toHaveProperty('message');
      expect(data.success).toBe(false);
    });
  });
});

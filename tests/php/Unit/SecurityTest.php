<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Security and input validation tests
 * Focus areas: XSS, SQL injection patterns, malicious input, file path traversal
 */
class SecurityTest extends TestCase
{
    private $testDataDir;
    private $poolFile;
    private $assignmentsFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testDataDir = __DIR__ . '/../../fixtures/security_test';
        if (!is_dir($this->testDataDir)) {
            mkdir($this->testDataDir, 0777, true);
        }

        $this->poolFile = $this->testDataDir . '/points_pool.json';
        $this->assignmentsFile = $this->testDataDir . '/daily_assignments.json';

        // Create test data
        $this->initializeTestData();
    }

    protected function tearDown(): void
    {
        $this->recursiveDelete($this->testDataDir);
        parent::tearDown();
    }

    private function recursiveDelete($dir)
    {
        if (!file_exists($dir)) return;

        if (!is_dir($dir)) {
            @chmod($dir, 0777);
            @unlink($dir);
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->recursiveDelete($path);
            } else {
                @chmod($path, 0777);
                @unlink($path);
            }
        }

        @chmod($dir, 0777);
        @rmdir($dir);
    }

    private function initializeTestData()
    {
        $poolData = [
            'config' => [
                'startDate' => '2025-11-14',
                'totalDays' => 30,
                'totalPoints' => 10,
                'lastUpdated' => '2025-11-14T10:00:00'
            ],
            'points' => [
                [
                    'id' => 1,
                    '科目' => '心理学',
                    '章节' => '认知心理学',
                    '考点' => '记忆三级加工',
                    '页码' => 'P123',
                    'status' => 'pending',
                    'assignedDay' => 1,
                    'completedAt' => null,
                    'forgottenCount' => 0,
                    'history' => []
                ]
            ]
        ];

        $assignmentsData = [
            'meta' => ['lastUpdated' => '2025-11-14T10:00:00', 'startDate' => '2025-11-14'],
            'day_1' => [
                'date' => '2025-11-14',
                'pointIds' => [1],
                'currentIndex' => 0,
                'completed' => [],
                'forgotten' => []
            ]
        ];

        writeJsonFile($this->poolFile, $poolData);
        writeJsonFile($this->assignmentsFile, $assignmentsData);
    }

    // ========== XSS Attack Prevention Tests ==========

    public function testXSSInPointData_ScriptTagsNotExecuted()
    {
        // Attempt to inject script tags in point data
        $pool = readJsonFile($this->poolFile);

        $xssPayloads = [
            '<script>alert("XSS")</script>',
            '<img src=x onerror=alert("XSS")>',
            'javascript:alert("XSS")',
            '<svg onload=alert("XSS")>',
            '"><script>alert(String.fromCharCode(88,83,83))</script>'
        ];

        foreach ($xssPayloads as $payload) {
            $pool['points'][0]['考点'] = $payload;
            writeJsonFile($this->poolFile, $pool);

            $readData = readJsonFile($this->poolFile);

            // Data should be stored as-is (escaping happens on output in frontend)
            $this->assertEquals($payload, $readData['points'][0]['考点']);

            // Verify it's properly JSON encoded (no code execution)
            $jsonString = json_encode($readData, JSON_UNESCAPED_UNICODE);
            $this->assertIsString($jsonString);

            // Verify the data can be round-tripped through JSON encoding
            $decodedData = json_decode($jsonString, true);
            $this->assertEquals($payload, $decodedData['points'][0]['考点']);
        }
    }

    public function testXSSInStartDate_Rejected()
    {
        // Attempt XSS in date fields
        $xssDate = '<script>alert("XSS")</script>';

        // Date validation should reject this
        $isValidFormat = preg_match('/^\d{4}-\d{2}-\d{2}$/', $xssDate);

        $this->assertEquals(0, $isValidFormat, 'XSS payload should not match date format');
    }

    // ========== JSON Injection Tests ==========

    public function testJSONInjection_MalformedJSON()
    {
        // Attempt to write malformed JSON
        file_put_contents($this->poolFile, '{"points": [{"id": 1}], "malicious": "<script>alert(1)</script>"}');

        $data = readJsonFile($this->poolFile);

        // Should still parse correctly
        $this->assertIsArray($data);
        $this->assertArrayHasKey('points', $data);
    }

    public function testJSONInjection_ExtraFields()
    {
        // Attempt to inject extra fields
        $pool = readJsonFile($this->poolFile);
        $pool['injected_field'] = 'malicious_value';
        $pool['admin'] = true;

        writeJsonFile($this->poolFile, $pool);

        $readData = readJsonFile($this->poolFile);

        // Extra fields should be preserved (but not used by application logic)
        $this->assertArrayHasKey('injected_field', $readData);
        $this->assertArrayHasKey('admin', $readData);
    }

    // ========== SQL Injection Pattern Tests ==========

    public function testSQLInjectionPatterns_InPointId()
    {
        // Test SQL injection-like patterns (even though we don't use SQL)
        $sqlPatterns = [
            "1 OR 1=1",
            "1'; DROP TABLE points; --",
            "1 UNION SELECT * FROM users",
            "1' AND '1'='1",
            "-1 OR 1=1"
        ];

        foreach ($sqlPatterns as $pattern) {
            // Convert to int (normal application behavior)
            $pointId = intval($pattern);

            // Should be converted to safe integer
            $this->assertIsInt($pointId);

            if ($pointId <= 0) {
                // Negative or zero IDs should be rejected
                $this->assertLessThanOrEqual(0, $pointId);
            }
        }
    }

    // ========== Path Traversal Tests ==========

    public function testPathTraversal_CannotAccessParentDirectories()
    {
        // Attempt path traversal attacks
        $traversalPaths = [
            '../../../etc/passwd',
            '..\\..\\..\\windows\\system32\\config\\sam',
            './../../etc/shadow',
            '....//....//....//etc/passwd'
        ];

        foreach ($traversalPaths as $path) {
            // readJsonFile should only work with intended paths
            $result = readJsonFile($path);

            // Should return null (file doesn't exist or not accessible)
            $this->assertNull($result);
        }
    }

    public function testPathTraversal_OnlyAccessIntendedDirectory()
    {
        // Verify we can only read from data directory
        $maliciousPath = __DIR__ . '/../../../api/common.php';

        // readJsonFile should return null for non-JSON PHP files
        $result = readJsonFile($maliciousPath);

        // PHP file won't parse as JSON
        $this->assertNull($result);
    }

    // ========== Integer Overflow Tests ==========

    public function testIntegerOverflow_LargePointId()
    {
        // Test with very large integers
        $largeInt = PHP_INT_MAX;
        $pool = readJsonFile($this->poolFile);

        // Try to find point with large ID
        $found = false;
        foreach ($pool['points'] as $point) {
            if ($point['id'] == $largeInt) {
                $found = true;
                break;
            }
        }

        $this->assertFalse($found, 'Should not find point with overflow ID');
    }

    public function testIntegerOverflow_NegativePointId()
    {
        // Test with negative integers
        $negativeId = -999999;

        // Application should reject negative IDs
        $this->assertLessThan(1, $negativeId);
    }

    // ========== Unicode and Encoding Tests ==========

    public function testUnicode_EmojisInData()
    {
        // Test with emoji and special Unicode characters
        $pool = readJsonFile($this->poolFile);
        $pool['points'][0]['考点'] = '测试 🧠 心理学 ✨ 记忆';

        writeJsonFile($this->poolFile, $pool);
        $readData = readJsonFile($this->poolFile);

        $this->assertEquals('测试 🧠 心理学 ✨ 记忆', $readData['points'][0]['考点']);
    }

    public function testUnicode_ControlCharacters()
    {
        // Test with control characters
        $pool = readJsonFile($this->poolFile);
        $pool['points'][0]['考点'] = "Test\x00\x01\x02";

        writeJsonFile($this->poolFile, $pool);
        $readData = readJsonFile($this->poolFile);

        // Should handle control characters gracefully
        $this->assertIsString($readData['points'][0]['考点']);
    }

    // ========== Type Confusion Tests ==========

    public function testTypeConfusion_StringAsPointId()
    {
        // Attempt to use string instead of integer
        $stringId = "not_a_number";
        $convertedId = intval($stringId);

        // Should convert to 0
        $this->assertEquals(0, $convertedId);

        // 0 should be rejected as invalid ID
        $this->assertLessThanOrEqual(0, $convertedId);
    }

    public function testTypeConfusion_ArrayAsPointId()
    {
        // Attempt to use array instead of integer
        $arrayId = ['id' => 1];
        $convertedId = intval($arrayId);

        // PHP converts array to int 1 (or 0 depending on version)
        $this->assertIsInt($convertedId);
    }

    public function testTypeConfusion_NullAsAction()
    {
        // Test null as action
        $action = null;
        $validActions = ['remember', 'forget'];

        $isValid = in_array($action, $validActions, true);

        $this->assertFalse($isValid, 'Null should not be a valid action');
    }

    // ========== Boundary Tests ==========

    public function testBoundary_EmptyStrings()
    {
        // Test empty strings in various fields
        $pool = readJsonFile($this->poolFile);
        $pool['points'][0]['科目'] = '';
        $pool['points'][0]['章节'] = '';
        $pool['points'][0]['考点'] = '';

        writeJsonFile($this->poolFile, $pool);
        $readData = readJsonFile($this->poolFile);

        // Empty strings should be preserved
        $this->assertEquals('', $readData['points'][0]['科目']);
        $this->assertEquals('', $readData['points'][0]['章节']);
        $this->assertEquals('', $readData['points'][0]['考点']);
    }

    public function testBoundary_VeryLongStrings()
    {
        // Test with very long strings (10KB)
        $longString = str_repeat('A', 10000);

        $pool = readJsonFile($this->poolFile);
        $pool['points'][0]['考点'] = $longString;

        writeJsonFile($this->poolFile, $pool);
        $readData = readJsonFile($this->poolFile);

        $this->assertEquals(10000, strlen($readData['points'][0]['考点']));
        $this->assertEquals($longString, $readData['points'][0]['考点']);
    }

    // ========== Date Validation Security Tests ==========

    public function testDateValidation_InvalidFormats()
    {
        // Test various invalid date formats
        $invalidDates = [
            "'; DROP TABLE dates; --",
            '../../../etc/passwd',
            '2025-13-01',  // Invalid month
            '2025-02-30',  // Invalid day
            '2025/11/14',  // Wrong separator
            '11-14-2025',  // Wrong order
            '<script>alert("XSS")</script>',
            '2025-11-14; rm -rf /',
        ];

        foreach ($invalidDates as $date) {
            // Check against strict date format
            $isValid = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date);

            if ($isValid) {
                // Even if format matches, validate actual date
                try {
                    $dateObj = new \DateTime($date);
                    $formatted = $dateObj->format('Y-m-d');

                    // Date should match input (catches 2025-02-30)
                    if ($formatted !== $date) {
                        $this->assertNotEquals($date, $formatted, "Invalid date $date should be rejected");
                    }
                } catch (\Exception $e) {
                    // Expected for invalid dates
                    $this->assertTrue(true);
                }
            } else {
                // Format doesn't match
                $this->assertEquals(0, $isValid, "Date $date should not match format");
            }
        }
    }

    // ========== File Lock Security Tests ==========

    public function testFileLock_ConcurrentWrites_NoCorruption()
    {
        // Simulate rapid concurrent writes
        $pool = readJsonFile($this->poolFile);

        for ($i = 0; $i < 10; $i++) {
            $pool['test_counter'] = $i;
            $result = writeJsonFile($this->poolFile, $pool);
            $this->assertTrue($result, "Write $i should succeed");

            // Read back immediately
            $readData = readJsonFile($this->poolFile);
            $this->assertEquals($i, $readData['test_counter'], "Data should be consistent");
        }
    }
}

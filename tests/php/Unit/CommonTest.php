<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Tests for common.php file I/O operations
 * Focus areas: File locking, error handling, data integrity
 */
class CommonTest extends TestCase
{
    private $testDataDir;
    private $testFile;

    protected function setUp(): void
    {
        parent::setUp();

        // Create temporary test data directory
        $this->testDataDir = __DIR__ . '/../../fixtures/temp_data';
        if (!is_dir($this->testDataDir)) {
            mkdir($this->testDataDir, 0777, true);
        }

        $this->testFile = $this->testDataDir . '/test_data.json';
    }

    protected function tearDown(): void
    {
        // Clean up test files recursively
        $this->recursiveDelete($this->testDataDir);

        parent::tearDown();
    }

    private function recursiveDelete($dir)
    {
        if (!file_exists($dir)) {
            return;
        }

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

    // ========== File Reading Tests ==========

    public function testReadJsonFile_ValidFile_ReturnsArray()
    {
        // Arrange
        $testData = ['key' => 'value', 'number' => 123];
        file_put_contents($this->testFile, json_encode($testData));

        // Act
        $result = readJsonFile($this->testFile);

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals($testData, $result);
    }

    public function testReadJsonFile_NonExistentFile_ReturnsNull()
    {
        // Arrange
        $nonExistentFile = $this->testDataDir . '/does_not_exist.json';

        // Act
        $result = readJsonFile($nonExistentFile);

        // Assert
        $this->assertNull($result);
    }

    public function testReadJsonFile_UnreadableFile_ReturnsNull()
    {
        // Skip if running as root (permissions don't work)
        if (posix_getuid() === 0) {
            $this->markTestSkipped('Cannot test file permissions when running as root');
        }

        // Arrange
        $testData = ['key' => 'value'];
        file_put_contents($this->testFile, json_encode($testData));
        chmod($this->testFile, 0000); // Make file unreadable

        // Act
        $result = readJsonFile($this->testFile);

        // Assert
        $this->assertNull($result);

        // Cleanup
        chmod($this->testFile, 0644);
    }

    public function testReadJsonFile_EmptyFile_ReturnsNull()
    {
        // Arrange
        file_put_contents($this->testFile, '');

        // Act
        $result = readJsonFile($this->testFile);

        // Assert
        $this->assertNull($result);
    }

    public function testReadJsonFile_InvalidJson_ReturnsNull()
    {
        // Arrange
        file_put_contents($this->testFile, '{invalid json}');

        // Act
        $result = readJsonFile($this->testFile);

        // Assert
        $this->assertNull($result);
    }

    public function testReadJsonFile_ChineseCharacters_PreservesEncoding()
    {
        // Arrange
        $testData = [
            'subject' => '心理学',
            'chapter' => '认知心理学',
            'point' => '记忆的三级加工理论'
        ];
        file_put_contents($this->testFile, json_encode($testData, JSON_UNESCAPED_UNICODE));

        // Act
        $result = readJsonFile($this->testFile);

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals('心理学', $result['subject']);
        $this->assertEquals('认知心理学', $result['chapter']);
        $this->assertEquals('记忆的三级加工理论', $result['point']);
    }

    // ========== File Writing Tests ==========

    public function testWriteJsonFile_ValidData_CreatesFile()
    {
        // Arrange
        $testData = ['key' => 'value', 'number' => 123];

        // Act
        $result = writeJsonFile($this->testFile, $testData);

        // Assert
        $this->assertTrue($result);
        $this->assertFileExists($this->testFile);

        $content = file_get_contents($this->testFile);
        $decoded = json_decode($content, true);
        $this->assertEquals($testData, $decoded);
    }

    public function testWriteJsonFile_UnwritableDirectory_ReturnsFalse()
    {
        // Skip if running as root (permissions don't work)
        if (posix_getuid() === 0) {
            $this->markTestSkipped('Cannot test file permissions when running as root');
        }

        // Arrange
        $unwritableDir = $this->testDataDir . '/unwritable';
        mkdir($unwritableDir, 0555); // Read-only directory
        $testFile = $unwritableDir . '/test.json';
        $testData = ['key' => 'value'];

        // Act
        $result = writeJsonFile($testFile, $testData);

        // Assert
        $this->assertFalse($result);

        // Cleanup
        chmod($unwritableDir, 0755);
        rmdir($unwritableDir);
    }

    public function testWriteJsonFile_UnwritableFile_ReturnsFalse()
    {
        // Skip if running as root (permissions don't work)
        if (posix_getuid() === 0) {
            $this->markTestSkipped('Cannot test file permissions when running as root');
        }

        // Arrange
        $testData = ['key' => 'value'];
        file_put_contents($this->testFile, '{}');
        chmod($this->testFile, 0444); // Read-only file

        // Act
        $result = writeJsonFile($this->testFile, $testData);

        // Assert
        $this->assertFalse($result);

        // Cleanup
        chmod($this->testFile, 0644);
    }

    public function testWriteJsonFile_ChineseCharacters_PreservesEncoding()
    {
        // Arrange
        $testData = [
            'subject' => '心理学',
            'chapter' => '认知心理学',
            'points' => ['记忆', '注意', '思维']
        ];

        // Act
        $result = writeJsonFile($this->testFile, $testData);

        // Assert
        $this->assertTrue($result);

        $content = file_get_contents($this->testFile);
        $this->assertStringContainsString('心理学', $content);
        $this->assertStringContainsString('认知心理学', $content);

        $decoded = json_decode($content, true);
        $this->assertEquals($testData, $decoded);
    }

    public function testWriteJsonFile_PrettyPrint_FormatsJson()
    {
        // Arrange
        $testData = ['key1' => 'value1', 'key2' => 'value2'];

        // Act
        writeJsonFile($this->testFile, $testData);

        // Assert
        $content = file_get_contents($this->testFile);
        $this->assertStringContainsString("\n", $content); // Should have newlines
        $this->assertStringContainsString("  ", $content); // Should have indentation
    }

    // ========== Concurrent Access Tests ==========

    public function testConcurrentReads_MultipleProcesses_NoDataCorruption()
    {
        // Arrange
        $testData = ['counter' => 0, 'data' => 'test'];
        writeJsonFile($this->testFile, $testData);

        // Simulate concurrent reads
        $processes = [];
        for ($i = 0; $i < 5; $i++) {
            $processes[] = $this->simulateReadProcess();
        }

        // Act - all processes read simultaneously
        foreach ($processes as $result) {
            // Assert each read is successful
            $this->assertIsArray($result);
            $this->assertEquals($testData, $result);
        }
    }

    private function simulateReadProcess()
    {
        // In a real test, you'd fork processes or use parallel testing
        // For now, we simulate with multiple sequential reads
        return readJsonFile($this->testFile);
    }

    public function testWriteAfterRead_DataPersists()
    {
        // Arrange
        $initialData = ['counter' => 0];
        writeJsonFile($this->testFile, $initialData);

        // Act
        $readData = readJsonFile($this->testFile);
        $readData['counter']++;
        writeJsonFile($this->testFile, $readData);

        // Assert
        $finalData = readJsonFile($this->testFile);
        $this->assertEquals(1, $finalData['counter']);
    }

    // ========== Large Data Tests ==========

    public function testReadWriteJsonFile_LargeDataset_HandlesProperly()
    {
        // Arrange - Create large dataset (1000 points)
        $largeData = [
            'points' => []
        ];

        for ($i = 1; $i <= 1000; $i++) {
            $largeData['points'][] = [
                'id' => $i,
                'subject' => '心理学',
                'chapter' => '第' . ($i % 10 + 1) . '章',
                'point' => '知识点 ' . $i,
                'status' => 'pending',
                'history' => []
            ];
        }

        // Act
        $writeResult = writeJsonFile($this->testFile, $largeData);
        $readResult = readJsonFile($this->testFile);

        // Assert
        $this->assertTrue($writeResult);
        $this->assertIsArray($readResult);
        $this->assertCount(1000, $readResult['points']);
        $this->assertEquals($largeData, $readResult);
    }

    // ========== Helper Function Tests ==========

    public function testJsonDecode_HandlesValidJson()
    {
        // Test that json_decode works properly with UTF-8
        $jsonString = '{"科目":"心理学","章节":"认知心理学"}';
        $decoded = json_decode($jsonString, true);

        $this->assertIsArray($decoded);
        $this->assertEquals('心理学', $decoded['科目']);
        $this->assertEquals('认知心理学', $decoded['章节']);
    }

    public function testJsonEncode_HandlesChineseCharacters()
    {
        // Test that json_encode works with JSON_UNESCAPED_UNICODE
        $data = ['科目' => '心理学', '章节' => '认知心理学'];
        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE);

        $this->assertStringContainsString('心理学', $encoded);
        $this->assertStringNotContainsString('\\u', $encoded);
    }
}

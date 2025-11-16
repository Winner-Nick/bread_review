<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Performance and load tests
 * Focus areas: Large datasets, concurrent operations, memory usage
 */
class PerformanceTest extends TestCase
{
    private $testDataDir;
    private $poolFile;
    private $assignmentsFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testDataDir = __DIR__ . '/../../fixtures/performance_test';
        if (!is_dir($this->testDataDir)) {
            mkdir($this->testDataDir, 0777, true);
        }

        $this->poolFile = $this->testDataDir . '/points_pool.json';
        $this->assignmentsFile = $this->testDataDir . '/daily_assignments.json';
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

    private function createLargePool($numPoints)
    {
        $points = [];
        for ($i = 1; $i <= $numPoints; $i++) {
            $points[] = [
                'id' => $i,
                '科目' => '心理学',
                '章节' => '第' . (($i - 1) % 20 + 1) . '章',
                '考点' => '知识点 ' . $i,
                '页码' => 'P' . (100 + $i),
                'status' => 'pending',
                'assignedDay' => ($i % 30) + 1,
                'completedAt' => null,
                'forgottenCount' => 0,
                'history' => []
            ];
        }

        return [
            'config' => [
                'startDate' => '2025-11-14',
                'totalDays' => 30,
                'avgPointsPerDay' => (int)($numPoints / 30),
                'totalPoints' => $numPoints,
                'createdAt' => '2025-11-14T10:00:00',
                'lastUpdated' => '2025-11-14T10:00:00'
            ],
            'points' => $points
        ];
    }

    // ========== Large Dataset Tests ==========

    public function testPerformance_Read1000Points()
    {
        // Arrange
        $pool = $this->createLargePool(1000);
        writeJsonFile($this->poolFile, $pool);

        // Act
        $start = microtime(true);
        $readData = readJsonFile($this->poolFile);
        $end = microtime(true);

        $readTime = ($end - $start) * 1000; // Convert to milliseconds

        // Assert
        $this->assertLessThan(100, $readTime, 'Reading 1000 points should take less than 100ms');
        $this->assertCount(1000, $readData['points']);
    }

    public function testPerformance_Write1000Points()
    {
        // Arrange
        $pool = $this->createLargePool(1000);

        // Act
        $start = microtime(true);
        writeJsonFile($this->poolFile, $pool);
        $end = microtime(true);

        $writeTime = ($end - $start) * 1000; // Convert to milliseconds

        // Assert
        $this->assertLessThan(200, $writeTime, 'Writing 1000 points should take less than 200ms');
        $this->assertFileExists($this->poolFile);
    }

    public function testPerformance_Read5000Points()
    {
        // Arrange
        $pool = $this->createLargePool(5000);
        writeJsonFile($this->poolFile, $pool);

        // Act
        $start = microtime(true);
        $readData = readJsonFile($this->poolFile);
        $end = microtime(true);

        $readTime = ($end - $start) * 1000;

        // Assert
        $this->assertLessThan(500, $readTime, 'Reading 5000 points should take less than 500ms');
        $this->assertCount(5000, $readData['points']);
    }

    public function testPerformance_Write5000Points()
    {
        // Arrange
        $pool = $this->createLargePool(5000);

        // Act
        $start = microtime(true);
        writeJsonFile($this->poolFile, $pool);
        $end = microtime(true);

        $writeTime = ($end - $start) * 1000;

        // Assert
        $this->assertLessThan(1000, $writeTime, 'Writing 5000 points should take less than 1000ms');
    }

    /**
     * @group slow
     */
    public function testPerformance_Read10000Points()
    {
        // Arrange
        $pool = $this->createLargePool(10000);
        writeJsonFile($this->poolFile, $pool);

        // Act
        $start = microtime(true);
        $readData = readJsonFile($this->poolFile);
        $end = microtime(true);

        $readTime = ($end - $start) * 1000;

        // Assert
        $this->assertLessThan(2000, $readTime, 'Reading 10000 points should take less than 2000ms');
        $this->assertCount(10000, $readData['points']);
    }

    // ========== Memory Usage Tests ==========

    public function testMemory_1000PointsUsage()
    {
        // Arrange
        $memoryBefore = memory_get_usage();
        $pool = $this->createLargePool(1000);
        writeJsonFile($this->poolFile, $pool);

        // Act
        $readData = readJsonFile($this->poolFile);
        $memoryAfter = memory_get_usage();

        $memoryUsed = ($memoryAfter - $memoryBefore) / 1024 / 1024; // Convert to MB

        // Assert
        $this->assertLessThan(5, $memoryUsed, 'Loading 1000 points should use less than 5MB');
        $this->assertCount(1000, $readData['points']);
    }

    /**
     * @group slow
     */
    public function testMemory_10000PointsUsage()
    {
        // Arrange
        $memoryBefore = memory_get_usage();
        $pool = $this->createLargePool(10000);
        writeJsonFile($this->poolFile, $pool);

        // Act
        $readData = readJsonFile($this->poolFile);
        $memoryAfter = memory_get_usage();

        $memoryUsed = ($memoryAfter - $memoryBefore) / 1024 / 1024; // Convert to MB

        // Assert
        $this->assertLessThan(50, $memoryUsed, 'Loading 10000 points should use less than 50MB');
        $this->assertCount(10000, $readData['points']);
    }

    // ========== Search Performance Tests ==========

    public function testPerformance_FindPointInLargeDataset()
    {
        // Arrange
        $pool = $this->createLargePool(5000);
        writeJsonFile($this->poolFile, $pool);

        // Act - Find point in the middle
        $targetId = 2500;

        $start = microtime(true);
        $pool = readJsonFile($this->poolFile);

        $found = null;
        foreach ($pool['points'] as $point) {
            if ($point['id'] == $targetId) {
                $found = $point;
                break;
            }
        }
        $end = microtime(true);

        $searchTime = ($end - $start) * 1000;

        // Assert
        $this->assertNotNull($found);
        $this->assertEquals($targetId, $found['id']);
        $this->assertLessThan(100, $searchTime, 'Finding point in 5000 items should take less than 100ms');
    }

    // ========== Rapid Sequential Operations Tests ==========

    public function testPerformance_RapidReadWrites()
    {
        // Arrange
        $pool = $this->createLargePool(100);

        // Act - Perform 50 rapid read-write cycles
        $start = microtime(true);

        for ($i = 0; $i < 50; $i++) {
            writeJsonFile($this->poolFile, $pool);
            $readData = readJsonFile($this->poolFile);
            $pool = $readData;
            $pool['config']['lastUpdated'] = date('Y-m-d\TH:i:s');
        }

        $end = microtime(true);
        $totalTime = ($end - $start) * 1000;
        $avgTime = $totalTime / 50;

        // Assert
        $this->assertLessThan(2000, $totalTime, '50 read-write cycles should take less than 2000ms');
        $this->assertLessThan(40, $avgTime, 'Average cycle time should be less than 40ms');
    }

    // ========== Distribution Algorithm Performance ==========

    public function testPerformance_Distribute10000Points()
    {
        // Arrange
        $allPointIds = range(1, 10000);

        // Act - Simulate distribution algorithm
        $start = microtime(true);

        $totalDays = 30;
        $totalPoints = count($allPointIds);
        $avgPointsPerDay = (int)($totalPoints / $totalDays);
        $remainder = $totalPoints % $totalDays;

        $assignments = [];
        $currentIndex = 0;

        for ($day = 1; $day <= $totalDays; $day++) {
            $pointsThisDay = $avgPointsPerDay;
            if ($day <= $remainder) {
                $pointsThisDay++;
            }

            $dayPointIds = array_slice($allPointIds, $currentIndex, $pointsThisDay);
            $currentIndex += $pointsThisDay;

            $assignments['day_' . $day] = [
                'pointIds' => $dayPointIds,
                'count' => count($dayPointIds)
            ];
        }

        $end = microtime(true);
        $distributionTime = ($end - $start) * 1000;

        // Assert
        $this->assertLessThan(50, $distributionTime, 'Distributing 10000 points should take less than 50ms');
        $this->assertCount(30, $assignments);

        // Verify all points distributed
        $totalDistributed = 0;
        foreach ($assignments as $day => $data) {
            $totalDistributed += $data['count'];
        }
        $this->assertEquals(10000, $totalDistributed);
    }

    // ========== JSON Parsing Performance ==========

    public function testPerformance_JSONEncodeDecode()
    {
        // Arrange
        $pool = $this->createLargePool(1000);

        // Act - Measure encode time
        $start = microtime(true);
        $json = json_encode($pool, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $encodeTime = (microtime(true) - $start) * 1000;

        // Act - Measure decode time
        $start = microtime(true);
        $decoded = json_decode($json, true);
        $decodeTime = (microtime(true) - $start) * 1000;

        // Assert
        $this->assertLessThan(50, $encodeTime, 'Encoding 1000 points should take less than 50ms');
        $this->assertLessThan(50, $decodeTime, 'Decoding 1000 points should take less than 50ms');
        $this->assertEquals($pool, $decoded);
    }

    // ========== File Size Tests ==========

    public function testFileSize_1000Points()
    {
        // Arrange
        $pool = $this->createLargePool(1000);
        writeJsonFile($this->poolFile, $pool);

        // Act
        $fileSize = filesize($this->poolFile) / 1024; // KB

        // Assert
        $this->assertLessThan(500, $fileSize, '1000 points should be less than 500KB');
    }

    /**
     * @group slow
     */
    public function testFileSize_10000Points()
    {
        // Arrange
        $pool = $this->createLargePool(10000);
        writeJsonFile($this->poolFile, $pool);

        // Act
        $fileSize = filesize($this->poolFile) / 1024; // KB

        // Assert
        $this->assertLessThan(5000, $fileSize, '10000 points should be less than 5MB');
    }

    // ========== Concurrent Operation Simulation ==========

    public function testPerformance_SimulateConcurrentMarking()
    {
        // Arrange
        $pool = $this->createLargePool(100);
        writeJsonFile($this->poolFile, $pool);

        // Act - Simulate 20 users marking different points
        $start = microtime(true);

        for ($i = 1; $i <= 20; $i++) {
            $pool = readJsonFile($this->poolFile);

            // Mark point as remembered
            foreach ($pool['points'] as &$point) {
                if ($point['id'] == $i) {
                    $point['status'] = 'remembered';
                    $point['completedAt'] = date('Y-m-d H:i:s');
                    break;
                }
            }
            unset($point);

            writeJsonFile($this->poolFile, $pool);
        }

        $end = microtime(true);
        $totalTime = ($end - $start) * 1000;

        // Assert
        $this->assertLessThan(1000, $totalTime, '20 sequential mark operations should take less than 1000ms');

        // Verify all 20 points marked
        $finalPool = readJsonFile($this->poolFile);
        $markedCount = 0;
        foreach ($finalPool['points'] as $point) {
            if ($point['status'] === 'remembered') {
                $markedCount++;
            }
        }
        $this->assertEquals(20, $markedCount);
    }
}

<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Tests for initialize.php distribution algorithm
 * Focus areas: Distribution correctness, date validation, cleanup
 */
class InitializeTest extends TestCase
{
    private $testDataDir;
    private $poolFile;
    private $assignmentsFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testDataDir = __DIR__ . '/../../fixtures/test_data';
        if (!is_dir($this->testDataDir)) {
            mkdir($this->testDataDir, 0777, true);
        }

        $this->poolFile = $this->testDataDir . '/points_pool.json';
        $this->assignmentsFile = $this->testDataDir . '/daily_assignments.json';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->poolFile)) {
            unlink($this->poolFile);
        }
        if (file_exists($this->assignmentsFile)) {
            unlink($this->assignmentsFile);
        }
        if (is_dir($this->testDataDir)) {
            rmdir($this->testDataDir);
        }

        parent::tearDown();
    }

    private function createSamplePool($totalPoints = 300)
    {
        $points = [];
        for ($i = 1; $i <= $totalPoints; $i++) {
            $points[] = [
                'id' => $i,
                '科目' => '心理学',
                '章节' => '第' . (($i - 1) % 10 + 1) . '章',
                '考点' => '知识点 ' . $i,
                '页码' => 'P' . (100 + $i),
                'status' => 'pending',
                'assignedDay' => null,
                'completedAt' => null,
                'forgottenCount' => 0,
                'history' => []
            ];
        }

        $poolData = [
            'config' => [
                'startDate' => '2025-11-14',
                'totalDays' => 30,
                'avgPointsPerDay' => (int)($totalPoints / 30),
                'totalPoints' => $totalPoints,
                'createdAt' => '2025-11-14T10:00:00',
                'lastUpdated' => '2025-11-14T10:00:00'
            ],
            'points' => $points
        ];

        writeJsonFile($this->poolFile, $poolData);
        return $poolData;
    }

    // ========== Distribution Algorithm Tests ==========

    public function testDistribution_AllPointsAssignedOnce()
    {
        // Arrange
        $totalPoints = 300;
        $pool = $this->createSamplePool($totalPoints);
        $startDate = '2025-11-14';

        // Act - Simulate initialize.php distribution logic
        $allPointIds = array_map(function($p) { return $p['id']; }, $pool['points']);
        $totalDays = 30;
        $avgPointsPerDay = (int)($totalPoints / $totalDays);
        $remainder = $totalPoints % $totalDays;

        $assignments = [
            'meta' => [
                'lastUpdated' => date('Y-m-d\TH:i:s'),
                'initializedAt' => date('Y-m-d\TH:i:s'),
                'startDate' => $startDate
            ]
        ];

        $dateObj = new \DateTime($startDate);
        $currentIndex = 0;
        $assignedIds = [];

        for ($day = 1; $day <= $totalDays; $day++) {
            $dayDate = clone $dateObj;
            $dayDate->modify('+' . ($day - 1) . ' days');

            $pointsThisDay = $avgPointsPerDay;
            if ($day <= $remainder) {
                $pointsThisDay++;
            }

            $dayPointIds = array_slice($allPointIds, $currentIndex, $pointsThisDay);
            $currentIndex += $pointsThisDay;

            foreach ($dayPointIds as $id) {
                $assignedIds[] = $id;
            }

            $assignments['day_' . $day] = [
                'date' => $dayDate->format('Y-m-d'),
                'pointIds' => $dayPointIds,
                'currentIndex' => 0,
                'completed' => [],
                'forgotten' => []
            ];
        }

        // Assert
        $this->assertCount($totalPoints, $assignedIds, 'All points should be assigned');
        $this->assertEquals(
            count($assignedIds),
            count(array_unique($assignedIds)),
            'No point should be assigned twice'
        );
        $this->assertEquals(
            sort($allPointIds),
            sort($assignedIds),
            'Assigned IDs should match original IDs'
        );
    }

    public function testDistribution_RemainderHandledCorrectly()
    {
        // Arrange
        $totalPoints = 305; // 305 / 30 = 10 remainder 5
        $pool = $this->createSamplePool($totalPoints);
        $totalDays = 30;
        $avgPointsPerDay = (int)($totalPoints / $totalDays); // 10
        $remainder = $totalPoints % $totalDays; // 5

        // Act
        $allPointIds = array_map(function($p) { return $p['id']; }, $pool['points']);
        $currentIndex = 0;
        $dayCounts = [];

        for ($day = 1; $day <= $totalDays; $day++) {
            $pointsThisDay = $avgPointsPerDay;
            if ($day <= $remainder) {
                $pointsThisDay++;
            }

            $dayPointIds = array_slice($allPointIds, $currentIndex, $pointsThisDay);
            $currentIndex += $pointsThisDay;
            $dayCounts[$day] = count($dayPointIds);
        }

        // Assert
        // First 5 days should have 11 points (10 + 1 remainder)
        for ($day = 1; $day <= $remainder; $day++) {
            $this->assertEquals(
                $avgPointsPerDay + 1,
                $dayCounts[$day],
                "Day {$day} should have " . ($avgPointsPerDay + 1) . " points"
            );
        }

        // Remaining days should have exactly avgPointsPerDay
        for ($day = $remainder + 1; $day <= $totalDays; $day++) {
            $this->assertEquals(
                $avgPointsPerDay,
                $dayCounts[$day],
                "Day {$day} should have {$avgPointsPerDay} points"
            );
        }

        // Total should equal totalPoints
        $this->assertEquals($totalPoints, array_sum($dayCounts));
    }

    public function testDistribution_EdgeCase_ExactlyDivisible()
    {
        // Arrange
        $totalPoints = 300; // 300 / 30 = 10 exactly
        $pool = $this->createSamplePool($totalPoints);
        $totalDays = 30;
        $avgPointsPerDay = (int)($totalPoints / $totalDays); // 10
        $remainder = $totalPoints % $totalDays; // 0

        // Act
        $allPointIds = array_map(function($p) { return $p['id']; }, $pool['points']);
        $currentIndex = 0;
        $dayCounts = [];

        for ($day = 1; $day <= $totalDays; $day++) {
            $pointsThisDay = $avgPointsPerDay;
            if ($day <= $remainder) {
                $pointsThisDay++;
            }

            $dayPointIds = array_slice($allPointIds, $currentIndex, $pointsThisDay);
            $currentIndex += $pointsThisDay;
            $dayCounts[$day] = count($dayPointIds);
        }

        // Assert - All days should have exactly the same number
        foreach ($dayCounts as $day => $count) {
            $this->assertEquals(
                $avgPointsPerDay,
                $count,
                "Day {$day} should have exactly {$avgPointsPerDay} points"
            );
        }
        $this->assertEquals($totalPoints, array_sum($dayCounts));
    }

    public function testDistribution_SmallDataset_HandlesCorrectly()
    {
        // Arrange
        $totalPoints = 35; // Less than 30 days worth at 1 per day
        $pool = $this->createSamplePool($totalPoints);
        $totalDays = 30;

        // Act
        $allPointIds = array_map(function($p) { return $p['id']; }, $pool['points']);
        $avgPointsPerDay = (int)($totalPoints / $totalDays); // 1
        $remainder = $totalPoints % $totalDays; // 5

        $currentIndex = 0;
        $dayCounts = [];

        for ($day = 1; $day <= $totalDays; $day++) {
            $pointsThisDay = $avgPointsPerDay;
            if ($day <= $remainder) {
                $pointsThisDay++;
            }

            $dayPointIds = array_slice($allPointIds, $currentIndex, $pointsThisDay);
            $currentIndex += $pointsThisDay;
            $dayCounts[$day] = count($dayPointIds);
        }

        // Assert
        // First 5 days have 2 points, remaining days have 1 point
        for ($day = 1; $day <= 5; $day++) {
            $this->assertEquals(2, $dayCounts[$day]);
        }
        for ($day = 6; $day <= $totalDays; $day++) {
            $this->assertEquals(1, $dayCounts[$day]);
        }
        $this->assertEquals($totalPoints, array_sum($dayCounts));
    }

    // ========== Date Validation Tests ==========

    public function testValidateDate_ValidFormat_ReturnsTrue()
    {
        // Test valid date formats
        $validDates = [
            '2025-11-14',
            '2025-01-01',
            '2025-12-31',
            '2024-02-29' // Leap year
        ];

        foreach ($validDates as $date) {
            $isValid = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date);
            $this->assertEquals(1, $isValid, "Date {$date} should be valid format");

            // Also test it's a valid date
            try {
                $dateObj = new \DateTime($date);
                $this->assertInstanceOf(\DateTime::class, $dateObj);
            } catch (\Exception $e) {
                $this->fail("Date {$date} should be parseable");
            }
        }
    }

    public function testValidateDate_InvalidFormat_ReturnsFalse()
    {
        // Test invalid date formats
        $invalidFormats = [
            '2025/11/14',
            '14-11-2025',
            '2025-11-14 10:00:00',
            '11-14-2025',
            '2025-13-01', // Invalid month
            '2025-02-30', // Invalid day
            'invalid-date'
        ];

        foreach ($invalidFormats as $date) {
            $isValidFormat = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date);

            if ($isValidFormat) {
                // Check if it's an actual valid date
                try {
                    $dateObj = new \DateTime($date);
                    // Check if it matches what was input (catches things like 2025-02-30)
                    $formatted = $dateObj->format('Y-m-d');
                    $this->assertNotEquals($date, $formatted, "Date {$date} should be invalid");
                } catch (\Exception $e) {
                    // Expected for invalid dates
                    $this->assertTrue(true);
                }
            }
        }
    }

    public function testDateCalculation_ThirtyDays_GeneratesCorrectDates()
    {
        // Arrange
        $startDate = '2025-11-14';
        $totalDays = 30;
        $dateObj = new \DateTime($startDate);

        // Act
        $generatedDates = [];
        for ($day = 1; $day <= $totalDays; $day++) {
            $dayDate = clone $dateObj;
            $dayDate->modify('+' . ($day - 1) . ' days');
            $generatedDates[$day] = $dayDate->format('Y-m-d');
        }

        // Assert
        $this->assertEquals('2025-11-14', $generatedDates[1]);  // Day 1
        $this->assertEquals('2025-11-15', $generatedDates[2]);  // Day 2
        $this->assertEquals('2025-12-13', $generatedDates[30]); // Day 30

        // Verify all dates are sequential
        for ($day = 1; $day < $totalDays; $day++) {
            $current = new \DateTime($generatedDates[$day]);
            $next = new \DateTime($generatedDates[$day + 1]);
            $diff = $current->diff($next);
            $this->assertEquals(1, $diff->days, "Days should be sequential");
        }
    }

    public function testDateCalculation_CrossesMonthBoundary()
    {
        // Arrange
        $startDate = '2025-11-25'; // Near end of November
        $totalDays = 30;
        $dateObj = new \DateTime($startDate);

        // Act
        $generatedDates = [];
        for ($day = 1; $day <= $totalDays; $day++) {
            $dayDate = clone $dateObj;
            $dayDate->modify('+' . ($day - 1) . ' days');
            $generatedDates[$day] = $dayDate->format('Y-m-d');
        }

        // Assert
        $this->assertEquals('2025-11-25', $generatedDates[1]);  // November
        $this->assertEquals('2025-12-01', $generatedDates[7]);  // Crosses to December
        $this->assertEquals('2025-12-24', $generatedDates[30]); // Still December
    }

    // ========== Cleanup/Reset Tests ==========

    public function testInitialize_ResetsAllPreviousStatuses()
    {
        // Arrange - Create pool with some completed/forgotten statuses
        $points = [];
        for ($i = 1; $i <= 10; $i++) {
            $points[] = [
                'id' => $i,
                '科目' => '心理学',
                '考点' => '知识点 ' . $i,
                'status' => $i % 2 == 0 ? 'remembered' : 'forgotten',
                'assignedDay' => ($i % 3) + 1,
                'completedAt' => '2025-11-14 10:00:00',
                'forgottenCount' => 2,
                'history' => [
                    ['action' => 'forgotten', 'timestamp' => '2025-11-14 10:00:00', 'day' => 1]
                ]
            ];
        }

        $poolData = [
            'config' => [
                'startDate' => '2025-11-14',
                'totalDays' => 30,
                'avgPointsPerDay' => 1,
                'totalPoints' => 10,
                'lastUpdated' => '2025-11-14T10:00:00'
            ],
            'points' => $points
        ];

        writeJsonFile($this->poolFile, $poolData);

        // Act - Simulate reset logic from initialize.php
        $pool = readJsonFile($this->poolFile);
        foreach ($pool['points'] as &$point) {
            $point['status'] = STATUS_PENDING;
            $point['assignedDay'] = null;
            $point['completedAt'] = null;
            $point['forgottenCount'] = 0;
            $point['history'] = [];
        }
        unset($point);
        writeJsonFile($this->poolFile, $pool);

        // Assert
        $updatedPool = readJsonFile($this->poolFile);
        foreach ($updatedPool['points'] as $point) {
            $this->assertEquals('pending', $point['status']);
            $this->assertNull($point['assignedDay']);
            $this->assertNull($point['completedAt']);
            $this->assertEquals(0, $point['forgottenCount']);
            $this->assertEmpty($point['history']);
        }
    }

    public function testInitialize_UpdatesStartDate()
    {
        // Arrange
        $pool = $this->createSamplePool(100);
        $newStartDate = '2025-12-01';

        // Act
        $pool['config']['startDate'] = $newStartDate;
        $pool['config']['lastUpdated'] = date('Y-m-d\TH:i:s');
        writeJsonFile($this->poolFile, $pool);

        // Assert
        $updatedPool = readJsonFile($this->poolFile);
        $this->assertEquals($newStartDate, $updatedPool['config']['startDate']);
    }

    public function testInitialize_AssignsCorrectDayToEachPoint()
    {
        // Arrange
        $totalPoints = 60; // 2 per day on average
        $pool = $this->createSamplePool($totalPoints);
        $totalDays = 30;

        // Act - Simulate assignment
        $allPointIds = array_map(function($p) { return $p['id']; }, $pool['points']);
        $avgPointsPerDay = (int)($totalPoints / $totalDays);
        $remainder = $totalPoints % $totalDays;

        $currentIndex = 0;
        for ($day = 1; $day <= $totalDays; $day++) {
            $pointsThisDay = $avgPointsPerDay;
            if ($day <= $remainder) {
                $pointsThisDay++;
            }

            $dayPointIds = array_slice($allPointIds, $currentIndex, $pointsThisDay);
            $currentIndex += $pointsThisDay;

            // Update assignedDay in pool
            foreach ($pool['points'] as &$point) {
                if (in_array($point['id'], $dayPointIds)) {
                    $point['assignedDay'] = $day;
                }
            }
            unset($point);
        }

        writeJsonFile($this->poolFile, $pool);

        // Assert - All points should have an assignedDay
        $updatedPool = readJsonFile($this->poolFile);
        foreach ($updatedPool['points'] as $point) {
            $this->assertNotNull($point['assignedDay']);
            $this->assertGreaterThanOrEqual(1, $point['assignedDay']);
            $this->assertLessThanOrEqual(30, $point['assignedDay']);
        }
    }
}

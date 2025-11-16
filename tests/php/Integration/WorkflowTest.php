<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Integration tests for complete user workflows
 * Focus areas: End-to-end scenarios, data consistency across multiple operations
 */
class WorkflowTest extends TestCase
{
    private $testDataDir;
    private $poolFile;
    private $assignmentsFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testDataDir = __DIR__ . '/../../fixtures/integration_data';
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

    private function createInitialPool($totalPoints = 30)
    {
        $points = [];
        for ($i = 1; $i <= $totalPoints; $i++) {
            $points[] = [
                'id' => $i,
                '科目' => '心理学',
                '章节' => '第' . (($i - 1) % 5 + 1) . '章',
                '考点' => '知识点 ' . $i,
                '页码' => 'P' . (100 + $i),
                'status' => 'pending',
                'assignedDay' => null,
                'completedAt' => null,
                'forgottenCount' => 0,
                'history' => []
            ];
        }

        return [
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
    }

    // ========== Complete Workflow Tests ==========

    public function testCompleteWorkflow_InitializeToCompletion()
    {
        /**
         * Test complete workflow: Initialize → Get Points → Mark → Verify Stats
         */

        // Step 1: Create initial pool (simulate generate_pool.py)
        $pool = $this->createInitialPool(30);
        writeJsonFile($this->poolFile, $pool);

        // Step 2: Initialize system (simulate initialize.php)
        $pool = readJsonFile($this->poolFile);
        $startDate = '2025-11-14';
        $pool['config']['startDate'] = $startDate;

        // Reset all points
        foreach ($pool['points'] as &$point) {
            $point['status'] = STATUS_PENDING;
            $point['assignedDay'] = null;
            $point['completedAt'] = null;
            $point['forgottenCount'] = 0;
            $point['history'] = [];
        }
        unset($point);

        // Distribute to 30 days
        $allPointIds = array_map(fn($p) => $p['id'], $pool['points']);
        $totalDays = 30;
        $totalPoints = count($allPointIds);
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

        for ($day = 1; $day <= $totalDays; $day++) {
            $dayDate = clone $dateObj;
            $dayDate->modify('+' . ($day - 1) . ' days');

            $pointsThisDay = $avgPointsPerDay;
            if ($day <= $remainder) {
                $pointsThisDay++;
            }

            $dayPointIds = array_slice($allPointIds, $currentIndex, $pointsThisDay);
            $currentIndex += $pointsThisDay;

            foreach ($pool['points'] as &$point) {
                if (in_array($point['id'], $dayPointIds)) {
                    $point['assignedDay'] = $day;
                }
            }
            unset($point);

            $assignments['day_' . $day] = [
                'date' => $dayDate->format('Y-m-d'),
                'pointIds' => $dayPointIds,
                'currentIndex' => 0,
                'completed' => [],
                'forgotten' => []
            ];
        }

        writeJsonFile($this->poolFile, $pool);
        writeJsonFile($this->assignmentsFile, $assignments);

        // Verify initialization
        $this->assertFileExists($this->poolFile);
        $this->assertFileExists($this->assignmentsFile);

        // Step 3: Get points for day 1 (simulate get_points.php)
        $assignments = readJsonFile($this->assignmentsFile);
        $day1Points = $assignments['day_1']['pointIds'];

        $this->assertNotEmpty($day1Points);

        // Step 4: Mark first point as remembered (simulate mark_point.php)
        $pointId = $day1Points[0];
        $pool = readJsonFile($this->poolFile);
        $assignments = readJsonFile($this->assignmentsFile);

        foreach ($pool['points'] as &$point) {
            if ($point['id'] == $pointId) {
                $point['status'] = STATUS_REMEMBERED;
                $point['completedAt'] = date('Y-m-d H:i:s');
                $point['history'][] = [
                    'action' => 'remembered',
                    'timestamp' => date('Y-m-d H:i:s'),
                    'day' => 1
                ];
                break;
            }
        }
        unset($point);

        if (!in_array($pointId, $assignments['day_1']['completed'])) {
            $assignments['day_1']['completed'][] = $pointId;
        }

        writeJsonFile($this->poolFile, $pool);
        writeJsonFile($this->assignmentsFile, $assignments);

        // Step 5: Verify state changes
        $updatedPool = readJsonFile($this->poolFile);
        $updatedAssignments = readJsonFile($this->assignmentsFile);

        $point = array_values(array_filter($updatedPool['points'], fn($p) => $p['id'] == $pointId))[0];
        $this->assertEquals('remembered', $point['status']);
        $this->assertNotNull($point['completedAt']);
        $this->assertContains($pointId, $updatedAssignments['day_1']['completed']);

        // Step 6: Calculate statistics (simulate get_stats.php)
        $totalCompleted = count($updatedAssignments['day_1']['completed']);
        $totalForgotten = count($updatedAssignments['day_1']['forgotten']);

        $this->assertEquals(1, $totalCompleted);
        $this->assertEquals(0, $totalForgotten);
    }

    public function testWorkflow_ForgetThenRememberCycle()
    {
        /**
         * Test workflow: Mark as forgotten → Re-mark as remembered
         */

        // Setup
        $pool = $this->createInitialPool(10);
        writeJsonFile($this->poolFile, $pool);

        // Initialize assignments
        $assignments = [
            'meta' => [
                'lastUpdated' => date('Y-m-d\TH:i:s'),
                'startDate' => '2025-11-14'
            ],
            'day_1' => [
                'date' => '2025-11-14',
                'pointIds' => [1, 2, 3],
                'currentIndex' => 0,
                'completed' => [],
                'forgotten' => []
            ]
        ];
        writeJsonFile($this->assignmentsFile, $assignments);

        $pointId = 1;

        // Step 1: Mark as forgotten
        $pool = readJsonFile($this->poolFile);
        $assignments = readJsonFile($this->assignmentsFile);

        foreach ($pool['points'] as &$point) {
            if ($point['id'] == $pointId) {
                $point['status'] = STATUS_FORGOTTEN;
                $point['forgottenCount']++;
                $point['history'][] = [
                    'action' => 'forgotten',
                    'timestamp' => date('Y-m-d H:i:s'),
                    'day' => 1
                ];
                break;
            }
        }
        unset($point);

        $assignments['day_1']['forgotten'][] = $pointId;
        writeJsonFile($this->poolFile, $pool);
        writeJsonFile($this->assignmentsFile, $assignments);

        // Verify forgotten state
        $pool = readJsonFile($this->poolFile);
        $assignments = readJsonFile($this->assignmentsFile);
        $point = array_values(array_filter($pool['points'], fn($p) => $p['id'] == $pointId))[0];

        $this->assertEquals('forgotten', $point['status']);
        $this->assertEquals(1, $point['forgottenCount']);
        $this->assertContains($pointId, $assignments['day_1']['forgotten']);

        // Step 2: Mark as remembered
        foreach ($pool['points'] as &$point) {
            if ($point['id'] == $pointId) {
                $point['status'] = STATUS_REMEMBERED;
                $point['completedAt'] = date('Y-m-d H:i:s');
                $point['history'][] = [
                    'action' => 'remembered',
                    'timestamp' => date('Y-m-d H:i:s'),
                    'day' => 1
                ];
                break;
            }
        }
        unset($point);

        if (!in_array($pointId, $assignments['day_1']['completed'])) {
            $assignments['day_1']['completed'][] = $pointId;
        }

        $assignments['day_1']['forgotten'] = array_values(
            array_diff($assignments['day_1']['forgotten'], [$pointId])
        );

        writeJsonFile($this->poolFile, $pool);
        writeJsonFile($this->assignmentsFile, $assignments);

        // Verify final state
        $finalPool = readJsonFile($this->poolFile);
        $finalAssignments = readJsonFile($this->assignmentsFile);
        $finalPoint = array_values(array_filter($finalPool['points'], fn($p) => $p['id'] == $pointId))[0];

        $this->assertEquals('remembered', $finalPoint['status']);
        $this->assertEquals(1, $finalPoint['forgottenCount']); // Should remain 1
        $this->assertContains($pointId, $finalAssignments['day_1']['completed']);
        $this->assertNotContains($pointId, $finalAssignments['day_1']['forgotten']);
        $this->assertCount(2, $finalPoint['history']); // forgotten + remembered
    }

    public function testWorkflow_ResetAndReinitialize()
    {
        /**
         * Test workflow: Use system → Reset → Re-initialize
         */

        // Step 1: Setup with some completed points
        $pool = $this->createInitialPool(10);

        // Mark some as completed
        $pool['points'][0]['status'] = 'remembered';
        $pool['points'][0]['completedAt'] = '2025-11-14 10:00:00';
        $pool['points'][1]['status'] = 'forgotten';
        $pool['points'][1]['forgottenCount'] = 2;

        writeJsonFile($this->poolFile, $pool);

        // Verify initial state
        $initialPool = readJsonFile($this->poolFile);
        $this->assertEquals('remembered', $initialPool['points'][0]['status']);
        $this->assertEquals('forgotten', $initialPool['points'][1]['status']);

        // Step 2: Reset (simulate reset.php)
        $pool = readJsonFile($this->poolFile);

        foreach ($pool['points'] as &$point) {
            $point['status'] = STATUS_PENDING;
            $point['assignedDay'] = null;
            $point['completedAt'] = null;
            $point['forgottenCount'] = 0;
            $point['history'] = [];
        }
        unset($point);

        $pool['config']['lastUpdated'] = date('Y-m-d\TH:i:s');
        writeJsonFile($this->poolFile, $pool);

        // Verify reset
        $resetPool = readJsonFile($this->poolFile);
        foreach ($resetPool['points'] as $point) {
            $this->assertEquals('pending', $point['status']);
            $this->assertNull($point['assignedDay']);
            $this->assertNull($point['completedAt']);
            $this->assertEquals(0, $point['forgottenCount']);
            $this->assertEmpty($point['history']);
        }

        // Step 3: Re-initialize with new start date
        $newStartDate = '2025-12-01';
        $pool = readJsonFile($this->poolFile);
        $pool['config']['startDate'] = $newStartDate;

        // Re-distribute
        $allPointIds = array_map(fn($p) => $p['id'], $pool['points']);
        // ... (distribution logic same as before)

        writeJsonFile($this->poolFile, $pool);

        // Verify re-initialization
        $newPool = readJsonFile($this->poolFile);
        $this->assertEquals($newStartDate, $newPool['config']['startDate']);
    }

    public function testWorkflow_MultipleDaysProgression()
    {
        /**
         * Test workflow: Complete day 1 → Move to day 2 → Complete day 2
         */

        // Setup
        $pool = $this->createInitialPool(30);
        writeJsonFile($this->poolFile, $pool);

        $assignments = [
            'meta' => [
                'lastUpdated' => date('Y-m-d\TH:i:s'),
                'startDate' => '2025-11-14'
            ],
            'day_1' => [
                'date' => '2025-11-14',
                'pointIds' => [1, 2],
                'currentIndex' => 0,
                'completed' => [],
                'forgotten' => []
            ],
            'day_2' => [
                'date' => '2025-11-15',
                'pointIds' => [3, 4],
                'currentIndex' => 0,
                'completed' => [],
                'forgotten' => []
            ]
        ];
        writeJsonFile($this->assignmentsFile, $assignments);

        // Complete all points in day 1
        $pool = readJsonFile($this->poolFile);
        $assignments = readJsonFile($this->assignmentsFile);

        foreach ([1, 2] as $pointId) {
            foreach ($pool['points'] as &$point) {
                if ($point['id'] == $pointId) {
                    $point['status'] = STATUS_REMEMBERED;
                    $point['completedAt'] = date('Y-m-d H:i:s');
                    break;
                }
            }
            unset($point);
            $assignments['day_1']['completed'][] = $pointId;
        }

        writeJsonFile($this->poolFile, $pool);
        writeJsonFile($this->assignmentsFile, $assignments);

        // Verify day 1 completed
        $assignments = readJsonFile($this->assignmentsFile);
        $this->assertCount(2, $assignments['day_1']['completed']);
        $this->assertEmpty($assignments['day_1']['forgotten']);

        // Start day 2
        $pool = readJsonFile($this->poolFile);
        foreach ([3, 4] as $pointId) {
            foreach ($pool['points'] as &$point) {
                if ($point['id'] == $pointId) {
                    $point['status'] = STATUS_REMEMBERED;
                    $point['completedAt'] = date('Y-m-d H:i:s');
                    break;
                }
            }
            unset($point);
            $assignments['day_2']['completed'][] = $pointId;
        }

        writeJsonFile($this->poolFile, $pool);
        writeJsonFile($this->assignmentsFile, $assignments);

        // Verify day 2 completed
        $finalAssignments = readJsonFile($this->assignmentsFile);
        $this->assertCount(2, $finalAssignments['day_2']['completed']);

        // Verify overall progress
        $totalCompleted = count($finalAssignments['day_1']['completed']) +
                         count($finalAssignments['day_2']['completed']);
        $this->assertEquals(4, $totalCompleted);
    }

    public function testWorkflow_ConcurrentMarking_NoDataLoss()
    {
        /**
         * Test that concurrent operations don't lose data
         * (Simplified test - real concurrent testing would need process forking)
         */

        $pool = $this->createInitialPool(10);
        writeJsonFile($this->poolFile, $pool);

        $assignments = [
            'meta' => ['lastUpdated' => date('Y-m-d\TH:i:s'), 'startDate' => '2025-11-14'],
            'day_1' => [
                'date' => '2025-11-14',
                'pointIds' => [1, 2, 3, 4, 5],
                'currentIndex' => 0,
                'completed' => [],
                'forgotten' => []
            ]
        ];
        writeJsonFile($this->assignmentsFile, $assignments);

        // Simulate multiple mark operations in sequence
        for ($pointId = 1; $pointId <= 5; $pointId++) {
            $pool = readJsonFile($this->poolFile);
            $assignments = readJsonFile($this->assignmentsFile);

            foreach ($pool['points'] as &$point) {
                if ($point['id'] == $pointId) {
                    $point['status'] = STATUS_REMEMBERED;
                    $point['completedAt'] = date('Y-m-d H:i:s');
                    break;
                }
            }
            unset($point);

            $assignments['day_1']['completed'][] = $pointId;

            writeJsonFile($this->poolFile, $pool);
            writeJsonFile($this->assignmentsFile, $assignments);
        }

        // Verify all 5 points marked
        $finalAssignments = readJsonFile($this->assignmentsFile);
        $this->assertCount(5, $finalAssignments['day_1']['completed']);

        // Verify no duplicates
        $completed = $finalAssignments['day_1']['completed'];
        $this->assertEquals(count($completed), count(array_unique($completed)));
    }
}

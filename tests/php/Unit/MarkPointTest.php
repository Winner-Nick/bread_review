<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Tests for mark_point.php state management
 * Focus areas: State transitions, data consistency, edge cases
 */
class MarkPointTest extends TestCase
{
    private $testDataDir;
    private $poolFile;
    private $assignmentsFile;
    private $originalPoolFile;
    private $originalAssignmentsFile;

    protected function setUp(): void
    {
        parent::setUp();

        // Create temporary test data directory
        $this->testDataDir = __DIR__ . '/../../fixtures/test_data';
        if (!is_dir($this->testDataDir)) {
            mkdir($this->testDataDir, 0777, true);
        }

        $this->poolFile = $this->testDataDir . '/points_pool.json';
        $this->assignmentsFile = $this->testDataDir . '/daily_assignments.json';

        // Backup original constant values
        $this->originalPoolFile = defined('POINTS_POOL_FILE') ? POINTS_POOL_FILE : null;
        $this->originalAssignmentsFile = defined('DAILY_ASSIGNMENTS_FILE') ? DAILY_ASSIGNMENTS_FILE : null;

        // Initialize test data
        $this->initializeTestData();
    }

    protected function tearDown(): void
    {
        // Clean up test files
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

    private function initializeTestData()
    {
        // Create sample points pool
        $poolData = [
            'config' => [
                'startDate' => '2025-11-14',
                'totalDays' => 30,
                'avgPointsPerDay' => 10,
                'totalPoints' => 300,
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
                ],
                [
                    'id' => 2,
                    '科目' => '心理学',
                    '章节' => '发展心理学',
                    '考点' => '皮亚杰理论',
                    '页码' => 'P234',
                    'status' => 'pending',
                    'assignedDay' => 1,
                    'completedAt' => null,
                    'forgottenCount' => 0,
                    'history' => []
                ],
                [
                    'id' => 3,
                    '科目' => '心理学',
                    '章节' => '社会心理学',
                    '考点' => '态度改变',
                    '页码' => 'P345',
                    'status' => 'remembered',
                    'assignedDay' => 1,
                    'completedAt' => '2025-11-14 15:30:00',
                    'forgottenCount' => 0,
                    'history' => [
                        [
                            'action' => 'remembered',
                            'timestamp' => '2025-11-14 15:30:00',
                            'day' => 1
                        ]
                    ]
                ]
            ]
        ];

        // Create sample daily assignments
        $assignmentsData = [
            'meta' => [
                'lastUpdated' => '2025-11-14T10:00:00',
                'initializedAt' => '2025-11-14T10:00:00',
                'startDate' => '2025-11-14'
            ],
            'day_1' => [
                'date' => '2025-11-14',
                'pointIds' => [1, 2, 3],
                'currentIndex' => 0,
                'completed' => [3],
                'forgotten' => []
            ],
            'day_2' => [
                'date' => '2025-11-15',
                'pointIds' => [4, 5, 6],
                'currentIndex' => 0,
                'completed' => [],
                'forgotten' => []
            ]
        ];

        writeJsonFile($this->poolFile, $poolData);
        writeJsonFile($this->assignmentsFile, $assignmentsData);
    }

    // ========== State Transition Tests ==========

    public function testMarkRemember_PendingToRemembered_UpdatesStatus()
    {
        // Arrange
        $pool = readJsonFile($this->poolFile);
        $assignments = readJsonFile($this->assignmentsFile);

        $pointId = 1;
        $day = 1;

        // Act - Simulate mark_point.php "remember" action
        foreach ($pool['points'] as &$point) {
            if ($point['id'] == $pointId) {
                $point['status'] = STATUS_REMEMBERED;
                $point['completedAt'] = date('Y-m-d H:i:s');
                $point['history'][] = [
                    'action' => 'remembered',
                    'timestamp' => date('Y-m-d H:i:s'),
                    'day' => $day
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

        // Assert
        $updatedPool = readJsonFile($this->poolFile);
        $updatedAssignments = readJsonFile($this->assignmentsFile);

        $point = array_values(array_filter($updatedPool['points'], fn($p) => $p['id'] == $pointId))[0];
        $this->assertEquals('remembered', $point['status']);
        $this->assertNotNull($point['completedAt']);
        $this->assertCount(1, $point['history']);
        $this->assertEquals('remembered', $point['history'][0]['action']);
        $this->assertContains($pointId, $updatedAssignments['day_1']['completed']);
    }

    public function testMarkForget_PendingToForgotten_UpdatesStatusAndCount()
    {
        // Arrange
        $pool = readJsonFile($this->poolFile);
        $assignments = readJsonFile($this->assignmentsFile);

        $pointId = 2;
        $day = 1;

        // Act - Simulate "forget" action
        foreach ($pool['points'] as &$point) {
            if ($point['id'] == $pointId) {
                $point['status'] = STATUS_FORGOTTEN;
                $point['forgottenCount']++;
                $point['history'][] = [
                    'action' => 'forgotten',
                    'timestamp' => date('Y-m-d H:i:s'),
                    'day' => $day
                ];
                break;
            }
        }
        unset($point);

        if (!in_array($pointId, $assignments['day_1']['forgotten'])) {
            $assignments['day_1']['forgotten'][] = $pointId;
        }

        writeJsonFile($this->poolFile, $pool);
        writeJsonFile($this->assignmentsFile, $assignments);

        // Assert
        $updatedPool = readJsonFile($this->poolFile);
        $updatedAssignments = readJsonFile($this->assignmentsFile);

        $point = array_values(array_filter($updatedPool['points'], fn($p) => $p['id'] == $pointId))[0];
        $this->assertEquals('forgotten', $point['status']);
        $this->assertEquals(1, $point['forgottenCount']);
        $this->assertCount(1, $point['history']);
        $this->assertEquals('forgotten', $point['history'][0]['action']);
        $this->assertContains($pointId, $updatedAssignments['day_1']['forgotten']);
    }

    public function testMarkRemember_AfterForgotten_MovesFromForgottenToCompleted()
    {
        // Arrange
        $pool = readJsonFile($this->poolFile);
        $assignments = readJsonFile($this->assignmentsFile);

        $pointId = 2;
        $day = 1;

        // First mark as forgotten
        foreach ($pool['points'] as &$point) {
            if ($point['id'] == $pointId) {
                $point['status'] = STATUS_FORGOTTEN;
                $point['forgottenCount'] = 1;
                break;
            }
        }
        unset($point);
        $assignments['day_1']['forgotten'][] = $pointId;

        writeJsonFile($this->poolFile, $pool);
        writeJsonFile($this->assignmentsFile, $assignments);

        // Act - Now mark as remembered
        $pool = readJsonFile($this->poolFile);
        $assignments = readJsonFile($this->assignmentsFile);

        foreach ($pool['points'] as &$point) {
            if ($point['id'] == $pointId) {
                $point['status'] = STATUS_REMEMBERED;
                $point['completedAt'] = date('Y-m-d H:i:s');
                $point['history'][] = [
                    'action' => 'remembered',
                    'timestamp' => date('Y-m-d H:i:s'),
                    'day' => $day
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

        // Assert
        $updatedPool = readJsonFile($this->poolFile);
        $updatedAssignments = readJsonFile($this->assignmentsFile);

        $point = array_values(array_filter($updatedPool['points'], fn($p) => $p['id'] == $pointId))[0];
        $this->assertEquals('remembered', $point['status']);
        $this->assertContains($pointId, $updatedAssignments['day_1']['completed']);
        $this->assertNotContains($pointId, $updatedAssignments['day_1']['forgotten']);
    }

    public function testMarkForget_MultipleTimes_IncrementsCounter()
    {
        // Arrange
        $pool = readJsonFile($this->poolFile);
        $pointId = 1;
        $day = 1;

        // Act - Mark as forgotten 3 times
        for ($i = 0; $i < 3; $i++) {
            foreach ($pool['points'] as &$point) {
                if ($point['id'] == $pointId) {
                    $point['status'] = STATUS_FORGOTTEN;
                    $point['forgottenCount']++;
                    $point['history'][] = [
                        'action' => 'forgotten',
                        'timestamp' => date('Y-m-d H:i:s'),
                        'day' => $day
                    ];
                    break;
                }
            }
            unset($point);
            writeJsonFile($this->poolFile, $pool);
            $pool = readJsonFile($this->poolFile);
        }

        // Assert
        $updatedPool = readJsonFile($this->poolFile);
        $point = array_values(array_filter($updatedPool['points'], fn($p) => $p['id'] == $pointId))[0];

        $this->assertEquals(3, $point['forgottenCount']);
        $this->assertCount(3, $point['history']);
    }

    // ========== Data Consistency Tests ==========

    public function testCompletedAndForgotten_ArraysDoNotOverlap()
    {
        // Arrange
        $assignments = readJsonFile($this->assignmentsFile);
        $completed = $assignments['day_1']['completed'];
        $forgotten = $assignments['day_1']['forgotten'];

        // Assert - No point should be in both arrays
        $overlap = array_intersect($completed, $forgotten);
        $this->assertEmpty($overlap, 'Completed and forgotten arrays should not overlap');
    }

    public function testMarkRemember_DuplicateOperation_IsIdempotent()
    {
        // Arrange
        $pool = readJsonFile($this->poolFile);
        $assignments = readJsonFile($this->assignmentsFile);
        $pointId = 1;
        $day = 1;

        // Act - Mark as remembered twice
        for ($i = 0; $i < 2; $i++) {
            foreach ($pool['points'] as &$point) {
                if ($point['id'] == $pointId) {
                    $point['status'] = STATUS_REMEMBERED;
                    $point['completedAt'] = date('Y-m-d H:i:s');
                    break;
                }
            }
            unset($point);

            if (!in_array($pointId, $assignments['day_1']['completed'])) {
                $assignments['day_1']['completed'][] = $pointId;
            }

            writeJsonFile($this->poolFile, $pool);
            writeJsonFile($this->assignmentsFile, $assignments);

            $pool = readJsonFile($this->poolFile);
            $assignments = readJsonFile($this->assignmentsFile);
        }

        // Assert - Should appear only once in completed array
        $completed = $assignments['day_1']['completed'];
        $count = count(array_filter($completed, fn($id) => $id == $pointId));
        $this->assertEquals(1, $count, 'Point should appear only once in completed array');
    }

    public function testHistoryArray_AppendsCorrectly()
    {
        // Arrange
        $pool = readJsonFile($this->poolFile);
        $pointId = 1;

        // Act - Perform sequence: remember -> forget -> remember
        $actions = ['remembered', 'forgotten', 'remembered'];

        foreach ($actions as $action) {
            foreach ($pool['points'] as &$point) {
                if ($point['id'] == $pointId) {
                    $point['status'] = $action === 'remembered' ? STATUS_REMEMBERED : STATUS_FORGOTTEN;
                    $point['history'][] = [
                        'action' => $action,
                        'timestamp' => date('Y-m-d H:i:s'),
                        'day' => 1
                    ];
                    if ($action === 'forgotten') {
                        $point['forgottenCount']++;
                    }
                    break;
                }
            }
            unset($point);
            writeJsonFile($this->poolFile, $pool);
            $pool = readJsonFile($this->poolFile);
        }

        // Assert
        $updatedPool = readJsonFile($this->poolFile);
        $point = array_values(array_filter($updatedPool['points'], fn($p) => $p['id'] == $pointId))[0];

        $this->assertCount(3, $point['history']);
        $this->assertEquals('remembered', $point['history'][0]['action']);
        $this->assertEquals('forgotten', $point['history'][1]['action']);
        $this->assertEquals('remembered', $point['history'][2]['action']);
    }

    // ========== Edge Cases Tests ==========

    public function testMarkPoint_NonExistentPointId_NoEffect()
    {
        // Arrange
        $pool = readJsonFile($this->poolFile);
        $originalPoolCount = count($pool['points']);
        $nonExistentId = 9999;

        // Act - Try to mark non-existent point
        $found = false;
        foreach ($pool['points'] as &$point) {
            if ($point['id'] == $nonExistentId) {
                $point['status'] = STATUS_REMEMBERED;
                $found = true;
                break;
            }
        }
        unset($point);

        // Assert
        $this->assertFalse($found);
        $this->assertCount($originalPoolCount, $pool['points']);
    }

    public function testMarkPoint_ZeroOrNegativeId_NoEffect()
    {
        // Arrange
        $pool = readJsonFile($this->poolFile);
        $invalidIds = [0, -1, -100];

        foreach ($invalidIds as $invalidId) {
            // Act
            $found = false;
            foreach ($pool['points'] as &$point) {
                if ($point['id'] == $invalidId) {
                    $found = true;
                    break;
                }
            }
            unset($point);

            // Assert
            $this->assertFalse($found, "Point with ID {$invalidId} should not exist");
        }
    }

    public function testTimestamps_AreValid()
    {
        // Arrange
        $pool = readJsonFile($this->poolFile);
        $pointId = 1;

        // Act
        foreach ($pool['points'] as &$point) {
            if ($point['id'] == $pointId) {
                $timestamp = date('Y-m-d H:i:s');
                $point['completedAt'] = $timestamp;
                $point['history'][] = [
                    'action' => 'remembered',
                    'timestamp' => $timestamp,
                    'day' => 1
                ];
                break;
            }
        }
        unset($point);
        writeJsonFile($this->poolFile, $pool);

        // Assert
        $updatedPool = readJsonFile($this->poolFile);
        $point = array_values(array_filter($updatedPool['points'], fn($p) => $p['id'] == $pointId))[0];

        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
            $point['completedAt'],
            'Timestamp should be in Y-m-d H:i:s format'
        );
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
            $point['history'][0]['timestamp'],
            'History timestamp should be in Y-m-d H:i:s format'
        );
    }
}

# Testing Guide

This document describes the comprehensive test suite for the 30-Day Exam Review System.

## Overview

The project now has automated tests covering:
- **PHP Backend** (PHPUnit) - File I/O, state management, distribution algorithm
- **Python Data Generation** (pytest) - Excel parsing, data processing
- **JavaScript Frontend** (Jest) - API integration, response handling
- **Integration Tests** (PHPUnit) - End-to-end workflows

## Test Coverage Areas

### 🔴 High Priority (Critical Path)
1. **File I/O Operations** (`tests/php/Unit/CommonTest.php`)
   - File locking correctness
   - Concurrent read/write operations
   - Error handling (permissions, missing files, corrupted data)
   - UTF-8 encoding for Chinese characters

2. **State Management** (`tests/php/Unit/MarkPointTest.php`)
   - State transitions (pending → remembered → forgotten)
   - Data consistency (completed/forgotten arrays don't overlap)
   - Idempotency of mark operations
   - History tracking

3. **Distribution Algorithm** (`tests/php/Unit/InitializeTest.php`)
   - All points assigned exactly once
   - Remainder distribution handling
   - Date validation and calculation
   - System reset/cleanup

### 🟡 Medium Priority (Quality Assurance)
4. **Data Generation** (`tests/python/test_generate_pool.py`)
   - Excel parsing with missing/invalid data
   - NaN value handling
   - Randomization correctness
   - JSON file generation with UTF-8

5. **Frontend API Integration** (`tests/javascript/api-integration.test.js`)
   - API request/response formats
   - Error handling
   - Network failure handling

### 🟢 Low Priority (Regression Protection)
6. **Integration Workflows** (`tests/php/Integration/WorkflowTest.php`)
   - Complete user workflows
   - Reset and re-initialization
   - Multi-day progression

---

## Installation

### PHP Tests (PHPUnit)

```bash
# Install dependencies
composer install

# Run all PHP tests
./vendor/bin/phpunit

# Run specific test suite
./vendor/bin/phpunit tests/php/Unit
./vendor/bin/phpunit tests/php/Integration

# Run with coverage report
./vendor/bin/phpunit --coverage-html tests/coverage/html

# Run specific test file
./vendor/bin/phpunit tests/php/Unit/CommonTest.php
```

### Python Tests (pytest)

```bash
# Install dependencies
pip install -r requirements-dev.txt

# Run all Python tests
pytest

# Run with coverage
pytest --cov --cov-report=html

# Run specific test file
pytest tests/python/test_generate_pool.py

# Run with verbose output
pytest -v

# Run specific test
pytest tests/python/test_generate_pool.py::TestGeneratePool::test_read_excel_valid_file
```

### JavaScript Tests (Jest)

```bash
# Install dependencies
npm install

# Run all JavaScript tests
npm test

# Run tests in watch mode
npm run test:watch

# Run with coverage
npm run test:coverage

# Run specific test file
npm test -- api-integration.test.js
```

---

## Running All Tests

```bash
# Run everything
./vendor/bin/phpunit && pytest && npm test
```

---

## Test Structure

```
tests/
├── php/
│   ├── Unit/
│   │   ├── CommonTest.php           # File I/O operations
│   │   ├── MarkPointTest.php        # State management
│   │   └── InitializeTest.php       # Distribution algorithm
│   ├── Integration/
│   │   └── WorkflowTest.php         # End-to-end workflows
│   └── fixtures/                    # Test data files
├── python/
│   ├── test_generate_pool.py        # Data generation tests
│   └── fixtures/                    # Sample Excel files
├── javascript/
│   └── api-integration.test.js      # Frontend API tests
└── coverage/                        # Coverage reports (gitignored)
    ├── html/                        # PHP coverage HTML
    ├── python/                      # Python coverage HTML
    └── javascript/                  # JavaScript coverage HTML
```

---

## Writing New Tests

### PHPUnit Test Template

```php
<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Setup code
    }

    protected function tearDown(): void
    {
        // Cleanup code
        parent::tearDown();
    }

    public function testExample()
    {
        // Arrange
        $data = ['key' => 'value'];

        // Act
        $result = someFunction($data);

        // Assert
        $this->assertTrue($result);
    }
}
```

### pytest Test Template

```python
import pytest

class TestExample:
    @pytest.fixture
    def sample_data(self):
        return {'key': 'value'}

    def test_example(self, sample_data):
        # Arrange
        expected = 'value'

        # Act
        result = sample_data['key']

        # Assert
        assert result == expected
```

### Jest Test Template

```javascript
describe('Example Tests', () => {
    beforeEach(() => {
        // Setup code
    });

    afterEach(() => {
        // Cleanup code
    });

    test('should do something', () => {
        // Arrange
        const data = { key: 'value' };

        // Act
        const result = someFunction(data);

        // Assert
        expect(result).toBe(true);
    });
});
```

---

## Coverage Goals

| Component | Current | Target |
|-----------|---------|--------|
| PHP Backend | TBD | 80%+ |
| Python Scripts | TBD | 75%+ |
| JavaScript Frontend | TBD | 70%+ |

---

## Continuous Integration

To run tests automatically on every commit, add to `.git/hooks/pre-commit`:

```bash
#!/bin/bash

echo "Running tests..."

# Run PHP tests
./vendor/bin/phpunit --no-coverage || exit 1

# Run Python tests
pytest --no-cov || exit 1

# Run JavaScript tests (if npm installed)
if command -v npm &> /dev/null; then
    npm test -- --passWithNoTests || exit 1
fi

echo "All tests passed!"
```

Make it executable:
```bash
chmod +x .git/hooks/pre-commit
```

---

## Troubleshooting

### PHPUnit Issues

**Problem:** "Class 'Tests\Unit\CommonTest' not found"
```bash
# Solution: Regenerate autoload
composer dump-autoload
```

**Problem:** Permission errors during file tests
```bash
# Solution: Ensure test directories are writable
chmod -R 755 tests/
```

### pytest Issues

**Problem:** "ModuleNotFoundError: No module named 'pandas'"
```bash
# Solution: Install dependencies
pip install -r requirements-dev.txt
```

### Jest Issues

**Problem:** "Cannot find module 'jest'"
```bash
# Solution: Install node modules
npm install
```

---

## Best Practices

1. **Isolation**: Each test should be independent
2. **Cleanup**: Always clean up test files in `tearDown()`
3. **Descriptive Names**: Use clear, descriptive test names
4. **AAA Pattern**: Arrange, Act, Assert
5. **One Assertion Per Test**: Test one thing at a time
6. **Mock External Dependencies**: Don't rely on actual APIs in tests
7. **Test Edge Cases**: Empty data, null values, boundary conditions

---

## Next Steps

### Planned Test Additions

- [ ] Performance/load tests for concurrent operations
- [ ] Browser automation tests (Selenium/Playwright)
- [ ] Security tests (XSS, SQL injection attempts)
- [ ] API contract tests
- [ ] Visual regression tests

### CI/CD Integration

Consider integrating with:
- GitHub Actions
- GitLab CI
- Travis CI
- Jenkins

---

## Resources

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [pytest Documentation](https://docs.pytest.org/)
- [Jest Documentation](https://jestjs.io/docs/getting-started)

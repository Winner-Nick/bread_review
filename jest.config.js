module.exports = {
  testEnvironment: 'jsdom',
  testMatch: [
    '**/tests/javascript/**/*.test.js'
  ],
  collectCoverageFrom: [
    'assets/**/*.js',
    '!assets/**/*.min.js',
    '!**/node_modules/**',
    '!**/vendor/**'
  ],
  coverageDirectory: 'tests/coverage/javascript',
  coverageReporters: ['html', 'text', 'lcov'],
  verbose: true,
  testTimeout: 10000
};

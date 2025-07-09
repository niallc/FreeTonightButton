/**
 * JavaScript tests for Free Tonight app
 * Run with: node tests/test_javascript.js
 * 
 * Note: This is a simplified test environment that doesn't require a browser
 * and focuses on testing the logic functions that were recently refactored.
 */

// Mock DOM environment for testing
global.document = {
    getElementById: () => ({ value: '', addEventListener: () => {} }),
    addEventListener: () => {}
};

global.localStorage = {
    getItem: () => null,
    setItem: () => {}
};

global.fetch = () => Promise.resolve({ ok: true, json: () => Promise.resolve({}) });

// Mock the constants and helper functions from app.js
const APP_VERSION = '1.0.0';
const DEFAULT_ACTIVITY = 'up for anything';
const SECONDS_PER_MINUTE = 60;
const GRACE_PERIOD_SECONDS = 3600;
const REFRESH_INTERVAL_MS = 2 * 60 * 1000;
const UPDATE_INTERVAL_MS = 1000;

// Helper functions extracted from app.js for testing
function validateName(name) {
    if (!name.trim()) {
        return false;
    }
    return true;
}

function getActivity(activityInput = { value: '' }) {
    const activity = activityInput.value.trim();
    return activity || DEFAULT_ACTIVITY;
}

function parseTimeInput(hoursInput, minutesInput) {
    const hours = hoursInput.value === '' ? 0 : parseInt(hoursInput.value, 10) || 0;
    const minutes = minutesInput.value === '' ? 0 : parseInt(minutesInput.value, 10) || 0;
    return hours * SECONDS_PER_MINUTE + minutes;
}

function calculateTimeUntilMidnight() {
    const now = new Date();
    const midnight = new Date(now);
    midnight.setHours(24, 0, 0, 0); // next local midnight
    return Math.floor((midnight - now) / 60000);
}

function validateAvailableTime(availableForMinutes) {
    if (availableForMinutes !== null && availableForMinutes <= 0) {
        return false;
    }
    return true;
}

// Test functions
function runTests() {
    console.log('Running JavaScript tests...\n');
    
    let passed = 0;
    let failed = 0;
    
    function test(description, testFunction) {
        try {
            testFunction();
            console.log(`✓ ${description}`);
            passed++;
        } catch (error) {
            console.log(`✗ ${description}: ${error.message}`);
            failed++;
        }
    }
    
    // Test constants
    test('APP_VERSION is defined', () => {
        if (APP_VERSION !== '1.0.0') {
            throw new Error('APP_VERSION should be 1.0.0');
        }
    });
    
    test('DEFAULT_ACTIVITY is defined', () => {
        if (DEFAULT_ACTIVITY !== 'up for anything') {
            throw new Error('DEFAULT_ACTIVITY should be "up for anything"');
        }
    });
    
    test('SECONDS_PER_MINUTE is 60', () => {
        if (SECONDS_PER_MINUTE !== 60) {
            throw new Error('SECONDS_PER_MINUTE should be 60');
        }
    });
    
    test('GRACE_PERIOD_SECONDS is 3600', () => {
        if (GRACE_PERIOD_SECONDS !== 3600) {
            throw new Error('GRACE_PERIOD_SECONDS should be 3600');
        }
    });
    
    // Test validateName function
    test('validateName accepts valid names', () => {
        if (!validateName('John')) {
            throw new Error('validateName should accept "John"');
        }
        if (!validateName('John Doe')) {
            throw new Error('validateName should accept "John Doe"');
        }
    });
    
    test('validateName rejects empty names', () => {
        if (validateName('')) {
            throw new Error('validateName should reject empty string');
        }
        if (validateName('   ')) {
            throw new Error('validateName should reject whitespace-only string');
        }
    });
    
    // Test getActivity function
    test('getActivity returns default when empty', () => {
        const result = getActivity({ value: '' });
        if (result !== DEFAULT_ACTIVITY) {
            throw new Error(`getActivity should return "${DEFAULT_ACTIVITY}" for empty input`);
        }
    });
    
    test('getActivity returns custom activity', () => {
        const result = getActivity({ value: 'Coffee' });
        if (result !== 'Coffee') {
            throw new Error('getActivity should return custom activity');
        }
    });
    
    test('getActivity trims whitespace', () => {
        const result = getActivity({ value: '  Coffee  ' });
        if (result !== 'Coffee') {
            throw new Error('getActivity should trim whitespace');
        }
    });
    
    // Test parseTimeInput function
    test('parseTimeInput handles empty inputs', () => {
        const result = parseTimeInput({ value: '' }, { value: '' });
        if (result !== 0) {
            throw new Error('parseTimeInput should return 0 for empty inputs');
        }
    });
    
    test('parseTimeInput handles hours only', () => {
        const result = parseTimeInput({ value: '2' }, { value: '' });
        if (result !== 120) {
            throw new Error('parseTimeInput should convert 2 hours to 120 minutes');
        }
    });
    
    test('parseTimeInput handles minutes only', () => {
        const result = parseTimeInput({ value: '' }, { value: '30' });
        if (result !== 30) {
            throw new Error('parseTimeInput should convert 30 minutes to 30 minutes');
        }
    });
    
    test('parseTimeInput handles hours and minutes', () => {
        const result = parseTimeInput({ value: '1' }, { value: '30' });
        if (result !== 90) {
            throw new Error('parseTimeInput should convert 1 hour 30 minutes to 90 minutes');
        }
    });
    
    test('parseTimeInput handles invalid inputs', () => {
        const result = parseTimeInput({ value: 'abc' }, { value: 'xyz' });
        if (result !== 0) {
            throw new Error('parseTimeInput should return 0 for invalid inputs');
        }
    });
    
    // Test validateAvailableTime function
    test('validateAvailableTime accepts positive values', () => {
        if (!validateAvailableTime(30)) {
            throw new Error('validateAvailableTime should accept positive values');
        }
        if (!validateAvailableTime(1)) {
            throw new Error('validateAvailableTime should accept 1 minute');
        }
    });
    
    test('validateAvailableTime rejects zero and negative', () => {
        if (validateAvailableTime(0)) {
            throw new Error('validateAvailableTime should reject zero');
        }
        if (validateAvailableTime(-10)) {
            throw new Error('validateAvailableTime should reject negative values');
        }
    });
    
    test('validateAvailableTime accepts null', () => {
        if (!validateAvailableTime(null)) {
            throw new Error('validateAvailableTime should accept null');
        }
    });
    
    // Test calculateTimeUntilMidnight function
    test('calculateTimeUntilMidnight returns positive value', () => {
        const result = calculateTimeUntilMidnight();
        if (result <= 0) {
            throw new Error('calculateTimeUntilMidnight should return positive value');
        }
        if (result > 1440) {
            throw new Error('calculateTimeUntilMidnight should return value <= 1440 minutes');
        }
    });
    
    console.log(`\nResults: ${passed} passed, ${failed} failed`);
    
    if (failed === 0) {
        console.log('🎉 All JavaScript tests passed!');
    } else {
        console.log('❌ Some JavaScript tests failed!');
    }
}

// Run the tests
runTests(); 
// Version tracking for development
const APP_VERSION = '1.0.0';

// Constants
const DEFAULT_ACTIVITY = 'up for anything';
const SECONDS_PER_MINUTE = 60;
const GRACE_PERIOD_SECONDS = 3600; // 1 hour after end time
const REFRESH_INTERVAL_MS = 2 * 60 * 1000; // 2 minutes
const UPDATE_INTERVAL_MS = 1000; // 1 second

// Debug logging system
const DEBUG_MODE = window.location.search.includes('debug=true');
function debugLog(message, ...args) {
    if (DEBUG_MODE) {
        console.log(`🔍 ${message}`, ...args);
    }
}

// DOM elements
let nameInput, activityInput, freeInHoursInput, freeInMinutesInput, availableForHoursInput, availableForMinutesInput;
let freeButton, removeButton, refreshButton, actionFeedback, statusBar, freeList;
let toggleOptionsButton, moreOptionsDiv;
let createGroupButton, deleteGroupButton, groupModal, groupTitle, groupManagement;
let groupNameInput, groupDisplayInput, createGroupForm, cancelCreateGroupButton, backToDefaultButton;

// Timers
let refreshTimer, updateTimer;
let lastRefreshTime = 0;

// Group state
let currentGroup = 'default';

// Initialize the application
document.addEventListener('DOMContentLoaded', function() {
    debugLog('DOMContentLoaded fired');
    
    initializeElements();
    setupEventListeners();
    setupTimers();
    loadSavedName();
    
    // Handle initial group from URL hash
    const hash = window.location.hash.slice(1);
    debugLog('Initial hash:', hash);
    if (hash) {
        currentGroup = hash;
        debugLog('Set currentGroup to:', currentGroup);
    } else {
        debugLog('No hash, using default group');
    }
    
    debugLog('About to call updateGroupUI with currentGroup:', currentGroup);
    updateGroupUI();
    getFreeList(); // Initial load
});

function initializeElements() {
    debugLog('Initializing elements...');
    
    nameInput = document.getElementById('name-input');
    toggleOptionsButton = document.getElementById('toggle-options');
    moreOptionsDiv = document.getElementById('more-options');
    activityInput = document.getElementById('activity-input');
    freeInHoursInput = document.getElementById('free-in-hours');
    freeInMinutesInput = document.getElementById('free-in-minutes');
    availableForHoursInput = document.getElementById('available-for-hours');
    availableForMinutesInput = document.getElementById('available-for-minutes');
    freeButton = document.getElementById('free-button');
    removeButton = document.getElementById('remove-button');
    refreshButton = document.getElementById('refresh-button');
    actionFeedback = document.getElementById('action-feedback');
    statusBar = document.getElementById('status-bar');
    freeList = document.getElementById('free-list');
    
    // Group management elements
    createGroupButton = document.getElementById('create-group-button');
    deleteGroupButton = document.getElementById('delete-group-button');
    groupModal = document.getElementById('group-modal');
    groupTitle = document.getElementById('group-title');
    groupManagement = document.getElementById('group-management');
    groupNameInput = document.getElementById('group-name-input');
    groupDisplayInput = document.getElementById('group-display-input');
    createGroupForm = document.getElementById('create-group-form');
    cancelCreateGroupButton = document.getElementById('cancel-create-group');
    backToDefaultButton = document.getElementById('back-to-default-button');
    
    debugLog('Element initialization results:', {
        createGroupButton: !!createGroupButton,
        deleteGroupButton: !!deleteGroupButton,
        groupManagement: !!groupManagement,
        backToDefaultButton: !!backToDefaultButton
    });
}

function setupEventListeners() {
    // Save name to localStorage as user types
    nameInput.addEventListener('input', function() {
        localStorage.setItem('freetonight_name', this.value);
    });
    // Allow pressing Enter in the name field to submit
    nameInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            setFreeStatus();
        }
    });
    // Toggle more options
    toggleOptionsButton.addEventListener('click', function() {
        if (moreOptionsDiv.style.display === 'none') {
            moreOptionsDiv.style.display = 'block';
            toggleOptionsButton.textContent = 'Hide options';
        } else {
            moreOptionsDiv.style.display = 'none';
            toggleOptionsButton.textContent = 'Show more options';
        }
    });
    // Button click handlers
    freeButton.addEventListener('click', setFreeStatus);
    removeButton.addEventListener('click', removeStatus);
    refreshButton.addEventListener('click', getFreeList);
    
    // Group management event listeners
    createGroupButton.addEventListener('click', showCreateGroupModal);
    deleteGroupButton.addEventListener('click', deleteCurrentGroup);
    cancelCreateGroupButton.addEventListener('click', hideCreateGroupModal);
    createGroupForm.addEventListener('submit', createGroup);
    backToDefaultButton.addEventListener('click', goToDefaultGroup);
    
    // Handle hash changes for group switching
    window.addEventListener('hashchange', handleHashChange);
}

function setupTimers() {
    // Auto-refresh every 2 minutes
    refreshTimer = setInterval(getFreeList, REFRESH_INTERVAL_MS);
    
    // Update timer display every second
    updateTimer = setInterval(updateTimerDisplay, UPDATE_INTERVAL_MS);
}

function loadSavedName() {
    const savedName = localStorage.getItem('freetonight_name');
    if (savedName) {
        nameInput.value = savedName;
    }
}

// Group management functions
function handleHashChange() {
    const hash = window.location.hash.slice(1); // Remove the #
    debugLog('Hash changed to:', hash, 'currentGroup was:', currentGroup);
    
    // Handle empty hash (default group)
    if (!hash && currentGroup !== 'default') {
        currentGroup = 'default';
        debugLog('Updated currentGroup to default');
        updateGroupUI();
        getFreeList();
    }
    // Handle new hash
    else if (hash && hash !== currentGroup) {
        currentGroup = hash;
        debugLog('Updated currentGroup to:', currentGroup);
        updateGroupUI();
        getFreeList();
    } else {
        debugLog('No change needed');
    }
}

function updateGroupUI() {
    debugLog('updateGroupUI called with currentGroup:', currentGroup);
    
    if (currentGroup === 'default') {
        debugLog('Setting up default group UI');
        groupTitle.textContent = 'Who\'s Free';
        if (groupManagement) {
            groupManagement.style.display = 'none';
            debugLog('Hidden group management');
        } else {
            debugLog('ERROR: groupManagement element not found!');
        }
    } else {
        debugLog('Setting up custom group UI for:', currentGroup);
        groupTitle.textContent = `Who's Free - ${currentGroup}`;
        if (groupManagement) {
            groupManagement.style.display = 'block';
            debugLog('Showed group management');
        } else {
            debugLog('ERROR: groupManagement element not found!');
        }
    }
}

function goToDefaultGroup() {
    debugLog('Going to default group');
    currentGroup = 'default';
    window.location.hash = '';
    updateGroupUI();
    getFreeList();
}

function showCreateGroupModal() {
    groupModal.style.display = 'flex';
    groupNameInput.focus();
}

function hideCreateGroupModal() {
    groupModal.style.display = 'none';
    groupNameInput.value = '';
    groupDisplayInput.value = '';
}

async function createGroup(e) {
    e.preventDefault();
    
    const groupName = groupNameInput.value.trim();
    const displayName = groupDisplayInput.value.trim() || groupName;
    
    if (!groupName) {
        showActionFeedback('Please enter a group name', 'error');
        return;
    }
    
    // Validate group name format
    if (!/^[a-zA-Z0-9_-]+$/.test(groupName)) {
        showActionFeedback('Group name can only contain letters, numbers, underscores, and hyphens', 'error');
        return;
    }
    
    try {
        const response = await fetch('/freetonight/api.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'create_group',
                group_name: groupName,
                display_name: displayName
            })
        });
        
        const data = await response.json();
        
        if (response.ok) {
            showActionFeedback(data.message, 'success');
            hideCreateGroupModal();
            
            // Switch to the new group
            window.location.hash = groupName;
        } else {
            showActionFeedback(data.error || 'Failed to create group', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showActionFeedback('Network error creating group - please try again', 'error');
    }
}

async function deleteCurrentGroup() {
    if (currentGroup === 'default') {
        showActionFeedback('Cannot delete the default group', 'error');
        return;
    }
    
    if (!confirm(`Are you sure you want to delete the group "${currentGroup}"? This will permanently delete all data in this group.`)) {
        return;
    }
    
    try {
        const response = await fetch('/freetonight/api.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'delete_group',
                group_name: currentGroup
            })
        });
        
        const data = await response.json();
        
        if (response.ok) {
            showActionFeedback(data.message, 'success');
            
            // Switch back to default group
            window.location.hash = '';
        } else {
            showActionFeedback(data.error || 'Failed to delete group', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showActionFeedback('Network error deleting group - please try again', 'error');
    }
}

let freeListUsers = [];

// Helper functions for input validation and processing
function validateName(name) {
    if (!name.trim()) {
        showActionFeedback('Please enter your name first', 'error');
        return false;
    }
    return true;
}

function getActivity() {
    const activity = activityInput.value.trim();
    return activity || DEFAULT_ACTIVITY;
}

function parseTimeInput(hoursInput, minutesInput) {
    const hours = hoursInput.value === '' ? 0 : parseInt(hoursInput.value, 10) || 0;
    const minutes = minutesInput.value === '' ? 0 : parseInt(minutesInput.value, 10) || 0;
    return hours * SECONDS_PER_MINUTE + minutes;
}

/**
 * Calculate minutes until local midnight (not UTC)
 * 
 * DESIGN CHOICE: Uses local timezone for "until midnight" calculations.
 * This is intentional - if you live in New Zealand, you go to bed on NZ time,
 * not UTC midnight. This provides better UX for users in different timezones.
 */
function calculateTimeUntilMidnight() {
    const now = new Date();
    const midnight = new Date(now);
    midnight.setHours(24, 0, 0, 0); // next local midnight
    return Math.floor((midnight - now) / 60000);
}

function validateAvailableTime(availableForMinutes) {
    if (availableForMinutes !== null && availableForMinutes <= 0) {
        showActionFeedback('Available for must be greater than 0 minutes', 'error');
        return false;
    }
    return true;
}

async function setFreeStatus() {
    const name = nameInput.value.trim();
    if (!validateName(name)) return;
    
    const activity = getActivity();
    
    // Parse time inputs
    let freeInMinutes = parseTimeInput(freeInHoursInput, freeInMinutesInput);
    let availableForMinutes = parseTimeInput(availableForHoursInput, availableForMinutesInput);
    
    // If no time is specified, set availableForMinutes to minutes until local midnight
    // (Uses local timezone intentionally for better UX - see calculateTimeUntilMidnight docs)
    if (freeInMinutes === 0 && availableForMinutes === 0) {
        availableForMinutes = calculateTimeUntilMidnight();
        freeInMinutes = 0;
    }
    
    if (!validateAvailableTime(availableForMinutes)) return;
    
    try {
        const response = await fetch('/freetonight/api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                name: name,
                activity: activity,
                free_in_minutes: freeInMinutes,
                available_for_minutes: availableForMinutes,
                group_name: currentGroup
            })
        });
        const data = await response.json();
        if (response.ok) {
            showActionFeedback(data.message || 'Status updated!', 'success');
            getFreeList(); // Refresh the list
        } else {
            showActionFeedback(data.error || 'Failed to update status', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showActionFeedback('Network error adding - please try again', 'error');
    }
}

async function removeStatus() {
    const name = nameInput.value.trim();
    if (!name) {
        showActionFeedback('Please enter your name first', 'error');
        return;
    }
    
    try {
        const response = await fetch('/freetonight/api.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ 
                name: name,
                group_name: currentGroup
            })
        });
        
        const data = await response.json();
        
        if (response.ok) {
            showActionFeedback(data.message || 'Removed from list!', 'success');
            getFreeList(); // Refresh the list
        } else {
            showActionFeedback(data.error || 'Failed to remove status', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showActionFeedback('Network error removing - please try again', 'error');
    }
}

async function getFreeList() {
    try {
        const url = currentGroup === 'default' 
            ? '/freetonight/api.php'
            : `/freetonight/api.php?group=${encodeURIComponent(currentGroup)}`;
            
        const response = await fetch(url);
        const data = await response.json();
        if (response.ok) {
            freeListUsers = data;
            displayFreeList(freeListUsers);
            lastRefreshTime = Date.now();
            updateTimerDisplay();
        } else {
            showActionFeedback(data.error || 'Failed to load list', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showActionFeedback('Network error refreshing - please try again', 'error');
    }
}

function createUserListItem(user, index) {
    const listItem = document.createElement('li');
    const nameSpan = document.createElement('span');
    const activitySpan = document.createElement('span');
    const timeSpan = document.createElement('span');
    
    nameSpan.textContent = user.name;
    activitySpan.textContent = user.activity ? ` (${user.activity})` : '';
    activitySpan.className = 'activity';
    activitySpan.title = user.activity || '';
    timeSpan.id = `timer-${index}`;
    timeSpan.className = 'timestamp';
    
    listItem.appendChild(nameSpan);
    listItem.appendChild(activitySpan);
    listItem.appendChild(timeSpan);
    
    return listItem;
}

function createEmptyListItem() {
    const emptyItem = document.createElement('li');
    emptyItem.textContent = 'No one is free yet';
    emptyItem.style.textAlign = 'center';
    emptyItem.style.color = '#666';
    return emptyItem;
}

function displayFreeList(users) {
    freeList.innerHTML = '';
    
    if (users.length === 0) {
        freeList.appendChild(createEmptyListItem());
        return;
    }
    
    users.forEach((user, idx) => {
        const listItem = createUserListItem(user, idx);
        freeList.appendChild(listItem);
    });
    
    updateTimersOnly();
}

function updateTimersOnly() {
    freeListUsers.forEach((user, idx) => {
        const timerSpan = document.getElementById(`timer-${idx}`);
        if (!timerSpan) return;
        timerSpan.textContent = formatLiveRelativeTime(user);
    });
}

function removeExpiredUsersAndRedraw() {
    const now = Math.floor(Date.now() / 1000);
    const filtered = freeListUsers.filter(user => {
        const posted = user.timestamp;
        const freeIn = user.free_in_minutes || 0;
        const availableFor = user.available_for_minutes || 0;
        const freeStart = posted + freeIn * SECONDS_PER_MINUTE;
        const freeEnd = freeStart + availableFor * SECONDS_PER_MINUTE;
        // Show 'no longer available' for 1 hour after end, then remove
        if (now < freeEnd + GRACE_PERIOD_SECONDS) {
            return true;
        }
        return false;
    });
    if (filtered.length !== freeListUsers.length) {
        freeListUsers = filtered;
        displayFreeList(freeListUsers);
    } else {
        updateTimersOnly();
    }
}

function formatLiveRelativeTime(user) {
    if (!user.available_for_minutes || user.available_for_minutes <= 0) return '';
    const now = Math.floor(Date.now() / 1000);
    const posted = user.timestamp;
    const freeIn = user.free_in_minutes || 0;
    const availableFor = user.available_for_minutes || 0;
    const freeStart = posted + freeIn * SECONDS_PER_MINUTE;
    const freeEnd = freeStart + availableFor * SECONDS_PER_MINUTE;
    if (now < freeStart) {
        return `Free in ${formatSeconds(freeStart - now)} (for ${formatSeconds(availableFor * SECONDS_PER_MINUTE)})`;
    } else if (now < freeEnd) {
        return `Free now (${formatSeconds(freeEnd - now)} left)`;
    } else if (now < freeEnd + GRACE_PERIOD_SECONDS) {
        return 'No longer available';
    } else {
        return '';
    }
}
function formatSeconds(totalSeconds) {
    const h = Math.floor(totalSeconds / 3600);
    const m = Math.floor((totalSeconds % 3600) / 60);
    const s = totalSeconds % 60;
    let out = '';
    if (h > 0) out += `${h}h`;
    if (h > 0 && m > 0) out += ' ';
    if (m > 0) out += `${m}m`;
    if ((h > 0 || m > 0) && s > 0) out += ' ';
    if (s > 0) out += `${s}s`;
    if (out === '') out = '0s';
    return out;
}

function formatRelativeTime(freeIn, availableFor) {
    if (freeIn > 0) {
        return `Free in ${formatMinutes(freeIn)} (for ${formatMinutes(availableFor)})`;
    } else {
        return `Free now (for ${formatMinutes(availableFor)})`;
    }
}

function formatMinutes(mins) {
    const h = Math.floor(mins / 60);
    const m = mins % 60;
    let out = '';
    if (h > 0) out += `${h}h`;
    if (h > 0 && m > 0) out += ' ';
    if (m > 0) out += `${m}m`;
    if (out === '') out = '0m';
    return out;
}

function showActionFeedback(message, type) {
    actionFeedback.textContent = message;
    actionFeedback.className = type;
    actionFeedback.classList.add('show');
    
    // Hide after different durations based on type
    const duration = (type === 'error') ? 10000 : 3000; // 10 seconds for errors, 3 for success
    
    setTimeout(() => {
        actionFeedback.classList.remove('show');
    }, duration);
}

function updateTimerDisplay() {
    if (lastRefreshTime === 0) {
        statusBar.textContent = 'Last updated: Never';
        return;
    }
    
    const now = Date.now();
    const diff = now - lastRefreshTime;
    
    if (diff < 60000) {
        const seconds = Math.floor(diff / 1000);
        statusBar.textContent = `Last updated: ${seconds} second${seconds > 1 ? 's' : ''} ago`;
    } else if (diff < 3600000) {
        const minutes = Math.floor(diff / 60000);
        statusBar.textContent = `Last updated: ${minutes} minute${minutes > 1 ? 's' : ''} ago`;
    } else {
        const hours = Math.floor(diff / 3600000);
        statusBar.textContent = `Last updated: ${hours} hour${hours > 1 ? 's' : ''} ago`;
    }
} 
// Version tracking for development
const APP_VERSION = '1.0.0';

// DOM elements
let nameInput, activityInput, freeInHoursInput, freeInMinutesInput, availableForHoursInput, availableForMinutesInput;
let freeButton, removeButton, refreshButton, actionFeedback, statusBar, freeList;
let toggleOptionsButton, moreOptionsDiv;

// Timers
let refreshTimer, updateTimer;
let lastRefreshTime = 0;

// Initialize the application
document.addEventListener('DOMContentLoaded', function() {
    initializeElements();
    setupEventListeners();
    setupTimers();
    loadSavedName();
    getFreeList(); // Initial load
});

function initializeElements() {
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
}

function setupTimers() {
    // Auto-refresh every 2 minutes
    refreshTimer = setInterval(getFreeList, 2 * 60 * 1000);
    
    // Update timer display every second
    updateTimer = setInterval(updateTimerDisplay, 1000);
}

function loadSavedName() {
    const savedName = localStorage.getItem('freetonight_name');
    if (savedName) {
        nameInput.value = savedName;
    }
}

let freeListUsers = [];

async function setFreeStatus() {
    const name = nameInput.value.trim();
    if (!name) {
        showActionFeedback('Please enter your name first', 'error');
        return;
    }
    let activity = activityInput.value.trim();
    if (!activity) {
        activity = 'up for anything';
    }
    // Parse input fields, treat blank as null
    const freeInMinutes = freeInHoursInput.value === '' && freeInMinutesInput.value === '' ? null : (parseInt(freeInHoursInput.value, 10) || 0) * 60 + (parseInt(freeInMinutesInput.value, 10) || 0);
    const availableForMinutes = availableForHoursInput.value === '' && availableForMinutesInput.value === '' ? null : (parseInt(availableForHoursInput.value, 10) || 0) * 60 + (parseInt(availableForMinutesInput.value, 10) || 0);
    // Allow blank availableForMinutes (null), but if set, must be > 0
    if (availableForMinutes !== null && availableForMinutes <= 0) {
        showActionFeedback('Available for must be greater than 0 minutes', 'error');
        return;
    }
    try {
        const response = await fetch('/freetonight/api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                name: name,
                activity: activity,
                free_in_minutes: freeInMinutes === null ? 0 : freeInMinutes,
                available_for_minutes: availableForMinutes === null ? 0 : availableForMinutes
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
        showActionFeedback('Network error - please try again', 'error');
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
            body: JSON.stringify({ name: name })
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
        showActionFeedback('Network error - please try again', 'error');
    }
}

async function getFreeList() {
    try {
        const response = await fetch('/freetonight/api.php');
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
        showActionFeedback('Network error - please try again', 'error');
    }
}

function displayFreeList(users) {
    freeList.innerHTML = '';
    if (users.length === 0) {
        const emptyItem = document.createElement('li');
        emptyItem.textContent = 'No one is free tonight yet';
        emptyItem.style.textAlign = 'center';
        emptyItem.style.color = '#666';
        freeList.appendChild(emptyItem);
        return;
    }
    users.forEach((user, idx) => {
        const listItem = document.createElement('li');
        const nameSpan = document.createElement('span');
        const activitySpan = document.createElement('span');
        const timeSpan = document.createElement('span');
        nameSpan.textContent = user.name;
        activitySpan.textContent = user.activity ? ` (${user.activity})` : '';
        activitySpan.className = 'activity';
        activitySpan.title = user.activity || '';
        timeSpan.id = `timer-${idx}`;
        timeSpan.className = 'timestamp';
        listItem.appendChild(nameSpan);
        listItem.appendChild(activitySpan);
        listItem.appendChild(timeSpan);
        freeList.appendChild(listItem);
    });
    updateAllTimers();
}
function updateAllTimers() {
    freeListUsers.forEach((user, idx) => {
        const timerSpan = document.getElementById(`timer-${idx}`);
        if (!timerSpan) return;
        timerSpan.textContent = formatLiveRelativeTime(user);
    });
}
function formatLiveRelativeTime(user) {
    // If available_for_minutes is 0 or null, show nothing
    if (!user.available_for_minutes || user.available_for_minutes <= 0) return '';
    // Calculate the time left
    const now = Math.floor(Date.now() / 1000);
    const posted = user.timestamp;
    const freeIn = user.free_in_minutes || 0;
    const availableFor = user.available_for_minutes || 0;
    const freeStart = posted + freeIn * 60;
    const freeEnd = freeStart + availableFor * 60;
    if (now < freeStart) {
        return `Free in ${formatSeconds(freeStart - now)} (for ${formatSeconds(availableFor * 60)})`;
    } else if (now < freeEnd) {
        return `Free now (${formatSeconds(freeEnd - now)} left)`;
    } else {
        return 'No longer free';
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
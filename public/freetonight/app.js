// Version tracking for development
const APP_VERSION = '1.0.0';

// DOM elements
let nameInput, freeButton, removeButton, refreshButton, actionFeedback, statusBar, freeList;

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

async function setFreeStatus() {
    const name = nameInput.value.trim();
    if (!name) {
        showActionFeedback('Please enter your name first', 'error');
        return;
    }
    
    try {
        const response = await fetch('api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ name: name })
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
        const response = await fetch('api.php', {
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
        const response = await fetch('api.php');
        const data = await response.json();
        
        if (response.ok) {
            displayFreeList(data);
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
    
    users.forEach(user => {
        const listItem = document.createElement('li');
        const nameSpan = document.createElement('span');
        const timeSpan = document.createElement('span');
        
        nameSpan.textContent = user.name;
        timeSpan.textContent = formatTimestamp(user.timestamp);
        timeSpan.className = 'timestamp';
        
        listItem.appendChild(nameSpan);
        listItem.appendChild(timeSpan);
        freeList.appendChild(listItem);
    });
}

function formatTimestamp(timestamp) {
    const now = Math.floor(Date.now() / 1000);
    const diff = now - timestamp;
    
    if (diff < 60) {
        return 'Just now';
    } else if (diff < 3600) {
        const minutes = Math.floor(diff / 60);
        return `${minutes} minute${minutes > 1 ? 's' : ''} ago`;
    } else if (diff < 86400) {
        const hours = Math.floor(diff / 3600);
        return `${hours} hour${hours > 1 ? 's' : ''} ago`;
    } else {
        return 'More than a day ago';
    }
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
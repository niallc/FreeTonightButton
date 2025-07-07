// Version tracking
const APP_VERSION = '1.0.1';
console.log('FreeTonight App v' + APP_VERSION + ' loaded');

document.addEventListener('DOMContentLoaded', function() {
    // DOM references
    const nameInput = document.getElementById('name-input');
    const freeButton = document.getElementById('free-button');
    const statusBar = document.getElementById('status-bar');
    const freeList = document.getElementById('free-list');
    const refreshButton = document.getElementById('refresh-button');
    
    // Global variables
    let lastRefreshTime = new Date();
    
    // Initialize name from localStorage
    const savedName = localStorage.getItem('userName');
    if (savedName) {
        nameInput.value = savedName;
    }
    
    // Save name to localStorage on input
    nameInput.addEventListener('input', function(event) {
        localStorage.setItem('userName', event.target.value);
    });
    
    // Set free status function
    async function setFreeStatus() {
        const userName = nameInput.value.trim();
        
        if (!userName) {
            statusBar.textContent = 'Please enter your name first.';
            return;
        }
        
        try {
            freeButton.disabled = true;
            freeButton.textContent = 'Updating...';
            
            const response = await fetch('./api.php?t=' + Date.now(), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Cache-Control': 'no-cache'
                },
                body: JSON.stringify({ name: userName })
            });
            
            const result = await response.json();
            
            if (result.success) {
                statusBar.textContent = `Successfully marked ${userName} as free tonight!`;
                await getFreeList(); // Refresh the list immediately
            } else {
                statusBar.textContent = result.error || 'Failed to update status.';
            }
            
        } catch (error) {
            statusBar.textContent = 'Network error. Please try again.';
            console.error('Error details:', error);
            console.error('Error message:', error.message);
            if (error.response) {
                console.error('Response status:', error.response.status);
                console.error('Response text:', await error.response.text());
            }
        } finally {
            freeButton.disabled = false;
            freeButton.textContent = "I'm Free Tonight!";
        }
    }
    
    // Get free list function
    async function getFreeList() {
        try {
            const response = await fetch('./api.php?t=' + Date.now(), {
                headers: {
                    'Cache-Control': 'no-cache'
                }
            });
            const users = await response.json();
            
            // Clear current list
            freeList.innerHTML = '';
            
            if (users.length === 0) {
                freeList.innerHTML = '<li style="text-align: center; color: #999; font-style: italic;">No one has marked themselves as free yet.</li>';
            } else {
                users.forEach(user => {
                    const li = document.createElement('li');
                    const timeAgo = getTimeAgo(user.timestamp);
                    li.textContent = `${user.name} (${timeAgo})`;
                    freeList.appendChild(li);
                });
            }
            
            lastRefreshTime = new Date();
            
        } catch (error) {
            console.error('Error fetching free list:', error);
            console.error('Error message:', error.message);
            if (error.response) {
                console.error('Response status:', error.response.status);
                console.error('Response text:', await error.response.text());
            }
            statusBar.textContent = 'Failed to load the list. Please refresh the page.';
        }
    }
    
    // Helper function to convert timestamp to human-readable time ago
    function getTimeAgo(timestamp) {
        const now = Math.floor(Date.now() / 1000);
        const diff = now - timestamp;
        
        if (diff < 60) {
            return 'just now';
        } else if (diff < 3600) {
            const minutes = Math.floor(diff / 60);
            return `${minutes} minute${minutes > 1 ? 's' : ''} ago`;
        } else if (diff < 86400) {
            const hours = Math.floor(diff / 3600);
            return `${hours} hour${hours > 1 ? 's' : ''} ago`;
        } else {
            const days = Math.floor(diff / 86400);
            return `${days} day${days > 1 ? 's' : ''} ago`;
        }
    }
    
    // Health check function
    async function checkHealth() {
        try {
            const response = await fetch('./api.php?t=' + Date.now(), { 
                method: 'HEAD',
                headers: {
                    'Cache-Control': 'no-cache'
                }
            });
            if (response.ok) {
                console.log('Server health check: OK');
            } else {
                console.warn('Server health check: Failed');
                statusBar.textContent = 'Warning: Server health check failed';
            }
        } catch (error) {
            console.error('Health check error:', error);
            statusBar.textContent = 'Warning: Cannot connect to server';
        }
    }
    
    // Update timer function
    function updateTimer() {
        const now = new Date();
        const secondsSinceRefresh = Math.floor((now - lastRefreshTime) / 1000);
        statusBar.textContent = `Last updated: ${secondsSinceRefresh} second${secondsSinceRefresh !== 1 ? 's' : ''} ago.`;
    }
    
    // Event listeners
    freeButton.addEventListener('click', setFreeStatus);
    refreshButton.addEventListener('click', async function() {
        refreshButton.textContent = '🔄 Refreshing...';
        refreshButton.disabled = true;
        await getFreeList();
        refreshButton.textContent = '🔄 Refresh';
        refreshButton.disabled = false;
    });
    
    // Allow Enter key to submit
    nameInput.addEventListener('keypress', function(event) {
        if (event.key === 'Enter') {
            setFreeStatus();
        }
    });
    
    // Auto-refresh every 2 minutes
    setInterval(getFreeList, 120000);
    
    // Update timer every second
    setInterval(updateTimer, 1000);
    
    // Health check on startup
    checkHealth();
    
    // Initial load
    getFreeList();
    
    // Log version for debugging
    console.log('App initialized with version:', APP_VERSION);
}); 
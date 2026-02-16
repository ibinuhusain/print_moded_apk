// Main application JavaScript for Apparel Collection System

document.addEventListener('DOMContentLoaded', function() {
    console.log('Apparel Collection System loaded');
    
    // Register service worker for PWA functionality
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js')
                .then(registration => {
                    console.log('ServiceWorker registered successfully:', registration.scope);
                    
                    // Listen for messages from service worker
                    navigator.serviceWorker.addEventListener('message', event => {
                        if (event.data && event.data.type === 'TRIGGER_SYNC') {
                            console.log('Background sync triggered');
                            // Handle background sync here
                            handleBackgroundSync();
                        }
                    });
                })
                .catch(error => {
                    console.log('ServiceWorker registration failed:', error);
                });
        });
    }
    
    // Background sync functionality
    if ('serviceWorker' in navigator && 'sync' in navigator.serviceWorker) {
        // Register sync when needed
        registerBackgroundSync();
    }
    
    // Check for network status changes
    window.addEventListener('online', handleConnectionChange);
    window.addEventListener('offline', handleConnectionChange);
    
    // Initialize app components
    initializeAppComponents();
});

function initializeAppComponents() {
    // Initialize any UI components, form handlers, etc.
    setupFormSubmissions();
    setupOfflineIndicators();
}

function setupFormSubmissions() {
    // Enhanced form submission handling with offline support
    const forms = document.querySelectorAll('form[data-offline-support]');
    forms.forEach(form => {
        form.addEventListener('submit', handleFormSubmission);
    });
}

function handleFormSubmission(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    const action = form.getAttribute('action') || window.location.href;
    const method = form.getAttribute('method') || 'POST';
    
    // Check if online
    if (navigator.onLine) {
        // Submit directly to server
        submitFormOnline(formData, action, method)
            .then(response => {
                if (response.ok) {
                    showMessage('Data submitted successfully', 'success');
                    form.reset();
                } else {
                    throw new Error('Server error');
                }
            })
            .catch(error => {
                console.error('Online submission failed:', error);
                // Store for offline sync
                storeForOfflineSync(formData, action, method);
                showMessage('Stored for offline sync', 'warning');
            });
    } else {
        // Store for offline sync
        storeForOfflineSync(formData, action, method);
        showMessage('Stored for offline sync', 'warning');
    }
}

function submitFormOnline(formData, action, method) {
    const data = Object.fromEntries(formData.entries());
    return fetch(action, {
        method: method,
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    });
}

function storeForOfflineSync(formData, action, method) {
    // Convert FormData to plain object
    const data = Object.fromEntries(formData.entries());
    data.timestamp = new Date().toISOString();
    data.url = action;
    data.method = method;
    
    // Store in localStorage
    const pendingRequests = JSON.parse(localStorage.getItem('pendingRequests') || '[]');
    pendingRequests.push(data);
    localStorage.setItem('pendingRequests', JSON.stringify(pendingRequests));
    
    // Trigger background sync if available
    if ('serviceWorker' in navigator && 'sync' in navigator.serviceWorker) {
        navigator.serviceWorker.ready.then(registration => {
            registration.sync.register('sync-collections');
        });
    }
}

function handleBackgroundSync() {
    // Attempt to sync stored requests
    const pendingRequests = JSON.parse(localStorage.getItem('pendingRequests') || '[]');
    
    if (pendingRequests.length > 0 && navigator.onLine) {
        // Process each pending request
        pendingRequests.forEach((request, index) => {
            fetch(request.url, {
                method: request.method,
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(request)
            })
            .then(response => {
                if (response.ok) {
                    // Remove successful request from storage
                    pendingRequests.splice(index, 1);
                    localStorage.setItem('pendingRequests', JSON.stringify(pendingRequests));
                    console.log('Successfully synced:', request);
                } else {
                    console.error('Failed to sync:', request);
                }
            })
            .catch(error => {
                console.error('Error syncing request:', error);
            });
        });
    }
}

function registerBackgroundSync() {
    navigator.serviceWorker.ready.then(registration => {
        if ('sync' in registration) {
            registration.sync.register('sync-collections');
        }
    });
}

function handleConnectionChange() {
    const onlineStatus = navigator.onLine;
    const indicator = document.getElementById('connection-status');
    
    if (indicator) {
        indicator.textContent = onlineStatus ? 'Online' : 'Offline';
        indicator.className = onlineStatus ? 'status-online' : 'status-offline';
    }
    
    if (onlineStatus) {
        console.log('Connection restored, attempting sync...');
        // Attempt to sync any pending requests
        handleBackgroundSync();
    } else {
        console.log('Connection lost, working offline...');
    }
}

function setupOfflineIndicators() {
    // Create offline status indicator if it doesn't exist
    if (!document.getElementById('connection-status')) {
        const statusElement = document.createElement('div');
        statusElement.id = 'connection-status';
        statusElement.className = navigator.onLine ? 'status-online' : 'status-offline';
        statusElement.textContent = navigator.onLine ? 'Online' : 'Offline';
        statusElement.style.position = 'fixed';
        statusElement.style.top = '10px';
        statusElement.style.right = '10px';
        statusElement.style.padding = '5px 10px';
        statusElement.style.borderRadius = '3px';
        statusElement.style.color = 'white';
        statusElement.style.backgroundColor = navigator.onLine ? '#4CAF50' : '#f44336';
        statusElement.style.zIndex = '9999';
        statusElement.style.fontSize = '12px';
        
        document.body.appendChild(statusElement);
    }
}

function showMessage(message, type = 'info') {
    // Create and display a temporary message
    const messageElement = document.createElement('div');
    messageElement.className = `message ${type}`;
    messageElement.textContent = message;
    messageElement.style.position = 'fixed';
    messageElement.style.bottom = '20px';
    messageElement.style.left = '50%';
    messageElement.style.transform = 'translateX(-50%)';
    messageElement.style.padding = '10px 20px';
    messageElement.style.borderRadius = '4px';
    messageElement.style.color = 'white';
    messageElement.style.backgroundColor = 
        type === 'success' ? '#4CAF50' : 
        type === 'warning' ? '#FF9800' : 
        type === 'error' ? '#f44336' : '#2196F3';
    messageElement.style.zIndex = '9998';
    messageElement.style.boxShadow = '0 2px 5px rgba(0,0,0,0.2)';
    
    document.body.appendChild(messageElement);
    
    // Remove message after 3 seconds
    setTimeout(() => {
        if (messageElement.parentNode) {
            messageElement.parentNode.removeChild(messageElement);
        }
    }, 3000);
}

// Capacitor-specific functionality
function initializeCapacitorFeatures() {
    if (window.Capacitor) {
        // Initialize camera functionality
        initializeCamera();
        
        // Initialize printer functionality
        initializePrinter();
        
        // Initialize haptic feedback
        initializeHaptics();
        
        // Initialize network monitoring
        initializeNetworkMonitoring();
    }
}

function initializeCamera() {
    // Camera functionality for capturing receipts
    console.log('Camera functionality initialized');
}

function initializePrinter() {
    // Bluetooth thermal printer functionality
    console.log('Printer functionality initialized');
}

function initializeHaptics() {
    // Haptic feedback for user interactions
    console.log('Haptics functionality initialized');
}

function initializeNetworkMonitoring() {
    // Network connectivity monitoring
    console.log('Network monitoring initialized');
}

// Export functions for use in other modules
window.ApparelCollection = {
    handleFormSubmission,
    storeForOfflineSync,
    handleBackgroundSync,
    registerBackgroundSync,
    showMessage,
    initializeAppComponents
};
// Global app state
let appState = {
  isAuthenticated: false,
  currentUser: null,
  assignments: [],
  collections: {},
  bankSubmissions: []
};

// DOM Elements
const elements = {
  loginPage: document.getElementById('login-page'),
  dashboardPage: document.getElementById('dashboard-page'),
  storePage: document.getElementById('store-page'),
  bankSubmissionPage: document.getElementById('bank-submission-page'),
  loginForm: document.getElementById('login-form'),
  usernameInput: document.getElementById('username'),
  passwordInput: document.getElementById('password'),
  loginError: document.getElementById('login-error'),
  agentName: document.getElementById('agent-name'),
  totalCollected: document.getElementById('total-collected'),
  totalTarget: document.getElementById('total-target'),
  completionRate: document.getElementById('completion-rate'),
  remainingStores: document.getElementById('remaining-stores'),
  currentDate: document.getElementById('current-date'),
  assignmentsList: document.getElementById('assignments-list'),
  backToDashboard: document.getElementById('back-to-dashboard'),
  backToDashboard2: document.getElementById('back-to-dashboard-2'),
  storeInfo: document.getElementById('store-info'),
  collectionForm: document.getElementById('collection-form'),
  amountCollected: document.getElementById('amount-collected'),
  pendingAmount: document.getElementById('pending-amount'),
  comments: document.getElementById('comments'),
  receiptImages: document.getElementById('receipt-images'),
  printReceiptBtn: document.getElementById('print-receipt-btn'),
  receiptPreview: document.getElementById('receipt-preview'),
  todayTotal: document.getElementById('today-total'),
  bankSubmissionForm: document.getElementById('bank-submission-form'),
  bankTotalAmount: document.getElementById('bank-total-amount'),
  bankReceiptImage: document.getElementById('bank-receipt-image'),
  submissionHistory: document.getElementById('submission-history'),
  bottomNavigation: document.querySelectorAll('.nav-item')
};

// API Configuration
const API_BASE_URL = 'https://your-hosted-site.com/api'; // Replace with your actual API URL

// Initialize the app
document.addEventListener('DOMContentLoaded', function() {
  initializeApp();
});

// Initialize app functionality
function initializeApp() {
  setupEventListeners();
  checkAuthentication();
  setCurrentDate();
}

// Set current date in dashboard
function setCurrentDate() {
  const now = new Date();
  const options = { year: 'numeric', month: 'short', day: 'numeric' };
  elements.currentDate.textContent = now.toLocaleDateString('en-US', options);
}

// Setup event listeners
function setupEventListeners() {
  // Login form
  elements.loginForm.addEventListener('submit', handleLogin);

  // Back buttons
  elements.backToDashboard.addEventListener('click', () => showPage('dashboard'));
  elements.backToDashboard2.addEventListener('click', () => showPage('dashboard'));

  // Collection form
  elements.collectionForm.addEventListener('submit', handleCollectionSubmit);

  // Print receipt button
  elements.printReceiptBtn.addEventListener('click', handlePrintReceipt);

  // Bank submission form
  elements.bankSubmissionForm.addEventListener('submit', handleBankSubmission);

  // Bottom navigation
  elements.bottomNavigation.forEach(item => {
    item.addEventListener('click', (e) => {
      e.preventDefault();
      const page = item.getAttribute('data-page');
      showPage(page);
    });
  });
}

// Check if user is already authenticated
function checkAuthentication() {
  const token = localStorage.getItem('authToken');
  if (token) {
    // Validate token with API
    validateToken(token);
  } else {
    showPage('login');
  }
}

// Validate authentication token
async function validateToken(token) {
  try {
    const response = await fetch(`${API_BASE_URL}/validate-token`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`
      }
    });

    if (response.ok) {
      const userData = await response.json();
      appState.isAuthenticated = true;
      appState.currentUser = userData.user;
      elements.agentName.textContent = userData.user.name;
      loadDashboardData();
      showPage('dashboard');
    } else {
      localStorage.removeItem('authToken');
      showPage('login');
    }
  } catch (error) {
    console.error('Token validation failed:', error);
    localStorage.removeItem('authToken');
    showPage('login');
  }
}

// Handle login
async function handleLogin(e) {
  e.preventDefault();
  
  const username = elements.usernameInput.value.trim();
  const password = elements.passwordInput.value;

  if (!username || !password) {
    showError('Please enter both username and password');
    return;
  }

  try {
    showLoading(true);
    const response = await fetch(`${API_BASE_URL}/login`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        username: username,
        password: password
      })
    });

    const data = await response.json();

    if (response.ok && data.success) {
      localStorage.setItem('authToken', data.token);
      appState.isAuthenticated = true;
      appState.currentUser = data.user;
      elements.agentName.textContent = data.user.name;
      
      // Load assignments after login
      await loadAssignments();
      loadDashboardData();
      showPage('dashboard');
    } else {
      showError(data.message || 'Invalid credentials');
    }
  } catch (error) {
    console.error('Login error:', error);
    showError('Network error. Please try again.');
  } finally {
    showLoading(false);
  }
}

// Load assignments for the current user
async function loadAssignments() {
  try {
    const token = localStorage.getItem('authToken');
    const response = await fetch(`${API_BASE_URL}/assignments/today`, {
      headers: {
        'Authorization': `Bearer ${token}`
      }
    });

    if (response.ok) {
      appState.assignments = await response.json();
    }
  } catch (error) {
    console.error('Error loading assignments:', error);
  }
}

// Load dashboard data
function loadDashboardData() {
  loadAssignments().then(() => {
    updateDashboardStats();
    renderAssignmentsList();
  });
}

// Update dashboard statistics
function updateDashboardStats() {
  let totalCollected = 0;
  let totalTarget = 0;
  let completedCount = 0;

  appState.assignments.forEach(assignment => {
    totalTarget += assignment.target_amount || 0;
    
    if (assignment.collection) {
      totalCollected += assignment.collection.amount_collected || 0;
    }
    
    if (assignment.status === 'completed') {
      completedCount++;
    }
  });

  const completionRate = totalTarget > 0 ? ((totalCollected / totalTarget) * 100).toFixed(2) : 0;
  const remainingStores = appState.assignments.length - completedCount;

  elements.totalCollected.textContent = `SAR ${totalCollected.toFixed(2)}`;
  elements.totalTarget.textContent = `SAR ${totalTarget.toFixed(2)}`;
  elements.completionRate.textContent = `${completionRate}%`;
  elements.remainingStores.textContent = remainingStores;
}

// Render assignments list
function renderAssignmentsList() {
  elements.assignmentsList.innerHTML = '';

  if (appState.assignments.length === 0) {
    elements.assignmentsList.innerHTML = '<p>No assignments found for today.</p>';
    return;
  }

  appState.assignments.forEach(assignment => {
    const assignmentElement = document.createElement('div');
    assignmentElement.className = 'assignment-item';
    
    const statusClass = assignment.status === 'completed' ? 'status-completed' : 'status-pending';
    const statusText = assignment.status === 'completed' ? 'Completed' : 'Pending';
    
    assignmentElement.innerHTML = `
      <h3>${assignment.store_name}</h3>
      <p><strong>Region:</strong> ${assignment.region_name || 'N/A'}</p>
      <p><strong>Mall:</strong> ${assignment.mall || 'N/A'}</p>
      <p><strong>Target:</strong> SAR ${(assignment.target_amount || 0).toFixed(2)}</p>
      <p><strong>Collected:</strong> SAR ${(assignment.collection?.amount_collected || 0).toFixed(2)}</p>
      <span class="status-badge ${statusClass}">${statusText}</span>
      <button class="btn" onclick="openStore(${assignment.id})">
        ${assignment.status === 'completed' ? 'View' : 'Manage'}
      </button>
    `;
    
    elements.assignmentsList.appendChild(assignmentElement);
  });
}

// Open store page for specific assignment
function openStore(assignmentId) {
  const assignment = appState.assignments.find(a => a.id === assignmentId);
  if (!assignment) return;

  // Load store information
  elements.storeInfo.innerHTML = `
    <div class="store-info-card">
      <p><strong>Store Name:</strong> ${assignment.store_name}</p>
      <p><strong>Region:</strong> ${assignment.region_name || 'N/A'}</p>
      <p><strong>Mall:</strong> ${assignment.mall || 'N/A'}</p>
      <p><strong>Entity:</strong> ${assignment.entity || 'N/A'}</p>
      <p><strong>Brand:</strong> ${assignment.brand || 'N/A'}</p>
      <p><strong>Address:</strong> ${assignment.store_address || 'N/A'}</p>
      <p><strong>Target Amount:</strong> SAR ${(assignment.target_amount || 0).toFixed(2)}</p>
    </div>
  `;

  // Load existing collection data if available
  const collection = assignment.collection || {};
  elements.amountCollected.value = collection.amount_collected || 0;
  elements.pendingAmount.value = collection.pending_amount || 0;
  elements.comments.value = collection.comments || '';

  // Store the current assignment ID
  elements.collectionForm.dataset.assignmentId = assignmentId;

  showPage('store');
}

// Handle collection submission
async function handleCollectionSubmit(e) {
  e.preventDefault();

  const assignmentId = elements.collectionForm.dataset.assignmentId;
  if (!assignmentId) {
    showError('No assignment selected');
    return;
  }

  const formData = new FormData();
  formData.append('assignment_id', assignmentId);
  formData.append('amount_collected', elements.amountCollected.value);
  formData.append('pending_amount', elements.pendingAmount.value);
  formData.append('comments', elements.comments.value);

  // Add receipt images if any
  const files = elements.receiptImages.files;
  for (let i = 0; i < files.length; i++) {
    formData.append('receipt_images[]', files[i]);
  }

  try {
    showLoading(true);
    const token = localStorage.getItem('authToken');
    const response = await fetch(`${API_BASE_URL}/collections`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`
      },
      body: formData
    });

    const data = await response.json();

    if (response.ok) {
      // Update local assignment data
      const assignmentIndex = appState.assignments.findIndex(a => a.id == assignmentId);
      if (assignmentIndex !== -1) {
        if (!appState.assignments[assignmentIndex].collection) {
          appState.assignments[assignmentIndex].collection = {};
        }
        appState.assignments[assignmentIndex].collection.amount_collected = parseFloat(elements.amountCollected.value);
        appState.assignments[assignmentIndex].collection.pending_amount = parseFloat(elements.pendingAmount.value);
        appState.assignments[assignmentIndex].collection.comments = elements.comments.value;
        appState.assignments[assignmentIndex].status = 'completed';
      }

      updateDashboardStats();
      showSuccess('Collection saved successfully!');
    } else {
      showError(data.message || 'Failed to save collection');
    }
  } catch (error) {
    console.error('Collection submission error:', error);
    showError('Network error. Collection saved locally.');
    // Save to local storage for offline sync
    saveOfflineCollection(formData, assignmentId);
  } finally {
    showLoading(false);
  }
}

// Save collection data offline for later sync
function saveOfflineCollection(formData, assignmentId) {
  const offlineCollections = JSON.parse(localStorage.getItem('offline_collections') || '[]');
  
  const collectionData = {
    assignmentId: assignmentId,
    amountCollected: elements.amountCollected.value,
    pendingAmount: elements.pendingAmount.value,
    comments: elements.comments.value,
    timestamp: new Date().toISOString()
  };

  offlineCollections.push(collectionData);
  localStorage.setItem('offline_collections', JSON.stringify(offlineCollections));
}

// Handle print receipt
function handlePrintReceipt() {
  const assignmentId = elements.collectionForm.dataset.assignmentId;
  if (!assignmentId) return;

  const assignment = appState.assignments.find(a => a.id == assignmentId);
  if (!assignment) return;

  const receiptData = {
    assignment_id: assignmentId,
    store_name: assignment.store_name,
    agent_name: appState.currentUser.name,
    target_amount: assignment.target_amount || 0,
    amount_collected: parseFloat(elements.amountCollected.value),
    pending_amount: parseFloat(elements.pendingAmount.value),
    comments: elements.comments.value,
    date: new Date().toLocaleString()
  };

  // Trigger print via Capacitor plugin
  if (window.Capacitor && window.Capacitor.Plugins.BluetoothSerial) {
    printViaBluetooth(receiptData);
  } else {
    // Fallback: show receipt preview
    showReceiptPreview(receiptData);
  }
}

// Show receipt preview
function showReceiptPreview(receiptData) {
  elements.receiptPreview.innerHTML = `
    <h3>Receipt Preview</h3>
    <p><strong>Store:</strong> ${receiptData.store_name}</p>
    <p><strong>Agent:</strong> ${receiptData.agent_name}</p>
    <p><strong>Date:</strong> ${receiptData.date}</p>
    <p><strong>Target:</strong> SAR ${receiptData.target_amount.toFixed(2)}</p>
    <p><strong>Collected:</strong> SAR ${receiptData.amount_collected.toFixed(2)}</p>
    <p><strong>Pending:</strong> SAR ${receiptData.pending_amount.toFixed(2)}</p>
    ${receiptData.comments ? `<p><strong>Comments:</strong> ${receiptData.comments}</p>` : ''}
    <p>Please connect to Bluetooth printer to print receipt.</p>
  `;
  elements.receiptPreview.style.display = 'block';
}

// Print via Bluetooth (Capacitor plugin)
async function printViaBluetooth(receiptData) {
  try {
    // Check if Capacitor is available
    if (!window.Capacitor) {
      throw new Error('Capacitor is not available');
    }
    
    const { BluetoothSerial } = window.Capacitor.Plugins;
    
    // Check if printer is connected
    const isConnected = await BluetoothSerial.isConnected();
    if (!isConnected) {
      // Attempt to connect to a paired device
      const pairedDevices = await BluetoothSerial.list();
      if (pairedDevices.length === 0) {
        throw new Error('No paired Bluetooth devices found');
      }
      
      // Connect to the first paired device (in a real app, let user select)
      await BluetoothSerial.connect(pairedDevices[0].address);
    }
    
    // Create ESC/POS formatted receipt
    let receipt = '';
    
    // Center align and bold title
    receipt += '\x1B\x61\x01'; // Center align
    receipt += '\x1B\x45\x01'; // Bold on
    receipt += 'APPAREL COLLECTION RECEIPT\n';
    receipt += '\x1B\x45\x00'; // Bold off
    receipt += '\x1B\x61\x00'; // Left align
    
    // Add store and agent info
    receipt += `\nStore: ${receiptData.store_name}`;
    receipt += `\nAgent: ${receiptData.agent_name}`;
    receipt += `\nDate: ${receiptData.date}`;
    receipt += '\n------------------------\n';
    
    // Add amounts
    receipt += `Target Amount: SAR ${receiptData.target_amount.toFixed(2)}\n`;
    receipt += `Amount Collected: SAR ${receiptData.amount_collected.toFixed(2)}\n`;
    receipt += `Pending Amount: SAR ${receiptData.pending_amount.toFixed(2)}\n`;
    
    // Add comments if any
    if (receiptData.comments) {
      receipt += `\nComments: ${receiptData.comments}\n`;
    }
    
    // Add footer
    receipt += '\n------------------------\n';
    receipt += 'Thank you for your business!\n';
    
    // Add some blank lines and cut the paper
    receipt += '\n\n\n\x1D\x56\x41\x00'; // Paper cut command
    
    // Send to printer
    await BluetoothSerial.write(receipt);
    
    showSuccess('Receipt sent to printer successfully!');
  } catch (error) {
    console.error('Bluetooth print error:', error);
    showError('Failed to print receipt via Bluetooth: ' + error.message);
  }
}

// Handle bank submission
async function handleBankSubmission(e) {
  e.preventDefault();

  const formData = new FormData();
  formData.append('total_amount', elements.bankTotalAmount.value);
  formData.append('receipt_image', elements.bankReceiptImage.files[0]);

  try {
    showLoading(true);
    const token = localStorage.getItem('authToken');
    const response = await fetch(`${API_BASE_URL}/bank-submissions`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`
      },
      body: formData
    });

    const data = await response.json();

    if (response.ok) {
      showSuccess('Bank submission sent for approval successfully!');
      loadBankSubmissions();
      // Reset form
      elements.bankSubmissionForm.reset();
    } else {
      showError(data.message || 'Failed to submit to bank');
    }
  } catch (error) {
    console.error('Bank submission error:', error);
    showError('Network error. Data saved locally for later sync.');
    // Save offline for later sync
    saveOfflineBankSubmission(formData);
  } finally {
    showLoading(false);
  }
}

// Save bank submission offline
function saveOfflineBankSubmission(formData) {
  const offlineSubmissions = JSON.parse(localStorage.getItem('offline_bank_submissions') || '[]');
  
  const submissionData = {
    totalAmount: elements.bankTotalAmount.value,
    timestamp: new Date().toISOString()
  };

  offlineSubmissions.push(submissionData);
  localStorage.setItem('offline_bank_submissions', JSON.stringify(offlineSubmissions));
}

// Load bank submissions
async function loadBankSubmissions() {
  try {
    const token = localStorage.getItem('authToken');
    const response = await fetch(`${API_BASE_URL}/bank-submissions/history`, {
      headers: {
        'Authorization': `Bearer ${token}`
      }
    });

    if (response.ok) {
      appState.bankSubmissions = await response.json();
      renderBankSubmissionHistory();
    }
  } catch (error) {
    console.error('Error loading bank submissions:', error);
  }
}

// Render bank submission history
function renderBankSubmissionHistory() {
  elements.submissionHistory.innerHTML = '';

  if (appState.bankSubmissions.length === 0) {
    elements.submissionHistory.innerHTML = '<p>No submission history found.</p>';
    return;
  }

  appState.bankSubmissions.forEach(submission => {
    const statusClass = submission.status === 'approved' ? 'status-approved' : 
                       submission.status === 'rejected' ? 'status-rejected' : 'status-pending';
    
    const submissionElement = document.createElement('div');
    submissionElement.className = 'submission-item';
    submissionElement.innerHTML = `
      <h4>SAR ${parseFloat(submission.total_amount).toFixed(2)}</h4>
      <p><strong>Date:</strong> ${new Date(submission.created_at).toLocaleString()}</p>
      <p><strong>Status:</strong> <span class="status-badge ${statusClass}">${submission.status}</span></p>
      ${submission.approved_by_name ? `<p><strong>Approved By:</strong> ${submission.approved_by_name}</p>` : ''}
      ${submission.approved_at ? `<p><strong>Approved At:</strong> ${new Date(submission.approved_at).toLocaleString()}</p>` : ''}
    `;
    
    elements.submissionHistory.appendChild(submissionElement);
  });
}

// Show specific page
function showPage(pageName) {
  // Hide all pages
  document.querySelectorAll('.page').forEach(page => {
    page.classList.remove('active');
  });

  // Show selected page
  const pageElement = document.getElementById(`${pageName}-page`);
  if (pageElement) {
    pageElement.classList.add('active');
  }

  // Update active nav item
  document.querySelectorAll('.nav-item').forEach(item => {
    item.classList.remove('active');
  });
  document.querySelector(`[data-page="${pageName}"]`)?.classList.add('active');

  // Load page-specific data
  switch(pageName) {
    case 'dashboard':
      loadDashboardData();
      break;
    case 'bank-submission':
      loadBankSubmissions();
      // Load today's total
      const todayTotal = appState.assignments.reduce((sum, assignment) => {
        return sum + (assignment.collection?.amount_collected || 0);
      }, 0);
      elements.todayTotal.textContent = `SAR ${todayTotal.toFixed(2)}`;
      elements.bankTotalAmount.value = todayTotal.toFixed(2);
      break;
  }
}

// Show loading state
function showLoading(show) {
  const buttons = document.querySelectorAll('button[type="submit"]');
  buttons.forEach(button => {
    if (show) {
      button.disabled = true;
      button.innerHTML = '<span class="spinner"></span> Processing...';
    } else {
      button.disabled = false;
      if (button.id === 'login-form').submit) {
        button.innerHTML = 'Login';
      } else if (button.form === elements.collectionForm) {
        button.innerHTML = 'Save Collection';
      } else if (button.form === elements.bankSubmissionForm) {
        button.innerHTML = 'Submit to Bank';
      }
    }
  });
}

// Show success message
function showSuccess(message) {
  const alertDiv = document.createElement('div');
  alertDiv.className = 'alert alert-success';
  alertDiv.textContent = message;
  
  // Insert after the form or at the beginning of the page
  const page = document.querySelector('.page.active');
  if (page) {
    page.insertBefore(alertDiv, page.firstChild);
    
    // Remove after 3 seconds
    setTimeout(() => {
      alertDiv.remove();
    }, 3000);
  }
}

// Show error message
function showError(message) {
  elements.loginError.textContent = message;
  elements.loginError.style.display = 'block';
  
  // Hide after 5 seconds
  setTimeout(() => {
    elements.loginError.style.display = 'none';
  }, 5000);
}

// Sync offline data when online
function syncOfflineData() {
  if (navigator.onLine) {
    syncOfflineCollections();
    syncOfflineBankSubmissions();
  }
}

// Sync offline collections
async function syncOfflineCollections() {
  const offlineCollections = JSON.parse(localStorage.getItem('offline_collections') || '[]');
  
  for (const collection of offlineCollections) {
    try {
      const formData = new FormData();
      formData.append('assignment_id', collection.assignmentId);
      formData.append('amount_collected', collection.amountCollected);
      formData.append('pending_amount', collection.pendingAmount);
      formData.append('comments', collection.comments);
      
      const token = localStorage.getItem('authToken');
      const response = await fetch(`${API_BASE_URL}/collections`, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`
        },
        body: formData
      });

      if (response.ok) {
        // Remove from offline storage
        const updatedCollections = offlineCollections.filter(c => 
          c.assignmentId !== collection.assignmentId || 
          c.timestamp !== collection.timestamp
        );
        localStorage.setItem('offline_collections', JSON.stringify(updatedCollections));
      }
    } catch (error) {
      console.error('Sync collection error:', error);
    }
  }
}

// Sync offline bank submissions
async function syncOfflineBankSubmissions() {
  const offlineSubmissions = JSON.parse(localStorage.getItem('offline_bank_submissions') || '[]');
  
  for (const submission of offlineSubmissions) {
    try {
      const formData = new FormData();
      formData.append('total_amount', submission.totalAmount);
      // Note: We can't sync files that were not uploaded, so we'd need to implement
      // a mechanism to collect and upload these files separately
      
      const token = localStorage.getItem('authToken');
      const response = await fetch(`${API_BASE_URL}/bank-submissions`, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`
        },
        body: formData
      });

      if (response.ok) {
        // Remove from offline storage
        const updatedSubmissions = offlineSubmissions.filter(s => 
          s.timestamp !== submission.timestamp
        );
        localStorage.setItem('offline_bank_submissions', JSON.stringify(updatedSubmissions));
      }
    } catch (error) {
      console.error('Sync bank submission error:', error);
    }
  }
}

// Listen for online/offline events
window.addEventListener('online', syncOfflineData);
window.addEventListener('offline', () => {
  console.log('Offline mode activated');
});

// Set current date in dashboard
function setCurrentDate() {
  const now = new Date();
  const options = { year: 'numeric', month: 'short', day: 'numeric' };
  elements.currentDate.textContent = now.toLocaleDateString('en-US', options);
}

// Setup event listeners
function setupEventListeners() {
  // Login form
  elements.loginForm.addEventListener('submit', handleLogin);

  // Back buttons
  elements.backToDashboard.addEventListener('click', () => showPage('dashboard'));
  elements.backToDashboard2.addEventListener('click', () => showPage('dashboard'));

  // Collection form
  elements.collectionForm.addEventListener('submit', handleCollectionSubmit);

  // Print receipt button
  elements.printReceiptBtn.addEventListener('click', handlePrintReceipt);

  // Bank submission form
  elements.bankSubmissionForm.addEventListener('submit', handleBankSubmission);

  // Bottom navigation
  elements.bottomNavigation.forEach(item => {
    item.addEventListener('click', (e) => {
      e.preventDefault();
      const page = item.getAttribute('data-page');
      showPage(page);
    });
  });
}

// Check if user is already authenticated
function checkAuthentication() {
  const token = localStorage.getItem('authToken');
  if (token) {
    // Validate token with API
    validateToken(token);
  } else {
    showPage('login');
  }
}

// Validate authentication token
async function validateToken(token) {
  try {
    const response = await fetch(`${API_BASE_URL}/validate-token`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`
      }
    });

    if (response.ok) {
      const userData = await response.json();
      appState.isAuthenticated = true;
      appState.currentUser = userData.user;
      elements.agentName.textContent = userData.user.name;
      loadDashboardData();
      showPage('dashboard');
    } else {
      localStorage.removeItem('authToken');
      showPage('login');
    }
  } catch (error) {
    console.error('Token validation failed:', error);
    localStorage.removeItem('authToken');
    showPage('login');
  }
}

// Handle login
async function handleLogin(e) {
  e.preventDefault();
  
  const username = elements.usernameInput.value.trim();
  const password = elements.passwordInput.value;

  if (!username || !password) {
    showError('Please enter both username and password');
    return;
  }

  try {
    showLoading(true);
    const response = await fetch(`${API_BASE_URL}/login`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        username: username,
        password: password
      })
    });

    const data = await response.json();

    if (response.ok && data.success) {
      localStorage.setItem('authToken', data.token);
      appState.isAuthenticated = true;
      appState.currentUser = data.user;
      elements.agentName.textContent = data.user.name;
      
      // Load assignments after login
      await loadAssignments();
      loadDashboardData();
      showPage('dashboard');
    } else {
      showError(data.message || 'Invalid credentials');
    }
  } catch (error) {
    console.error('Login error:', error);
    showError('Network error. Please try again.');
  } finally {
    showLoading(false);
  }
}

// Load assignments for the current user
async function loadAssignments() {
  try {
    const token = localStorage.getItem('authToken');
    const response = await fetch(`${API_BASE_URL}/assignments/today`, {
      headers: {
        'Authorization': `Bearer ${token}`
      }
    });

    if (response.ok) {
      appState.assignments = await response.json();
    }
  } catch (error) {
    console.error('Error loading assignments:', error);
  }
}

// Load dashboard data
function loadDashboardData() {
  loadAssignments().then(() => {
    updateDashboardStats();
    renderAssignmentsList();
  });
}

// Update dashboard statistics
function updateDashboardStats() {
  let totalCollected = 0;
  let totalTarget = 0;
  let completedCount = 0;

  appState.assignments.forEach(assignment => {
    totalTarget += assignment.target_amount || 0;
    
    if (assignment.collection) {
      totalCollected += assignment.collection.amount_collected || 0;
    }
    
    if (assignment.status === 'completed') {
      completedCount++;
    }
  });

  const completionRate = totalTarget > 0 ? ((totalCollected / totalTarget) * 100).toFixed(2) : 0;
  const remainingStores = appState.assignments.length - completedCount;

  elements.totalCollected.textContent = `SAR ${totalCollected.toFixed(2)}`;
  elements.totalTarget.textContent = `SAR ${totalTarget.toFixed(2)}`;
  elements.completionRate.textContent = `${completionRate}%`;
  elements.remainingStores.textContent = remainingStores;
}

// Render assignments list
function renderAssignmentsList() {
  elements.assignmentsList.innerHTML = '';

  if (appState.assignments.length === 0) {
    elements.assignmentsList.innerHTML = '<p>No assignments found for today.</p>';
    return;
  }

  appState.assignments.forEach(assignment => {
    const assignmentElement = document.createElement('div');
    assignmentElement.className = 'assignment-item';
    
    const statusClass = assignment.status === 'completed' ? 'status-completed' : 'status-pending';
    const statusText = assignment.status === 'completed' ? 'Completed' : 'Pending';
    
    assignmentElement.innerHTML = `
      <h3>${assignment.store_name}</h3>
      <p><strong>Region:</strong> ${assignment.region_name || 'N/A'}</p>
      <p><strong>Mall:</strong> ${assignment.mall || 'N/A'}</p>
      <p><strong>Target:</strong> SAR ${(assignment.target_amount || 0).toFixed(2)}</p>
      <p><strong>Collected:</strong> SAR ${(assignment.collection?.amount_collected || 0).toFixed(2)}</p>
      <span class="status-badge ${statusClass}">${statusText}</span>
      <button class="btn" onclick="openStore(${assignment.id})">
        ${assignment.status === 'completed' ? 'View' : 'Manage'}
      </button>
    `;
    
    elements.assignmentsList.appendChild(assignmentElement);
  });
}

// Open store page for specific assignment
function openStore(assignmentId) {
  const assignment = appState.assignments.find(a => a.id === assignmentId);
  if (!assignment) return;

  // Load store information
  elements.storeInfo.innerHTML = `
    <div class="store-info-card">
      <p><strong>Store Name:</strong> ${assignment.store_name}</p>
      <p><strong>Region:</strong> ${assignment.region_name || 'N/A'}</p>
      <p><strong>Mall:</strong> ${assignment.mall || 'N/A'}</p>
      <p><strong>Entity:</strong> ${assignment.entity || 'N/A'}</p>
      <p><strong>Brand:</strong> ${assignment.brand || 'N/A'}</p>
      <p><strong>Address:</strong> ${assignment.store_address || 'N/A'}</p>
      <p><strong>Target Amount:</strong> SAR ${(assignment.target_amount || 0).toFixed(2)}</p>
    </div>
  `;

  // Load existing collection data if available
  const collection = assignment.collection || {};
  elements.amountCollected.value = collection.amount_collected || 0;
  elements.pendingAmount.value = collection.pending_amount || 0;
  elements.comments.value = collection.comments || '';

  // Store the current assignment ID
  elements.collectionForm.dataset.assignmentId = assignmentId;

  showPage('store');
}

// Handle collection submission
async function handleCollectionSubmit(e) {
  e.preventDefault();

  const assignmentId = elements.collectionForm.dataset.assignmentId;
  if (!assignmentId) {
    showError('No assignment selected');
    return;
  }

  const formData = new FormData();
  formData.append('assignment_id', assignmentId);
  formData.append('amount_collected', elements.amountCollected.value);
  formData.append('pending_amount', elements.pendingAmount.value);
  formData.append('comments', elements.comments.value);

  // Add receipt images if any
  const files = elements.receiptImages.files;
  for (let i = 0; i < files.length; i++) {
    formData.append('receipt_images[]', files[i]);
  }

  try {
    showLoading(true);
    const token = localStorage.getItem('authToken');
    const response = await fetch(`${API_BASE_URL}/collections`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`
      },
      body: formData
    });

    const data = await response.json();

    if (response.ok) {
      // Update local assignment data
      const assignmentIndex = appState.assignments.findIndex(a => a.id == assignmentId);
      if (assignmentIndex !== -1) {
        if (!appState.assignments[assignmentIndex].collection) {
          appState.assignments[assignmentIndex].collection = {};
        }
        appState.assignments[assignmentIndex].collection.amount_collected = parseFloat(elements.amountCollected.value);
        appState.assignments[assignmentIndex].collection.pending_amount = parseFloat(elements.pendingAmount.value);
        appState.assignments[assignmentIndex].collection.comments = elements.comments.value;
        appState.assignments[assignmentIndex].status = 'completed';
      }

      updateDashboardStats();
      showSuccess('Collection saved successfully!');
    } else {
      showError(data.message || 'Failed to save collection');
    }
  } catch (error) {
    console.error('Collection submission error:', error);
    showError('Network error. Collection saved locally.');
    // Save to local storage for offline sync
    saveOfflineCollection(formData, assignmentId);
  } finally {
    showLoading(false);
  }
}

// Save collection data offline for later sync
function saveOfflineCollection(formData, assignmentId) {
  const offlineCollections = JSON.parse(localStorage.getItem('offline_collections') || '[]');
  
  const collectionData = {
    assignmentId: assignmentId,
    amountCollected: elements.amountCollected.value,
    pendingAmount: elements.pendingAmount.value,
    comments: elements.comments.value,
    timestamp: new Date().toISOString()
  };

  offlineCollections.push(collectionData);
  localStorage.setItem('offline_collections', JSON.stringify(offlineCollections));
}

// Handle print receipt
function handlePrintReceipt() {
  const assignmentId = elements.collectionForm.dataset.assignmentId;
  if (!assignmentId) return;

  const assignment = appState.assignments.find(a => a.id == assignmentId);
  if (!assignment) return;

  const receiptData = {
    assignment_id: assignmentId,
    store_name: assignment.store_name,
    agent_name: appState.currentUser.name,
    target_amount: assignment.target_amount || 0,
    amount_collected: parseFloat(elements.amountCollected.value),
    pending_amount: parseFloat(elements.pendingAmount.value),
    comments: elements.comments.value,
    date: new Date().toLocaleString()
  };

  // Trigger print via Capacitor plugin
  if (window.Capacitor && window.Capacitor.Plugins.BluetoothSerial) {
    printViaBluetooth(receiptData);
  } else {
    // Fallback: show receipt preview
    showReceiptPreview(receiptData);
  }
}

// Show receipt preview
function showReceiptPreview(receiptData) {
  elements.receiptPreview.innerHTML = `
    <h3>Receipt Preview</h3>
    <p><strong>Store:</strong> ${receiptData.store_name}</p>
    <p><strong>Agent:</strong> ${receiptData.agent_name}</p>
    <p><strong>Date:</strong> ${receiptData.date}</p>
    <p><strong>Target:</strong> SAR ${receiptData.target_amount.toFixed(2)}</p>
    <p><strong>Collected:</strong> SAR ${receiptData.amount_collected.toFixed(2)}</p>
    <p><strong>Pending:</strong> SAR ${receiptData.pending_amount.toFixed(2)}</p>
    ${receiptData.comments ? `<p><strong>Comments:</strong> ${receiptData.comments}</p>` : ''}
    <p>Please connect to Bluetooth printer to print receipt.</p>
  `;
  elements.receiptPreview.style.display = 'block';
}

// Print via Bluetooth (Capacitor plugin)
async function printViaBluetooth(receiptData) {
  try {
    // Check if Capacitor is available
    if (!window.Capacitor) {
      throw new Error('Capacitor is not available');
    }
    
    const { BluetoothSerial } = window.Capacitor.Plugins;
    
    // Check if printer is connected
    const isConnected = await BluetoothSerial.isConnected();
    if (!isConnected) {
      // Attempt to connect to a paired device
      const pairedDevices = await BluetoothSerial.list();
      if (pairedDevices.length === 0) {
        throw new Error('No paired Bluetooth devices found');
      }
      
      // Connect to the first paired device (in a real app, let user select)
      await BluetoothSerial.connect(pairedDevices[0].address);
    }
    
    // Create ESC/POS formatted receipt
    let receipt = '';
    
    // Center align and bold title
    receipt += '\x1B\x61\x01'; // Center align
    receipt += '\x1B\x45\x01'; // Bold on
    receipt += 'APPAREL COLLECTION RECEIPT\n';
    receipt += '\x1B\x45\x00'; // Bold off
    receipt += '\x1B\x61\x00'; // Left align
    
    // Add store and agent info
    receipt += `\nStore: ${receiptData.store_name}`;
    receipt += `\nAgent: ${receiptData.agent_name}`;
    receipt += `\nDate: ${receiptData.date}`;
    receipt += '\n------------------------\n';
    
    // Add amounts
    receipt += `Target Amount: SAR ${receiptData.target_amount.toFixed(2)}\n`;
    receipt += `Amount Collected: SAR ${receiptData.amount_collected.toFixed(2)}\n`;
    receipt += `Pending Amount: SAR ${receiptData.pending_amount.toFixed(2)}\n`;
    
    // Add comments if any
    if (receiptData.comments) {
      receipt += `\nComments: ${receiptData.comments}\n`;
    }
    
    // Add footer
    receipt += '\n------------------------\n';
    receipt += 'Thank you for your business!\n';
    
    // Add some blank lines and cut the paper
    receipt += '\n\n\n\x1D\x56\x41\x00'; // Paper cut command
    
    // Send to printer
    await BluetoothSerial.write(receipt);
    
    showSuccess('Receipt sent to printer successfully!');
  } catch (error) {
    console.error('Bluetooth print error:', error);
    showError('Failed to print receipt via Bluetooth: ' + error.message);
  }
}

// Handle bank submission
async function handleBankSubmission(e) {
  e.preventDefault();

  const formData = new FormData();
  formData.append('total_amount', elements.bankTotalAmount.value);
  formData.append('receipt_image', elements.bankReceiptImage.files[0]);

  try {
    showLoading(true);
    const token = localStorage.getItem('authToken');
    const response = await fetch(`${API_BASE_URL}/bank-submissions`, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`
      },
      body: formData
    });

    const data = await response.json();

    if (response.ok) {
      showSuccess('Bank submission sent for approval successfully!');
      loadBankSubmissions();
      // Reset form
      elements.bankSubmissionForm.reset();
    } else {
      showError(data.message || 'Failed to submit to bank');
    }
  } catch (error) {
    console.error('Bank submission error:', error);
    showError('Network error. Data saved locally for later sync.');
    // Save offline for later sync
    saveOfflineBankSubmission(formData);
  } finally {
    showLoading(false);
  }
}

// Save bank submission offline
function saveOfflineBankSubmission(formData) {
  const offlineSubmissions = JSON.parse(localStorage.getItem('offline_bank_submissions') || '[]');
  
  const submissionData = {
    totalAmount: elements.bankTotalAmount.value,
    timestamp: new Date().toISOString()
  };

  offlineSubmissions.push(submissionData);
  localStorage.setItem('offline_bank_submissions', JSON.stringify(offlineSubmissions));
}

// Load bank submissions
async function loadBankSubmissions() {
  try {
    const token = localStorage.getItem('authToken');
    const response = await fetch(`${API_BASE_URL}/bank-submissions/history`, {
      headers: {
        'Authorization': `Bearer ${token}`
      }
    });

    if (response.ok) {
      appState.bankSubmissions = await response.json();
      renderBankSubmissionHistory();
    }
  } catch (error) {
    console.error('Error loading bank submissions:', error);
  }
}

// Render bank submission history
function renderBankSubmissionHistory() {
  elements.submissionHistory.innerHTML = '';

  if (appState.bankSubmissions.length === 0) {
    elements.submissionHistory.innerHTML = '<p>No submission history found.</p>';
    return;
  }

  appState.bankSubmissions.forEach(submission => {
    const statusClass = submission.status === 'approved' ? 'status-approved' : 
                       submission.status === 'rejected' ? 'status-rejected' : 'status-pending';
    
    const submissionElement = document.createElement('div');
    submissionElement.className = 'submission-item';
    submissionElement.innerHTML = `
      <h4>SAR ${parseFloat(submission.total_amount).toFixed(2)}</h4>
      <p><strong>Date:</strong> ${new Date(submission.created_at).toLocaleString()}</p>
      <p><strong>Status:</strong> <span class="status-badge ${statusClass}">${submission.status}</span></p>
      ${submission.approved_by_name ? `<p><strong>Approved By:</strong> ${submission.approved_by_name}</p>` : ''}
      ${submission.approved_at ? `<p><strong>Approved At:</strong> ${new Date(submission.approved_at).toLocaleString()}</p>` : ''}
    `;
    
    elements.submissionHistory.appendChild(submissionElement);
  });
}

// Show specific page
function showPage(pageName) {
  // Hide all pages
  document.querySelectorAll('.page').forEach(page => {
    page.classList.remove('active');
  });

  // Show selected page
  const pageElement = document.getElementById(`${pageName}-page`);
  if (pageElement) {
    pageElement.classList.add('active');
  }

  // Update active nav item
  document.querySelectorAll('.nav-item').forEach(item => {
    item.classList.remove('active');
  });
  document.querySelector(`[data-page="${pageName}"]`)?.classList.add('active');

  // Load page-specific data
  switch(pageName) {
    case 'dashboard':
      loadDashboardData();
      break;
    case 'bank-submission':
      loadBankSubmissions();
      // Load today's total
      const todayTotal = appState.assignments.reduce((sum, assignment) => {
        return sum + (assignment.collection?.amount_collected || 0);
      }, 0);
      elements.todayTotal.textContent = `SAR ${todayTotal.toFixed(2)}`;
      elements.bankTotalAmount.value = todayTotal.toFixed(2);
      break;
  }
}

// Show loading state
function showLoading(show) {
  const buttons = document.querySelectorAll('button[type="submit"]');
  buttons.forEach(button => {
    if (show) {
      button.disabled = true;
      button.innerHTML = '<span class="spinner"></span> Processing...';
    } else {
      button.disabled = false;
      if (button.id === 'login-form').submit) {
        button.innerHTML = 'Login';
      } else if (button.form === elements.collectionForm) {
        button.innerHTML = 'Save Collection';
      } else if (button.form === elements.bankSubmissionForm) {
        button.innerHTML = 'Submit to Bank';
      }
    }
  });
}

// Show success message
function showSuccess(message) {
  const alertDiv = document.createElement('div');
  alertDiv.className = 'alert alert-success';
  alertDiv.textContent = message;
  
  // Insert after the form or at the beginning of the page
  const page = document.querySelector('.page.active');
  if (page) {
    page.insertBefore(alertDiv, page.firstChild);
    
    // Remove after 3 seconds
    setTimeout(() => {
      alertDiv.remove();
    }, 3000);
  }
}

// Show error message
function showError(message) {
  elements.loginError.textContent = message;
  elements.loginError.style.display = 'block';
  
  // Hide after 5 seconds
  setTimeout(() => {
    elements.loginError.style.display = 'none';
  }, 5000);
}

// Sync offline data when online
function syncOfflineData() {
  if (navigator.onLine) {
    syncOfflineCollections();
    syncOfflineBankSubmissions();
  }
}

// Sync offline collections
async function syncOfflineCollections() {
  const offlineCollections = JSON.parse(localStorage.getItem('offline_collections') || '[]');
  
  for (const collection of offlineCollections) {
    try {
      const formData = new FormData();
      formData.append('assignment_id', collection.assignmentId);
      formData.append('amount_collected', collection.amountCollected);
      formData.append('pending_amount', collection.pendingAmount);
      formData.append('comments', collection.comments);
      
      const token = localStorage.getItem('authToken');
      const response = await fetch(`${API_BASE_URL}/collections`, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`
        },
        body: formData
      });

      if (response.ok) {
        // Remove from offline storage
        const updatedCollections = offlineCollections.filter(c => 
          c.assignmentId !== collection.assignmentId || 
          c.timestamp !== collection.timestamp
        );
        localStorage.setItem('offline_collections', JSON.stringify(updatedCollections));
      }
    } catch (error) {
      console.error('Sync collection error:', error);
    }
  }
}

// Sync offline bank submissions
async function syncOfflineBankSubmissions() {
  const offlineSubmissions = JSON.parse(localStorage.getItem('offline_bank_submissions') || '[]');
  
  for (const submission of offlineSubmissions) {
    try {
      const formData = new FormData();
      formData.append('total_amount', submission.totalAmount);
      // Note: We can't sync files that were not uploaded, so we'd need to implement
      // a mechanism to collect and upload these files separately
      
      const token = localStorage.getItem('authToken');
      const response = await fetch(`${API_BASE_URL}/bank-submissions`, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${token}`
        },
        body: formData
      });

      if (response.ok) {
        // Remove from offline storage
        const updatedSubmissions = offlineSubmissions.filter(s => 
          s.timestamp !== submission.timestamp
        );
        localStorage.setItem('offline_bank_submissions', JSON.stringify(updatedSubmissions));
      }
    } catch (error) {
      console.error('Sync bank submission error:', error);
    }
  }
}

// Listen for online/offline events
window.addEventListener('online', syncOfflineData);
window.addEventListener('offline', () => {
  console.log('Offline mode activated');
});
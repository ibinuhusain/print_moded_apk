// Cordova Bridge for Capacitor App
// This file provides a bridge between Capacitor and Cordova plugins

(function() {
  // Check if we're running in Capacitor/Cordova environment
  if (typeof window.cordova !== 'undefined') {
    // Cordova is available, plugins should be accessible
    console.log('Cordova environment detected');
  } else {
    // Create mock implementations for web testing
    console.log('Web environment - creating mock Cordova plugins');
    
    // Mock Bluetooth Serial Plugin
    window.bluetoothSerial = {
      list: function(successCallback, errorCallback) {
        console.log('Mock: Listing Bluetooth devices');
        // Return mock devices for testing
        successCallback([
          { name: 'Test Printer 1', address: '00:00:00:00:00:01' },
          { name: 'Test Printer 2', address: '00:00:00:00:00:02' }
        ]);
      },
      
      connect: function(deviceAddress, successCallback, errorCallback) {
        console.log('Mock: Connecting to device', deviceAddress);
        setTimeout(successCallback, 500);
      },
      
      disconnect: function(successCallback, errorCallback) {
        console.log('Mock: Disconnecting from device');
        setTimeout(successCallback, 300);
      },
      
      write: function(data, successCallback, errorCallback) {
        console.log('Mock: Writing to printer:', data);
        setTimeout(successCallback, 1000);
      },
      
      subscribe: function(successCallback, errorCallback) {
        console.log('Mock: Subscribing to printer data');
      },
      
      unsubscribe: function(successCallback, errorCallback) {
        console.log('Mock: Unsubscribing from printer data');
      }
    };
    
    // Mock Camera plugin for web testing
    window.camera = {
      getPicture: function(successCallback, errorCallback, options) {
        console.log('Mock: Camera getPicture called with options', options);
        // For testing, return a base64 image
        const mockImageData = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/5+hHgAHggJ/PchI7wAAAABJRU5ErkJggg==';
        setTimeout(() => successCallback(mockImageData), 500);
      }
    };
  }
  
  // Expose a global function to register printers
  window.AgentAppBridge = {
    registerPrinter: function(address) {
      localStorage.setItem('printerAddress', address);
      console.log('Printer registered:', address);
    },
    
    getRegisteredPrinter: function() {
      return localStorage.getItem('printerAddress');
    },
    
    // Function to trigger receipt capture
    captureReceipt: function() {
      document.dispatchEvent(new CustomEvent('captureReceipt'));
    },
    
    // Function to navigate to print receipt page
    goToPrintPage: function(receiptData) {
      localStorage.setItem('currentReceipt', JSON.stringify(receiptData));
      window.location.href = 'src/print-receipt.html';
    },
    
    // Function to initiate print
    initiatePrint: function(receiptData) {
      document.dispatchEvent(new CustomEvent('initiatePrint', { detail: receiptData }));
    },
    
    // Function to show toast notification
    showToast: function(message, duration) {
      // Using Capacitor Toast plugin if available
      if (window.Capacitor && window.Capacitor.Plugins && window.Capacitor.Plugins.Toast) {
        window.Capacitor.Plugins.Toast.show({
          text: message,
          duration: duration || 'short'
        });
      } else {
        // Fallback for web
        alert(message);
      }
    }
  };
  
  // Listen for navigation events to handle the transition to print page
  document.addEventListener('DOMContentLoaded', function() {
    // Override print links/buttons if needed
    const printButtons = document.querySelectorAll('.print-receipt, .print-btn, [id*="print"]');
    printButtons.forEach(button => {
      button.addEventListener('click', function(e) {
        e.preventDefault();
        
        // Extract receipt data from the page
        const receiptData = extractReceiptData();
        
        // Navigate to print page
        window.AgentAppBridge.goToPrintPage(receiptData);
      });
    });
  });
  
  // Helper function to extract receipt data from the current page
  function extractReceiptData() {
    // Look for elements that contain receipt information
    const items = [];
    let total = '0.00';
    
    // Try to find receipt items on the page
    const itemElements = document.querySelectorAll('.item, .receipt-item, [class*="product"], [class*="order"]');
    itemElements.forEach(el => {
      const name = el.querySelector('h3, h4, .name, .title, .product-name')?.textContent || el.textContent;
      const price = el.querySelector('.price, .cost, .amount')?.textContent || '0.00';
      items.push({ name: name.trim(), price: price.trim() });
    });
    
    // Try to find total amount
    const totalElement = document.querySelector('.total, .grand-total, .amount-due');
    if (totalElement) {
      total = totalElement.textContent;
    }
    
    // If no items found, create a basic structure
    if (items.length === 0) {
      items.push({ name: 'Service', price: 'LKR 100.00' });
    }
    
    return {
      title: document.title,
      items: items,
      total: total
    };
  }
})();
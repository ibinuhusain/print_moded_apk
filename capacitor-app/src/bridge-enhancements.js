// Enhanced Bridge for Capacitor App
// This file provides enhanced functionality between Capacitor and Cordova plugins

(function() {
  // Enhanced AgentAppBridge with additional functionality
  if (!window.AgentAppBridge) {
    window.AgentAppBridge = {};
  }

  // Extend the existing bridge with new methods
  Object.assign(window.AgentAppBridge, {
    // Enhanced receipt data extraction
    extractDetailedReceiptData: function() {
      // Try to get receipt data from various possible sources on the page
      const receiptData = {
        id: null,
        customerName: '',
        items: [],
        total: '0.00',
        date: new Date().toISOString(),
        agentId: null
      };

      // Extract receipt ID if available
      const receiptIdElement = document.querySelector('[data-receipt-id], .receipt-id, #receipt-id');
      if (receiptIdElement) {
        receiptData.id = receiptIdElement.textContent.trim() || receiptIdElement.value;
      }

      // Extract customer name
      const customerNameElement = document.querySelector('.customer-name, .client-name, [data-customer-name]');
      if (customerNameElement) {
        receiptData.customerName = customerNameElement.textContent.trim();
      } else {
        // Try to find customer info in other common elements
        const nameSelectors = ['.name', '.title', '[id*="name"]', '[class*="name"]'];
        for (const selector of nameSelectors) {
          const element = document.querySelector(selector);
          if (element && element.textContent.trim()) {
            receiptData.customerName = element.textContent.trim();
            break;
          }
        }
      }

      // Extract items
      const itemSelectors = ['.item', '.receipt-item', '.product', '.order-item', '[class*="item"]'];
      for (const selector of itemSelectors) {
        const itemElements = document.querySelectorAll(selector);
        if (itemElements.length > 0) {
          itemElements.forEach(el => {
            const name = el.querySelector('h3, h4, .name, .title, .product-name, .item-name')?.textContent || 
                         el.getAttribute('data-name') || 
                         el.textContent.split(':')[0].trim();
            const price = el.querySelector('.price, .cost, .amount, .value')?.textContent || 
                          el.getAttribute('data-price') || 
                          '0.00';
            
            // Only add if we have meaningful data
            if (name && name !== '') {
              receiptData.items.push({ 
                name: name.trim(), 
                price: price.trim(),
                quantity: el.querySelector('.quantity, .qty')?.textContent || '1'
              });
            }
          });
          break; // Break after finding items with first selector that works
        }
      }

      // If no items found with special selectors, look for general product containers
      if (receiptData.items.length === 0) {
        const productContainers = document.querySelectorAll('.product-container, .item-container, .order-details');
        productContainers.forEach(container => {
          const nameEl = container.querySelector('.name, .title, .product-name');
          const priceEl = container.querySelector('.price, .cost, .amount');
          
          if (nameEl && priceEl) {
            receiptData.items.push({
              name: nameEl.textContent.trim(),
              price: priceEl.textContent.trim()
            });
          }
        });
      }

      // If still no items, create a default one
      if (receiptData.items.length === 0) {
        receiptData.items.push({ 
          name: 'Service/Product', 
          price: 'LKR 0.00',
          quantity: '1'
        });
      }

      // Extract total
      const totalSelectors = ['.total, .grand-total, .amount-due, .final-amount, [class*="total"]'];
      for (const selector of totalSelectors) {
        const totalElement = document.querySelector(selector);
        if (totalElement && totalElement.textContent.trim()) {
          receiptData.total = totalElement.textContent.trim();
          break;
        }
      }

      // Extract agent ID if available
      const agentIdElement = document.querySelector('[data-agent-id], .agent-id');
      if (agentIdElement) {
        receiptData.agentId = agentIdElement.textContent.trim() || agentIdElement.value;
      }

      return receiptData;
    },

    // Method to save receipt data to server
    saveReceiptToServer: function(receiptData) {
      return new Promise((resolve, reject) => {
        // Get API endpoint from config or use default
        const apiUrl = localStorage.getItem('apiEndpoint') || 
                      'https://aquamarine-mule-238491.hostingersite.com/api/receipt_data.php';

        fetch(apiUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify(receiptData)
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            console.log('Receipt saved successfully:', data.receipt_id);
            resolve(data);
          } else {
            console.error('Error saving receipt:', data.error);
            reject(data.error || 'Unknown error');
          }
        })
        .catch(error => {
          console.error('Network error saving receipt:', error);
          reject(error);
        });
      });
    },

    // Method to load receipt data from server
    loadReceiptFromServer: function(receiptId) {
      return new Promise((resolve, reject) => {
        const apiUrl = localStorage.getItem('apiEndpoint') || 
                      'https://aquamarine-mule-238491.hostingersite.com/api/receipt_data.php';

        fetch(`${apiUrl}?id=${encodeURIComponent(receiptId)}`)
        .then(response => response.json())
        .then(data => {
          if (data.error) {
            console.error('Error loading receipt:', data.error);
            reject(data.error);
          } else {
            console.log('Receipt loaded successfully:', receiptId);
            resolve(data);
          }
        })
        .catch(error => {
          console.error('Network error loading receipt:', error);
          reject(error);
        });
      });
    },

    // Enhanced print function with server data
    initiateEnhancedPrint: function(receiptId) {
      if (receiptId) {
        // Load receipt from server and print
        this.loadReceiptFromServer(receiptId)
          .then(receiptData => {
            this.goToPrintPage(receiptData);
          })
          .catch(error => {
            console.error('Error loading receipt for print:', error);
            // Fallback to extracting from current page
            const receiptData = this.extractDetailedReceiptData();
            this.goToPrintPage(receiptData);
          });
      } else {
        // Extract from current page
        const receiptData = this.extractDetailedReceiptData();
        this.goToPrintPage(receiptData);
      }
    },

    // Enhanced camera capture with server saving
    captureAndSaveReceipt: async function() {
      try {
        // Trigger haptic feedback
        if (window.Capacitor && window.Capacitor.Plugins && window.Capacitor.Plugins.Haptics) {
          await window.Capacitor.Plugins.Haptics.impact({ style: 'MEDIUM' });
        }

        // Capture image using Capacitor Camera
        if (window.Capacitor && window.Capacitor.Plugins && window.Capacitor.Plugins.Camera) {
          const photo = await window.Capacitor.Plugins.Camera.getPhoto({
            resultType: 'uri',
            source: 'CAMERA',
            quality: 80
          });

          // Get receipt data from current page
          const receiptData = this.extractDetailedReceiptData();
          
          // Add image path to receipt data
          receiptData.imagePath = photo.path || photo.webPath;

          // Save receipt to server with image reference
          try {
            const result = await this.saveReceiptToServer(receiptData);
            console.log('Receipt with image saved:', result);

            // Show success notification
            this.showToast(`Receipt saved with ID: ${result.receipt_id}`, 'long');

            // Trigger haptic feedback for success
            if (window.Capacitor && window.Capacitor.Plugins && window.Capacitor.Plugins.Haptics) {
              await window.Capacitor.Plugins.Haptics.impact({ style: 'LIGHT' });
            }

            // Dispatch event for web app
            document.dispatchEvent(new CustomEvent('receiptCaptureSuccess', {
              detail: { 
                receiptId: result.receipt_id,
                imagePath: receiptData.imagePath
              }
            }));
          } catch (saveError) {
            console.error('Error saving receipt to server:', saveError);
            
            // Show error notification
            this.showToast('Error saving receipt to server', 'long');
            
            // Dispatch event for web app
            document.dispatchEvent(new CustomEvent('receiptCaptureError', {
              detail: { error: saveError.toString() }
            }));
          }
        } else {
          throw new Error('Capacitor Camera plugin not available');
        }
      } catch (error) {
        console.error('Error capturing receipt:', error);
        
        // Show error notification
        this.showToast('Error capturing receipt', 'long');
        
        // Dispatch event for web app
        document.dispatchEvent(new CustomEvent('receiptCaptureError', {
          detail: { error: error.toString() }
        }));
      }
    },

    // Enhanced notification system
    showEnhancedNotification: function(title, text, actions = []) {
      // Using Capacitor Push Notifications if available
      if (window.Capacitor && window.Capacitor.Plugins && window.Capacitor.Plugins.PushNotifications) {
        window.Capacitor.Plugins.PushNotifications.schedule([
          {
            title: title,
            body: text,
            id: Date.now(),
            schedule: { at: new Date(Date.now() + 1000) },
            actionTypeId: 'generic',
            extra: { ...actions }
          }
        ]);
      } else {
        // Fallback to browser notification
        if ('Notification' in window) {
          if (Notification.permission === 'granted') {
            new Notification(title, { body: text });
          } else if (Notification.permission !== 'denied') {
            Notification.requestPermission().then(permission => {
              if (permission === 'granted') {
                new Notification(title, { body: text });
              }
            });
          }
        }
      }
    },

    // Bluetooth printer management
    scanForBluetoothPrinters: function() {
      return new Promise((resolve, reject) => {
        if (window.bluetoothSerial) {
          window.bluetoothSerial.list(resolve, reject);
        } else {
          reject('Bluetooth Serial plugin not available');
        }
      });
    },

    connectToPrinter: function(address) {
      return new Promise((resolve, reject) => {
        if (window.bluetoothSerial) {
          window.bluetoothSerial.connect(address, resolve, reject);
        } else {
          reject('Bluetooth Serial plugin not available');
        }
      });
    },

    disconnectFromPrinter: function() {
      return new Promise((resolve, reject) => {
        if (window.bluetoothSerial) {
          window.bluetoothSerial.disconnect(resolve, reject);
        } else {
          reject('Bluetooth Serial plugin not available');
        }
      });
    },

    // Enhanced receipt formatting for thermal printer
    formatReceiptForThermalPrinter: function(receiptData) {
      let receiptString = '';
      
      // Add header with company information
      receiptString += '--------------------------------\n';
      receiptString += '        AGENT DASHBOARD\n';
      receiptString += '     Receipt Management System\n';
      receiptString += '--------------------------------\n';
      
      // Add receipt details
      receiptString += `Receipt #: ${receiptData.id || 'N/A'}\n`;
      receiptString += `Date: ${new Date(receiptData.date).toLocaleDateString()} \n`;
      receiptString += `Time: ${new Date(receiptData.date).toLocaleTimeString()} \n`;
      receiptString += `Customer: ${receiptData.customerName}\n`;
      receiptString += '--------------------------------\n';
      
      // Add items
      receiptData.items.forEach((item, index) => {
        // Truncate item name to fit on thermal paper (assuming ~30 chars width)
        const itemName = item.name.substring(0, 20);
        const paddedItem = itemName.padEnd(21, ' ');
        const itemPrice = item.price.toString().padStart(8, ' ');
        receiptString += `${paddedItem}${itemPrice}\n`;
        
        // Add quantity if available
        if (item.quantity && item.quantity !== '1') {
          receiptString += `  Qty: ${item.quantity}\n`;
        }
      });
      
      receiptString += '--------------------------------\n';
      
      // Add total
      const paddedTotalLabel = 'TOTAL'.padEnd(21, ' ');
      const paddedTotalValue = receiptData.total.toString().padStart(8, ' ');
      receiptString += `${paddedTotalLabel}${paddedTotalValue}\n`;
      
      // Add footer
      receiptString += '--------------------------------\n';
      receiptString += '   Thank you for your business!\n';
      receiptString += '     Please save your receipt\n';
      receiptString += '\n\n\n'; // Add spacing at the end
      
      return receiptString;
    }
  });

  // Override print links/buttons to use enhanced functionality
  document.addEventListener('DOMContentLoaded', function() {
    // Enhanced listener for print buttons
    const printButtons = document.querySelectorAll('.print-receipt, .print-btn, [id*="print"], [class*="print"]');
    printButtons.forEach(button => {
      button.removeEventListener('click', handlePrintClick); // Remove any existing listeners
      button.addEventListener('click', handlePrintClick);
    });

    // Also listen for any element with data-action="print-receipt"
    const dataActionPrints = document.querySelectorAll('[data-action="print-receipt"]');
    dataActionPrints.forEach(element => {
      element.addEventListener('click', handlePrintClick);
    });
  });

  // Enhanced print click handler
  function handlePrintClick(e) {
    e.preventDefault();

    // Get receipt ID if available as a data attribute
    const receiptId = this.getAttribute('data-receipt-id') || 
                     this.getAttribute('data-id') || 
                     null;

    // Use enhanced print function
    window.AgentAppBridge.initiateEnhancedPrint(receiptId);
  }

  // Add keyboard shortcut for camera capture (Ctrl+Shift+C)
  document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.shiftKey && e.key === 'c') {
      e.preventDefault();
      window.AgentAppBridge.captureAndSaveReceipt();
    }
  });

  // Add a global error handler for Cordova/Capacitor related errors
  window.addEventListener('error', function(e) {
    if (e.message.includes('cordova') || e.message.includes('Capacitor')) {
      console.error('Capacitor/Cordova error:', e.error);
      
      // Try to show error to user
      if (window.AgentAppBridge && typeof window.AgentAppBridge.showToast === 'function') {
        window.AgentAppBridge.showToast('Application error occurred. Please restart.', 'long');
      }
    }
  });

  console.log('Enhanced AgentAppBridge initialized');
})();
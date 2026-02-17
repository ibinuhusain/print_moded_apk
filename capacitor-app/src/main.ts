import { Camera, CameraResultType, CameraSource } from '@capacitor/camera';
import { Haptics, ImpactStyle } from '@capacitor/haptics';
import { Filesystem, Directory } from '@capacitor/filesystem';
import { Plugins } from '@capacitor/core';
import { PushNotifications } from '@capacitor/push-notifications';

const { App } = Plugins;

class AgentApp {
  private isWebViewReady: boolean = false;
  
  constructor() {
    this.initializeApp();
  }

  async initializeApp() {
    // Initialize Capacitor plugins
    await this.initCapacitorPlugins();
    
    // Set up event listeners
    this.setupEventListeners();
    
    // Check if webview is ready
    this.checkWebViewReady();
  }

  async initCapacitorPlugins() {
    try {
      // Request permissions for camera, storage, etc.
      await this.requestPermissions();
      
      // Initialize push notifications
      await this.initPushNotifications();
      
      console.log('Capacitor plugins initialized');
    } catch (error) {
      console.error('Error initializing plugins:', error);
    }
  }

  async requestPermissions() {
    try {
      // On Android, permissions are handled via the manifest
      // On iOS, we might need to request specific permissions
      console.log('Permissions requested');
    } catch (error) {
      console.error('Error requesting permissions:', error);
    }
  }

  async initPushNotifications() {
    try {
      const result = await PushNotifications.requestPermissions();
      
      if (result.receive === 'granted') {
        await PushNotifications.register();
      } else {
        console.log('Push notification permission denied');
      }

      PushNotifications.addListener('registration', token => {
        console.log('Push registration success, token:', token.value);
      });

      PushNotifications.addListener('registrationError', error => {
        console.error('Push registration error:', error);
      });

      PushNotifications.addListener('pushNotificationReceived', notification => {
        console.log('Push notification received:', notification);
      });

      PushNotifications.addListener('pushNotificationActionPerformed', action => {
        console.log('Push notification action performed:', action);
      });
    } catch (error) {
      console.error('Error initializing push notifications:', error);
    }
  }

  setupEventListeners() {
    // Listen for app state changes
    App.addListener('appStateChange', state => {
      if (state.isActive) {
        console.log('App is active');
        this.handleAppResume();
      } else {
        console.log('App is inactive');
        this.handleAppPause();
      }
    });

    // Listen for URL changes to handle navigation
    window.addEventListener('hashchange', () => {
      this.handleNavigation();
    });

    // Listen for custom events for printing functionality
    document.addEventListener('initiatePrint', (event: CustomEvent) => {
      this.handlePrintReceipt(event.detail);
    });

    // Listen for camera capture requests
    document.addEventListener('captureReceipt', () => {
      this.captureReceiptWithCamera();
    });
  }

  checkWebViewReady() {
    // Check if the webview is loaded and ready
    if (window.location.href.includes('agent')) {
      this.isWebViewReady = true;
      console.log('WebView is ready and on agent site');
    } else {
      // Redirect to agent login if not on agent pages
      window.location.href = 'https://aquamarine-mule-238491.hostingersite.com/agent/agent-login.php';
    }
  }

  handleNavigation() {
    // Ensure navigation stays within agent pages
    const currentUrl = window.location.href;
    if (!currentUrl.includes('/agent/')) {
      // Redirect back to agent login if trying to navigate outside agent area
      window.location.href = 'https://aquamarine-mule-238491.hostingersite.com/agent/agent-login.php';
    }
  }

  handleAppResume() {
    // Handle app resume logic
    console.log('App resumed');
    this.checkWebViewReady();
  }

  handleAppPause() {
    // Handle app pause logic
    console.log('App paused');
  }

  async captureReceiptWithCamera() {
    try {
      // Trigger haptic feedback
      await Haptics.impact({ style: ImpactStyle.Medium });
      
      const photo = await Camera.getPhoto({
        resultType: CameraResultType.Uri,
        source: CameraSource.Camera,
        quality: 80
      });

      // Process the captured image
      if (photo.webPath) {
        // Save the image to device storage
        const savedFile = await this.saveImageToStorage(photo.webPath, `receipt_${Date.now()}.jpg`);
        
        // Trigger another haptic feedback
        await Haptics.impact({ style: ImpactStyle.Light });
        
        console.log('Receipt captured and saved:', savedFile);
        
        // Optionally send the image URI to the web app
        window.dispatchEvent(new CustomEvent('receiptCaptured', {
          detail: { imagePath: savedFile }
        }));
      }
    } catch (error) {
      console.error('Error capturing receipt:', error);
      // Notify the web app about the error
      window.dispatchEvent(new CustomEvent('receiptCaptureError', {
        detail: { error: error.message }
      }));
    }
  }

  async saveImageToStorage(imagePath: string, fileName: string) {
    try {
      // Fetch the image data
      const response = await fetch(imagePath);
      const blob = await response.blob();
      const arrayBuffer = await blob.arrayBuffer();

      // Write the file to device storage
      const savedFile = await Filesystem.writeFile({
        path: fileName,
        data: arrayBuffer,
        directory: Directory.Documents
      });

      console.log('File saved at:', savedFile.uri);
      return savedFile.uri;
    } catch (error) {
      console.error('Error saving image:', error);
      throw error;
    }
  }

  async handlePrintReceipt(receiptData: any) {
    try {
      // Trigger haptic feedback for print initiation
      await Haptics.impact({ style: ImpactStyle.Heavy });
      
      // This would connect to Bluetooth thermal printer
      // Since we're using Cordova plugin, we'll trigger a JavaScript bridge call
      if ((window as any).cordova) {
        // Use Cordova Bluetooth Serial plugin
        this.printViaBluetooth(receiptData);
      } else {
        // Fallback for testing without Cordova
        console.log('Cordova not available, simulating print:', receiptData);
        setTimeout(() => {
          this.simulatePrintSuccess();
        }, 2000);
      }
    } catch (error) {
      console.error('Error handling print:', error);
      // Notify the web app about the error
      window.dispatchEvent(new CustomEvent('printError', {
        detail: { error: error.message }
      }));
    }
  }

  printViaBluetooth(receiptData: any) {
    try {
      // Check if Bluetooth is enabled
      if (!(window as any).bluetoothSerial) {
        console.error('Bluetooth Serial plugin not available');
        return;
      }

      // List paired devices (this would be called first to select a printer)
      (window as any).bluetoothSerial.list((pairedDevices: any[]) => {
        console.log('Paired devices:', pairedDevices);
        
        // Assuming we have a known printer address
        // In real implementation, you'd want to select from the list
        const printerAddress = localStorage.getItem('printerAddress');
        
        if (printerAddress) {
          this.connectAndPrint(printerAddress, receiptData);
        } else {
          console.error('No printer address configured');
          // You could prompt the user to select a printer here
        }
      }, (error: any) => {
        console.error('Error listing Bluetooth devices:', error);
      });
    } catch (error) {
      console.error('Error in Bluetooth printing:', error);
    }
  }

  connectAndPrint(address: string, receiptData: any) {
    (window as any).bluetoothSerial.connect(address, () => {
      console.log('Connected to printer');
      
      // Format and send the receipt data
      const formattedReceipt = this.formatReceiptForThermalPrinter(receiptData);
      
      (window as any).bluetoothSerial.write(formattedReceipt, () => {
        console.log('Receipt sent to printer');
        
        // Disconnect after printing
        (window as any).bluetoothSerial.disconnect(() => {
          console.log('Disconnected from printer');
          
          // Trigger haptic feedback for successful print
          Haptics.impact({ style: ImpactStyle.Medium }).then(() => {
            // Notify web app of successful print
            window.dispatchEvent(new CustomEvent('printSuccess'));
          });
        }, (error: any) => {
          console.error('Error disconnecting:', error);
        });
      }, (error: any) => {
        console.error('Error writing to printer:', error);
      });
    }, (error: any) => {
      console.error('Error connecting to printer:', error);
    });
  }

  formatReceiptForThermalPrinter(receiptData: any): string {
    // Use the enhanced formatting from the bridge enhancements
    if (window.AgentAppBridge && typeof window.AgentAppBridge.formatReceiptForThermalPrinter === 'function') {
      return window.AgentAppBridge.formatReceiptForThermalPrinter(receiptData);
    }
    
    // Fallback formatting
    let receiptString = '';
    
    // Add header with company information
    receiptString += '--------------------------------\n';
    receiptString += '        AGENT DASHBOARD\n';
    receiptString += '     Receipt Management System\n';
    receiptString += '--------------------------------\n';
    
    // Add receipt details
    receiptString += `Receipt #: ${receiptData.id || 'N/A'}\n`;
    receiptString += `Date: ${new Date().toLocaleDateString()} \n`;
    receiptString += `Time: ${new Date().toLocaleTimeString()} \n`;
    receiptString += `Customer: ${receiptData.customerName || 'N/A'}\n`;
    receiptString += '--------------------------------\n';
    
    // Add items
    if (receiptData.items && Array.isArray(receiptData.items)) {
      receiptData.items.forEach((item: any) => {
        // Truncate item name to fit on thermal paper (assuming ~30 chars width)
        const itemName = (item.name || '').substring(0, 20);
        const paddedItem = itemName.padEnd(21, ' ');
        const itemPrice = (item.price || '0.00').toString().padStart(8, ' ');
        receiptString += `${paddedItem}${itemPrice}\n`;
        
        // Add quantity if available
        if (item.quantity && item.quantity !== '1') {
          receiptString += `  Qty: ${item.quantity}\n`;
        }
      });
    } else {
      receiptString += 'No items\n';
    }
    
    receiptString += '--------------------------------\n';
    
    // Add total
    const paddedTotalLabel = 'TOTAL'.padEnd(21, ' ');
    const paddedTotalValue = (receiptData.total || '0.00').toString().padStart(8, ' ');
    receiptString += `${paddedTotalLabel}${paddedTotalValue}\n`;
    
    // Add footer
    receiptString += '--------------------------------\n';
    receiptString += '   Thank you for your business!\n';
    receiptString += '     Please save your receipt\n';
    receiptString += '\n\n\n'; // Add spacing at the end
    
    return receiptString;
  }

  simulatePrintSuccess() {
    // Simulate successful print for testing purposes
    Haptics.impact({ style: ImpactStyle.Medium }).then(() => {
      window.dispatchEvent(new CustomEvent('printSuccess'));
    });
  }
}

// Initialize the app when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
  new AgentApp();
});
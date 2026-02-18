import { Plugins } from '@capacitor/core';

const { BluetoothSerial } = Plugins;

/**
 * Bluetooth Printer Plugin for Thermal Receipt Printing
 */
class BluetoothPrinter {
  private isConnected: boolean = false;
  private deviceAddress: string | null = null;

  /**
   * Initialize the Bluetooth printer
   */
  async initialize(): Promise<void> {
    try {
      // Request necessary permissions
      await this.requestPermissions();
      
      // Enable Bluetooth
      await BluetoothSerial.enable();
      
      console.log('Bluetooth printer initialized successfully');
    } catch (error) {
      console.error('Error initializing Bluetooth printer:', error);
      throw error;
    }
  }

  /**
   * Request necessary permissions for Bluetooth operations
   */
  private async requestPermissions(): Promise<void> {
    try {
      // On Android, permissions are handled in the config
      // For iOS, we might need to request specific permissions
      console.log('Permissions requested');
    } catch (error) {
      console.error('Error requesting permissions:', error);
      throw error;
    }
  }

  /**
   * Scan for available Bluetooth devices
   */
  async scanDevices(): Promise<Array<{name: string, address: string}>> {
    try {
      const pairedDevices = await BluetoothSerial.list();
      console.log('Paired devices:', pairedDevices);
      return pairedDevices;
    } catch (error) {
      console.error('Error scanning devices:', error);
      throw error;
    }
  }

  /**
   * Connect to a specific Bluetooth device
   */
  async connectToDevice(address: string): Promise<boolean> {
    try {
      const connected = await BluetoothSerial.connect(address);
      this.isConnected = connected;
      this.deviceAddress = address;
      console.log(`Connected to device: ${address}`);
      return connected;
    } catch (error) {
      console.error('Error connecting to device:', error);
      this.isConnected = false;
      this.deviceAddress = null;
      throw error;
    }
  }

  /**
   * Disconnect from the current device
   */
  async disconnect(): Promise<boolean> {
    try {
      if (this.isConnected && this.deviceAddress) {
        const disconnected = await BluetoothSerial.disconnect();
        this.isConnected = false;
        this.deviceAddress = null;
        console.log('Disconnected from device');
        return disconnected;
      }
      return true;
    } catch (error) {
      console.error('Error disconnecting:', error);
      throw error;
    }
  }

  /**
   * Print a receipt using ESC/POS commands
   */
  async printReceipt(receiptData: {
    store_name: string,
    agent_name: string,
    target_amount: number,
    amount_collected: number,
    pending_amount: number,
    comments?: string,
    date: string
  }): Promise<boolean> {
    try {
      if (!this.isConnected) {
        throw new Error('Not connected to any printer');
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
      const written = await BluetoothSerial.write(receipt);
      console.log('Receipt sent to printer');
      
      return written;
    } catch (error) {
      console.error('Error printing receipt:', error);
      throw error;
    }
  }

  /**
   * Check if the printer is connected
   */
  isConnectedToPrinter(): boolean {
    return this.isConnected;
  }

  /**
   * Get the current device address
   */
  getCurrentDeviceAddress(): string | null {
    return this.deviceAddress;
  }
}

export default BluetoothPrinter;
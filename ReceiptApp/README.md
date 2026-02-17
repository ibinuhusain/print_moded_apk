# Receipt Capture App

## Overview
This is a Cordova-based mobile application designed to capture receipts using the device camera and print them to a Bluetooth thermal printer. The app also includes haptic feedback for user interactions and integrates with a backend API to store receipt data.

## Features Implemented

### 1. Camera Functionality
- Capture receipts using the device camera
- Save images to the photo album
- Display captured images in the app
- Haptic feedback when capturing

### 2. Thermal Printer Support
- Connect to Bluetooth thermal printers
- Print formatted receipts
- Support for basic receipt formatting
- Image placeholder printing

### 3. Haptic Feedback
- Vibrations for user actions (capture, connect, print)
- Different vibration patterns for success/error notifications
- Tactile feedback for user interactions

### 4. API Integration
- Automatic upload of captured receipts to server
- API bridge for communication with backend
- Device information tracking
- Error handling for network operations

### 5. User Interface
- Clean, intuitive interface for receipt capture
- Visual feedback for all operations
- Status messages and debug information
- Printer selection interface

## Technical Implementation

### Plugins Used
- `cordova-plugin-camera` - For camera functionality
- `cordova-plugin-file` - For file system access
- `cordova-plugin-bluetooth-serial` - For Bluetooth printer communication
- `cordova-plugin-vibration` - For haptic feedback
- `cordova-plugin-dialogs` - For user dialogs

### Key Files
- `www/index.html` - Main application interface and logic
- `www/js/api_bridge.js` - API communication layer
- `config.xml` - Configuration and permissions

### Permissions
The app requires the following permissions:
- Camera access
- Storage access (read/write)
- Bluetooth communication
- Location services (for Bluetooth discovery)
- Vibration access

## How to Build and Run

### Prerequisites
- Node.js and npm
- Cordova CLI (`npm install -g cordova`)
- Android Studio and Android SDK (for Android builds)
- Xcode (for iOS builds, macOS only)

### Setup Instructions
1. Install Cordova globally:
   ```
   npm install -g cordova
   ```

2. Navigate to the project directory:
   ```
   cd ReceiptApp
   ```

3. Add the Android platform:
   ```
   cordova platform add android
   ```

4. Build the application:
   ```
   cordova build android
   ```

### Configuration
Before building, you may need to update the API endpoint in `www/js/api_bridge.js`:
```javascript
this.baseURL = 'https://your-api-endpoint.com'; // Replace with your actual API endpoint
```

## Usage Guide

1. **Capture Receipt**: Tap the "Capture Receipt" button to open the camera and take a picture of a receipt.

2. **Connect Printer**: Tap "Connect to Printer" to scan for available Bluetooth thermal printers and select one.

3. **Print Receipt**: After capturing a receipt and connecting to a printer, tap "Print Receipt" to print a formatted receipt.

4. **Automatic Upload**: Captured receipts are automatically uploaded to the configured API endpoint.

## API Bridge Functionality

The application includes an API bridge that handles:
- Receipt uploads
- Metadata submission
- Server communication
- Error handling

The bridge provides methods for:
- `setToken()` - Set authentication token
- `submitReceipt()` - Submit receipt data to server
- `getReceiptStatus()` - Check processing status
- `uploadImage()` - Upload image files to server

## Bluetooth Printer Communication

The app uses ESC/POS commands to communicate with thermal printers:
- Initialize printer (`\x1B\x40`)
- Set alignment (`\x1B\x61\x01` for center, `\x1B\x61\x00` for left)
- Line feeds (`\x0A`)
- Print formatted receipt data

## Security Considerations

- Content Security Policy prevents inline script execution
- Secure communication with backend API
- Proper permission handling for sensitive features

## Troubleshooting

### Common Issues
- **Camera not working**: Ensure camera permissions are granted
- **Bluetooth not connecting**: Check Bluetooth permissions and ensure location services are enabled
- **Printer not found**: Make sure printer is powered on and in pairing mode
- **API errors**: Verify API endpoint configuration

### Debugging
The app includes a debug info panel that shows real-time status updates and error messages.

## Future Enhancements

Potential improvements could include:
- Enhanced image processing for better receipt quality
- More sophisticated receipt formatting
- Offline capability with sync when online
- Advanced printer command support for graphics
- Receipt OCR integration

## License

This project is licensed under the MIT License - see the LICENSE file for details.
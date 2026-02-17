# Thermal Printer Photo Receipt Application

This application allows users to take photos and print receipts using a thermal POS printer via Bluetooth connection. It uses the Web Bluetooth API and HTML5 camera functionality.

## Features

- Take photos using device camera
- Connect to thermal POS printer via Bluetooth
- Print receipts with photo attachment
- Cross-platform compatibility (web-based)

## Technical Components

- **HTML5 Camera API**: For capturing photos
- **Web Bluetooth API**: For connecting to thermal printer
- **ESC/POS Commands**: For controlling the thermal printer
- **Cordova Plugins**: For native mobile functionality

## Files Included

1. `thermal_printer_app.html` - Main application interface
2. `config.xml` - Cordova configuration file
3. `package.json` - Project dependencies

## Setup Instructions

### For Web Browser Usage

1. Open `thermal_printer_app.html` in a modern browser (Chrome, Edge, Opera)
2. Ensure your thermal printer is turned on and discoverable via Bluetooth
3. Allow camera and Bluetooth permissions when prompted
4. Follow the on-screen instructions to take a photo and print a receipt

### For Mobile App Development

1. Install Cordova CLI: `npm install -g cordova`
2. Create a new project: `cordova create thermal-printer-app com.example.thermalprinter ThermalPrinterApp`
3. Copy `thermal_printer_app.html` to `www/index.html`
4. Copy `config.xml` to the project root
5. Add platforms: `cordova platform add android ios`
6. Add plugins:
   ```
   cordova plugin add cordova-plugin-camera
   cordova plugin add cordova-plugin-bluetooth-serial
   cordova plugin add cordova-plugin-whitelist
   ```
7. Build the app: `cordova build`

## How to Use

1. Click "Start Camera" to activate the device camera
2. Position your subject and click "Capture Photo"
3. Click "Connect to Thermal Printer" to pair with your Bluetooth thermal printer
4. Once connected, click "Print Receipt" to send the receipt to the printer

## Bluetooth Service UUIDs

The app attempts to connect to common Bluetooth service UUIDs used by thermal printers:
- Nordic UART Service: `6e400001-b5a3-f393-e0a9-e50e24dcca9e`
- Serial Port Profile: `serial_port`

## ESC/POS Commands Used

- `\x1B\x40` - Initialize printer
- `\x1B\x61\x01` - Center align
- `\x1D\x56\x01` - Full paper cut

## Supported Printers

This app should work with most Bluetooth-enabled thermal printers that support standard ESC/POS commands, including:
- Generic POS thermal printers
- Epson TM-series printers
- Star Micronics printers
- Rongta printers
- Zjiang printers

## Troubleshooting

- If Bluetooth connection fails, ensure your printer is in pairing mode
- Some browsers may require HTTPS for Web Bluetooth API to work
- On Android, location services may need to be enabled for Bluetooth scanning
- Make sure your thermal printer supports the ESC/POS command set

## Security Considerations

- The app requires camera and Bluetooth permissions
- Only connect to trusted thermal printers
- The app does not store or transmit captured images

## Limitations

- Web Bluetooth API is only supported in Chrome-based browsers (Chrome, Edge, Opera)
- iOS Safari does not support Web Bluetooth API
- For iOS compatibility, consider building as a native app using Cordova
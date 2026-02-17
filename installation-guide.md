# Complete Installation Guide for Agent Dashboard Capacitor App

## Prerequisites

1. Node.js (v16 or higher)
2. npm or yarn package manager
3. Android Studio (for Android builds)
4. Xcode (for iOS builds, macOS only)
5. Java Development Kit (JDK 8 or higher)
6. Cordova Bluetooth Serial plugin

## Step-by-Step Installation

### 1. Project Setup

First, install the required Capacitor CLI globally:

```bash
npm install -g @capacitor/cli
```

Navigate to your project directory and install dependencies:

```bash
cd /path/to/capacitor-app
npm install
```

### 2. Add Platforms

Add the platforms you want to support:

```bash
npx cap add android
npx cap add ios
```

### 3. Install Required Capacitor Plugins

Install all the necessary Capacitor plugins:

```bash
npm install @capacitor/core @capacitor/cli @capacitor/android @capacitor/ios
npm install @capacitor/camera @capacitor/haptics @capacitor/filesystem
npm install @capacitor/app @capacitor/device @capacitor/network @capacitor/toast
npm install @capacitor/push-notifications @capacitor/status-bar
```

### 4. Install Cordova Bluetooth Serial Plugin

Since Capacitor doesn't have a built-in thermal printer plugin, we use the Cordova plugin:

```bash
npm install cordova-plugin-bluetooth-serial
npx cap update
```

### 5. Update Capacitor Configuration

Make sure your `capacitor.config.ts` is properly configured:

```typescript
import { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
  appId: 'com.agent.dashboard',
  appName: 'Agent Dashboard',
  webDir: 'dist',
  server: {
    url: 'https://aquamarine-mule-238491.hostingersite.com/agent/agent-login.php',
    cleartext: true
  },
  android: {
    path: './android'
  },
  ios: {
    path: './ios'
  }
};

export default config;
```

### 6. Sync Plugins to Native Projects

Sync all plugins and configurations to the native projects:

```bash
npx cap sync
```

### 7. Configure Android Permissions

The Android project should already have the required permissions in `android/app/src/main/AndroidManifest.xml`:

```xml
<uses-permission android:name="android.permission.INTERNET" />
<uses-permission android:name="android.permission.CAMERA" />
<uses-permission android:name="android.permission.WRITE_EXTERNAL_STORAGE" />
<uses-permission android:name="android.permission.READ_EXTERNAL_STORAGE" />
<uses-permission android:name="android.permission.ACCESS_NETWORK_STATE" />
<uses-permission android:name="android.permission.BLUETOOTH" android:maxSdkVersion="30" />
<uses-permission android:name="android.permission.BLUETOOTH_ADMIN" android:maxSdkVersion="30" />
<uses-permission android:name="android.permission.BLUETOOTH_CONNECT" />
<uses-permission android:name="android.permission.ACCESS_FINE_LOCATION" />
```

### 8. Build the Application

#### For Android:

```bash
npx cap build android
```

Or open the project in Android Studio:

```bash
npx cap open android
```

In Android Studio:
1. Connect your Android device or start an emulator
2. Click "Run" to build and deploy the app

#### For iOS:

```bash
npx cap build ios
```

Or open the project in Xcode:

```bash
npx cap open ios
```

In Xcode:
1. Select your development team
2. Choose your device or simulator
3. Click "Run" to build and deploy the app

### 9. Server-Side Setup

Upload the following PHP files to your web server (in the `/api/` directory relative to your agent pages):

1. `receipt_data.php` - Handles receipt data saving and retrieval
2. Make sure your database is set up with the schema from `database_setup.sql`

### 10. Testing the Features

After installation, test the following features:

1. **Navigation**: App should start at the agent login page and stay within the agent section
2. **Camera**: Test receipt capture functionality
3. **Printing**: Connect to a Bluetooth thermal printer and test receipt printing
4. **Notifications**: Check if push notifications work
5. **Haptics**: Verify haptic feedback during camera capture and printing
6. **Offline Functionality**: Test PWA features when offline

### 11. Troubleshooting

#### Common Issues:

1. **Camera not working**: Check permissions in Android/iOS settings
2. **Bluetooth not connecting**: Ensure location services are enabled and permissions granted
3. **API calls failing**: Verify your server allows CORS requests from the app
4. **App crashes on startup**: Check platform-specific logs in Android Studio or Xcode

#### Platform-Specific Logs:

**Android:**
```bash
npx cap run android --list
adb logcat | grep -i capacitor
```

**iOS:**
Use Console app on macOS to view device logs

### 12. Building Release Versions

#### Android Release:

1. Generate a signing key:
```bash
keytool -genkey -v -keystore my-release-key.keystore -alias my-key-alias -keyalg RSA -keysize 2048 -validity 10000
```

2. Configure signing in `android/app/build.gradle`:
```gradle
android {
    ...
    signingConfigs {
        release {
            storeFile file('my-release-key.keystore')
            storePassword 'your-store-password'
            keyAlias 'my-key-alias'
            keyPassword 'your-key-password'
        }
    }
    buildTypes {
        release {
            ...
            signingConfig signingConfigs.release
        }
    }
}
```

3. Build the release APK:
```bash
cd android
./gradlew assembleRelease
```

The APK will be located at `android/app/build/outputs/apk/release/app-release.apk`

## Important Notes

1. Make sure to replace placeholder database credentials in `receipt_data.php` with your actual database details
2. The thermal printer functionality depends on proper Bluetooth permissions and device compatibility
3. Test thoroughly on physical devices as emulator limitations may affect Bluetooth functionality
4. For production deployment, ensure your server uses HTTPS for security
5. Consider implementing additional security measures for API endpoints

## Additional Resources

- [Capacitor Documentation](https://capacitorjs.com/docs)
- [Cordova Bluetooth Serial Plugin](https://github.com/don/cordova-plugin-ble-central)
- [Android Bluetooth Documentation](https://developer.android.com/guide/topics/connectivity/bluetooth)
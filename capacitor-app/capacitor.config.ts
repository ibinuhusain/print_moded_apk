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
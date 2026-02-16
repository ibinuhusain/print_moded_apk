import { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
  appId: 'com.apparel.collection',
  appName: 'Apparel Collection Agent',
  webDir: 'www',
  server: {
    androidScheme: 'https',
    url: 'https://aquamarine-mule-238491.hostingersite.com',
    cleartext: true
  },
  bundledWebRuntime: false
};

export default config;
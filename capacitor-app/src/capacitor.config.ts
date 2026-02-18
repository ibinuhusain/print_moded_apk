import { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
  appId: 'com.appraisal.collection',
  appName: 'Apparel Collection Agent App',
  webDir: 'dist',
  server: {
    androidScheme: 'https'
  },
  plugins: {
    Camera: {
      quality: 90,
      allowEditing: true,
      resultType: 'uri',
      saveToGallery: true
    },
    Network: {
      wifiName: 'Apparel Collection'
    }
  }
};

export default config;
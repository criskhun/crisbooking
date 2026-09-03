import type { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
    appId: 'com.davaorentzone.app',
    appName: 'Davao Rent Zone',
    webDir: 'www',
    appendUserAgent: 'DavaoRentZoneAndroid/1.0.1',
    backgroundColor: '#173f35',
    loggingBehavior: 'none',
    server: {
        url: 'https://davaorentzone.com',
        cleartext: false,
        allowNavigation: [
            'davaorentzone.com',
            'www.davaorentzone.com',
        ],
    },
    android: {
        allowMixedContent: false,
        backgroundColor: '#173f35',
        captureInput: true,
        resolveServiceWorkerRequests: false,
        webContentsDebuggingEnabled: false,
    },
    plugins: {
        SplashScreen: {
            launchAutoHide: true,
            launchShowDuration: 1200,
            backgroundColor: '#173f35',
            showSpinner: false,
        },
        StatusBar: {
            style: 'DARK',
            backgroundColor: '#ffffff',
            overlaysWebView: false,
        },
    },
};

export default config;

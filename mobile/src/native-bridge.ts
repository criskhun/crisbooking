import { App } from '@capacitor/app';
import { Browser } from '@capacitor/browser';
import { Capacitor } from '@capacitor/core';
import { Network } from '@capacitor/network';
import { SplashScreen } from '@capacitor/splash-screen';
import { StatusBar, Style } from '@capacitor/status-bar';

const isAndroidApp = Capacitor.isNativePlatform() && Capacitor.getPlatform() === 'android';

if (isAndroidApp) {
    document.documentElement.dataset.nativePlatform = 'android';
    document.body?.classList.add('is-native-android');

    void StatusBar.setStyle({ style: Style.Dark }).catch(() => undefined);
    void StatusBar.setBackgroundColor({ color: '#ffffff' }).catch(() => undefined);
    void SplashScreen.hide().catch(() => undefined);

    void App.addListener('backButton', ({ canGoBack }) => {
        const openDialog = document.querySelector<HTMLDialogElement>('dialog[open]');

        if (openDialog) {
            openDialog.close();
            return;
        }

        if (canGoBack) {
            window.history.back();
            return;
        }

        void App.minimizeApp();
    });

    void App.addListener('appUrlOpen', ({ url }) => {
        try {
            const destination = new URL(url);
            if (destination.hostname === 'davaorentzone.com' || destination.hostname === 'www.davaorentzone.com') {
                window.location.assign(destination.href);
            }
        } catch {
            // Ignore malformed deep links.
        }
    });

    void Network.addListener('networkStatusChange', ({ connected }) => {
        window.dispatchEvent(new Event(connected ? 'online' : 'offline'));
    });

    document.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) return;

        const link = target.closest<HTMLAnchorElement>('a[href]');
        if (!link || link.hasAttribute('download')) return;

        let destination: URL;
        try {
            destination = new URL(link.href, window.location.href);
        } catch {
            return;
        }

        if (!['http:', 'https:'].includes(destination.protocol)) return;

        const isAppHost = destination.hostname === 'davaorentzone.com'
            || destination.hostname === 'www.davaorentzone.com';

        if (isAppHost && link.target !== '_blank') return;

        event.preventDefault();
        void Browser.open({ url: destination.href });
    });
}

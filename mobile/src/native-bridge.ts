import { App } from '@capacitor/app';
import { Browser } from '@capacitor/browser';
import { Capacitor } from '@capacitor/core';
import { Network } from '@capacitor/network';
import { SplashScreen } from '@capacitor/splash-screen';
import { StatusBar, Style } from '@capacitor/status-bar';

const isAndroidApp = Capacitor.isNativePlatform() && Capacitor.getPlatform() === 'android';

const hidePageLoader = () => {
    const loading = (window as Window & {
        DavaoRentZoneLoading?: { hide?: () => void };
    }).DavaoRentZoneLoading;

    loading?.hide?.();
    document.body?.classList.remove('is-loading', 'is-booting');
};

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
        hidePageLoader();

        try {
            const destination = new URL(url);

            if (destination.protocol === 'davaorentzone:'
                && destination.hostname === 'auth'
                && destination.pathname === '/callback') {
                void Browser.close().catch(() => undefined);

                const token = destination.searchParams.get('token');
                if (token) {
                    window.location.replace(`https://davaorentzone.com/auth/mobile/complete?token=${encodeURIComponent(token)}`);
                    return;
                }

                const message = destination.searchParams.get('error') || 'Social sign-in could not be completed. Please try again.';
                window.location.replace(`https://davaorentzone.com/login?mobile_oauth_error=${encodeURIComponent(message)}`);
                return;
            }

            if (destination.hostname === 'davaorentzone.com' || destination.hostname === 'www.davaorentzone.com') {
                if (destination.pathname === '/auth/mobile/return') {
                    const token = destination.searchParams.get('token');
                    if (token) {
                        void Browser.close().catch(() => undefined);
                        window.location.replace(`https://davaorentzone.com/auth/mobile/complete?token=${encodeURIComponent(token)}`);
                        return;
                    }
                }

                window.location.assign(destination.href);
            }
        } catch {
            // Ignore malformed deep links.
        }
    });

    void App.addListener('appStateChange', ({ isActive }) => {
        if (!isActive) return;

        hidePageLoader();
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

        if (isAppHost && link.hasAttribute('data-native-oauth')) {
            event.preventDefault();
            hidePageLoader();
            void Browser.open({ url: destination.href }).catch(() => {
                hidePageLoader();
            });
            return;
        }

        if (isAppHost && link.target !== '_blank') return;

        event.preventDefault();
        void Browser.open({ url: destination.href });
    });
}

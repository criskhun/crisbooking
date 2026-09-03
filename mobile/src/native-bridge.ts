import { App } from '@capacitor/app';
import { Browser } from '@capacitor/browser';
import { Capacitor } from '@capacitor/core';
import { Network } from '@capacitor/network';
import { PushNotifications } from '@capacitor/push-notifications';
import { SplashScreen } from '@capacitor/splash-screen';
import { StatusBar, Style } from '@capacitor/status-bar';

const isAndroidApp = Capacitor.isNativePlatform() && Capacitor.getPlatform() === 'android';
const pendingOAuthKey = 'davao-rent-zone-pending-oauth';
const nativePushTokenKey = 'davao-rent-zone-native-push-token';
const nativePushUserKey = 'davao-rent-zone-native-push-user';
const bridgeScript = document.currentScript as HTMLScriptElement | null;
const nativePushUserId = bridgeScript?.dataset.nativePushUserId || '';
const nativePushSubscribeUrl = bridgeScript?.dataset.nativePushSubscribeUrl || '';
const nativePushUnsubscribeUrl = bridgeScript?.dataset.nativePushUnsubscribeUrl || '';
const nativePushAvailable = Capacitor.isPluginAvailable('PushNotifications');
let checkingPendingOAuth = false;

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

    type NativePushState = 'disabled' | 'enabled' | 'denied' | 'working' | 'error';

    const emitNativePushState = (state: NativePushState, message: string) => {
        window.dispatchEvent(new CustomEvent('davaorentzone:native-push-state', {
            detail: { state, message },
        }));
    };

    const nativePushRequest = async (url: string, method: 'POST' | 'DELETE', token: string) => {
        const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
        const response = await fetch(url, {
            method,
            credentials: 'include',
            cache: 'no-store',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
            },
            body: JSON.stringify({ token, platform: 'android' }),
        });

        if (!response.ok) {
            const result = await response.json().catch(() => ({})) as { message?: string };
            throw new Error(result.message || 'The device could not be registered for notifications.');
        }
    };

    const registerNativePush = async (askPermission: boolean) => {
        if (!nativePushAvailable) {
            emitNativePushState('error', 'Update the Android app before enabling native notifications.');
            return;
        }

        if (!nativePushSubscribeUrl || !nativePushUserId) {
            emitNativePushState('disabled', 'Log in to enable notifications on this device.');
            return;
        }

        emitNativePushState('working', 'Connecting this device to notifications…');

        try {
            let permission = await PushNotifications.checkPermissions();
            if (permission.receive === 'prompt' && askPermission) {
                permission = await PushNotifications.requestPermissions();
            }

            if (permission.receive === 'prompt') {
                emitNativePushState('disabled', 'Tap enable to allow Android notifications.');
                return;
            }

            if (permission.receive !== 'granted') {
                emitNativePushState('denied', 'Notifications are blocked. Allow them in Android app settings.');
                return;
            }

            await PushNotifications.createChannel({
                id: 'davao_rent_zone_updates',
                name: 'Booking updates',
                description: 'Bookings, messages, payments, and service updates',
                importance: 5,
                visibility: 1,
                sound: 'default',
                vibration: true,
            });
            await PushNotifications.register();
        } catch (error) {
            emitNativePushState('error', error instanceof Error ? error.message : 'Mobile notifications could not be enabled.');
        }
    };

    if (nativePushAvailable) {
        void PushNotifications.addListener('registration', ({ value }) => {
            if (!nativePushSubscribeUrl || !nativePushUserId) return;

            void nativePushRequest(nativePushSubscribeUrl, 'POST', value)
                .then(() => {
                    localStorage.setItem(nativePushTokenKey, value);
                    localStorage.setItem(nativePushUserKey, nativePushUserId);
                    emitNativePushState('enabled', 'Mobile notifications are enabled on this device.');
                })
                .catch((error: unknown) => {
                    emitNativePushState('error', error instanceof Error ? error.message : 'The server could not save this device.');
                });
        });

        void PushNotifications.addListener('registrationError', ({ error }) => {
            emitNativePushState('error', error || 'Firebase could not register this Android device.');
        });

        void PushNotifications.addListener('pushNotificationReceived', () => {
            window.dispatchEvent(new CustomEvent('davaorentzone:native-notification-received'));
        });

        void PushNotifications.addListener('pushNotificationActionPerformed', ({ notification }) => {
            const target = notification.data?.url;
            if (typeof target !== 'string' || target === '') return;

            try {
                const destination = new URL(target, 'https://davaorentzone.com');
                if (!['davaorentzone.com', 'www.davaorentzone.com'].includes(destination.hostname)) return;

                const notificationId = notification.data?.notification_id;
                if (notificationId) destination.searchParams.set('notification', String(notificationId));
                window.location.assign(destination.href);
            } catch {
                // Ignore malformed or untrusted notification destinations.
            }
        });
    }

    window.addEventListener('davaorentzone:native-push-enable', () => {
        void registerNativePush(true);
    });

    window.addEventListener('davaorentzone:native-push-disable', () => {
        if (!nativePushAvailable) {
            emitNativePushState('error', 'Update the Android app before changing native notifications.');
            return;
        }

        const token = localStorage.getItem(nativePushTokenKey);
        emitNativePushState('working', 'Turning off notifications on this device…');

        void (async () => {
            try {
                if (token && nativePushUnsubscribeUrl) {
                    await nativePushRequest(nativePushUnsubscribeUrl, 'DELETE', token);
                }
                await PushNotifications.unregister();
                localStorage.removeItem(nativePushTokenKey);
                localStorage.removeItem(nativePushUserKey);
                emitNativePushState('disabled', 'Notifications are disabled on this device.');
            } catch (error) {
                emitNativePushState('error', error instanceof Error ? error.message : 'Mobile notifications could not be disabled.');
            }
        })();
    });

    window.addEventListener('davaorentzone:native-push-sync', () => {
        const token = localStorage.getItem(nativePushTokenKey);
        const userId = localStorage.getItem(nativePushUserKey);

        if (token && userId === nativePushUserId) {
            emitNativePushState('enabled', 'Mobile notifications are enabled on this device.');
            return;
        }

        void registerNativePush(false);
    });

    if (nativePushUserId) void registerNativePush(false);

    const clearPendingOAuth = () => localStorage.removeItem(pendingOAuthKey);

    const completePendingOAuth = async () => {
        if (checkingPendingOAuth) return;

        const token = localStorage.getItem(pendingOAuthKey);
        if (!token || !/^[a-zA-Z0-9]{64}$/.test(token)) {
            clearPendingOAuth();
            return;
        }

        checkingPendingOAuth = true;

        try {
            for (let attempt = 0; attempt < 4; attempt += 1) {
                const response = await fetch(
                    `https://davaorentzone.com/auth/mobile/status?token=${encodeURIComponent(token)}`,
                    {
                        credentials: 'include',
                        headers: { Accept: 'application/json' },
                        cache: 'no-store',
                    },
                );

                if (response.status === 403) {
                    clearPendingOAuth();
                    return;
                }

                if (!response.ok) return;

                const result = await response.json() as { ready?: boolean };
                if (result.ready) {
                    clearPendingOAuth();
                    hidePageLoader();
                    void Browser.close().catch(() => undefined);
                    window.location.replace(
                        `https://davaorentzone.com/auth/mobile/complete?token=${encodeURIComponent(token)}`,
                    );
                    return;
                }

                await new Promise((resolve) => window.setTimeout(resolve, 700));
            }
        } catch {
            // A later app resume will retry if the network was temporarily unavailable.
        } finally {
            checkingPendingOAuth = false;
        }
    };

    const openMobileOAuth = async (provider: 'google' | 'facebook', fallbackUrl: string) => {
        try {
            const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
            const response = await fetch('https://davaorentzone.com/auth/mobile/attempt', {
                method: 'POST',
                credentials: 'include',
                cache: 'no-store',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                },
                body: JSON.stringify({ provider }),
            });

            if (!response.ok) throw new Error('Could not prepare mobile sign-in.');

            const result = await response.json() as {
                token?: string;
                authorization_url?: string;
            };

            if (!result.token || !result.authorization_url) {
                throw new Error('Mobile sign-in response was incomplete.');
            }

            localStorage.setItem(pendingOAuthKey, result.token);
            await Browser.open({ url: result.authorization_url }).catch(() => {
                clearPendingOAuth();
                hidePageLoader();
            });
        } catch {
            clearPendingOAuth();
            hidePageLoader();
            await Browser.open({ url: fallbackUrl }).catch(() => undefined);
        }
    };

    const resumePendingOAuth = () => {
        hidePageLoader();
        window.setTimeout(() => void completePendingOAuth(), 250);
    };

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
                    clearPendingOAuth();
                    window.location.replace(`https://davaorentzone.com/auth/mobile/complete?token=${encodeURIComponent(token)}`);
                    return;
                }

                const message = destination.searchParams.get('error') || 'Social sign-in could not be completed. Please try again.';
                clearPendingOAuth();
                window.location.replace(`https://davaorentzone.com/login?mobile_oauth_error=${encodeURIComponent(message)}`);
                return;
            }

            if (destination.hostname === 'davaorentzone.com' || destination.hostname === 'www.davaorentzone.com') {
                if (destination.pathname === '/auth/mobile/return') {
                    const token = destination.searchParams.get('token');
                    if (token) {
                        clearPendingOAuth();
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

        resumePendingOAuth();
    });

    void Browser.addListener('browserFinished', () => {
        resumePendingOAuth();
    });

    window.addEventListener('focus', resumePendingOAuth);
    window.addEventListener('pageshow', resumePendingOAuth);
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') resumePendingOAuth();
    });
    resumePendingOAuth();

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
            const provider = destination.pathname.endsWith('/facebook') ? 'facebook' : 'google';
            void openMobileOAuth(provider, destination.href);
            return;
        }

        if (isAppHost && link.target !== '_blank') return;

        event.preventDefault();
        void Browser.open({ url: destination.href });
    });
}

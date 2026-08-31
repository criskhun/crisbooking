(() => {
    const script = document.currentScript;
    const installBanner = document.querySelector('[data-pwa-install-banner]');
    const installMessage = document.querySelector('[data-pwa-install-message]');
    const installAction = document.querySelector('[data-pwa-install-action]');
    const dismissAction = document.querySelector('[data-pwa-install-dismiss]');
    const connectivityStatus = document.querySelector('[data-connectivity-status]');
    const dismissedAtKey = 'davao-rent-zone-install-dismissed-at';
    const dismissalLifetime = 7 * 24 * 60 * 60 * 1000;
    let deferredInstallPrompt = null;
    let connectivityTimer = null;

    const runsStandalone = window.matchMedia('(display-mode: standalone)').matches
        || window.matchMedia('(display-mode: window-controls-overlay)').matches
        || window.navigator.standalone === true;

    const wasRecentlyDismissed = () => {
        try {
            const dismissedAt = Number(localStorage.getItem(dismissedAtKey));
            return Number.isFinite(dismissedAt) && Date.now() - dismissedAt < dismissalLifetime;
        } catch (error) {
            return false;
        }
    };

    const hideInstallBanner = () => {
        if (installBanner) installBanner.hidden = true;
    };

    const showInstallBanner = (message, actionLabel = 'Install app') => {
        if (!installBanner || runsStandalone || wasRecentlyDismissed()) return;
        if (installMessage) installMessage.textContent = message;
        if (installAction) installAction.textContent = actionLabel;
        installBanner.hidden = false;
    };

    const rememberDismissal = () => {
        try {
            localStorage.setItem(dismissedAtKey, String(Date.now()));
        } catch (error) {}
        hideInstallBanner();
    };

    const showConnectivity = (online) => {
        if (!connectivityStatus) return;
        window.clearTimeout(connectivityTimer);
        connectivityStatus.textContent = online ? 'You are back online.' : 'You are offline. Reconnect to use live booking features.';
        connectivityStatus.classList.toggle('is-offline', !online);
        connectivityStatus.hidden = false;

        if (online) {
            connectivityTimer = window.setTimeout(() => {
                connectivityStatus.hidden = true;
            }, 3500);
        }
    };

    const hideConnectivity = () => {
        if (!connectivityStatus) return;
        window.clearTimeout(connectivityTimer);
        connectivityStatus.hidden = true;
        connectivityStatus.classList.remove('is-offline');
        connectivityStatus.textContent = '';
    };

    const syncConnectivity = () => {
        if (!connectivityStatus) return;

        if (!navigator.onLine) {
            showConnectivity(false);
            return;
        }

        if (connectivityStatus.classList.contains('is-offline')) {
            showConnectivity(true);
            return;
        }

        hideConnectivity();
    };

    if ('serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1')) {
        window.addEventListener('load', () => {
            const serviceWorkerUrl = script?.dataset.serviceWorker;
            if (serviceWorkerUrl) navigator.serviceWorker.register(serviceWorkerUrl).catch(() => {});
        });
    }

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferredInstallPrompt = event;
        showInstallBanner('Install it on this device for quick, app-like access.');
    });

    installAction?.addEventListener('click', async () => {
        if (deferredInstallPrompt) {
            deferredInstallPrompt.prompt();
            await deferredInstallPrompt.userChoice;
            deferredInstallPrompt = null;
            hideInstallBanner();
            return;
        }

        const isAppleMobile = /iphone|ipad|ipod/i.test(navigator.userAgent);
        if (isAppleMobile) {
            showInstallBanner('In Safari, tap Share, then choose “Add to Home Screen”.', 'Got it');
            installAction.dataset.instructionsShown = 'true';
        }

        if (installAction.dataset.instructionsShown === 'true') rememberDismissal();
    });

    dismissAction?.addEventListener('click', rememberDismissal);
    window.addEventListener('appinstalled', () => {
        deferredInstallPrompt = null;
        hideInstallBanner();
    });
    window.addEventListener('offline', () => showConnectivity(false));
    window.addEventListener('online', () => showConnectivity(true));
    window.addEventListener('pageshow', syncConnectivity);
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') syncConnectivity();
    });

    if (!runsStandalone && /iphone|ipad|ipod/i.test(navigator.userAgent)) {
        showInstallBanner('In Safari, tap Share, then choose “Add to Home Screen”.', 'Got it');
        if (installAction) installAction.dataset.instructionsShown = 'true';
    }

    syncConnectivity();
})();

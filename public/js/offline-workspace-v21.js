(() => {
    const script = document.currentScript;
    const userId = Number(script?.dataset.offlineUserId || 0);
    const sessionUrl = script?.dataset.offlineSessionUrl || '';
    const databaseName = 'davao-rent-zone-offline-workspace-v1';
    const operationStore = 'operations';
    const manualForm = document.querySelector('[data-offline-queue="manual-booking"]');
    const queueShell = document.querySelector('[data-offline-booking-queue]');
    const queueStatus = document.querySelector('[data-offline-booking-status]');
    const queueList = document.querySelector('[data-offline-booking-list]');
    const syncButton = document.querySelector('[data-offline-sync-now]');
    let databasePromise = null;
    let syncing = false;
    let knownPendingCount = 0;

    if (!('indexedDB' in window)) return;

    const requestResult = (request) => new Promise((resolve, reject) => {
        request.addEventListener('success', () => resolve(request.result), {once: true});
        request.addEventListener('error', () => reject(request.error), {once: true});
    });

    const transactionComplete = (transaction) => new Promise((resolve, reject) => {
        transaction.addEventListener('complete', resolve, {once: true});
        transaction.addEventListener('abort', () => reject(transaction.error), {once: true});
        transaction.addEventListener('error', () => reject(transaction.error), {once: true});
    });

    const openDatabase = () => {
        if (databasePromise) return databasePromise;
        databasePromise = new Promise((resolve, reject) => {
            const request = indexedDB.open(databaseName, 1);
            request.addEventListener('upgradeneeded', () => {
                if (!request.result.objectStoreNames.contains(operationStore)) {
                    request.result.createObjectStore(operationStore, {keyPath: 'id'});
                }
            });
            request.addEventListener('success', () => resolve(request.result), {once: true});
            request.addEventListener('error', () => reject(request.error), {once: true});
        });
        return databasePromise;
    };

    const allOperations = async () => {
        const database = await openDatabase();
        const transaction = database.transaction(operationStore, 'readonly');
        const operations = await requestResult(transaction.objectStore(operationStore).getAll());
        return operations
            .filter((operation) => Number(operation.userId) === userId)
            .sort((left, right) => left.createdAt.localeCompare(right.createdAt));
    };

    const saveOperation = async (operation) => {
        const database = await openDatabase();
        const transaction = database.transaction(operationStore, 'readwrite');
        transaction.objectStore(operationStore).put(operation);
        await transactionComplete(transaction);
    };

    const removeOperation = async (operationId) => {
        const database = await openDatabase();
        const transaction = database.transaction(operationStore, 'readwrite');
        transaction.objectStore(operationStore).delete(operationId);
        await transactionComplete(transaction);
    };

    const generateSyncId = () => {
        if (window.crypto?.randomUUID) return window.crypto.randomUUID();
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (character) => {
            const random = Math.floor(Math.random() * 16);
            const value = character === 'x' ? random : (random & 0x3) | 0x8;
            return value.toString(16);
        });
    };

    const entryValue = (operation, name) => {
        const matchingEntries = operation.entries.filter(([entryName]) => entryName === name);
        return matchingEntries.length ? String(matchingEntries.at(-1)[1]) : '';
    };

    const publishQueueCount = (operations) => {
        knownPendingCount = operations.length;
        window.dispatchEvent(new CustomEvent('mybooking:offline-queue-change', {
            detail: {
                pending: operations.filter((operation) => operation.status !== 'error').length,
                errors: operations.filter((operation) => operation.status === 'error').length,
            },
        }));
    };

    const renderQueue = async () => {
        if (!userId) return [];
        const operations = await allOperations();
        publishQueueCount(operations);
        if (!queueShell || !queueList) return operations;
        queueShell.hidden = operations.length === 0;
        queueList.replaceChildren();

        const errorCount = operations.filter((operation) => operation.status === 'error').length;
        if (queueStatus) {
            queueStatus.textContent = errorCount
                ? `${errorCount} saved ${errorCount === 1 ? 'entry needs' : 'entries need'} review before syncing.`
                : `${operations.length} outside ${operations.length === 1 ? 'booking is' : 'bookings are'} waiting to sync.`;
        }

        operations.forEach((operation) => {
            const item = document.createElement('article');
            const copy = document.createElement('span');
            const title = document.createElement('b');
            const details = document.createElement('small');
            const remove = document.createElement('button');
            title.textContent = operation.summary?.unit || 'Outside booking';
            details.textContent = [
                operation.summary?.customer || 'Unnamed customer',
                entryValue(operation, 'start_date'),
                `₱${Number(entryValue(operation, 'total_amount') || 0).toLocaleString('en-PH', {minimumFractionDigits: 2})}`,
            ].join(' · ');
            copy.append(title, details);
            if (operation.error) {
                const error = document.createElement('em');
                error.textContent = operation.error;
                copy.append(error);
            }
            remove.type = 'button';
            remove.className = 'offline-booking-remove';
            remove.textContent = 'Remove';
            remove.addEventListener('click', async () => {
                if (!window.confirm('Remove this unsynced outside booking from this device?')) return;
                await removeOperation(operation.id);
                await renderQueue();
            });
            item.className = 'offline-booking-item';
            item.append(copy, remove);
            queueList.append(item);
        });

        return operations;
    };

    const showSyncNotice = (message, isError = false) => {
        const section = document.querySelector('.booking-calendar-section');
        if (!section) return;
        let notice = section.querySelector('[data-offline-sync-notice]');
        if (!notice) {
            notice = document.createElement('div');
            notice.dataset.offlineSyncNotice = '';
            section.prepend(notice);
        }
        notice.className = isError ? 'oauth-error account-alert' : 'flash-message account-alert';
        notice.setAttribute('role', isError ? 'alert' : 'status');
        notice.textContent = message;
    };

    const refreshSession = async () => {
        if (!sessionUrl) throw new Error('Sign in again before syncing saved bookings.');
        const response = await fetch(sessionUrl, {
            cache: 'no-store',
            credentials: 'same-origin',
            headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
        });
        if (!response.ok) throw new Error('Sign in again before syncing saved bookings.');
        const session = await response.json();
        if (Number(session.user_id) !== userId) throw new Error('These saved bookings belong to a different account.');
        return session.csrf_token;
    };

    const syncOperations = async ({retryErrors = false} = {}) => {
        if (syncing || !userId) return;
        syncing = true;
        if (syncButton) {
            syncButton.disabled = true;
            syncButton.textContent = 'Syncing…';
        }

        let syncedCount = 0;
        try {
            const operations = await allOperations();
            const hasSyncableOperations = operations.some((operation) => retryErrors || operation.status !== 'error');
            if (!hasSyncableOperations) return;
            const online = await window.DavaoRentZoneConnectivity?.check?.();
            if (!online) {
                if (queueStatus) queueStatus.textContent = 'Still offline. Saved bookings remain on this device and will retry automatically.';
                return;
            }
            const csrfToken = await refreshSession();

            for (const operation of operations) {
                if (operation.status === 'error' && !retryErrors) continue;
                const formData = new FormData();
                operation.entries.forEach(([name, value]) => {
                    if (name !== '_token') formData.append(name, value);
                });
                formData.append('_token', csrfToken);

                let response;
                try {
                    response = await fetch(operation.action, {
                        method: operation.method,
                        body: formData,
                        credentials: 'same-origin',
                        headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                    });
                } catch (error) {
                    break;
                }

                if (response.ok) {
                    await removeOperation(operation.id);
                    syncedCount++;
                    continue;
                }

                if ([401, 419].includes(response.status)) {
                    throw new Error('Your session expired. Sign in again to sync saved bookings.');
                }

                const result = await response.json().catch(() => ({}));
                const validationMessage = Object.values(result.errors || {}).flat()[0];
                operation.status = 'error';
                operation.error = validationMessage || result.message || 'The server could not accept this saved booking.';
                await saveOperation(operation);
            }

            const remaining = await renderQueue();
            if (syncedCount > 0) {
                showSyncNotice(`${syncedCount} offline ${syncedCount === 1 ? 'booking has' : 'bookings have'} synced. Refresh the calendar to see the latest schedule.`);
            }
            if (remaining.some((operation) => operation.status === 'error')) {
                showSyncNotice('One or more offline bookings need review. Remove the invalid entry and add it again with corrected details.', true);
            }
        } catch (error) {
            showSyncNotice(error.message || 'Saved bookings could not be synced yet.', true);
            await renderQueue();
        } finally {
            syncing = false;
            if (syncButton) {
                syncButton.disabled = false;
                syncButton.textContent = 'Sync now';
            }
        }
    };

    const queueManualBooking = async (form) => {
        const syncId = generateSyncId();
        const formData = new FormData(form);
        formData.set('offline_sync_id', syncId);
        const selectedUnit = form.querySelector('[name="unit_id"]')?.selectedOptions?.[0];
        const operation = {
            id: syncId,
            userId,
            type: 'manual-booking',
            action: form.action,
            method: (form.method || 'POST').toUpperCase(),
            entries: Array.from(formData.entries())
                .filter(([name]) => name !== '_token')
                .map(([name, value]) => [name, String(value)]),
            createdAt: new Date().toISOString(),
            status: 'pending',
            error: null,
            summary: {
                unit: selectedUnit?.textContent?.trim() || 'Outside booking',
                customer: String(formData.get('external_customer_name') || '').trim(),
            },
        };
        await saveOperation(operation);
        form.reset();
        form.querySelector('[data-manual-booking-unit]')?.dispatchEvent(new Event('change', {bubbles: true}));
        form.querySelector('[data-manual-booking-duration-unit]')?.dispatchEvent(new Event('change', {bubbles: true}));
        await renderQueue();
        showSyncNotice('Outside booking saved on this device. It will sync automatically after reconnecting.');
    };

    manualForm?.addEventListener('submit', async (event) => {
        if (manualForm.dataset.offlineSubmitBypass === 'true') {
            delete manualForm.dataset.offlineSubmitBypass;
            return;
        }

        event.preventDefault();
        const submitter = event.submitter;
        const online = await window.DavaoRentZoneConnectivity?.check?.();
        if (online) {
            manualForm.dataset.offlineSubmitBypass = 'true';
            manualForm.requestSubmit(submitter);
            return;
        }

        try {
            await queueManualBooking(manualForm);
        } catch (error) {
            showSyncNotice('This device could not save the offline booking. Keep this page open and try again.', true);
        }
    });

    syncButton?.addEventListener('click', () => syncOperations({retryErrors: true}));
    window.addEventListener('mybooking:connectivity', (event) => {
        if (event.detail?.online) syncOperations();
    });

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.action.endsWith('/logout')) return;
        if (knownPendingCount > 0) {
            event.preventDefault();
            event.stopImmediatePropagation();
            window.alert('Sync or remove the saved offline bookings before logging out. This prevents private booking details from being left on this device.');
            return;
        }
        navigator.serviceWorker?.controller?.postMessage({type: 'CLEAR_PRIVATE_OFFLINE_DATA'});
    }, true);

    if (userId && 'serviceWorker' in navigator) {
        const configureWorker = () => navigator.serviceWorker.ready.then((registration) => {
            const worker = registration.active || navigator.serviceWorker.controller;
            worker?.postMessage({type: 'SET_OFFLINE_USER', userId});
            if (window.location.pathname.replace(/\/+$/, '').endsWith('/calendar')) {
                worker?.postMessage({type: 'CACHE_CURRENT_CALENDAR', url: window.location.href, userId});
            }
        }).catch(() => {});
        configureWorker();
        navigator.serviceWorker.addEventListener('controllerchange', configureWorker);
    }

    renderQueue().then((operations) => {
        if (operations.length && window.DavaoRentZoneConnectivity?.online === true) syncOperations();
    }).catch(() => {});
})();

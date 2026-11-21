(() => {
    const panel = document.getElementById('offline-sync-panel');
    const userId = document.body.dataset.userId;
    if (!panel || !userId) return;
    const state = document.getElementById('offline-sync-state');
    const detail = document.getElementById('offline-sync-detail');
    const items = document.getElementById('offline-sync-items');
    const syncButton = document.getElementById('offline-sync-now');
    syncButton.disabled = true;
    if (!('indexedDB' in window)) {
        state.textContent = 'Offline queue unavailable';
        detail.textContent = 'This browser does not support local storage for pending transactions.';
        syncButton.disabled = true;
        return;
    }
    if (!window.OfflineRetryPolicy) {
        state.textContent = 'Offline queue unavailable';
        detail.textContent = 'The retry policy could not be loaded. Refresh before creating offline transactions.';
        syncButton.disabled = true;
        return;
    }

    const retryPolicy = window.OfflineRetryPolicy;
    let database;
    let syncInProgress = false;
    let retryTimer = null;

    const openDatabase = () => new Promise((resolve, reject) => {
        const request = indexedDB.open('student-finance-offline', 1);
        request.onupgradeneeded = () => {
            if (!request.result.objectStoreNames.contains('outbox')) request.result.createObjectStore('outbox', { keyPath: 'id' });
        };
        request.onsuccess = () => { database = request.result; resolve(database); };
        request.onerror = () => reject(request.error);
    });
    const transaction = (mode, action) => new Promise((resolve, reject) => {
        const tx = database.transaction('outbox', mode);
        const result = action(tx.objectStore('outbox'));
        tx.oncomplete = () => resolve(result?.result);
        tx.onerror = () => reject(tx.error);
        tx.onabort = () => reject(tx.error);
    });
    const allEntries = async () => {
        const tx = database.transaction('outbox', 'readonly');
        return new Promise((resolve, reject) => {
            const request = tx.objectStore('outbox').getAll();
            request.onsuccess = () => resolve(request.result || []);
            request.onerror = () => reject(request.error);
        });
    };
    const save = entry => transaction('readwrite', store => store.put(entry));
    const remove = id => transaction('readwrite', store => store.delete(id));
    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));

    const render = async () => {
        if (!database) return;
        const mine = (await allEntries()).filter(entry => String(entry.owner) === String(userId));
        state.textContent = navigator.onLine ? 'Online' : 'Offline';
        detail.textContent = mine.length
            ? mine.length + ' pending transaction(s). Server balances and reports may be stale until sync completes.'
            : 'Offline transactions stay on this device until they sync. Do not use this feature on a shared device.';
        items.innerHTML = mine.map(entry => {
            const description = entry.kind === 'income' ? 'Income · ' + (entry.payload.source || 'New entry') : 'Expense · ' + (entry.payload.category || 'New entry');
            return '<div style="display:flex;justify-content:space-between;gap:8px;padding:7px 0;border-top:1px solid #edf0f5"><span>' + escapeHtml(description) + '<small style="display:block;color:#8490a3">' + escapeHtml(entry.status || 'Pending') + '</small></span><span style="white-space:nowrap"><button type="button" data-edit="' + escapeHtml(entry.id) + '" style="border:0;background:transparent;color:#344a75;cursor:pointer">Edit</button><button type="button" data-discard="' + escapeHtml(entry.id) + '" style="border:0;background:transparent;color:#b42318;cursor:pointer">Discard</button></span></div>';
        }).join('');
        syncButton.disabled = !navigator.onLine || mine.length === 0 || syncInProgress;
        syncButton.style.opacity = syncButton.disabled ? '.55' : '1';
    };

    const postEntry = async entry => {
        const target = new URL(entry.action, window.location.origin);
        if (target.origin !== window.location.origin || !['/income', '/expenses'].includes(target.pathname)) {
            const error = new Error('This pending transaction has an unsupported destination and was not sent.');
            error.status = 400;
            throw error;
        }
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        if (!csrf) {
            const error = new Error('A fresh session is required before syncing.');
            error.status = 419;
            throw error;
        }
        const body = new URLSearchParams();
        Object.entries(entry.payload).forEach(([key, value]) => body.set(key, value));
        const response = await fetch(target.href, {
            method: 'POST', credentials: 'same-origin',
            headers: {'Accept':'application/json','Content-Type':'application/x-www-form-urlencoded;charset=UTF-8','X-CSRF-TOKEN':csrf,'X-Requested-With':'XMLHttpRequest'},
            body
        });
        const result = await response.json().catch(() => ({}));
        if (!response.ok) {
            const message = response.status === 401 || response.status === 419
                ? 'Sign in again or refresh your session to sync. Your pending entry is still saved on this device.'
                : result.message || 'The server rejected this entry. Review it before retrying.';
            const error = new Error(message);
            error.status = response.status;
            throw error;
        }
        return result;
    };

    const scheduleNextRetry = async () => {
        if (!database) return;
        if (retryTimer !== null) window.clearTimeout(retryTimer);
        retryTimer = null;
        if (!navigator.onLine || syncInProgress) return;
        const mine = (await allEntries()).filter(entry => String(entry.owner) === String(userId));
        const nextTimes = mine
            .filter(entry => Number(entry.attempts || 0) < retryPolicy.MAX_AUTOMATIC_ATTEMPTS && Number(entry.nextAttemptAt || 0) > Date.now())
            .map(entry => Number(entry.nextAttemptAt));
        if (nextTimes.length === 0) return;
        const nextAttemptAt = Math.min(...nextTimes);
        retryTimer = window.setTimeout(() => sync(false), Math.max(0, nextAttemptAt - Date.now()));
    };

    const sync = async (force = false) => {
        if (!database) return;
        if (!navigator.onLine || syncInProgress) return render();
        syncInProgress = true;
        syncButton.disabled = true;
        let synced = 0;
        try {
            const mine = (await allEntries()).filter(entry => String(entry.owner) === String(userId));
            for (const entry of mine) {
                if (force) {
                    entry.attempts = 0;
                    entry.nextAttemptAt = null;
                } else if (Number(entry.nextAttemptAt || 0) > Date.now()) {
                    continue;
                }

                try {
                    await postEntry(entry);
                    await remove(entry.id);
                    synced++;
                } catch (error) {
                    const attempts = Number(entry.attempts || 0) + 1;
                    const networkFailure = error instanceof TypeError;
                    const retryAutomatically = retryPolicy.shouldRetryAutomatically(attempts, error.status, networkFailure);
                    entry.attempts = attempts;
                    if (retryAutomatically) {
                        const delay = retryPolicy.retryDelayMs(attempts);
                        entry.nextAttemptAt = Date.now() + delay;
                        entry.status = error.message + ' Automatic retry ' + attempts + '/' + retryPolicy.MAX_AUTOMATIC_ATTEMPTS + ' in ' + Math.ceil(delay / 1000) + 's.';
                    } else {
                        entry.nextAttemptAt = null;
                        entry.status = error.message + (attempts >= retryPolicy.MAX_AUTOMATIC_ATTEMPTS
                            ? ' Automatic retries are paused. Select Sync now to retry manually.'
                            : ' Review this entry or refresh your session, then select Sync now.');
                    }
                    await save(entry);
                    detail.textContent = entry.status;
                    break;
                }
            }
        } finally {
            syncInProgress = false;
            await render();
            if (synced) detail.textContent = synced + ' transaction(s) synchronised. Refresh the page to see updated balances.';
            await scheduleNextRetry();
        }
    };

    document.addEventListener('submit', async event => {
        const form = event.target;
        const kind = form.dataset.offlineQueue;
        if (!kind || !['income', 'expense'].includes(kind) || !form.reportValidity()) return;
        event.preventDefault();
        const submit = form.querySelector('[type="submit"]');
        if (submit) submit.disabled = true;
        const payload = {};
        new FormData(form).forEach((value, key) => { if (key !== '_token' && key !== '_method' && typeof value === 'string') payload[key] = value; });
        const entry = {
            id: payload.submission_token,
            owner: String(userId),
            kind,
            action: form.action,
            payload,
            status: 'Pending',
            attempts: 0,
            nextAttemptAt: null,
            createdAt: new Date().toISOString(),
        };
        if (!entry.id) {
            detail.textContent = 'This form is missing its unique submission token. Refresh the page before saving.';
            if (submit) submit.disabled = false;
            return;
        }
        if (navigator.onLine) {
            try {
                await postEntry(entry);
                detail.textContent = kind + ' saved successfully.';
                window.location.reload();
                return;
            } catch (error) {
                if (!(error instanceof TypeError) && navigator.onLine) {
                    detail.textContent = error.message;
                    if (submit) submit.disabled = false;
                    return;
                }
            }
        }
        try {
            await save(entry);
            form.reset();
            const token = form.querySelector('input[name="submission_token"]');
            if (token) token.value = window.crypto.randomUUID();
            detail.textContent = 'Saved on this device. It will sync when you are online and signed in again.';
            await render();
        } catch (error) {
            detail.textContent = 'Local storage is unavailable. This transaction was not saved.';
        } finally {
            if (submit) submit.disabled = false;
        }
    });

    items.addEventListener('click', async event => {
        const editButton = event.target.closest('[data-edit]');
        if (editButton) {
            const entry = (await allEntries()).find(item => item.id === editButton.dataset.edit && String(item.owner) === String(userId));
            if (!entry) return;
            const label = entry.kind === 'income' ? 'Income source' : 'Expense category';
            const field = entry.kind === 'income' ? 'source' : 'category';
            const nextLabel = prompt(label, entry.payload[field] || '');
            if (nextLabel === null) return;
            const nextAmount = prompt('Amount', entry.payload.amount || '');
            if (nextAmount === null) return;
            if (!nextLabel.trim() || !/^\d+(?:\.\d{1,2})?$/.test(nextAmount) || Number(nextAmount) <= 0) {
                detail.textContent = 'Enter a description and a positive amount with up to two decimal places.';
                return;
            }
            entry.payload[field] = nextLabel.trim();
            entry.payload.amount = nextAmount;
            entry.status = 'Edited locally; ready to retry';
            entry.attempts = 0;
            entry.nextAttemptAt = null;
            await save(entry);
            await render();
            return;
        }
        const discardButton = event.target.closest('[data-discard]');
        if (!discardButton || !confirm('Permanently discard this pending transaction from this device?')) return;
        await remove(discardButton.dataset.discard);
        await render();
    });

    syncButton.addEventListener('click', () => sync(true));
    window.addEventListener('online', () => sync(false));
    window.addEventListener('offline', render);
    openDatabase().then(async () => {
        document.querySelectorAll('form[action]').forEach(form => {
            if (form.querySelector('input[name="_method"]')) return;
            if (form.action.endsWith('/income') && form.method.toLowerCase() === 'post') form.dataset.offlineQueue = 'income';
            if (form.action.endsWith('/expenses') && form.method.toLowerCase() === 'post') form.dataset.offlineQueue = 'expense';
        });
        await render();
        if (navigator.onLine) await sync(false);
    }).catch(() => {
        state.textContent = 'Offline queue unavailable';
        syncButton.disabled = true;
        detail.textContent = 'This browser could not open local storage. Do not submit financial records while offline.';
    });
})();

let stopUpdates = () => {};

const initializeInboxUpdates = () => {
    stopUpdates();
    const list = document.querySelector('[data-inbox-updates]');
    if (!list) return;
    let cursor = Number(list.dataset.cursor);
    let stopped = false;
    let timer;
    let pending;
    const poll = async () => {
        if (stopped) return;
        if (!document.hidden) {
            pending = new AbortController();
            const timeout = setTimeout(() => pending?.abort(), 10000);
            try {
                const url = new URL(list.dataset.inboxUpdates, window.location.origin);
                url.searchParams.set('after', String(cursor));
                const response = await fetch(url, {
                    credentials: 'same-origin', headers: { Accept: 'application/json' }, signal: pending.signal,
                });
                if ([401, 403, 404].includes(response.status) || response.redirected) {
                    stopped = true;
                    return;
                }
                if (!response.ok) throw new Error('Inbox update unavailable');
                const result = await response.json();
                if (stopped || !list.isConnected) return;
                const scroller = list.closest('[data-message-scroller]');
                const nearBottom = scroller.scrollHeight - scroller.scrollTop - scroller.clientHeight < 120;
                if (result.html) list.insertAdjacentHTML('beforeend', result.html);
                cursor = Number(result.cursor);
                if (result.html && nearBottom) scroller.scrollTop = scroller.scrollHeight;
            } catch {
                // Retry on the next cycle without disturbing the reply draft.
            } finally {
                clearTimeout(timeout);
                pending = null;
            }
        }
        if (!stopped) timer = setTimeout(poll, 2000);
    };
    stopUpdates = () => { stopped = true; clearTimeout(timer); pending?.abort(); };
    timer = setTimeout(poll, 2000);
};

document.addEventListener('DOMContentLoaded', initializeInboxUpdates);
document.addEventListener('livewire:navigated', initializeInboxUpdates);
document.addEventListener('livewire:navigating', () => stopUpdates());

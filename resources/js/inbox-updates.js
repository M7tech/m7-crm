// Live team inbox: new messages, delivery status, assignment, notes, presence,
// queue refresh, and a per-device reply draft that survives failures.
let stopUpdates = () => {};

const safeStorage = {
    get(key) {
        try { return window.localStorage.getItem(key); } catch { return null; }
    },
    set(key, value) {
        try { window.localStorage.setItem(key, value); } catch { /* storage unavailable */ }
    },
    remove(key) {
        try { window.localStorage.removeItem(key); } catch { /* storage unavailable */ }
    },
};

const createPoller = (intervalMs, run) => {
    let stopped = false;
    let timer;
    let pending;

    const tick = async () => {
        if (stopped) return;
        if (!document.hidden) {
            pending = new AbortController();
            const timeout = setTimeout(() => pending?.abort(), 10000);
            try {
                const keepGoing = await run(pending.signal);
                if (keepGoing === false) stopped = true;
            } catch {
                // Retry on the next cycle without disturbing the composer.
            } finally {
                clearTimeout(timeout);
                pending = null;
            }
        }
        if (!stopped) timer = setTimeout(tick, intervalMs);
    };

    timer = setTimeout(tick, intervalMs);

    return () => { stopped = true; clearTimeout(timer); pending?.abort(); };
};

const fetchJson = async (url, signal) => {
    const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' }, signal });
    if ([401, 403, 404, 419].includes(response.status) || response.redirected) return null;
    if (!response.ok) throw new Error('Inbox update unavailable');

    return response.json();
};

const initializeDraft = (conversation) => {
    const input = conversation?.querySelector('[data-draft-input]');
    const key = conversation?.dataset.draftKey;
    if (!input || !key || input.dataset.draftReady === 'true') return;
    input.dataset.draftReady = 'true';

    if (conversation.hasAttribute('data-clear-draft')) {
        safeStorage.remove(key);
    } else if (!input.value) {
        input.value = safeStorage.get(key) ?? '';
    }

    input.addEventListener('input', () => {
        if (input.value.trim()) safeStorage.set(key, input.value);
        else safeStorage.remove(key);
    });

    conversation.addEventListener('click', (event) => {
        const button = event.target.closest('[data-restore-draft]');
        if (!button) return;
        const body = button.closest('[data-message-id]')?.querySelector('[data-message-body]')?.textContent ?? '';
        input.value = body;
        safeStorage.set(key, body);
        input.focus();
    });
};

const applyConversationState = (conversation, result) => {
    const status = conversation.querySelector('[data-conversation-status]');
    if (status && result.conversation) status.textContent = result.conversation.status_label;

    const assignee = conversation.querySelector('[data-conversation-assignee]');
    if (assignee && result.conversation) {
        assignee.textContent = result.conversation.assignee;
        assignee.dataset.assigneeId = result.conversation.assignee_id ?? '';
    }

    for (const delivery of result.deliveries ?? []) {
        const label = conversation.querySelector(`[data-delivery-status="${delivery.id}"]`);
        if (label) label.textContent = delivery.label;
        const error = conversation.querySelector(`[data-delivery-error="${delivery.id}"]`);
        if (error) {
            error.classList.toggle('hidden', delivery.status !== 'failed');
            const text = error.querySelector('[data-delivery-error-text]');
            if (text) text.textContent = delivery.error ?? '';
        }
    }

    const presence = conversation.querySelector('[data-presence]');
    if (presence) {
        const names = result.viewers ?? [];
        presence.classList.toggle('hidden', names.length === 0);
        const target = presence.querySelector('[data-presence-names]');
        if (target) target.textContent = names.join(', ');
    }
};

const initializeConversation = () => {
    const conversation = document.querySelector('[data-conversation]');
    const list = conversation?.querySelector('[data-inbox-updates]');
    initializeDraft(conversation);
    if (!conversation || !list) return () => {};

    let cursor = Number(list.dataset.cursor);
    let activityCursor = Number(conversation.dataset.activityCursor ?? 0);
    const activityList = document.querySelector('[data-activity-list]');

    return createPoller(2000, async (signal) => {
        const url = new URL(list.dataset.inboxUpdates, window.location.origin);
        url.searchParams.set('after', String(cursor));
        url.searchParams.set('activity_after', String(activityCursor));
        const result = await fetchJson(url, signal);
        if (result === null) return false;
        if (!list.isConnected) return false;

        const scroller = list.closest('[data-message-scroller]');
        const nearBottom = scroller.scrollHeight - scroller.scrollTop - scroller.clientHeight < 120;
        if (result.html) list.insertAdjacentHTML('beforeend', result.html);
        cursor = Number(result.cursor);
        if (result.html && nearBottom) scroller.scrollTop = scroller.scrollHeight;

        if (result.activity_html && activityList) activityList.insertAdjacentHTML('afterbegin', result.activity_html);
        activityCursor = Number(result.activity_cursor ?? activityCursor);

        applyConversationState(conversation, result);

        return true;
    });
};

const initializeQueue = () => {
    const queue = document.querySelector('[data-inbox-list]');
    if (!queue?.dataset.feedUrl) return () => {};

    return createPoller(5000, async (signal) => {
        const url = new URL(queue.dataset.feedUrl, window.location.origin);
        if (queue.dataset.activeId) url.searchParams.set('active', queue.dataset.activeId);
        const result = await fetchJson(url, signal);
        if (result === null) return false;
        if (!queue.isConnected) return false;

        if (typeof result.html === 'string') {
            const template = document.createElement('template');
            template.innerHTML = result.html;
            const activeId = queue.dataset.activeId;
            if (activeId) {
                template.content.querySelectorAll('[data-conversation-row]').forEach((row) => {
                    const isActive = row.dataset.conversationRow === activeId;
                    row.classList.toggle('bg-blue-50', isActive);
                    if (isActive) row.setAttribute('aria-current', 'page');
                });
            }
            queue.replaceChildren(template.content);
        }

        return true;
    });
};

const initializeInbox = () => {
    stopUpdates();
    const stops = [initializeConversation(), initializeQueue()];
    stopUpdates = () => stops.forEach((stop) => stop());
};

document.addEventListener('DOMContentLoaded', initializeInbox);
document.addEventListener('livewire:navigated', initializeInbox);
document.addEventListener('livewire:navigating', () => stopUpdates());

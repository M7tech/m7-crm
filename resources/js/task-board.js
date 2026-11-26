// Kanban task board: drag a card between columns, or use the per-card status
// control. Both paths call the same server endpoint and the same policy.
const moveCard = async (board, card, status) => {
    const destination = board.querySelector(`[data-task-dropzone][data-status="${status}"]`);
    const source = card.closest('[data-task-dropzone]');
    if (!destination || card.dataset.currentStatus === status) return;

    const message = board.querySelector('[data-board-message]');
    const show = (text, ok) => {
        if (!message) return;
        message.textContent = text;
        message.className = `rounded-xl border px-4 py-3 text-sm font-medium ${ok
            ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200'
            : 'border-red-200 bg-red-50 text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200'}`;
    };

    card.classList.add('pointer-events-none', 'opacity-60');
    try {
        const response = await fetch(card.dataset.moveUrl, {
            method: 'PUT',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': board.dataset.csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ status }),
        });
        const result = await response.json().catch(() => ({}));
        if (!response.ok) {
            const validation = result.errors ? Object.values(result.errors).flat()[0] : null;
            throw new Error(validation ?? result.message ?? 'The task could not be moved.');
        }

        destination.querySelector('[data-task-cards]')?.prepend(card);
        card.dataset.currentStatus = status;
        const select = card.querySelector('select[name="status"]');
        if (select) select.value = status;
        [source, destination].forEach((column) => {
            if (!column) return;
            const cards = column.querySelectorAll('[data-task-card]').length;
            const count = column.querySelector('[data-column-count]');
            if (count) count.textContent = String(Math.max(0, Number(count.textContent) + (column === destination ? 1 : -1)));
            column.querySelector('[data-empty-column]')?.classList.toggle('hidden', cards > 0);
        });
        show(result.message ?? 'Task moved.', true);
    } catch (error) {
        show(error.message, false);
    } finally {
        card.classList.remove('pointer-events-none', 'opacity-60');
    }
};

const initializeTaskBoard = () => {
    const board = document.querySelector('[data-task-board]');
    if (!board || board.dataset.boardReady === 'true') return;
    board.dataset.boardReady = 'true';
    let dragged = null;

    board.addEventListener('dragstart', (event) => {
        const card = event.target.closest('[data-task-card][draggable="true"]');
        if (!card || !event.dataTransfer) return;
        dragged = card;
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', card.dataset.taskId ?? '');
    });

    board.addEventListener('dragend', () => {
        dragged = null;
        board.querySelectorAll('[data-task-dropzone]').forEach((zone) => zone.classList.remove('ring-2', 'ring-emerald-500'));
    });

    board.addEventListener('dragover', (event) => {
        const zone = event.target.closest('[data-task-dropzone]');
        if (!dragged || !zone) return;
        event.preventDefault();
        board.querySelectorAll('[data-task-dropzone]').forEach((item) => item.classList.toggle('ring-2', item === zone));
        zone.classList.add('ring-emerald-500');
    });

    board.addEventListener('drop', (event) => {
        const zone = event.target.closest('[data-task-dropzone]');
        if (!dragged || !zone) return;
        event.preventDefault();
        const card = dragged;
        dragged = null;
        zone.classList.remove('ring-2', 'ring-emerald-500');
        moveCard(board, card, zone.dataset.status);
    });

    // Keyboard and screen-reader path: the status control on each card.
    board.addEventListener('submit', (event) => {
        const form = event.target.closest('[data-status-form]');
        const card = form?.closest('[data-task-card]');
        if (!form || !card) return;
        event.preventDefault();
        moveCard(board, card, form.querySelector('select[name="status"]').value);
    });
};

document.addEventListener('DOMContentLoaded', initializeTaskBoard);
document.addEventListener('livewire:navigated', initializeTaskBoard);

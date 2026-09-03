const initializePipelineBoard = () => {
    const board = document.querySelector('[data-pipeline-board]');

    if (!board || board.dataset.dragInitialized === 'true') {
        return;
    }

    board.dataset.dragInitialized = 'true';
    const message = board.querySelector('[data-board-message]');
    let draggedCard = null;
    let activeDropzone = null;

    const showMessage = (text, type = 'success') => {
        if (!message) {
            return;
        }

        message.textContent = text;
        message.classList.remove(
            'hidden',
            'border-red-200',
            'bg-red-50',
            'text-red-800',
            'border-emerald-200',
            'bg-emerald-50',
            'text-emerald-800',
            'dark:border-red-900',
            'dark:bg-red-950/40',
            'dark:text-red-200',
            'dark:border-emerald-800',
            'dark:bg-emerald-950/40',
            'dark:text-emerald-200',
        );
        message.classList.add('border');
        message.classList.add(...(type === 'error'
            ? ['border-red-200', 'bg-red-50', 'text-red-800', 'dark:border-red-900', 'dark:bg-red-950/40', 'dark:text-red-200']
            : ['border-emerald-200', 'bg-emerald-50', 'text-emerald-800', 'dark:border-emerald-800', 'dark:bg-emerald-950/40', 'dark:text-emerald-200']));
    };

    const clearDropzone = () => {
        activeDropzone?.classList.remove('ring-2', 'ring-emerald-500', 'ring-offset-2', 'dark:ring-offset-zinc-800');
        activeDropzone = null;
    };

    const refreshStage = (stage) => {
        if (!stage) {
            return;
        }

        const cards = stage.querySelectorAll('[data-lead-card]');
        const count = stage.querySelector('[data-stage-count]');
        const emptyState = stage.querySelector('[data-empty-stage]');

        if (count) {
            count.textContent = String(cards.length);
        }

        emptyState?.classList.toggle('hidden', cards.length > 0);
    };

    board.addEventListener('dragstart', (event) => {
        const card = event.target.closest('[data-lead-card][draggable="true"]');

        if (!card || !event.dataTransfer) {
            return;
        }

        draggedCard = card;
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', card.dataset.leadId ?? '');
        window.requestAnimationFrame(() => card.classList.add('opacity-40', 'scale-[0.98]'));
    });

    board.addEventListener('dragover', (event) => {
        const dropzone = event.target.closest('[data-stage-dropzone]');

        if (!draggedCard || !dropzone) {
            return;
        }

        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';

        if (activeDropzone !== dropzone) {
            clearDropzone();
            activeDropzone = dropzone;
            activeDropzone.classList.add('ring-2', 'ring-emerald-500', 'ring-offset-2', 'dark:ring-offset-zinc-800');
        }
    });

    board.addEventListener('drop', async (event) => {
        const destination = event.target.closest('[data-stage-dropzone]');

        if (!draggedCard || !destination) {
            return;
        }

        event.preventDefault();
        const card = draggedCard;
        const source = card.closest('[data-stage-dropzone]');
        const destinationStageId = destination.dataset.stageId;
        clearDropzone();

        if (!destinationStageId || card.dataset.currentStageId === destinationStageId) {
            return;
        }

        let lossReason = null;

        if (destination.dataset.stageType === 'lost') {
            lossReason = window.prompt('Why was this lead lost?');

            if (lossReason === null) {
                return;
            }

            lossReason = lossReason.trim();
            if (!lossReason) {
                showMessage('A loss reason is required before moving a lead to Lost.', 'error');
                return;
            }
        }

        card.classList.add('pointer-events-none', 'animate-pulse');

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
                body: JSON.stringify({stage_id: Number(destinationStageId), loss_reason: lossReason}),
            });
            const result = await response.json();

            if (!response.ok) {
                const validationMessage = result.errors ? Object.values(result.errors).flat()[0] : null;
                throw new Error(validationMessage ?? result.message ?? 'The lead could not be moved.');
            }

            destination.querySelector('[data-stage-cards]')?.prepend(card);
            card.dataset.currentStageId = destinationStageId;
            const fallbackSelect = card.querySelector('select[name="stage_id"]');
            if (fallbackSelect) {
                fallbackSelect.value = destinationStageId;
            }
            refreshStage(source);
            refreshStage(destination);
            showMessage(result.message ?? `Lead moved to ${destination.dataset.stageName}.`);
        } catch (error) {
            showMessage(error instanceof Error ? error.message : 'The lead could not be moved.', 'error');
        } finally {
            card.classList.remove('pointer-events-none', 'animate-pulse');
        }
    });

    board.addEventListener('dragend', () => {
        draggedCard?.classList.remove('opacity-40', 'scale-[0.98]');
        draggedCard = null;
        clearDropzone();
    });
};

document.addEventListener('DOMContentLoaded', initializePipelineBoard);
document.addEventListener('livewire:navigated', initializePipelineBoard);

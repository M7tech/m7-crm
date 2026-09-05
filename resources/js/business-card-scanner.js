import { parseBusinessCard } from './business-card-parser';

export function initializeCardScanner(root = document.querySelector('[data-card-scanner]')) {
    if (!root || root.dataset.initialized) return;
    root.dataset.initialized = 'true';
    const get = selector => root.querySelector(selector);
    const form = get('[data-review]');
    const preview = get('[data-preview]');
    const progress = get('[data-progress]');
    const scanButton = get('[data-scan]');
    const saveButton = get('[data-save]');
    const language = get('[data-language]');
    const companySelect = get('[data-company-select]');
    const createCompany = get('[data-create-company]');
    const newCompany = get('[data-new-company]');
    const newCompanyName = get('[data-new-company-name]');
    const imageInputs = [get('[data-image]'), get('[data-camera]')];
    const events = new AbortController();
    let imageUrl = null;
    let worker = null;
    let generation = 0;
    let scanning = false;
    let saving = false;

    const syncCompanyChoice = () => {
        const creating = createCompany.checked;
        companySelect.disabled = creating;
        companySelect.required = !creating;
        newCompany.hidden = !creating;
        newCompanyName.required = creating;
    };

    const busy = value => {
        scanning = value;
        scanButton.disabled = value || !imageUrl;
        language.disabled = value;
        imageInputs.forEach(input => { input.disabled = value; });
    };
    const clear = () => {
        generation++;
        if (worker) { void worker.terminate(); worker = null; }
        if (imageUrl) URL.revokeObjectURL(imageUrl);
        imageUrl = null;
        preview.removeAttribute('src');
        preview.hidden = true;
        imageInputs.forEach(input => { input.value = ''; });
        form.reset();
        syncCompanyChoice();
        form.hidden = true;
        get('[data-raw]').textContent = '';
        get('[data-company-hint]').textContent = '';
        get('[data-save-error]').textContent = '';
        progress.textContent = '';
        busy(false);
    };
    const select = file => {
        if (saving) return;
        clear();
        if (!file) return;
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 10 * 1024 * 1024) {
            progress.textContent = 'Choose a JPG, PNG, or WebP image smaller than 10 MB.';
            return;
        }
        imageUrl = URL.createObjectURL(file);
        preview.src = imageUrl;
        preview.hidden = false;
        scanButton.disabled = false;
    };
    const on = (element, event, handler) => element.addEventListener(event, handler, { signal: events.signal });
    imageInputs.forEach(input => on(input, 'change', () => select(input.files[0])));
    on(get('[data-drop-zone]'), 'dragover', event => event.preventDefault());
    on(get('[data-drop-zone]'), 'drop', event => { event.preventDefault(); if (!scanning) select(event.dataTransfer.files[0]); });
    on(get('[data-clear]'), 'click', () => { if (!saving) clear(); });
    on(createCompany, 'change', syncCompanyChoice);
    on(scanButton, 'click', async () => {
        if (!imageUrl || scanning || saving) return;
        const run = ++generation;
        let localWorker = null;
        let canvas = null;
        let timeout = null;
        busy(true);
        form.hidden = true;
        progress.textContent = 'Preparing scanner. First use downloads language files…';
        try {
            if (!globalThis.WebAssembly || !globalThis.Worker) throw new Error('This browser cannot scan. Please use the server scanner.');
            const { createWorker } = await import('tesseract.js');
            if (generation !== run) return;
            const task = (async () => {
                localWorker = await createWorker(language.value.split('+'), 1, {
                    workerPath: `${root.dataset.assets}/worker.min.js`,
                    corePath: root.dataset.assets,
                    langPath: root.dataset.assets,
                    cachePath: 'm7-card-ocr-v7-2',
                    logger: message => {
                        if (run !== generation) return;
                        progress.textContent = message.status === 'recognizing text'
                            ? `Reading card… ${Math.round(message.progress * 100)}%`
                            : 'Loading scanner and language files…';
                    },
                });
                if (run !== generation) { await localWorker.terminate(); return null; }
                worker = localWorker;
                await preview.decode();
                if (run !== generation) return null;
                // Bound worker memory on phones; the full-resolution photo stays outside OCR.
                const scale = Math.min(1, 2200 / Math.max(preview.naturalWidth, preview.naturalHeight));
                canvas = document.createElement('canvas');
                canvas.width = Math.max(1, Math.round(preview.naturalWidth * scale));
                canvas.height = Math.max(1, Math.round(preview.naturalHeight * scale));
                const context = canvas.getContext('2d');
                context.fillStyle = '#fff';
                context.fillRect(0, 0, canvas.width, canvas.height);
                context.filter = 'grayscale(1) contrast(1.25)';
                context.drawImage(preview, 0, 0, canvas.width, canvas.height);
                await localWorker.setParameters({
                    tessedit_pageseg_mode: language.value.startsWith('sorani') ? '6' : '11',
                    preserve_interword_spaces: '1',
                    user_defined_dpi: '300',
                });
                return localWorker.recognize(canvas);
            })();
            const result = await Promise.race([task, new Promise((_, reject) => {
                timeout = setTimeout(() => reject(new Error('Scanning took too long. Try a smaller photo or the server scanner.')), 120000);
            })]);
            if (run !== generation || !result) return;
            const text = result.data.text.trim();
            const suggested = parseBusinessCard(text);
            form.reset();
            syncCompanyChoice();
            for (const [key, value] of Object.entries(suggested)) {
                if (form.elements.namedItem(key)) form.elements.namedItem(key).value = value;
            }
            get('[data-raw]').textContent = text;
            newCompanyName.value = suggested.company_name;
            const existingCompany = [...companySelect.options].find(option => option.text.trim().toLocaleLowerCase() === suggested.company_name.trim().toLocaleLowerCase());
            if (existingCompany) companySelect.value = existingCompany.value;
            get('[data-company-hint]').textContent = suggested.company_name ? `Card company: ${suggested.company_name}. Choose the CRM destination below.` : 'Choose the company this contact belongs to.';
            form.hidden = false;
            progress.textContent = text ? 'Scan complete. Check every field before saving.' : 'No readable text found. Retake the photo or enter the contact details below.';
        } catch (error) {
            if (run === generation) {
                progress.textContent = error instanceof Error
                    ? error.message
                    : (String(error) || 'The image could not be read. Try another photo or the server scanner.');
                generation++;
                busy(false);
            }
        } finally {
            clearTimeout(timeout);
            if (localWorker) await localWorker.terminate();
            if (worker === localWorker) worker = null;
            if (canvas) { canvas.width = 0; canvas.height = 0; }
            if (run === generation) busy(false);
        }
    });
    on(form, 'submit', async event => {
        event.preventDefault();
        if (saving || scanning || !form.reportValidity()) return;
        saving = true;
        saveButton.disabled = true;
        scanButton.disabled = true;
        get('[data-save-error]').textContent = '';
        // Explicit allowlist: the image, OCR text, and token never enter the body.
        const payload = Object.fromEntries(['company_id', 'new_company_name', 'first_name', 'last_name', 'job_title', 'email', 'phone', 'notes', 'status']
            .map(key => [key, form.elements.namedItem(key).value]));
        payload.create_company = createCompany.checked;
        if (payload.create_company) payload.company_id = null;
        try {
            const response = await fetch(form.action, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': form.elements.namedItem('_token').value },
                body: JSON.stringify(payload),
            });
            const result = await response.json();
            if (!response.ok) throw new Error(Object.values(result.errors ?? {}).flat().join(' ') || result.message || 'Contact could not be saved.');
            clear();
            window.location.assign(result.redirect);
        } catch (error) {
            get('[data-save-error]').textContent = error.message || 'Could not save. Check your connection before trying again.';
        } finally {
            saving = false;
            saveButton.disabled = false;
            scanButton.disabled = !imageUrl;
        }
    });
    const dispose = () => { clear(); events.abort(); delete root.dataset.initialized; };
    on(document, 'livewire:navigating', dispose);
    on(window, 'pagehide', dispose);
    return { clear, dispose };
}

document.addEventListener('DOMContentLoaded', () => initializeCardScanner());
document.addEventListener('livewire:navigated', () => initializeCardScanner());
window.addEventListener('pageshow', () => initializeCardScanner());

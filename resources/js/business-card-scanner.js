import { parseBusinessCard } from './business-card-parser';

export function initializeCardScanner(root = document.querySelector('[data-card-scanner]')) {
    if (!root || root.dataset.initialized) return;
    root.dataset.initialized = 'true';
    const get = selector => root.querySelector(selector);
    const form = get('[data-review]');
    const progress = get('[data-progress]');
    const scanButton = get('[data-scan]');
    const saveButton = get('[data-save]');
    const language = get('[data-language]');
    const companySelect = get('[data-company-select]');
    const createCompany = get('[data-create-company]');
    const newCompany = get('[data-new-company]');
    const newCompanyName = get('[data-new-company-name]');
    const sides = [
        { key: 'front', label: 'first side', preview: get('[data-preview]'), inputs: [get('[data-image]'), get('[data-camera]')], imageUrl: null },
        { key: 'back', label: 'other side', preview: get('[data-preview-back]'), inputs: [get('[data-image-back]'), get('[data-camera-back]')], imageUrl: null },
    ].filter(side => side.preview && side.inputs.every(Boolean));
    const imageInputs = sides.flatMap(side => side.inputs);
    const events = new AbortController();
    let worker = null;
    let generation = 0;
    let scanning = false;
    let saving = false;
    const hasImage = () => sides.some(side => side.imageUrl);

    const syncCompanyChoice = () => {
        const creating = createCompany.checked;
        companySelect.disabled = creating;
        companySelect.required = !creating;
        newCompany.hidden = !creating;
        newCompanyName.required = creating;
    };

    const busy = value => {
        scanning = value;
        scanButton.disabled = value || !hasImage();
        language.disabled = value;
        imageInputs.forEach(input => { input.disabled = value; });
    };
    const clear = () => {
        generation++;
        if (worker) { void worker.terminate(); worker = null; }
        sides.forEach(side => {
            if (side.imageUrl) URL.revokeObjectURL(side.imageUrl);
            side.imageUrl = null;
            side.preview.removeAttribute('src');
            side.preview.hidden = true;
        });
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
    const resetReview = () => {
        form.reset();
        syncCompanyChoice();
        form.hidden = true;
        get('[data-raw]').textContent = '';
        get('[data-company-hint]').textContent = '';
        get('[data-save-error]').textContent = '';
    };
    const select = (side, file) => {
        if (saving) return;
        if (!file) return;
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 10 * 1024 * 1024) {
            progress.textContent = 'Choose a JPG, PNG, or WebP image smaller than 10 MB.';
            return;
        }
        generation++;
        if (worker) { void worker.terminate(); worker = null; }
        if (side.imageUrl) URL.revokeObjectURL(side.imageUrl);
        side.imageUrl = URL.createObjectURL(file);
        side.preview.src = side.imageUrl;
        side.preview.hidden = false;
        side.inputs.forEach(input => {
            if (input.files?.[0] !== file) input.value = '';
        });
        resetReview();
        progress.textContent = `${side.label[0].toUpperCase()}${side.label.slice(1)} ready.`;
        scanButton.disabled = false;
    };
    const on = (element, event, handler) => element.addEventListener(event, handler, { signal: events.signal });
    sides.forEach(side => {
        side.inputs.forEach(input => on(input, 'change', () => select(side, input.files[0])));
        const dropZone = root.querySelector(`[data-drop-zone][data-card-side="${side.key}"]`);
        on(dropZone, 'dragover', event => event.preventDefault());
        on(dropZone, 'drop', event => { event.preventDefault(); if (!scanning) select(side, event.dataTransfer.files[0]); });
    });
    on(get('[data-clear]'), 'click', () => { if (!saving) clear(); });
    on(createCompany, 'change', syncCompanyChoice);
    on(scanButton, 'click', async () => {
        if (!hasImage() || scanning || saving) return;
        const run = ++generation;
        let localWorker = null;
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
                await localWorker.setParameters({
                    tessedit_pageseg_mode: language.value.startsWith('sorani') ? '6' : '11',
                    preserve_interword_spaces: '1',
                    user_defined_dpi: '300',
                });
                const selectedSides = sides.filter(side => side.imageUrl);
                const extracted = [];
                for (let sideIndex = 0; sideIndex < selectedSides.length; sideIndex++) {
                    const side = selectedSides[sideIndex];
                    await side.preview.decode();
                    if (run !== generation) return null;
                    const angles = side.preview.naturalHeight > side.preview.naturalWidth
                        ? [90, 270, 0, 180]
                        : [0, 180, 90, 270];
                    let best = { text: '', score: -Infinity };
                    for (let angleIndex = 0; angleIndex < angles.length; angleIndex++) {
                        if (run !== generation) return null;
                        progress.textContent = `Reading ${side.label} (${sideIndex + 1} of ${selectedSides.length}), orientation ${angleIndex + 1}…`;
                        const canvas = prepareCanvas(side.preview, angles[angleIndex]);
                        try {
                            const result = await localWorker.recognize(canvas);
                            const candidate = scoreRecognition(result);
                            if (candidate.score > best.score) best = candidate;
                            // Compare both directions on the likely axis before accepting OCR.
                            if (best.usable && angleIndex >= 1) break;
                        } finally {
                            canvas.width = 0;
                            canvas.height = 0;
                        }
                    }
                    extracted.push({ label: side.label, text: best.text });
                }
                return extracted;
            })();
            const result = await Promise.race([task, new Promise((_, reject) => {
                timeout = setTimeout(() => reject(new Error('Scanning took too long. Try smaller photos, scan one side at a time, or use the server scanner.')), 180000);
            })]);
            if (run !== generation || !result) return;
            const text = result.map(item => item.text).filter(Boolean).join('\n').trim();
            const suggested = parseBusinessCard(text);
            form.reset();
            syncCompanyChoice();
            for (const [key, value] of Object.entries(suggested)) {
                if (form.elements.namedItem(key)) form.elements.namedItem(key).value = value;
            }
            get('[data-raw]').textContent = result
                .filter(item => item.text)
                .map(item => result.length > 1 ? `${item.label[0].toUpperCase()}${item.label.slice(1)}:\n${item.text}` : item.text)
                .join('\n\n');
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
            scanButton.disabled = !hasImage();
        }
    });
    const dispose = () => { clear(); events.abort(); delete root.dataset.initialized; };
    on(document, 'livewire:navigating', dispose);
    on(window, 'pagehide', dispose);
    return { clear, dispose };
}

function prepareCanvas(image, angle) {
    // Bound OCR memory on phones; the original photo remains untouched.
    const scale = Math.min(1, 2000 / Math.max(image.naturalWidth, image.naturalHeight));
    const sourceWidth = Math.max(1, Math.round(image.naturalWidth * scale));
    const sourceHeight = Math.max(1, Math.round(image.naturalHeight * scale));
    const sideways = angle % 180 !== 0;
    const canvas = document.createElement('canvas');
    canvas.width = sideways ? sourceHeight : sourceWidth;
    canvas.height = sideways ? sourceWidth : sourceHeight;
    const context = canvas.getContext('2d', { willReadFrequently: true });
    context.fillStyle = '#fff';
    context.fillRect(0, 0, canvas.width, canvas.height);
    context.translate(canvas.width / 2, canvas.height / 2);
    context.rotate(angle * Math.PI / 180);
    context.drawImage(image, -sourceWidth / 2, -sourceHeight / 2, sourceWidth, sourceHeight);
    context.setTransform(1, 0, 0, 1, 0, 0);

    const pixels = context.getImageData(0, 0, canvas.width, canvas.height);
    let luminance = 0;
    let samples = 0;
    for (let offset = 0; offset < pixels.data.length; offset += 64) {
        luminance += pixels.data[offset] * 0.299 + pixels.data[offset + 1] * 0.587 + pixels.data[offset + 2] * 0.114;
        samples++;
    }
    const invert = samples > 0 && luminance / samples < 120;
    for (let offset = 0; offset < pixels.data.length; offset += 4) {
        const gray = pixels.data[offset] * 0.299 + pixels.data[offset + 1] * 0.587 + pixels.data[offset + 2] * 0.114;
        let enhanced = Math.max(0, Math.min(255, 128 + (gray - 128) * 1.35));
        if (invert) enhanced = 255 - enhanced;
        pixels.data[offset] = enhanced;
        pixels.data[offset + 1] = enhanced;
        pixels.data[offset + 2] = enhanced;
    }
    context.putImageData(pixels, 0, 0);
    return canvas;
}

function scoreRecognition(result) {
    const text = result.data.text.trim();
    const meaningfulCharacters = (text.match(/[\p{L}\p{N}]/gu) ?? []).length;
    const words = (text.match(/[\p{L}\p{N}]{2,}/gu) ?? []).length;
    const contactSignals = [/@/, /(?:https?:\/\/|www\.)/i, /(?:\+?\d[\d ()-]{6,}\d)/].filter(pattern => pattern.test(text)).length;
    const confidence = Number(result.data.confidence) || 0;
    return {
        text,
        score: confidence + Math.min(meaningfulCharacters, 120) * 0.2 + contactSignals * 15,
        usable: meaningfulCharacters >= 10 && (
            (contactSignals >= 2 && confidence >= 30)
            || (contactSignals > 0 && confidence >= 55)
            || (words >= 3 && confidence >= 85)
        ),
    };
}

document.addEventListener('DOMContentLoaded', () => initializeCardScanner());
document.addEventListener('livewire:navigated', () => initializeCardScanner());
window.addEventListener('pageshow', () => initializeCardScanner());

// Run after npm run build. Set PLAYWRIGHT_MODULE to an installed Playwright entrypoint.
import { createServer } from 'node:http';
import { readFile } from 'node:fs/promises';
import { resolve, extname, sep } from 'node:path';
import assert from 'node:assert/strict';
import { pathToFileURL } from 'node:url';

const { chromium } = await import(pathToFileURL(process.env.PLAYWRIGHT_MODULE).href);
const manifest = JSON.parse(await readFile('public/build/manifest.json', 'utf8'));
const script = manifest['resources/js/app.js'].file;
const fields = ['company_id', 'first_name', 'last_name', 'job_title', 'email', 'phone', 'notes', 'status', '_token'];
const html = `<div data-card-scanner data-assets="/ocr/v7-1"><div data-drop-zone>
<img data-preview hidden><input type="file" data-image><input type="file" data-camera>
<select data-language><option value="eng">English</option><option value="kur">Sorani</option><option value="eng+ara">Arabic</option><option value="kmr+eng">Kurmanji</option></select>
<button data-scan disabled>Scan</button><button data-clear>Clear</button><p data-progress></p></div>
<form data-review hidden action="/contacts">${fields.map(name => `<input name="${name}" value="${name === 'status' ? 'active' : ''}">`).join('')}
<p data-company-hint></p><pre data-raw></pre><p data-save-error></p><button data-save>Save</button></form></div>
<script type="module" src="/build/${script}"></script>`;
const writes = [];
const server = createServer(async (req, res) => {
    if (req.method === 'POST') {
        let body = ''; for await (const part of req) body += part;
        writes.push(JSON.parse(body));
        res.writeHead(201, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ redirect: '/saved' })); return;
    }
    if (req.url === '/' || req.url === '/saved') { res.end(req.url === '/' ? html : 'Saved'); return; }
    const path = resolve('public', `.${req.url}`);
    if (!path.startsWith(resolve('public') + sep)) { res.writeHead(404).end(); return; }
    try {
        const bytes = await readFile(path);
        res.setHeader('Content-Type', ({ '.js': 'text/javascript', '.wasm': 'application/wasm', '.gz': 'application/gzip' })[extname(path)] ?? 'application/octet-stream');
        res.end(bytes);
    } catch { res.writeHead(404).end(); }
});
await new Promise(done => server.listen(0, '127.0.0.1', done));
let browser;
try {
    browser = await chromium.launch({ channel: process.env.BROWSER_CHANNEL || 'msedge', headless: true });
    const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    const origin = `http://127.0.0.1:${server.address().port}`;
    await page.route('**/*', route => {
        if (!route.request().url().startsWith(origin)) throw new Error('Unexpected external request: ' + route.request().url());
        return route.continue();
    });
    await page.goto(origin);
    const data = await page.evaluate(() => {
        const canvas = document.createElement('canvas'); canvas.width = 1000; canvas.height = 450;
        const ctx = canvas.getContext('2d'); ctx.fillStyle = 'white'; ctx.fillRect(0, 0, 1000, 450);
        ctx.fillStyle = 'black'; ctx.font = '40px Arial';
        ['Jane Smith', 'Sales Director', 'Example Trading', 'jane@example.com', '+964 750 123 4567'].forEach((line, index) => ctx.fillText(line, 40, 65 + index * 70));
        return canvas.toDataURL().split(',')[1];
    });
    await page.locator('[data-image]').setInputFiles({ name: 'test.png', mimeType: 'image/png', buffer: Buffer.from(data, 'base64') });
    await page.locator('[data-scan]').click();
    await page.locator('[data-review]').waitFor({ state: 'visible', timeout: 120000 });
    assert.equal(await page.locator('[name=first_name]').inputValue(), 'Jane');
    assert.equal(await page.locator('[name=email]').inputValue(), 'jane@example.com');
    assert.equal(writes.length, 0, 'OCR must not upload anything');
    await page.route('**/contacts', route => route.fulfill({ status: 422, contentType: 'application/json', body: JSON.stringify({ errors: { company_id: ['Choose a valid company.'] } }) }));
    await page.locator('[data-save]').click();
    await page.locator('[data-save-error]').filter({ hasText: 'Choose a valid company.' }).waitFor();
    assert.equal(await page.locator('[name=first_name]').inputValue(), 'Jane');
    assert.ok(await page.locator('[data-preview]').getAttribute('src'), 'Validation failure keeps the local photo for review');
    await page.unroute('**/contacts');
    await page.locator('[name=company_id]').fill('1');
    await page.locator('[name=first_name]').fill('Reviewed');
    await page.locator('[data-save]').click();
    await page.waitForURL('**/saved');
    assert.equal(writes.length, 1);
    assert.equal(writes[0].first_name, 'Reviewed');
    assert.deepEqual(Object.keys(writes[0]).sort(), fields.filter(key => key !== '_token').sort());
    assert.equal(errors.length, 0, errors.join('\n'));
    console.log('PASS: real browser OCR, phone viewport, local assets, review/save payload.');

    await page.goto(origin);
    await page.locator('[data-image]').setInputFiles({ name: 'test.png', mimeType: 'image/png', buffer: Buffer.from(data, 'base64') });
    await page.locator('[data-scan]').click();
    await page.locator('[data-clear]').click();
    assert.equal(await page.locator('[data-preview]').getAttribute('src'), null);
    assert.equal(await page.locator('[data-review]').isVisible(), false);
    assert.equal(await page.locator('[data-image]').inputValue(), '');
    console.log('PASS: cancel clears photo and review.');
    for (const language of ['kur', 'eng+ara', 'kmr+eng']) {
        await page.goto(origin);
        await page.locator('[data-language]').selectOption(language);
        await page.locator('[data-image]').setInputFiles({ name: 'test.png', mimeType: 'image/png', buffer: Buffer.from(data, 'base64') });
        await page.locator('[data-scan]').click();
        await page.waitForFunction(() => !document.querySelector('[data-review]').hidden || /could not|too long|Error|failed/i.test(document.querySelector('[data-progress]').textContent), null, { timeout: 120000 });
        assert.equal(await page.locator('[data-review]').isVisible(), true, await page.locator('[data-progress]').textContent());
        console.log(`PASS: ${language} model loads and processes in browser.`);
    }
} finally {
    await browser?.close();
    await new Promise(done => server.close(done));
}

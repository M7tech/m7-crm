import { copyFile, mkdir, readdir, access } from 'node:fs/promises';
import { resolve } from 'node:path';

// Ship engine and models with the app. Scanning never depends on a public CDN.
const target = resolve('public/ocr/v7-2');
await mkdir(target, { recursive: true });
await copyFile('node_modules/tesseract.js/dist/worker.min.js', `${target}/worker.min.js`);
for (const name of await readdir('node_modules/tesseract.js-core')) {
    if (/\.wasm(?:\.js)?$/.test(name)) {
        await copyFile(`node_modules/tesseract.js-core/${name}`, `${target}/${name}`);
    }
}
for (const language of ['eng', 'ara', 'kmr']) {
    const root = `node_modules/@tesseract.js-data/${language}`;
    // Prefer the integerized high-accuracy model when a package provides it.
    let model = `${root}/4.0.0_best_int/${language}.traineddata.gz`;
    try { await access(model); } catch { model = `${root}/4.0.0/${language}.traineddata.gz`; }
    await copyFile(model, `${target}/${language}.traineddata.gz`);
}
await copyFile('resources/ocr/sorani.traineddata.gz', `${target}/sorani.traineddata.gz`);
console.log('Browser OCR assets prepared.');

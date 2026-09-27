'use strict';
const fs = require('node:fs');
const path = require('node:path');
const sharp = require('sharp');
const { createWorker, PSM } = require('tesseract.js');
const language = require('@tesseract.js-data/eng');
const { parseTSV } = require('./stamp-parser.cjs');

async function extract(file) {
    // PHP supplies a server-generated file path. No URL or OCR text is accepted.
    const root = fs.realpathSync(path.join(__dirname, '../../uploads/reports'));
    const absolute = fs.realpathSync(file);
    if (!absolute.startsWith(root + path.sep)) throw new Error('invalid_path');
    const original = fs.readFileSync(absolute);
    if (original.length > 6 * 1024 * 1024) throw new Error('image_size');
    const image = sharp(original, { limitInputPixels: 50000000 }).rotate();
    const oriented = await image.png().toBuffer();
    const meta = await sharp(oriented).metadata();
    const worker = await createWorker('eng', 1, { langPath: language.langPath, gzip: true, cacheMethod: 'none', logger: () => {} });
    try {
        // One OCR engine per request, reused for likely lower stamp and whole-image fallback.
        const attempts = [
            { top: Math.floor(meta.height * 0.5), height: meta.height - Math.floor(meta.height * 0.5), psm: PSM.SPARSE_TEXT, gray: false },
            { top: 0, height: meta.height, psm: PSM.SPARSE_TEXT, gray: true },
            { top: 0, height: meta.height, psm: PSM.AUTO, gray: false }
        ];
        let last = { ok: false, reason: 'unreadable' };
        for (const attempt of attempts) {
            let processed = sharp(oriented).extract({ left: 0, top: attempt.top, width: meta.width, height: attempt.height }).resize({ width: Math.min(3200, Math.max(1800, meta.width)), fit: 'inside' });
            if (attempt.gray) processed = processed.grayscale().normalize().sharpen();
            await worker.setParameters({ tessedit_pageseg_mode: attempt.psm, preserve_interword_spaces: '1' });
            const { data } = await worker.recognize(await processed.png().toBuffer(), {}, { text: true, tsv: true });
            last = parseTSV(data.tsv);
            if (last.ok) return last;
        }
        return last;
    } finally { await worker.terminate(); }
}

if (require.main === module) {
    const timeout = setTimeout(() => { process.stdout.write(JSON.stringify({ ok: false, reason: 'timeout' })); process.exit(1); }, 55000);
    extract(process.argv[2]).then(result => { clearTimeout(timeout); process.stdout.write(JSON.stringify(result)); }).catch(() => { clearTimeout(timeout); process.stdout.write(JSON.stringify({ ok: false, reason: 'ocr_unavailable' })); process.exitCode = 1; });
}
module.exports = { extract };

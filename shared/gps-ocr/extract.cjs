'use strict';
const fs = require('node:fs');
const path = require('node:path');
const sharp = require('sharp');
const { createWorker, PSM } = require('tesseract.js');
const language = require('@tesseract.js-data/eng');
const { parseTSV } = require('./stamp-parser.cjs');

function gpsFieldScore(text) {
    const value = String(text || '');
    let score = 0;
    const dateMatch = value.match(/\b(\d{1,2})[\/-](\d{1,2})[\/-](\d{4})\b/);
    if (dateMatch) {
        const first = Number(dateMatch[1]), second = Number(dateMatch[2]), year = Number(dateMatch[3]);
        const candidates = first > 12 ? [[second, first]] : second > 12 ? [[first, second]] : [[first, second], [second, first]];
        if (year >= 1900 && year <= 2100 && candidates.some(([month, day]) => {
            const date = new Date(Date.UTC(year, month - 1, day));
            return month >= 1 && month <= 12 && day >= 1 && date.getUTCMonth() === month - 1 && date.getUTCDate() === day;
        })) score++;
    }
    const timeMatch = value.match(/\b(\d{1,2}):(\d{2})(?::(\d{2}))?\s*(AM|PM)?\b/i);
    if (timeMatch && Number(timeMatch[2]) <= 59 && (timeMatch[3] === undefined || Number(timeMatch[3]) <= 59)
        && (timeMatch[4] ? Number(timeMatch[1]) >= 1 && Number(timeMatch[1]) <= 12 : Number(timeMatch[1]) <= 23)) score++;
    const coordinate = (label, min, max) => {
        const match = value.match(label);
        if (!match) return false;
        const number = Number(match[1].replace(/O/gi, '0').replace(/[Il]/g, '1'));
        return Number.isFinite(number) && number >= min && number <= max;
    };
    if (coordinate(/\bLat(?:itude)?\s*[:=]?\s*([-+]?[0-9OIl]{1,3}\.[0-9OIl]{1,9})/i, -90, 90)) score++;
    if (coordinate(/\b(?:Long(?:itude)?|Lon)\s*[:=]?\s*([-+]?[0-9OIl]{1,3}\.[0-9OIl]{1,9})/i, -180, 180)) score++;
    const contentLines = value.replace(/\r/g, '').split('\n').map(line => line.trim()).filter(Boolean);
    if (contentLines.some(line => /[A-Za-z]{3}/.test(line) && !/\b(?:Lat(?:itude)?|Long(?:itude)?|Lon|GMT|UTC)\s*[:=]?/i.test(line) && !/\d{1,2}[\/-]\d{1,2}[\/-]\d{4}/.test(line))) score++;
    return score;
}

async function extract(file, options = {}) {
    // PHP supplies a server-generated file path. No URL or OCR text is accepted.
    const root = fs.realpathSync(path.join(__dirname, '../../uploads/reports'));
    const absolute = fs.realpathSync(file);
    if (!absolute.startsWith(root + path.sep)) throw new Error('invalid_path');
    const original = fs.readFileSync(absolute);
    if (original.length > 6 * 1024 * 1024) throw new Error('image_size');
    const image = sharp(original, { limitInputPixels: 50000000 }).rotate();
    const oriented = await image.png().toBuffer();
    const meta = await sharp(oriented).metadata();
    let worker = null;
    let timeout = null;
    try {
        worker = await createWorker('eng', 1, { langPath: language.langPath, gzip: true, cacheMethod: 'none', logger: () => {} });
        // One OCR engine per request. Focused bottom-stamp attempts run first;
        // the established broad attempts remain as fallbacks for older layouts.
        const attempts = [
            { name: 'bottom-25-threshold-block', top: Math.floor(meta.height * 0.75), height: meta.height - Math.floor(meta.height * 0.75), psm: PSM.SINGLE_BLOCK, scale: 3, treatment: 'threshold' },
            { name: 'bottom-35-normalized-block', top: Math.floor(meta.height * 0.65), height: meta.height - Math.floor(meta.height * 0.65), psm: PSM.SINGLE_BLOCK, scale: 3, treatment: 'normalized' },
            { name: 'bottom-half-sparse', top: Math.floor(meta.height * 0.5), height: meta.height - Math.floor(meta.height * 0.5), psm: PSM.SPARSE_TEXT, scale: 1, treatment: 'color' },
            { name: 'full-normalized-sparse', top: 0, height: meta.height, psm: PSM.SPARSE_TEXT, scale: 1, treatment: 'normalized' },
            { name: 'full-auto', top: 0, height: meta.height, psm: PSM.AUTO, scale: 1, treatment: 'color' }
        ];
        const recognize = async () => {
            let best = { score: -1, result: { ok: false, reason: 'unreadable' } };
            for (const [index, attempt] of attempts.entries()) {
                const targetWidth = attempt.scale > 1
                    ? Math.min(4800, Math.round(meta.width * attempt.scale))
                    : Math.min(3200, Math.max(1800, meta.width));
                let processed = sharp(oriented)
                    .extract({ left: 0, top: attempt.top, width: meta.width, height: attempt.height })
                    .resize({ width: targetWidth, fit: 'inside' });
                if (attempt.treatment === 'normalized') processed = processed.grayscale().normalize().sharpen();
                if (attempt.treatment === 'threshold') processed = processed.grayscale().normalize().sharpen().threshold(150).negate();
                await worker.setParameters({ tessedit_pageseg_mode: attempt.psm, preserve_interword_spaces: '1' });
                const { data } = await worker.recognize(await processed.png().toBuffer(), {}, { text: true, tsv: true });
                const result = parseTSV(data.tsv);
                const score = gpsFieldScore(data.text);
                if (score > best.score) best = { score, result };
                if (typeof options.onAttempt === 'function') {
                    options.onAttempt({ index: index + 1, name: attempt.name, text: data.text || '', score, result });
                }
                if (result.ok) return result;
            }
            return best.result;
        };
        const timeoutFailure = new Promise((resolve, reject) => {
            timeout = setTimeout(() => {
                const error = new Error('ocr_timeout');
                error.code = 'OCR_TIMEOUT';
                reject(error);
            }, 55000);
        });
        return await Promise.race([recognize(), timeoutFailure]);
    } finally {
        if (timeout) clearTimeout(timeout);
        if (worker) await worker.terminate().catch(() => {});
    }
}

if (require.main === module) {
    const diagnose = process.argv.includes('--diagnose');
    extract(process.argv[2], diagnose ? {
        onAttempt: attempt => {
            process.stderr.write(`Attempt ${attempt.index}: ${attempt.name}\n`);
            process.stderr.write(`Raw OCR:\n${attempt.text.trim()}\n`);
            process.stderr.write(`GPS-field score: ${attempt.score}/5\n`);
            process.stderr.write(`Parser: ${JSON.stringify(attempt.result)}\n`);
        }
    } : {})
        .then(result => { process.stdout.write(JSON.stringify(result)); })
        .catch(error => {
            process.stdout.write(JSON.stringify({ ok: false, reason: error && error.code === 'OCR_TIMEOUT' ? 'timeout' : 'ocr_unavailable' }));
            process.exitCode = 1;
        });
}
module.exports = { extract };

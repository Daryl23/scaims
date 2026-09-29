'use strict';

// Date order is resolved only from the stamp itself; never infer it from farm/profile data.
const pad = n => String(n).padStart(2, '0');
function time12(value) {
    const match = /^(\d{2}):(\d{2})(?::(\d{2}))?$/.exec(value || '');
    if (!match) return '';
    const h = Number(match[1]);
    return `${pad(h % 12 || 12)}:${match[2]}${match[3] === undefined ? '' : ':' + match[3]} ${h >= 12 ? 'PM' : 'AM'}`;
}

function parseStamp(text, confidenceLines = []) {
    const lines = String(text || '').replace(/\r/g, '').split('\n').map(s => s.trim()).filter(Boolean);
    const fail = reason => ({ ok: false, reason });
    // The client's GPS Camera layout is identifiable by all three markers. For
    // ambiguous dates (both values <= 12), that layout is DD/MM; all other
    // ambiguous layouts retain the historical MM/DD behavior.
    const gpsCameraDmyLayout = lines.some(line => /\b(?:GMT|UTC)\s*[+-]\s*\d{1,2}(?::\d{2})?\b/i.test(line))
        && lines.some(line => /\bLat(?:itude)?\s*[:=]/i.test(line))
        && lines.some(line => /\b(?:Long(?:itude)?|Lon)\s*[:=]/i.test(line));
    const datePattern = /\b(\d{1,2})[\/-](\d{1,2})[\/-](\d{4})\s+(\d{1,2}):(\d{2})(?::(\d{2}))?\s*(AM|PM)?(?:\s*((?:GMT|UTC)\s*[+-]\s*\d{1,2}(?::\d{2})?))?\b/ig;
    const dates = [];
    lines.forEach((line, index) => {
        for (const m of line.matchAll(datePattern)) {
            let [first, dateSecond, year, hour, minute] = m.slice(1, 6).map(Number);
            let month;
            let day;
            if (first > 12 && dateSecond <= 12) {
                day = first; month = dateSecond;
            } else if (dateSecond > 12 && first <= 12) {
                month = first; day = dateSecond;
            } else if (first <= 12 && dateSecond <= 12) {
                if (gpsCameraDmyLayout) { day = first; month = dateSecond; }
                else { month = first; day = dateSecond; }
            } else {
                dates.push({ invalid: true }); continue;
            }
            const second = m[6] === undefined ? null : Number(m[6]);
            const ampm = m[7] && m[7].toUpperCase();
            const date = new Date(Date.UTC(year, month - 1, day));
            if (year < 1900 || year > 2100 || month < 1 || month > 12 || day < 1 || date.getUTCDate() !== day || date.getUTCMonth() !== month - 1 || minute > 59 || (second !== null && second > 59) || (ampm ? hour < 1 || hour > 12 : hour > 23)) {
                dates.push({ invalid: true }); continue;
            }
            if (ampm) hour = hour % 12 + (ampm === 'PM' ? 12 : 0);
            dates.push({ index, date: `${year}-${pad(month)}-${pad(day)}`, time: `${pad(hour)}:${pad(minute)}${second === null ? '' : ':' + pad(second)}`, timezone: m[8] ? m[8].replace(/\s/g, '').toUpperCase() : null });
        }
    });
    if (!dates.length || dates.some(d => d.invalid)) return fail('date_time');
    if (new Set(dates.map(d => `${d.date}|${d.time}|${d.timezone}`)).size !== 1) return fail('ambiguous_date_time');

    // Character substitutions are confined to explicit decimal coordinate tokens.
    const number = '[-+]?[0-9OIl]{1,3}\\.[0-9OIl]{1,9}';
    const latitudePattern = new RegExp('\\b(?:Lat(?:itude)?)\\s*[:=]?\\s*(' + number + ')(?![\\d.])', 'ig');
    const longitudePattern = new RegExp('\\b(?:Long(?:itude)?|Lon)\\s*[:=]?\\s*(' + number + ')(?![\\d.])', 'ig');
    const pairPattern = new RegExp('(?:^|\\s)(' + number + ')\\s*,\\s*(' + number + ')(?![\\d.])', 'g');
    const lat = [], lon = [], coordinateLines = new Set();
    const decimal = token => Number(token.replace(/O/g, '0').replace(/[Il]/g, '1'));
    lines.forEach((line, index) => {
        for (const m of line.matchAll(latitudePattern)) { lat.push(decimal(m[1])); coordinateLines.add(index); }
        for (const m of line.matchAll(longitudePattern)) { lon.push(decimal(m[1])); coordinateLines.add(index); }
        for (const m of line.matchAll(pairPattern)) { lat.push(decimal(m[1])); lon.push(decimal(m[2])); coordinateLines.add(index); }
    });
    if (!lat.length || !lon.length) return fail('coordinates');
    if (lat.some(n => !Number.isFinite(n) || n < -90 || n > 90) || lon.some(n => !Number.isFinite(n) || n < -180 || n > 180)) return fail('coordinate_range');
    if (new Set(lat).size !== 1 || new Set(lon).size !== 1) return fail('ambiguous_coordinates');

    // Address is the contiguous text block beside the stamp, not arbitrary scene text.
    const dateIndices = new Set(dates.map(d => d.index));
    const first = Math.min(...dateIndices, ...coordinateLines);
    const last = Math.max(...dateIndices, ...coordinateLines);
    if (last - first > 10) return fail('stamp_layout');
    const ignore = /^(?:GPS\s*(?:Map\s*)?Camera|GPS\s*Map|Google|Map data|©|GMT|UTC|Accuracy\s*:|Altitude\s*:|Speed\s*:|Heading\s*:|\+?\/?-?\s*\d+\s*m\b)/i;
    const address = [];
    for (let i = first; i <= Math.min(lines.length - 1, last + 3); i++) {
        if (dateIndices.has(i) || coordinateLines.has(i)) continue;
        const line = lines[i].replace(/^\s*(?:Address|Location)\s*:\s*/i, '').trim();
        if (ignore.test(line) || !/[A-Za-z]{3}/.test(line)) { if (i > last) break; continue; }
        // Outside the date/coordinate block, stop at unrelated telemetry or another timestamp.
        if (/\b\d{1,2}:\d{2}\b/.test(line) || /[<>{}]/.test(line)) break;
        address.push({ line, index: i });
    }
    if (!address.length) return fail('location');
    const location = address.map(a => a.line.replace(/[,;]\s*$/, '')).join(', ');
    if (location.length < 5 || location.length > 2000) return fail('location');
    const used = new Set([...dateIndices, ...coordinateLines, ...address.map(a => a.index)]);
    if (confidenceLines.length) {
        for (const index of used) {
            const confidence = confidenceLines[index];
            if (!confidence || confidence.mean < 65 || confidence.min < 35) return fail('low_confidence');
        }
    }
    return { ok: true, photo: { date: dates[0].date, time: dates[0].time, time_display: time12(dates[0].time), location, latitude: String(lat[0]), longitude: String(lon[0]), timezone: dates[0].timezone, version: 'tesseract7-mdY-v1' } };
}

function parseTSV(tsv) {
    const groups = new Map();
    String(tsv).split('\n').slice(1).forEach(row => {
        const cells = row.split('\t');
        if (cells[0] !== '5' || !cells[11]?.trim()) return;
        const key = cells.slice(1, 5).join(':');
        if (!groups.has(key)) groups.set(key, []);
        groups.get(key).push({ text: cells.slice(11).join('\t').trim(), confidence: Number(cells[10]) });
    });
    const groupsArray = [...groups.values()];
    return parseStamp(groupsArray.map(words => words.map(w => w.text).join(' ')).join('\n'), groupsArray.map(words => ({ mean: words.reduce((n, w) => n + w.confidence, 0) / words.length, min: Math.min(...words.map(w => w.confidence)) })));
}
module.exports = { parseStamp, parseTSV, time12 };

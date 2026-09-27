const assert = require('node:assert/strict');
const { parseStamp, time12 } = require('../shared/gps-ocr/stamp-parser.cjs');
const a = `09/09/2026 08:52 PM GMT+8
Garcia - Rosario - San Juan - Candelaria Road
Rosario Calabarzon
Lat: 13.841768
Long: 121.2782`;
const b = `09/09/2026 20:49:24
13.841767, 121.278198 ±20 m
Candelaria Road, Rosario, Calabarzon, Philippines`;
assert.deepEqual(parseStamp(a).photo, {date:'2026-09-09',time:'20:52',time_display:'08:52 PM',location:'Garcia - Rosario - San Juan - Candelaria Road, Rosario Calabarzon',latitude:'13.841768',longitude:'121.2782',timezone:'GMT+8',version:'tesseract7-mdY-v1'});
assert.equal(parseStamp(b).photo.time_display,'08:49:24 PM');
assert.equal(parseStamp(a.replace('09/09','04/05')).photo.date,'2026-04-05');
assert.equal(parseStamp(a.replace('09/09','12/31')).photo.date,'2026-12-31');
for(const [input,expected] of [['13:05:10','01:05:10 PM'],['08:52','08:52 AM'],['07:15','07:15 AM'],['00:00','12:00 AM'],['12:00:00','12:00:00 PM']]) assert.equal(time12(input),expected);
assert.equal(parseStamp(a.replace('08:52 PM','12:00 AM')).photo.time,'00:00');
assert.equal(parseStamp(a.replace('13.841768','138.41768')).ok,false);
assert.equal(parseStamp(a.replace('121.2782','181.2782')).ok,false);
assert.equal(parseStamp(a.replace('09/09/2026','02/30/2026')).ok,false);
assert.equal(parseStamp(a.replace('08:52 PM','25:52')).ok,false);
assert.equal(parseStamp(a.replace('Long: 121.2782','')).ok,false);
assert.equal(parseStamp('09/09/2026 20:49:24\n13.841767, 121.278198 ±20 m').ok,false);
assert.equal(parseStamp(a+'\nLat: 14.0000').ok,false);
assert.equal(parseStamp(a.replace('Lat:','LAT:').replace('Long:','Longitude:')).ok,true);
assert.equal(parseStamp(a.replace('13.841768','I3.84l768')).photo.latitude,'13.841768');
assert.equal(parseStamp(a,[...Array(5)].map(()=>({mean:40,min:20}))).ok,false);
assert.equal(parseStamp(a.replace('09/09/2026','31/12/2026')).ok,false);
console.log('21 parser assertions passed; both formats, MDY dates, AM/PM/seconds, ranges, missing/ambiguous fields and confidence.');
module.exports = {a,b};

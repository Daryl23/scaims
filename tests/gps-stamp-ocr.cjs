const fs=require('node:fs'), path=require('node:path'), assert=require('node:assert/strict'), {spawnSync}=require('node:child_process');
const sharp=require('../shared/gps-ocr/node_modules/sharp');
const {extract}=require('../shared/gps-ocr/extract.cjs');
const {a,b,c}=require('./gps-stamp-parser.cjs');
(async()=>{
 const root=path.resolve(__dirname,'../uploads/reports/pending');fs.mkdirSync(root,{recursive:true});
 const files=[];
 try {
  const fatal=spawnSync(process.execPath,[path.resolve(__dirname,'../shared/gps-ocr/extract.cjs'),path.join(root,'missing-phase8a.png')],{encoding:'utf8'});
  assert.notEqual(fatal.status,0);assert.equal(fatal.stderr,'');assert.deepEqual(JSON.parse(fatal.stdout),{ok:false,reason:'ocr_unavailable'});
  for(const [name,text,top,expectedDate,expectedTime] of [['a',a,false,'2026-09-09','20:52'],['b',b,true,'2026-09-09','20:49:24'],['c',c,false,'2026-09-29','19:37']]) {
   const escaped=text.split('\n').map(s=>s.replace(/&/g,'&amp;').replace(/</g,'&lt;'));
   const start=top?70:770;
   const svg=`<svg xmlns="http://www.w3.org/2000/svg" width="1600" height="1100"><rect width="1600" height="1100" fill="#678846"/><rect x="0" y="${top?0:700}" width="1600" height="400" fill="white"/>${escaped.map((line,i)=>`<text x="45" y="${start+i*52}" font-family="Arial" font-size="32" fill="black">${line}</text>`).join('')}</svg>`;
   const file=path.join(root,'ocr-test-'+Date.now()+'-'+name+'.png');files.push(file);await sharp(Buffer.from(svg)).png().toFile(file);
   const before=fs.readFileSync(file);const result=await extract(file);console.log(name,JSON.stringify(result));assert.equal(result.ok,true);assert.equal(result.photo.date,expectedDate);assert.equal(result.photo.time,expectedTime);assert.deepEqual(fs.readFileSync(file),before);
   fs.mkdirSync(path.join(__dirname,'gps-photo-results'),{recursive:true});fs.copyFileSync(file,path.join(__dirname,'gps-photo-results','ocr-layout-'+name+'.png'));
  }
  const noise=Array.from({length:220},(_,i)=>`<circle cx="${(i*137)%2000}" cy="${(i*257)%3800}" r="${12+(i%47)}" fill="hsl(${(i*53)%360} 35% ${28+(i%42)}%)" opacity=".48"/>`).join('');
  const smallLines=c.split('\n').map(s=>s.replace(/&/g,'&amp;').replace(/</g,'&lt;'));
  const smallSvg=`<svg xmlns="http://www.w3.org/2000/svg" width="2000" height="4000"><defs><linearGradient id="photo" x2="1" y2="1"><stop stop-color="#315d42"/><stop offset=".48" stop-color="#9b8651"/><stop offset="1" stop-color="#31495b"/></linearGradient></defs><rect width="2000" height="4000" fill="url(#photo)"/>${noise}<path d="M0 3250 Q560 2600 1100 3320 T2000 3020 V4000 H0Z" fill="#24452f" opacity=".86"/><rect x="38" y="3822" width="1924" height="153" rx="8" fill="#101713" opacity=".78"/>${smallLines.map((line,i)=>`<text x="62" y="${3846+i*23}" font-family="Arial" font-size="10" fill="white">${line}</text>`).join('')}</svg>`;
  const smallFile=path.join(root,'ocr-test-'+Date.now()+'-small-bottom.jpg');files.push(smallFile);await sharp(Buffer.from(smallSvg)).jpeg({quality:90}).toFile(smallFile);
  const smallBefore=fs.readFileSync(smallFile),attempts=[];const smallResult=await extract(smallFile,{onAttempt:attempt=>attempts.push(attempt)});console.log('small-bottom',JSON.stringify(smallResult));if(!smallResult.ok) attempts.forEach(attempt=>console.error(`Attempt ${attempt.index} ${attempt.name}\n${attempt.text.trim()}\nParser: ${JSON.stringify(attempt.result)}`));assert.equal(smallResult.ok,true);assert.equal(attempts.length,2,'OCR should stop after the first complete focused result');assert.equal(attempts[1].name,'bottom-35-normalized-block');assert.equal(attempts[1].score,5);assert.deepEqual({date:smallResult.photo.date,time:smallResult.photo.time,location:smallResult.photo.location,latitude:smallResult.photo.latitude,longitude:smallResult.photo.longitude,timezone:smallResult.photo.timezone},{date:'2026-09-29',time:'19:37',location:'Garcia - Rosario - San Juan - Candelaria Road, Rosario Calabarzon',latitude:'13.841764',longitude:'121.27818',timezone:'GMT+8'});assert.deepEqual(fs.readFileSync(smallFile),smallBefore);fs.copyFileSync(smallFile,path.join(__dirname,'gps-photo-results','ocr-small-bottom.jpg'));
  console.log('Actual OCR passed for legacy Formats A/B, GPS Camera DD/MM Format C, and the small bottom-stamp fixture; original bytes preserved.');
 }finally{files.forEach(f=>fs.unlinkSync(f));}
})().catch(e=>{console.error(e);process.exitCode=1;});

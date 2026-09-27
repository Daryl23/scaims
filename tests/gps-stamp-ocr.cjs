const fs=require('node:fs'), path=require('node:path'), assert=require('node:assert/strict');
const sharp=require('../shared/gps-ocr/node_modules/sharp');
const {extract}=require('../shared/gps-ocr/extract.cjs');
const {a,b}=require('./gps-stamp-parser.cjs');
(async()=>{
 const root=path.resolve(__dirname,'../uploads/reports/pending');fs.mkdirSync(root,{recursive:true});
 const files=[];
 try {
  for(const [name,text,top] of [['a',a,false],['b',b,true]]) {
   const escaped=text.split('\n').map(s=>s.replace(/&/g,'&amp;').replace(/</g,'&lt;'));
   const start=top?70:770;
   const svg=`<svg xmlns="http://www.w3.org/2000/svg" width="1600" height="1100"><rect width="1600" height="1100" fill="#678846"/><rect x="0" y="${top?0:700}" width="1600" height="400" fill="white"/>${escaped.map((line,i)=>`<text x="45" y="${start+i*52}" font-family="Arial" font-size="32" fill="black">${line}</text>`).join('')}</svg>`;
   const file=path.join(root,'ocr-test-'+Date.now()+'-'+name+'.png');files.push(file);await sharp(Buffer.from(svg)).png().toFile(file);
   const before=fs.readFileSync(file);const result=await extract(file);console.log(name,JSON.stringify(result));assert.equal(result.ok,true);assert.equal(result.photo.date,'2026-09-09');assert.equal(result.photo.time,name==='a'?'20:52':'20:49:24');assert.deepEqual(fs.readFileSync(file),before);
   fs.mkdirSync(path.join(__dirname,'gps-photo-results'),{recursive:true});fs.copyFileSync(file,path.join(__dirname,'gps-photo-results','ocr-layout-'+name+'.png'));
  }
  console.log('Actual OCR passed for bottom Format A and top Format B, original bytes preserved.');
 }finally{files.forEach(f=>fs.unlinkSync(f));}
})().catch(e=>{console.error(e);process.exitCode=1;});

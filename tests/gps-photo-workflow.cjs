// Run with NODE_PATH pointing to Playwright. Uses a disposable database, never production records.
const { chromium } = require('playwright');
const assert = require('node:assert/strict');
const { spawn, execFileSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const root = path.resolve(__dirname, '..');
const php = process.env.PHP_BINARY || 'C:/xampp/php/php.exe';
const database = 'scaims_gps_test_' + Date.now();
const key = crypto.randomBytes(24).toString('hex');
const port = 8157, base = `http://127.0.0.1:${port}`;
const out = path.join(__dirname, 'gps-photo-results'); fs.mkdirSync(out, {recursive:true});
const fixture = (action) => execFileSync(php, [path.join(__dirname,'gps-photo-fixtures.php'),action,database], {cwd:root,encoding:'utf8'}).trim();
const rows = () => JSON.parse(fixture('rows'));
const farmerPath = '/FARMERS%20UI/farmersDashboard/report/';
const adminPath = '/ADMIN%20UI/adminDashboard/report/';
let server, browser, ready = false;
const checks = [];
function check(name) { checks.push(name); console.log('PASS ' + name); }
(async () => {
 try {
    fixture('setup'); ready = true;
    server = spawn(php, ['-d','upload_max_filesize=6M','-d','post_max_size=16M','-S',`127.0.0.1:${port}`,'-t',root,path.join(__dirname,'gps-photo-router.php')], {cwd:root,env:{...process.env,SCAIMS_GPS_TEST_DB:database,SCAIMS_GPS_TEST_KEY:key},windowsHide:true,stdio:['ignore','ignore','pipe']});
    let log = ''; server.stderr.on('data',d => { log += d.toString(); });
    for(let i=0;i<30;i++) { try { await fetch(base); break; } catch { await new Promise(r=>setTimeout(r,200)); } }
    browser = await chromium.launch({channel:'chrome',headless:true});
    const farmer = await browser.newContext({viewport:{width:1366,height:900}});
    const admin = await browser.newContext({viewport:{width:1366,height:900}});
    const other = await browser.newContext(); const anon = await browser.newContext();
    for(const [ctx,role] of [[farmer,'farmer'],[admin,'admin'],[other,'other']]) await ctx.request.get(base+'/__test/session?as='+role,{headers:{'X-Test-Key':key}});
    const page = await farmer.newPage(); let pageErrors = [];
    page.on('pageerror',e=>pageErrors.push(e.message));
    await page.goto(base+farmerPath+'report.php');
    const sharp=require('../shared/gps-ocr/node_modules/sharp');
    const png=fs.readFileSync(path.join(out,'ocr-layout-a.png'));
    const images={png,jpg:await sharp(png).jpeg().toBuffer(),webp:await sharp(fs.readFileSync(path.join(out,'ocr-layout-b.png'))).webp({quality:95}).toBuffer(),ddmmy:fs.readFileSync(path.join(out,'ocr-layout-c.png')),smallBottom:fs.readFileSync(path.join(out,'ocr-small-bottom.jpg'))};
    const stage=async(ctx,type='png')=>{const res=await ctx.request.post(base+'/shared/report_image_stage.php',{multipart:{report_image:image(type)},timeout:70000});const json=await res.json();assert.equal(json.success,true,JSON.stringify(json));return json.token;};
    const image = (type='png') => ({name:'untrusted-original.'+(type==='ddmmy'?'png':type==='smallBottom'?'jpg':type),mimeType:type==='jpg'||type==='smallBottom'?'image/jpeg':type==='ddmmy'?'image/png':'image/'+type,buffer:images[type]});
    const ddmmyResponse=await farmer.request.post(base+'/shared/report_image_stage.php',{multipart:{report_image:image('ddmmy')},timeout:70000});const ddmmyResult=await ddmmyResponse.json();assert.equal(ddmmyResult.success,true,JSON.stringify(ddmmyResult));assert.deepEqual({date:ddmmyResult.photo.date,time:ddmmyResult.photo.time,time_display:ddmmyResult.photo.time_display,location:ddmmyResult.photo.location,latitude:ddmmyResult.photo.latitude,longitude:ddmmyResult.photo.longitude,timezone:ddmmyResult.photo.timezone},{date:'2026-09-29',time:'19:37',time_display:'07:37 PM',location:'Garcia - Rosario - San Juan - Candelaria Road, Rosario Calabarzon',latitude:'13.841764',longitude:'121.27818',timezone:'GMT+8'});check('GPS Camera DD/MM image passes the PHP staging endpoint with normalized metadata');
    const smallResponse=await farmer.request.post(base+'/shared/report_image_stage.php',{multipart:{report_image:image('smallBottom')},timeout:70000});const smallResult=await smallResponse.json();assert.equal(smallResult.success,true,JSON.stringify(smallResult));assert.deepEqual({date:smallResult.photo.date,time:smallResult.photo.time,time_display:smallResult.photo.time_display,location:smallResult.photo.location,latitude:smallResult.photo.latitude,longitude:smallResult.photo.longitude,timezone:smallResult.photo.timezone},{date:'2026-09-29',time:'19:37',time_display:'07:37 PM',location:'Garcia - Rosario - San Juan - Candelaria Road, Rosario Calabarzon',latitude:'13.841764',longitude:'121.27818',timezone:'GMT+8'});check('Small bottom GPS Camera stamp passes the PHP staging endpoint');
    const input = page.locator('[data-report-image-input]');
    assert.equal(await page.locator('#report-type').isDisabled(),true);
    await input.setInputFiles(image()); await page.waitForFunction(()=>document.querySelector('[data-report-image-capture]').dataset.hasImage==='true');
    await page.locator('#report-type').selectOption('crop');
    assert.equal(await page.locator('#crop-report-fields').isVisible(),true);
    const originalUrl=await page.locator('.report-image-preview-wrap img').getAttribute('src');
    await page.locator('#report-type').selectOption('damage');
    assert.equal(await page.locator('.report-image-preview-wrap img').getAttribute('src'),originalUrl);
    await page.locator('[data-remove]').click();assert.equal(await page.locator('#report-type').isDisabled(),true);
    await input.setInputFiles(image('webp'));await page.waitForFunction(()=>document.querySelector('[data-report-image-capture]').dataset.hasImage==='true');
    assert.equal(await page.locator('[data-capture]').count(),0);
    await page.locator('.language-toggle-button[data-language="tl"]').click();
    assert.match(await page.locator('.report-image-header small').innerText(),/Mag-upload/);
    await page.locator('.language-toggle-button[data-language="en"]').click();
    check('Shared upload, replace/remove, report-type attachment retention, no built-in capture, EN/TL');
    await input.setInputFiles({name:'fake.png',mimeType:'image/png',buffer:Buffer.from('not an image')});
    await page.waitForFunction(()=>document.querySelector('.report-image-feedback').classList.contains('is-error'));
    assert.equal(await page.locator('#report-type').isDisabled(),true);
    check('Undisplayable image rejected by client');
    const blank=await sharp({create:{width:1000,height:700,channels:3,background:'#777'}}).png().toBuffer();
    await input.setInputFiles({name:'unstamped.png',mimeType:'image/png',buffer:blank});
    await page.waitForFunction(()=>document.querySelector('[data-report-image-capture]').getAttribute('aria-busy')==='true');
    assert.equal(await page.locator('#report-type').isDisabled(),true);
    await page.waitForFunction(()=>document.querySelector('.report-image-feedback').classList.contains('is-error'),{},{timeout:65000});
    assert.match(await page.locator('.report-image-feedback').innerText(),/could not be read/);
    assert.equal(await page.locator('input[data-pending-image-token]').inputValue(),'');
    assert.equal(await page.locator('#date-occurrence, #crop-location, #damage-location, [placeholder*=Waze]').count(),0);
    check('Unstamped photo fails real OCR before submission, loading blocks progression, no manual fallback');
    await page.goto(base+'/FARMERS%20UI/farmersDashboard/home/home.php');
    await page.locator('[data-report-image-input]').setInputFiles(image('jpg'));
    await page.locator('[data-home-report-type="crop"]').click();
    await page.waitForURL('**/report.php?**');
    assert.ok(await page.locator('input[data-pending-image-token]').inputValue());
    assert.equal(await page.locator('.report-image-preview-wrap img').evaluate(i=>i.naturalWidth),1600);
    await page.locator('#report-type').selectOption('damage'); await page.locator('#report-type').selectOption('crop');
    await page.locator('#date-planted').fill('2026-01-01');await page.locator('#harvest-date').fill('2026-09-01');
    await page.locator('#estimate-harvest').fill('50'); await page.locator('#farm-gate-price').fill('60');
    page.on('dialog',d=>d.accept());
    await page.locator('#crop-report-fields .report-submit-action').click();
    await page.waitForURL('**/report.php');
    assert.equal(rows().crop_reports.length,1);
    let row=rows().crop_reports[0]; assert.equal(Number(row.FarmerID),1);assert.equal(row.created_by,'gps-test-farmer');assert.ok(row.reportDate);
    assert.deepEqual(fs.readFileSync(path.join(root,row.image_path)),images.jpg);
    check('Farmer Home staging through real Harvest form saves original 1600px JPEG without EXIF');
    const cropFields={'report-type':'crop','crop-location':'adya','crop-commodity':'fruits','crop-type':'Mango','crop-variety':'Carabao','date-planted':'2026-01-01','harvest-date':'2026-09-01','estimate-harvest':'75','crop-status':'Bearing','farm-gate-price':'60','number-of-trees':'10'};
    const damageFields={'report-type':'damage','damage-location':'adya','damage-crop-commodity':'fruits','damage-crop-type':'Mango','damage-crop-variety':'Carabao','date-occurrence':'2026-09-01','damage-cause':'Typhoon','damage-percentage':'25','estimated-loss':'20','damage-farm-gate-price':'60','damage-number-of-trees':'2'};
    await farmer.request.post(base+farmerPath+'report_submit.php',{multipart:{...damageFields,'pending-report-image-token':await stage(farmer),'reporting-for':'2',latitude:'89',longitude:'1'}});
    row=rows().damage_reports[0]; assert.equal(Number(row.FarmerID),1); assert.deepEqual(fs.readFileSync(path.join(root,row.image_path)),images.png);
    assert.equal(row.photo_capture_date,'2026-09-09');assert.equal(row.date_occurrence,'2026-09-09');assert.equal(row.photo_capture_time,'20:52');assert.equal(Number(row.latitude),13.841768);assert.notEqual(row.dmgLocation,'adya');
    check('Farmer Damage saves photo values and ignores forged ownership/date/location/coordinates');
    const prefill=await (await admin.request.get(base+adminPath+'get_farmer_report_prefill.php?farmer_id=1')).json();
    assert.equal(prefill.prefill_by_type.crop.estimate_harvest,'');assert.equal(prefill.prefill_by_type.damage.estimated_loss,'');assert.equal(prefill.crop_choices[0].crop_type,'Mango');
    const adminPage=await admin.newPage();adminPage.on('pageerror',e=>pageErrors.push(e.message));
    await adminPage.goto(base+adminPath+'reportAnalytics.php');
    await adminPage.locator('#reporting-for').selectOption('1');
    await adminPage.waitForFunction(()=>document.querySelector('[data-upload]').disabled===false);
    assert.equal(await adminPage.locator('#report-type').isDisabled(),true);
    await adminPage.locator('.language-toggle-button[data-language="tl"]').click();assert.match(await adminPage.locator('.report-image-header small').innerText(),/Mag-upload/);
    await adminPage.locator('.language-toggle-button[data-language="en"]').click();
    await adminPage.locator('[data-report-image-input]').setInputFiles(image('webp'));
    await adminPage.waitForFunction(()=>!document.querySelector('#report-type').disabled);
    await adminPage.locator('#report-type').selectOption('damage');
    assert.equal(await adminPage.locator('#damage-crop-type').inputValue(),'Mango');
    assert.equal(await adminPage.locator('#date-occurrence').count(),0);
    assert.match(await adminPage.locator('.gps-photo-details').innerText(),/08:49:24 PM/);
    await adminPage.locator('#damage-cause').fill('Typhoon');
    await adminPage.locator('#damage-percentage').fill('25');await adminPage.locator('#estimated-loss').fill('20');await adminPage.locator('#damage-farm-gate-price').fill('60');
    adminPage.on('dialog',d=>d.accept());
    await adminPage.locator('.submit-report-button').click();
    await adminPage.waitForURL('**/reportAnalytics.php');
    assert.equal(rows().damage_reports.length,2);
    await admin.request.post(base+adminPath+'report_submit.php',{multipart:{...cropFields,'reporting-for':'1','pending-report-image-token':await stage(admin,'jpg')}});
    assert.equal(rows().crop_reports.length,2);
    for(const type of ['crop','damage']){
      const record=rows()[type+'_reports'][1];assert.equal(Number(record.FarmerID),1);assert.equal(record.created_by,'GPS Test Personnel');
      const res=await admin.request.post(base+adminPath+type+'/get'+(type==='crop'?'Crop':'Damage')+'Reports.php',{form:{id:String(record.id)}});
      const detail=await res.json();assert.ok(detail.ImageUrl);assert.equal(detail.gps_photo.date,'2026-09-09');assert.match(detail.ReportedBy,/GPS Test Personnel/i);
      await page.goto(base+farmerPath+'report.php');
      const reports=await page.evaluate(()=>window.farmerReportsData || window.farmerReports || null);
      // Both views read the same persisted URL and row, with different relative prefixes.
      assert.ok((await page.content()).includes(path.basename(record.image_path)));
      const bytes=await (await admin.request.get(new URL(detail.ImageUrl,base+adminPath+type+'/get'+(type==='crop'?'Crop':'Damage')+'Reports.php').href)).body();
      assert.deepEqual(bytes,images[type==='crop'?'jpg':'webp']);
    }
    check('Personnel Damage UI + Harvest API, registered prefill only, FarmerID/created_by and same image in both portals');
    for (const [type,filename] of [['crop','cropReports.php'],['damage','damageReports.php']]) {
      await adminPage.goto(base+adminPath+type+'/'+filename);
      await adminPage.locator('tr[data-id="2"] .action-button').click();
      const panel=adminPage.locator('#'+type+'ReportImagePanelGPS');
      await panel.waitFor({state:'visible'});assert.match(await panel.innerText(),/09\/09\/2026/);
      assert.equal(await panel.locator('input,select,textarea').count(),0);
      await adminPage.setViewportSize({width:390,height:900});
      assert.equal(await panel.evaluate(e=>e.scrollWidth>e.clientWidth+1),false);
      await adminPage.screenshot({path:path.join(out,type+'-details-mobile.png'),fullPage:true});
    }
    check('Admin Harvest/Damage details display persisted read-only photo information on mobile');
    const before=JSON.stringify(rows());
    const foreignToken=await stage(admin);
    await farmer.request.post(base+farmerPath+'report_submit.php',{multipart:{...cropFields,'pending-report-image-token':foreignToken,photo_capture_date:'2020-01-01'}});
    assert.equal(JSON.stringify(rows()),before);
    check('Another session cannot reuse an extraction token or inject photo metadata');
    await admin.request.post(base+adminPath+'report_submit.php',{multipart:{...cropFields,'reporting-for':'9999','admin-report-image':image()}});
    await admin.request.post(base+adminPath+'report_submit.php',{multipart:{...cropFields,'admin-report-image':image()}});
    await farmer.request.post(base+farmerPath+'report_submit.php',{multipart:cropFields});
    for(const bad of [{name:'evil.php',mimeType:'image/png',buffer:Buffer.from('<?php echo 1;')},{name:'huge.png',mimeType:'image/png',buffer:Buffer.alloc(6*1024*1024+1)}]) {
      const invalid=await farmer.request.post(base+'/shared/report_image_stage.php',{multipart:{report_image:bad}});assert.equal(invalid.status(),400);
    }
    assert.equal(JSON.stringify(rows()),before);
    const unauth=await anon.request.post(base+'/shared/report_image_stage.php',{multipart:{report_image:image()}});assert.equal(unauth.status(),401);
    check('Server rejects missing image, spoofed MIME, oversized file, invalid/missing Farmer and unauthenticated upload');
    const legacyId=fixture('legacy');
    await page.goto(base+farmerPath+'report.php');
    const index = await page.evaluate(()=>window.farmerSubmittedReports.findIndex(r=>Number(r.id)===1 && r.report_type==='crop'));
    await page.locator(`button[data-report-index="${index}"]`).click();
    assert.equal(await page.locator('#farmerReportEditForm').isVisible(),false);
    const old=await page.locator('#farmerReportDetailImage').getAttribute('src');
    await page.locator('#farmerReportEditButton').click();
    await page.locator('#edit-report-image').setInputFiles(image('webp'));
    await page.waitForFunction(()=>document.querySelector('#farmerReportDetailImage').src.startsWith('blob:'));
    await page.locator('#farmerReportCancelEdit').click();
    assert.equal(await page.locator('#farmerReportDetailImage').getAttribute('src'),old);
    check('View locked, Edit replacement preview, Cancel restores original');
    await page.locator('#farmerReportEditButton').click();
    await page.locator('#edit-report-image').setInputFiles(image('webp'));
    await page.waitForFunction(()=>document.querySelector('#farmerReportDetailImage').src.startsWith('blob:'));
    await page.waitForFunction(()=>document.querySelector('[name="edit-pending-report-image-token"]').value);
    const savedResponse = page.waitForResponse(r=>r.url().endsWith('/report_update.php'));
    await page.locator('#farmerReportEditForm button[type="submit"]').click();
    assert.equal((await (await savedResponse).json()).success,true);
    assert.equal(rows().crop_reports.length,3);
    assert.equal(rows().crop_reports[0].latitude,'13.8417670');
    const reopened = await browser.newContext();
    await reopened.request.get(base+'/__test/session?as=farmer',{headers:{'X-Test-Key':key}});
    const reopenedHtml = await (await reopened.request.get(base+farmerPath+'report.php')).text();
    assert.ok(reopenedHtml.includes(path.basename(rows().crop_reports[0].image_path)));
    await reopened.close();
    check('UI Save persists replacement and reopening in a new signed-in session retains evidence');

    const update={'report-type':'crop','report-id':'1',location:'adya','crop-commodity':'fruits','crop-type':'Mango','crop-variety':'Carabao','date-planted':'2026-01-01','harvest-date':'2026-09-01','estimate-harvest':'90','crop-status':'Bearing','farm-gate-price':'60','number-of-trees':'10',latitude:'13.8',longitude:'121.1'};
    const forbidden=await other.request.post(base+farmerPath+'report_update.php',{multipart:update});assert.equal(forbidden.status(),404);
    let result=await (await farmer.request.post(base+farmerPath+'report_update.php',{multipart:{...update,'edit-pending-report-image-token':await stage(farmer,'webp')}})).json();assert.equal(result.success,true);
    assert.equal(rows().crop_reports.length,3);assert.equal(rows().crop_reports[0].latitude,'13.8417670');
    assert.deepEqual(fs.readFileSync(path.join(root,rows().crop_reports[0].image_path)),images.webp);
    result=await (await farmer.request.post(base+farmerPath+'report_update.php',{multipart:{...update,'remove-report-image':'1'}})).json();assert.equal(result.success,false);assert.ok(rows().crop_reports[0].image_path);
    result=await (await farmer.request.post(base+farmerPath+'report_update.php',{multipart:{...update,'report-id':legacyId}})).json();assert.equal(result.success,true);
    check('Save updates same record; modern evidence removal rejected; old reports editable; photo GIS source preserved; cross-Farmer edit denied');
    for(const [p,url,label] of [[page,farmerPath+'report.php','farmer'],[adminPage,adminPath+'reportAnalytics.php','admin']]) {
      await p.goto(base+url);
      if(label==='admin') {await p.locator('#reporting-for').selectOption('1');await p.waitForFunction(()=>!document.querySelector('[data-upload]').disabled);}
      await p.locator('[data-report-image-input]').setInputFiles(image());await p.waitForFunction(()=>document.querySelector('[data-report-image-capture]').dataset.hasImage==='true');await p.locator('#report-type').selectOption('damage');
      for(const width of [1366,768,390]) {await p.setViewportSize({width,height:900});await p.waitForTimeout(350);await p.locator('[data-report-image-capture]').scrollIntoViewIfNeeded();await p.screenshot({path:path.join(out,`${label}-${width}.png`),fullPage:true});
        await p.locator('[data-report-image-capture] .gps-photo-details').scrollIntoViewIfNeeded();await p.screenshot({path:path.join(out,`${label}-info-${width}.png`)});
        const layout=await p.evaluate(()=>{const img=document.querySelector('.report-image-preview-wrap img'),wrap=img.closest('.report-image-preview-wrap'),link=wrap.querySelector('[data-enlarge]'),rect=img.getBoundingClientRect(),container=wrap.getBoundingClientRect(),style=getComputedStyle(img);return {overflow:document.documentElement.scrollWidth>innerWidth+1,inside:rect.left>=container.left-1&&rect.right<=container.right+1,height:rect.height,width:rect.width,ratio:rect.width/rect.height,naturalRatio:img.naturalWidth/img.naturalHeight,objectFit:style.objectFit,enlarge:Boolean(link.href)&&link.getClientRects().length>0};});if(layout.overflow) console.log(await p.evaluate(()=>Array.from(document.querySelectorAll('body *')).filter(e=>e.getBoundingClientRect().right>innerWidth+1).slice(0,12).map(e=>({tag:e.tagName,cl:e.className,r:e.getBoundingClientRect().right,w:getComputedStyle(e).width}))));assert.equal(layout.overflow,false,label+' overflow '+width);assert.equal(layout.inside,true,label+' preview bounds '+width);assert.ok(layout.height<=421,label+' preview height '+width+': '+layout.height);assert.ok(Math.abs(layout.ratio-layout.naturalRatio)<0.02,label+' preview aspect ratio '+width);assert.equal(layout.objectFit,'contain');assert.equal(layout.enlarge,true,label+' enlarge control '+width);
      }
    }
    check('Farmer and Admin desktop/tablet/mobile image and form layout');
    assert.deepEqual(pageErrors,[]);
    fs.writeFileSync(path.join(out,'results.json'),JSON.stringify({checks,pageErrors},null,2));
    fs.writeFileSync(path.join(out,'server.log'),log);
    console.log(`${checks.length} workflow groups passed`);
 } finally {
    if(browser) await browser.close();
    if(server) server.kill();
    if(ready) console.log(fixture('cleanup'));
 }
})().catch(e=>{console.error(e);process.exitCode=1;});


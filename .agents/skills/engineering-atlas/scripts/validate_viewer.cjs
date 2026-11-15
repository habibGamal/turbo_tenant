// Exercise the delivered documentation, including hidden pages and exported SVGs.
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const {pathToFileURL,fileURLToPath}=require('node:url');
const viewer=path.resolve(process.argv[2]),deps=path.resolve(process.argv[3]);
const evidence=process.argv[4]&&path.resolve(process.argv[4]);
const {chromium}=require(path.join(deps,'node_modules/playwright'));
(async()=>{
 const browser=await chromium.launch({headless:true});
 try{
  const page=await browser.newPage({viewport:{width:1440,height:1000}});
  const errors=[],external=[];
  page.on('pageerror',error=>errors.push(error.message));
  page.on('request',request=>{if(/^https?:/.test(request.url()))external.push(request.url());});
  await page.goto(pathToFileURL(viewer).href);
  if(evidence)fs.mkdirSync(evidence,{recursive:true});
  const sections=await page.locator('main > .doc-page').count();
  const expectedDiagrams=await page.locator('.diagram-card').count();
  assert.equal(sections,9,'Expected overview and eight engineering sections');
  assert.equal(await page.locator('main > .doc-page:visible').count(),1,'Only one documentation page should be visible');
  assert.ok(await page.locator('#overview').isVisible());
  if(evidence)await page.screenshot({path:path.join(evidence,'desktop.png')});

  await page.keyboard.press('Control+k');
  assert.ok(await page.locator('#search-dialog').isVisible());
  const routeQuery=await page.locator('#routes tbody tr code').first().textContent();
  await page.locator('#search').fill(routeQuery);
  const matching=await page.locator('.search-result').count();
  assert.ok(matching>0,'Global search did not find a route in a hidden section');
  const routeResult=page.locator('.search-result').filter({has:page.locator('small', {hasText:'Routes & middleware'})}).first();
  await routeResult.click();
  await page.waitForFunction(()=>!document.querySelector('#routes').hidden);
  assert.ok(!await page.locator('#search-dialog').isVisible());
  assert.ok(await page.locator('#routes').isVisible());
  await page.keyboard.press('Control+k');
  await page.locator('#search').fill('Architecture');
  await page.keyboard.press('ArrowDown');await page.keyboard.press('ArrowUp');await page.keyboard.press('Enter');
  await page.waitForFunction(()=>!document.querySelector('#architecture').hidden);
  const search={results:matching,hidden_page_results:true,keyboard_navigation:true};

  const pageIds=await page.locator('main > .doc-page').evaluateAll(nodes=>nodes.map(n=>n.id));
  let diagrams=0,roots=0,zoom,sourceTabs=0,expanded=0;
  for(const id of pageIds){
   await page.locator('.sidebar nav a[data-page="'+id+'"]').click();
   await page.waitForFunction(id=>!document.getElementById(id).hidden,id);
   assert.equal(await page.locator('main > .doc-page:visible').count(),1);
   assert.equal(await page.locator('.sidebar nav a[aria-current="page"]').getAttribute('data-page'),id);
   const count=await page.locator('#'+id+' .diagram-card').count();
   const validity=await page.locator('#'+id+' .diagram').evaluateAll(containers=>containers.map(container=>{
    const root=container.firstElementChild;
    if(!root||root.localName!=='svg'||root.namespaceURI!=='http://www.w3.org/2000/svg')return false;
    const parsed=new DOMParser().parseFromString(new XMLSerializer().serializeToString(root),'image/svg+xml');
    return parsed.documentElement.localName==='svg'&&!parsed.querySelector('parsererror')&&root.getBoundingClientRect().width+1>=root.viewBox.baseVal.width*.75;
   }));
   assert.equal(validity.filter(Boolean).length,count,'Invalid SVG on '+id);
   roots+=validity.filter(Boolean).length;diagrams+=count;
   for(let i=0;i<count;i++){
    const card=page.locator('#'+id+' .diagram-card').nth(i);
    await card.locator('.diagram-tab[data-view="source"]').click();
    assert.ok(await card.locator('.diagram-source').isVisible());
    assert.ok((await card.locator('.diagram-source code').textContent()).length>5);
    await card.locator('.diagram-tab[data-view="diagram"]').click();sourceTabs++;
    await card.locator('.expand').click();
    assert.ok(await page.locator('#diagram-modal').isVisible());
    assert.equal(await page.locator('.modal-canvas > svg').count(),1);
    assert.equal(await page.locator('#diagram-modal parsererror').count(),0);expanded++;
    if(!zoom){
     const width=await page.locator('.modal-canvas > svg').evaluate(e=>e.getBoundingClientRect().width);
     await page.locator('#zoom-in').click();
     const zoomed=await page.locator('.modal-canvas > svg').evaluate(e=>e.getBoundingClientRect().width);
     assert.ok(zoomed>width);zoom={width,zoomed};
     await page.locator('#zoom-fit').click();
     assert.ok(await page.locator('.modal-canvas > svg').evaluate(e=>e.getBoundingClientRect().width)<=await page.locator('.modal-canvas').evaluate(e=>e.clientWidth));
    }
    await page.keyboard.press('Escape');assert.ok(!await page.locator('#diagram-modal').isVisible());
   }
  }
  assert.equal(diagrams,expectedDiagrams);assert.ok(diagrams>0);assert.equal(await page.locator('parsererror').count(),0);

  const links=await page.locator('a.source').evaluateAll(nodes=>nodes.map(a=>a.getAttribute('href')));
  for(const href of new Set(links)){
   const url=new URL(href,pathToFileURL(viewer));url.hash='';
   assert.equal(url.protocol,'file:');assert.ok(fs.existsSync(fileURLToPath(url)),'Broken source target: '+href);
  }
  await page.locator('.sidebar nav a[data-page="architecture"]').click();
  await page.waitForFunction(()=>!document.querySelector('#architecture').hidden);
  await page.locator('#toc-list a').first().click();
  await page.waitForFunction(()=>{const id=location.hash.split('/')[1];return id&&document.getElementById(id).getBoundingClientRect().top>=65&&document.getElementById(id).getBoundingClientRect().top<innerHeight/2;});
  const downloadEvent=page.waitForEvent('download');await page.locator('#architecture .download-svg').first().click();
  const download=await downloadEvent;assert.ok(download.suggestedFilename().endsWith('.svg'));
  assert.equal(await download.failure(),null);
  if(evidence)await download.saveAs(path.join(evidence,'downloaded-diagram.svg'));
  await page.locator('#architecture .diagram-evidence').first().locator('summary').click();
  assert.ok(await page.locator('#architecture .evidence-list').first().isVisible());
  await page.locator('#architecture .diagram-evidence').first().locator('summary').click();

  if(evidence){
   await page.screenshot({path:path.join(evidence,'architecture.png')});
   await page.locator('#architecture .diagram-card').first().screenshot({path:path.join(evidence,'system-context.png')});
   await page.locator('.sidebar nav a[data-page="database"]').click();
   await page.screenshot({path:path.join(evidence,'database.png')});
   await page.locator('.sidebar nav a[data-page="workflows"]').click();
   await page.screenshot({path:path.join(evidence,'checkout.png')});
  }
  await page.locator('.sidebar nav a[data-page="overview"]').click();
  await page.setViewportSize({width:390,height:844});
  await page.waitForFunction(()=>document.querySelector('.sidebar').getBoundingClientRect().right<=0);
  assert.ok(!await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),'Mobile overview overflow');
  if(evidence)await page.screenshot({path:path.join(evidence,'mobile.png')});
  await page.locator('#menu-toggle').click();assert.equal(await page.locator('#menu-toggle').getAttribute('aria-expanded'),'true');
  await page.locator('.sidebar nav a[data-page="routes"]').click();
  assert.equal(await page.locator('#menu-toggle').getAttribute('aria-expanded'),'false');
  await page.waitForFunction(()=>!document.querySelector('#routes').hidden);
  assert.ok(!await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),'Mobile route-page overflow');
  await page.locator('#search-open').click();await page.locator('#search').fill('nothing-matches-this-token');
  assert.ok(await page.locator('.search-empty').isVisible());await page.keyboard.press('Escape');
  assert.equal(errors.length,0,errors.join('\n'));assert.equal(external.length,0,'Offline viewer requested the network');
  const report={viewer,desktop:'1440x1000',mobile:'390x844',sections,diagrams,valid_svg_roots:roots,expanded_diagrams:expanded,source_tabs:sourceTabs,svg_download:true,mobile_navigation:true,source_links:links.length,all_source_targets_exist:true,search,zoom,errors,external_requests:external.length};
  if(evidence)fs.writeFileSync(path.join(evidence,'viewer-validation.json'),JSON.stringify(report,null,2));console.log(JSON.stringify(report,null,2));
 }finally{await browser.close();}
})().catch(error=>{console.error(error.stack);process.exitCode=1;});

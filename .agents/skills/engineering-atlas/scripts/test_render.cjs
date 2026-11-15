// Regression for HTML line breaks in Mermaid's SVG foreignObject labels.
const assert=require('node:assert/strict');
const fs=require('node:fs');
const os=require('node:os');
const path=require('node:path');
const {spawnSync}=require('node:child_process');
const deps=path.resolve(process.argv[2]);
const {chromium}=require(path.join(deps,'node_modules/playwright'));
(async()=>{
 const temp=fs.mkdtempSync(path.join(os.tmpdir(),'atlas-render-test-'));
 try{
  fs.mkdirSync(path.join(temp,'diagrams'));
  fs.writeFileSync(path.join(temp,'diagram-index.json'),JSON.stringify([{id:'multiline',title:'Multiline & symbols',mermaid:'flowchart LR\n A["First line<br>Second & third"] --> B["Target"]'}]));
  const rendered=spawnSync(process.execPath,[path.join(__dirname,'render.cjs'),temp,deps],{encoding:'utf8'});
  assert.equal(rendered.status,0,rendered.stderr);
  const svg=fs.readFileSync(path.join(temp,'diagrams/multiline.svg'),'utf8');
  const browser=await chromium.launch({headless:true});
  try{
   const page=await browser.newPage();
   const result=await page.evaluate(markup=>{const doc=new DOMParser().parseFromString(markup,'image/svg+xml');return {root:doc.documentElement.localName,namespace:doc.documentElement.namespaceURI,errors:doc.getElementsByTagName('parsererror').length,text:doc.documentElement.textContent};},svg);
   assert.equal(result.root,'svg','Exporter saved a browser error document instead of an SVG');
   assert.equal(result.namespace,'http://www.w3.org/2000/svg');
   assert.equal(result.errors,0);
   assert.ok(result.text.includes('Second & third'),'Multiline label was lost');
  }finally{await browser.close();}
  console.log('Multiline HTML-label SVG export regression passed.');
 }finally{
  assert.equal(path.dirname(path.resolve(temp)),path.resolve(os.tmpdir()));
  assert.ok(path.basename(temp).startsWith('atlas-render-test-'));
  fs.rmSync(temp,{recursive:true,force:true});
 }
})().catch(error=>{console.error(error.message);process.exitCode=1;});

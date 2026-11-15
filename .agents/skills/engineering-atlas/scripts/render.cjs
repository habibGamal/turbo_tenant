const fs=require('fs');const path=require('path');
const output=path.resolve(process.argv[2]),deps=path.resolve(process.argv[3]);
const {chromium}=require(path.join(deps,'node_modules/playwright'));
(async()=>{
 const browser=await chromium.launch({headless:true});
 try {
  const page=await browser.newPage({viewport:{width:1600,height:1000}});
  await page.setContent('<!doctype html><html><body style="background:#ffffff"></body></html>');
  await page.addScriptTag({path:path.join(deps,'node_modules/mermaid/dist/mermaid.min.js')});
  await page.evaluate(()=>mermaid.initialize({startOnLoad:false,theme:'base',securityLevel:'strict',deterministicIds:true,deterministicIDSeed:'engineering-atlas',maxTextSize:200000,themeVariables:{fontFamily:'Arial, sans-serif',fontSize:'15px',background:'#ffffff',primaryColor:'#f3f2ff',primaryTextColor:'#242841',primaryBorderColor:'#aaa4e2',secondaryColor:'#f6f8fc',tertiaryColor:'#ffffff',lineColor:'#8590a6',edgeLabelBackground:'#ffffff',clusterBkg:'#f8f9fc',clusterBorder:'#d8deea',actorBkg:'#f3f2ff',actorBorder:'#aaa4e2',actorTextColor:'#242841',signalColor:'#6d7790',signalTextColor:'#333c52',labelBoxBkgColor:'#f5f6fb',labelBoxBorderColor:'#d8deea',labelTextColor:'#333c52',activationBkgColor:'#ebe8fd',activationBorderColor:'#aaa4e2'}}));
  const diagrams=JSON.parse(fs.readFileSync(path.join(output,'diagram-index.json'),'utf8'));
  for(const [i,d] of diagrams.entries()){
   const svg=await page.evaluate(async({text,id,title})=>{
    await mermaid.parse(text);
    const result=await mermaid.render(id,text);
    // Mermaid HTML labels may contain HTML void elements such as <br>.
    // Build the namespace-aware HTML DOM before serializing valid XML.
    const template=document.createElement('template');
    template.innerHTML=result.svg;
    const root=template.content.firstElementChild;
    if(!root || root.localName!=='svg' || root.namespaceURI!=='http://www.w3.org/2000/svg' || template.content.querySelector('parsererror')) throw new Error('Invalid rendered SVG: '+id);
    root.setAttribute('aria-label',title);root.setAttribute('role','img');
    const serialized=new XMLSerializer().serializeToString(root);
    const verified=new DOMParser().parseFromString(serialized,'image/svg+xml');
    if(verified.documentElement.localName!=='svg' || verified.querySelector('parsererror')) throw new Error('Invalid SVG XML export: '+id);
    return serialized;
   },{text:d.mermaid,id:'atlas'+i,title:d.title});
   fs.writeFileSync(path.join(output,'diagrams',d.id+'.svg'),svg);
  }
  console.log('Parsed and rendered '+diagrams.length+' diagrams.');
 }finally{await browser.close();}
})().catch(error=>{console.error(error.message);process.exitCode=1;});

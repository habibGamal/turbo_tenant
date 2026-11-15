const fs = require('fs');
const path = require('path');
const root = path.resolve(process.argv[2]);
const ts = require(path.join(root, 'node_modules/typescript'));
const result = {pages: [], requests: [], imports: [], errors: []};
function walk(dir) {
  if (!fs.existsSync(dir)) return;
  for (const item of fs.readdirSync(dir, {withFileTypes:true}).sort((a,b)=>a.name.localeCompare(b.name))) {
    const file = path.join(dir, item.name);
    if (item.isDirectory()) walk(file);
    else if (/\.[jt]sx?$/.test(file) && !file.endsWith('.d.ts')) scan(file);
  }
}
function scan(file) {
  const text = fs.readFileSync(file, 'utf8');
  const source = ts.createSourceFile(file, text, ts.ScriptTarget.Latest, true, file.endsWith('x') ? ts.ScriptKind.TSX : ts.ScriptKind.TS);
  const relative = path.relative(root,file).replaceAll('\\','/');
  const line = n => source.getLineAndCharacterOfPosition(n.getStart(source)).line + 1;
  const value = n => ts.isStringLiteralLike(n) ? n.text : ts.isNoSubstitutionTemplateLiteral(n) ? n.text : ts.isTemplateExpression(n) ? n.getText(source).slice(1,-1).replace(/\$\{[^}]+\}/g, '{dynamic}') : null;
  function visit(n) {
    if (ts.isImportDeclaration(n)) result.imports.push({file:relative,line:line(n),target:n.moduleSpecifier.text});
    if (ts.isJsxSelfClosingElement(n) || ts.isJsxOpeningElement(n)) {
      if (n.tagName.getText(source) === 'Route') {
        const attrs = n.attributes.properties;
        const attr = attrs.find(a => a.name?.text === 'path');
        const route = attr?.initializer && value(attr.initializer);
        if (route) {
          const element = attrs.find(a=>a.name?.text==='element');
          const snippet = element?.getText(source) || '';
          result.pages.push({path:route,file:relative,line:line(n),plugin:snippet.match(/RequirePlugin\s+id=["']([^"']+)/)?.[1] || null,checkout_guard:snippet.includes('CheckoutGuard'),element:snippet.replace(/\s+/g,' ').slice(0,500)});
        }
      }
    }
    if (ts.isCallExpression(n) && ts.isPropertyAccessExpression(n.expression)) {
      const method = n.expression.name.text.toUpperCase();
      const client = n.expression.expression.getText(source);
      if (['GET','POST','PUT','PATCH','DELETE'].includes(method) && /api|client|axios|http/i.test(client) && n.arguments[0]) {
        const url = value(n.arguments[0]);
        result.requests.push({method,url:url || '{unresolved expression}',client,file:relative,line:line(n),dynamic:url?.includes('{dynamic}') || !url});
      }
    }
    ts.forEachChild(n, visit);
  }
  visit(source);
  for (const e of source.parseDiagnostics) result.errors.push({file:relative,line:source.getLineAndCharacterOfPosition(e.start||0).line+1,message:'TypeScript parse failed'});
}
walk(path.join(root,'resources/js'));
process.stdout.write(JSON.stringify(result,null,2));

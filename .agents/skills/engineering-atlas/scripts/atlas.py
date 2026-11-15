"""Evidence-linked Laravel/React engineering atlas. Python 3.10+, standard library."""
import argparse
import hashlib
import html
import json
import re
import subprocess
import sys
import xml.etree.ElementTree as ET
from collections import Counter, defaultdict
from pathlib import Path

SCRIPTS = Path(__file__).resolve().parent
OUTPUT = 'docs/engineering-atlas'
VERSION = '1.0'
ADAPTER_DIR = '.agents/skills/engineering-atlas/scripts'
ADAPTER_FILES = ['atlas.py', 'extract.php', 'frontend.cjs', 'render.cjs', 'routes.php', 'viewer.html']

def digest(value):
    return hashlib.sha256(json.dumps(value, sort_keys=True, separators=(',', ':')).encode()).hexdigest()

def file_digest(file):
    return hashlib.sha256(file.read_bytes().replace(b'\r\n',b'\n')).hexdigest()

def validate_svg(path):
    try:
        root=ET.parse(path).getroot()
    except ET.ParseError as error:
        raise RuntimeError(f'Invalid SVG XML: {path.name}: {error}') from error
    if root.tag!='{http://www.w3.org/2000/svg}svg':
        raise RuntimeError(f'Invalid SVG root: {path.name}; got {root.tag}')
    if any(node.tag.split('}')[-1]=='parsererror' for node in root.iter()):
        raise RuntimeError(f'Embedded parser error in SVG: {path.name}')
    if not any(node.tag.split('}')[-1] in {'g','path','text','rect','circle','foreignObject','line','polygon','polyline','ellipse','use'} for node in root.iter()):
        raise RuntimeError(f'Empty SVG diagram: {path.name}')

def validate_diagrams(output):
    for diagram in read_json(output/'diagram-index.json'):
        path=output/'diagrams'/(diagram['id']+'.svg')
        if not path.is_file(): raise RuntimeError('Unrendered diagram '+diagram['id'])
        validate_svg(path)

def read_json(path):
    return json.loads(path.read_text(encoding='utf-8-sig'))

def write_json(path, value):
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(value, indent=2, ensure_ascii=False)+'\n', encoding='utf-8')

def source_inventory(root):
    files = set()
    for directory in ['app', 'bootstrap', 'config', 'routes', 'database/migrations', 'resources/js', 'resources/views']:
        for file in (root/directory).rglob('*'):
            if file.is_file() and file.suffix in {'.php','.ts','.tsx','.js','.jsx','.json','.py','.cjs','.mjs','.md','.yaml','.html'} and not any(p in {'node_modules','__pycache__','cache'} for p in file.relative_to(root/directory).parts[:-1]):
                files.add(file)
    # Only the adapter's generator code is an input; handbook references/templates/tools in the skill are not.
    for name in ADAPTER_FILES:
        if (root/ADAPTER_DIR/name).is_file(): files.add(root/ADAPTER_DIR/name)
    for name in ['composer.json','composer.lock','package.json','package-lock.json','AGENTS.md',f'{OUTPUT}/definition.json']:
        if (root/name).is_file(): files.add(root/name)
    return {file.relative_to(root).as_posix(): file_digest(file) for file in sorted(files)}

def registration_fingerprint(root, inventory=None):
    inputs=source_inventory(root) if inventory is None else inventory
    registration={key:value for key,value in inputs.items() if key.endswith('.php') or key in {'composer.json','composer.lock'} or (key.startswith('app/Plugins/') and key.endswith('/plugin.json'))}
    return digest(registration)

def ident(kind, key):
    return kind+'-'+hashlib.sha256(key.encode()).hexdigest()[:14]

def owner(file):
    match = re.search(r'app/Plugins/([^/]+)/', file)
    return match[1] if match else 'Host'

def source(file, line=1):
    return {'file':file, 'line':line}

def schema_model(operations):
    """Preserve declarations and provenance, never claim migration replay/live state."""
    tables = {}
    for op in operations:
        name = op['table']
        if not isinstance(name, str): continue
        scope = 'tenant' if '/tenant/' in op['file'] or op['file'].startswith('app/Plugins/') else 'central'
        key = scope+':'+name
        table = tables.setdefault(key, dict(id=ident('table',key), key=key, name=name, scope=scope,
            owners=[], definitions=[], columns=[], foreign_keys=[], indexes=[], changes=[], evidence='declared'))
        if owner(op['file']) not in table['owners']: table['owners'].append(owner(op['file']))
        evidence = source(op['file'],op['line'])
        table['changes'].append(dict(operation=op['operation'],source=evidence))
        if op['operation']=='create': table['definitions'].append(evidence)
        for statement in op['operations']:
            calls=statement['chain']; first=calls[0]; method=first['method']; args=first['args']
            modifiers={c['method']:c['args'] for c in calls[1:]}
            evidence=source(op['file'], statement['line'])
            column=args[0] if args and isinstance(args[0],str) else None
            if method in {'foreign','foreignId','foreignUuid'}:
                target=None
                if 'constrained' in modifiers:
                    target=(modifiers['constrained'] or [None])[0]
                    # Laravel convention is explicitly inference, not an explicit FK target.
                    if target is None: target=statement.get('convention_target')
                if 'on' in modifiers: target=modifiers['on'][0]
                if target and column:
                    table['foreign_keys'].append(dict(column=column,target=target,
                        referenced_column=(modifiers.get('references') or ['id'])[0],
                        nullable='nullable' in modifiers, inference=not bool(modifiers.get('constrained') or modifiers.get('on')),
                        source=evidence,evidence='declared'))
            if method in {'index','unique','primary','fullText','spatialIndex'}:
                table['indexes'].append(dict(kind=method,columns=args[0] if args else [],source=evidence)); continue
            if method.startswith('drop') or method in {'renameColumn','renameIndex'}:
                table['changes'].append(dict(operation=method,arguments=args,source=evidence)); continue
            if method in {'timestamps','nullableTimestamps','softDeletes','softDeletesTz','rememberToken','morphs','nullableMorphs','uuidMorphs','nullableUuidMorphs'}:
                table['columns'].append(dict(name='['+method+']',type=method,nullable=method.startswith('nullable'),source=evidence,shorthand=True)); continue
            if column and method!='foreign':
                table['columns'].append(dict(name=column,type=method,nullable='nullable' in modifiers,
                    primary=method=='id' or 'primary' in modifiers,unique='unique' in modifiers,change='change' in modifiers,source=evidence))
    return sorted(tables.values(),key=lambda t:t['key'])

def route_candidates(url, method, routes):
    if not url or not url.startswith('/'): return []
    url=url.split('?')[0].strip('/')
    matches=[]
    for route in routes:
        pattern=re.sub(r'\\\{[^}]+\\\}', '[^/]+', re.escape(route['uri'].strip('/')))
        candidate=re.sub(r'\{dynamic\}', 'value',url)
        if method in route['methods'] and re.fullmatch(pattern,candidate): matches.append(route['id'])
    return matches

def run_json(command, root):
    result=subprocess.run(command,cwd=root,capture_output=True,text=True,encoding='utf-8',timeout=180)
    if result.returncode:
        raise RuntimeError(f'Extractor {Path(command[1]).name} failed (exit {result.returncode}); inspect locally without publishing configuration or traces.')
    try: return json.loads(result.stdout)
    except json.JSONDecodeError: raise RuntimeError(f'Extractor {Path(command[1]).name} did not return clean JSON; no partial atlas was accepted.')

def extract(root, observe):
    php=run_json(['php',str(SCRIPTS/'extract.php'),str(root)],root)
    frontend=run_json(['node',str(SCRIPTS/'frontend.cjs'),str(root)],root)
    if php['errors'] or frontend['errors']: raise RuntimeError('Source parsing failed: '+json.dumps(php['errors']+frontend['errors']))
    output=root/OUTPUT
    snapshot=output/'route-snapshot.json'
    registration_hash=registration_fingerprint(root)
    if observe:
        observed=run_json(['php',str(SCRIPTS/'routes.php'),str(root)],root)
        observed['source_fingerprint']=registration_hash
        write_json(snapshot,observed)
    elif not snapshot.exists():
        raise RuntimeError('No route observation. Use --observe after inspecting application boot side effects, or add an adapter for static routes.')
    observed=read_json(snapshot)
    if observed['source_fingerprint'] != registration_hash: raise RuntimeError('Registered route snapshot is stale. Refresh with build --observe.')
    manifests=[]
    for file in sorted((root/'app/Plugins').glob('*/plugin.json')):
        data=read_json(file)
        manifests.append({k:data.get(k) for k in ['id','name','dependencies','providers','listeners','is_core','tenant_scoped']} | {'folder':file.parent.name,'source':source(file.relative_to(root).as_posix())})
    routes=[]
    for r in observed['routes']:
        r['id']=ident('route',json.dumps([r['methods'],r['uri'],r['name'],r['action'],r['domain_bound']]))
        r['evidence']='observed-registration'
        r['owner']=owner(r['source']['file']) if r['source'] else 'Framework'
        r['plugin_gate']=next((m.split(':',1)[1] for m in r['middleware'] if isinstance(m,str) and m.startswith('plugin.active:')),None)
        routes.append(r)
    classes=php['classes']
    for c in classes:
        c['id']=ident('class',c['name']); c['owner']=owner(c['file']); c['source']=source(c['file'],c['line']); c['evidence']='declared'
    for p in frontend['pages']: p.update(id=ident('page',p['file']+':'+p['path']),source=source(p['file'],p['line']),evidence='declared')
    for r in frontend['requests']:
        r.update(id=ident('request',r['file']+':'+str(r['line'])),source=source(r['file'],r['line']),evidence='declared')
        r['route_candidates']=route_candidates(r['url'],r['method'],routes)
    security=[]
    for c in classes:
        for m in c['methods']:
            if m['name'] in {'authorize','canAccessPanel','canAccess','shouldRegisterNavigation'} or '/Policies/' in c['file'] or any(x['name'] in {'authorize','policy','define'} for x in m['calls']):
                security.append(dict(id=ident('control',c['name']+'@'+m['name']),symbol=c['name']+'@'+m['name'],owner=c['owner'],source=source(c['file'],m['line']),evidence='declared'))
    head=subprocess.run(['git','rev-parse','HEAD'],cwd=root,capture_output=True,text=True).stdout.strip()
    return dict(contract_version=VERSION,project=root.name,revision=head,scope='current working tree; configured CLI route registration; source declarations',
        modules=manifests,classes=classes,tables=schema_model(php['schema_operations']),schema_operations=php['schema_operations'],
        routes=routes,pages=frontend['pages'],requests=frontend['requests'],frontend_imports=frontend['imports'],controls=security,
        coverage=dict(php_files=len({c['file'] for c in classes}),request_extraction='axios-style named client calls only; fetch and custom wrappers require adapters',
            schema='up() Schema::create/table declarations, including historical and conditional declarations; no migrations executed or live database queried',
            routes=observed['mode'],page_request_links='reviewed workflows only; URL matches are candidates, not call-graph proof',
            limitations=['No authenticated browser or tenant request was executed.','No deployed topology, runtime plugin activation, tenant data, or live schema was verified.',
                'Column shorthands remain labeled; raw SQL, schema macros, connection chains, and dynamic table names require review.',
                'Migration histories are not replayed: declarations may overlap, change, or remove columns and constraints.',
                'Static class references show source dependencies, not actual runtime calls.','Middleware inventory is route-level; framework global middleware requires source review.']))

def mermaid_id(key): return 'n'+hashlib.sha256(key.encode()).hexdigest()[:12]
def label(text): return str(text).replace('"',"'").replace('<','&lt;').replace('>','&gt;').replace('\n',' ')

def diagrams(model, definition):
    result=list(definition['diagrams'])
    page_lines=['flowchart LR','  Router["React BrowserRouter"]']
    for page in model['pages']:
        node=mermaid_id(page['id'])
        guard=page['plugin'] or ('CheckoutGuard' if page['checkout_guard'] else 'no wrapper declared here')
        page_lines.append(f'  Router -->|"{label(guard)}"| {node}["{label(page["path"])}"]')
    result.append(dict(id='browser-route-map',title='Browser routes and declared guards',section='pages',description='Router wrappers only. Page components and backend handlers can impose additional controls.',mermaid='\n'.join(page_lines),sources=[p['source'] for p in model['pages']]))
    declared_events=[]
    event_lines=['flowchart LR']
    for module in model['modules']:
        for event,listeners in (module['listeners'] or {}).items():
            for listener in listeners if isinstance(listeners,list) else [listeners]:
                event_lines.append(f'  {mermaid_id(event)}["{label(event.split(chr(92))[-1])}"] -->|"active {label(module["id"])}"| {mermaid_id(listener)}["{label(listener.split(chr(92))[-1])}"]')
                declared_events.append(module['source'])
    if declared_events:
        result.append(dict(id='event-listeners',title='Manifest event subscriptions',section='workflows',description='Manifest-declared subscriptions. ActivationGatedListener invokes listeners only within an initialized tenant with the subscribing plugin active. Queue behavior is determined by each listener/job.',mermaid='\n'.join(event_lines),sources=declared_events))
    lines=['flowchart LR']
    for m in model['modules']:
        lines.append(f'  {mermaid_id(m["id"])}["{label(m["id"])}"]')
    for m in model['modules']:
        for dep in m['dependencies'] or []: lines.append(f'  {mermaid_id(m["id"])} -->|requires| {mermaid_id(dep)}')
    result.append(dict(id='plugin-dependencies',title='Plugin dependencies',section='modules',description='Manifest declarations. A dependency does not imply activation for any tenant.',mermaid='\n'.join(lines),sources=[m['source'] for m in model['modules']]))
    for scope in ['central','tenant']:
        groups=defaultdict(list)
        for t in model['tables']:
            if t['scope']==scope:
                for own in t['owners']: groups[own].append(t)
        for own,tables in groups.items():
            lines=['erDiagram']; seen=set()
            for t in tables:
                node=mermaid_id(t['key']); lines.append(f'  {node}["{label(t["name"])}"] {{')
                columns={c['name']:c for c in t['columns'] if owner(c['source']['file'])==own}
                for name,c in columns.items():
                    if name.startswith('['): continue
                    safe=re.sub(r'[^a-zA-Z0-9_]', '_',name)
                    typ=re.sub(r'[^a-zA-Z0-9_]', '_',c['type'])
                    lines.append(f'    {typ} {safe}'+(' PK' if c.get('primary') else ''))
                if not columns: lines.append('    string declaration_only')
                lines.append('  }')
                for fk in t['foreign_keys']:
                    if owner(fk['source']['file'])!=own: continue
                    edge=(t['name'],fk['column'],fk['target'])
                    if edge in seen: continue
                    seen.add(edge)
                    target=mermaid_id(scope+':'+fk['target'])
                    lines.append(f'  {target}["{label(fk["target"])}"]')
                    lines.append(f'  {target} '+('|o' if fk['nullable'] else '||')+f'..o{{ {node} : "{label(fk["column"])}"')
            result.append(dict(id='schema-'+scope+'-'+own.lower(),title=f'{scope.title()} schema · {own}',section='database',
                description='Migration-declared structure and FK links; not a live or replayed schema. Parent maximum is one; child minimum is zero. Circle markers indicate optional participation. Overlapping definitions and changes are listed below.',
                mermaid='\n'.join(lines),sources=[s for t in tables for s in t['definitions']+[c['source'] for c in t['columns']]+[f['source'] for f in t['foreign_keys']] if owner(s['file'])==own]))
    # One readable route topology per plugin instead of a single large graph.
    for m in model['modules']:
        routes=[r for r in model['routes'] if r['owner']==m['folder']]
        if not routes: continue
        lines=['flowchart LR','  B["HTTP request"]']
        for r in routes:
            rid=mermaid_id(r['id']); handler=mermaid_id(r['action'])
            gate=r['plugin_gate'] or 'no route plugin gate'
            lines.extend([f'  B --> {rid}["{label("|".join(r["methods"]))} /{label(r["uri"])}"]',f'  {rid} -->|"{label(gate)}"| {handler}["{label(r["action"].split(chr(92))[-1])}"]'])
        result.append(dict(id='routes-'+m['id'],title='Routes · '+m['id'],section='routes',description='Registered routes and handlers. Full middleware order, exclusions, and source references are in the route inventory.',mermaid='\n'.join(lines),sources=[r['source'] for r in routes if r['source']]))
    return result

def evidence_check(root, model, definition):
    errors=[]
    required={'contract_version','project','revision','scope','modules','classes','tables','schema_operations','routes','pages','requests','frontend_imports','controls','coverage'}
    if required-model.keys(): errors.append('Missing model fields: '+', '.join(sorted(required-model.keys())))
    route_ids={r['id'] for r in model['routes']}
    for request in model['requests']:
        if set(request['route_candidates'])-route_ids: errors.append('Dangling route candidate: '+request['id'])
    for diagram in definition['diagrams']:
        if not diagram.get('sources'): errors.append('Diagram has no evidence: '+diagram['id'])
        if not re.fullmatch('[a-z0-9-]+',diagram['id']): errors.append('Unsafe diagram identifier')
    evidence=[]
    for key in ['modules','classes','pages','requests','controls']:
        evidence += [e['source'] for e in model[key]]
    evidence += [r['source'] for r in model['routes'] if r['source'] and not r['source']['file'].startswith('vendor/')]
    for table in model['tables']:
        evidence.extend(table['definitions'])
        for key in ['columns','foreign_keys','indexes','changes']: evidence.extend(x['source'] for x in table[key])
    for d in definition['diagrams']: evidence.extend(d.get('sources',[]))
    for item in definition.get('observations',[]): evidence.extend(item.get('sources',[]))
    for ref in evidence:
        file=(root/ref['file']).resolve()
        if not file.is_relative_to(root.resolve()) or not file.is_file(): errors.append('Missing/unsafe evidence '+ref['file']); continue
        lines=file.read_text(encoding='utf-8-sig').splitlines()
        if not 1<=ref['line']<=max(1,len(lines)): errors.append('Invalid evidence line '+ref['file'])
        if ref.get('contains') and ref['contains'] not in '\n'.join(lines[max(0,ref['line']-1):ref['line']+3]): errors.append('Evidence anchor moved: '+ref['file']+':'+str(ref['line']))
    ids=[item['id'] for key in ['modules','classes','tables','routes','pages','requests','controls'] for item in model[key]]
    if len(ids)!=len(set(ids)): errors.append('Duplicate stable identifiers')
    return errors

def source_html(ref):
    if not ref: return 'Framework / unresolved'
    path=html.escape(ref['file']); line=ref['line']
    return f'<a class="source" title="{path}:{line}" href="../../{path}#L{line}">{path}:{line}</a>'

def table_html(headers,rows):
    return '<div class="table-scroll"><table><thead><tr>'+''.join('<th>'+html.escape(h)+'</th>' for h in headers)+'</tr></thead><tbody>'+''.join('<tr>'+''.join('<td>'+str(cell)+'</td>' for cell in row)+'</tr>' for row in rows)+'</tbody></table></div>'

def build_html(output,model,definition,all_diagrams):
    esc=html.escape
    sections={k:[] for k in ['architecture','modules','database','routes','pages','security','workflows','evidence']}
    for d in all_diagrams:
        svg_path=output/'diagrams'/(d['id']+'.svg')
        validate_svg(svg_path)
        svg=svg_path.read_text(encoding='utf-8')
        kind='Entity relationship' if d['mermaid'].startswith('erDiagram') else 'Sequence diagram' if d['mermaid'].startswith('sequenceDiagram') else 'System map'
        sections[d['section']].append(f'<article class="diagram-card" id="{esc(d["id"])}"><div class="diagram-heading"><div><span class="diagram-kind">{kind}</span><h3>{esc(d["title"])}</h3></div><button class="expand" aria-label="Expand {esc(d["title"])}"><svg class="ui-icon"><use href="#i-expand"/></svg>Expand</button></div><p class="diagram-description">{esc(d["description"])}</p><div class="diagram-toolbar"><div class="diagram-tabs" role="tablist" aria-label="Diagram view"><button class="diagram-tab active" role="tab" aria-selected="true" data-view="diagram">Diagram</button><button class="diagram-tab" role="tab" aria-selected="false" data-view="source">Mermaid source</button></div><button class="download-svg" aria-label="Download {esc(d["title"])} as SVG"><svg class="ui-icon"><use href="#i-download"/></svg>SVG</button></div><div class="diagram" tabindex="0" role="region" aria-label="{esc(d["title"])} — scrollable diagram">{svg}</div><div class="diagram-source" hidden><button class="copy-source">Copy source</button><pre><code>{esc(d["mermaid"])}</code></pre></div><div class="diagram-caption"><span class="canvas-dot"></span>Scroll to explore. Expand for zoom and fit.</div><details class="diagram-evidence"><summary><svg class="ui-icon"><use href="#i-code"/></svg>Source evidence<span class="evidence-count">{len(d.get("sources",[]))} references</span></summary><div class="evidence-list">'+''.join('<p>'+source_html(s)+'</p>' for s in d.get('sources',[]))+'</div></details></article>')
    sections['modules'].append(table_html(['Plugin','Dependencies','Providers / listeners','Evidence'],[
        [f'<strong>{esc(m["id"])}</strong><br>{esc(m["name"] or "")}',esc(', '.join(m['dependencies'] or [])),str(len(m['providers'] or []))+' providers · '+str(sum(len(v) if isinstance(v,list) else 1 for v in (m['listeners'] or {}).values()))+' listeners',source_html(m['source'])] for m in model['modules']]))
    dependency_edges=defaultdict(list)
    class_owners={c['name']:c['owner'] for c in model['classes']}
    for c in model['classes']:
        for target in c['references']:
            target_owner=class_owners.get(target)
            if target_owner and target_owner!=c['owner']:
                dependency_edges[(c['owner'],target_owner)].append((target,c['source']))
    sections['modules'].append('<h3>Cross-module source references</h3><p>Qualified PHP references extracted from syntax trees. These are static references, including types and bindings; they do not prove runtime calls or constitute a dependency-rule violation.</p>'+table_html(['Referencing module','Referenced module','Distinct symbols','Example evidence'],[[esc(a),esc(b),str(len({name for name,_ in edges})),source_html(edges[0][1])] for (a,b),edges in sorted(dependency_edges.items())]))
    sections['database'].append('<h3>Schema declaration inventory</h3><p>Columns below are declaration history, including changes. Multiple creates and plugin/baseline overlaps require migration-history review. Index declarations are inventoried separately from column modifiers.</p>')
    for t in model['tables']:
        columns={c['name']:c for c in t['columns']}
        sections['database'].append(f'<details class="entity"><summary>{esc(t["key"])} · {len(columns)} declared column names · {len(t["definitions"])} create declarations</summary><p>Owners: {esc(", ".join(t["owners"]))}</p>'+table_html(['Declared column / shorthand','Type','Declaration modifiers','Source'],[[esc(c['name']),esc(c['type']),esc(' · '.join(x for x,b in [('nullable',c.get('nullable')),('primary',c.get('primary')),('unique',c.get('unique')),('change',c.get('change'))] if b)),source_html(c['source'])] for c in t['columns']])+table_html(['Foreign key','Referenced table','Referenced column','Source'],[[esc(f['column']),esc(f['target']),esc(str(f['referenced_column'])),source_html(f['source'])] for f in t['foreign_keys']])+table_html(['Migration operation','Source'],[[esc(c['operation']+' '+str(c.get('arguments',''))),source_html(c['source'])] for c in t['changes']])+table_html(['Index kind','Columns','Source'],[[esc(i['kind']),esc(str(i['columns'])),source_html(i['source'])] for i in t['indexes']])+'</details>')
    route_rows=[]
    for r in model['routes']:
        middleware='<details><summary>'+str(len(r['expanded_middleware']))+' middleware</summary><ol>'+''.join('<li>'+esc(m)+'</li>' for m in r['expanded_middleware'])+'</ol><p>Excluded: '+esc(', '.join(r['excluded_middleware']))+'</p></details>'
        route_rows.append([esc('|'.join(r['methods'])),f'<code>/{esc(r["uri"])}</code><br>'+esc(r['name'] or ''),esc(r['action']),esc(r['plugin_gate'] or '—'),middleware,source_html(r['source'])])
    sections['routes'].append('<h3>Complete configured route inventory</h3><p>Middleware is expanded and priority-sorted by Laravel for each route. Global middleware and request-dependent behavior need separate review.</p>'+table_html(['Methods','URI / name','Handler','Plugin gate','Middleware','Handler source'],route_rows))
    sections['pages'].append('<h3>Browser routes and guards</h3>'+table_html(['Path','Declared guard','Element','Source'],[[f'<code>{esc(p["path"])}</code>',esc(p['plugin'] or ('CheckoutGuard' if p['checkout_guard'] else 'No wrapper declared here')),esc(p['element']),source_html(p['source'])] for p in model['pages']]))
    route_lookup={r['id']:r for r in model['routes']}
    sections['pages'].append('<h3>Frontend request call sites</h3><p>Method/URL matches below are route candidates. They do not establish that every page executes a request. Parameterized patterns may have multiple candidates.</p>'+table_html(['Method','URL expression','Matching route candidates','Source'],[[esc(r['method']),esc(r['url']),'<br>'.join(esc(route_lookup[x]['name'] or route_lookup[x]['uri']) for x in r['route_candidates']) or 'Unresolved',source_html(r['source'])] for r in model['requests']]))
    sections['security'].append('<h3>Authorization and visibility declarations</h3><p>This inventory locates FormRequest authorization, policy methods, panel access, and gate registrations/calls. It does not assert complete access-control coverage.</p>'+table_html(['Symbol','Module','Source'],[[esc(c['symbol']),esc(c['owner']),source_html(c['source'])] for c in model['controls']]))
    for observation in definition.get('observations',[]):
        sections[observation['section']].append('<article class="note"><span class="badge">'+esc(observation['evidence'])+'</span><h3>'+esc(observation['title'])+'</h3><p>'+esc(observation['description'])+'</p>'+''.join('<p>'+source_html(s)+'</p>' for s in observation['sources'])+'</article>')
    sections['evidence'].append('<h3>Coverage and interpretation</h3><ul>'+''.join('<li>'+esc(x)+'</li>' for x in model['coverage']['limitations'])+'</ul><p>'+esc(model['coverage']['schema'])+'</p><p>'+esc(model['coverage']['request_extraction'])+'</p><p>Revision at analysis: <code>'+esc(model['revision'])+'</code>. The content hashes identify the analyzed working tree, including uncommitted source changes.</p>')
    names={'architecture':'Architecture','modules':'Plugins & dependencies','database':'Database schema','routes':'Routes & middleware','pages':'Pages & requests','security':'Access controls','workflows':'Request workflows','evidence':'Evidence & coverage'}
    groups={'overview':'Getting started','architecture':'System design','modules':'System design','database':'Reference','routes':'Reference','pages':'Reference','security':'Reference','workflows':'Execution','evidence':'Quality'}
    icons={'overview':'book','architecture':'layers','modules':'cube','database':'database','routes':'route','pages':'window','security':'shield','workflows':'flow','evidence':'check'}
    counts={'architecture':sum(d['section']=='architecture' for d in all_diagrams),'modules':len(model['modules']),'database':len(model['tables']),'routes':len(model['routes']),'pages':len(model['pages']),'security':len(model['controls']),'workflows':sum(d['section']=='workflows' for d in all_diagrams)}
    nav=''; last_group=None
    for key,name in {'overview':'Overview',**names}.items():
        if groups[key]!=last_group: nav+=f'<div class="nav-group">{groups[key]}</div>'; last_group=groups[key]
        nav+=f'<a href="#{key}" data-page="{key}"><svg class="ui-icon"><use href="#i-{icons[key]}"/></svg><span>{name}</span>'+ (f'<span class="nav-count">{counts[key]}</span>' if key in counts else '')+'</a>'
    descriptions={'architecture':'The system in context, its applications, and the boundaries between components.','modules':'How plugin modules are registered, connected, and activated for a tenant.','database':'Explore central and tenant data structures, with migration evidence for every declaration.','routes':'Trace HTTP entry points, handlers, middleware, and plugin activation gates.','pages':'Connect browser routes and UI guards to frontend request call sites.','security':'Inspect authentication, ownership checks, authorization, and visibility declarations.','workflows':'Follow critical requests through validation, persistence, events, and integrations.','evidence':'Understand what was inspected, how it was extracted, and what remains unverified.'}
    body=''.join(f'<section class="doc-page" id="{key}" data-title="{name}" data-group="{groups[key]}" hidden><header class="page-header"><div class="page-eyebrow">{groups[key]}</div><h1>{name}</h1><p>{descriptions[key]}</p></header>'+''.join(sections[key])+'</section>' for key,name in names.items())
    stats=[('Plugins',len(model['modules'])),('Routes',len(model['routes'])),('Browser routes',len(model['pages'])),('Tables',len(model['tables'])),('API call sites',len(model['requests'])),('Diagrams',len(all_diagrams))]
    template=(SCRIPTS/'viewer.html').read_text(encoding='utf-8')
    values={'TITLE':esc(definition['title']),'PROJECT':esc(model['project'].replace('_',' ').title()),'STACK':esc(definition.get('stack','Engineering documentation')),'REVISION':esc(model['revision'][:8]),'NAV':nav,'BODY':body,'STATS':''.join(f'<div><span>{k}</span><strong>{v}<span class="stat-unit"> indexed</span></strong></div>' for k,v in stats),'SUMMARY':esc(definition['summary'])}
    for key,value in values.items(): template=template.replace('{{'+key+'}}',value)
    (output/'index.html').write_text(template,encoding='utf-8')

def write_docs(output,model,definition,all_diagrams):
    for d in all_diagrams:
        (output/'diagrams').mkdir(exist_ok=True)
        (output/'diagrams'/(d['id']+'.mmd')).write_text(d['mermaid']+'\n',encoding='utf-8')
    active={d['id'] for d in all_diagrams}
    for path in (output/'diagrams').iterdir():
        if path.suffix in {'.svg','.mmd'} and path.stem not in active: path.unlink()
    lines=['# '+definition['title'],'',definition['summary'],'','Open [the offline explorer](index.html). All SVG diagrams and inventories are embedded in that file.','','## Rebuild','','```sh','python .agents/skills/engineering-atlas/scripts/atlas.py build . --observe --render-deps /path/to/renderer-dependencies','python .agents/skills/engineering-atlas/scripts/atlas.py check .','```','','`--observe` boots the configured application to read route registration. Review provider side effects first; it does not dispatch a request or initialize a tenant. Install renderer dependencies in a separate directory using the skill tooling/package.json.','','## Views','']
    for d in all_diagrams: lines.extend(['### '+d['title'],'',d['description'],'','```mermaid',d['mermaid'],'```',''])
    lines.extend(['## Coverage','']+['- '+x for x in model['coverage']['limitations']])
    (output/'README.md').write_text('\n'.join(lines)+'\n',encoding='utf-8')

def seal(root, analyzed_sources=None):
    output=root/OUTPUT
    current=source_inventory(root)
    if analyzed_sources is not None and current!=analyzed_sources:
        raise RuntimeError('Source inputs changed during generation; rebuild before accepting this atlas.')
    validate_diagrams(output)
    artifacts={p.relative_to(output).as_posix():file_digest(p) for p in sorted(output.rglob('*')) if p.is_file() and p.name not in {'manifest.json','definition.json'}}
    write_json(output/'manifest.json',dict(contract_version=VERSION,sources=current,artifacts=artifacts))

def check(root):
    output=root/OUTPUT; manifest=read_json(output/'manifest.json'); errors=[]
    current=source_inventory(root)
    if current != manifest['sources']:
        changed=sorted(k for k in current.keys() | manifest['sources'].keys() if current.get(k)!=manifest['sources'].get(k))
        errors.append('Atlas is stale; changed inputs: '+', '.join(changed[:12]))
    for name,expected in manifest['artifacts'].items():
        path=output/name
        if not path.is_file() or file_digest(path)!=expected: errors.append('Missing or modified artifact: '+name)
    model=read_json(output/'model.json'); definition=read_json(output/'definition.json')
    if model['routes']:
        snapshot=output/'route-snapshot.json'
        if not snapshot.is_file() or read_json(snapshot).get('source_fingerprint')!=registration_fingerprint(root,current):
            errors.append('Registered route snapshot is stale. Refresh with build --observe.')
    errors.extend(evidence_check(root,model,definition))
    try: validate_diagrams(output)
    except RuntimeError as error: errors.append(str(error))
    if errors: raise RuntimeError('\n'.join(errors))
    print(f'Atlas valid: {len(current)} source files, {len(manifest["artifacts"])} artifacts; SVG XML, evidence references and source hashes match.')

def main():
    parser=argparse.ArgumentParser(description=__doc__)
    parser.add_argument('command',choices=['build','check'])
    parser.add_argument('root',type=Path)
    parser.add_argument('--observe',action='store_true')
    parser.add_argument('--render-deps',type=Path)
    args=parser.parse_args(); root=args.root.resolve(); output=root/OUTPUT
    if args.command=='check': check(root); return
    analyzed_sources=source_inventory(root)
    definition=read_json(output/'definition.json')
    if args.command=='build':
        model=extract(root,args.observe)
        errors=evidence_check(root,model,definition)
        if errors: raise RuntimeError('\n'.join(errors))
        all_diagrams=diagrams(model,definition)
        write_json(output/'model.json',model); write_json(output/'diagram-index.json',all_diagrams)
        write_docs(output,model,definition,all_diagrams)
        if not args.render_deps: raise RuntimeError('Sources generated; SVG rendering remains required. Supply --render-deps before accepting this atlas.')
        completed=subprocess.run(['node',str(SCRIPTS/'render.cjs'),str(output),str(args.render_deps.resolve())],cwd=root)
        if completed.returncode: raise RuntimeError('Diagram rendering failed; atlas was not sealed.')
    else:
        model=read_json(output/'model.json'); all_diagrams=read_json(output/'diagram-index.json')
    for d in all_diagrams:
        if not (output/'diagrams'/(d['id']+'.svg')).exists(): raise RuntimeError('Missing rendered diagram: '+d['id'])
    build_html(output,model,definition,all_diagrams); seal(root,analyzed_sources); check(root)
    print('Built '+str(output/'index.html'))

if __name__=='__main__':
    try: main()
    except (RuntimeError,FileNotFoundError,ValueError) as error:
        print(str(error),file=sys.stderr); sys.exit(1)

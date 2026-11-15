#!/usr/bin/env python3
"""Stack-agnostic repository discovery for documentation work.

Produces a factual starting inventory: languages, manifests and frameworks,
runnable scripts, entry-point candidates, deployment and CI assets, environment
variable *names* (never values), tests, existing docs, and git history shape.
The output is a reading list for the documentation author, not documentation.

Usage: python discover.py <repo> [--json] [--out FILE]
"""
import argparse
import json
import os
import re
import subprocess
import sys
from collections import Counter
from pathlib import Path

SKIP_DIRS = {
    '.git', '.hg', '.svn', 'node_modules', 'vendor', 'bower_components', '.venv', 'venv', 'env',
    '__pycache__', '.mypy_cache', '.pytest_cache', '.tox', 'dist', 'build', 'out', 'target', '.next',
    '.nuxt', '.svelte-kit', '.turbo', '.cache', 'coverage', '.idea', '.vscode', '.gradle', 'Pods',
    'DerivedData', '.dart_tool', '.terraform', '.agents', '.claude',
}
TOP_LEVEL_SKIP = {'storage', 'tmp', 'logs', 'out', 'env', 'var'}
SKIP_PATHS = {'bootstrap/cache', 'public/build', 'public/hot', 'docs/engineering-atlas/diagrams'}
MAX_FILES = 60000

LANGUAGES = {
    '.py': 'Python', '.php': 'PHP', '.js': 'JavaScript', '.mjs': 'JavaScript', '.cjs': 'JavaScript',
    '.jsx': 'JavaScript', '.ts': 'TypeScript', '.tsx': 'TypeScript', '.vue': 'Vue', '.svelte': 'Svelte',
    '.go': 'Go', '.rs': 'Rust', '.java': 'Java', '.kt': 'Kotlin', '.kts': 'Kotlin', '.scala': 'Scala',
    '.rb': 'Ruby', '.ex': 'Elixir', '.exs': 'Elixir', '.cs': 'C#', '.fs': 'F#', '.swift': 'Swift',
    '.m': 'Objective-C', '.dart': 'Dart', '.c': 'C', '.h': 'C/C++ header', '.cpp': 'C++', '.cc': 'C++',
    '.hpp': 'C++', '.lua': 'Lua', '.sql': 'SQL', '.sh': 'Shell', '.ps1': 'PowerShell',
    '.tf': 'Terraform', '.proto': 'Protocol Buffers', '.graphql': 'GraphQL', '.gql': 'GraphQL',
    '.blade.php': 'Blade', '.erb': 'ERB', '.html': 'HTML', '.css': 'CSS', '.scss': 'SCSS',
}

MANIFESTS = {
    'package.json': 'Node.js', 'composer.json': 'PHP/Composer', 'pyproject.toml': 'Python',
    'requirements.txt': 'Python', 'setup.py': 'Python', 'Pipfile': 'Python', 'go.mod': 'Go',
    'Cargo.toml': 'Rust', 'pom.xml': 'Java/Maven', 'build.gradle': 'JVM/Gradle',
    'build.gradle.kts': 'JVM/Gradle', 'Gemfile': 'Ruby', 'mix.exs': 'Elixir', 'pubspec.yaml': 'Dart/Flutter',
    'Package.swift': 'Swift', 'deno.json': 'Deno', 'deno.jsonc': 'Deno', 'bun.lockb': 'Bun',
}
MANIFEST_SUFFIXES = {'.csproj': '.NET', '.sln': '.NET', '.fsproj': '.NET'}

# dependency name (exact or prefix ending in '/') -> framework label
FRAMEWORKS = {
    'laravel/framework': 'Laravel', 'symfony/framework-bundle': 'Symfony', 'filament/filament': 'Filament',
    'livewire/livewire': 'Livewire', 'inertiajs/inertia-laravel': 'Inertia', 'stancl/tenancy': 'stancl/tenancy',
    'slim/slim': 'Slim', 'cakephp/cakephp': 'CakePHP', 'yiisoft/yii2': 'Yii2', 'wordpress': 'WordPress',
    'django': 'Django', 'flask': 'Flask', 'fastapi': 'FastAPI', 'starlette': 'Starlette', 'celery': 'Celery',
    'sqlalchemy': 'SQLAlchemy', 'pydantic': 'Pydantic', 'streamlit': 'Streamlit', 'airflow': 'Airflow',
    'express': 'Express', 'fastify': 'Fastify', 'koa': 'Koa', '@nestjs/core': 'NestJS', 'next': 'Next.js',
    'nuxt': 'Nuxt', 'react': 'React', 'react-native': 'React Native', 'expo': 'Expo', 'vue': 'Vue',
    '@angular/core': 'Angular', 'svelte': 'Svelte', '@sveltejs/kit': 'SvelteKit', 'solid-js': 'Solid',
    'astro': 'Astro', 'remix': 'Remix', '@remix-run/node': 'Remix', 'electron': 'Electron', 'vite': 'Vite',
    'webpack': 'webpack', 'prisma': 'Prisma', '@prisma/client': 'Prisma', 'typeorm': 'TypeORM',
    'sequelize': 'Sequelize', 'mongoose': 'Mongoose', 'drizzle-orm': 'Drizzle', 'graphql': 'GraphQL',
    '@apollo/server': 'Apollo Server', 'socket.io': 'Socket.IO', 'bullmq': 'BullMQ', 'tailwindcss': 'Tailwind',
    'rails': 'Rails', 'sinatra': 'Sinatra', 'sidekiq': 'Sidekiq', 'phoenix': 'Phoenix',
    'github.com/gin-gonic/gin': 'Gin', 'github.com/labstack/echo': 'Echo', 'github.com/gofiber/fiber': 'Fiber',
    'github.com/go-chi/chi': 'chi', 'gorm.io/gorm': 'GORM', 'actix-web': 'Actix', 'axum': 'Axum',
    'rocket': 'Rocket', 'tokio': 'Tokio', 'diesel': 'Diesel', 'sqlx': 'SQLx',
    'org.springframework.boot': 'Spring Boot', 'io.quarkus': 'Quarkus', 'io.micronaut': 'Micronaut',
    'flutter': 'Flutter',
}

DEPLOY_PATTERNS = [
    (r'(^|/)Dockerfile[^/]*$', 'container image'), (r'(^|/)(docker-)?compose[^/]*\.ya?ml$', 'compose stack'),
    (r'(^|/)Procfile$', 'Procfile processes'), (r'(^|/)Chart\.yaml$', 'Helm chart'),
    (r'\.tf$', 'Terraform'), (r'(^|/)serverless\.ya?ml$', 'Serverless Framework'), (r'(^|/)fly\.toml$', 'Fly.io'),
    (r'(^|/)render\.ya?ml$', 'Render'), (r'(^|/)vercel\.json$', 'Vercel'), (r'(^|/)netlify\.toml$', 'Netlify'),
    (r'(^|/)app\.ya?ml$', 'App Engine / app spec'), (r'(^|/)\.platform\.app\.yaml$', 'Platform.sh'),
    (r'(^|/)nixpacks\.toml$', 'Nixpacks'), (r'(^|/)railway\.(json|toml)$', 'Railway'),
    (r'(^|/)Envoy\.blade\.php$', 'Laravel Envoy'), (r'(^|/)deploy\.php$', 'Deployer'), (r'(^|/)Capfile$', 'Capistrano'),
    (r'(^|/)(deploy|release|provision)[^/]*\.(sh|ps1|py)$', 'deploy script'),
    (r'(^|/)(supervisor[^/]*\.conf|[^/]+\.service)$', 'process supervisor'), (r'(^|/)nginx[^/]*\.conf$', 'nginx config'),
    (r'(^|/)(k8s|kubernetes|manifests|deploy|helm|charts|infra|infrastructure|ansible|terraform)/', 'infrastructure directory'),
    (r'(^|/)(ecosystem\.config\.[cm]?js|pm2\.json)$', 'PM2'), (r'(^|/)appspec\.ya?ml$', 'AWS CodeDeploy'),
    (r'(^|/)(cloudbuild|buildspec)\.ya?ml$', 'cloud build'),
]
CI_PATTERNS = [
    (r'^\.github/workflows/.+\.ya?ml$', 'GitHub Actions'), (r'^\.gitlab-ci\.yml$', 'GitLab CI'),
    (r'(^|/)Jenkinsfile$', 'Jenkins'), (r'^\.circleci/', 'CircleCI'), (r'(^|/)azure-pipelines\.ya?ml$', 'Azure Pipelines'),
    (r'(^|/)bitbucket-pipelines\.yml$', 'Bitbucket Pipelines'), (r'^\.drone\.yml$', 'Drone'),
    (r'^\.buildkite/', 'Buildkite'), (r'^\.travis\.yml$', 'Travis CI'),
]
TEST_CONFIG = re.compile(r'(^|/)(phpunit\.xml(\.dist)?|pest\.php|jest\.config\.[a-z]+|vitest\.config\.[a-z]+|'
                         r'playwright\.config\.[a-z]+|cypress\.config\.[a-z]+|karma\.conf\.js|pytest\.ini|tox\.ini|'
                         r'conftest\.py|\.rspec|codecept(ion)?\.ya?ml|behat\.yml)$')
TEST_DIR = re.compile(r'(^|/)(tests?|spec|specs|__tests__|e2e|integration_tests|test_[^/]+)/')
ENTRY_POINTS = re.compile(
    r'(^|/)(routes/[^/]+\.(php|rb|js|ts)|config/routes\.rb|urls\.py|wsgi\.py|asgi\.py|manage\.py|artisan|'
    r'main\.(go|py|rs|ts|js|kt|java|dart|swift)|app\.(py|js|ts)|server\.(js|ts|py)|index\.(js|ts)|'
    r'cmd/[^/]+/main\.go|Program\.cs|Startup\.cs|[^/]*Application\.(java|kt)|bin/[^/]+|'
    r'src/(main|index|app|App)\.(tsx|ts|jsx|js|vue)|pages/_app\.[jt]sx?|app/layout\.[jt]sx?|'
    r'(console|kernel|Kernel)\.php|[^/]*(Controller|Handler|Resolver|Consumer|Listener|Job|Command|Worker)\.[a-z]+)$')
SCHEMA = re.compile(r'(^|/)(migrations?|db/migrate|alembic|prisma/schema\.prisma|schema\.(sql|graphql|rb)|'
                    r'[^/]*\.sql$|models?/|entities/|entity/)')
DOC_FILES = re.compile(r'(^|/)(README|CHANGELOG|CHANGES|HISTORY|CONTRIBUTING|SECURITY|UPGRADING|RELEASING|'
                       r'DEPLOY(MENT)?|RUNBOOK|ARCHITECTURE)[^/]*\.(md|rst|txt|adoc)$|^docs?/|(^|/)adr/|(^|/)decisions/', re.I)
DOC_ENGINES = {
    'mkdocs.yml': 'MkDocs', 'docusaurus.config.js': 'Docusaurus', 'docusaurus.config.ts': 'Docusaurus',
    'conf.py': 'Sphinx (if under docs/)', 'book.toml': 'mdBook', '.vitepress/config.ts': 'VitePress',
    '.vitepress/config.js': 'VitePress', 'antora.yml': 'Antora', 'hugo.toml': 'Hugo', '_config.yml': 'Jekyll',
    'astro.config.mjs': 'Astro/Starlight (check)', 'mint.json': 'Mintlify', 'docs.json': 'Mintlify (check)',
}
ENV_FILES = re.compile(r'(^|/)\.env\.(example|sample|dist|template|defaults)$|(^|/)(example|sample)\.env$')
ENV_USAGE = [
    re.compile(r"""\benv\(\s*['"]([A-Z][A-Z0-9_]+)['"]"""), re.compile(r'process\.env\.([A-Z][A-Z0-9_]+)'),
    re.compile(r"""process\.env\[['"]([A-Z][A-Z0-9_]+)['"]\]"""), re.compile(r'import\.meta\.env\.([A-Z][A-Z0-9_]+)'),
    re.compile(r"""os\.(?:environ(?:\.get)?|getenv)\(?\[?\s*['"]([A-Z][A-Z0-9_]+)['"]"""),
    re.compile(r"""getenv\(\s*['"]([A-Z][A-Z0-9_]+)['"]"""), re.compile(r"""ENV\[['"]([A-Z][A-Z0-9_]+)['"]\]"""),
    re.compile(r"""ENV\.fetch\(['"]([A-Z][A-Z0-9_]+)['"]"""), re.compile(r'os\.Getenv\("([A-Z][A-Z0-9_]+)"\)'),
    re.compile(r'std::env::var\("([A-Z][A-Z0-9_]+)"\)'), re.compile(r'System\.getenv\("([A-Z][A-Z0-9_]+)"\)'),
    re.compile(r'Environment\.GetEnvironmentVariable\("([A-Z][A-Z0-9_]+)"\)'),
]
CODE_EXT = {'.php', '.py', '.js', '.mjs', '.cjs', '.ts', '.tsx', '.jsx', '.rb', '.go', '.rs', '.java', '.kt', '.cs', '.ex', '.exs', '.vue', '.svelte'}


def walk(root):
    count = 0
    for dirpath, dirnames, filenames in os.walk(root):
        rel_dir = Path(dirpath).relative_to(root).as_posix()
        rel_dir = '' if rel_dir == '.' else rel_dir
        dirnames[:] = sorted(d for d in dirnames
                             if d not in SKIP_DIRS and not (rel_dir == '' and d in TOP_LEVEL_SKIP)
                             and f'{rel_dir}/{d}'.lstrip('/') not in SKIP_PATHS)
        for name in sorted(filenames):
            count += 1
            if count > MAX_FILES:
                return
            yield (f'{rel_dir}/{name}' if rel_dir else name)


def language_of(path):
    lower = path.lower()
    if lower.endswith('.blade.php'):
        return 'Blade'
    return LANGUAGES.get(Path(lower).suffix)


def read_text(path, limit=400_000):
    try:
        with open(path, 'r', encoding='utf-8', errors='replace') as handle:
            return handle.read(limit)
    except OSError:
        return ''


def toml_section(text, section):
    """Return key names from a [section] of a TOML file without a TOML parser (py<3.11 safe)."""
    match = re.search(r'^\[' + re.escape(section) + r'\]\s*$(.*?)(?=^\[|\Z)', text, re.M | re.S)
    if not match:
        return []
    return re.findall(r'^\s*"?([A-Za-z0-9_.\-/@]+)"?\s*=', match.group(1), re.M)


def dependencies_of(root, manifest):
    path = root / manifest
    name = Path(manifest).name
    text = read_text(path)
    deps, scripts = [], {}
    try:
        if name in ('package.json', 'composer.json'):
            data = json.loads(text)
            for key in ('dependencies', 'devDependencies', 'peerDependencies', 'require', 'require-dev'):
                deps += list((data.get(key) or {}).keys())
            raw = data.get('scripts') or {}
            scripts = {k: (v if isinstance(v, str) else ' && '.join(map(str, v))) for k, v in raw.items()}
        elif name in ('requirements.txt',) or name.startswith('requirements'):
            deps = [re.split(r'[<>=~!\[; ]', line.strip(), 1)[0] for line in text.splitlines()
                    if line.strip() and not line.startswith(('#', '-'))]
        elif name == 'pyproject.toml':
            deps = re.findall(r'^\s*"([A-Za-z0-9_.\-]+)', text, re.M)
            deps += toml_section(text, 'tool.poetry.dependencies')
            scripts = {k: '(entry point)' for k in toml_section(text, 'project.scripts') + toml_section(text, 'tool.poetry.scripts')}
        elif name == 'go.mod':
            deps = re.findall(r'^\s*(?:require\s+)?([a-z0-9.\-]+\.[a-z]+/[^\s]+)\s+v', text, re.M)
        elif name == 'Cargo.toml':
            deps = toml_section(text, 'dependencies') + toml_section(text, 'dev-dependencies')
        elif name == 'Gemfile':
            deps = re.findall(r"""^\s*gem\s+['"]([^'"]+)""", text, re.M)
        elif name in ('pom.xml',):
            deps = re.findall(r'<groupId>([^<]+)</groupId>', text)
        elif name.startswith('build.gradle'):
            deps = re.findall(r"""['"]([a-zA-Z0-9_.\-]+):[a-zA-Z0-9_.\-]+""", text)
            deps += re.findall(r'id\s*\(?\s*["\']([a-zA-Z0-9_.\-]+)', text)
        elif name == 'mix.exs':
            deps = re.findall(r'\{:([a-z_]+),', text)
        elif name == 'pubspec.yaml':
            deps = re.findall(r'^\s{2}([a-z_]+):', text, re.M)
    except (ValueError, AttributeError):
        pass
    return sorted(set(d for d in deps if d)), scripts


def detect_frameworks(deps):
    found = set()
    for dep in deps:
        lower = dep.lower()
        if lower in FRAMEWORKS:
            found.add(FRAMEWORKS[lower])
        for key, label in FRAMEWORKS.items():
            if '/' in key and '.' in key.split('/')[0] and lower.startswith(key):
                found.add(label)
            if key.startswith('org.') or key.startswith('io.'):
                if lower.startswith(key):
                    found.add(label)
    return sorted(found)


def make_targets(root, files):
    targets = {}
    for rel in files:
        if Path(rel).name in ('Makefile', 'makefile', 'GNUmakefile', 'justfile', 'Justfile', 'Taskfile.yml') and '/' not in rel:
            text = read_text(root / rel)
            if Path(rel).name.lower() == 'taskfile.yml':
                names = re.findall(r'^  ([A-Za-z0-9_:\-]+):\s*$', text, re.M)
            else:
                names = re.findall(r'^([A-Za-z0-9_][A-Za-z0-9_\-.]*)\s*:(?!=)', text, re.M)
            targets[rel] = sorted(set(n for n in names if not n.startswith('.')))
    return targets


def env_names(root, files):
    declared, used = {}, {}
    for rel in files:
        if ENV_FILES.search(rel):
            for line in read_text(root / rel).splitlines():
                m = re.match(r'^\s*(?:export\s+)?([A-Z][A-Z0-9_]*)\s*=', line)
                if m:
                    declared.setdefault(m.group(1), rel)
    for rel in files:
        if Path(rel).suffix in CODE_EXT and not TEST_DIR.search(rel):
            text = read_text(root / rel, 200_000)
            for pattern in ENV_USAGE:
                for name in pattern.findall(text):
                    used.setdefault(name, rel)
    return declared, used


def git(root, *args):
    try:
        result = subprocess.run(['git', '-C', str(root), *args], capture_output=True, text=True,
                                encoding='utf-8', errors='replace', timeout=60)
        return result.stdout.strip() if result.returncode == 0 else None
    except (OSError, subprocess.SubprocessError):
        return None


def git_summary(root):
    head = git(root, 'rev-parse', 'HEAD')
    if head is None:
        return {'available': False}
    tags = git(root, 'for-each-ref', '--sort=creatordate', '--format=%(refname:short)\t%(creatordate:short)', 'refs/tags') or ''
    status = git(root, 'status', '--porcelain') or ''
    roots = (git(root, 'log', '--reverse', '--format=%as', '--max-parents=0', 'HEAD') or '').splitlines()
    return {
        'available': True,
        'head': head,
        'branch': git(root, 'rev-parse', '--abbrev-ref', 'HEAD'),
        'commits': int(git(root, 'rev-list', '--count', 'HEAD') or 0),
        'first_commit': roots[0] if roots else None,
        'last_commit': git(root, 'log', '-1', '--format=%as'),
        'tags': [dict(zip(('name', 'date'), line.split('\t'))) for line in tags.splitlines() if line],
        'uncommitted_paths': len([line for line in status.splitlines() if line.strip()]),
        'conventional_commit_ratio': conventional_ratio(git(root, 'log', '-200', '--no-merges', '--format=%s') or ''),
    }


def conventional_ratio(subjects):
    lines = [s for s in subjects.splitlines() if s.strip()]
    if not lines:
        return 0.0
    hits = sum(1 for s in lines if re.match(r'^[a-z]+(\([^)]*\))?!?:\s', s))
    return round(hits / len(lines), 2)


def discover(root):
    root = root.resolve()
    files = list(walk(root))
    languages = Counter(filter(None, (language_of(f) for f in files)))
    manifests, all_deps, scripts = [], [], {}
    for rel in files:
        name = Path(rel).name
        kind = MANIFESTS.get(name) or MANIFEST_SUFFIXES.get(Path(rel).suffix)
        if name.startswith('requirements') and name.endswith('.txt'):
            kind = 'Python'
        if kind:
            deps, script_map = dependencies_of(root, rel)
            manifests.append({'file': rel, 'ecosystem': kind, 'dependency_count': len(deps),
                              'frameworks': detect_frameworks(deps)})
            all_deps += deps
            if script_map:
                scripts[rel] = script_map
    docs_engines = sorted({label for rel in files for key, label in DOC_ENGINES.items()
                           if rel == key or rel.endswith('/' + key) and (key != 'conf.py' or rel.startswith('doc'))})
    declared_env, used_env = env_names(root, files)
    return {
        'root': root.name,
        'file_count': len(files),
        'truncated': len(files) >= MAX_FILES,
        'languages': languages.most_common(),
        'manifests': manifests,
        'frameworks': detect_frameworks(all_deps),
        'scripts': scripts,
        'task_runners': make_targets(root, files),
        'entry_point_candidates': [f for f in files if ENTRY_POINTS.search(f) and not TEST_DIR.search(f)],
        'schema_candidates': [f for f in files if SCHEMA.search(f) and language_of(f)],
        'deployment_assets': [{'file': f, 'kind': kind} for f in files for pat, kind in DEPLOY_PATTERNS if re.search(pat, f)],
        'ci': [{'file': f, 'kind': kind} for f in files for pat, kind in CI_PATTERNS if re.search(pat, f)],
        'tests': {'configs': [f for f in files if TEST_CONFIG.search(f)],
                  'test_file_count': sum(1 for f in files if TEST_DIR.search(f) and language_of(f))},
        'env': {'declared_in_examples': declared_env, 'referenced_in_code_only': {k: v for k, v in used_env.items() if k not in declared_env}},
        'existing_docs': [f for f in files if DOC_FILES.search(f) and Path(f).suffix.lower() in ('.md', '.rst', '.txt', '.adoc', '.mdx')],
        'docs_engines': docs_engines,
        'git': git_summary(root),
    }


def dedupe_files(items):
    seen, result = set(), []
    for item in items:
        if item['file'] not in seen:
            seen.add(item['file'])
            result.append(item)
    return result


def bullet_list(items, limit=40):
    lines = [f'- `{i}`' for i in items[:limit]]
    if len(items) > limit:
        lines.append(f'- ... {len(items) - limit} more (use --json for the full list)')
    return lines or ['- none found']


def to_markdown(r):
    out = [f"# Discovery report: {r['root']}", '',
           'Starting inventory for documentation. Every item is a lead to read, not a documented fact.', '',
           f"Files scanned: {r['file_count']}" + (' (truncated)' if r['truncated'] else ''), '']
    g = r['git']
    if g.get('available'):
        out += ['## Git', f"- HEAD `{g['head'][:12]}` on `{g['branch']}`; {g['commits']} commits from {g['first_commit']} to {g['last_commit']}",
                f"- Tags: {len(g['tags'])}" + (f" (latest `{g['tags'][-1]['name']}` {g['tags'][-1]['date']})" if g['tags'] else ' - changelog must be grouped by date/merge, see references/changelog.md'),
                f"- Uncommitted paths: {g['uncommitted_paths']}", f"- Conventional-commit ratio (last 200): {g['conventional_commit_ratio']}", '']
    out += ['## Languages'] + [f'- {lang}: {n} files' for lang, n in r['languages'][:12]] + ['']
    out += ['## Manifests and frameworks'] + [f"- `{m['file']}` ({m['ecosystem']}, {m['dependency_count']} deps)" + (f": {', '.join(m['frameworks'])}" if m['frameworks'] else '') for m in r['manifests']] + ['']
    out += ['## Runnable scripts']
    for file, scripts in r['scripts'].items():
        out.append(f'- `{file}`')
        out += [f'  - `{name}`: `{cmd[:140]}`' for name, cmd in scripts.items()]
    for file, targets in r['task_runners'].items():
        out.append(f"- `{file}` targets: {', '.join(targets) or 'none'}")
    out += ['', '## Entry-point candidates'] + bullet_list(r['entry_point_candidates'], 60)
    out += ['', '## Schema / data-model candidates'] + bullet_list(r['schema_candidates'], 40)
    out += ['', '## Deployment assets'] + [f"- `{d['file']}` ({d['kind']})" for d in dedupe_files(r['deployment_assets'])[:60]]
    if not r['deployment_assets']:
        out.append('- none found: derive a recommended procedure from runtime requirements (references/deployment-and-operations.md)')
    out += ['', '## CI'] + ([f"- `{c['file']}` ({c['kind']})" for c in r['ci']] or ['- none found'])
    out += ['', '## Tests', f"- Test files: {r['tests']['test_file_count']}"] + [f'- config `{c}`' for c in r['tests']['configs']]
    env = r['env']
    out += ['', '## Environment variables (names only)']
    out += [f'- `{k}` (declared in `{v}`)' for k, v in sorted(env['declared_in_examples'].items())][:120]
    if env['referenced_in_code_only']:
        out += ['', 'Referenced in code but absent from example env files (document or flag):']
        out += [f'- `{k}` (first seen `{v}`)' for k, v in sorted(env['referenced_in_code_only'].items())][:80]
    out += ['', '## Existing documentation'] + bullet_list(r['existing_docs'], 80)
    out += ['', f"Docs engine: {', '.join(r['docs_engines']) or 'none detected (use docs/README.md + Markdown)'}", '']
    return '\n'.join(out)


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    parser.add_argument('root', type=Path)
    parser.add_argument('--json', action='store_true', help='emit JSON instead of Markdown')
    parser.add_argument('--out', type=Path, help='write to a file instead of stdout')
    args = parser.parse_args(argv)
    if not args.root.is_dir():
        parser.error(f'{args.root} is not a directory')
    report = discover(args.root)
    text = json.dumps(report, indent=2) if args.json else to_markdown(report)
    if args.out:
        args.out.write_text(text + '\n', encoding='utf-8')
    else:
        sys.stdout.reconfigure(encoding='utf-8')
        print(text)


if __name__ == '__main__':
    main()

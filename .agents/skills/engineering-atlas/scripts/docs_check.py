#!/usr/bin/env python3
"""Structural checks for a project handbook (any stack, plain Markdown).

Checks: required pages exist; relative links and heading/line anchors resolve; pages are
reachable from the index; feature pages contain the required section groups and link to
source; template placeholders were replaced; Mermaid fences declare a known diagram type;
the changelog has dated/versioned entries. Passing proves structure, not accuracy.

Configure with docs/handbook.json (see references/maintenance-and-validation.md).
Usage: python docs_check.py <repo> [--config FILE] [--strict]
"""
import argparse
import json
import re
import sys
from collections import deque
from pathlib import Path
from urllib.parse import unquote

DEFAULT_CONFIG = {
    'index': 'docs/README.md',
    'required': [
        'docs/README.md',
        'docs/overview.md',
        'docs/getting-started.md',
        'docs/architecture.md',
        'docs/data-model.md',
        'docs/interfaces.md|docs/api.md|docs/api/README.md',
        'docs/configuration.md',
        'docs/development.md',
        'docs/features/README.md',
        'docs/deployment.md',
        'docs/operations.md|docs/runbook.md',
        'docs/coverage.md',
        'CHANGELOG.md|docs/changelog.md',
    ],
    'not_applicable': [],
    'feature_dir': 'docs/features',
    'feature_catalog': 'docs/features/README.md',
    'feature_sections': [
        ['how to use', 'user guide', 'usage', 'using'],
        ['how it works', 'implementation', 'technical flow', 'flow'],
        ['configuration', 'settings', 'rollout', 'enablement'],
        ['testing', 'verification', 'how to test'],
        ['troubleshooting', 'failure', 'errors', 'known issues'],
    ],
    'scan': ['docs', 'README.md', 'CHANGELOG.md'],
    'exclude': ['docs/engineering-atlas'],
    'changelog': 'CHANGELOG.md|docs/changelog.md',
}
MERMAID_TYPES = ('graph', 'flowchart', 'sequenceDiagram', 'classDiagram', 'stateDiagram', 'erDiagram', 'journey',
                 'gantt', 'pie', 'C4Context', 'C4Container', 'C4Component', 'C4Dynamic', 'C4Deployment', 'mindmap',
                 'timeline', 'gitGraph', 'quadrantChart', 'requirementDiagram', 'sankey', 'xychart', 'block',
                 'architecture', 'packet', 'kanban', 'zenuml', 'radar', 'treemap')
LINK = re.compile(r'(?<!!)\[[^\]]*\]\(\s*<?([^)\s>]+)>?(?:\s+"[^"]*")?\s*\)|!\[[^\]]*\]\(\s*<?([^)\s>]+)>?[^)]*\)')
REF_LINK = re.compile(r'^\s*\[[^\]]+\]:\s*<?(\S+?)>?(?:\s+.*)?$')
HTML_LINK = re.compile(r'<(?:a|img)\s[^>]*(?:href|src)="([^"]+)"', re.I)
HEADING = re.compile(r'^(#{1,6})\s+(.*?)\s*#*\s*$')
PLACEHOLDER = re.compile(r'\{\{[^}]*\}\}')
WEAK_PLACEHOLDER = re.compile(r'\b(TODO|TBD|FIXME|XXX)\b|lorem ipsum', re.I)
LINE_ANCHOR = re.compile(r'^L(\d+)(?:C\d+)?(?:-L(\d+)(?:C\d+)?)?$')
DOC_SUFFIXES = {'.md', '.mdx', '.markdown'}


class Report:
    def __init__(self, strict):
        self.strict, self.errors, self.warnings = strict, [], []

    def error(self, where, message):
        self.errors.append(f'{where}: {message}')

    def warn(self, where, message):
        (self.errors if self.strict else self.warnings).append(f'{where}: {message}')


def load_config(root, path):
    config = dict(DEFAULT_CONFIG)
    candidate = path or root / 'docs' / 'handbook.json'
    if candidate.is_file():
        config.update(json.loads(candidate.read_text(encoding='utf-8')))
    elif path:
        raise SystemExit(f'config not found: {path}')
    return config


def first_existing(root, alternatives):
    for option in alternatives.split('|'):
        if (root / option).exists():
            return option
    return None


def strip_code(lines):
    """Yield (line_number, text, fence_language) with fenced code removed and inline code blanked."""
    fence, language = None, None
    for number, line in enumerate(lines, 1):
        stripped = line.strip()
        match = re.match(r'^(`{3,}|~{3,})\s*([\w+-]*)', stripped)
        if fence is None and match:
            fence, language = match.group(1), match.group(2).lower()
            yield number, None, ('open', language)
            continue
        if fence is not None:
            if stripped.startswith(fence[0] * len(fence)) and stripped.strip(fence[0]) == '':
                yield number, None, ('close', language)
                fence, language = None, None
            else:
                yield number, line, ('code', language)
            continue
        yield number, re.sub(r'`[^`]*`', '', line), None


def slugify(text, seen):
    text = re.sub(r'<[^>]+>', '', text)
    text = re.sub(r'\[([^\]]*)\]\([^)]*\)', r'\1', text)
    slug = re.sub(r'[^\w\- ]', '', text.strip().lower(), flags=re.UNICODE).replace(' ', '-')
    count = seen.get(slug, 0)
    seen[slug] = count + 1
    return slug if count == 0 else f'{slug}-{count}'


def headings(path, cache={}):
    if path not in cache:
        seen, found = {}, []
        for _, text, fence in strip_code(path.read_text(encoding='utf-8', errors='replace').splitlines()):
            if text is not None and fence is None:
                match = HEADING.match(text)
                if match:
                    found.append((len(match.group(1)), match.group(2), slugify(match.group(2), seen)))
        explicit = re.findall(r'<a\s+(?:name|id)="([^"]+)"', path.read_text(encoding='utf-8', errors='replace'))
        cache[path] = (found, set(explicit))
    return cache[path]


def is_excluded(rel, config):
    return any(rel == e or rel.startswith(e.rstrip('/') + '/') for e in config['exclude'])


def doc_files(root, config):
    files = []
    for entry in config['scan']:
        target = root / entry
        if target.is_file():
            files.append(target)
        elif target.is_dir():
            files += [p for p in sorted(target.rglob('*')) if p.suffix.lower() in DOC_SUFFIXES]
    return [f for f in dict.fromkeys(files) if not is_excluded(f.relative_to(root).as_posix(), config)]


def check_link(root, source, number, target, report):
    """Return the resolved path when the link points at a local file, else None."""
    if re.match(r'^[a-z][a-z0-9+.-]*:', target, re.I) or target.startswith('//'):
        return None
    where = f'{source.relative_to(root).as_posix()}:{number}'
    path_part, _, anchor = target.partition('#')
    path_part = unquote(path_part.split('?')[0])
    resolved = source if not path_part else (source.parent / path_part).resolve()
    try:
        resolved.relative_to(root.resolve())
    except ValueError:
        report.error(where, f'link leaves the repository: {target}')
        return None
    if not resolved.exists():
        report.error(where, f'broken link: {target}')
        return None
    if anchor and resolved.is_file():
        if resolved.suffix.lower() in DOC_SUFFIXES:
            found, explicit = headings(resolved)
            if anchor.lower() not in {slug for _, _, slug in found} and anchor not in explicit:
                report.error(where, f'missing heading anchor #{anchor} in {resolved.relative_to(root.resolve()).as_posix()}')
        else:
            match = LINE_ANCHOR.match(anchor)
            if match:
                last = int(match.group(2) or match.group(1))
                count = len(resolved.read_text(encoding='utf-8', errors='replace').splitlines())
                if last > count:
                    report.error(where, f'line anchor #{anchor} beyond end of file ({count} lines)')
    return resolved


def scan_file(root, path, report):
    links, source_links, mermaid, in_mermaid = [], 0, [], None
    text_lines = path.read_text(encoding='utf-8', errors='replace').splitlines()
    rel = path.relative_to(root).as_posix()
    for number, text, fence in strip_code(text_lines):
        if fence:
            state, language = fence
            if state == 'open' and language == 'mermaid':
                in_mermaid = (number, [])
            elif state == 'code' and in_mermaid:
                in_mermaid[1].append(text)
                if PLACEHOLDER.search(text):
                    report.error(f'{rel}:{number}', f'unreplaced template placeholder {PLACEHOLDER.search(text).group(0)} in diagram')
            elif state == 'close' and in_mermaid:
                mermaid.append(in_mermaid)
                in_mermaid = None
            continue
        if PLACEHOLDER.search(text):
            report.error(f'{rel}:{number}', f'unreplaced template placeholder {PLACEHOLDER.search(text).group(0)}')
        if WEAK_PLACEHOLDER.search(text):
            report.warn(f'{rel}:{number}', f'placeholder marker "{WEAK_PLACEHOLDER.search(text).group(0)}"')
        targets = [a or b for a, b in LINK.findall(text)] + HTML_LINK.findall(text)
        ref = REF_LINK.match(text)
        if ref:
            targets.append(ref.group(1))
        for target in targets:
            resolved = check_link(root, path, number, target, report)
            if resolved is not None and resolved != path:
                links.append(resolved)
                if resolved.is_file() and resolved.suffix.lower() not in DOC_SUFFIXES | {'.png', '.svg', '.jpg', '.jpeg', '.gif', '.webp', '.html'}:
                    source_links += 1
    for start, body in mermaid:
        meaningful = [l.strip() for l in body if l.strip() and not l.strip().startswith('%%')]
        if meaningful and meaningful[0] == '---':
            closing = meaningful.index('---', 1) if '---' in meaningful[1:] else len(meaningful) - 1
            meaningful = meaningful[closing + 1:]
        if not meaningful:
            report.error(f'{rel}:{start}', 'empty mermaid diagram')
        elif not meaningful[0].startswith(MERMAID_TYPES):
            report.error(f'{rel}:{start}', f'mermaid block does not start with a diagram type: "{meaningful[0][:40]}"')
    return links, source_links, len(mermaid)


def section_groups_missing(path, groups):
    found, _ = headings(path)
    titles = [title.lower() for level, title, _ in found if level >= 2]
    return [group[0] for group in groups if not any(any(alias in t for alias in group) for t in titles)]


def run(root, config, strict=False):
    root = root.resolve()
    report = Report(strict)
    na = {entry.split('|')[0] for entry in config.get('not_applicable', [])}
    for required in config['required']:
        if required.split('|')[0] in na:
            continue
        if not first_existing(root, required):
            report.error('handbook', f"missing required page {required.replace('|', ' or ')} "
                                     "(create it, or list it under not_applicable in docs/handbook.json with a reason in coverage.md)")

    files = doc_files(root, config)
    graph, stats = {}, {}
    for path in files:
        links, source_links, diagrams = scan_file(root, path, report)
        graph[path] = [l for l in links if l.suffix.lower() in DOC_SUFFIXES or l.is_dir()]
        stats[path] = (source_links, diagrams)

    index = root / config['index']
    if index.is_file():
        reachable, queue = {index}, deque([index])
        while queue:
            for target in graph.get(queue.popleft(), []):
                if target.is_dir():
                    target = next((target / n for n in ('README.md', 'index.md') if (target / n).is_file()), target)
                if target not in reachable and target in graph:
                    reachable.add(target)
                    queue.append(target)
        docs_root = index.parent
        for path in files:
            if path not in reachable and docs_root in path.parents:
                report.warn(path.relative_to(root).as_posix(), f"not reachable from {config['index']}")

    feature_dir, catalog = root / config['feature_dir'], root / config['feature_catalog']
    feature_pages = [p for p in files if feature_dir in p.parents and p != catalog]
    if catalog.is_file():
        linked = set(graph.get(catalog, []))
        for page in feature_pages:
            if page not in linked:
                report.error(page.relative_to(root).as_posix(), 'feature page is not linked from the feature catalog')
        if not feature_pages:
            report.warn(config['feature_catalog'], 'catalog has no feature pages: each capability needs its own guide')
    for page in feature_pages:
        rel = page.relative_to(root).as_posix()
        missing = section_groups_missing(page, config['feature_sections'])
        if missing:
            report.error(rel, 'feature page lacks sections: ' + ', '.join(missing))
        if stats[page][0] == 0:
            report.warn(rel, 'feature page has no links to source files (cite the implementing code)')

    changelog = first_existing(root, config['changelog'])
    if changelog:
        text = (root / changelog).read_text(encoding='utf-8', errors='replace')
        if not re.search(r'^##\s+\S', text, re.M) or not re.search(r'^\s*[-*]\s+\S', text, re.M):
            report.error(changelog, 'changelog has no version/date sections with entries')

    return report, files, stats, feature_pages


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    parser.add_argument('root', type=Path)
    parser.add_argument('--config', type=Path)
    parser.add_argument('--strict', action='store_true', help='treat warnings as errors')
    args = parser.parse_args(argv)
    config = load_config(args.root.resolve(), args.config)
    report, files, stats, features = run(args.root, config, args.strict)
    sys.stdout.reconfigure(encoding='utf-8')
    for line in report.errors:
        print('ERROR ' + line)
    for line in report.warnings:
        print('WARN  ' + line)
    diagrams = sum(d for _, d in stats.values())
    source_links = sum(s for s, _ in stats.values())
    print(f'{len(files)} pages, {len(features)} feature pages, {diagrams} mermaid diagrams, {source_links} source links; '
          f'{len(report.errors)} errors, {len(report.warnings)} warnings')
    print('Structure only: accuracy still needs source review and, for runtime claims, exercised evidence.')
    sys.exit(1 if report.errors else 0)


if __name__ == '__main__':
    main()

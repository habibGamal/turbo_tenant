#!/usr/bin/env python3
"""Draft changelog sections from git history for a human/agent to curate.

Groups commits by release tag (or by month/week when the repository has no tags),
classifies them into Keep a Changelog categories using Conventional Commit prefixes
or subject keywords, and prints Markdown. The draft is raw material: rewrite entries
as user/developer/operator effects before putting them in CHANGELOG.md.

Usage: python changelog_draft.py <repo> [--group tag|month|week] [--since REF_OR_DATE]
                                  [--include-maintenance] [--include-merges] [--json]
"""
import argparse
import datetime as dt
import json
import re
import subprocess
import sys
from collections import OrderedDict
from pathlib import Path

CONVENTIONAL = re.compile(r'^(?P<type>[a-zA-Z]+)(?:\((?P<scope>[^)]*)\))?(?P<bang>!)?:\s*(?P<subject>.+)$')
TYPE_SECTION = {
    'feat': 'Added', 'feature': 'Added', 'add': 'Added',
    'fix': 'Fixed', 'bugfix': 'Fixed', 'hotfix': 'Fixed',
    'perf': 'Changed', 'refactor': 'Changed', 'change': 'Changed', 'update': 'Changed', 'improve': 'Changed',
    'revert': 'Reverted', 'remove': 'Removed', 'deprecate': 'Deprecated', 'security': 'Security', 'sec': 'Security',
    'docs': 'Documentation', 'doc': 'Documentation',
    'chore': 'Maintenance', 'ci': 'Maintenance', 'build': 'Maintenance', 'test': 'Maintenance',
    'tests': 'Maintenance', 'style': 'Maintenance', 'deps': 'Maintenance', 'release': 'Maintenance',
}
KEYWORDS = [
    (re.compile(r'\b(security|vulnerab\w*|cve-\d+|xss|csrf|injection|exploit)\b', re.I), 'Security'),
    (re.compile(r'^(add|adds|added|implement\w*|introduce\w*|create\w*|support\w*|new)\b', re.I), 'Added'),
    (re.compile(r'^(fix|fixes|fixed|resolve\w*|correct\w*|repair\w*|prevent\w*|handle\w*)\b', re.I), 'Fixed'),
    (re.compile(r'^(remove\w*|delete\w*|drop\w*)\b', re.I), 'Removed'),
    (re.compile(r'^(deprecate\w*)\b', re.I), 'Deprecated'),
    (re.compile(r'^(revert\w*)\b', re.I), 'Reverted'),
    (re.compile(r'^(update\w*|change\w*|refactor\w*|improve\w*|rename\w*|move\w*|migrate\w*|upgrade\w*|bump\w*)\b', re.I), 'Changed'),
    (re.compile(r'^(docs?|readme|document\w*)\b', re.I), 'Documentation'),
    (re.compile(r'^(chore|wip|merge|test\w*|lint\w*|format\w*|ci)\b', re.I), 'Maintenance'),
]
ORDER = ['Breaking', 'Security', 'Added', 'Changed', 'Deprecated', 'Removed', 'Fixed', 'Reverted',
         'Documentation', 'Unclassified', 'Maintenance']
HIDDEN_BY_DEFAULT = {'Maintenance', 'Documentation'}
SEP_FIELD, SEP_RECORD = '\x1f', '\x1e'


def git(root, *args):
    result = subprocess.run(['git', '-C', str(root), *args], capture_output=True, text=True,
                            encoding='utf-8', errors='replace')
    if result.returncode != 0:
        raise SystemExit(f'git {" ".join(args)} failed: {result.stderr.strip()}')
    return result.stdout


def classify(subject, body=''):
    match = CONVENTIONAL.match(subject.strip())
    breaking = bool(match and match.group('bang')) or 'BREAKING CHANGE' in body or 'BREAKING-CHANGE' in body
    if match:
        kind, scope, text = match.group('type').lower(), (match.group('scope') or '').strip(), match.group('subject').strip()
        section = TYPE_SECTION.get(kind, 'Unclassified')
        if section in ('Fixed', 'Changed') and re.search(r'\b(security|vulnerab|xss|csrf|injection)\b', text, re.I):
            section = 'Security'
    else:
        scope, text, section = '', subject.strip(), 'Unclassified'
        for pattern, label in KEYWORDS:
            if pattern.search(text):
                section = label
                break
    return {'section': 'Breaking' if breaking else section, 'scope': scope, 'text': text, 'breaking': breaking}


def commits(root, rev_range=None, since=None, include_merges=False):
    args = ['log', f'--format=%H{SEP_FIELD}%as{SEP_FIELD}%P{SEP_FIELD}%s{SEP_FIELD}%b{SEP_RECORD}']
    if not include_merges:
        args.append('--no-merges')
    if since:
        args.append(f'--since={since}')
    if rev_range:
        args.append(rev_range)
    result = []
    for record in git(root, *args).split(SEP_RECORD):
        record = record.strip('\n')
        if not record.strip():
            continue
        sha, date, parents, subject, body = (record.split(SEP_FIELD) + [''] * 5)[:5]
        entry = classify(subject, body)
        entry.update(sha=sha.strip(), date=date.strip(), subject=subject.strip(), merge=len(parents.split()) > 1)
        result.append(entry)
    return result


def tags(root):
    lines = git(root, 'for-each-ref', '--sort=creatordate', '--format=%(refname:short)\t%(creatordate:short)', 'refs/tags').splitlines()
    return [tuple(line.split('\t')) for line in lines if line.strip()]


def period_key(date, group):
    day = dt.date.fromisoformat(date)
    if group == 'week':
        year, week, _ = day.isocalendar()
        return f'{year}-W{week:02d}'
    return day.strftime('%Y-%m')


def build_groups(root, group='tag', since=None, include_merges=False):
    """Return an ordered list of {title, date, commits}, newest first."""
    groups = []
    tag_list = tags(root) if group == 'tag' else []
    if group == 'tag' and tag_list:
        latest = tag_list[-1][0]
        groups.append({'title': 'Unreleased', 'date': None, 'range': f'{latest}..HEAD',
                       'commits': commits(root, f'{latest}..HEAD', since, include_merges)})
        for index in range(len(tag_list) - 1, -1, -1):
            name, date = tag_list[index]
            rev = f'{tag_list[index - 1][0]}..{name}' if index else name
            groups.append({'title': name, 'date': date, 'range': rev, 'commits': commits(root, rev, since, include_merges)})
        return groups
    if group == 'tag':
        group = 'month'
    buckets = OrderedDict()
    for entry in commits(root, 'HEAD', since, include_merges):
        buckets.setdefault(period_key(entry['date'], group), []).append(entry)
    for key, entries in buckets.items():
        groups.append({'title': key, 'date': f"{entries[-1]['date']} to {entries[0]['date']}", 'range': None, 'commits': entries})
    return groups


def render(groups, include_maintenance=False, dirty=0, tagged=False):
    out = ['# Changelog draft (generated from git: curate before publishing)', '',
           'Rewrite each kept entry as an effect on users, developers or operators; merge related commits;',
           'drop noise; link the feature page and any migration/config action. Do not publish hashes-only lists.', '']
    if not tagged:
        out += ['_No release tags found: sections are calendar periods, not releases. Present them as dated history',
                '(for example "## 2026-10 (untagged)") unless the maintainers confirm release versions._', '']
    if dirty:
        out += [f'_Working tree has {dirty} uncommitted paths. Describe only changes you verified belong to this work_',
                '_under `## [Unreleased]`; do not attribute every dirty file to the current task._', '']
    for grp in groups:
        visible = [c for c in grp['commits'] if include_maintenance or c['section'] not in HIDDEN_BY_DEFAULT]
        hidden = len(grp['commits']) - len(visible)
        heading = f"## {grp['title']}" + (f" ({grp['date']})" if grp['date'] else '')
        out.append(heading)
        if not grp['commits']:
            out += ['', '- no commits', '']
            continue
        by_section = OrderedDict((s, []) for s in ORDER)
        for c in visible:
            by_section[c['section']].append(c)
        for section, items in by_section.items():
            if not items:
                continue
            out += ['', f'### {section}']
            for c in items:
                scope = f"**{c['scope']}**: " if c['scope'] else ''
                out.append(f"- {scope}{c['text']} (`{c['sha'][:7]}`, {c['date']})")
        if hidden:
            out += ['', f'_{hidden} maintenance/docs commits hidden (use --include-maintenance)._']
        out.append('')
    return '\n'.join(out)


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    parser.add_argument('root', type=Path)
    parser.add_argument('--group', choices=['tag', 'month', 'week'], default='tag',
                        help='tag (falls back to month without tags), month, or week')
    parser.add_argument('--since', help='only commits after this date (git --since syntax)')
    parser.add_argument('--include-maintenance', action='store_true', help='show chore/ci/test/style/docs commits')
    parser.add_argument('--include-merges', action='store_true')
    parser.add_argument('--json', action='store_true')
    args = parser.parse_args(argv)
    groups = build_groups(args.root, args.group, args.since, args.include_merges)
    dirty = len([line for line in git(args.root, 'status', '--porcelain').splitlines() if line.strip()])
    sys.stdout.reconfigure(encoding='utf-8')
    if args.json:
        print(json.dumps({'groups': groups, 'uncommitted_paths': dirty}, indent=2))
    else:
        print(render(groups, args.include_maintenance, dirty, tagged=bool(tags(args.root)) and args.group == 'tag'))


if __name__ == '__main__':
    main()

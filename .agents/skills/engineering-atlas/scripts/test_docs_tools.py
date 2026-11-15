#!/usr/bin/env python3
"""Tests for the stack-agnostic documentation tools (stdlib only).

Usage: python test_docs_tools.py
"""
import json
import subprocess
import sys
import tempfile
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
import changelog_draft  # noqa: E402
import discover  # noqa: E402
import docs_check  # noqa: E402

TEMPLATES = Path(__file__).resolve().parent.parent / 'assets' / 'templates'


def write(root, rel, text):
    path = root / rel
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(text, encoding='utf-8')
    return path


FEATURE_PAGE = """# Checkout

## Overview
Lets a customer pay. Implemented in [service](../../src/checkout.py#L1).

## How to use
1. Open the cart.

## How it works
```mermaid
sequenceDiagram
  User->>API: POST /checkout
```

## Configuration
None.

## Testing
Run the suite.

## Troubleshooting
Nothing yet.
"""


def minimal_handbook(root):
    write(root, 'src/checkout.py', 'def checkout():\n    return True\n')
    write(root, 'CHANGELOG.md', '# Changelog\n\n## [Unreleased]\n\n### Added\n- Checkout.\n')
    write(root, 'docs/features/checkout.md', FEATURE_PAGE)
    write(root, 'docs/features/README.md', '# Features\n\n- [Checkout](checkout.md)\n')
    pages = ['overview', 'getting-started', 'architecture', 'data-model', 'interfaces', 'configuration',
             'development', 'deployment', 'operations', 'coverage']
    for page in pages:
        write(root, f'docs/{page}.md', f'# {page}\n\nBack to [index](README.md).\n')
    links = '\n'.join(f'- [{p}]({p}.md)' for p in pages)
    write(root, 'docs/README.md', f'# Handbook\n\n{links}\n- [Features](features/README.md)\n- [Changes](../CHANGELOG.md)\n')


class DocsCheckTest(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.root = Path(self.tmp.name)
        docs_check.headings.__defaults__[0].clear()

    def tearDown(self):
        self.tmp.cleanup()

    def check(self, strict=False):
        report, *_ = docs_check.run(self.root, docs_check.load_config(self.root, None), strict)
        return report

    def test_complete_minimal_handbook_passes(self):
        minimal_handbook(self.root)
        report = self.check(strict=True)
        self.assertEqual(report.errors, [])

    def test_missing_required_page_is_an_error_unless_not_applicable(self):
        minimal_handbook(self.root)
        (self.root / 'docs/data-model.md').unlink()
        self.assertTrue(any('docs/data-model.md' in e for e in self.check().errors))
        write(self.root, 'docs/handbook.json', json.dumps({'not_applicable': ['docs/data-model.md']}))
        self.assertFalse(any('missing required page docs/data-model' in e for e in self.check().errors))

    def test_alternative_page_names_satisfy_requirement(self):
        minimal_handbook(self.root)
        (self.root / 'docs/operations.md').rename(self.root / 'docs/runbook.md')
        (self.root / 'docs/README.md').write_text((self.root / 'docs/README.md').read_text().replace('operations.md', 'runbook.md'))
        self.assertEqual(self.check().errors, [])

    def test_broken_file_heading_and_line_anchors_are_reported(self):
        minimal_handbook(self.root)
        write(self.root, 'docs/architecture.md', '# Arch\n\n## Request flow\n\n[ok](#request-flow) [bad](#nope) '
              '[gone](missing.md) [src](../src/checkout.py#L2) [far](../src/checkout.py#L99)\n')
        errors = '\n'.join(self.check().errors)
        self.assertIn('missing heading anchor #nope', errors)
        self.assertIn('broken link: missing.md', errors)
        self.assertIn('#L99 beyond end of file', errors)
        self.assertNotIn('request-flow', errors)
        self.assertNotIn('#L2 ', errors)

    def test_links_inside_code_are_ignored(self):
        minimal_handbook(self.root)
        write(self.root, 'docs/development.md', '# Dev\n\n```md\n[x](nowhere.md)\n```\n\nUse `[y](nowhere.md)`.\n')
        self.assertEqual(self.check().errors, [])

    def test_feature_page_needs_sections_catalog_link_and_source_links(self):
        minimal_handbook(self.root)
        write(self.root, 'docs/features/refunds.md', '# Refunds\n\n## Overview\nText.\n')
        report = self.check()
        errors = '\n'.join(report.errors)
        self.assertIn('refunds.md: feature page is not linked from the feature catalog', errors)
        self.assertIn('lacks sections: how to use, how it works, configuration, testing, troubleshooting', errors)
        self.assertTrue(any('no links to source files' in w for w in report.warnings))

    def test_template_placeholders_fail_and_todo_warns(self):
        minimal_handbook(self.root)
        write(self.root, 'docs/deployment.md', '# Deploy\n\nRun {{build command}}.\n\nTODO verify rollback.\n')
        report = self.check()
        self.assertTrue(any('{{build command}}' in e for e in report.errors))
        self.assertTrue(any('TODO' in w for w in report.warnings))
        self.assertTrue(any('TODO' in e for e in self.check(strict=True).errors))

    def test_invalid_mermaid_and_orphan_pages(self):
        minimal_handbook(self.root)
        write(self.root, 'docs/architecture.md', '# A\n\n```mermaid\nA --> B\n```\n\n```mermaid\n%% c\nflowchart LR\n A-->B\n```\n')
        write(self.root, 'docs/orphan.md', '# Orphan\n')
        report = self.check()
        self.assertTrue(any('does not start with a diagram type' in e for e in report.errors))
        self.assertEqual(sum('does not start' in e for e in report.errors), 1)
        self.assertTrue(any('orphan.md: not reachable' in w for w in report.warnings))

    def test_empty_changelog_is_rejected(self):
        minimal_handbook(self.root)
        write(self.root, 'CHANGELOG.md', '# Changelog\n\nNothing recorded.\n')
        self.assertTrue(any('changelog has no version/date sections' in e for e in self.check().errors))

    def test_shipped_templates_only_contain_valid_mermaid(self):
        for template in TEMPLATES.rglob('*.md'):
            report = docs_check.Report(strict=False)
            docs_check.scan_file(TEMPLATES.parent.parent, template, report)
            bad = [e for e in report.errors if 'mermaid' in e]
            self.assertEqual(bad, [], template.name)


class DiscoverTest(unittest.TestCase):
    def test_detects_stack_scripts_deploy_ci_and_env_names_without_values(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            write(root, 'package.json', json.dumps({'dependencies': {'express': '4', 'react': '18'},
                                                    'scripts': {'start': 'node server.js', 'test': 'jest'}}))
            write(root, 'server.js', "const port = process.env.PORT; const s = process.env['API_SECRET'];\n")
            write(root, '.env.example', 'PORT=3000\nDATABASE_URL=postgres://user:hunter2@db/app\n')
            write(root, 'Dockerfile', 'FROM node:20\n')
            write(root, '.github/workflows/ci.yml', 'on: push\n')
            write(root, 'tests/server.test.js', 'test("x", () => {})\n')
            write(root, 'node_modules/left-pad/index.js', 'module.exports = 1\n')
            write(root, 'pyproject.toml', '[project]\ndependencies = [\n  "fastapi>=0.1",\n]\n[project.scripts]\napp = "app:main"\n')
            report = discover.discover(root)
            self.assertIn('Express', report['frameworks'])
            self.assertIn('FastAPI', report['frameworks'])
            self.assertEqual(report['scripts']['package.json']['start'], 'node server.js')
            self.assertIn('app', report['scripts']['pyproject.toml'])
            self.assertIn('server.js', report['entry_point_candidates'])
            self.assertEqual([d['kind'] for d in report['deployment_assets']], ['container image'])
            self.assertEqual(report['ci'][0]['kind'], 'GitHub Actions')
            self.assertEqual(set(report['env']['declared_in_examples']), {'PORT', 'DATABASE_URL'})
            self.assertEqual(set(report['env']['referenced_in_code_only']), {'API_SECRET'})
            self.assertNotIn('hunter2', json.dumps(report))
            self.assertFalse(any('node_modules' in f for f in report['entry_point_candidates']))
            self.assertIn('Discovery report', discover.to_markdown(report))


def git(root, *args):
    subprocess.run(['git', '-C', str(root), *args], check=True, capture_output=True)


class ChangelogDraftTest(unittest.TestCase):
    def test_classification(self):
        self.assertEqual(changelog_draft.classify('feat(cart): add coupons')['section'], 'Added')
        self.assertEqual(changelog_draft.classify('feat(cart): add coupons')['scope'], 'cart')
        self.assertEqual(changelog_draft.classify('fix!: drop legacy token')['section'], 'Breaking')
        self.assertEqual(changelog_draft.classify('refactor: x', 'BREAKING CHANGE: y')['section'], 'Breaking')
        self.assertEqual(changelog_draft.classify('fix(auth): prevent XSS in login')['section'], 'Security')
        self.assertEqual(changelog_draft.classify('Remove old importer')['section'], 'Removed')
        self.assertEqual(changelog_draft.classify('chore: bump deps')['section'], 'Maintenance')
        self.assertEqual(changelog_draft.classify('misc stuff')['section'], 'Unclassified')

    def test_groups_by_tag_with_unreleased_and_falls_back_to_months(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            git(root, 'init', '-q')
            git(root, 'config', 'user.email', 't@example.com')
            git(root, 'config', 'user.name', 'T')
            git(root, 'config', 'commit.gpgsign', 'false')
            for message in ('feat: first feature', 'chore: tidy'):
                write(root, 'f.txt', message)
                git(root, 'add', '.')
                git(root, 'commit', '-q', '-m', message)
            month_groups = changelog_draft.build_groups(root, 'tag')
            self.assertEqual(len(month_groups), 1)
            self.assertRegex(month_groups[0]['title'], r'^\d{4}-\d{2}$')
            git(root, 'tag', 'v1.0.0')
            write(root, 'f.txt', 'later')
            git(root, 'commit', '-q', '-am', 'fix: later bug')
            groups = changelog_draft.build_groups(root, 'tag')
            self.assertEqual([g['title'] for g in groups], ['Unreleased', 'v1.0.0'])
            self.assertEqual([c['section'] for c in groups[0]['commits']], ['Fixed'])
            text = changelog_draft.render(groups, tagged=True)
            self.assertIn('## Unreleased', text)
            self.assertIn('### Added\n- first feature', text)
            self.assertNotIn('tidy', text)
            self.assertIn('tidy', changelog_draft.render(groups, include_maintenance=True, tagged=True))


if __name__ == '__main__':
    unittest.main(verbosity=1)

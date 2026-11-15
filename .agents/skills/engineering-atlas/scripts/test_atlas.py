import importlib.util
import tempfile
import unittest
import subprocess
import sys
from contextlib import redirect_stdout
from io import StringIO
from pathlib import Path

spec = importlib.util.spec_from_file_location('atlas', Path(__file__).with_name('atlas.py'))
atlas = importlib.util.module_from_spec(spec)
spec.loader.exec_module(atlas)

class AtlasTests(unittest.TestCase):
    def test_same_table_name_in_central_and_tenant_remains_separate(self):
        operations = [dict(table='users', operation='create', file=f, line=1, operations=[])
                      for f in ['database/migrations/a.php', 'database/migrations/tenant/a.php']]
        self.assertEqual({'central:users', 'tenant:users'}, {t['key'] for t in atlas.schema_model(operations)})

    def test_optional_foreign_key_keeps_nullability_and_explicit_target(self):
        operation = dict(table='orders', operation='create', file='database/migrations/tenant/a.php', line=1,
                         operations=[dict(line=2, chain=[dict(method='foreignId', args=['customer_id']),
                            dict(method='nullable', args=[]), dict(method='constrained', args=['users'])])])
        table = atlas.schema_model([operation])[0]
        self.assertEqual('users', table['foreign_keys'][0]['target'])
        self.assertTrue(table['foreign_keys'][0]['nullable'])

    def test_runtime_snapshot_is_rejected_after_source_change(self):
        with tempfile.TemporaryDirectory() as temp:
            root = Path(temp)
            (root/'app').mkdir()
            (root/'app/Example.php').write_text('<?php // original')
            before = atlas.source_inventory(root)
            (root/'app/Example.php').write_text('<?php // changed')
            self.assertNotEqual(atlas.digest(before), atlas.digest(atlas.source_inventory(root)))

    def test_dynamic_url_matching_returns_candidates_only_for_matching_method(self):
        routes = [dict(id='r1', uri='api/orders/{orderId}', methods=['GET','HEAD']),
                  dict(id='r2', uri='api/orders/{orderId}', methods=['DELETE'])]
        self.assertEqual(['r1'], atlas.route_candidates('/api/orders/{dynamic}', 'GET', routes))

    def test_parent_route_does_not_match_extra_segment(self):
        self.assertEqual([], atlas.route_candidates('/api/orders/{dynamic}/status', 'GET',
            [dict(id='r1',uri='api/orders/{id}',methods=['GET'])]))

    def test_duplicate_create_is_visible_in_provenance(self):
        operations=[dict(table='products',operation='create',file=f,line=1,operations=[])
                    for f in ['database/migrations/tenant/a.php','app/Plugins/Catalog/database/migrations/tenant/a.php']]
        self.assertEqual(2, len(atlas.schema_model(operations)[0]['definitions']))

    def test_source_fingerprints_are_independent_of_checkout_line_endings(self):
        with tempfile.TemporaryDirectory() as temp:
            root=Path(temp); (root/'app').mkdir(); file=root/'app/Example.php'
            file.write_bytes(b'<?php\n// example\n'); before=atlas.source_inventory(root)
            file.write_bytes(b'<?php\r\n// example\r\n')
            self.assertEqual(before, atlas.source_inventory(root))

    def test_modified_generated_artifact_is_rejected(self):
        with tempfile.TemporaryDirectory() as temp:
            root=Path(temp); output=root/atlas.OUTPUT; output.mkdir(parents=True)
            model=dict(contract_version='1.0',project='fixture',revision='fixture',scope='fixture',modules=[],classes=[],tables=[],schema_operations=[],routes=[],pages=[],requests=[],frontend_imports=[],controls=[],coverage={})
            atlas.write_json(output/'definition.json',dict(diagrams=[]))
            atlas.write_json(output/'model.json',model)
            atlas.write_json(output/'diagram-index.json',[])
            (output/'index.html').write_text('original')
            atlas.seal(root)
            with redirect_stdout(StringIO()): atlas.check(root)
            (output/'index.html').write_text('tampered')
            with self.assertRaisesRegex(RuntimeError,'modified artifact'): atlas.check(root)

    def test_default_foreign_target_is_not_guessed_with_naive_pluralization(self):
        op=dict(table='products',operation='create',file='database/migrations/tenant/a.php',line=1,operations=[dict(line=2,chain=[dict(method='foreignId',args=['category_id']),dict(method='constrained',args=[])],convention_target='categories')])
        fk=atlas.schema_model([op])[0]['foreign_keys'][0]
        self.assertEqual('categories',fk['target']); self.assertTrue(fk['inference'])

    def test_svg_export_must_have_svg_root_not_browser_error_page(self):
        with tempfile.TemporaryDirectory() as temp:
            path=Path(temp)/'diagram.svg'
            path.write_text('<html xmlns="http://www.w3.org/1999/xhtml"><body><parsererror>Opening and ending tag mismatch: br and p</parsererror><svg xmlns="http://www.w3.org/2000/svg"/></body></html>')
            with self.assertRaisesRegex(RuntimeError,'SVG root'): atlas.validate_svg(path)

    def test_svg_parser_errors_and_malformed_xml_are_rejected(self):
        with tempfile.TemporaryDirectory() as temp:
            path=Path(temp)/'diagram.svg'
            for content in ['<svg xmlns="http://www.w3.org/2000/svg"><parsererror>bad export</parsererror></svg>', '<svg><p><br></p></svg>']:
                path.write_text(content)
                with self.assertRaises(RuntimeError): atlas.validate_svg(path)

    def test_svg_with_well_formed_html_label_is_accepted(self):
        with tempfile.TemporaryDirectory() as temp:
            path=Path(temp)/'diagram.svg'
            path.write_text('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><foreignObject width="100" height="100"><div xmlns="http://www.w3.org/1999/xhtml">First<br/>Second</div></foreignObject></svg>')
            atlas.validate_svg(path)

    def test_sealing_cannot_bless_a_model_after_its_source_changes(self):
        with tempfile.TemporaryDirectory() as temp:
            root=Path(temp); (root/'app').mkdir(); file=root/'app/Example.php'
            file.write_text('<?php // analyzed')
            analyzed=atlas.source_inventory(root)
            file.write_text('<?php // changed during rendering')
            with self.assertRaisesRegex(RuntimeError,'changed during generation'):
                atlas.seal(root,analyzed)

    def test_finalize_cannot_bypass_reextraction(self):
        with tempfile.TemporaryDirectory() as temp:
            result=subprocess.run([sys.executable,str(Path(__file__).with_name('atlas.py')),'finalize',temp],capture_output=True,text=True)
            self.assertNotEqual(0,result.returncode)
            self.assertIn('invalid choice',result.stderr)

    def test_plugin_manifest_changes_invalidate_registered_route_snapshot(self):
        with tempfile.TemporaryDirectory() as temp:
            root=Path(temp); plugin=root/'app/Plugins/Catalog'; plugin.mkdir(parents=True)
            manifest=plugin/'plugin.json'; manifest.write_text('{"providers": ["OldProvider"]}')
            before=atlas.registration_fingerprint(root)
            manifest.write_text('{"providers": ["NewProvider"]}')
            self.assertNotEqual(before,atlas.registration_fingerprint(root))

    def test_schema_module_diagrams_do_not_mix_host_and_plugin_columns(self):
        def op(file,column):
            return dict(table='products',operation='create',file=file,line=1,operations=[dict(line=2,chain=[dict(method='string',args=[column])])])
        model=dict(pages=[],modules=[],routes=[],tables=atlas.schema_model([op('database/migrations/tenant/a.php','host_column'),op('app/Plugins/Catalog/database/migrations/tenant/a.php','plugin_column')]))
        diagrams=atlas.diagrams(model,dict(diagrams=[]))
        host=next(d for d in diagrams if d['id']=='schema-tenant-host')
        plugin=next(d for d in diagrams if d['id']=='schema-tenant-catalog')
        self.assertNotIn('plugin_column',host['mermaid'])
        self.assertNotIn('host_column',plugin['mermaid'])

    def test_integrity_check_rejects_error_page_even_if_artifact_hash_matches(self):
        with tempfile.TemporaryDirectory() as temp:
            root=Path(temp); output=root/atlas.OUTPUT; (output/'diagrams').mkdir(parents=True)
            atlas.write_json(output/'definition.json',dict(diagrams=[]))
            atlas.write_json(output/'model.json',dict(contract_version='1.0',project='fixture',revision='fixture',scope='fixture',modules=[],classes=[],tables=[],schema_operations=[],routes=[],pages=[],requests=[],frontend_imports=[],controls=[],coverage={}))
            atlas.write_json(output/'diagram-index.json',[dict(id='example')])
            svg=output/'diagrams/example.svg'
            svg.write_text('<svg xmlns="http://www.w3.org/2000/svg"><text>Valid</text></svg>')
            atlas.seal(root)
            svg.write_text('<html xmlns="http://www.w3.org/1999/xhtml"><body><parsererror>br mismatch</parsererror></body></html>')
            manifest=atlas.read_json(output/'manifest.json')
            manifest['artifacts']['diagrams/example.svg']=atlas.file_digest(svg)
            atlas.write_json(output/'manifest.json',manifest)
            with self.assertRaisesRegex(RuntimeError,'SVG root'): atlas.check(root)

if __name__ == '__main__': unittest.main()

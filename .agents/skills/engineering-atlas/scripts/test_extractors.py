"""Integration fixtures using an installed pilot project's parser dependencies."""
import json
import subprocess
import sys
import tempfile
from pathlib import Path

scripts=Path(__file__).resolve().parent
project=Path(sys.argv[1]).resolve()
with tempfile.TemporaryDirectory() as temp:
    root=Path(temp)
    (root/'vendor').mkdir()
    autoload=(project/'vendor/autoload.php').as_posix().replace("'","\\'")
    (root/'vendor/autoload.php').write_text("<?php require '"+autoload+"';",encoding='utf-8')
    migrations=root/'database/migrations/tenant'; migrations.mkdir(parents=True)
    (migrations/'fixture.php').write_text('''<?php
use Illuminate\\Support\\Facades\\Schema;
return new class {
 public function up() {
  // Schema::create('fake_comment', function ($table) {});
  Schema::create('products', function ($table) {
   $table->id();
   $table->foreignId('category_id')->nullable()->constrained();
  });
 }
 public function down() { Schema::dropIfExists('products'); }
};
''',encoding='utf-8')
    result=subprocess.run(['php',str(scripts/'extract.php'),str(root)],capture_output=True,text=True,check=True)
    data=json.loads(result.stdout)
    assert not data['errors'],data['errors']
    assert len(data['schema_operations'])==1, 'Comments/down() must not enter the up() schema inventory'
    operation=data['schema_operations'][0]
    assert operation['table']=='products'
    assert operation['operations'][1]['convention_target']=='categories','Laravel inflection must be preserved'
    js=root/'resources/js'; js.mkdir(parents=True)
    dependency=root/'node_modules/typescript'; dependency.mkdir(parents=True)
    compiler=(project/'node_modules/typescript').as_posix()
    (dependency/'index.js').write_text('module.exports = require('+json.dumps(compiler)+');',encoding='utf-8')
    (js/'fixture.tsx').write_text('''import { apiClient } from './client';
// <Route path="/fake-comment" />
const route = <Route path="/products/:id" element={<RequirePlugin id="catalog"><Product /></RequirePlugin>} />;
apiClient.get<Result>(`/api/products/${product.id}`);
''',encoding='utf-8')
    result=subprocess.run(['node',str(scripts/'frontend.cjs'),str(root)],capture_output=True,text=True,check=True)
    data=json.loads(result.stdout)
    assert not data['errors'],data['errors']
    assert len(data['pages'])==1 and data['pages'][0]['plugin']=='catalog'
    assert data['requests'][0]['url']=='/api/products/{dynamic}'
print('PHP/TypeScript integration fixtures passed: comments excluded, rollback excluded, FK inflection, JSX guards, and dynamic URL extraction.')

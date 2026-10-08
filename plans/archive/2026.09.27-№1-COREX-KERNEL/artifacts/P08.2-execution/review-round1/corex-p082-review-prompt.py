import json,pathlib,sys,hashlib,subprocess
root=pathlib.Path(sys.argv[1]);manifest=json.loads((root/'manifest.json').read_text());summary=json.loads((root/'validation/summary.json').read_text())
parts=['''You are an independent native read-only reviewer for CoreX plan P08.2. Inspect the complete changed product and its dependency closure against the supplied adopted norms and actual final validation evidence. Review current P08.2 scope only. Return findings and stop; the executor owns repairs and closure.
Use only the immutable snapshot rooted at '''+str(root)+'''. Do not inspect executor sessions, journal, memory, other plan items, or unrelated repository files. Your tools are only read_file, grep, and list_dir. No edits or execution are authorized.
All changed/new files are available in product/, all deleted baseline files in deleted/, direct dependencies in dependencies/, applicable norms in norms/, actual selected final check logs/results and PG catalog witnesses in validation/. manifest.json indexes every entire file and hash. complete-product.diff contains the full original-to-candidate product diff, including large generated surfaces; their entire final content is also available. Generated API/Golden files are accessible by tools rather than repeated inline. Inspect consistency and approved finite BC deltas, native Laravel UUIDv7 behavior, ResourceId transport formats, deterministic DDL and retained default catalog, package boundaries, removed configuration consumers, tests and allowed retired test/mutation inventory. Existing future-scope issues are recommendations only unless they prevent current acceptance. Prove findings with exact path/line, concrete failure or violated adopted norm; do not invent removed-surface BC rows absent from the actual accepted base. GREEN requires no unresolved current-scope blocker.
Return one final JSON object with keys verdict (GREEN or RED), findings (array with severity, path, line, impact, norm and required_correction), recommendations (future/out-of-scope only), coverage (short), validation_assessment (short). Be candid if the snapshot lacks necessary evidence; do not call an incomplete review GREEN.
This is a fresh review. No executor reasoning or session history is supplied.
''']
parts.append('MANIFEST\n'+json.dumps(manifest,ensure_ascii=False,indent=2))
for f in sorted((root/'norms').glob('*.json')):
 parts.append('ADOPTED NORM '+f.name+'\n'+f.read_text())
for path in ['docs/dev-doctrine.md','docs/architecture/semver-policy.md','composer.json']:
 f=root/'dependencies'/path;parts.append('DEPENDENCY NORM '+path+'\n'+f.read_text())
# Whole non-generated changed/new/deleted product inline. Large generated JSON is present in snapshot.
for entry in manifest['product_files']:
 p=pathlib.Path(entry['snapshot_path']);name=entry['path']
 generated=(name.endswith('.json') or name.endswith('.neon') or (not name.endswith('.php') and p.stat().st_size>30000))
 if not generated:
  parts.append('WHOLE PRODUCT FILE '+entry['change']+' '+name+'\n'+p.read_text(errors='replace'))
 elif not name.endswith('/php-api.json') and not name.startswith('tests/Golden/baseline/'):
  delta=subprocess.check_output(['git','diff','--no-renames',manifest['base_revision'],manifest['candidate_commit'],'--',name],cwd='/home/vostrikov/projects/packages/corex',text=True)
  parts.append('PRODUCT DELTA '+name+' (entire file available at '+str(p)+')\n'+delta)
# Concise factual selected final acceptance table; actual entire logs/results remain accessible.
proof={k:v for k,v in summary.items() if k!='logs'}
proof['selected_runs']=[{k:r.get(k) for k in ['id','command','exit','expected_exit','exit_matches_expectation','product_current','product_sha256','after_product_sha256','candidate_head','elapsed_seconds','snapshot_result_path','snapshot_log_path']} for r in summary.get('logs',{}).values() if isinstance(r,dict)]
parts.append('FINAL VALIDATION INDEX\n'+json.dumps(proof,ensure_ascii=False,indent=2))
f=root/'validation/self-check.json'
if f.exists():parts.append('V20 STATIC EVIDENCE\n'+f.read_text())
for f in sorted((root/'validation/schema-after').glob('comparison*.json')):
 parts.append('SCHEMA COMPARISON '+f.name+'\n'+f.read_text())
text='\n\n'.join(parts);out=root.parent/(root.name+'-prompt.txt');out.write_text(text);out.chmod(0o444)
print(json.dumps({'prompt':str(out),'bytes':out.stat().st_size,'sha256':hashlib.sha256(out.read_bytes()).hexdigest(),'product_files':len(manifest['product_files'])}))

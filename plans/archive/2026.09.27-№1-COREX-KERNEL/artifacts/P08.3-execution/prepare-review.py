from pathlib import Path
import hashlib
import json
import shutil
import subprocess

ROOT = Path(__file__).resolve().parents[4]
OUT = Path(__file__).resolve().parent
SNAP = OUT/'review-snapshot'
SNAP.mkdir(exist_ok=True)
tracked = subprocess.check_output(['git','diff','--name-only','-z'],cwd=ROOT).split(b'\0')
untracked = subprocess.check_output(['git','ls-files','--others','--exclude-standard','-z'],cwd=ROOT).split(b'\0')
files = sorted({p.decode() for p in tracked+untracked if p and not p.decode().startswith('plans/')})
manifest = {}
for name in files:
 p=ROOT/name
 if not p.is_file(): continue
 dst=SNAP/'product'/name
 dst.parent.mkdir(parents=True,exist_ok=True)
 shutil.copyfile(p,dst)
 manifest['product/'+name]=hashlib.sha256(p.read_bytes()).hexdigest()
# The normal diff omits untracked files; copies above contain every complete final file.
(SNAP/'tracked.diff').write_bytes(subprocess.check_output(['git','diff','--',*files],cwd=ROOT))
dependencies=['tests/Golden/GoldenTestCase.php','tests/TestCase.php','tests/Pest.php','phpunit.xml','phpstan.neon','pint.json','packages/core/tests/Support/CoreXTestCase.php','packages/core/tests/Support/IsolatesDatabasePath.php','products/flex-crm/tests/Support/AccountFixture.php','packages/operations/src/Laravel/ApiSchemaRegistrar.php']
dependencies += [str(p.relative_to(ROOT)) for p in (ROOT/'packages/core/src/Database').rglob('*.php')]
dependencies += [str(p.relative_to(ROOT)) for p in ROOT.glob('packages/*/src/Internal/*SchemaSets.php')]
dependencies += ['products/flex-crm/src/Internal/CrmSchemaSets.php','scripts/schema-sets.json','packages/tenancy/src/Provisioning/Internal/Database/SchemaCatalogueSerializer.php']
for name in dependencies:
 p=ROOT/name
 if not p.is_file(): continue
 dst=SNAP/'dependencies'/name
 dst.parent.mkdir(parents=True,exist_ok=True);shutil.copyfile(p,dst)
plan=ROOT/'plans/2026.09.27-№1-COREX-KERNEL'
for name in ['phases/P08/P08.3.json','decisions/D110.json','decisions/D111.json','decisions/D112.json','decisions/D113.json','knowledge/research-P08-design.json','knowledge/artifacts-P08-design-examples.json']:
 dst=SNAP/'norms'/Path(name).name;dst.parent.mkdir(parents=True,exist_ok=True);shutil.copyfile(plan/name,dst)
for name in ['validation-summary.json','negative.txt','V1.log','V2.log','V3.log','V4.log','V5.log','V6.log','V8.log','V9.log','V14.log','V15.log','V16.log','V17.log','related.log']:
 dst=SNAP/'validation'/name;dst.parent.mkdir(parents=True,exist_ok=True);shutil.copyfile(OUT/name,dst)
(SNAP/'manifest.json').write_text(json.dumps(manifest,indent=2)+'\n')
(SNAP/'owner-scope.txt').write_text('Owner 2026-10-06 explicitly authorizes efficient affected-scope checks only, no unchanged package suites; review only at the end over all work. Owner authorizes three operations/webhooks up() connection-source repairs and principled necessary related changes, no workarounds. DDL unchanged, retention tests now migrate through SchemaMigrator. S9 catalog reconciliation adds pre-existing crm_sources only. SQLite uses existing account fixture because physical tenancy is PG-only before baseline; snapshots represent supported driver sets. composer golden excludes pg for the hermetic CI job; PG Golden is in test:pg. Snapshot wrapper with no opt-in proves no mutation; supplied SNAPSHOT_SETS exports selected sets. Initial Golden class: current pre-baseline schema. Pending and integer allowlist ratchets added to existing anti-weakening machinery.\n')
print('review snapshot:',len(files),'product files')

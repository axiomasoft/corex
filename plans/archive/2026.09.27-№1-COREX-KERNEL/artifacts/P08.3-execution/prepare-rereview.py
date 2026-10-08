from pathlib import Path
import hashlib
import json
import shutil
import difflib
import uuid

ROOT=Path(__file__).resolve().parents[4]
OUT=Path(__file__).resolve().parent
OLD=OUT/'review-snapshot'
NEW=OUT/'review-round2'
NEW.mkdir(exist_ok=True)
previous=json.loads((OLD/'manifest.json').read_text())
files=[name.removeprefix('product/') for name in previous]+['scripts/schema-golden-name-pending.json']
changed=[];manifest={};diff=[]
for name in files:
 current=(ROOT/name).read_bytes()
 baseline=(OLD/'product'/name).read_bytes() if (OLD/'product'/name).is_file() else b''
 if current==baseline:continue
 changed.append(name)
 dst=NEW/'product'/name;dst.parent.mkdir(parents=True,exist_ok=True);dst.write_bytes(current)
 manifest['product/'+name]=hashlib.sha256(current).hexdigest()
 if name.endswith('.php') or name.endswith('.sh') or name.endswith('pending.json'):
  diff.extend(difflib.unified_diff(baseline.decode().splitlines(keepends=True),current.decode().splitlines(keepends=True),fromfile='round1/'+name,tofile='round2/'+name))
(NEW/'correction.diff').write_text(''.join(diff))
(NEW/'manifest.json').write_text(json.dumps(manifest,indent=2)+'\n')
shutil.copyfile(OUT/'review-verdict-round1.json',NEW/'round1-verdict.json')
for name in ['V1.log','V2.log','V4.log','V5.log','V6.log','V8.log','V9.log','V16.log','negative.txt','validation-summary.json']:
 dst=NEW/'validation'/name;dst.parent.mkdir(parents=True,exist_ok=True);shutil.copyfile(OUT/name,dst)
plan=ROOT/'plans/2026.09.27-№1-COREX-KERNEL'
shutil.copyfile(plan/'phases/P08/P08.3.json',NEW/'current-item.json')
(NEW/'repair-facts.txt').write_text('One owning correction. F1: structural rules now scan the same invoked helper bodies as purity; create ownership is independent of callback syntax, including arrow/variable callback. Helpers have S3/S4/S6 regressions. Fluent FK names now checked, plus fixed-date/duplicate-name tests. F2: unnamed autoindexes retain the actual sqlite_autoindex_* identifier; both driver catalogues validate table/column/object name lengths. Four exact existing SQLite long stored identifiers are listed in schema-golden-name-pending.json, only valid while their owner is in the original pending package list. Every exception must be observed; stale/new long names fail. Existing anti-weakening ratchet protects all three inventories. Initial catalogue correction GOLDEN_UPDATE=1; no shipped DDL rename. Scope amendment uses owner prior authorization for principled necessary related repairs. Current checks: gate fixtures35; SQLite3 incl real autoindex identity and new long-name rejection; PG4; affected Pint/PHPStan/anti-weakening and ownership GREEN; two destructive-negative copies restore byte-identically and GREEN. Other unchanged acceptance logs reused.\n')
id=str(uuid.uuid4());launch={'native_session_id':id,'provider':'grok','model':'grok-4.7','reasoning_effort':'high','tools':['read_file','list_dir','grep'],'cwd':str(NEW),'completed_round_budget':2,'review_scope':'only F1/F2 owning correction and changed dependency closure'}
(OUT/'review-launch-round2.json').write_text(json.dumps(launch,indent=2)+'\n')
prompt=f'''Fresh native read-only bounded re-review, grok/grok-4.7/high, CoreX P08.3, completed round 2 of at most 2. Only read_file, grep and list_dir; no edits/execution/web/MCP/subagents.
Root: {NEW}
Prior completed full review is round1-verdict.json. Recheck the two current-scope findings F1/F2 and their one owning correction only, plus changed dependency closure. Do not restart the full review or re-read unchanged phase/whole repository/PG Goldens. Current-item.json transparently declares the owner-authorized repair scope. Read repair-facts.txt, correction.diff, and complete changed source in product/. manifest.json indexes every complete changed file, including full corrected tenant.sqlite.json. validation/ contains actual affected GREEN checks and restored negative controls. The old snapshot remains at {OLD} for one specific ambiguity only.
Verify helper and callback S9 parity (and S3/S4/S6 helper enforcement), retained purity/pending/T4, actual SQLite autoindex identity, name-length checks on both drivers, four exact existing long names being tied to pending owners with stale/unlisted names rejected and a shrinking inventory ratchet. No shipped DDL renames; these old long SQLite names normalize in their baseline owning items, preserving initial-schema evidence. Confirm the limited unrelated recommendation fixes did not regress these invariants. Recommendations for future baseline work remain non-gating.
Return only JSON {{"verdict":"GREEN"|"FINDINGS","findings":[],"recommendations":[],"coverage":"..."}}. Findings require exact path/line and concrete failure. Keep this to the bounded correction and return the verdict as soon as those invariants are checked; no whole-scope replay or speculative additional review rounds.'''
(OUT/'review-prompt-round2.txt').write_text(prompt)
print('rereview',id,'changed files',len(changed))

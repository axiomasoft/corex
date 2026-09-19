exec(open('/tmp/corex-p2-common.py').read())
f=p/'findings/P2-contract-proposals.md';b=f.read_text();cs=tbl('findings/P2-contract-proposals.md','Contracts','Contract / R / Owner / Existing anchor / Proposed section / Public surface / BC and version / Schema or dependency / Approval / Producer')
for r in cs:r['Existing anchor']='Full P1 ledger Requirements/'+r['R'].replace(' ','/')+' @ '+sha(p/'findings/P1-evidence-ledger.md')+'; exact inspected source bodies and equivalence retained; owning design/Files/signature qualification in linked section'
head=mdtable('Contracts','Contract / R / Owner / Existing anchor / Proposed section / Public surface / BC and version / Schema or dependency / Approval / Producer',cs).replace('## Contracts\n','## Contracts\n\n### 1 — Exact surface index\n',1)
b=re.sub(r'^## Contracts\n.*?(?=^## |\Z)',lambda _:head,b,flags=re.M|re.S)
# Explicit error file ownership and separate table connection targets, proposal-only.
b=b.replace('New tables only, no tenant_id root filter/FK/cross-product schema.','New tables only, no tenant_id root filter/FK/cross-product schema. Every root_wakeup_hints statement is a SEPARATE opt-in P5.3 root migration on its approved operator root connection; sys_wakeup_requests statements belong P5.2 current tenant/boxed connection only. No single connection script applies both roles. All other tables here use current trusted physical tenant/boxed connection.')
# Exact declarations for hidden P3 candidates, source-current baseline and no public port edit.
base=Path('packages/tenancy/src/Membership/Internal/Persistence/PairState.php').read_text();new=base.replace('public bool $denied,','public bool $denied,\n        public ?string $snapshotDigest = null,')
extra='''Exact hidden candidate signatures (fully qualified types, compatibility defaults):

```php
declare(strict_types=1);
// packages/tenancy/src/Storage/ObjectTenantStorage.php; @internal final
// constructor property promotion:
public function __construct(
    private readonly \\CoreX\\Contracts\\TenantContextResolver $contexts,
    private readonly \\Illuminate\\Filesystem\\FilesystemManager $filesystems,
    private readonly array $durableConfig,
    private readonly string $ephemeralRoot,
);
// ScopedFilesystem adds last parameter, retains all old four promoted parameters:
private readonly bool $localPaths = true;
```

'''
pos=b.index('## C-P2-002\n');b=b[:pos]+extra+b[pos:]
extra4='''Exact hidden PairStore/DurableInbox changes; old constructor had only connection and old apply had four parameters. Existing methods and named parameter semantics stay compatible:

```php
declare(strict_types=1);
// PairStore, final readonly, @internal:
public function __construct(
    private \\Illuminate\\Database\\ConnectionInterface $connection,
    private string $table = 'membership_pair_references',
);
public function apply(\\CoreX\\Tenancy\\Membership\\Internal\\Persistence\\PairKey $key, string $operationId, string $revision, string $revokeGeneration, ?string $snapshotDigest = null): \\CoreX\\Tenancy\\Membership\\Internal\\Persistence\\PairState;
public function isFor(\\Illuminate\\Database\\ConnectionInterface $connection): bool;
// DurableInbox, final readonly, @internal:
public function __construct(
    private \\Illuminate\\Database\\ConnectionInterface $connection,
    private string $table = 'membership_trigger_inbox',
);
```

The production snapshotDigest is mandatory validated lowercase SHA64; null remains ONLY reference mode compatibility. Candidate PairState source declaration (existing guards kept, added property validation additionally mandatory by the normative profile):

```php
'''+new.rstrip()+'\n```\n\n'
pos=b.index('## C-P2-005\n');b=b[:pos]+extra4+b[pos:]
f.write_text(b)
# Complete same-source numeric selections; no limits or sources removed.
f=p/'phases/P2/P2.4.md';b=f.read_text();b=b.replace('P2.3 → `findings/P2-contract-proposals.md` ·','P2.3 → `findings/P2-contract-proposals.md` §0 ·')
raw=re.search(r'\*\*Required Reads:\*\* (.*?)(?=\n\*\*)',b,re.S)[1];parts=re.split(r'\d+\) ',raw)[1:];parts=[re.sub(r'\.\s*$','',x.strip()) for x in parts]
last=parts[-1];tail=' Остальные src/tests и indexed affected specs открывать по bounded anchors; все исходные источники сохранены, per-read limits неизменны.';parts[-1]=last.split(' Остальные src/tests',1)[0].rstrip('.')
out=[]
for part in parts:
 if part.startswith('`findings/P2-contract-proposals.md`'):
  out+=['`findings/P2-contract-proposals.md` §'+s+' (producer: P2.3)' for s in ['1','0']+['2.'+str(j) for j in range(1,31)]]
 else:out.append(part)
b=re.sub(r'\*\*Required Reads:\*\* .*?(?=\n\*\*)',lambda _:'**Required Reads:** '+' '.join(str(n)+') '+x+'.' for n,x in enumerate(out,1))+tail,b,flags=re.S);f.write_text(b)
from task import plan_bundle
# Build only after native two-digit declaration grouping.
print('Source-complete P2.4 selected32 proposal sections; generated actual pending producer bundle')

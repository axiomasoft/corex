"""P04.3: deterministic catalogue/site migration from the D64 map and preimage.

Run once on the item preimage. Behavioral adapters are edited separately.
"""
from pathlib import Path
import csv
import json
import re

ROOT = Path(__file__).resolve().parents[4]
OUT = Path(__file__).resolve().parent
PLAN = OUT.parents[1]
catalogue = {}
namespaces = {}
statuses = {'authentication': 401, 'authorization': 403, 'validation': 422,
            'conflict': 409, 'not_found': 404, 'rate_limit': 429,
            'unsupported': 422, 'unavailable': 503, 'internal': 500}

def case(code):
    return ''.join(x.title() for x in code.split('_'))

def add(package, code, category, retryable=False, keys=()):
    rows = catalogue.setdefault(package, {})
    old = rows.get(code)
    if old:
        assert old['category'] == category, (package, code, old, category)
        old['retryable'] |= retryable
        old['keys'] = sorted(set(old['keys']) | set(keys))
        return
    rows[code] = {'category': category, 'retryable': retryable, 'keys': list(keys)}

for row in json.loads((OUT/'preimage/error-contract.json').read_text())['contracts']:
    code = row['code']
    package = 'custom-fields' if row['owner'].endswith('custom-fields') else 'operations'
    if code.startswith('webhook_') or code in ('unknown_message_contract', 'unknown_message_schema'):
        package = 'webhooks'
    if code == 'internal_error':
        package = 'core'
    add(package, code, row['category'], row['retryable'], row['detail_keys'])
for code in ['duplicate_code', 'foreign_prefix', 'duplicate_type', 'invalid_status', 'invalid_definition', 'unknown_code', 'registry_frozen']:
    add('core', code, 'internal')
add('operations','capability_unavailable','unavailable',keys=['reason'])
add('operations','unknown_message_contract','validation')
add('webhooks','unknown_message_schema','validation')
add('webhooks','webhook_secret_denied','authorization')
add('webhooks','webhook_sensitive_denied','authorization')

# Explicit semantic choices for the existing exception types; no diagnostic text is exposed.
categories = {
    'CentralContextException':'authorization', 'ScopeContextMismatch':'authorization',
    'ImpersonationRestrictedException':'authorization', 'StorageCapabilityUnavailable':'unavailable',
    'StorageOperationFailed':'unavailable', 'InvalidScopePayload':'validation',
    'SettingValueTooLargeException':'validation', 'UnsupportedScopeException':'unsupported',
    'DepartmentCycleException':'conflict', 'CrossWorkspaceMoveException':'conflict',
    'InvalidStateTransitionException':'conflict', 'ModuleUpgradeFailedException':'internal',
    'ModuleEnableFailedException':'internal', 'CompilationException':'internal',
    'StaleRegistryRevision':'conflict', 'MissingDependencyException':'conflict',
    'InvalidRegistryPoint':'validation', 'RegistryInputUnavailable':'unavailable',
    'InvalidManifestException':'validation', 'HasEnabledDependentsException':'conflict',
    'ModuleNotFound':'not_found', 'EntityNotFound':'not_found', 'InvalidMessage':'validation',
    'OutboxLeaseLost':'conflict', 'InboxRefused':'conflict', 'OutboxRefused':'conflict',
    'UnknownMessageContract':'validation', 'InvalidConfiguration':'internal', 'RegistryFrozen':'internal',
    'TasksModuleDisabled':'unavailable', 'InvalidPlanningDraft':'validation', 'PlanningConflict':'conflict',
    'UnsupportedMaterialization':'unsupported', 'StaleMaterialization':'conflict', 'MaterializationConflict':'conflict',
    'InvalidAttributeTables':'internal', 'ScopeCapabilityMismatch':'unsupported',
    'NotificationAccessDenied':'authorization', 'NotificationOperationConflict':'conflict',
    'DomainTrustDenied':'authorization', 'DomainTrustDisabled':'unavailable',
    'OidcAuthenticationException':'authentication', 'TenantCouldNotBeIdentifiedByHostException':'not_found',
    'ClaimUnavailable':'unavailable', 'ClaimConflict':'conflict', 'TemplateIntegrityException':'internal',
    'NoDefaultWorkspaceException':'not_found', 'InvitationNotAcceptableException':'conflict',
    'CentralUnavailable':'unavailable', 'PairStoreViolation':'internal', 'RevokeUncertain':'unavailable',
    'ProjectionDenied':'authorization', 'InboxConflict':'conflict', 'CredentialIssuanceDenied':'authorization',
    'CentralProtocolViolation':'internal', 'CentralUnauthorized':'authorization',
    'SchemaMismatchException':'conflict', 'OperationConflictException':'conflict', 'AccessDeniedException':'authorization',
    'InvalidRateException':'validation', 'SnapshotExpiredException':'not_found', 'InvalidLocaleException':'validation',
    'UnsupportedFormattingException':'unsupported', 'StaleCursorException':'conflict',
    'UnsupportedEnvironmentException':'unsupported', 'InvalidSearchConfiguration':'internal',
    'InvalidMeilisearchConfiguration':'internal', 'BudgetStoreUnavailable':'unavailable', 'BudgetRateLimited':'rate_limit',
}
exceptions=[]
for p in sorted((ROOT/'packages').glob('*/src/**/Exceptions/*.php')):
    if '/Errors/Exceptions/' in str(p):
        continue
    s=p.read_text()
    m=re.search(r'final class (\w+) extends (\w+)',s)
    if not m:
        continue
    name,parent=m.groups(); category=categories[name]
    package=p.relative_to(ROOT/'packages').parts[0]
    code=re.sub(r'(?<!^)([A-Z])',r'_\1',name).lower()
    retryable=name in ('CentralUnavailable','StorageOperationFailed','ClaimUnavailable','BudgetStoreUnavailable','RevokeUncertain')
    add(package,code,category,retryable)
    exceptions.append({'path':p.relative_to(ROOT).as_posix(),'package':package,'code':code,'category':category,'retryable':retryable,'parent':parent})

for package in catalogue:
    manifest=json.loads((ROOT/'packages'/package/'composer.json').read_text())
    namespaces[package]=next(iter(manifest['autoload']['psr-4'])).rstrip('\\')

for package, rows in sorted(catalogue.items()):
    ns=namespaces[package]+'\\Errors'
    enum=case(package.replace('-','_'))+'ErrorCode'
    ep=ROOT/'packages'/package/'src/Errors/Enums'/f'{enum}.php'; ep.parent.mkdir(parents=True,exist_ok=True)
    ep.write_text('<?php\n\ndeclare(strict_types=1);\n\nnamespace '+ns+'\\Enums;\n\nuse CoreX\\Attributes\\Api;\nuse CoreX\\Errors\\Contracts\\ErrorCode;\n\n#[Api(since: \'1.0\')]\nenum '+enum+': string implements ErrorCode\n{\n'+''.join("    case "+case(code)+" = '"+package+'.'+code+"';\n" for code in sorted(rows))+"\n    public function value(): string\n    {\n        return $this->value;\n    }\n}\n")
    pp=ROOT/'packages'/package/'src/Errors/Internal/PackageProblemDefinitions.php'; pp.parent.mkdir(parents=True,exist_ok=True)
    pp.write_text('<?php\n\ndeclare(strict_types=1);\n\nnamespace '+ns+'\\Internal;\n\nuse CoreX\\Errors\\Contracts\\ProblemDefinitionProvider;\nuse CoreX\\Errors\\Data\\ProblemDefinition;\nuse CoreX\\Errors\\Enums\\ErrorCategory;\nuse '+ns+'\\Enums\\'+enum+';\n\n/** @internal The owning package contributes its safe error catalogue at boot. */\nfinal class PackageProblemDefinitions implements ProblemDefinitionProvider\n{\n    public function definitions(): iterable\n    {\n'+''.join("        yield new ProblemDefinition("+enum+'::'+case(code)+", 'urn:corex:problem:"+package+'.'+code+"', "+str(statuses[v['category']])+", '"+code.replace('_',' ').capitalize()+".', "+str(v['retryable']).lower()+', ErrorCategory::'+case(v['category'])+', ['+', '.join("'"+k+"'" for k in v['keys'])+"]);\n" for code,v in sorted(rows.items()))+'    }\n}\n')
    providers=json.loads((ROOT/'packages'/package/'composer.json').read_text()).get('extra',{}).get('laravel',{}).get('providers',[])
    if package=='operations': providers=[namespaces[package]+'\\ApiServiceProvider']
    if package=='recurrence':
        providers=[namespaces[package]+'\\RecurrenceServiceProvider']
        path=ROOT/'packages/recurrence/src/RecurrenceServiceProvider.php'
        path.write_text("<?php\n\ndeclare(strict_types=1);\n\nnamespace CoreX\\Recurrence;\n\nuse CoreX\\Attributes\\Spi;\nuse CoreX\\Attributes\\SpiKind;\nuse Illuminate\\Support\\ServiceProvider;\n\n#[Spi(kind: SpiKind::Call, since: '1.0')]\nfinal class RecurrenceServiceProvider extends ServiceProvider\n{\n    public function register(): void\n    {\n    }\n}\n")
        manifest=json.loads((ROOT/'packages/recurrence/composer.json').read_text());manifest['extra']['laravel']={'providers':providers}
        (ROOT/'packages/recurrence/composer.json').write_text(json.dumps(manifest,indent=4,ensure_ascii=False)+'\n')
    provider=providers[0]; path=ROOT/'packages'/package/'src'/Path(provider.removeprefix(namespaces[package]+'\\').replace('\\','/')).with_suffix('.php')
    s=path.read_text(); brace=re.search(r'public function register\(\): void\s*\{',s)
    assert brace,path
    registration="\n        \\CoreX\\Errors\\Laravel\\ProblemDefinitions::register($this->app, __DIR__.'/../composer.json', \\"+ns+"\\Internal\\PackageProblemDefinitions::class);\n"
    if registration.strip() not in s:
        s=s[:brace.end()]+registration+s[brace.end():];path.write_text(s)

for row in exceptions:
    p=ROOT/row['path'];s=p.read_text();package=row['package'];enum=case(package.replace('-','_'))+'ErrorCode'
    if 'function errorDescriptor()' in s:
        continue
    if row['parent']=='RuntimeException':
        s=s.replace('use RuntimeException;', 'use CoreX\\Errors\\Exceptions\\CodedException;').replace('extends RuntimeException','extends CodedException')
    else:
        s=re.sub(r'(final class \w+ extends \w+)(\s*\{)',r'\1 implements \\CoreX\\Errors\\Contracts\\DescribesError\2',s)
    s=s.rstrip(); index=s.rfind('}')
    method='\n    public function errorDescriptor(): \\CoreX\\Errors\\Data\\ErrorDescriptor\n    {\n        return new \\CoreX\\Errors\\Data\\ErrorDescriptor(\\'+namespaces[package]+'\\Errors\\Enums\\'+enum+'::'+case(row['code'])+', \\CoreX\\Errors\\Enums\\ErrorCategory::'+case(row['category'])+', retryable: '+str(row['retryable']).lower()+');\n    }\n'
    s=s[:index]+method+s[index:]+'\n';p.write_text(s)

site_map=list(csv.DictReader((PLAN/'artifacts/P04-design/error-code-map.tsv').open(),delimiter='\t'))
for row in site_map:
    p=ROOT/row['path']
    if not p.exists():
        matches=list((ROOT/'packages'/p.relative_to(ROOT/'packages').parts[0]/'src').rglob(p.name))
        internal=[x for x in matches if 'Internal' in x.parts]
        if len(internal)==1: matches=internal
        assert len(matches)==1,(p,matches)
        p=matches[0];row['current_path']=p.relative_to(ROOT).as_posix()
    s=p.read_text()
    old=row['old_expression']
    if not old.startswith("'"):
        continue
    wire=row['new_code'];package,code=wire.split('.',1)
    if code=='internal_error': package='core'
    assert code in catalogue[package],(p,code)
    expression='\\'+namespaces[package]+'\\Errors\\Enums\\'+case(package.replace('-','_'))+'ErrorCode::'+case(code)
    # Only the immutable map's error constructor positions are transformed.
    pattern=r'((?:errorCode|code)\s*:\s*|new\s+(?:\\?[\w\\]+\\)?ApiError\s*\(\s*)'+re.escape(old)
    s,n=re.subn(pattern,lambda m:m.group(1)+expression,s)
    p.write_text(s)
    row['typed_expression']=expression
    row['new_code']=package+'.'+code

(OUT/'exception-map.json').write_text(json.dumps(exceptions,indent=2)+'\n')
(OUT/'catalogue-source.json').write_text(json.dumps(catalogue,indent=2)+'\n')
with (OUT/'typed-site-map.tsv').open('w') as f:
    w=csv.DictWriter(f,fieldnames=list(dict.fromkeys(k for row in site_map for k in row)),delimiter='\t');w.writeheader();w.writerows(site_map)
print(json.dumps({'codes':sum(map(len,catalogue.values())),'exceptions':len(exceptions),'sites':len(site_map),'packages':len(catalogue)}))

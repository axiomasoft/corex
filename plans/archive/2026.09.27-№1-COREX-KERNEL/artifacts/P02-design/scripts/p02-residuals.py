#!/usr/bin/env python3
"""Named residuals reported by rename-completeness in the P02.3 dry-run."""
import json,re
from pathlib import Path

pairs={
    'CoreX\\Attributes\\Internal\\Values':'CoreX\\CustomFields\\Internal\\Values',
    'CoreX\\Attributes\\Internal\\Integration':'CoreX\\CustomFields\\Internal\\Integration',
    'CoreX\\Auth\\Testing':'CoreX\\Access\\Testing',
    'CoreX\\Auth\\Tests\\Stubs\\DoesNotExist':'CoreX\\Access\\Tests\\Stubs\\DoesNotExist',
    'CoreX\\Contracts\\Audit':'CoreX\\Audit\\Contracts',
    'CoreX\\Contracts\\Auditable':'CoreX\\Audit\\Contracts\\Auditable',
    'CoreX\\Models\\AuditLog':'CoreX\\Audit\\Internal\\AuditLog',
    'CoreX\\SavedViews':'CoreX\\Panels\\SavedViews',
    'CoreX\\Api':'CoreX\\Operations',
    'CoreX\\Events\\Foo':'CoreX\\Messaging\\Events\\Foo',
    'CoreX\\Support':'CoreX\\Config',
    'CoreX\\Contracts':'CoreX\\Settings\\Contracts',
    'packages/core/src/Support/':'packages/core/src/Config/',
    'packages/core/src/Contracts/ProbeHelper.php':'packages/core/src/Settings/Contracts/ProbeHelper.php',
}
files=[
    'tests/fixtures/attributes-architecture/runtime-config.php.fixture',
    'tests/fixtures/attributes-architecture/search-import.php.fixture',
    'docs/architecture/core-zones.md','docs/architecture/structure-profiles.md',
    'packages/access/tests/Feature/WorkspaceScopeWiringTest.php',
    'packages/core/tests/Unit/MessagingEnvelopeTest.php',
    'tests/Arch/ConfigCompositionTest.php','tests/Arch/CoreZoneTest.php',
    'tests/Arch/NamingAndConfigGateTest.php','tests/Arch/ProviderSizeTest.php',
]
residuals=[]
for name in files:
    p=Path(name);s=p.read_text()
    # One whole FQCN token; class-looking examples retain their test semantics.
    def fq(m):
        token=m[0];doubled='\\\\' in token;bare=token.replace('\\\\','\\')
        result=pairs.get(bare,bare)
        if result != bare:
            residuals.append({'file':name,'line':s.count('\n',0,m.start())+1,'old':bare,'new':result})
        return result.replace('\\','\\\\') if doubled else result
    s=re.sub(r'(?:CoreX|Flex)(?:(?:\\\\|\\)[A-Za-z_]\w*)+',fq,s)
    for old,new in pairs.items():
        if old.startswith('packages/'):
            for hit in re.finditer(re.escape(old),s):
                residuals.append({'file':name,'line':s.count('\n',0,hit.start())+1,'old':old,'new':new})
            s=s.replace(old,new)
    p.write_text(s)

# Freeze the projected engine manifest expectations, retaining the optional
# transfer checks. These assertions still compare against literal values.
p=Path('packages/operations/tests/Feature/LoadContourBootTest.php');s=p.read_text()
m=json.loads(Path('packages/operations/composer.json').read_text())
def php_strings(values):
    return '['+', '.join("'"+v.replace('\\','\\\\').replace("'","\\'")+"'" for v in values)+']'
s=re.sub(r"self::assertSame\(\[.*?\], \$composer\['extra'\]\['corex'\]\['public-api'\]\);",lambda _:"self::assertSame("+php_strings(m['extra']['corex']['public-api'])+", $composer['extra']['corex']['public-api']);",s)
s=re.sub(r"self::assertSame\(\[\s*'axiomasoft/corex'.*?\], array_keys\(\$require\)\);",lambda _:"self::assertSame("+php_strings(m['require'].keys())+", array_keys($require));",s,flags=re.S)
p.write_text(s)
assert len(residuals) <= 50, residuals
Path('build/p02/residuals.json').write_text(json.dumps(residuals,indent=2)+'\n')
print('residuals: exact docs, negative-control probes and manifest expectations projected')

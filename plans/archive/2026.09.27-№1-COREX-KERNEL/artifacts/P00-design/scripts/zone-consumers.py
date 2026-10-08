#!/usr/bin/env python3
"""Consumers of each corex/core zone outside packages/core (static `use` scan).

Zone = second FQCN segment under CoreX\\ for symbols physically in packages/core/src;
CoreX\\Contracts\\<Name> is attributed to its bounded context via CONTRACT_ZONE.
Usage: zone-consumers.py [--check TSV]  (run from repo root)
"""
import collections, glob, os, re, sys

CONTRACT_ZONE = {
    'DataScopeAuthorizer': 'Data', 'DataScopeCodec': 'Data', 'DepartmentRepository': 'Departments',
    'DescribesError': 'Errors', 'DurablePayload': 'Messaging', 'FeatureFlagDefinition': 'Features',
    'FeatureFlags': 'Features', 'IdentityPasswordRehasher': 'Identity', 'ModuleRegistrar': 'Modules',
    'OperationObserver': 'Observability', 'PubSub': 'Messaging', 'SavedViewRepository': 'SavedViews',
    'SettingDefaultsProvider': 'Settings', 'SettingsRepository': 'Settings', 'SettingsScope': 'Settings',
    'TenantStorage': 'Storage', 'ViewAccessPolicy': 'SavedViews', 'ViewSchemaProvider': 'SavedViews',
    'WorkerRuntime': 'Messaging',
}
core_fqcns = set()
for f in glob.glob('packages/core/src/**/*.php', recursive=True):
    src = open(f, encoding='utf-8').read()
    ns = re.search(r'^namespace ([\w\\]+);', src, re.M)
    cls = re.search(r'^(?:final |abstract |readonly )*(?:class|interface|trait|enum) (\w+)', src, re.M)
    if ns and cls:
        core_fqcns.add(ns.group(1) + '\\' + cls.group(1))

def zone(fq):
    parts = fq.split('\\')
    if len(parts) < 3:
        return 'Root'
    if parts[1] == 'Contracts':
        return CONTRACT_ZONE.get(parts[2], 'Contracts?')
    if parts[1] == 'Support' and len(parts) > 3 and parts[2] in ('Data', 'Time', 'Schema', 'Console'):
        return 'Support\\' + parts[2]
    return parts[1]

rows = collections.defaultdict(lambda: collections.Counter())
for f in glob.glob('packages/*/src/**/*.php', recursive=True) + glob.glob('packages/*/tests/**/*.php', recursive=True):
    pkg = f.split('/')[1]
    if pkg == 'core':
        continue
    for u in re.findall(r'^use ((?:CoreX)\\[\w\\]+)', open(f, encoding='utf-8').read(), re.M):
        if u in core_fqcns:
            rows[zone(u)][pkg] += 1
out = ['zone\tconsumer_packages\tpackage_count\timports']
for z in sorted(rows):
    c = rows[z]
    out.append(f"{z}\t{','.join(sorted(c))}\t{len(c)}\t{sum(c.values())}")
text = '\n'.join(out) + '\n'
if len(sys.argv) > 2 and sys.argv[1] == '--check':
    sys.exit(0 if open(sys.argv[2]).read().split('\n')[:len(out)] == out else 1)
sys.stdout.write(text)

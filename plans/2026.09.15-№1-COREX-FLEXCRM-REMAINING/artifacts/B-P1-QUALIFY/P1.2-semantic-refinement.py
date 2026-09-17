"""Explicit source-preserving refinements; this tool does not attest semantic GREEN."""
from pathlib import Path
import copy
import hashlib
import json
import re

plan = Path(__file__).resolve().parents[2]
artifacts = plan / 'artifacts/B-P1-QUALIFY'
draft = json.loads((artifacts / 'P1.2-draft-model.json').read_text())
parents = copy.deepcopy(draft['parents'])
trace = {}
for line in (plan / 'research/01-traceability.md').read_text().splitlines():
    if re.match(r'^\| R\d\d \|', line):
        cells = [cell.strip() for cell in line.split('|')[1:-1]]
        trace[cells[0]] = cells


def slices(text, markers=()):
    """Retain every byte of the normalized parent, including punctuation."""
    cuts = {0, len(text)}
    code = False
    depth = 0
    for index, char in enumerate(text):
        if char == '`':
            code = not code
        if not code:
            if char in '([{':
                depth += 1
            elif char in ')]}':
                depth = max(0, depth - 1)
            elif char == ';' and not depth:
                cuts.add(index + 1)
    for marker in markers:
        assert text.count(marker) == 1, (marker, text)
        cuts.add(text.index(marker))
    ordered = sorted(cuts)
    result = [text[left:right] for left, right in zip(ordered, ordered[1:])]
    assert ''.join(result) == text
    return result


# These cuts separate independently observable outcomes, rather than splitting
# every comma inside a tuple, schema, threshold or atomic transaction invariant.
cuts = {
    'B10 AC-5': ['идемпотентные сиды', 'записи `mod_records`', 'строка `mod_modules',
                  'событие `ModuleEnabled`', 'инвалидация per-account'],
    'B10 AC-7': ['`mod_lifecycle_log', 'строки в mod_modules нет'],
    'B10 AC-9': ['таблицы живы'],
    'B10 AC-16': ['фиксирует их'],
    'B10 AC-20': ['записи в sys_audit_log'],
    'B11 AC-1': ['и aut-таблицах'],
    'B11 AC-31': ['баннер', 'ACTOR=support', 'запрещённые операции', 'авто-завершение',
                    'события в root_account_events'],
    'B12 AC-1': ['PK везде uuid', 'CHECK/FK/индексы'],
    'B13 AC-20': ['закрывает бегущий таймер автора'],
    'B34 AC-19': ['письмо ведёт', 'IdP аутентифицирует', '`root_identities` create/find',
                   '**root пишет', 'событие `IdentityMembershipGranted`',
                   '`IdentitySyncer::syncToAccount()`', 'редирект на поддомен CRM',
                   'помечает `wsp_invitations.accepted`', 'создаёт `wsp_workspace_members`',
                   'Лимит `users_max`'],
    'B34 AC-7': ['не пишет напрямую', 'не использует общий', 'не копирует в tenant'],
    'B18.§8.6': ['sample ≥20'],
}

# Own the complete engine/provider behavior outside CoreX. R11/R12 remain
# requirement links, while their reusable adapter portions have separate non-AC
# programme anchors. This does not authorize a second engine or its DDL in L0.
engine_owners = {
    15: ('standalone', 'Attributes engine owner', 'P6: qualify selected standalone engine; CoreX adapter separately'),
    18: ('standalone', 'Import/export engine owner', 'P6: standalone engine and consumer adapters'),
}
consumer_overrides = {
    10: ('app', 'Flex modules/profile maintainer'),
    13: ('app', 'FlexCRM domain and agent composition maintainer'),
    27: ('app', 'Flex collaborative tasks maintainer'),
    29: ('app', 'Flex calendar maintainer'),
}


def ownership(parent, fragment, ordinal):
    block, number = map(int, re.fullmatch(r'B(\d+)(?: AC-|\.§8\.)(\d+)', parent['label']).groups())
    old = parent['atoms'][min(ordinal - 1, len(parent['atoms']) - 1)]
    requirement = old['R']
    boundary, owner, destination = old['Boundary'], trace[requirement][3], trace[requirement][7]
    note = 'Source-profile behavior; no executed acceptance claimed.'
    if block in engine_owners and (block == 15 or number in (1, 2, 3, 4, 5, 6, 12)):
        boundary, owner, destination = engine_owners[block]
        note = 'Full source engine/schema behavior belongs to this owner; reusable CoreX binding remains separately qualified.'
    if block in consumer_overrides:
        boundary, owner = consumer_overrides[block]
        note = 'Historical product/profile condition retained; no product table, role, command or agent policy transferred to CoreX.'
    if block == 10 and 26 <= number <= 30:
        requirement, boundary, owner = 'R48', 'excluded', 'Flex component/UI owner'
    if block == 13 and 30 <= number <= 38:
        requirement, boundary, owner = 'R35', 'app', trace['R35'][3]
    if block == 13 and number == 42:
        requirement, boundary, owner = 'R18', 'app', trace['R18'][3]
        note = '10K leads and post-backfill domain counters/facets are consumer import acceptance; generic import/checkpoint requirement remains separately anchored R12.'
    if block == 27 and number == 3:
        requirement, boundary, owner = 'R19', 'app', trace['R19'][3]
    if block == 10 and number == 18:
        requirement, boundary, owner = 'R50', 'app', 'Flex API consumer maintainer; CoreX API maintainer for qualification'
        note = 'Automatic CRUD is a historical proposal, not an approved generic CoreX API; qualify existing SPI before a bounded choice in P2/P8.'
    if block == 10 and number == 19:
        note = 'Eight-class historical Flex compiler policy retained; no new L0 closed class list, registry, ninth extender or RFC approval inferred.'
    if block == 11 and 21 <= number <= 27:
        boundary, owner = 'app', 'Flex permissions/role policy owner; AzGuard engine owner for native mechanism'
    if block == 11 and number == 14 and 'users-строку' in fragment:
        requirement, boundary, owner = 'R43', 'app', trace['R43'][3]
    if block == 11 and number == 1 and 'aut-таблицах' in fragment:
        requirement, boundary, owner = 'R51', 'app', 'Flex permissions schema owner; AzGuard native schema owner'
    if block == 11 and number == 32:
        requirement, boundary, owner = 'R52', 'external-ops', 'Control-plane/schema owner and tenant consumer schema owner'
        note = 'Historical UUIDv7/schema profile; accepted CoreX ULID/Contract policy stays unchanged. Root and tenant schemas need separate receipts.'
    if block == 11 and number == 28:
        requirement, boundary, owner = ('R51', 'corex', 'Optional CoreX auth adapter maintainer') if 'auth' in fragment else ('R07', 'corex', trace['R07'][3]) if 'SingleAccountResolver' in fragment else ('R41', 'app', trace['R41'][3])
        if 'SingleAccountResolver' in fragment:
            requirement, boundary, owner = 'R52', 'corex', 'CoreX boxed tenant-context adapter maintainer'
            note = 'Single-account context profile is independence/optional-tenancy qualification, not versioned central account-list/cache acceptance R07.'
        if 'auth' in fragment:
            boundary, owner = 'app', 'Boxed consumer auth composition owner; optional CoreX/AzGuard adapter maintainers'
            note = 'Complete source-profile auth functionality needs installed consumer/engine proof; optional reusable adapter is a separate non-AC qualification.'
    if block == 11 and number in (29, 30):
        requirement, boundary, owner = 'R52', 'corex', 'CoreX contract/tenancy/layer-test maintainers'
    if block == 12 and number == 1:
        requirement = 'R31' if '`wfl_drafts`' in fragment else 'R39' if 'root_' in fragment else 'R09'
        boundary = 'app' if '`wfl_drafts`' in fragment else 'external-ops' if 'root_' in fragment else 'standalone'
        owner = {'app': 'Proprietary workflow draft owner', 'external-ops': 'Control-plane wakeup owner', 'standalone': 'Workflow engine schema owner'}[boundary]
        note = 'Historical schema profile owned by respective schema producer; UUIDv7 does not rewrite CoreX model IDs.'
    if block == 12 and number == 13 and 'p95' in fragment:
        requirement, boundary, owner = 'R46', 'external-ops', trace['R46'][3]
    elif block == 12 and number in (13, 15):
        boundary, owner = 'external-ops', 'Control-plane wakeup/sweep infrastructure adapter owner'
        note = 'Concrete root-table/lease/sweep operation is external infrastructure conformance; generic wakeup scheduling port is bound separately.'
    if block == 12 and number == 14:
        boundary, owner = 'standalone', 'Workflow engine/local-sweep adapter owner'
    if block == 31 and number == 14:
        boundary, owner = 'app', 'Flex saved-view schema/persistence owner'
        note = 'Concrete workspace/default-tab SQL profile outside reusable saved-view descriptor; qualify generic owner-scoped persistence separately.'
    if block == 32 and number == 7:
        requirement, boundary, owner = 'R35', 'app', trace['R35'][3]
        note = 'Consumer edition/no_ai/tool-role catalog policy; generic descriptor discovery has separate non-AC binding and deny tests.'
    if block == 32 and number == 12 and 'lint' in fragment:
        requirement, boundary, owner = 'R35', 'app', trace['R35'][3]
    if block == 32 and number in (8, 9, 10):
        boundary, owner = 'app', 'Flex agent/tool authorization policy owner; AzGuard engine maintainer'
    if block == 12 and number == 20:
        requirement, boundary, owner = 'R18', 'app', trace['R18'][3]
        note = 'Concrete deal event scenario is consumer integration acceptance; generic wakeup/dedup binding requires independent non-CRM proof.'
    if block == 15 and number == 26:
        requirement = 'R36' if 'MCP-генератор' in fragment else 'R11'
        boundary, owner = ('app', 'Flex MCP catalog/handler owner') if requirement == 'R36' else ('standalone', 'Attributes API/metadata owner')
        destination = trace[requirement][7]
    if block == 15 and number == 27:
        boundary, owner = ('app', 'Tariff/quota policy owner') if 'grandfather' in fragment else ('standalone', 'Attributes engine/quota provider owner')
    if block == 15 and number == 28:
        boundary, owner = 'app', 'Consumer attributes/search slot-policy and installed PG/Meili adapter owners'
    if block == 15 and number == 30:
        boundary, owner = 'external-ops', 'Restore orchestrator; field verification, queue discard and search rebuild adapter owners'
    if block == 16 and number == 1:
        requirement = 'R14' if 'compile' in fragment else 'R37'
        boundary = 'corex' if requirement == 'R14' else 'app'
        owner = trace[requirement][3] if requirement == 'R14' else 'Chatom and Vaulter registration owners'
    if block == 16 and number in (12, 14, 15, 16):
        boundary, owner = 'app', {12: 'Installed media schema/lifecycle adapter owner', 14: 'Flex notification digest/provider policy owner', 15: 'Flex boxed notification/channel composition owner', 16: 'Vaulter/workflow deal-signal adapter owner'}[number]
        note = 'Provider/product scenario retained outside reusable port; generic seam has non-AC programme binding and independent conformance obligation.'
    if block == 16 and number == 18 and 'Vaulter' in fragment:
        requirement, boundary, owner = 'R37', 'app', trace['R37'][3]
    if block == 16 and number == 20:
        boundary, owner = 'cross-cutting', 'PanelHost/RelationLookup CoreX owner; MessageService Chatom owner; PermissionResolver Vaulter owner; NotificationCenter installed provider owner'
        note = 'One common analysis criterion applied independently to five explicitly owned contracts; no second messaging/permission engine in CoreX.'
    if block == 18 and number in (16, 17, 18, 19, 24):
        requirement, boundary, owner = 'R30', 'excluded', 'CommerceML/commerce source consumer owner'
        destination = trace['R30'][7]
        note = 'Commerce-specific behavior retained as excluded owning continuation; historical corex/1c-sync package string grants no L0 scope.'
    if block == 18 and number == 6:
        if 'UI ' in fragment or 'сохранение шаблона' in fragment:
            requirement, boundary, owner = 'R13', 'app', 'Import preview/template consumer owner'
        else:
            requirement, boundary, owner = 'R12', 'standalone', 'Import mapping/sample engine owner'
    if block == 18 and number == 21:
        requirement = 'R33' if 'AccountExportService' in fragment else 'R12'
        boundary, owner = 'standalone', 'Offboarding/export engine owner; CoreX maintenance/import port maintainers separately'
    if block == 18 and number == 25:
        requirement, boundary, owner = 'R12', 'standalone', 'Import engine contract maintainer; each aggregate provider owner'
        note = 'Idempotent target protocol is reusable; native engine contract and actual target event/write conformance need distinct receipts, not a duplicate CoreX import engine.'
    if block == 33 and number == 7:
        boundary, owner = 'app', 'MediaLibrary and Vaulter TenantStorage adapter owners'
        note = 'Both concrete adapters must use the one accepted TenantStorage contract; their registration/config receipts remain distinct from primitive proof.'
    if block == 33 and number == 14:
        requirement, boundary, owner = 'R01', 'external-ops', 'Boxed storage driver owner and consumer saga/restore/export owners'
        note = 'Local durable/ephemeral driver conformance is reusable; every source §5 operational scenario remains separately owned and cannot inherit one local storage test.'
    if block == 34:
        if number == 2:
            requirement, boundary, owner = 'R03', 'external-ops', 'Central identity owner; tenant sync/invitation/import adapter owners'
            note = 'Universal no-email/phone-merge invariant spans all concrete entry paths; accepted CoreX stable-ID primitive proof stays bounded and is separately bound in Requirements.'
        if number in (3, 4, 6, 10):
            boundary, owner = 'external-ops', 'Central identity/membership/switcher owner'
        if number == 8 and 'wsp.member.remove' in fragment:
            requirement, boundary, owner = 'R43', 'app', trace['R43'][3]
        if number == 5 and 'is_active' in fragment:
            boundary, owner = 'app', 'FlexCRM auth/request guard owner'
        if number == 7:
            boundary, owner = ('app', 'Tenant integration/session/projection owner') if any(token in fragment for token in ('не пишет', 'не использует', 'не копирует')) else ('external-ops', 'Central and tenant cross-database boundary owners')
        if number == 12 and 'users_max' in fragment:
            requirement, boundary, owner = 'R43', 'app', 'Tenant membership acceptance/quota policy owner'
        if number == 19:
            tenant = any(token in fragment for token in ('wsp_invitations', 'wsp_workspace_members', 'users_max', 'редирект на поддомен CRM'))
            requirement, boundary, owner = ('R43', 'app', 'Tenant invitation/token/role/users_max acceptance owner') if tenant else ('R40', 'external-ops', trace['R40'][3])
            note = 'Historical source-reference to B11 AC-19; central is the sole root membership writer, tenant accepts invitation and owns workspace role/quota.'
    if block == 35:
        if number == 1:
            requirement = 'R52' if 'root-схема' in fragment else 'R26'
            boundary, owner = ('external-ops', 'Control-plane canonical schema owner') if requirement == 'R52' else ('app', 'Consumer intl/geo/fx/translation schema maintainers')
        if number == 5:
            requirement, boundary, owner = 'R32', 'app', 'Consumer localized document producer; installed Meili and boxed PG search adapter owners'
            note = 'Exact historical localized method and PG degradation are installed consumer/provider conformance; generic optional search capability remains separately qualified.'
        if number in (1, 7, 8, 10, 15):
            requirement = 'R36' if 'side_effect' in fragment else requirement
            boundary, owner = 'app', 'Flex intl/geo/fx/translation/schema/UI policy and installed provider owners'
            if number == 1 and requirement == 'R52':
                boundary, owner = 'external-ops', 'Control-plane canonical schema owner'
            note = 'Concrete source-profile tables/UI/read-only/manual fetch policy retained outside L0; portable intl/search/descriptor seam qualified separately.'
        if number == 2:
            boundary, owner = 'app', 'Consumer locale context/settings binding owner'
        if number == 11:
            boundary, owner = 'app', 'Flex intl/event namespace composition owner'
        if number == 6:
            boundary, owner = 'standalone', 'Installed geography dataset/lookup provider owner'
            note = 'Specific geo index/performance is provider conformance; no mandatory dataset/DDL in CoreX lookup contract.'
    # Requirement changes must carry the corrected programme destination.
    if requirement != old['R']:
        destination = trace[requirement][7]
    return requirement, boundary, owner, destination, note


for parent in parents:
    raw = slices(parent['full_body'], cuts.get(parent['label'], ()))
    refined = []
    for ordinal, fragment in enumerate(raw, 1):
        requirement, boundary, owner, destination, note = ownership(parent, fragment, ordinal)
        refined.append({
            'Atom': parent['label'] + '.a' + str(ordinal), 'Parent AC': parent['label'],
            'R': requirement, 'Boundary': boundary, 'Owner': owner,
            'Condition': fragment.strip(), 'Source fragment': fragment,
            'Required evidence': f"{parent['path']} {parent['location']}; parent {parent['original_sha256']}; {note} {trace[requirement][6]}",
            'Destination': destination,
        })
    parent['atoms'] = refined
    parent['correction'] += ' Explicit refinement: lossless source slices; separate consumer/native engine/schema ownership. Qualification never promotes historical profile names to accepted L0 contracts.'
    assert ''.join(atom['Source fragment'] for atom in refined) == parent['full_body']
    if parent['label'] == 'B12 AC-1':
        parent['correction'] += ' Source-owner contradiction retained: all-PK-uuid aggregate versus §2.1 wfl_logs bigint identity PK and deliberate absence of updated_at; resolve in owning workflow schema decision, not by changing snapshots/CoreX IDs.'
    if parent['label'] == 'B13 AC-1':
        parent['correction'] += ' Source-owner clarification retained: aggregate all-PK-uuid versus §2.6 vrt_records composite textual code/record_type/reference key; owning CRM schema decision required, no silent snapshot repair.'
    if parent['label'] == 'B15 AC-1':
        parent['correction'] += ' Source profile exception: §2 declares UUID PK convention but fld_schema_state is a smallint singleton. Preserve explicit DDL exception; no global L0 ID rewrite.'
    if parent['label'] == 'B31 AC-14':
        parent['correction'] += ' Owning schema qualification: §2.2 unique index includes nullable workspace_id without NULLS NOT DISTINCT; claimed account-scope default uniqueness requires a null-scope concurrency test/owner correction, not a P1 acceptance claim.'
    if parent['label'] == 'B32 AC-2':
        parent['correction'] += ' Source-owner policy conflict: aggregate write/irreversible confirmation requirement versus §8.4.1 allowed write-med internal-runtime relaxation referring §3.4/Q25; stricter denial remains the unaccepted branch pending owner choice, no permission to bypass confirmation inferred.'
    if parent['label'] == 'B35 AC-7':
        parent['correction'] += ' Source-owner translation allowlist clarification: AC says exactly process-template.name/saved-view.name/geo_*.name, while §9 row 11 also mentions process-template.description; preserve both definitions and require an explicit consumer schema/translation choice.'
    if parent['label'] == 'B16.§8.18':
        parent['correction'] += ' Privacy capability is a required outcome, not implemented automation proof: §9 questions 3–4 leave manual/semi-automatic document redaction and retention worker details unresolved; own provider/legal qualification must preserve other participants and history.'

# Expand normative referenced outcomes without discarding the parent referring
# clause. The root clause remains in its existing atom as coverage context;
# targets below are additional independently testable source conditions.
targets = {
    'B11 AC-8': ('b11', 864, 869, 'external-ops', 'Control-plane restore orchestrator and named field/queue/search/wakeup/identity/usage owners', 'R39'),
    'B12 AC-10': ('b12', 842, 844, 'standalone', 'Workflow approval engine owner', 'R09'),
}
for parent in parents:
    if parent['label'] not in targets:
        continue
    source, first, last, boundary, owner, requirement = targets[parent['label']]
    lines = (plan / f'brief/sources/{source}.md').read_text().splitlines(keepends=True)
    for line_number in range(first, last + 1):
        body = lines[line_number - 1]
        markers = ('Итог approved',) if parent['label'] == 'B12 AC-10' and line_number == 843 else ()
        for fragment in slices(body, markers):
            ordinal = len(parent['atoms']) + 1
            parent['atoms'].append({
                'Atom': parent['label'] + '.a' + str(ordinal), 'Parent AC': parent['label'],
                'R': requirement, 'Boundary': boundary, 'Owner': owner,
                'Condition': fragment.strip(), 'Source fragment': fragment,
                'Referenced target': f'brief/sources/{source}.md:L{line_number}',
                'Required evidence': f"brief/sources/{source}.md:L{line_number}; target SHA256 {hashlib.sha256(body.encode()).hexdigest()}; {trace[requirement][6]}; source definition only",
                'Destination': trace[requirement][7],
            })
    parent['correction'] += f' Referenced normative target expanded losslessly: {source} L{first}–{last}; original referring clause retained.'

# The common static-analysis predicate applies to each contract, whose native
# owner differs. Keep the complete original span as coverage context, then
# project its unchanged predicate onto each named contract.
for parent in parents:
    if parent['label'] != 'B16.§8.20':
        continue
    parent['atoms'] = []
    for name, requirement, boundary, owner in (
        ('PanelHost', 'R14', 'corex', trace['R14'][3]),
        ('MessageService', 'R38', 'app', 'Chatom MessageService owner'),
        ('PermissionResolver', 'R37', 'app', 'Vaulter PermissionResolver owner'),
        ('NotificationCenter', 'R16', 'app', 'Installed notification provider owner'),
        ('RelationLookup', 'R14', 'corex', trace['R14'][3]),
    ):
        ordinal = len(parent['atoms']) + 1
        parent['atoms'].append({
            'Atom': parent['label'] + '.a' + str(ordinal), 'Parent AC': parent['label'],
            'R': requirement, 'Boundary': boundary, 'Owner': owner,
            'Condition': f'Контракт `{name}` проходит PHPStan level 8 без `@phpstan-ignore`.',
            'Source fragment': parent['full_body'] if ordinal == 1 else '',
            'Focus': name,
            'Required evidence': f"{parent['path']} {parent['location']}; parent {parent['original_sha256']}; shared original level-8/no-ignore predicate projected onto exact named contract; no engine clone or runtime acceptance",
            'Destination': trace[requirement][7],
        })
    parent['correction'] += ' Common PHPStan predicate split across all five exact contracts with their own native owners; original clause preserved as source context.'

framing = {
    'B10 AC-5': 'При enable из available',
    'B10 AC-7': 'При падении миграции K и последующем повторе enable',
    'B10 AC-9': 'При uninstall(keepData=true) и последующем re-enable',
    'B10 AC-16': 'Extend\\Permissions при enable',
    'B10 AC-20': 'Auditable observer при CRUD',
    'B11 AC-31': 'Impersonation по support-гранту',
    'B13 AC-20': 'FSM задачи; действие complete',
    'B34 AC-19': 'Исходная последовательность принятия приглашения B34 L205–212',
    'B34 AC-7': 'Для каждого потока B34',
    'B18.§8.6': 'CSV/XLSX previewMapping',
}
for parent in parents:
    for atom in parent['atoms']:
        if parent['label'] in framing:
            atom['Condition'] = framing[parent['label']] + ': ' + atom['Condition'].strip(' ;+→')
        if parent['label'] == 'B11 AC-8' and 'Referenced target' in atom:
            atom['Condition'] = 'Пост-restore reconciliation: ' + atom['Condition'].lstrip('- ')
        if parent['label'] == 'B12 AC-10' and 'Referenced target' in atom:
            line_number = int(atom['Referenced target'].split(':L')[1])
            mode = {842: 'parallel', 843: 'sequential', 844: 'quorum'}[line_number]
            atom['Condition'] = 'Approval mode=' + mode + ': ' + atom['Condition'].lstrip('- ').strip(' ;')

result = {'schema_version': 'p1-semantic-model/v1', 'status': 'OPEN', 'parents': parents,
          'policy': 'Explicit refinements and lossless coverage checks are review inputs, not autonomous semantic approval.'}
(artifacts / 'P1.2-semantic-model.json').write_text(json.dumps(result, ensure_ascii=False, indent=2) + '\n')
print(json.dumps({'parents': len(parents), 'atoms': sum(len(parent['atoms']) for parent in parents), 'status': 'OPEN'}))

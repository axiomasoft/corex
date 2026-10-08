"""Historical transformation: run on the pre-baseline tree after removing the two folded ALTER files."""
import json
import re
import subprocess
from pathlib import Path

root = Path('packages/core/database/migrations')
order = ['mod_modules', 'mod_records', 'mod_lifecycle_log', 'sys_settings', 'sys_audit_log', 'sys_feature_flags', 'sys_feature_overrides', 'sys_departments', 'sys_department_user', 'sys_pubsub_messages', 'sys_pubsub_cursors', 'sys_outbox_messages', 'sys_inbox_messages']
renames = {
    'mod_ll_module_ix': 'mod_lifecycle_log_module_ix', 'mod_ll_op_ck': 'mod_lifecycle_log_operation_ck', 'mod_ll_status_ck': 'mod_lifecycle_log_status_ck', 'mod_ll_actor_ck': 'mod_lifecycle_log_actor_ck',
    'sys_audit_actor_ck': 'sys_audit_log_actor_ck', 'sys_audit_seq_ix': 'sys_audit_log_seq_ix', 'sys_audit_entity_ix': 'sys_audit_log_entity_ix', 'sys_audit_actor_ix': 'sys_audit_log_actor_ix', 'sys_audit_wsp_ix': 'sys_audit_log_workspace_ix', 'sys_audit_brin_ix': 'sys_audit_log_occurred_brin_ix',
    'sys_fo_scope_ck': 'sys_feature_overrides_scope_ck', 'sys_fo_uq': 'sys_feature_overrides_flag_scope_uq',
    'sys_dept_parent_fk': 'sys_departments_parent_fk', 'sys_dept_path_uq': 'sys_departments_path_uq', 'sys_dept_path_gist': 'sys_departments_path_gist_ix', 'sys_dept_wsp_ix': 'sys_departments_workspace_ix',
    'sys_du_role_ck': 'sys_department_user_role_ck', 'sys_du_primary_uq': 'sys_department_user_primary_uq',
    'sys_outbox_messages_claim_idx': 'sys_outbox_messages_claim_ix', 'sys_outbox_messages_reclaim_idx': 'sys_outbox_messages_reclaim_ix', 'sys_outbox_messages_published_idx': 'sys_outbox_messages_published_ix', 'sys_outbox_messages_dead_idx': 'sys_outbox_messages_dead_ix', 'sys_inbox_messages_processed_idx': 'sys_inbox_messages_processed_ix',
}
subprocess.run(['git', 'mv', str(root/'pg'), str(root/'tenant')], check=True)
files = list(root.glob('*.php')) + list((root/'tenant').glob('*.php'))
for i, table in enumerate(order, 1):
    source = next(p for p in files if f'create_{table}_table' in p.name)
    s = source.read_text()
    s = s.replace("$table->uuid('id')->primary();", f"$table->uuid('id');\n            $table->primary(['id'], '{table}_pk');")
    s = re.sub(r"\$table->primary\((\[[^\n]+?\])\);", rf"$table->primary(\1, '{table}_pk');", s)
    s = s.replace("$table->string('key', 128)->unique();", "$table->string('key', 128);\n            $table->unique(['key'], 'sys_feature_flags_key_uq');")
    if table == 'sys_feature_overrides':
        s = s.replace("$table->foreignUuid('flag_id')->constrained('sys_feature_flags')->cascadeOnDelete();", "$table->uuid('flag_id');\n            $table->foreign(['flag_id'], 'sys_feature_overrides_flag_fk')->references('id')->on('sys_feature_flags')->cascadeOnDelete();")
    if table == 'sys_department_user':
        s = s.replace("$table->foreignUuid('department_id')->constrained('sys_departments')->cascadeOnDelete();", "$table->uuid('department_id');\n            $table->foreign(['department_id'], 'sys_department_user_department_fk')->references('id')->on('sys_departments')->cascadeOnDelete();")
    if table == 'sys_pubsub_messages':
        s = s.replace('$table->id();', "$table->bigInteger('id', autoIncrement: true);\n            $table->primary(['id'], 'sys_pubsub_messages_pk');")
        s = s.replace("$table->index(['channel', 'id']);", "$table->index(['channel', 'id'], 'sys_pubsub_messages_channel_id_ix');")
    if table == 'sys_audit_log':
        s = s.replace("'user','agent','api_key','system'", "'user','agent','api_key','system','support'")
    if table == 'mod_lifecycle_log':
        s = s.replace("'user','system','platform','cli'", "'user','system','platform','cli','support'")
    s = s.replace("$table->integer('sort_order')", "$table->bigInteger('sort_order')").replace("$table->unsignedInteger('attempts')", "$table->unsignedBigInteger('attempts')")
    for old, name in renames.items():
        s = s.replace(old, name)
    if table in ['sys_inbox_messages', 'sys_outbox_messages']:
        pos = s.rfind('};')
        s = s[:pos] + f"\n    public function down(): void\n    {{\n        Schema::dropIfExists('{table}');\n    }}\n" + s[pos:]
    s = s.replace('        });', f"        }});\n\n        Schema::getConnection()->statement('ALTER TABLE {table} RENAME CONSTRAINT {table}_pkey TO {table}_pk');", 1)
    s = s.replace('// Executed from the package schema set on its declared connection.\n', '')
    s = re.sub(r'        // Beyond the DRAFT.*?(?=        DB::statement)', '', s, flags=re.S)
    s = re.sub(r'        // Trusted extension.*?(?=        DB::statement)', '', s, flags=re.S)
    s = re.sub(r'        // Self-referencing FK.*?(?=        DB::statement)', '        // Add the self-reference after the primary key exists.\n', s, flags=re.S)
    if table == 'mod_records':
        a = s.index('        // Monotonic surrogate')
        b = s.index("        DB::statement('ALTER TABLE mod_records", a)
        s = s[:a] + "        // Insertion order determines reverse, foreign-key-safe purge order.\n" + s[b:]
        a = s.index('// Ownership')
        b = s.index('return new class', a)
        s = s[:a] + "// Logical module ownership keys deliberately have no foreign key to mod_modules.\n" + s[b:]
    if table == 'sys_audit_log':
        a = s.index('        // Monotonic audit')
        b = s.index('        DB::statement', a)
        s = s[:a] + "        // A sequence keeps keyset pagination independent of wall-clock ordering.\n" + s[b:]
    if table == 'sys_pubsub_cursors':
        a = s.index('// PG-only')
        b = s.index('return new class', a)
        s = s[:a] + "// Durable subscriber positions use the transport's monotonic message cursor.\n" + s[b:]
    target = root/'tenant'/f'2026_10_01_01{i*10:04d}_create_{table}.php'
    subprocess.run(['git', 'mv', str(source), str(target)], check=True)
    target.write_text(s)
provider = Path('packages/core/src/Database/Internal/CoreSchemaSets.php')
s = provider.read_text().replace("$root.'/pg'", "$root.'/tenant'")
s = '\n'.join(line for line in s.split('\n') if "name: 'tenant-modules'" not in line)
provider.write_text(s)
for rootpath in [Path('tests'), Path('packages/core/tests'), Path('products/flex-crm/tests')]:
    for p in rootpath.rglob('*.php'):
        s = p.read_text()
        t = s.replace("['axiomasoft/corex:tenant', 'axiomasoft/corex:tenant-modules']", "['axiomasoft/corex:tenant']").replace('axiomasoft/corex:tenant-modules', 'axiomasoft/corex:tenant')
        t = t.replace('core/database/migrations/pg/', 'core/database/migrations/tenant/')
        t = t.replace('2026_07_13_100000_create_sys_settings_table', '2026_10_01_010040_create_sys_settings')
        if p.name == 'SchemaSetsPgTest.php':
            t = t.replace('sets()[1]', 'sets()[0]')
        t = t.replace('migrates core tenant schema directly from both registered platform sets', 'migrates the complete core tenant baseline from its single platform set')
        if t != s:
            p.write_text(t)
p = Path('packages/core/tests/Modules/Feature/LifecycleRemediationTest.php')
s = p.read_text()
a = s.index('    foreach ([app(SchemaSets::class)')
b = s.index('    $discovery', a)
s = s[:a] + "    $this->migrateSchemaSets(['axiomasoft/corex:tenant'], 'pgsql');\n" + s[b:]
s = s.replace('use CoreX\\Database\\Contracts\\SchemaSets;\n', '')
p.write_text(s)
p = Path('scripts/schema-gate-pending.json')
pending = json.loads(p.read_text())
pending.remove('axiomasoft/corex')
p.write_text(json.dumps(pending, indent=4) + '\n')

"""Prove full catalogue equality after only D113/D114 transformations."""
import copy,json
from pathlib import Path
root=Path(__file__).parent
bigints={('root_clusters','db_count'),('root_clusters','template_version'),('root_templates','version'),('root_accounts','template_version'),('root_migration_batches','from_version'),('root_migration_batches','to_version'),('root_account_schema_state','current_version'),('root_account_schema_state','target_version'),('root_wakeup_reconciliation_state','attempts'),('sessions','last_activity')}
for scope in ['root','tenant']:
 before=json.loads((root/f'tenancy-{scope}-before.json').read_text())
 after=json.loads((root/f'tenancy-{scope}-after.json').read_text())
 for table,definition in before['tables'].items():
  for col,shape in definition['columns'].items():
   if (table,col) in bigints:
    assert shape['type']=='integer',(table,col)
    shape['type']='bigint'
    if shape['default'] is not None:shape['default']="'"+shape['default']+"'::bigint"
 if scope=='root':
  accounts=before['tables']['root_accounts'];del accounts['columns']['data']
  for col,typ in [('signup_key','text'),('signup_request_digest','text'),('signup_cluster_id','uuid'),('signup_template_id','uuid')]:accounts['columns'][col]={'type':typ,'nullable':True,'default':None}
  accounts['columns']['pool_cleanup']={'type':'boolean','nullable':False,'default':'false'}
  index=next(i for i in accounts['indexes'] if i['name']=='root_accounts_signup_key_uq')
  index.update(columns=['signup_key'],predicate='((signup_key IS NOT NULL) AND (deleted_at IS NULL))')
  check=next(c for c in after['tables']['root_accounts']['checks'] if c['name']=='root_accounts_signup_ck')
  assert check['expression']=='(((signup_key IS NULL) AND (signup_request_digest IS NULL) AND (signup_cluster_id IS NULL) AND (signup_template_id IS NULL)) OR ((signup_key IS NOT NULL) AND (signup_request_digest IS NOT NULL) AND (signup_cluster_id IS NOT NULL) AND (signup_template_id IS NOT NULL)))'
  accounts['checks'].append(copy.deepcopy(check))
 # Keep every index method/expression/order, complete FK actions, check and trigger.
 for catalogue in [before,after]:
  for table,definition in catalogue['tables'].items():
   for kind in ['indexes','foreign_keys','checks']:
    for obj in definition[kind]:del obj['name']
    definition[kind].sort(key=lambda obj:json.dumps(obj,sort_keys=True))
 assert before==after,scope
 print(scope+': GREEN — exact shape after object renames, approved int→bigint/default casts and D114; index methods, FK actions, CHECKs, triggers preserved')

from pathlib import Path
from task import plan_context
import pickle,json,sys
p=Path('plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING');i=sys.argv[1]
s=plan_context.capture(p,i);Path('/tmp/corex-p2-context-'+i+'.pkl').write_bytes(pickle.dumps(s));print(json.dumps(s.receipt(),ensure_ascii=False,indent=2))

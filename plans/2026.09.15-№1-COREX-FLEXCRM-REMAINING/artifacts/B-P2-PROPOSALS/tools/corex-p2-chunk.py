import pickle,sys,json
from pathlib import Path
s=pickle.loads(Path('/tmp/corex-p2-context-'+sys.argv[1]+'.pkl').read_bytes());role=sys.argv[2];offset=int(sys.argv[3]);limit=int(sys.argv[4]);x=s.chunk(role,offset=offset,limit=limit);content=x.pop('content');print(json.dumps(x,ensure_ascii=False));print(content.decode('utf-8'));print('EXPOSED-CHUNK-END',x['next_offset'])

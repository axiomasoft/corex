import sys,json,pathlib
out=pathlib.Path(__file__).resolve().parent
for name in sys.argv[1:]:
 d=json.loads((out/f'current-{name}.json').read_text());print(name,d['totals'],d.get('errors'))
 for f,x in d['files'].items():
  print(f.split('/src/')[-1])
  for e in x['messages']:print(e['line'],e['identifier'],e['message'])

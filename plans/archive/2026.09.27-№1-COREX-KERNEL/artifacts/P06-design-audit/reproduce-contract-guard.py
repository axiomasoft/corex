from pathlib import Path
import json,subprocess,tempfile
source=Path(__file__).resolve().parents[4]/'scripts/contract-guard.sh'
# This fixture is disposable. No tracked product files or repository refs are changed.
with tempfile.TemporaryDirectory(prefix='corex-contract-guard-audit-') as td:
    work=Path(td)
    def git(*args):
        return subprocess.check_output(['git','-C',str(work),*args],text=True).strip()
    git('init','-q'); git('config','user.email','audit@example.test'); git('config','user.name','Design audit fixture')
    f=work/'packages/tools/resources/contracts/php-api.json'; f.parent.mkdir(parents=True); f.write_text('{}\n')
    changelog=work/'packages/tools/CHANGELOG.md'; changelog.write_text('# Changelog\n')
    git('add','.'); git('commit','-qm','base'); base=git('rev-parse','HEAD')
    f.write_text('{"new_type":"ToolOutcome"}\n'); git('add','.'); git('commit','-qm','candidate contract only')
    red=subprocess.run(['bash',str(source),base],cwd=work,text=True,capture_output=True)
    print(json.dumps({'case':'manifest without CHANGELOG','exit':red.returncode,'stdout':red.stdout},ensure_ascii=False))
    assert red.returncode==1
    changelog.write_text('# Changelog\n\nToolOutcome replaces string outcome.\n'); git('add','.'); git('commit','-qm','matching changelog')
    green=subprocess.run(['bash',str(source),base],cwd=work,text=True,capture_output=True)
    print(json.dumps({'case':'manifest with CHANGELOG','exit':green.returncode,'stdout':green.stdout},ensure_ascii=False))
    assert green.returncode==0

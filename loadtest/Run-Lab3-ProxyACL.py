import datetime as dt
import json
from pathlib import Path
import subprocess

files=['docker-compose.yml','docker-compose.lab1.yml','docker-compose.lab2.yml',
       'docker-compose.lab2-network.yml','docker-compose.lab2-http.yml',
       'docker-compose.lab3.yml','docker-compose.lab3-fixed.yml','docker-compose.lab3-egress.yml']
compose=['docker','compose']+[x for file in files for x in ['-f',file]]
out=Path('work')/('lab3-egress-acl-'+dt.datetime.now().strftime('%Y%m%d-%H%M%S'))
out.mkdir(parents=True)

def run(*args):
    p=subprocess.run(compose+list(args),capture_output=True,text=True,encoding='utf8',errors='replace')
    if p.returncode: raise RuntimeError(p.stdout+p.stderr)
    return p.stdout

run('cp','./loadtest/lab3-egress-probe.php','app1:/tmp/lab3-egress-probe.php')
text=run('exec','-T','app1','php','/tmp/lab3-egress-probe.php')
rows=json.loads(text)
(out/'results.json').write_text(json.dumps(rows,indent=2),encoding='utf8')
print(json.dumps(rows,indent=2),flush=True)
logs=run('exec','-T','squid','tail','-n','30','/var/log/squid/access.log')
(out/'squid-access.log').write_text(logs,encoding='utf8')
print('\nSquid access log:\n'+logs,flush=True)
print('Results:',out,flush=True)
if not all(r['passed'] for r in rows):
    raise SystemExit('An ACL check failed; do not proceed to network isolation yet.')
print('PASS: public HTTPS allowed; four internal targets blocked. Global proxy and network isolation are NEXT, not yet implemented.',flush=True)

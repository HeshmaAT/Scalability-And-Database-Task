import datetime,json,subprocess
from pathlib import Path
files=['docker-compose.yml','docker-compose.lab1.yml','docker-compose.lab2.yml','docker-compose.lab2-network.yml','docker-compose.lab2-http.yml','docker-compose.lab3.yml','docker-compose.lab3-fixed.yml','docker-compose.lab3-egress.yml','docker-compose.lab3-isolated.yml','docker-compose.lab4-origin.yml','docker-compose.lab4-edges.yml','docker-compose.lab4-signed.yml','docker-compose.lab5.yml','docker-compose.lab5-analytics.yml']
base=['docker','compose']+[v for f in files for v in ['-f',f]]
out=Path('work')/('lab5-analytics-'+datetime.datetime.now().strftime('%Y%m%d-%H%M%S'));out.mkdir(parents=True)
def run(*args):
    p=subprocess.run(base+list(args),capture_output=True,text=True,encoding='utf8',errors='replace')
    if p.returncode: raise RuntimeError(p.stdout+p.stderr)
    return p.stdout
def state(): return json.loads(run('exec','-T','app1','php','/tmp/lab5-fixture.php','state',str(fixture['link_id'])))
run('cp','./loadtest/lab5-analytics-fixture.php','app1:/tmp/lab5-fixture.php')
fixture=json.loads(run('exec','-T','app1','php','/tmp/lab5-fixture.php','create'))
(out/'fixture.json').write_text(json.dumps(fixture,indent=2))
def command(name,skip=False):
    return base+['exec','-T','app1','php','artisan','events:consume','analytics-writer','--once','--count=200','--consumer='+name,'--stream='+fixture['stream']]+(['--skip-ack'] if skip else [])
processes=[subprocess.Popen(command('probe-1',True),stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True),subprocess.Popen(command('probe-2'),stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True)]
# Retry once if the shared maintenance lock was busy. Pending entries retain ownership.
for name,p in zip(['probe-1','probe-2'],processes):
    stdout,stderr=p.communicate(timeout=60)
    (out/(name+'.log')).write_text(stdout+stderr)
    if p.returncode:
        r=subprocess.run(command(name,name=='probe-1'),capture_output=True,text=True)
        if r.returncode: raise RuntimeError(r.stdout+r.stderr)
before=state();print('After two consumers:',json.dumps(before),flush=True)
assert before['count']==400 and before['raw']==400,before
assert len(before['consumers'])==2,before
r=subprocess.run(command('probe-1'),capture_output=True,text=True)
if r.returncode: raise RuntimeError(r.stdout+r.stderr)
after=state();assert after==before,(before,after)
print('PASS: committed-but-unacknowledged events replayed without increasing raw rows or counter.',flush=True)
# Duplicate event UUID in a NEW stream entry must also have no second effect.
redis=['exec','-T','redis-streams','redis-cli']
run(*(redis+['XADD',fixture['stream'],'*']+fixture['first']))
r=subprocess.run(command('probe-2'),capture_output=True,text=True)
if r.returncode: raise RuntimeError(r.stdout+r.stderr)
duplicate=state();assert duplicate==before,(before,duplicate)
print('PASS: same event_id delivered under a new stream ID counted once.',flush=True)
(out/'results.json').write_text(json.dumps({'two_consumers':before,'after_pending_replay':after,'after_duplicate_event':duplicate},indent=2))
print('Results:',out,flush=True)

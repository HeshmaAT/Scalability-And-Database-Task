import datetime,json,subprocess
from pathlib import Path
files=['docker-compose.yml','docker-compose.lab1.yml','docker-compose.lab2.yml','docker-compose.lab2-network.yml','docker-compose.lab2-http.yml','docker-compose.lab3.yml','docker-compose.lab3-fixed.yml','docker-compose.lab3-egress.yml','docker-compose.lab3-isolated.yml','docker-compose.lab4-origin.yml','docker-compose.lab4-edges.yml','docker-compose.lab4-signed.yml','docker-compose.lab5.yml','docker-compose.lab5-analytics.yml','docker-compose.lab5-active.yml','docker-compose.lab5-milestones.yml']
base=['docker','compose']+[v for f in files for v in ['-f',f]]
out=Path('work')/('lab5-milestones-'+datetime.datetime.now().strftime('%Y%m%d-%H%M%S'));out.mkdir(parents=True)
def run(*args):
    p=subprocess.run(base+list(args),capture_output=True,text=True,encoding='utf8',errors='replace')
    if p.returncode:raise RuntimeError(p.stdout+p.stderr)
    return p.stdout
run('cp','./loadtest/lab5-milestone-fixture.php','app1:/tmp/lab5-ms.php')
f=json.loads(run('exec','-T','app1','php','/tmp/lab5-ms.php','create'))
(out/'fixture.json').write_text(json.dumps(f,indent=2))
consume=['exec','-T','app1','php','artisan','events:consume','milestones','--consumer=ms-probe','--once','--count=500','--stream='+f['stream']]
for _ in range(3):run(*consume)
def state():return json.loads(run('exec','-T','app1','php','/tmp/lab5-ms.php','state',str(f['id'])))
before=state()
assert before['projection_count']==1001 and before['processed_events']==1001,before
assert len(before['milestones'])==1 and len(before['outbox'])==1,before
print('Threshold crossed: 1001 unique clicks, one milestone row, one transactional outbox event.',flush=True)
run('exec','-T','redis-streams','redis-cli','XADD',f['stream'],'*',*f['first'])
run(*consume)
after=state();assert after==before,(before,after)
print('PASS: duplicate click event did not increment milestone projection or create another milestone.',flush=True)
run('exec','-T','app1','php','artisan','events:relay','--once','--stream='+f['stream'])
published=state();assert published['outbox'][0]['published_at'] is not None,published
event_id=published['outbox'][0]['event_id']
last=json.loads(run('exec','-T','redis-streams','redis-cli','--json','XREVRANGE',f['stream'],'+','-','COUNT','1'))
envelope=json.loads(last[0][1][1]);assert envelope['event_id']==event_id and envelope['event_type']=='MilestoneReached',envelope
run('exec','-T','app1','php','artisan','events:relay','--once','--stream='+f['stream'])
print('PASS: outbox relayed MilestoneReached with stable event_id; second normal relay had nothing to resend.',flush=True)
(out/'results.json').write_text(json.dumps({'before_duplicate':before,'after_duplicate':after,'published':published,'stream_event':envelope},indent=2))
print('Results:',out,flush=True)

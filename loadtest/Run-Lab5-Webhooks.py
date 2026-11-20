import datetime,json,secrets,subprocess,time
from pathlib import Path
files=['docker-compose.yml','docker-compose.lab1.yml','docker-compose.lab2.yml','docker-compose.lab2-network.yml','docker-compose.lab2-http.yml','docker-compose.lab3.yml','docker-compose.lab3-fixed.yml','docker-compose.lab3-egress.yml','docker-compose.lab3-isolated.yml','docker-compose.lab4-origin.yml','docker-compose.lab4-edges.yml','docker-compose.lab4-signed.yml','docker-compose.lab5.yml','docker-compose.lab5-analytics.yml','docker-compose.lab5-active.yml','docker-compose.lab5-milestones.yml','docker-compose.lab5-webhooks.yml','docker-compose.lab5-webhook-test.yml']
base=['docker','compose']+[v for f in files for v in ['-f',f]]
out=Path('work')/('lab5-webhooks-'+datetime.datetime.now().strftime('%Y%m%d-%H%M%S'));out.mkdir(parents=True)
def run(*args):
    p=subprocess.run(base+list(args),capture_output=True,text=True,encoding='utf8',errors='replace')
    if p.returncode:raise RuntimeError(p.stdout+p.stderr)
    return p.stdout
Path('work/lab5-webhook-secret').write_text(secrets.token_hex(32))
try:
    run('build','lab5-webhook-proxy','lab5-webhook-receiver')
    run('up','-d','--no-deps','lab5-webhook-proxy','lab5-webhook-receiver')
    run('cp','./loadtest/lab5-webhook-fixture.php','app1:/tmp/lab5-wh.php');run('cp','./work/lab5-webhook-secret','app1:/tmp/lab5-webhook-secret')
    for _ in range(20):
        try:
            metrics=json.loads(run('exec','-T','lab5-webhook-receiver','curl','--noproxy','*','-sS','http://127.0.0.1/metrics'));break
        except Exception:time.sleep(1)
    else:raise RuntimeError('Receiver not ready')
    fixtures=json.loads(run('exec','-T','app1','php','/tmp/lab5-wh.php','prepare'));(out/'fixtures.json').write_text(json.dumps(fixtures,indent=2))
    results={}
    for path,f in fixtures.items():
        run('exec','-T','app1','php','artisan','events:webhooks','--once','--stream='+f['stream'])
        attempts=[]
        for _ in range(5):
            row=json.loads(run('exec','-T','app1','php','/tmp/lab5-wh.php','tick',f['event_id']));attempts.append(row)
            if row['status'] in ['delivered','dead']:break
        expected='dead' if path=='always-fail' else 'delivered';assert attempts[-1]['status']==expected,attempts
        results[path]=attempts;print(json.dumps({'case':path,'attempts':len(attempts),'final':expected}),flush=True)
    # Simulate replay after delivery succeeded: receiver must not repeat its committed effect.
    f=fixtures['ok'];run('exec','-T','app1','php','/tmp/lab5-wh.php','reset-delivered',f['event_id'])
    repeated=json.loads(run('exec','-T','app1','php','/tmp/lab5-wh.php','tick',f['event_id']));assert repeated['status']=='delivered'
    metrics=json.loads(run('exec','-T','lab5-webhook-receiver','curl','--noproxy','*','-sS','http://127.0.0.1/metrics'))
    assert len(metrics['effects'])==3 and all(v==1 for v in metrics['effects'].values()),metrics
    assert metrics['attempts'][fixtures['lost-ack']['event_id']]==2,metrics
    security=json.loads(run('exec','-T','app1','php','/tmp/lab5-wh.php','security'));assert all(v=='rejected'for v in security.values()),security
    unsigned=run('exec','-T','lab5-webhook-receiver','curl','--noproxy','*','-sS','-o','/dev/null','-w','%{http_code}','-X','POST','http://127.0.0.1/ok');assert unsigned=='401',unsigned
    proxylog=run('logs','--no-color','lab5-webhook-proxy')
    access=run('exec','-T','lab5-webhook-proxy','cat','/var/log/squid/access.log');assert 'lab5-webhook-receiver' in access,access
    (out/'proxy-access.log').write_text(access)
    (out/'results.json').write_text(json.dumps({'deliveries':results,'receiver':metrics,'security':security,'unsigned_status':unsigned,'method':'Real HTTP through separate lab Squid with exact receiver-only exception. Live Squid ACL unchanged. Backoff due times advanced per fixture to avoid waiting; computed exponential+jitter delays saved. Lost-ack case commits receiver effect then closes connection; duplicate request has one SQLite effect. Receiver state is disposable test storage, not production HA proof.'},indent=2))
    print('PASS: HMAC verified, retries succeeded, five failures became dead-letter, lost ACK and replay had one receiver effect.',flush=True)
finally:
    try:run('rm','-s','-f','-v','lab5-webhook-proxy','lab5-webhook-receiver')
    except Exception as e:print('Test cleanup needs attention:',e)
    print('Results:',out,flush=True)

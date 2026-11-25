import datetime,json,subprocess,time,uuid
from pathlib import Path

FILES=['docker-compose.yml','docker-compose.lab1.yml','docker-compose.lab2.yml','docker-compose.lab2-network.yml','docker-compose.lab2-http.yml','docker-compose.lab3.yml','docker-compose.lab3-fixed.yml','docker-compose.lab3-egress.yml','docker-compose.lab3-isolated.yml','docker-compose.lab4-origin.yml','docker-compose.lab4-edges.yml','docker-compose.lab4-signed.yml','docker-compose.lab5.yml','docker-compose.lab5-analytics.yml','docker-compose.lab5-active.yml','docker-compose.lab5-milestones.yml','docker-compose.lab5-webhooks.yml','docker-compose.lab6.yml','docker-compose.lab6-reverb.yml']
BASE=['docker','compose']+[v for f in FILES for v in ('-f',f)]
FLAGS=getattr(subprocess,'CREATE_NO_WINDOW',0)
out=Path('work')/('lab7-restarts-'+datetime.datetime.now().strftime('%Y%m%d-%H%M%S'));out.mkdir(parents=True)
run_id=uuid.uuid4().hex[:12];actors=[];checkpoints=[]
def execute(command,timeout=90,check=True):
    p=subprocess.run(command,capture_output=True,text=True,encoding='utf-8',errors='replace',timeout=timeout,creationflags=FLAGS)
    if check and p.returncode:raise RuntimeError(p.stdout+p.stderr)
    return p
def compose(*args,timeout=90):return execute(BASE+list(args),timeout).stdout
def data(text):
    for line in reversed(text.splitlines()):
        try:return json.loads(line.lstrip('\ufeff'))
        except ValueError:pass
    raise RuntimeError('No JSON result: '+text[-600:])
def save(name,value):(out/name).write_text(json.dumps(value,indent=2),encoding='utf-8')

BASE+=['-f','docker-compose.lab5-webhook-test.yml']

def wait_for(test,message,seconds=90):
    end=time.monotonic()+seconds
    while time.monotonic()<end:
        value=test()
        if value:return value
        time.sleep(.25)
    raise RuntimeError(message)

def fixture(action,link=None,event=None):
    args=['exec','-T','-e','LAB7_FAILURE_DRILLS=1','-e','APP_CONFIG_CACHE=/tmp/lab7-restart-cli.php','-e','DB_READ_HOST=db-primary','-e','DB_WRITE_HOST=db-primary','app1','php','/tmp/lab7-restart-fixture.php',action]
    if link is not None:args.append(str(link))
    if event is not None:args.append(event)
    return data(compose(*args))

def status(name):
    try:return json.loads((out/name).read_text(encoding='utf-8-sig'))
    except (FileNotFoundError,json.JSONDecodeError):return None

def fresh_monitor():
    row=status('dashboard-status.json')
    return row if row and time.time()*1000-row['at']<3000 else None

demo_path=Path('work/lab6-demo-private.json')
demo=json.loads(demo_path.read_text(encoding='utf-8-sig'))
secret_path=Path('work/lab5-webhook-secret')
if not secret_path.exists():secret_path.write_text(uuid.uuid4().hex+uuid.uuid4().hex,encoding='utf-8')
compose('config','--quiet')
# Existing Lab5 test receiver and restricted test proxy only; public egress policy unchanged.
compose('up','-d','--no-build','--no-deps','lab5-webhook-proxy','lab5-webhook-receiver')
compose('cp','./loadtest/lab7-restart-fixture.php','app1:/tmp/lab7-restart-fixture.php')
compose('cp',str(demo_path),'app1:/tmp/lab7-demo-private.json')
compose('cp',str(secret_path),'app1:/tmp/lab7-webhook-secret')
live=fixture('prepare');save('live-fixture.json',live)
save('recovery-private.json',{'email':demo['email'],'password':demo['password'],'link_id':live['link_id'],'short_code':live['short_code']})
app_id=compose('ps','-q','app1').strip()
nets=json.loads(execute(['docker','inspect','--format','{{json .NetworkSettings.Networks}}',app_id]).stdout)
network=next(n for n in nets if n.endswith('_default'))
processes=[];handles=[];stopped=set();phases=[];result={}
def start_node(script,env):
    handle=(out/(Path(script).stem+'.log')).open('w',encoding='utf-8');handles.append(handle)
    command=['docker','run','--rm','--network',network,'--mount','type=bind,source='+str(Path.cwd())+',target=/workspace','-e',env+'=/workspace/'+out.as_posix(),'--entrypoint','node','node:22-alpine','/workspace/loadtest/'+script]
    p=subprocess.Popen(command,stdout=handle,stderr=subprocess.STDOUT,creationflags=FLAGS);processes.append(p);return p
def alive():
    for p in processes:
        if p.poll() is not None:raise RuntimeError('A monitor or producer stopped early; inspect saved logs')
    s=status('load-status.json')
    if not s or time.time()*1000-s['at']>4000:raise RuntimeError('Steady click generator stalled')
try:
    monitor=start_node('lab7-restart-monitor.cjs','LAB6_RESULT_DIR')
    wait_for(lambda:status('dashboard-ready.json'),'Dashboard did not connect; inspect monitor log',65)
    producer=start_node('lab7-restart-load.cjs','LAB7_RESULT_DIR')
    wait_for(lambda:status('load-status.json'),'HTTP generator failed',25)
    print('Steady 200/s offered load and real Echo/dashboard monitor running.',flush=True)
    for service in ['app1','reverb-2','analytics-1','event-outbox-relay','redis-streams']:
        alive();before=status('load-status.json');view_before=fresh_monitor();started=time.time()
        print('Restart drill:',service,flush=True)
        compose('stop','-t','2',service);stopped.add(service)
        # app1 executes this helper, so create its notification immediately after start.
        notice=None
        if service!='app1':notice=fixture('notice',live['control_id'])
        time.sleep(3)
        queued=fixture('outbox',live['control_id'],notice['event_id']) if notice else None
        compose('start',service);stopped.discard(service)
        if service=='app1':
            wait_for(lambda:execute(BASE+['exec','-T','app1','php','-r','echo 1;'],check=False).returncode==0,'App did not restart',35)
            # Entry point must finish cache warmup before executing application CLI.
            time.sleep(2);notice=fixture('notice',live['control_id'])
        restored_at=time.time()
        def published():
            value=fixture('outbox',live['control_id'],notice['event_id'])
            return value if value['published'] else None
        wait_for(published,'Outbox did not recover for '+service,90)
        delivered=fixture('deliver',live['control_id'],notice['event_id'])
        if delivered['final_status']!='delivered':raise RuntimeError('Diagnostic webhook failed: '+json.dumps(delivered))
        # Let the UI process reconnect/catch-up, without requiring every intermediate count.
        time.sleep(3);alive();after=status('load-status.json');view_after=fresh_monitor()
        if view_after is None:raise RuntimeError('Dashboard monitor stopped reporting')
        phase={'service':service,'started_at_ms':round(started*1000),'service_started_at_ms':round(restored_at*1000),
            'finished_at_ms':round(time.time()*1000),'outbox_during_stop':queued,'notice_event_id':notice['event_id'],
            'notice_to_delivery_seconds':round(time.time()-notice['committed_at'],3),
            'delivery':delivered,'load_before':before,'load_after':after,'dashboard_before':view_before,'dashboard_after':view_after}
        phases.append(phase);save('phases.json',phases)
        print(service+': recovered; diagnostic webhook delivered.',flush=True)
    (out/'stop-load').touch();producer.wait(timeout=20)
    if producer.returncode:raise RuntimeError('HTTP generator failed')
    load=status('live-load.json');assert load and not load['missing_trace'],'Scoped append diagnostic header missing'
    # Consumers must process every confirmed append; total raw rows also includes ambiguous HTTP outcomes.
    def drained():
        state=fixture('state',live['link_id'])
        return state if state['count']==state['raw']==state['ledger']==state['dashboard_rows'] and state['count']>=load['append_confirmed'] else None
    final=wait_for(drained,'Consumers did not converge after restarts',120)
    wait_for(lambda:(fresh_monitor() or {}).get('count')==final['count'],'Dashboard did not recover final count',65)
    (out/'stop-monitor').touch();monitor.wait(timeout=15)
    if monitor.returncode:raise RuntimeError('Dashboard monitor failed')
    monitor_result=status('dashboard-monitor.json')
    # Check actual receiver effects per stable UUID, without exposing the secret.
    receiver=data(compose('exec','-T','lab5-webhook-receiver','python3','-c','import urllib.request;print(urllib.request.urlopen("http://127.0.0.1/metrics").read().decode())'))
    observed={p['notice_event_id']:{'effects':receiver['effects'].get(p['notice_event_id'],0),'attempts':receiver['attempts'].get(p['notice_event_id'],0)} for p in phases}
    assert all(v['effects']==1 for v in observed.values()),observed
    rows=status('click-observations.json')
    for phase in phases:
        sample=[r for r in rows if phase['started_at_ms']<=r['started_at']<phase['finished_at_ms']]
        phase['http_attempts']=len(sample);phase['http_302']=sum(r['status']==302 for r in sample)
        phase['http_errors']=len(sample)-phase['http_302'];phase['unconfirmed_302']=sum(r['status']==302 and r['append_confirmed']=='no' for r in sample)
        latencies=sorted(r['finished_at']-r['started_at'] for r in sample)
        phase['observed_http_p95_ms']=latencies[min(len(latencies)-1,int(len(latencies)*.95))] if latencies else None
    result={'passed':True,'phases':phases,'steady_load':load,'final_projection':final,'dashboard':monitor_result,
        'webhook_receiver':observed,'successful_redirects_without_recorded_click_lower_bound':max(0,load['success']-final['count']),
        'recorded_clicks_without_confirmed_http302_lower_bound':max(0,final['count']-load['success']),
        'method':'Sequential graceful stop, 3-second hold, start of one app, connected Reverb node, one analytics consumer, outbox relay and stream Redis. Existing Redis AOF volume retained. Steady offered target 200 HTTP clicks/s. Actual frontend Echo/dashboard code under real-network minimal DOM harness.',
        'limitations':'This is graceful restart, not power-loss/AOF hard-kill durability. Append unconfirmed can include accepted-but-response-lost writes. Failed HTTP requests may still create events; counter-vs-HTTP differences are bounds, not exact UUID loss attribution. One viewer, one host. Diagnostic milestone notices are synthetic committed outbox records, delivered explicitly by real WebhookDeliveryWorker through the isolated Lab5 proxy/receiver; this does not retest automatic threshold crossing or consumer subscription scheduling.'}
    save('results.json',result)
    diagnostic=execute(BASE+['logs','--since','10m','--tail','150','analytics-1','analytics-2','dashboard-consumer','webhook-consumer','event-outbox-relay','reverb-1','reverb-2'],check=False)
    (out/'recovery-diagnostics.log').write_text(diagnostic.stdout+diagnostic.stderr,encoding='utf-8')
    import csv
    with(out/'restart-summary.csv').open('w',newline='',encoding='utf-8-sig')as f:
        fields=['service','http_attempts','http_302','http_errors','unconfirmed_302','observed_http_p95_ms','notice_to_delivery_seconds']
        writer=csv.DictWriter(f,fieldnames=fields,extrasaction='ignore');writer.writeheader();writer.writerows(phases)
    print('PASS: consumers, dashboard and durable notification intent recovered.',flush=True)
    print('HTTP results:',json.dumps(load),flush=True);print('Final recorded count:',final['count'],flush=True)
    print('Results:',out,flush=True)
finally:
    (out/'stop-load').touch();(out/'stop-monitor').touch()
    for service in list(stopped):execute(BASE+['start',service],check=False)
    for p in processes:
        try:p.wait(timeout=20)
        except subprocess.TimeoutExpired:p.kill();p.wait(timeout=10)
    for h in handles:h.close()
    # Keep diagnostic receiver state until evidence is copied; no volumes or data deleted.

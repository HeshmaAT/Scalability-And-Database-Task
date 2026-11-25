import datetime,json,os,subprocess,time,uuid
from pathlib import Path

FILES=['docker-compose.yml','docker-compose.lab1.yml','docker-compose.lab2.yml','docker-compose.lab2-network.yml','docker-compose.lab2-http.yml','docker-compose.lab3.yml','docker-compose.lab3-fixed.yml','docker-compose.lab3-egress.yml','docker-compose.lab3-isolated.yml','docker-compose.lab4-origin.yml','docker-compose.lab4-edges.yml','docker-compose.lab4-signed.yml','docker-compose.lab5.yml','docker-compose.lab5-analytics.yml','docker-compose.lab5-active.yml','docker-compose.lab5-milestones.yml','docker-compose.lab5-webhooks.yml','docker-compose.lab6.yml','docker-compose.lab6-reverb.yml']
BASE=['docker','compose']+[part for name in FILES for part in ('-f',name)]
out=Path('work')/('lab7-crash-poison-'+datetime.datetime.now().strftime('%Y%m%d-%H%M%S'));out.mkdir(parents=True)
CREATE_NO_WINDOW=getattr(subprocess,'CREATE_NO_WINDOW',0)
def execute(command,timeout=60,check=True):
    result=subprocess.run(command,capture_output=True,text=True,encoding='utf-8',errors='replace',timeout=timeout,creationflags=CREATE_NO_WINDOW)
    if check and result.returncode:raise RuntimeError(result.stdout+result.stderr)
    return result
def compose(*args,timeout=60):return execute(BASE+list(args),timeout).stdout
def php(*args):return compose('exec','-T','app1','php','/tmp/lab7-fixture.php',*map(str,args))
def data(text):
    for line in reversed(text.splitlines()):
        try:return json.loads(line.lstrip('\ufeff'))
        except ValueError:pass
    raise RuntimeError('No JSON result: '+text[-500:])
def save(name,value):(out/name).write_text(json.dumps(value,indent=2),encoding='utf-8')
def state(fixture):return data(php('state',fixture['link_id'],fixture['stream'],fixture.get('poison_source_id','')))
def wait_for(test,message,seconds=45):
    deadline=time.monotonic()+seconds
    while time.monotonic()<deadline:
        value=test()
        if value:return value
        time.sleep(.2)
    raise RuntimeError(message)
def load_alive():
    if producer.poll() is not None:raise RuntimeError('Steady click load stopped before drills completed')
    value=json.loads((out/'load-status.json').read_text(encoding='utf-8-sig'))
    if time.time()*1000-value['at']>2500:raise RuntimeError('Steady click producer status is stale')
def consumer(fixture,name,*,fault='',count=100,container=None,idle=60000):
    args=BASE+['run','--rm','-T','--no-deps']
    if container:args+=['--name',container]
    args+=['-e','LAB7_FAILURE_DRILLS=1','analytics-1','php','artisan','events:consume','analytics-writer','--once','--count='+str(count),'--consumer='+name,'--stream='+fixture['stream'],'--claim-idle-ms='+str(idle)]
    if fault:args+=['--fault='+fault]
    return args

compose('cp','./loadtest/lab7-fixture.php','app1:/tmp/lab7-fixture.php')
live=data(php('create','live',1));save('live-fixture.json',live)
app_id=compose('ps','-q','app1').strip()
networks=json.loads(execute(['docker','inspect','--format','{{json .NetworkSettings.Networks}}',app_id]).stdout)
network=next((name for name in networks if name.endswith('_default')),None)
if not network:raise RuntimeError('Default application network missing')
producer_log=(out/'live-load.log').open('w',encoding='utf-8')
producer=subprocess.Popen(['docker','run','--rm','--network',network,'--mount','type=bind,source='+str(Path.cwd())+',target=/workspace','-e','LAB7_RESULT_DIR=/workspace/'+out.as_posix(),'--entrypoint','node','node:22-alpine','/workspace/loadtest/lab7-steady-clicks.cjs'],stdout=producer_log,stderr=subprocess.STDOUT,creationflags=CREATE_NO_WINDOW)
active_containers=[];crash_results=[];poison_result=None
try:
    wait_for(lambda:(out/'load-status.json').exists(),'Steady click load did not start',20)
    print('Steady real HTTP load is running at target 200 clicks/s.',flush=True)
    for phase in ('after-read','after-commit'):
        load_alive();fixture=data(php('create',phase,100));save(phase+'-fixture.json',fixture)
        container='lab7-fault-'+uuid.uuid4().hex[:12];active_containers.append(container)
        with (out/(phase+'-consumer.log')).open('w',encoding='utf-8') as log:
            process=subprocess.Popen(consumer(fixture,'victim-'+phase,fault=phase,container=container),stdout=log,stderr=subprocess.STDOUT,creationflags=CREATE_NO_WINDOW)
            try:
                def reached():
                    if process.poll() is not None:raise RuntimeError('Fault consumer exited before pause point; inspect '+phase+'-consumer.log')
                    return ('"lab7_fault_reached":"'+phase+'"') in (out/(phase+'-consumer.log')).read_text(encoding='utf-8')
                wait_for(reached,'Consumer did not reach '+phase,25)
                before=state(fixture);assert before['pending']==100,before
                expected=0 if phase=='after-read' else 100
                assert before['count']==before['raw']==expected,before
                load_alive();execute(['docker','kill','--signal=KILL',container]);process.wait(timeout=15)
                killed=state(fixture);assert killed['pending']==100,killed
                time.sleep(2.2)
                rescue=execute(consumer(fixture,'rescuer-'+phase,idle=2000));(out/(phase+'-rescuer.log')).write_text(rescue.stdout+rescue.stderr,encoding='utf-8')
                after=state(fixture);assert after['count']==after['raw']==100 and after['pending']==0,after
                assert any(row['consumer_name']=='rescuer-'+phase for row in after['consumers']) if phase=='after-read' else sum(row['total'] for row in after['consumers'])==100
                result={'fault_point':phase,'sent':100,'before_kill':before,'after_sigkill':killed,'after_reclaim':after,'reclaim_idle_ms':2000,'production_reclaim_idle_ms':60000,'steady_load_active':True,'method':'SIGKILL of an isolated analytics consumer container, real XPENDING evidence and XAUTOCLAIM recovery; same MariaDB projection logic as live consumers.'}
                crash_results.append(result);save(phase+'-result.json',result)
                print('PASS '+phase+': SIGKILL left 100 pending; another consumer reclaimed them; final raw rows and count = 100.',flush=True)
            finally:
                if process.poll() is None:
                    execute(['docker','rm','-f',container],check=False);process.wait(timeout=15)
    load_alive();fixture=data(php('create','poison',21));save('poison-fixture.json',fixture)
    first=execute(consumer(fixture,'poison-owner',count=1));(out/'poison-attempt1.log').write_text(first.stdout+first.stderr,encoding='utf-8')
    pending=state(fixture);assert pending['pending']==1 and pending['count']==0 and pending['retry']['attempts']==1,pending
    flowing=execute(consumer(fixture,'healthy-worker',count=100));(out/'poison-healthy.log').write_text(flowing.stdout+flowing.stderr,encoding='utf-8')
    flow_state=state(fixture);assert flow_state['count']==20 and flow_state['raw']==20 and flow_state['pending']==1,flow_state
    for attempt in (2,3):
        time.sleep(.45);load_alive();reply=execute(consumer(fixture,'poison-owner',count=100));(out/('poison-attempt'+str(attempt)+'.log')).write_text(reply.stdout+reply.stderr,encoding='utf-8')
    dead=state(fixture);assert dead['count']==20 and dead['pending']==0,dead
    listed=data(compose('exec','-T','app1','php','artisan','events:dead:list','--limit=1000','--source='+fixture['stream']))
    matching=[row for row in listed if row['source_id']==fixture['poison_source_id']]
    assert len(matching)==1 and matching[0]['attempt_count']==3,matching
    corrected=out/'poison-corrected.json';corrected.write_text(json.dumps(fixture['corrected']),encoding='utf-8')
    compose('cp',str(corrected),'app1:/tmp/lab7-poison-corrected.json')
    replay=data(compose('exec','-T','app1','php','artisan','events:dead:replay',matching[0]['id'],'--payload-file=/tmp/lab7-poison-corrected.json'))
    assert replay['event_id']==fixture['poison_event_id'],replay
    execute(consumer(fixture,'replay-worker',count=100));after=state(fixture);assert after['count']==after['raw']==21 and after['pending']==0,after
    compose('exec','-T','app1','php','artisan','events:dead:replay',matching[0]['id'],'--payload-file=/tmp/lab7-poison-corrected.json')
    execute(consumer(fixture,'replay-worker',count=100));again=state(fixture);assert again['count']==again['raw']==21,again
    poison_result={'sent':21,'after_first_failure':pending,'healthy_events_while_poison_pending':flow_state,'dead_letter':matching[0],'after_corrected_replay':after,'after_duplicate_replay':again,'steady_load_active':True}
    save('poison-result.json',poison_result)
    print('PASS poison: three attempts -> dead-letter with metadata; 20 healthy events flowed; corrected replay counted once.',flush=True)
finally:
    (out/'stop-load').touch()
    try:producer.wait(timeout=20)
    except subprocess.TimeoutExpired:producer.kill();producer.wait(timeout=10)
    producer_log.close()
    for container in active_containers:execute(['docker','rm','-f',container],check=False)
if producer.returncode:raise RuntimeError('Live click producer failed; inspect live-load.log')
load=json.loads((out/'live-load.json').read_text(encoding='utf-8'))
assert not load['errors'] and not load['skipped'] and 190<=load['attempt_rps']<=210,load
def live_caught_up():
    value=state(live)
    return value if value['count']==value['raw']==load['success'] else None
final_live=wait_for(live_caught_up,'Live click count did not catch up',60)
result={'consumer_crashes':crash_results,'poison':poison_result,'steady_http_load':load,'final_live_count':final_live,'passed':True,'scope':'Required Lab 7 consumer crash and poison drills only. Lifecycle outbox, projection rebuild, lifecycle event sourcing, and restart-everything drills are next.'}
save('results.json',result)
print('Live load:',json.dumps(load),flush=True)
print('PASS live link: final raw rows and click counter equal successful HTTP redirects.',flush=True)
print('Results:',out,flush=True)

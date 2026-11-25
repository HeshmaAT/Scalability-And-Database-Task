import datetime,json,subprocess,time,uuid
from pathlib import Path

FILES=['docker-compose.yml','docker-compose.lab1.yml','docker-compose.lab2.yml','docker-compose.lab2-network.yml','docker-compose.lab2-http.yml','docker-compose.lab3.yml','docker-compose.lab3-fixed.yml','docker-compose.lab3-egress.yml','docker-compose.lab3-isolated.yml','docker-compose.lab4-origin.yml','docker-compose.lab4-edges.yml','docker-compose.lab4-signed.yml','docker-compose.lab5.yml','docker-compose.lab5-analytics.yml','docker-compose.lab5-active.yml','docker-compose.lab5-milestones.yml','docker-compose.lab5-webhooks.yml','docker-compose.lab6.yml','docker-compose.lab6-reverb.yml']
BASE=['docker','compose']+[v for f in FILES for v in ('-f',f)]
FLAGS=getattr(subprocess,'CREATE_NO_WINDOW',0)
out=Path('work')/('lab7-rebuild-'+datetime.datetime.now().strftime('%Y%m%d-%H%M%S'));out.mkdir(parents=True)
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
def key(suffix):return 'lab5:test:lab7:lifecycle:'+run_id+':'+suffix
def probe(action,stream,**options):
    args=['exec','-T','-e','LAB7_FAILURE_DRILLS=1','app1','php','artisan','lab7:lifecycle-probe',action,'--stream='+stream,'--run='+run_id]
    args+=['--'+name.replace('_','-')+'='+str(value) for name,value in options.items()]
    return data(compose(*args,timeout=300 if action=='seed' else 90))
def wait_for(test,message,seconds=90):
    until=time.monotonic()+seconds
    while time.monotonic()<until:
        value=test()
        if value:return value
        time.sleep(.2)
    raise RuntimeError(message)
def alive(label):
    if load_process.poll() is not None:raise RuntimeError('Steady load ended during '+label)
    status=json.loads((out/'load-status.json').read_text(encoding='utf-8-sig'))
    if time.time()*1000-status['at']>3000:raise RuntimeError('Steady producer stalled during '+label)
    checkpoints.append({'drill':label,'at':datetime.datetime.now(datetime.timezone.utc).isoformat(),'click_attempts_so_far':status['attempts']})
def start_actor(name,command):
    container='lab7-lc-'+name+'-'+uuid.uuid4().hex[:8]
    logpath=out/(name+'.log');handle=logpath.open('w',encoding='utf-8')
    p=subprocess.Popen(BASE+['run','--rm','-T','--no-deps','--name',container,'-e','LAB7_FAILURE_DRILLS=1','analytics-1']+command,stdout=handle,stderr=subprocess.STDOUT,creationflags=FLAGS)
    actor={'container':container,'process':p,'handle':handle,'log':logpath};actors.append(actor);return actor
def stop_actor(actor,hard=False):
    if actor['process'].poll() is None:
        if hard:execute(['docker','kill','--signal=KILL',actor['container']])
        else:execute(['docker','stop','-t','1',actor['container']],check=False)
        try:actor['process'].wait(timeout=15)
        except subprocess.TimeoutExpired:execute(['docker','rm','-f',actor['container']],check=False);actor['process'].wait(timeout=10)
    actor['handle'].close()
def crash_probe(action,stream,marker):
    actor=start_actor(action,['php','artisan','lab7:lifecycle-probe',action,'--stream='+stream,'--run='+run_id])
    def reached():
        if actor['process'].poll() is not None:raise RuntimeError(action+' exited before fault point; inspect '+str(actor['log']))
        for line in actor['log'].read_text(encoding='utf-8').splitlines():
            try:
                value=json.loads(line)
                if value.get('lab7_fault_reached')==marker:return value
            except ValueError:pass
        return None
    value=wait_for(reached,'Fault marker missing for '+action,45);alive(action+'-sigkill');stop_actor(actor,hard=True);return value
def relay_pair(stream,label):
    barrier=stream+':barrier:'+uuid.uuid4().hex[:8]
    pair=[start_actor(label+'-'+str(i),['php','artisan','events:relay','--stream='+stream,'--claim-delay-ms=100','--barrier='+barrier]) for i in (1,2)]
    try:
        def drained():
            for actor in pair:
                if actor['process'].poll() is not None:raise RuntimeError('Relay exited early: '+str(actor['log']))
            state=probe('summary',stream)
            return state if state['unpublished']==0 else None
        result=wait_for(drained,'Two relays did not drain outbox',120)
    finally:
        for actor in pair:stop_actor(actor)
    publications=[]
    for actor in pair:
        total=0
        for line in actor['log'].read_text(encoding='utf-8').splitlines():
            try:total+=json.loads(line).get('outbox_published',0)
            except ValueError:pass
        publications.append(total)
    result['published_by_each_relay']=publications;return result


compose('cp','./loadtest/lab7-fixture.php','app1:/tmp/lab7-fixture.php')
live=data(compose('exec','-T','app1','php','/tmp/lab7-fixture.php','create','live','1'));save('live-fixture.json',live)
app_id=compose('ps','-q','app1').strip()
nets=json.loads(execute(['docker','inspect','--format','{{json .NetworkSettings.Networks}}',app_id]).stdout)
network=next(n for n in nets if n.endswith('_default'))
log=(out/'live-load.log').open('w',encoding='utf-8')
load_process=subprocess.Popen(['docker','run','--rm','--network',network,'--mount','type=bind,source='+str(Path.cwd())+',target=/workspace','-e','LAB7_RESULT_DIR=/workspace/'+out.as_posix(),'--entrypoint','node','node:22-alpine','/workspace/loadtest/lab7-long-steady-clicks.cjs'],stdout=log,stderr=subprocess.STDOUT,creationflags=FLAGS)
try:
    wait_for(lambda:(out/'load-status.json').exists(),'Steady load failed to start',25)
    alive('before-rebuild')
    print('Steady HTTP target 200/s; rebuilding only a separate isolated counter.',flush=True)
    output=compose('exec','-T','-e','LAB7_FAILURE_DRILLS=1','app1','php','artisan','lab7:click-rebuild',timeout=120)
    (out/'command.log').write_text(output,encoding='utf-8')
    result=data(output);assert result['passed'];save('counter-rebuild.json',result)
    alive('after-trim-and-fallback')
finally:
    (out/'stop-load').touch()
    try:load_process.wait(timeout=20)
    except subprocess.TimeoutExpired:load_process.kill();load_process.wait(timeout=10)
    log.close()
if load_process.returncode:raise RuntimeError('Producer failed; inspect live-load.log')
load=json.loads((out/'live-load.json').read_text(encoding='utf-8'))
assert not load['errors'] and not load['skipped'],load
def caught_up():
    value=data(compose('exec','-T','app1','php','/tmp/lab7-fixture.php','state',str(live['link_id']),live['stream']))
    return value if value['count']==value['raw']==load['success'] else None
final=wait_for(caught_up,'Live counters did not catch up',120)
save('results.json',{'passed':True,'rebuild':result,'steady_load':load,'live_counter':final,'load_checkpoints':checkpoints})
print('PASS: 1000 clicks rebuilt without duplicate raw rows; duplicate UUID counted once.',flush=True)
print('PASS: trimmed replay refused; existing SQL fallback restored all 1000.',flush=True)
print('Replay seconds:',result['rebuild_seconds'],'Fallback seconds:',result['fallback_seconds'],flush=True)
print('Live load:',json.dumps(load),flush=True)
print('Results:',out,flush=True)

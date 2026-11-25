import datetime,json,subprocess,time,uuid
from pathlib import Path

FILES=['docker-compose.yml','docker-compose.lab1.yml','docker-compose.lab2.yml','docker-compose.lab2-network.yml','docker-compose.lab2-http.yml','docker-compose.lab3.yml','docker-compose.lab3-fixed.yml','docker-compose.lab3-egress.yml','docker-compose.lab3-isolated.yml','docker-compose.lab4-origin.yml','docker-compose.lab4-edges.yml','docker-compose.lab4-signed.yml','docker-compose.lab5.yml','docker-compose.lab5-analytics.yml','docker-compose.lab5-active.yml','docker-compose.lab5-milestones.yml','docker-compose.lab5-webhooks.yml','docker-compose.lab6.yml','docker-compose.lab6-reverb.yml']
BASE=['docker','compose']+[v for f in FILES for v in ('-f',f)]
FLAGS=getattr(subprocess,'CREATE_NO_WINDOW',0)
out=Path('work')/('lab7-lifecycle-'+datetime.datetime.now().strftime('%Y%m%d-%H%M%S'));out.mkdir(parents=True)
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
app_id=compose('ps','-q','app1').strip();nets=json.loads(execute(['docker','inspect','--format','{{json .NetworkSettings.Networks}}',app_id]).stdout)
network=next((n for n in nets if n.endswith('_default')),None)
if not network:raise RuntimeError('Application network missing')
load_log=(out/'live-load.log').open('w',encoding='utf-8')
load_process=subprocess.Popen(['docker','run','--rm','--network',network,'--mount','type=bind,source='+str(Path.cwd())+',target=/workspace','-e','LAB7_RESULT_DIR=/workspace/'+out.as_posix(),'--entrypoint','node','node:22-alpine','/workspace/loadtest/lab7-long-steady-clicks.cjs'],stdout=load_log,stderr=subprocess.STDOUT,creationflags=FLAGS)
result={}
try:
    wait_for(lambda:(out/'load-status.json').exists(),'Click load failed to start',20)
    print('Steady real HTTP click load running: target 200/s.',flush=True)
    lost=crash_probe('naive-lost',key('lost'),'naive-commit-before-publish')
    lost_state=probe('inspect',key('lost'),link=lost['link_id']);lost_stream=probe('summary',key('lost'))
    assert lost_state['version']==0 and not lost_state['events'] and lost_stream['stream_entries']==0 and lost_stream['outbox_rows']==0
    result['naive_event_lost']={'database_state':lost_state,'stream':lost_stream};save('naive-lost.json',result['naive_event_lost'])
    print('PROVED naive dual-write loss: committed link survived SIGKILL, no event was published.',flush=True)
    alive('phantom-rollback');phantom=probe('naive-phantom',key('phantom'));phantom_stream=probe('summary',key('phantom'))
    assert phantom['row_exists'] is False and phantom_stream['phantom_events']==1 and phantom_stream['stream_entries']==1
    result['naive_phantom']={'database_state':phantom,'stream':phantom_stream};save('naive-phantom.json',result['naive_phantom'])
    print('PROVED naive phantom: published lifecycle event survived database rollback.',flush=True)
    alive('atomic-rollback');rollback=probe('fixed-rollback',key('rollback'));empty=probe('summary',key('rollback'))
    assert not rollback['row_exists'] and rollback['event_rows']==rollback['outbox_rows']==empty['stream_entries']==0
    result['atomic_rollback']=rollback;print('PASS fixed rollback: state, lifecycle log and outbox all rolled back; stream stayed empty.',flush=True)
    fixed=crash_probe('fixed-commit',key('fixed'),'fixed-commit-before-relay')
    saved=probe('inspect',key('fixed'),link=fixed['link_id']);assert saved['version']==1 and len(saved['events'])==1 and saved['unpublished']==1
    actor=start_actor('relay-crash',['php','artisan','events:relay','--once','--stream='+key('fixed'),'--fault=after-publish'])
    def relay_fault():
        if actor['process'].poll() is not None:raise RuntimeError('Relay crashed before marker; inspect relay-crash.log')
        return '"lab7_fault_reached":"after-publish"' in actor['log'].read_text(encoding='utf-8')
    wait_for(relay_fault,'Relay did not reach publish-before-mark fault',30);alive('relay-after-publish-sigkill');stop_actor(actor,hard=True)
    ambiguous=probe('summary',key('fixed'));assert ambiguous['stream_entries']==1 and ambiguous['unpublished']==1
    recovered=relay_pair(key('fixed'),'fixed-relay');assert recovered['missing_events']==recovered['phantom_events']==recovered['payload_mismatches']==recovered['unpublished']==0 and recovered['unique_events']==1 and recovered['duplicate_deliveries']>=1
    result['atomic_commit_and_relay_crash']={'committed_state':saved,'published_before_relay_mark':ambiguous,'after_recovery':recovered}
    save('outbox-recovery.json',result['atomic_commit_and_relay_crash'])
    print('PASS committed outbox survived SIGKILL; relay crash caused duplicate delivery with the same UUID, no loss or phantom.',flush=True)
    alive('optimistic-concurrency');fixture=probe('create',key('concurrency'))
    started=int(time.time()*1000)+5000
    commands=[]
    for active in (0,1):
        command=BASE+['exec','-T','-e','LAB7_FAILURE_DRILLS=1','app1','php','artisan','lab7:lifecycle-probe','mutate','--stream='+key('concurrency'),'--run='+run_id,'--link='+str(fixture['link_id']),'--expected-version='+str(fixture['version']),'--active='+str(active),'--start-at='+str(started)]
        commands.append(subprocess.Popen(command,stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True,encoding='utf-8',errors='replace',creationflags=FLAGS))
    outcomes=[]
    for process in commands:
        stdout,stderr=process.communicate(timeout=30)
        if process.returncode:raise RuntimeError(stdout+stderr)
        outcomes.append(data(stdout))
    assert sorted(row['status'] for row in outcomes)==['rejected','won'],outcomes
    final=probe('inspect',key('concurrency'),link=fixture['link_id']);assert final['version']==2 and len(final['events'])==2
    cas_relay=relay_pair(key('concurrency'),'cas-relay');assert cas_relay['unpublished']==cas_relay['missing_events']==cas_relay['phantom_events']==cas_relay['payload_mismatches']==0
    result['optimistic_concurrency']={'same_expected_version':fixture['version'],'outcomes':outcomes,'final':final,'relay':cas_relay};save('concurrency.json',result['optimistic_concurrency'])
    print('PASS concurrent pause/resume: one winner, one 409 rejection against the same version.',flush=True)
    alive('seed-1000-links');print('Generating 1000 isolated link histories; this is the longest setup step.',flush=True);seed=probe('seed',key('seed'),count=1000);save('seed.json',seed)
    alive('rebuild-1000-links');rebuilt=probe('rebuild',key('seed'));assert rebuilt['links']==1000 and rebuilt['all_states_match'] is True
    result['lifecycle_rebuild']=rebuilt;save('lifecycle-rebuild.json',rebuilt)
    print('PASS lifecycle replay: '+str(rebuilt['events'])+' events rebuilt 1000 matching link states in '+str(round(rebuilt['rebuild_seconds'],3))+'s.',flush=True)
    alive('two-relay-concurrency');bulk=relay_pair(key('seed'),'bulk-relay')
    assert bulk['missing_events']==bulk['phantom_events']==bulk['payload_mismatches']==bulk['unpublished']==0 and bulk['unique_events']==rebuilt['events'],bulk
    assert all(total>0 for total in bulk['published_by_each_relay']),bulk
    result['two_relays']=bulk;save('two-relays.json',bulk);alive('lifecycle-drills-complete')
    print('PASS two concurrent relays: both published committed rows, no lifecycle event lost or phantom.',flush=True)
finally:
    (out/'stop-load').touch()
    for actor in actors:
        if actor['process'].poll() is None:stop_actor(actor)
        elif not actor['handle'].closed:actor['handle'].close()
    try:load_process.wait(timeout=20)
    except subprocess.TimeoutExpired:load_process.kill();load_process.wait(timeout=10)
    load_log.close()
if load_process.returncode:raise RuntimeError('Steady producer failed; inspect live-load.log')
load=json.loads((out/'live-load.json').read_text(encoding='utf-8'));assert not load['errors'] and not load['skipped'] and 190<=load['attempt_rps']<=210,load
def caught_up():
    value=data(compose('exec','-T','app1','php','/tmp/lab7-fixture.php','state',str(live['link_id']),live['stream']))
    return value if value['count']==value['raw']==load['success'] else None
final_live=wait_for(caught_up,'Live click projection did not catch up',120)
result.update(passed=True,steady_http_load=load,load_checkpoints=checkpoints,final_live_count=final_live,
    guarantees='Lifecycle state + immutable application log + outbox commit atomically. Relay is at-least-once; duplicate delivery after publish-before-mark uses the same event UUID. LinkClicked remains best-effort direct XADD to protect redirect availability; no DB outbox per click.',
    limitations='1000-link event replay validates detached reconstructed state, not replacement of the live links table. Append-only is enforced by application write paths and model guards; query-builder/direct SQL maintenance must be restricted operationally. Parallel relays may reorder notifications; lifecycle version orders authoritative replay.')
save('results.json',result)
notes=f"""استخدمت Event Sourcing لحالة اللينك، وسجلت التغييرات بترتيب version داخل link_events في MariaDB.
اللينكات القديمة ليها LinkImported من حالتها وقت أول تعديل، مش تاريخ قديم متخيل.
أعدت بناء حالة 1000 لينك من {rebuilt['events']} حدث في {rebuilt['rebuild_seconds']:.3f} ثانية، وطابقت الوجهة والحالة والانتهاء والإصدار مع links.
استخدمت Event Streaming في Redis علشان كل Consumer Group تقرأ الأحداث بشكل مستقل، وإعادة القراءة هناك مرتبطة بالتاريخ اللي لسه محتفظ بيه الـStream.
حالة اللينك وسجل الحدث وصف الـOutbox بيتكتبوا في Transaction واحدة، والـRelay ممكن يعيد التسليم بنفس UUID لو وقع بعد النشر وقبل تسجيل النجاح.
ضغطات اللينكات بتتنشر مباشرة بـXADD من غير Outbox لكل ضغطة، وده مسار Best Effort بيقبل احتمال فقد الحدث عند فشل النشر للحفاظ على توفر الـRedirect.
مطبقتش Event Sourcing على كل الضغطة بسبب حجمها وتكلفة الكتابة المتزامنة، وسجل Lifecycle مستقل عن Retention الخاص بـRedis.
"""
(out/'EVENT_SOURCING_VS_STREAMING.txt').write_text(notes,encoding='utf-8')
print('Live load:' ,json.dumps(load),flush=True);print('Results:',out,flush=True)

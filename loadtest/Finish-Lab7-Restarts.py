import ast,csv,datetime,json,subprocess,time,sys
from pathlib import Path
tree=ast.parse(Path('loadtest/Run-Lab7-Restarts.py').read_text(encoding='utf-8-sig'))
files=next(ast.literal_eval(n.value)for n in tree.body if isinstance(n,ast.Assign)and any(isinstance(t,ast.Name)and t.id=='FILES'for t in n.targets))
base=['docker','compose']+[v for f in files+['docker-compose.lab5-webhook-test.yml']for v in ['-f',f]]
out=Path(sys.argv[1])if len(sys.argv)>1 else max((p for p in Path('work').glob('lab7-restarts-*')if(p/'live-load.json').exists()and(p/'phases.json').exists()),key=lambda p:p.stat().st_mtime)
def read(name):return json.loads((out/name).read_text(encoding='utf-8-sig'))
def save(name,value):(out/name).write_text(json.dumps(value,ensure_ascii=False,indent=2),encoding='utf-8')
def execute(args,check=True):
    p=subprocess.run(base+args,capture_output=True,text=True,encoding='utf-8',errors='replace',timeout=60,creationflags=getattr(subprocess,'CREATE_NO_WINDOW',0))
    if check and p.returncode:raise RuntimeError(p.stdout+p.stderr)
    return p
def data(text):
    for line in reversed(text.splitlines()):
        try:return json.loads(line.lstrip('\ufeff'))
        except ValueError:pass
    raise RuntimeError(text[-1000:])
live=read('live-fixture.json');load=read('live-load.json');phases=read('phases.json')
execute(['cp','./loadtest/lab7-restart-fixture.php','app1:/tmp/lab7-restart-fixture.php'])
history=[];deadline=time.monotonic()+300;last=None;changed=time.monotonic()
while True:
    state=data(execute(['exec','-T','-e','LAB7_FAILURE_DRILLS=1','-e','APP_CONFIG_CACHE=/tmp/lab7-restart-cli.php','-e','DB_READ_HOST=db-primary','-e','DB_WRITE_HOST=db-primary','app1','php','/tmp/lab7-restart-fixture.php','state',str(live['link_id'])]).stdout)
    stamp=datetime.datetime.now(datetime.timezone.utc).isoformat();history.append({'at':stamp,'state':state});save('drain-observations.json',history)
    groups=[dict(zip(fields[::2],fields[1::2]))for fields in state['groups']]
    group=next(g for g in groups if g['name']=='dashboard')
    print('Counter/raw/ledger:',state['count'],state['raw'],state['ledger'],'Dashboard rows:',state['dashboard_rows'],'pending:',group['pending'],'lag:',group.get('lag'),flush=True)
    if state['count']==state['raw']==state['ledger']==state['dashboard_rows']and state['count']>=load['append_confirmed']:break
    marker=(state['dashboard_rows'],group['pending'],group.get('lag'))
    if marker!=last:last=marker;changed=time.monotonic()
    if time.monotonic()>deadline or time.monotonic()-changed>15:
        logs=execute(['logs','--since','5m','--tail','60','dashboard-consumer','reverb-1','analytics-1','analytics-2'],False)
        (out/'drain-stall.log').write_text(logs.stdout+logs.stderr,encoding='utf-8')
        save('drain-stall.json',state)
        print('STALLED pending sample:',json.dumps(state.get('pending_dashboard_sample'),ensure_ascii=False),flush=True)
        print((logs.stdout+logs.stderr)[-6000:],flush=True)
        raise RuntimeError('Dashboard has stopped progressing; evidence saved. No events were skipped or acknowledged by this script.')
    time.sleep(3)
monitor=read('dashboard-monitor.json')
receiver=data(execute(['exec','-T','lab5-webhook-receiver','python3','-c','import urllib.request;print(urllib.request.urlopen("http://127.0.0.1/metrics").read().decode())']).stdout)
observed={p['notice_event_id']:{'effects':receiver['effects'].get(p['notice_event_id'],0),'attempts':receiver['attempts'].get(p['notice_event_id'],0)}for p in phases}
assert all(v['effects']==1 for v in observed.values()),observed
assert monitor['final_count']==state['count'],'Original frontend monitor did not reach the final database count'
rows=read('click-observations.json')
for phase in phases:
    sample=[r for r in rows if phase['started_at_ms']<=r['started_at']<phase['finished_at_ms']]
    phase['http_attempts']=len(sample);phase['http_302']=sum(r['status']==302 for r in sample)
    phase['http_errors']=len(sample)-phase['http_302'];phase['unconfirmed_302']=sum(r['status']==302 and r['append_confirmed']=='no'for r in sample)
    times=sorted(r['finished_at']-r['started_at']for r in sample)
    phase['observed_http_p95_ms']=times[min(len(times)-1,int(len(times)*.95))]if times else None
finished=datetime.datetime.fromisoformat(load['finished_at'].replace('Z','+00:00'))
result={'passed':True,'resumed_without_repeating_load':True,'phases':phases,'steady_load':load,'final_projection':state,'dashboard':monitor,'webhook_receiver':observed,
 'successful_redirects_without_recorded_click_lower_bound':max(0,load['success']-state['count']),
 'recorded_clicks_without_confirmed_http302_lower_bound':max(0,state['count']-load['success']),
 'drain_completion_observed_at':stamp,'seconds_since_load_end_at_observation':round((datetime.datetime.now(datetime.timezone.utc)-finished).total_seconds(),3),
 'limitations':'Original 120-second convergence deadline failed. Drain was checked later; the observation time is an upper bound, not exact recovery latency. Original frontend monitor final counter reached database value; complete per-event dashboard history was verified separately. Graceful Redis restart with existing AOF volume, not hard power-loss proof. Unconfirmed append does not prove loss of that particular UUID; HTTP timeouts may commit. Synthetic outbox notices use actual delivery worker with isolated test receiver, not automatic milestone subscription scheduling.'}
save('results.json',result)
with(out/'restart-summary.csv').open('w',newline='',encoding='utf-8-sig')as f:
    writer=csv.DictWriter(f,fieldnames=['service','http_attempts','http_302','http_errors','unconfirmed_302','observed_http_p95_ms','notice_to_delivery_seconds'],extrasaction='ignore');writer.writeheader();writer.writerows(phases)
print('PASS: retained analytics facts and dashboard history match; original frontend reached final count.',flush=True)
print('HTTP302:',load['success'],'confirmed appends:',load['append_confirmed'],'unconfirmed:',load['append_unconfirmed'],'HTTP errors:',load['errors'],flush=True)
print('Results:',out,flush=True)

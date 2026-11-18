import datetime as dt
import json
from pathlib import Path
import subprocess

files=['docker-compose.yml','docker-compose.lab1.yml','docker-compose.lab2.yml',
       'docker-compose.lab2-network.yml','docker-compose.lab2-http.yml',
       'docker-compose.lab3.yml','docker-compose.lab3-fixed.yml','docker-compose.lab3-microcache.yml']
compose=['docker','compose']+[x for f in files for x in ['-f',f]]
out=Path('work')/('lab3-pause-'+dt.datetime.now().strftime('%Y%m%d-%H%M%S'))
out.mkdir(parents=True)

def run(*args):
    r=subprocess.run(compose+list(args),capture_output=True,text=True)
    if r.returncode: raise RuntimeError(r.stdout+r.stderr)
    return r.stdout

def fixture(action,id=None):
    args=['exec','-T','app1','php','/tmp/lab3-micro-fixture.php',action]
    if id is not None: args.append(str(id))
    return run(*args)

run('cp','./loadtest/lab3-micro-fixture.php','app1:/tmp/lab3-micro-fixture.php')
run('cp','./loadtest/lab3-pause-probe.py','dns-client:/tmp/lab3-pause-probe.py')
links=json.loads(fixture('prepare'))
link=links['pause']
samples=[];raw=[];pause=None;probe=None
try:
    probe=subprocess.Popen(compose+['exec','-T','dns-client','python3','-u','/tmp/lab3-pause-probe.py',link['code']],
                           stdout=subprocess.PIPE,stderr=subprocess.STDOUT,text=True)
    for line in probe.stdout:
        raw.append(line)
        if line.startswith('READY '):
            print('Cold cache warmed. Pausing through existing UpdateLinkService...',flush=True)
            pause=subprocess.Popen(compose+['exec','-T','app1','php','/tmp/lab3-micro-fixture.php','pause',str(link['id'])],
                                   stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True)
        elif line.startswith('SAMPLE '): samples.append(json.loads(line[7:]))
        else: print(line,end='',flush=True)
    if probe.wait(): raise RuntimeError('Continuous probe failed; see raw evidence.')
    if pause is None: raise RuntimeError('Probe did not become ready.')
    stdout,stderr=pause.communicate(timeout=20)
    if pause.returncode: raise RuntimeError(stdout+stderr)
    paused_at=json.loads(stdout)['service_returned_at_unix']
    after=[r for r in samples if r['request_started_unix']>=paused_at]
    stale=[r for r in after if r['status']==302 and r['cache']=='HIT']
    gone=[r for r in after if r['status']==404]
    result={'link':link,'pause_service_returned_at_unix':paused_at,
        'cached_302_after_pause':len(stale),
        'last_cached_302_seconds_after_pause':round(stale[-1]['response_at_unix']-paused_at,3) if stale else None,
        'first_404_seconds_after_pause':round(gone[0]['response_at_unix']-paused_at,3) if gone else None,
        'observed_staleness':bool(stale),'samples':samples,
        'method':'One persistent HTTPS connection, polling every 50ms from a container; no per-request Docker exec. '
                 'Only requests started AFTER UpdateLinkService returns count as post-pause. '
                 'PHP pause and probe share the Docker VM clock. Timings measure this run, not maximum TTL guarantee.'}
    (out/'result.json').write_text(json.dumps(result,indent=2))
    print('Cached 302 replies AFTER confirmed pause:',len(stale),flush=True)
    print('Last stale 302 after pause:',result['last_cached_302_seconds_after_pause'],'seconds',flush=True)
    print('First 404 after pause:',result['first_404_seconds_after_pause'],'seconds',flush=True)
    if not stale:
        print('No stale reply observed in this run; do not claim pause-staleness proof.',flush=True)
finally:
    if probe is not None and probe.poll() is None: probe.terminate()
    if pause is not None and pause.poll() is None: pause.communicate(timeout=20)
    fixture('resume',link['id'])
    (out/'probe.log').write_text(''.join(raw),encoding='utf8')
    print('Fixture resumed. Results:',out,flush=True)

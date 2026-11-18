import datetime as dt
import json
from pathlib import Path
import subprocess
import time

files=['docker-compose.yml','docker-compose.lab1.yml','docker-compose.lab2.yml',
       'docker-compose.lab2-network.yml','docker-compose.lab2-http.yml',
       'docker-compose.lab3.yml','docker-compose.lab3-fixed.yml','docker-compose.lab3-microcache.yml']
compose=['docker','compose']+[x for f in files for x in ['-f',f]]
out=Path('work')/('lab3-microcache-demo-'+dt.datetime.now().strftime('%Y%m%d-%H%M%S'))
out.mkdir(parents=True)
evidence=[]

def run(*args):
    r=subprocess.run(compose+list(args),capture_output=True,text=True)
    if r.returncode: raise RuntimeError(r.stdout+r.stderr)
    return r.stdout

def fixture(action,id=None):
    return run('exec','-T','app1','php','/tmp/lab3-micro-fixture.php',action,*([str(id)] if id else []))

def request(code):
    text=run('exec','-T','dns-client','curl','--noproxy','*','--cacert','/lab2/rootCA.pem',
        '--resolve','go.brand.test:443:172.30.53.10','--max-time','10','-sS','-D','-','-o','/dev/null',
        'https://go.brand.test/'+code)
    lines=text.splitlines()
    status=int(lines[0].split()[1])
    headers={k.lower():v.strip() for line in lines[1:] if ':' in line for k,v in [line.split(':',1)]}
    return {'status':status,'cache':headers.get('x-lab3-cache','ABSENT'),'location':headers.get('location')}

run('cp','./loadtest/lab3-micro-fixture.php','app1:/tmp/lab3-micro-fixture.php')
links=json.loads(fixture('prepare'))
(out/'fixture.json').write_text(json.dumps(links,indent=2))
pause_id=links['pause']['id']
try:
    before=json.loads(fixture('stats',links['clicks']['id']))
    for _ in range(30):
        response=request(links['clicks']['code'])
        evidence.append(response)
        if response['status']!=302 or not response['location']:
            raise RuntimeError('Expected redirect 302.')
    if not any(r['cache']=='HIT' for r in evidence):
        raise RuntimeError('No cache HIT observed. Cache demo not passed.')
    hits=sum(r['cache']=='HIT' for r in evidence)
    backend=len(evidence)-hits
    print(f'Redirect requests: {len(evidence)}; cache HITs: {hits}; requests reaching Laravel: {backend}',flush=True)
    run('exec','-T','app1','php','artisan','analytics:flush')
    deadline=time.monotonic()+25
    while True:
        after=json.loads(fixture('stats',links['clicks']['id']))
        recorded=after['raw_clicks']-before['raw_clicks']
        if recorded>=backend: break
        if time.monotonic()>deadline:
            raise RuntimeError('Analytics did not drain; check worker logs, do not label delay as lost clicks.')
        time.sleep(1)
    print(f'Raw clicks recorded: {recorded}; HTTP redirects served: {len(evidence)}; difference: {len(evidence)-recorded}',flush=True)
    warm=request(links['pause']['code'])
    if warm['status']!=302 or warm['cache']!='MISS':
        raise RuntimeError('Pause fixture must start with cold MISS.')
    fixture('pause',pause_id)
    paused=time.monotonic()
    observations=[]
    while True:
        response=request(links['pause']['code'])
        response['seconds_after_pause_service_returned']=round(time.monotonic()-paused,3)
        observations.append(response)
        if response['status']==404: break
        if response['status']!=302 or time.monotonic()-paused>8:
            raise RuntimeError('Unexpected pause response or stale beyond expected window.')
        time.sleep(0.2)
    stale=sum(r['status']==302 for r in observations)
    print('Cached 302 replies AFTER pause:',stale,flush=True)
    print('First observed 404 after pause:',observations[-1]['seconds_after_pause_service_returned'],'seconds',flush=True)
    result={'http_redirects':len(evidence),'cache_hits':hits,'backend_requests':backend,
        'raw_clicks_recorded':recorded,'click_difference':len(evidence)-recorded,
        'pause_observations':observations,'pause_cached_302_observed':stale>0,
        'note':'Isolated lab3mc_ links only. Cache ignores origin Cache-Control for this experiment; browser still receives no-store. '
               'Pausing uses the existing UpdateLinkService directly, not a new pause API. '
               'Timing starts after service returns, so it measures remaining stale window, not a guaranteed maximum.'}
    (out/'result.json').write_text(json.dumps(result,indent=2))
finally:
    fixture('resume',pause_id)
    (out/'redirect-evidence.json').write_text(json.dumps(evidence,indent=2))
    print('Pause fixture resumed. Results:',out,flush=True)

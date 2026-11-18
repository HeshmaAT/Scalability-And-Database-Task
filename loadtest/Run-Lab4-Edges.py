import concurrent.futures
import datetime as dt
import hashlib
import json
from pathlib import Path
import statistics
import subprocess
import threading
import time
import urllib.parse
import urllib.request
import uuid

root=Path.cwd()
files=['docker-compose.yml','docker-compose.lab1.yml','docker-compose.lab2.yml',
       'docker-compose.lab2-network.yml','docker-compose.lab2-http.yml','docker-compose.lab3.yml',
       'docker-compose.lab3-fixed.yml','docker-compose.lab3-egress.yml','docker-compose.lab3-isolated.yml',
       'docker-compose.lab4-origin.yml','docker-compose.lab4-edges.yml']
compose=['docker','compose']+[x for f in files for x in ['-f',f]]
out=root/'work'/('lab4-edges-'+dt.datetime.now().strftime('%Y%m%d-%H%M%S'))
out.mkdir(parents=True)
rows=[]

def run(*args,compose_args=compose):
    p=subprocess.run(compose_args+list(args),capture_output=True,text=True,encoding='utf8',errors='replace')
    if p.returncode: raise RuntimeError(p.stdout+p.stderr)
    return p.stdout

def api(method,path,payload=None,allowed=(200,204)):
    args=['exec','-T','lab4-tools','curl','--noproxy','*','--max-time','5','-sS','-X',method]
    if payload is not None:
        data=out/'api-payload.json';data.write_text(json.dumps(payload))
        run('cp',str(data),'lab4-tools:/tmp/lab4-api-payload.json')
        args+=['-H','Content-Type: application/json','--data-binary','@/tmp/lab4-api-payload.json']
    text=run(*(args+['-w','\n%{http_code}','http://toxiproxy:8474'+path]))
    body,status=text.rsplit('\n',1)
    if int(status) not in allowed: raise RuntimeError('Toxiproxy API failed: '+status+' '+body)
    return json.loads(body) if body.strip() else None

def edge_url(original,port):
    parsed=urllib.parse.urlsplit(original)
    return f'http://127.0.0.1:{port}'+parsed.path+'?'+parsed.query

def fetch(url,probe):
    opener=urllib.request.build_opener(urllib.request.ProxyHandler({}))
    start=time.perf_counter()
    with opener.open(urllib.request.Request(url,headers={'X-Lab4-Probe':probe}),timeout=30) as r:
        data=r.read()
        elapsed=(time.perf_counter()-start)*1000
        if r.status!=200 or hashlib.sha256(data).hexdigest()!=fixture['sha256']:
            raise RuntimeError('Bad image/status from edge.')
        return {'status':r.status,'cache':r.headers.get('X-Cache-Status'),
            'age':r.headers.get('Age'),'cache_control':r.headers.get('Cache-Control'),
            'total_ms':round(elapsed,3),'bytes':len(data)}

def origin_count(probe):
    text=run('exec','-T','nginx','tail','-n','1000','/var/log/nginx/lab4-origin.log')
    matches=[]
    for line in text.splitlines():
        try: row=json.loads(line)
        except json.JSONDecodeError: continue
        if row.get('probe')==probe: matches.append(row)
    (out/(probe+'-origin.json')).write_text(json.dumps(matches,indent=2))
    return len(matches)

def burst(url,label):
    probe=label+'-'+uuid.uuid4().hex[:10]
    parsed=urllib.parse.urlsplit(url)
    edge='edge-eu' if parsed.port==8084 else 'edge-asia'
    internal_url='http://'+edge+parsed.path+'?'+parsed.query
    measured=json.loads(run('exec','-T','lab4-tools','node','/tmp/lab4-edge-burst.mjs',internal_url,probe,fixture['sha256']))
    (out/(probe+'-responses.json')).write_text(json.dumps(measured,indent=2))
    expected='off' if label=='without_collapsing' else 'on'
    if any(r.get('collapsing')!=expected for r in measured['responses']):
        raise RuntimeError('Runtime collapsing mode does not match '+expected)
    if not measured['all_connected_before_first_response']:
        raise RuntimeError('Not all 200 connections were established before the first response; concurrency proof failed.')
    row={'test':label,'requests':200,'origin_requests':origin_count(probe),
         **{key:measured[key] for key in ['hits','median_ms','burst_elapsed_s','connection_spread_ms','all_connected_before_first_response']}}
    rows.append(row);print(json.dumps(row),flush=True)
    return row

try:
    run('cp','./loadtest/lab4-edge-burst.mjs','lab4-tools:/tmp/lab4-edge-burst.mjs')
    for edge in ['edge-eu','edge-asia']:
        run('exec','-T',edge,'nginx','-t')
        run('exec','-T',edge,'nginx','-s','reload')
    api('DELETE','/proxies/lab4-minio/toxics/lab4-origin-latency',allowed=(204,404))
    run('cp','./loadtest/lab4-origin-fixture.php','app1:/tmp/lab4-origin-fixture.php')
    fixture=json.loads(run('exec','-T','app1','php','/tmp/lab4-origin-fixture.php'))
    run('cp','./loadtest/lab4-edge-sign.php','app1:/tmp/lab4-edge-sign.php')
    urls=json.loads(run('exec','-T','app1','php','/tmp/lab4-edge-sign.php',str(fixture['id'])))
    (out/'fixture-private.json').write_text(json.dumps({'fixture':fixture,'urls':urls},indent=2))
    api('POST','/proxies/lab4-minio/toxics',{'name':'lab4-origin-latency','type':'latency',
        'stream':'downstream','toxicity':1.0,'attributes':{'latency':200,'jitter':0}},allowed=(200,201))
    # The origin config was changed via a bind mount; reload also refreshes FPM addresses.
    run('exec','-T','nginx','nginx','-t')
    run('exec','-T','nginx','nginx','-s','reload')
    for label,port in [('eu',8084),('asia',8085)]:
        url=edge_url(urls[label+'_sample'],port)
        probe=label+'-sample-'+uuid.uuid4().hex[:8]
        miss=fetch(url,probe)
        time.sleep(1.1)
        hit=fetch(url,probe)
        row={'test':label+'_miss_hit','miss':miss,'hit':hit,'origin_requests':origin_count(probe)}
        rows.append(row);print(json.dumps(row),flush=True)
        if miss['cache']!='MISS' or hit['cache']!='HIT' or int(hit['age'] or '-1')<1:
            raise RuntimeError('MISS/HIT/Age preflight failed; stop before burst.')
    # Only the Asia lab edge is temporarily recreated with collapsing OFF.
    unlocked=compose+['-f','docker-compose.lab4-unlocked.yml']
    run('up','-d','--force-recreate','--no-deps','edge-asia',compose_args=unlocked)
    time.sleep(2)
    config=run('exec','-T','edge-asia','nginx','-T')
    (out/'asia-unlocked-runtime.conf').write_text(config)
    if 'proxy_cache_lock off;' not in config:
        raise RuntimeError('The running Asia edge is not using collapsing OFF.')
    without=burst(edge_url(urls['asia_burst'],8085),'without_collapsing')
    with_lock=burst(edge_url(urls['eu_burst'],8084),'with_collapsing')
    if with_lock['origin_requests']>=without['origin_requests']:
        raise RuntimeError('No origin-request reduction observed; do not claim collapsing win.')
    print('PASS: both edges MISS then HIT; Age advances; collapsing reduced origin requests.',flush=True)
finally:
    (out/'comparison.json').write_text(json.dumps(rows,indent=2))
    try: api('DELETE','/proxies/lab4-minio/toxics/lab4-origin-latency',allowed=(204,404))
    except Exception as e: print('Toxic cleanup failed:',str(e),flush=True)
    try: run('up','-d','--no-deps','edge-asia')
    except Exception as e: print('Edge restore failed:',str(e),flush=True)
    print('Latency removed; both edges restored to collapsing ON. Results:',out,flush=True)

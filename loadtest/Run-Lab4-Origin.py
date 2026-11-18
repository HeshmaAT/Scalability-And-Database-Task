import datetime as dt
import hashlib
import json
from pathlib import Path
import statistics
import subprocess
import time
import urllib.request

root=Path.cwd()
files=['docker-compose.yml','docker-compose.lab1.yml','docker-compose.lab2.yml',
       'docker-compose.lab2-network.yml','docker-compose.lab2-http.yml',
       'docker-compose.lab3.yml','docker-compose.lab3-fixed.yml',
       'docker-compose.lab3-egress.yml','docker-compose.lab3-isolated.yml','docker-compose.lab4-origin.yml']
compose=['docker','compose']+[x for f in files for x in ['-f',f]]
out=root/'work'/('lab4-origin-'+dt.datetime.now().strftime('%Y%m%d-%H%M%S'))
out.mkdir(parents=True)
opener=urllib.request.build_opener(urllib.request.ProxyHandler({}))

def run(*args):
    p=subprocess.run(compose+list(args),capture_output=True,text=True,encoding='utf8',errors='replace')
    if p.returncode: raise RuntimeError(p.stdout+p.stderr)
    return p.stdout

def api(method,path,payload=None,allowed=(200,204)):
    args=['exec','-T','lab4-tools','curl','--noproxy','*','--max-time','5','-sS','-X',method]
    if payload is not None:
        data=out/'api-payload.json'
        data.write_text(json.dumps(payload),encoding='utf8')
        run('cp',str(data),'lab4-tools:/tmp/lab4-api-payload.json')
        args+=['-H','Content-Type: application/json','--data-binary','@/tmp/lab4-api-payload.json']
    text=run(*(args+['-w','\n%{http_code}','http://toxiproxy:8474'+path]))
    body,status=text.rsplit('\n',1)
    if int(status) not in allowed: raise RuntimeError('Toxiproxy API status '+status+': '+body)
    return json.loads(body) if body.strip() else None

rows=[]
try:
    deadline=time.monotonic()+20
    while True:
        try:
            proxy=api('GET','/proxies/lab4-minio')
            break
        except Exception:
            if time.monotonic()>deadline: raise
            time.sleep(1)
    if proxy.get('upstream')!='minio:9000' or not proxy.get('enabled'):
        raise RuntimeError('Wrong or disabled origin proxy.')
    # Clear only our named toxic on repeat runs, leaving unrelated proxies alone.
    api('DELETE','/proxies/lab4-minio/toxics/lab4-origin-latency',allowed=(204,404))
    run('cp','./loadtest/lab4-origin-fixture.php','app1:/tmp/lab4-origin-fixture.php')
    fixture=json.loads(run('exec','-T','app1','php','/tmp/lab4-origin-fixture.php'))
    (out/'fixture-private.json').write_text(json.dumps(fixture,indent=2))
    print('Ready: isolated QR in MinIO, S3 client endpoint confirmed as Toxiproxy.',flush=True)
    for delay in [0,200]:
        if delay:
            api('POST','/proxies/lab4-minio/toxics',{'name':'lab4-origin-latency','type':'latency',
                'stream':'downstream','toxicity':1.0,'attributes':{'latency':200,'jitter':0}},allowed=(200,201))
        state=api('GET','/proxies/lab4-minio/toxics')
        (out/f'toxics-{delay}.json').write_text(json.dumps(state,indent=2))
        if delay and not any(t.get('name')=='lab4-origin-latency' and t.get('attributes',{}).get('latency')==200 for t in state):
            raise RuntimeError('200ms latency toxic was not applied.')
        samples=[]
        for sample in range(6):
            start=time.perf_counter()
            with opener.open(fixture['url'],timeout=15) as response:
                body=response.read()
                elapsed=(time.perf_counter()-start)*1000
                if response.status!=200 or 'image/svg+xml' not in response.headers.get('Content-Type',''):
                    raise RuntimeError('Expected a successful SVG response.')
                if hashlib.sha256(body).hexdigest()!=fixture['sha256']:
                    raise RuntimeError('QR content changed between measurements.')
                row={'downstream_delay_ms':delay,'sample':sample,'warmup':sample==0,
                     'http_status':response.status,'total_ms':round(elapsed,3),'bytes':len(body),
                     'cache_control':response.headers.get('Cache-Control')}
            rows.append(row)
            if sample: samples.append(elapsed)
        print(json.dumps({'downstream_delay_ms':delay,'samples':5,
                          'median_total_ms':round(statistics.median(samples),3),
                          'image_bytes':fixture['image_bytes']}),flush=True)
    base=statistics.median(r['total_ms'] for r in rows if r['downstream_delay_ms']==0 and not r['warmup'])
    slow=statistics.median(r['total_ms'] for r in rows if r['downstream_delay_ms']==200 and not r['warmup'])
    summary={'baseline_median_ms':round(base,3),'slow_origin_median_ms':round(slow,3),
             'difference_ms':round(slow-base,3),'samples_per_condition':5,
             'method':'Same real SVG and signed Laravel QR endpoint, content hash checked, origin no-store. '
                      'Toxiproxy delays downstream MinIO data by 200ms. This is not a measured geographical RTT. '
                      'Host end-to-end timings include nginx, PHP, metadata lookup and S3 read; first request per condition excluded as warmup.'}
    (out/'summary.json').write_text(json.dumps(summary,indent=2))
    print('Summary:',json.dumps(summary),flush=True)
    if slow-base<150:
        raise RuntimeError('Configured toxic did not show a clear latency effect; investigate before claiming success.')
finally:
    (out/'measurements.json').write_text(json.dumps(rows,indent=2))
    try:
        api('DELETE','/proxies/lab4-minio/toxics/lab4-origin-latency',allowed=(204,404))
        print('Latency toxic removed. MinIO remains reachable through the no-delay proxy.',flush=True)
    except Exception as exc:
        print('Could not remove latency toxic:',exc,flush=True)
    print('Results:',out,flush=True)

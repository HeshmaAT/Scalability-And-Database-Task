import datetime as dt
import json
from pathlib import Path
import subprocess
import time
import urllib.error
import urllib.parse
import urllib.request
import uuid

root=Path.cwd()
files=['docker-compose.yml','docker-compose.lab1.yml','docker-compose.lab2.yml',
       'docker-compose.lab2-network.yml','docker-compose.lab2-http.yml','docker-compose.lab3.yml',
       'docker-compose.lab3-fixed.yml','docker-compose.lab3-egress.yml','docker-compose.lab3-isolated.yml',
       'docker-compose.lab4-origin.yml','docker-compose.lab4-edges.yml','docker-compose.lab4-signed.yml']
compose=['docker','compose']+[x for f in files for x in ['-f',f]]
out=root/'work'/('lab4-signed-cache-'+dt.datetime.now().strftime('%Y%m%d-%H%M%S'))
out.mkdir(parents=True)
summary=[]

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

def count(probe,file):
    text=run('exec','-T','nginx','tail','-n','6000',file)
    matched=[]
    for line in text.splitlines():
        try: row=json.loads(line)
        except json.JSONDecodeError: continue
        if row.get('probe')==probe: matched.append(row)
    (out/(probe+'-'+Path(file).name+'.json')).write_text(json.dumps(matched,indent=2))
    return len(matched)

def security_request(original,port=8084):
    parsed=urllib.parse.urlsplit(original)
    url=f'http://127.0.0.1:{port}'+parsed.path+'?'+parsed.query
    opener=urllib.request.build_opener(urllib.request.ProxyHandler({}))
    try:
        with opener.open(url,timeout=15) as response:
            response.read()
            return response.status,response.headers.get('X-Cache-Status')
    except urllib.error.HTTPError as e:
        e.read()
        return e.code,e.headers.get('X-Cache-Status')

try:
    run('up','-d')
    api('DELETE','/proxies/lab4-minio/toxics/lab4-origin-latency',allowed=(204,404))
    run('exec','-T','nginx','nginx','-t')
    run('exec','-T','nginx','nginx','-s','reload')
    run('cp','./loadtest/lab4-signature-fixtures.php','app1:/tmp/lab4-signature-fixtures.php')
    print('Preparing 100 real QR images and 1000 distinct valid signed URLs...',flush=True)
    fixture=json.loads(run('exec','-T','app1','php','/tmp/lab4-signature-fixtures.php'))
    private=out/'fixture-private.json';private.write_text(json.dumps(fixture))
    run('cp',str(private),'lab4-tools:/tmp/lab4-signed-input.json')
    run('cp','./loadtest/lab4-signature-load.mjs','lab4-tools:/tmp/lab4-signature-load.mjs')
    api('POST','/proxies/lab4-minio/toxics',{'name':'lab4-origin-latency','type':'latency',
        'stream':'downstream','toxicity':1.0,'attributes':{'latency':200,'jitter':0}},allowed=(200,201))
    for mode in ['full_uri','authorized_version']:
        active=compose+['-f','docker-compose.lab4-full-uri.yml'] if mode=='full_uri' else compose
        run('up','-d','--no-deps','edge-eu','edge-asia',compose_args=active)
        time.sleep(1)
        config=run('exec','-T','edge-eu','nginx','-T')
        (out/(mode+'-nginx.conf')).write_text(config)
        if mode=='authorized_version' and 'auth_request /_lab4_authorize;' not in config:
            raise RuntimeError('Authorized cache configuration was not activated.')
        probe=mode+'-'+uuid.uuid4().hex[:10]
        print('Measuring:',mode,flush=True)
        result=json.loads(run('exec','-T','lab4-tools','node','/tmp/lab4-signature-load.mjs',
                              '/tmp/lab4-signed-input.json',probe))
        (out/(mode+'-responses.json')).write_text(json.dumps(result,indent=2))
        if mode=='authorized_version' and any(not r.get('key_version') for r in result['results']):
            raise RuntimeError('Missing authorized image version.')
        row={'mode':mode,'images':100,'requests':result['requests'],'hits':result['hits'],
             'hit_percent':round(result['hit_percent'],2),'image_origin_requests':count(probe,'/var/log/nginx/lab4-origin.log'),
             'authorization_requests':count(probe,'/var/log/nginx/lab4-auth.log'),
             'median_ms':round(result['median_ms'],3),'elapsed_s':round(result['elapsed_s'],3)}
        summary.append(row);print(json.dumps(row),flush=True)
    if summary[0]['hit_percent']>1 or summary[1]['hit_percent']<=summary[0]['hit_percent']:
        raise RuntimeError('Signature-fragmentation comparison did not show the expected effect.')
    valid=fixture['requests'][0]['url']
    parsed=urllib.parse.urlsplit(valid)
    values=urllib.parse.parse_qs(parsed.query)
    values['signature']=['invalid']
    tampered=urllib.parse.urlunsplit(parsed._replace(query=urllib.parse.urlencode(values,doseq=True)))
    checks=[]
    for label,url,expected in [('valid_warm',valid,200),('tampered_warm',tampered,403),('expired_warm',fixture['expired'],403)]:
        status,cache=security_request(url)
        checks.append({'test':label,'status':status,'expected':expected,'cache':cache})
        if status!=expected: raise RuntimeError('Security check failed: '+label)
    for row in checks: print(json.dumps(row),flush=True)
    (out/'security.json').write_text(json.dumps(checks,indent=2))
    (out/'METHOD.txt').write_text('100 new real QR objects, 1000 independently signed URLs, a fresh signed nonce per request to deliberately reproduce fragmentation. '
        'The ordinary API may generate the SAME signature within one second: this test does not claim every API call inherently differs. '
        '20 concurrent HTTP/1.1 client sockets inside Docker; same fixture/order, 200ms MinIO downstream delay, no browser cache. '
        'Before: full signed URI key. After: QR path + current object version; every request authorized by Laravel through nginx auth_request before cache lookup. '
        'Auth validates signature/expiry and current nondeleted link + ready metadata; no S3 get in authorization. '
        'Image-origin counts exclude authorization; auth traffic still reaches Laravel and is explicitly reported. '
        'New QR objects use unique immutable object keys; legacy ready objects remain unchanged. '
        'API-generated QR download URLs use configured EU edge; canonical signing authority remains APP_URL. '
        'Local-only edge exposure, fixed topology, short lab test; production TLS/HA/health checks are not proved here.')
    print('PASS: cache fragmentation reduced; invalid and expired signatures rejected despite warm image cache.',flush=True)
finally:
    (out/'comparison.json').write_text(json.dumps(summary,indent=2))
    try: api('DELETE','/proxies/lab4-minio/toxics/lab4-origin-latency',allowed=(204,404))
    except Exception as e: print('Latency cleanup failed:',e,flush=True)
    try: run('up','-d','--no-deps','edge-eu','edge-asia')
    except Exception as e: print('Authorized edge restore failed:',e,flush=True)
    print('Latency removed; authorized/versioned cache active. Results:',out,flush=True)

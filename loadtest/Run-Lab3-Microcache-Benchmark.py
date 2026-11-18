import datetime as dt
import json
import os
from pathlib import Path
import shutil
import subprocess
import time
import urllib.request

root=Path.cwd()
base_files=['docker-compose.yml','docker-compose.lab1.yml','docker-compose.lab2.yml',
            'docker-compose.lab2-network.yml','docker-compose.lab2-http.yml',
            'docker-compose.lab3.yml','docker-compose.lab3-fixed.yml']
base=['docker','compose']+[part for file in base_files for part in ['-f',file]]
out=root/'work'/('lab3-micro-benchmark-'+dt.datetime.now().strftime('%Y%m%d-%H%M%S'))
out.mkdir(parents=True)
k6=shutil.which('k6') or str(Path(os.environ.get('ProgramFiles','C:/Program Files'))/'k6/k6.exe')
if not Path(k6).exists(): raise SystemExit('k6 executable not found.')
rows=[]

def run(compose,*args):
    p=subprocess.run(compose+list(args),capture_output=True,text=True,encoding='utf8',errors='replace')
    if p.returncode: raise RuntimeError(p.stdout+p.stderr)
    return p.stdout

def helper(action):
    return json.loads(run(base,'exec','-T','app1','php','/tmp/lab3-benchmark-fixture.php',action))

def drain():
    deadline=time.monotonic()+60
    while time.monotonic()<deadline:
        run(base,'exec','-T','app1','php','artisan','analytics:flush')
        state=helper('queue-state')
        if all(state[key]==0 for key in ['pending','processing','queued_jobs']): return
        time.sleep(2)
    raise RuntimeError('Analytics did not drain within 60 seconds; stop comparison and inspect worker.')

try:
    print('Starting complete stack and enabling only the benchmark create-rate limit...',flush=True)
    off=base+['-f','docker-compose.lab3-benchmark-off.yml']
    run(off,'up','-d')
    run(base,'cp','./loadtest/lab3-benchmark-fixture.php','app1:/tmp/lab3-benchmark-fixture.php')
    fixture=helper('prepare')
    input_path=out/'input-private.json'
    input_path.write_text(json.dumps(fixture),encoding='utf8')
    print('Isolated fixture ready: 1000 links; token saved only under ignored work directory.',flush=True)
    drain()
    for mode in ['off','on']:
        compose=base+['-f',f'docker-compose.lab3-benchmark-{mode}.yml']
        run(compose,'up','-d','--no-deps','nginx')
        print('Phase:',mode,flush=True)
        deadline=time.monotonic()+15
        while True:
            try:
                req=urllib.request.Request('http://localhost:8080/up')
                opener=urllib.request.build_opener(urllib.request.ProxyHandler({}))
                with opener.open(req,timeout=3) as response:
                    if response.status!=200: raise RuntimeError('Health check failed')
                break
            except Exception:
                if time.monotonic()>deadline: raise
                time.sleep(1)
        # Warm ONLY Laravel's Redis cache, not nginx's micro-cache: fixture prepare
        # already populated Redis for the 10 hot links. Both phases start identically.
        result_path=out/f'{mode}-k6.json'
        args=[k6,'run','--no-color','-e','RATE=200','-e','DURATION=30s',
              '-e','INPUT_FILE='+input_path.as_posix(),'-e','RESULT_FILE='+result_path.as_posix(),
              str(root/'loadtest/lab3-microcache-k6.js')]
        proc=subprocess.Popen(args,stdout=subprocess.PIPE,stderr=subprocess.STDOUT,text=True,encoding='utf8',errors='replace')
        lines=[]
        for line in proc.stdout:
            lines.append(line)
            if 'failed:' in line or 'ERRO' in line or 'WARN' in line:
                print(line.rstrip(),flush=True)
        exit_code=proc.wait()
        (out/f'{mode}-k6.log').write_text(''.join(lines),encoding='utf8')
        if not result_path.exists(): raise RuntimeError('k6 produced no summary; inspect '+str(out))
        metrics=json.loads(result_path.read_text())['metrics']
        def metric(name,key,default=0): return metrics.get(name,{}).get('values',{}).get(key,default)
        redirects=metric('redirect_requests','count')
        backend=metric('backend_redirect_requests','count')
        hits=metric('proxy_hit_requests','count')
        errors=metric('request_errors','rate')
        dropped=metric('dropped_iterations','count')
        row={'microcache':mode,'actual_rps':round(metric('http_reqs','rate'),3),
             'redirects':redirects,'creates':metric('create_requests','count'),
             'backend_redirects':backend,'backend_redirect_rps':round(metric('backend_redirect_requests','rate'),3),
             'cache_hits':hits,'cache_hit_percent':round(hits/redirects*100,2) if redirects else 0,
             'redirect_p99_ms':round(metric('redirect_latency','p(99)'),3),
             'error_rate':errors,'dropped_iterations':dropped,'k6_exit_code':exit_code}
        rows.append(row)
        print(json.dumps(row),flush=True)
        if exit_code or errors or dropped or redirects!=backend+hits:
            raise RuntimeError('Comparison validity check failed; do not claim a performance win.')
        drain()
    (out/'METHOD.txt').write_text('Task 2 part9.js workload adapted only for fixture input, 30s duration, 100 preallocated VUs, seed and instrumentation. '
        '200 total offered RPS, one create per 100 redirects, 80% of redirects target 10 of 1000 links. '
        'Two ordered 30-second phases on same machine; short before/after experiment, not a max-capacity measurement. '
        'Origin request count derived from nginx X-Lab3-Cache: non-HIT redirect responses require backend handling. '
        'Origin RPS in table counts redirects only; API creates bypass cache in both phases. '
        'Cache TTL 3s with collapsing; only synthetic lab3mc_ codes eligible. Initial micro-cache is cold. '
        'Origin Cache-Control is ignored only in this isolated lab; browser no-store header remains. '
        'Create limit temporarily raised to prevent 429 confounding; restored after run. '
        'Analytics drained before/between/after phases. HTTP, no redirect following, same stock Task 2 code path. '
        'Results include process/ramp scheduling overhead; repeats would be required for strong causal/capacity claims.')
finally:
    (out/'comparison.json').write_text(json.dumps(rows,indent=2))
    print('Restoring fixed proxy config, with micro-cache OFF and normal create-rate limit...',flush=True)
    try:
        run(base,'up','-d','--force-recreate','app1','app2','nginx')
    except Exception as exc:
        print('Restore failed; run fixed-proxy compose up manually:',exc,flush=True)
    print('Results:',out,flush=True)

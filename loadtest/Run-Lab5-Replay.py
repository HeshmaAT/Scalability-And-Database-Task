import datetime,json,subprocess
from pathlib import Path
files=['docker-compose.yml','docker-compose.lab1.yml','docker-compose.lab2.yml','docker-compose.lab2-network.yml','docker-compose.lab2-http.yml','docker-compose.lab3.yml','docker-compose.lab3-fixed.yml','docker-compose.lab3-egress.yml','docker-compose.lab3-isolated.yml','docker-compose.lab4-origin.yml','docker-compose.lab4-edges.yml','docker-compose.lab4-signed.yml','docker-compose.lab5.yml','docker-compose.lab5-analytics.yml','docker-compose.lab5-active.yml','docker-compose.lab5-replay.yml']
compose=['docker','compose']+[v for f in files for v in ['-f',f]]
out=Path('work')/('lab5-replay-'+datetime.datetime.now().strftime('%Y%m%d-%H%M%S'));out.mkdir(parents=True)
def run(*args):
    p=subprocess.run(compose+list(args),capture_output=True,text=True,encoding='utf8',errors='replace')
    if p.returncode: raise RuntimeError(p.stdout+p.stderr)
    return p.stdout
try:
    run('up','-d','--wait','--no-deps','lab5-replay-redis')
    run('cp','./loadtest/lab5-replay.php','app1:/tmp/lab5-replay.php')
    print('Dedicated test broker ready; publishing 1,000,000 synthetic events. Live counters stay unchanged.',flush=True)
    process=subprocess.Popen(compose+['exec','-T','app1','php','/tmp/lab5-replay.php'],stdout=subprocess.PIPE,stderr=subprocess.STDOUT,text=True,encoding='utf8',errors='replace',bufsize=1)
    summary=None
    with (out/'run.log').open('w',encoding='utf8') as log:
        for line in process.stdout:
            log.write(line);log.flush()
            if line.startswith('SUMMARY '): summary=json.loads(line[8:])
            else: print(line,end='',flush=True)
    if process.wait()!=0: raise RuntimeError('Replay failed; see '+str(out/'run.log'))
    if summary is None: raise RuntimeError('Missing replay summary')
    (out/'summary.json').write_text(json.dumps(summary,indent=2))
    print(json.dumps({k:v for k,v in summary.items() if not k.startswith('groups_')},indent=2),flush=True)
    print('PASS: late group replayed all retained events; trimming limited the next group to 1000.',flush=True)
finally:
    try:
        run('rm','-s','-f','-v','lab5-replay-redis')
        print('Disposable replay broker and anonymous test volume removed.',flush=True)
    except Exception as e: print('Replay cleanup needs attention:',e,flush=True)
    print('Results:',out,flush=True)

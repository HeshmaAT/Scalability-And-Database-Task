import datetime as dt
import json
from pathlib import Path
import subprocess

files=['docker-compose.yml','docker-compose.lab1.yml','docker-compose.lab2.yml',
       'docker-compose.lab2-network.yml','docker-compose.lab2-http.yml',
       'docker-compose.lab3.yml','docker-compose.lab3-broken.yml']
compose=['docker','compose']
for f in files: compose+=['-f',f]
out=Path('work')/('lab3-broken-'+dt.datetime.now().strftime('%Y%m%d-%H%M%S'))
out.mkdir(parents=True)
evidence=[]
def request(service,path):
    cmd=compose+['exec','-T',service,'curl','--noproxy','*','--cacert','/lab2/rootCA.pem',
        '--resolve','go.brand.test:443:172.30.53.10','--connect-timeout','3','--max-time','10',
        '-H','Accept: application/json','-sS','-w','\n%{http_code}','https://go.brand.test'+path]
    r=subprocess.run(cmd,capture_output=True,text=True)
    if r.returncode: raise RuntimeError(r.stdout+r.stderr)
    body,status=r.stdout.rsplit('\n',1)
    try:
        data=json.loads(body)
    except json.JSONDecodeError:
        data={'non_json_body':body[:500]}
    row={'client_container':service,'path':path,'status':int(status),'body':data}
    evidence.append(row)
    return row
try:
    clients=[request(s,'/api/lab3/client') for s in ['dns-client','lab2-client']]
    for row in clients: print(json.dumps(row),flush=True)
    if any(r['status']!=200 for r in clients): raise RuntimeError('Diagnostic endpoint failed')
    a,b=[r['body'] for r in clients]
    if not (a['client_ip']==b['client_ip'] and a['visitor_hash']==b['visitor_hash'] and
            a['is_secure'] is False and b['is_secure'] is False):
        raise RuntimeError('Broken state was not reproduced; do not claim success.')
    print('PROVED: two different containers collapse to the same client IP and visitor hash; HTTPS seen as false.',flush=True)
    # Clear only the anonymous limiter bucket for this deliberately broken proxy IP.
    php="\\Illuminate\\Support\\Facades\\RateLimiter::clear(sha1('|172.30.53.10'));"
    reset=subprocess.run(compose+['exec','-T','app1','php','artisan','tinker','--execute='+php],capture_output=True,text=True)
    if reset.returncode:
        raise RuntimeError('Could not reset the diagnostic bucket: '+reset.stdout+reset.stderr)
    for i,service in enumerate(['dns-client']*3+['lab2-client'],1):
        row=request(service,'/api/lab3/limited')
        print(f'Rate request {i}, {service}: HTTP {row["status"]}',flush=True)
        if row['status']!=(200 if i<=3 else 429):
            raise RuntimeError('Rate test did not match expected fresh bucket. Wait 60 seconds before retrying.')
    print('PROVED: IP-based diagnostic limiter bucket is shared. Production create-links limiter remains per user.',flush=True)
finally:
    (out/'evidence.json').write_text(json.dumps(evidence,indent=2),encoding='utf8')
    print('Results:',out,flush=True)

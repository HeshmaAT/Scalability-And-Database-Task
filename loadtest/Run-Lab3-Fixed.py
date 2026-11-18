import datetime as dt
import json
from pathlib import Path
import subprocess

files=['docker-compose.yml','docker-compose.lab1.yml','docker-compose.lab2.yml',
       'docker-compose.lab2-network.yml','docker-compose.lab2-http.yml',
       'docker-compose.lab3.yml','docker-compose.lab3-fixed.yml']
compose=['docker','compose']+[x for f in files for x in ['-f',f]]
out=Path('work')/('lab3-fixed-'+dt.datetime.now().strftime('%Y%m%d-%H%M%S'))
out.mkdir(parents=True)
evidence=[]

def request(service,path,spoof=False):
    args=compose+['exec','-T',service,'curl','--noproxy','*','--cacert','/lab2/rootCA.pem',
        '--resolve','go.brand.test:443:172.30.53.10','--connect-timeout','3','--max-time','10',
        '-H','Accept: application/json','-sS','-w','\n%{http_code}']
    if spoof:
        args+=['-H','X-Forwarded-For: 198.51.100.77','-H','X-Forwarded-Proto: http',
               '-H','X-Forwarded-Host: evil.example','-H','Forwarded: for=198.51.100.77;proto=http;host=evil.example']
    r=subprocess.run(args+['https://go.brand.test'+path],capture_output=True,text=True)
    if r.returncode: raise RuntimeError(r.stdout+r.stderr)
    body,status=r.stdout.rsplit('\n',1)
    try: data=json.loads(body)
    except json.JSONDecodeError: data={'non_json_response':body[:500]}
    row={'client_container':service,'path':path,'spoof':spoof,'status':int(status),'body':data}
    evidence.append(row)
    return row

try:
    identities=[]
    for service,expected in [('dns-client','172.30.53.20'),('lab2-client','172.30.53.30')]:
        for spoof in [False,True]:
            row=request(service,'/api/lab3/client',spoof)
            print(json.dumps(row),flush=True)
            body=row['body']
            if not (row['status']==200 and body['client_ip']==expected and body['is_secure'] is True
                    and body['host']=='go.brand.test' and body['forwarded_for']==expected
                    and body['forwarded_proto']=='https'):
                raise RuntimeError('Identity or nginx header sanitization failed.')
            if not spoof: identities.append(body)
            elif body['visitor_hash']!=identities[-1]['visitor_hash']:
                raise RuntimeError('Spoof changed visitor hash.')
    if identities[0]['visitor_hash']==identities[1]['visitor_hash']:
        raise RuntimeError('Distinct clients still share visitor hash.')
    print('PASS: distinct IPs/hashes, HTTPS restored, forged headers overwritten.',flush=True)
    php="foreach (['172.30.53.20','172.30.53.30'] as $ip) { \\Illuminate\\Support\\Facades\\RateLimiter::clear(sha1('|'.$ip)); }"
    r=subprocess.run(compose+['exec','-T','app1','php','artisan','tinker','--execute='+php],capture_output=True,text=True)
    if r.returncode: raise RuntimeError(r.stdout+r.stderr)
    cases=[('dns-client',False,200)]*3+[('dns-client',True,429),('lab2-client',False,200),('lab2-client',True,200)]
    for i,(service,spoof,expected) in enumerate(cases,1):
        row=request(service,'/api/lab3/limited',spoof)
        print(f'Rate request {i}: {service}, spoof={spoof}, HTTP {row["status"]}',flush=True)
        if row['status']!=expected:
            raise RuntimeError('Rate-limit isolation or spoof-resistance failed.')
    print('PASS: first client stays limited despite spoof; second has a separate bucket.',flush=True)
finally:
    (out/'evidence.json').write_text(json.dumps(evidence,indent=2),encoding='utf8')
    print('Results:',out,flush=True)

import datetime as dt
import json
from pathlib import Path
import subprocess

files=['docker-compose.yml','docker-compose.lab1.yml','docker-compose.lab2.yml',
       'docker-compose.lab2-network.yml','docker-compose.lab2-http.yml','docker-compose.lab3.yml',
       'docker-compose.lab3-fixed.yml','docker-compose.lab3-egress.yml','docker-compose.lab3-isolated.yml']
compose=['docker','compose']+[x for f in files for x in ['-f',f]]
out=Path('work')/('lab3-isolation-'+dt.datetime.now().strftime('%Y%m%d-%H%M%S'))
out.mkdir(parents=True)

def run(*args):
    p=subprocess.run(list(args),capture_output=True,text=True,encoding='utf8',errors='replace')
    if p.returncode: raise RuntimeError(p.stdout+p.stderr)
    return p.stdout

topology={}
for service in ['app1','app2','worker','scheduler','squid']:
    cid=run(*compose,'ps','-q',service).strip()
    if not cid: raise RuntimeError(service+' is not running')
    data=json.loads(run('docker','inspect',cid))[0]
    networks=data['NetworkSettings']['Networks']
    details={name:json.loads(run('docker','network','inspect',name))[0]['Internal'] for name in networks}
    topology[service]=details
    if service!='squid' and (len(details)!=1 or not all(details.values())):
        raise RuntimeError(service+' is not isolated to one internal network: '+str(details))
    if service=='squid' and not (any(details.values()) and any(not v for v in details.values())):
        raise RuntimeError('Squid must connect to internal and egress networks.')
(out/'topology.json').write_text(json.dumps(topology,indent=2))
print('Topology verified: app/worker/scheduler internal-only; Squid has egress.',flush=True)

# Control: the same public TCP destination is reachable from an external lab network.
control=run(*compose,'exec','-T','lab2-client','python3','-c',
            'import socket; s=socket.create_connection(("1.1.1.1",443),3); s.close(); print("CONTROL_CONNECTED")')
if 'CONTROL_CONNECTED' not in control: raise RuntimeError('External network control failed.')
(out/'external-control.txt').write_text(control)
print('Control passed: lab2-client CAN connect directly to 1.1.1.1:443.',flush=True)

results={}
for service in ['app1','app2']:
    run(*compose,'cp','./loadtest/lab3-isolation-probe.php',service+':/tmp/lab3-isolation-probe.php')
    text=run(*compose,'exec','-T',service,'php','/tmp/lab3-isolation-probe.php')
    rows=json.loads(text)
    results[service]=rows
    print(service+':\n'+json.dumps(rows,indent=2),flush=True)
    (out/'results.json').write_text(json.dumps(results,indent=2))
    if not all(row['passed'] for row in rows):
        raise RuntimeError('Isolation or global policy check failed for '+service)
logs=run(*compose,'exec','-T','squid','tail','-n','40','/var/log/squid/access.log')
(out/'squid-access.log').write_text(logs)
print('PASS: global Laravel HTTP policy works; direct public TCP and HTTP bypass attempts failed from both app instances.',flush=True)
print('Results:',out,flush=True)

import csv
import datetime as dt
import json
from pathlib import Path
import re
import subprocess
import sys
import time

code = sys.argv[1] if len(sys.argv) > 1 else 'iQhQTMUo6M'
if not re.fullmatch(r'[A-Za-z0-9_-]+', code):
    raise SystemExit('Invalid short code.')
compose = ['docker', 'compose', '-f', 'docker-compose.yml', '-f', 'docker-compose.lab1.yml',
           '-f', 'docker-compose.lab2.yml', '-f', 'docker-compose.lab2-network.yml']
out = Path('work')/('lab2-network-'+dt.datetime.now().strftime('%Y%m%d-%H%M%S'))
out.mkdir(parents=True)

def run(*args, allow_fail=False):
    proc = subprocess.run(compose+list(args), capture_output=True, text=True)
    if proc.returncode and not allow_fail:
        raise RuntimeError(proc.stdout+proc.stderr)
    return proc.stdout+proc.stderr

def client(*args, **kwargs):
    return run('exec', '-T', 'lab2-client', *args, **kwargs)

rows = []
try:
    client('sh', '-c', 'echo nameserver 172.30.53.3 > /etc/resolv.conf')
    version = client('curl', '--version')
    (out/'curl-version.txt').write_text(version)
    print(version.splitlines()[0], flush=True)
    route = client('ip', '-j', 'route', 'get', '172.30.53.10')
    dev = json.loads(route)[0]['dev']
    url = 'https://go.brand.test/'+code
    base = ['curl', '--http1.1', '--noproxy', '*', '--cacert', '/lab2/rootCA.pem',
            '--connect-timeout', '10', '--max-time', '15', '-sS']
    status = client(*(base+['-o','/dev/null','-w','%{http_code}',url])).strip()
    if status != '302':
        raise RuntimeError('Preflight expected verified HTTPS 302, got '+status)
    print('Preflight: certificate verified, DNS resolved, redirect=302.', flush=True)
    for delay in [0, 50, 150]:
        if delay:
            client('tc','qdisc','replace','dev',dev,'root','netem','delay',str(delay)+'ms')
        else:
            client('tc','qdisc','del','dev',dev,'root',allow_fail=True)
        qdisc = client('tc','qdisc','show','dev',dev)
        ping = client('ping','-n','-c','3','-W','3','172.30.53.10')
        (out/f'delay-{delay}-qdisc.txt').write_text(qdisc)
        (out/f'delay-{delay}-ping.txt').write_text(ping)
        match = re.search(r'=\s*[\d.]+/([\d.]+)/',ping)
        if not match:
            raise RuntimeError('Cannot verify RTT using ping; see saved evidence.')
        measured_rtt = float(match.group(1))
        for sample in range(1, 6):
            text = client(*(base+['-o','/dev/null','-w','%{json}\n',url,'-o','/dev/null',url]))
            (out/f'delay-{delay}-sample-{sample}.jsonl').write_text(text)
            results = [json.loads(line) for line in text.splitlines() if line.startswith('{')]
            if len(results) != 2:
                raise RuntimeError('Expected two curl measurements.')
            for index, item in enumerate(results):
                if int(item['http_code']) != 302 or int(item['num_connects']) != (1 if index == 0 else 0):
                    raise RuntimeError('Unexpected status or no connection reuse; raw result saved.')
                dns = item['time_namelookup']*1000
                tcp = max(0,(item['time_connect']-item['time_namelookup'])*1000) if index == 0 else 0
                tls = max(0,(item['time_appconnect']-item['time_connect'])*1000) if index == 0 else 0
                wait = (item['time_starttransfer']-(item['time_appconnect'] if index == 0 else item['time_pretransfer']))*1000
                rows.append({'added_egress_delay_ms':delay,'measured_ping_rtt_ms':measured_rtt,
                    'sample':sample,'connection':'fresh' if index == 0 else 'reused',
                    'dns_ms':round(dns,3),'tcp_ms':round(tcp,3),'tls_ms':round(tls,3),
                    'request_to_first_byte_ms':round(wait,3),'ttfb_ms':round(item['time_starttransfer']*1000,3),
                    'total_ms':round(item['time_total']*1000,3),'new_connections':item['num_connects']})
        print(f'Completed added delay {delay}ms; measured ping RTT {measured_rtt}ms',flush=True)
    import statistics
    summary = []
    for delay in [0,50,150]:
        for connection in ['fresh','reused']:
            group = [r for r in rows if r['added_egress_delay_ms']==delay and r['connection']==connection]
            summary.append({'added_delay_ms':delay,'connection':connection, 'ping_rtt_ms':group[0]['measured_ping_rtt_ms'],
                **{k:round(statistics.median(r[k] for r in group),3) for k in
                   ['dns_ms','tcp_ms','tls_ms','request_to_first_byte_ms','ttfb_ms','total_ms']}})
    (out/'summary.json').write_text(json.dumps(summary,indent=2))
    print(json.dumps(summary,indent=2),flush=True)
    (out/'METHOD.txt').write_text('Five fresh/reused pairs per condition, same curl process per pair, HTTP/1.1, no redirect following, CA verification enabled. '
        'CoreDNS resolves the name; no --resolve. DNS may hit resolver cache. '
        'tc netem applies 0/50/150ms to client EGRESS only. This adds approximately that amount to RTT, not twice it; actual ping RTT recorded. '
        'TLS version/session resumption not held fixed: fresh means new TCP, not proof of a full non-resumed TLS handshake. '
        'request_to_first_byte includes request/response network delay, PHP queueing and execution; it is NOT pure server execution time. '
        'Host curl and container curl results have different TLS backends, so compare conditions within this run. '
        'Use nginx request_time/upstream_response_time instrumentation if pure server-side timing is needed. '
        'No permanent Docker or host network settings changed; netem removed on normal completion/failure.')
finally:
    if 'dev' in globals():
        client('tc','qdisc','del','dev',dev,'root',allow_fail=True)
    if rows:
        with (out/'measurements.csv').open('w',newline='') as f:
            writer=csv.DictWriter(f,fieldnames=list(rows[0]));writer.writeheader();writer.writerows(rows)
    print('Results:',out,flush=True)

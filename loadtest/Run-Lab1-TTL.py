import argparse
import datetime as dt
import json
from pathlib import Path
import re
import subprocess
import time

parser = argparse.ArgumentParser()
parser.add_argument('--ttls', nargs='+', type=int, choices=[5, 60, 3600], default=[60, 5])
parser.add_argument('--check-only', action='store_true')
args = parser.parse_args()
root = Path.cwd()
zone = root / 'docker/dns-lab/auth/brand.test.zone'
helper = root / 'docker/dns-lab/ttl-client.py'
if not zone.exists() or not helper.exists():
    raise SystemExit('Run from the project root after extracting the package.')
compose = ['docker', 'compose', '-f', 'docker-compose.yml', '-f', 'docker-compose.lab1.yml']
out = root / 'work' / ('lab1-ttl-' + dt.datetime.now().strftime('%Y%m%d-%H%M%S'))
out.mkdir(parents=True)
original = zone.read_bytes()
serial = int(re.search(r'(?m)^\s*(\d+)\s*;\s*serial', original.decode()).group(1))

def run(*commands):
    return subprocess.run(compose + list(commands), check=True, capture_output=True, text=True).stdout

def set_zone(ttl, ip):
    global serial
    serial += 1
    text = original.decode()
    text = re.sub(r'(?m)^\$TTL\s+\d+', '$TTL ' + str(ttl), text)
    text = re.sub(r'(?m)^\s*\d+(\s*;\s*serial.*)$', lambda m: '    ' + str(serial) + m.group(1), text)
    text = re.sub(r'(?m)^go\s+\d+\s+IN\s+A\s+\S+\s*$', f'go {ttl} IN A {ip}', text)
    zone.write_text(text, encoding='ascii', newline='\n')

rows = []
try:
    print(run('cp', str(helper), 'dns-client:/tmp/ttl-client.py'), flush=True)
    for ttl in args.ttls:
        print(f'\nTTL={ttl}: 100 simulated clients, one query/client/second.', flush=True)
        set_zone(ttl, '172.30.53.10')
        deadline = time.monotonic() + 20
        while True:
            answer = run('exec', '-T', 'dns-client', 'dig', '@172.30.53.2', 'go.brand.test', 'A', '+noall', '+answer')
            if re.search(rf'\s{ttl}\s+IN\s+A\s+172\.30\.53\.10', answer):
                break
            if time.monotonic() > deadline:
                raise RuntimeError('Authoritative did not reload the baseline zone.')
            time.sleep(1)
        run('restart', 'dns-resolver')
        time.sleep(2)
        if args.check_only:
            answer = run('exec', '-T', 'dns-client', 'dig', '@172.30.53.3', 'go.brand.test', 'A', '+noall', '+answer')
            print(answer, flush=True)
            if not re.search(rf'\s{ttl}\s+IN\s+A\s+172\.30\.53\.10', answer):
                raise RuntimeError('Effective resolver TTL is wrong.')
            print('Preflight passed. No long experiment was run.', flush=True)
            continue
        process = subprocess.Popen(compose + ['exec', '-T', 'dns-client', 'python3', '-u', '/tmp/ttl-client.py', str(ttl)],
                                   stdout=subprocess.PIPE, stderr=subprocess.STDOUT, text=True)
        result = None
        change_offset = None
        transcript = []
        started = None
        for line in process.stdout:
            transcript.append(line)
            print(line, end='', flush=True)
            if line.startswith('{'):
                ready = json.loads(line)
                if ready.get('ready'):
                    started = dt.datetime.fromisoformat(ready['started_utc'])
                    change_offset = (dt.datetime.now(dt.timezone.utc)-started).total_seconds()
                    set_zone(ttl, '172.30.53.11')
            if line.startswith('RESULT '):
                result = json.loads(line[7:])
        if process.wait() or result is None:
            raise RuntimeError('DNS clients failed; see console output.')
        until = started + dt.timedelta(seconds=60)
        logs = run('logs', '--no-color', '--timestamps', '--since', started.isoformat(), '--until', until.isoformat(), 'dns-auth')
        # Only A queries for the tested name from the forwarding resolver.
        forwarded = sum('172.30.53.3:' in line and 'A IN go.brand.test.' in line for line in logs.splitlines())
        result.update({'zone_change_from_start_s': change_offset,
                       'first_new_after_zone_write_s': None if result['first_new_from_start_s'] is None else round(result['first_new_from_start_s']-change_offset, 3),
                       'authoritative_queries_first_60s': forwarded,
                       'authoritative_window_s': 60,
                       'note': '100 logical clients share one resolver. 100 queries/s intended; check late_rounds. Transition includes zone reload and up to 1s polling granularity. .11 is DNS-only, not a second web server.'})
        (out / f'ttl-{ttl}.json').write_text(json.dumps(result, indent=2), encoding='utf8')
        (out / f'ttl-{ttl}-auth.log').write_text(logs, encoding='utf8')
        (out / f'ttl-{ttl}-client.log').write_text(''.join(transcript), encoding='utf8')
        rows.append({k: result[k] for k in ['ttl', 'first_new_after_zone_write_s', 'authoritative_queries_first_60s', 'errors', 'late_rounds']})
        print('Measured:', json.dumps(rows[-1]), flush=True)
finally:
    # Restore original records with a NEW serial so CoreDNS notices the restoration.
    serial += 1
    text = re.sub(r'(?m)^\s*\d+(\s*;\s*serial.*)$', lambda m: '    ' + str(serial) + m.group(1), original.decode())
    zone.write_text(text, encoding='ascii', newline='\n')
    try:
        time.sleep(2)
        run('restart', 'dns-resolver')
    except Exception as exc:
        print('Resolver reset failed:', exc)
    (out / 'comparison.json').write_text(json.dumps(rows, indent=2), encoding='utf8')
    print('\nResults:', out)
    print('Original DNS records restored; only the SOA serial was advanced.')

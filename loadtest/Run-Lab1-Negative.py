import datetime as dt
import json
from pathlib import Path
import re
import subprocess
import time
import uuid

root = Path.cwd()
zone = root / 'docker/dns-lab/auth/brand.test.zone'
if not zone.exists():
    raise SystemExit('Run this script from the project root.')
compose = ['docker', 'compose', '-f', 'docker-compose.yml', '-f', 'docker-compose.lab1.yml']
original = zone.read_text(encoding='ascii')
serial_match = re.search(r'(?m)^\s*(\d+)\s*;\s*serial', original)
if not serial_match:
    raise SystemExit('Cannot locate SOA serial; no changes made.')
serial = int(serial_match.group(1))
label = 'negative-' + uuid.uuid4().hex[:10]
name = label + '.brand.test'
out = root / 'work' / ('lab1-negative-' + dt.datetime.now().strftime('%Y%m%d-%H%M%S'))
out.mkdir(parents=True)
evidence = []

def dig(server, qtype='A'):
    result = subprocess.run(compose + ['exec', '-T', 'dns-client', 'dig', '@'+server,
        name if qtype == 'A' else 'brand.test', qtype, '+time=2', '+tries=1', '+noall', '+comments', '+answer', '+authority'],
        capture_output=True, text=True, check=True)
    if not re.search(r'status: (NOERROR|NXDOMAIN)', result.stdout):
        raise RuntimeError('DNS query failed: ' + result.stdout + result.stderr)
    evidence.append({'at_utc': dt.datetime.now(dt.timezone.utc).isoformat(),
                     'server': server, 'type': qtype, 'output': result.stdout})
    return result.stdout

def write_zone(text, serial):
    text = re.sub(r'(?m)^\s*\d+(\s*;\s*serial.*)$', lambda m: '    '+str(serial)+m.group(1), text)
    zone.write_text(text, encoding='ascii', newline='\n')

try:
    print('Test name:', name, flush=True)
    print('Authoritative SOA:\n'+dig('172.30.53.2', 'SOA'), flush=True)
    negative = dig('172.30.53.3')
    primed_at = time.monotonic()
    print('1. Resolver BEFORE creating the record:\n'+negative, flush=True)
    if 'status: NXDOMAIN' not in negative:
        raise RuntimeError('Expected NXDOMAIN for fresh test name.')
    soa = re.search(r'(?m)^brand\.test\.\s+(\d+)\s+IN\s+SOA\s+(.*)$', negative)
    if not soa:
        raise RuntimeError('Negative answer has no SOA TTL; cannot validate experiment.')
    negative_ttl = int(soa.group(1))
    if not 1 <= negative_ttl <= 60:
        raise RuntimeError(f'Unexpected negative TTL {negative_ttl}; stopping before waiting.')
    serial += 1
    write_zone(original.rstrip()+f'\n{label} 60 IN A 172.30.53.10\n', serial)
    written_at = time.monotonic()
    deadline = written_at + 12
    while True:
        authoritative = dig('172.30.53.2')
        if re.search(r'\sIN\s+A\s+172\.30\.53\.10', authoritative):
            break
        if time.monotonic() >= deadline:
            raise RuntimeError('Authoritative zone did not reload within 12 seconds.')
        time.sleep(0.5)
    print('2. Authoritative AFTER creating the record:\n'+authoritative, flush=True)
    cached = dig('172.30.53.3')
    stale_seen = 'status: NXDOMAIN' in cached
    print('3. Resolver while authoritative already has the record:\n'+cached, flush=True)
    if not stale_seen:
        raise RuntimeError('No stale NXDOMAIN observed; do not mark demonstration as passed.')
    deadline = primed_at + negative_ttl + 12
    while True:
        answer = dig('172.30.53.3')
        elapsed = time.monotonic()-primed_at
        if re.search(r'\sIN\s+A\s+172\.30\.53\.10', answer):
            print('4. Resolver AFTER negative cache expired:\n'+answer, flush=True)
            break
        print(f'{elapsed:.2f}s: still NXDOMAIN', flush=True)
        if time.monotonic() > deadline:
            raise RuntimeError('Negative cache did not expire within the expected interval.')
        time.sleep(1)
    result = {'name': name, 'negative_answer_soa_ttl': negative_ttl,
              'observed_stale_nxdomain_after_authoritative_update': stale_seen,
              'first_positive_from_negative_prime_seconds': round(elapsed, 3),
              'first_positive_after_zone_write_seconds': round(time.monotonic()-written_at, 3),
              'note': 'Host orchestration and one-second polling add measurement overhead; raw replies saved.'}
    (out/'result.json').write_text(json.dumps(result, indent=2), encoding='utf8')
    print('Measured:', json.dumps(result), flush=True)
finally:
    serial += 1
    write_zone(original, serial)
    (out/'dns-evidence.json').write_text(json.dumps(evidence, indent=2), encoding='utf8')
    print('Original zone records restored with an advanced SOA serial.', flush=True)
    print('Results:', out, flush=True)

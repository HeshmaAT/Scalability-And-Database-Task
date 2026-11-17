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
    raise SystemExit('Run from the project root.')
compose = ['docker', 'compose', '-f', 'docker-compose.yml', '-f', 'docker-compose.lab1.yml']
original = zone.read_text(encoding='ascii')
serial = int(re.search(r'(?m)^\s*(\d+)\s*;\s*serial', original).group(1))
label = 'layers-' + uuid.uuid4().hex[:10]
name = label + '.brand.test'
out = root / 'work' / ('lab1-cache-layers-' + dt.datetime.now().strftime('%Y%m%d-%H%M%S'))
out.mkdir(parents=True)
evidence = []
client_cache = {}

def command(*args):
    result = subprocess.run(compose + list(args), capture_output=True, text=True)
    if result.returncode:
        raise RuntimeError(result.stdout + result.stderr)
    return result.stdout

def query(server):
    output = command('exec', '-T', 'dns-client', 'dig', '@'+server, name, 'A', '+time=2', '+tries=1', '+noall', '+answer')
    match = re.search(r'\s(\d+)\s+IN\s+A\s+(\d+\.\d+\.\d+\.\d+)', output)
    if not match:
        raise RuntimeError('No A answer: ' + output)
    evidence.append({'server': server, 'reply': output})
    return match.group(2), int(match.group(1))

def write_record(ip):
    global serial
    serial += 1
    text = re.sub(r'(?m)^\s*\d+(\s*;\s*serial.*)$', lambda m: '    '+str(serial)+m.group(1), original)
    zone.write_text(text.rstrip()+f'\n{label} 60 IN A {ip}\n', encoding='ascii', newline='\n')
    deadline = time.monotonic()+12
    while time.monotonic() < deadline:
        try:
            answer, _ = query('172.30.53.2')
            if answer == ip:
                return
        except RuntimeError:
            pass
        time.sleep(0.5)
    raise RuntimeError('Authoritative zone did not reload.')

def client_lookup():
    if name in client_cache and client_cache[name]['expires'] > time.monotonic():
        return client_cache[name]['ip'], 'simulated client cache'
    ip, ttl = query('172.30.53.3')
    client_cache[name] = {'ip': ip, 'expires': time.monotonic()+ttl}
    return ip, 'real CoreDNS resolver'

def step(title, expected):
    ip, source = client_lookup()
    print(f'{title}: {ip} [{source}]', flush=True)
    evidence.append({'step': title, 'ip': ip, 'source': source, 'expected': expected})
    if ip != expected:
        raise RuntimeError('Unexpected result; demonstration not passed.')

try:
    print('Client/application cache is simulated in Python; CoreDNS cache is real.', flush=True)
    write_record('172.30.53.10')
    step('1. Prime both caches', '172.30.53.10')
    write_record('172.30.53.11')
    print('2. Authoritative now returns 172.30.53.11', flush=True)
    client_cache.clear()
    step('3. Flush CLIENT ONLY: still old IP from resolver', '172.30.53.10')
    command('restart', 'dns-resolver')
    time.sleep(2)
    resolver_ip, _ = query('172.30.53.3')
    print('4. After resolver restart, DIRECT resolver answer: '+resolver_ip, flush=True)
    if resolver_ip != '172.30.53.11':
        raise RuntimeError('Resolver did not return new IP after flush.')
    step('5. Client STILL returns old IP from its own cache', '172.30.53.10')
    client_cache.clear()
    step('6. Flush CLIENT too: new IP end to end', '172.30.53.11')
    print('PASS: each stale layer must expire or be flushed.', flush=True)
finally:
    serial += 1
    text = re.sub(r'(?m)^\s*\d+(\s*;\s*serial.*)$', lambda m: '    '+str(serial)+m.group(1), original)
    zone.write_text(text, encoding='ascii', newline='\n')
    (out/'evidence.json').write_text(json.dumps(evidence, indent=2), encoding='utf8')
    print('Original zone records restored; SOA serial advanced.', flush=True)
    print('Results:', out, flush=True)

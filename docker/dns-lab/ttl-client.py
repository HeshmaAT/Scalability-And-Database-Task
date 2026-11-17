import concurrent.futures
import datetime
import json
import secrets
import socket
import struct
import sys
import time

ttl = int(sys.argv[1])
server = ('172.30.53.3', 53)

def query(_=0):
    try:
        ident = secrets.randbelow(65536)
        name = b''.join(bytes([len(s)]) + s.encode() for s in 'go.brand.test'.split('.')) + b'\0'
        packet = struct.pack('!HHHHHH', ident, 0x100, 1, 0, 0, 0) + name + struct.pack('!HH', 1, 1)
        with socket.socket(socket.AF_INET, socket.SOCK_DGRAM) as sock:
            sock.settimeout(2)
            sock.sendto(packet, server)
            data = sock.recv(4096)
        rid, flags, qd, an, _, _ = struct.unpack_from('!HHHHHH', data)
        if rid != ident or flags & 15:
            raise ValueError('DNS response error')
        def skip_name(pos):
            while data[pos]:
                if data[pos] & 0xc0 == 0xc0:
                    return pos + 2
                pos += 1 + data[pos]
            return pos + 1
        pos = 12
        for _ in range(qd):
            pos = skip_name(pos) + 4
        for _ in range(an):
            pos = skip_name(pos)
            typ, cls, remaining, size = struct.unpack_from('!HHIH', data, pos)
            pos += 10
            if typ == 1 and cls == 1 and size == 4:
                return socket.inet_ntoa(data[pos:pos+4]), remaining, time.monotonic()
            pos += size
        raise ValueError('No A answer')
    except Exception:
        return 'ERROR', None, time.monotonic()

ip, remaining, _ = query()
if ip != '172.30.53.10':
    raise SystemExit('Priming failed: expected original IP, got ' + ip)
if remaining != ttl:
    raise SystemExit(f'Wrong effective TTL: expected {ttl}, received {remaining}. Stopping before load.')
primed_remaining = remaining
start = time.monotonic()
started = datetime.datetime.now(datetime.timezone.utc).isoformat()
print(json.dumps({'ready': True, 'started_utc': started, 'primed_ttl': remaining}), flush=True)
first_new = None
all_new = None
errors = 0
requests = 0
late_rounds = 0
rounds = []
duration = max(65, ttl + 8)
with concurrent.futures.ThreadPoolExecutor(max_workers=100) as pool:
    for second in range(duration):
        delay = start + second - time.monotonic()
        if delay > 0:
            time.sleep(delay)
        elif delay < -1:
            late_rounds += 1
        answers = list(pool.map(query, range(100)))
        counts = {}
        for ip, remaining, at in answers:
            counts[ip] = counts.get(ip, 0) + 1
            if ip == '172.30.53.11' and first_new is None:
                first_new = at - start
        errors += counts.get('ERROR', 0)
        requests += 100
        if counts.get('172.30.53.11') == 100 and all_new is None:
            all_new = time.monotonic() - start
        rounds.append({'second': second, 'answers': counts})
        if second % 10 == 0:
            print('Progress ' + str(second) + 's ' + json.dumps(counts), flush=True)
print('RESULT ' + json.dumps({'ttl': ttl, 'started_utc': started, 'primed_ttl': primed_remaining,
      'first_new_from_start_s': first_new, 'all_100_new_from_start_s': all_new,
      'requests': requests, 'errors': errors, 'late_rounds': late_rounds,
      'duration_s': time.monotonic()-start, 'rounds': rounds}), flush=True)

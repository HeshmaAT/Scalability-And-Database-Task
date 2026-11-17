import datetime as dt
import json
from pathlib import Path
import re
import subprocess
import sys

code=sys.argv[1] if len(sys.argv)>1 else 'iQhQTMUo6M'
if not re.fullmatch(r'[A-Za-z0-9_-]+',code):
    raise SystemExit('Invalid code')
compose=['docker','compose','-f','docker-compose.yml','-f','docker-compose.lab1.yml','-f','docker-compose.lab2.yml',
         '-f','docker-compose.lab2-network.yml','-f','docker-compose.lab2-http.yml']
out=Path('work')/('lab2-http-'+dt.datetime.now().strftime('%Y%m%d-%H%M%S'))
out.mkdir(parents=True)
proc=subprocess.Popen(compose+['exec','-T','lab2-client','node','/lab2/http-benchmark.mjs',code],
                      stdout=subprocess.PIPE,stderr=subprocess.STDOUT,text=True)
lines=[];result=None
for line in proc.stdout:
    lines.append(line)
    if line.startswith('RESULT '):
        result=json.loads(line[7:])
        print(json.dumps(result['summary'],indent=2),flush=True)
    else:
        print(line,end='',flush=True)
(out/'raw.log').write_text(''.join(lines),encoding='utf8')
if proc.wait() or result is None:
    raise SystemExit('Comparison did not pass. Evidence saved at '+str(out))
(out/'results.json').write_text(json.dumps(result,indent=2),encoding='utf8')
print('Results:',out)

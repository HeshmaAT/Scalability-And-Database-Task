import datetime
import hashlib
import json
from pathlib import Path
import subprocess
import urllib.request
import urllib.error
import urllib.parse

files=['docker-compose.yml','docker-compose.lab1.yml','docker-compose.lab2.yml','docker-compose.lab2-network.yml','docker-compose.lab2-http.yml','docker-compose.lab3.yml','docker-compose.lab3-fixed.yml','docker-compose.lab3-egress.yml','docker-compose.lab3-isolated.yml','docker-compose.lab4-origin.yml','docker-compose.lab4-edges.yml','docker-compose.lab4-signed.yml']
compose=['docker','compose']+[v for f in files for v in ['-f',f]]
out=Path('work')/('lab4-lifecycle-'+datetime.datetime.now().strftime('%Y%m%d-%H%M%S'))
out.mkdir(parents=True)
rows=[]
def run(*args):
    p=subprocess.run(compose+list(args),capture_output=True,text=True,encoding='utf8',errors='replace')
    if p.returncode: raise RuntimeError(p.stdout+p.stderr)
    return p.stdout
def fixture(action,ident=None):
    args=['exec','-T','app1','php','/tmp/lab4-lifecycle.php',action]
    if ident: args.append(str(ident))
    return json.loads(run(*args))
opener=urllib.request.build_opener(urllib.request.ProxyHandler({}))
def request(stage,url,expected,expected_hash=None):
    parsed=urllib.parse.urlsplit(url)
    result=[]
    for edge,port in [('EU',8084),('Asia',8085)]:
        target=f'http://127.0.0.1:{port}'+parsed.path+'?'+parsed.query
        try:
            response=opener.open(target,timeout=20)
        except urllib.error.HTTPError as e: response=e
        with response:
            body=response.read()
            row={'stage':stage,'edge':edge,'status':response.code,'cache':response.headers.get('X-Cache-Status'),'version':response.headers.get('X-Lab4-Cache-Version')}
        assert row['status']==expected,row
        if expected_hash: assert hashlib.sha256(body).hexdigest()==expected_hash,'Image hash mismatch'
        rows.append(row);result.append(row);print(json.dumps(row),flush=True)
    return result
try:
    run('cp','./loadtest/lab4-lifecycle.php','app1:/tmp/lab4-lifecycle.php')
    original=fixture('create')
    (out/'fixture-private.json').write_text(json.dumps(original,indent=2))
    request('initial_cold',original['url'],200,original['sha256'])
    warm=request('initial_warm',original['url'],200,original['sha256'])
    assert all(r['cache']=='HIT' for r in warm)
    regenerated=fixture('regenerate',original['id'])
    assert original['key']!=regenerated['key'],'Object key was reused'
    # Same signed URL still authorizes this resource, but now selects the new object version.
    changed=request('regenerated_same_signed_url',original['url'],200,regenerated['sha256'])
    assert all(r['cache']=='MISS' for r in changed)
    assert all(a['version']!=b['version'] for a,b in zip(warm,changed))
    request('regenerated_warm',original['url'],200,regenerated['sha256'])
    fixture('delete',original['id'])
    request('deleted_while_cached',original['url'],403)
    # Explicit physical purge of the dedicated LAB4 image cache on both lab edges.
    # These cache directories contain only disposable lab QR responses.
    for edge in ['edge-eu','edge-asia']:
        run('exec','-T',edge,'sh','-c','find /var/cache/nginx/lab4 -type f -delete')
        remaining=run('exec','-T',edge,'sh','-c','find /var/cache/nginx/lab4 -type f').strip()
        assert not remaining,'Lab cache purge incomplete'
        print('Purged dedicated LAB4 cache: '+edge,flush=True)
    request('deleted_after_purge',original['url'],403)
    (out/'METHOD.txt').write_text('Isolated fixture only. Real GenerateQrCode job creates a new immutable object key; SVG bytes may remain identical because the encoded URL is unchanged. Same signed resource URL selects new version through per-request metadata authorization. Soft delete denies access on both warm edges before physical purge. Explicit purge removes all disposable LAB4 QR cache files on both edges, not MinIO objects or other application data. This is a lab purge, not a production per-object purge API. Old MinIO versions need a separate retention policy.')
    print('PASS: new version selected on both edges; deleted link denied before and after physical cache purge.',flush=True)
finally:
    (out/'results.json').write_text(json.dumps(rows,indent=2))
    print('Results:',out,flush=True)

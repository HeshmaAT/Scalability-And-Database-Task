import http.client
import json
import socket
import ssl
import sys
import time

code=sys.argv[1]
context=ssl.create_default_context(cafile='/lab2/rootCA.pem')
connection=http.client.HTTPSConnection('go.brand.test',context=context,timeout=3)
# Fixed address only for this controlled probe; SNI/name verification remain go.brand.test.
sock=socket.create_connection(('172.30.53.10',443),timeout=3)
connection.sock=context.wrap_socket(sock,server_hostname='go.brand.test')

def get():
    started=time.time()
    connection.request('GET','/'+code,headers={'Host':'go.brand.test'})
    response=connection.getresponse()
    row={'request_started_unix':started,'response_at_unix':time.time(),
         'status':response.status,'cache':response.getheader('X-Lab3-Cache')}
    response.read()
    return row

try:
    first=get()
    if first['status']!=302 or first['cache']!='MISS':
        raise RuntimeError('Expected cold 302 MISS for new pause fixture: '+str(first))
    print('READY '+json.dumps(first),flush=True)
    deadline=time.monotonic()+12
    while time.monotonic()<deadline:
        row=get()
        print('SAMPLE '+json.dumps(row),flush=True)
        if row['status']==404:
            print('DONE',flush=True)
            break
        if row['status']!=302:
            raise RuntimeError('Unexpected response: '+str(row))
        time.sleep(0.05)
    else:
        raise RuntimeError('No 404 observed within 12 seconds.')
finally:
    connection.close()

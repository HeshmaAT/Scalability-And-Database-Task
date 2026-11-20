import hashlib,hmac,json,sqlite3,time
from http.server import BaseHTTPRequestHandler,HTTPServer
secret=open('/run/webhook-secret').read().strip().encode()
db=sqlite3.connect('/data/receiver.sqlite')
db.executescript('CREATE TABLE IF NOT EXISTS effects(event_id TEXT PRIMARY KEY, digest TEXT); CREATE TABLE IF NOT EXISTS attempts(event_id TEXT PRIMARY KEY, count INTEGER);')
class Handler(BaseHTTPRequestHandler):
    def log_message(self,*args):pass
    def reply(self,status,data):
        body=json.dumps(data).encode();self.send_response(status);self.send_header('Content-Type','application/json');self.send_header('Content-Length',str(len(body)));self.end_headers();self.wfile.write(body)
    def do_GET(self):
        if self.path!='/metrics':return self.reply(404,{})
        self.reply(200,{'effects':dict(db.execute('SELECT event_id,1 FROM effects')),'attempts':dict(db.execute('SELECT event_id,count FROM attempts'))})
    def do_POST(self):
        body=self.rfile.read(int(self.headers.get('Content-Length','0')));timestamp=self.headers.get('X-Webhook-Timestamp','');key=self.headers.get('Idempotency-Key','')
        expected='sha256='+hmac.new(secret,timestamp.encode()+b'.'+body,hashlib.sha256).hexdigest()
        try:valid=abs(time.time()-int(timestamp))<=300 and hmac.compare_digest(expected,self.headers.get('X-Webhook-Signature','')) and json.loads(body)['event_id']==key
        except Exception:valid=False
        if not valid:return self.reply(401,{'verified':False})
        with db:db.execute('INSERT INTO attempts VALUES (?,1) ON CONFLICT(event_id) DO UPDATE SET count=count+1',(key,))
        attempt=db.execute('SELECT count FROM attempts WHERE event_id=?',(key,)).fetchone()[0]
        if self.path=='/always-fail' or (self.path=='/flaky' and attempt<3):return self.reply(503,{'verified':True})
        digest=hashlib.sha256(body).hexdigest();existing=db.execute('SELECT digest FROM effects WHERE event_id=?',(key,)).fetchone()
        if existing and existing[0]!=digest:return self.reply(409,{'conflict':True})
        with db:db.execute('INSERT OR IGNORE INTO effects VALUES (?,?)',(key,digest))
        if self.path=='/lost-ack' and attempt==1:
            self.close_connection=True;return
        self.reply(200,{'verified':True,'duplicate':existing is not None})
HTTPServer(('0.0.0.0',80),Handler).serve_forever()

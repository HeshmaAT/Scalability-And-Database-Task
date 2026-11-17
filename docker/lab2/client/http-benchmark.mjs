import https from 'node:https';
import http2 from 'node:http2';
import fs from 'node:fs';
import {execFileSync} from 'node:child_process';
import {performance} from 'node:perf_hooks';

const origin = 'https://go.brand.test';
const ca = fs.readFileSync('/lab2/rootCA.pem');
const code = process.argv[2] || 'iQhQTMUo6M';
if (!/^[A-Za-z0-9_-]+$/.test(code)) throw Error('Invalid code');
fs.writeFileSync('/etc/resolv.conf', 'nameserver 172.30.53.3\n');
const dev = JSON.parse(execFileSync('ip',['-j','route','get','172.30.53.10'],{encoding:'utf8'}))[0].dev;
const rows = [];
const tc = (...args) => execFileSync('tc',args,{encoding:'utf8'});
function clearDelay() { try { tc('qdisc','del','dev',dev,'root'); } catch {} }

async function measure(protocol, kind) {
  let connections = 0;
  let agent, session;
  const started = performance.now();
  let request;
  if (protocol === 'HTTP/1.1') {
    agent = new https.Agent({keepAlive:true,maxSockets:6,ca,ALPNProtocols:['http/1.1']});
    const create = agent.createConnection.bind(agent);
    agent.createConnection = function(options, callback) {
      connections++;
      return create(options,callback);
    };
    request = path => new Promise((resolve,reject) => {
      const req = https.get(origin+path,{agent},res => {
        if (res.httpVersion !== '1.1') { res.resume(); reject(Error('HTTP/1.1 was not negotiated')); return; }
        const chunks=[];
        res.on('data',chunk=>chunks.push(chunk));
        res.on('end',()=>resolve({status:res.statusCode,headers:res.headers,body:Buffer.concat(chunks)}));
        res.on('error',reject);
      });
      req.setTimeout(15000,()=>req.destroy(Error('Timeout')));
      req.on('error',reject);
    });
  } else {
    session = http2.connect(origin,{ca});
    session.on('error',()=>{});
    await new Promise((resolve,reject)=>{
      session.once('connect',resolve);session.once('error',reject);
    });
    if (session.socket.alpnProtocol !== 'h2') { session.destroy(); throw Error('HTTP/2 not negotiated'); }
    connections=1;
    request = path => new Promise((resolve,reject)=>{
      const req=session.request({':path':path,':method':'GET'});
      const chunks=[];let headers;
      req.setTimeout(15000,()=>req.close(http2.constants.NGHTTP2_CANCEL));
      req.on('response',h=>headers=h);
      req.on('data',chunk=>chunks.push(chunk));
      req.on('end',()=>resolve({status:Number(headers?.[':status']),headers,body:Buffer.concat(chunks)}));
      req.on('error',reject);req.end();
    });
  }
  try {
    const response = await request(kind === 'redirect' ? '/'+code : '/lab2-assets/index.html');
    let requests=1, bytes=response.body.length;
    if (kind === 'redirect') {
      if (response.status!==302 || !response.headers.location) throw Error('Expected redirect 302 and Location');
    } else {
      if (response.status!==200) throw Error('Page failed: '+response.status);
      const assets=[...response.body.toString().matchAll(/href="(\/lab2-assets\/[^" ]+)"/g)].map(m=>m[1]);
      if (assets.length!==50 || new Set(assets).size!==50) throw Error('Expected 50 distinct assets');
      const responses=await Promise.all(assets.map(request));
      if (responses.some(r=>r.status!==200)) throw Error('An asset did not return 200');
      requests+=responses.length;bytes+=responses.reduce((n,r)=>n+r.body.length,0);
    }
    return {protocol,kind,total_ms:performance.now()-started,connections,requests,body_bytes:bytes};
  } finally {
    agent?.destroy();session?.destroy();
  }
}

try {
  clearDelay();
  for (const protocol of ['HTTP/1.1','HTTP/2']) {
    await measure(protocol,'redirect');await measure(protocol,'page_50_assets');
  }
  console.log('Preflight passed: certificates, both protocols, 302 and page + 50 assets.');
  for (const delay of [0,50]) {
    if (delay) tc('qdisc','replace','dev',dev,'root','netem','delay',delay+'ms'); else clearDelay();
    const ping=execFileSync('ping',['-n','-c','3','-W','3','172.30.53.10'],{encoding:'utf8'});
    console.log('PING '+delay+'ms\n'+ping);
    for (let sample=1;sample<=5;sample++) {
      const protocols=sample%2 ? ['HTTP/1.1','HTTP/2'] : ['HTTP/2','HTTP/1.1'];
      for (const kind of ['redirect','page_50_assets']) {
        for (const protocol of protocols) {
          rows.push({added_delay_ms:delay,sample,...await measure(protocol,kind)});
        }
      }
    }
    console.log('Completed delay '+delay+'ms');
  }
  const median=values=>[...values].sort((a,b)=>a-b)[Math.floor(values.length/2)];
  const summary=[];
  for (const delay of [0,50]) for (const kind of ['redirect','page_50_assets']) for (const protocol of ['HTTP/1.1','HTTP/2']) {
    const group=rows.filter(r=>r.added_delay_ms===delay&&r.kind===kind&&r.protocol===protocol);
    summary.push({added_delay_ms:delay,kind,protocol,median_total_ms:Number(median(group.map(r=>r.total_ms)).toFixed(3)),
      median_connections:median(group.map(r=>r.connections)),requests:group[0].requests});
  }
  console.log('RESULT '+JSON.stringify({runtime:process.version,rows,summary,method:
    'Node HTTPS agent vs Node HTTP/2 session, same CA, DNS, assets and network. Five samples each; alternating protocol order. '
    +'Fresh client connection pool per sample; document fetched before discovering 50 CSS assets. Connections retained from document to assets. '
    +'HTTP/1.1 max 6 sockets, HTTP/2 50 concurrent streams on one session. Not a browser render metric. '
    +'No following redirects, no browser asset cache. Report measured connections; count is successfully created connection attempts. '
    +'Delay applied to client egress only, actual ping RTT printed. Static files served by nginx, redirects still reach Laravel.'}));
} finally { clearDelay(); }

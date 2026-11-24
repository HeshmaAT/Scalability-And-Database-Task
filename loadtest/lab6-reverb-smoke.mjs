import {rawFetch as fetch} from './lab6-raw-http.cjs';
import fs from 'node:fs';
import assert from 'node:assert/strict';
import WebSocket from 'ws';
(async()=>{
const demo=JSON.parse(fs.readFileSync('/workspace/work/lab6-demo-private.json','utf8').replace(/^\uFEFF/,''));
let token='';
async function api(path,options={}){
 const r=await fetch('http://nginx'+path,{...options,headers:{Host:'localhost:8080',Accept:'application/json',...(token?{Authorization:'Bearer '+token}:{}),...options.headers},signal:AbortSignal.timeout(10000)});
 if(!r.ok)throw new Error('HTTP '+r.status+' '+path);return r.json();
}
const login=await api('/api/login',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({email:demo.email,password:demo.password})});token=login.access_token;
const initial=await api('/api/lab6/links/'+demo.link_id+'/snapshot');
const messages=[];let ws;
const socketId=await new Promise((resolve,reject)=>{
 ws=new WebSocket('ws://reverb-2:8080/app/lab6-public-key?protocol=7&client=js&version=8.4.0&flash=false',{headers:{Origin:'http://localhost:8080'}});
 const timer=setTimeout(()=>reject(new Error('Reverb connection timeout')),10000);
 ws.on('error',reject);ws.on('message',raw=>{const event=JSON.parse(raw.toString());const data=typeof event.data==='string'?JSON.parse(event.data):event.data;
   if(event.event==='pusher:connection_established'){clearTimeout(timer);resolve(data.socket_id);}
   if(event.event==='pusher:ping')ws.send(JSON.stringify({event:'pusher:pong',data:{}}));
   if(event.event==='LinkCountUpdated')messages.push(data);
 });
});
const auth=await api('/api/lab6/broadcasting/auth',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({socket_id:socketId,channel_name:'private-links.'+demo.link_id})});
await new Promise((resolve,reject)=>{
 const timer=setTimeout(()=>reject(new Error('Private subscription timeout')),10000);
 const handler=raw=>{const e=JSON.parse(raw.toString());if(e.event==='pusher_internal:subscription_succeeded'){clearTimeout(timer);ws.off('message',handler);resolve();}};
 ws.on('message',handler);ws.send(JSON.stringify({event:'pusher:subscribe',data:{channel:'private-links.'+demo.link_id,auth:auth.auth}}));
});
for(let i=0;i<5;i++){
 const response=await fetch('http://nginx/'+demo.short_code,{redirect:'manual',headers:{Host:'localhost:8080','X-Lab6-App':'app1'},signal:AbortSignal.timeout(10000)});
 assert.equal(response.status,302);assert.equal(response.headers.get('x-lab6-app'),process.env.LAB6_APP_A_IP+':9000');await new Promise(r=>setTimeout(r,200));
}
const deadline=Date.now()+15000;
while(!messages.some(m=>m.total_clicks>=initial.total_clicks+5)&&Date.now()<deadline)await new Promise(r=>setTimeout(r,100));
assert(messages.some(m=>m.total_clicks>=initial.total_clicks+5),'Viewer on Reverb B did not receive the app A clicks');
ws.close();
const catchup=await api('/api/lab6/links/'+demo.link_id+'/events?since='+initial.cursor);
assert(catchup.events.length>=5,'REST catch-up did not retain click history');
// Also prove the browser-facing nginx Upgrade path works.
const upstream=await new Promise((resolve,reject)=>{
 const socket=new WebSocket('ws://nginx/app/lab6-public-key?protocol=7&client=js&version=8.4.0&flash=false',{headers:{Host:'localhost:8080',Origin:'http://localhost:8080'}});
 socket.on('upgrade',r=>resolve(r.headers['x-lab6-socket-node']));socket.on('open',()=>socket.close());socket.on('error',reject);
 setTimeout(()=>{socket.terminate();reject(new Error('nginx Upgrade timeout'));},10000).unref();
});
const result={app_a_redirects:5,socket_instance:'reverb-2',broadcast_target:'reverb-1',received_messages:messages.length,
 initial_count:initial.total_clicks,final_count:Math.max(...messages.map(m=>m.total_clicks)),catchup_events:catchup.events.length,nginx_upgrade:true,nginx_socket_upstream:upstream};
fs.writeFileSync('/workspace/work/lab6-reverb-smoke.json',JSON.stringify(result,null,2));console.log(JSON.stringify(result,null,2));
})().catch(error=>{console.error(error);process.exit(1);});

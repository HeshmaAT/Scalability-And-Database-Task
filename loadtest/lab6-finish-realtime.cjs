const fs=require('node:fs'),vm=require('node:vm'),net=require('node:net'),assert=require('node:assert/strict');
const {performance}=require('node:perf_hooks');
const {TextEncoder,TextDecoder}=require('node:util');
const {rawFetch}=require('/workspace/loadtest/lab6-raw-http.cjs');
const output=process.env.LAB6_RESULT_DIR;
const bundle=fs.readFileSync('/workspace/work/lab6-client/smoke.cjs','utf8');
const boundary=bundle.lastIndexOf('// smoke.mjs');
if(boundary<0||!bundle.slice(0,boundary).includes('var wrapper_default'))throw Error('Unsupported WebSocket bundle');
fs.writeFileSync('/workspace/work/lab6-client/ws-runtime.cjs',bundle.slice(0,boundary)+'\nmodule.exports=wrapper_default;\n');
const NativeWS=require('/workspace/work/lab6-client/ws-runtime.cjs');
const fixture=JSON.parse(fs.readFileSync(output+'/recovery-private.json','utf8').replace(/^\uFEFF/,''));
const echoSource=fs.readFileSync('/workspace/public/lab6/echo.js','utf8');
const uiSource=fs.readFileSync('/workspace/public/lab6/dashboard.js','utf8');
const sleep=ms=>new Promise(resolve=>setTimeout(resolve,ms));
async function until(test,message,timeout=15000){const deadline=Date.now()+timeout;while(Date.now()<deadline){if(await test())return;await sleep(100);}throw Error(message);}
async function control(action){
 const id=Date.now()+'-'+Math.random().toString(16).slice(2);
 const file=output+'/control-request.json',temp=file+'.tmp';
 fs.writeFileSync(temp,JSON.stringify({id,action,service:'reverb-2'}));fs.renameSync(temp,file);
 await until(()=>{try{const response=JSON.parse(fs.readFileSync(output+'/control-response.json','utf8').replace(/^\uFEFF/,''));if(response.id!==id)return false;if(!response.ok)throw Error('Host control failed');return true;}catch(error){if(error.code==='ENOENT'||error instanceof SyntaxError)return false;throw error;}},'Host did not acknowledge '+action,30000);
}
class Element {
 constructor(){this.listeners={};this.children=[];this.value='';this.dataset={};this.hidden=false;this.disabled=false;this.classList={add(){},remove(){},toggle(){}};this._text='';}
 addEventListener(name,callback){(this.listeners[name]??=[]).push(callback);}
 async fire(name){for(const callback of this.listeners[name]??[])await callback({preventDefault(){},target:this});}
 set textContent(text){this._text=String(text);this.children=[];}get textContent(){return this._text;}
 set innerHTML(text){this._html=String(text);this.children=[];}get innerHTML(){return this._html||'';}
 append(...children){for(const child of children){child.parent=this;this.children.push(child);}}
 prepend(child){child.parent=this;this.children.unshift(child);}get lastElementChild(){return this.children.at(-1);}
 remove(){if(this.parent)this.parent.children=this.parent.children.filter(child=>child!==this);}
 querySelector(selector){return selector==='.empty'&&this._html?.includes('empty')?{}:null;}
}
function realm({firstOnReverb2=false,proxyPort=null}={}){
 const elements=new Map(),modes=['short','long','ws'].map(mode=>{const element=new Element; element.dataset.mode=mode;return element;});
 const get=id=>{if(!elements.has(id))elements.set(id,new Element);return elements.get(id);};
 const location={hostname:'localhost',port:'8080',protocol:'http:',host:'localhost:8080',href:'http://localhost:8080/lab6'};
 const document={body:{},location,getElementById:get,querySelectorAll:selector=>selector==='[data-mode]'?modes:[],createElement:()=>new Element,createTextNode:text=>({textContent:text}),addEventListener(){}};
 const listeners={},timers=new Set(),intervals=new Set(),nativeSockets=[],states=[],catchups=[],counts=[],heartbeats={received:0,sent:0};
 let socketAttempts=0,holdReconnect=false,lastBearer='',destroyed=false;
 class BrowserSocket extends NativeWS{
  constructor(url,protocols){
   const parsed=new URL(url);socketAttempts++;
   const routeToStoppedNode=holdReconnect||(firstOnReverb2&&socketAttempts===1);
   parsed.hostname=routeToStoppedNode?'reverb-2':proxyPort?'127.0.0.1':'nginx';parsed.port=routeToStoppedNode?'8080':proxyPort?String(proxyPort):'80';
   super(parsed.href,protocols,{headers:{Host:'localhost:8080',Origin:'http://localhost:8080'}});
   nativeSockets.push(this);this.on('error',()=>{});
   this.on('message',raw=>{try{const e=JSON.parse(raw.toString());if(e.event==='pusher:ping'||e.event==='pusher:pong')heartbeats.received++;}catch{}});
  }
  send(data,...args){try{const e=JSON.parse(data);if(e.event==='pusher:ping'||e.event==='pusher:pong')heartbeats.sent++;}catch{}return super.send(data,...args);}
 }
 const sandbox={console,document,location,crypto:require('node:crypto').webcrypto,navigator:{onLine:true,userAgent:'Lab6 Node browser-API harness'},WebSocket:BrowserSocket,JSON,Math,Date,Promise,Error,URL,Buffer,performance,AbortController,AbortSignal,TextEncoder,TextDecoder,
  setTimeout(fn,ms,...args){let timer;timer=setTimeout(()=>{timers.delete(timer);fn(...args);},ms);timers.add(timer);return timer;},clearTimeout(timer){timers.delete(timer);clearTimeout(timer);},
  setInterval(fn,ms,...args){const timer=setInterval(fn,ms,...args);intervals.add(timer);return timer;},clearInterval(timer){intervals.delete(timer);clearInterval(timer);},
  btoa:value=>Buffer.from(value,'binary').toString('base64'),atob:value=>Buffer.from(value,'base64').toString('binary'),
  addEventListener(name,callback){(listeners[name]??=[]).push(callback);},removeEventListener(){},
  async fetch(route,options={}){
   if(options.headers?.Authorization)lastBearer=options.headers.Authorization;
   const response=await rawFetch('http://nginx'+route,{...options,headers:{...options.headers,Host:'localhost:8080'},signal:options.signal??AbortSignal.timeout(15000)});
   const originalJson=response.json;response.json=async()=>{const data=await originalJson();if(route.includes('/events?'))catchups.push({route,event_ids:data.events?.map(e=>e.event_id)??[],snapshot_count:data.snapshot?.total_clicks,at:Date.now()});return data;};return response;
  }
 };
 sandbox.window=sandbox;sandbox.self=sandbox;sandbox.globalThis=sandbox;
 const context=vm.createContext(sandbox);
 vm.runInContext(echoSource,context,{filename:'public/lab6/echo.js'});
 // Instrument the real adapter callbacks without changing connection or recovery behavior.
 const actualConnect=context.Lab6Socket.connect;
 const adapter={connect(options){return actualConnect({...options,onState(state){states.push({state,at:Date.now()});options.onState(state);}});}};
 context.Lab6Socket=adapter;
 vm.runInContext(uiSource,context,{filename:'public/lab6/dashboard.js'});
 get('email').value=fixture.email;get('password').value=fixture.password;
 async function login(){await get('login-form').fire('submit');if(get('notice').textContent)throw Error('UI login: '+get('notice').textContent);assert.equal(Number(get('link-id').value),Number(fixture.link_id),'UI selected a different fixture');}
 async function connect(){await modes[2].fire('click');await get('toggle').fire('click');}
 async function ready(){await until(()=>get('state').textContent.includes('متصل عبر WebSocket'),'UI did not finish subscription/catch-up',45000);}
 async function api(route){const response=await sandbox.fetch(route,{headers:{Accept:'application/json',Authorization:lastBearer}});assert.equal(response.status,200);return response.json();}
 const count=()=>Number(get('count').textContent.replace(/,/g,''));
 function destroy(){if(destroyed)return;destroyed=true;for(const fn of listeners.pagehide??[])fn();for(const socket of nativeSockets)socket.terminate();for(const timer of timers)clearTimeout(timer);for(const timer of intervals)clearInterval(timer);}
 return {get,login,connect,ready,api,count,destroy,states,catchups,nativeSockets,heartbeats,get attempts(){return socketAttempts;},set hold(value){holdReconnect=value;}};
}
async function clicks(number){for(let i=0;i<number;i++){const response=await rawFetch('http://nginx/'+fixture.short_code,{headers:{Host:'localhost:8080'},signal:AbortSignal.timeout(10000)});assert.equal(response.status,302);await sleep(200);}}
async function recovery(){
 console.log('Recovery: real Echo and dashboard code; first connection pinned to Reverb 2 by the harness.');
 const ui=realm({firstOnReverb2:true});let stopped=false;
 try{
  await ui.login();assert.equal(ui.count(),0);await ui.connect();await ui.ready();
  await clicks(2);await until(()=>ui.count()===2,'Initial live updates did not arrive');
  const previousCatchups=ui.catchups.length;
  ui.hold=true;await control('stop');stopped=true;
  await until(()=>ui.nativeSockets[0].readyState===NativeWS.CLOSED,'Stopping Reverb did not close client connection');
  await clicks(5);
  await until(async()=>{const snapshot=await ui.api('/api/lab6/links/'+fixture.link_id+'/snapshot');return snapshot.total_clicks===7;},'Missed clicks did not commit');
  assert.equal(ui.count(),2,'Offline UI unexpectedly updated');
  await control('start');stopped=false;ui.hold=false;
  await until(()=>ui.catchups.length>previousCatchups&&ui.count()===7,'Automatic Echo reconnect and dashboard catch-up did not restore count',60000);
  const recovered=ui.catchups.slice(previousCatchups).flatMap(row=>row.event_ids);
  assert.equal(new Set(recovered).size,5,'REST did not recover the five distinct missed events');
  await clicks(1);await until(()=>ui.count()===8,'Live delivery did not resume after catch-up');
  const snapshot=await ui.api('/api/lab6/links/'+fixture.link_id+'/snapshot');assert.equal(snapshot.total_clicks,8);
  return {passed:true,initial_live_clicks:2,clicks_while_disconnected:5,distinct_catchup_events:new Set(recovered).size,live_clicks_after_reconnect:1,final_ui_count:ui.count(),final_db_count:snapshot.total_clicks,automatic_connection_attempts:ui.attempts,states:ui.states,method:'Executed deployed Echo/Pusher adapter and dashboard JavaScript with a minimal DOM harness and real network. Host stopped and restarted Reverb 2. Harness held reconnection on the stopped node while generating missed clicks, then allowed reconnect through nginx. This is frontend logic/protocol verification, not browser rendering.'};
 }finally{ui.destroy();if(stopped)await control('start');}
}
async function brokenIdle(){
 console.log('Idle mismatch: real TCP proxy with a 3-second upstream-data timeout.');
 const connections=new Set();let timedOut=0;
 const server=net.createServer(client=>{
  const upstream=net.connect(80,'nginx');connections.add(client);connections.add(upstream);let timer;
  const reset=()=>{clearTimeout(timer);timer=setTimeout(()=>{timedOut++;client.destroy();upstream.destroy();},3000);};
  upstream.on('connect',reset);upstream.on('data',reset);
  client.on('error',()=>{});upstream.on('error',()=>client.destroy());
  client.on('close',()=>{clearTimeout(timer);upstream.destroy();connections.delete(client);connections.delete(upstream);});client.pipe(upstream);upstream.pipe(client);
 });
 await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
 const ui=realm({proxyPort:server.address().port});
 try{await ui.login();await ui.connect();await ui.ready();await until(()=>timedOut>0,'Short proxy idle timeout did not close connection',8000);return {passed:true,proxy_upstream_idle_timeout_seconds:3,configured_server_ping_seconds:20,observed_proxy_timeout:timedOut>0,method:'Deliberately short TCP proxy in the test client process; production nginx configuration was not changed.'};}
 finally{ui.destroy();for(const socket of connections)socket.destroy();await new Promise(resolve=>server.close(resolve));}
}
async function healthyIdle(){
 console.log('Heartbeat: holding the real nginx WebSocket idle for 82 seconds (nginx read timeout 75s, server ping interval 20s).');
 const ui=realm();
 try{
  await ui.login();await ui.connect();await ui.ready();const attempts=ui.attempts,before=ui.count(),started=Date.now();
  await sleep(82000);
  assert.equal(ui.attempts,attempts,'Connection was recreated during idle period');
  assert.equal(ui.nativeSockets.at(-1).readyState,NativeWS.OPEN,'Idle socket is not open');
  assert(ui.heartbeats.received+ui.heartbeats.sent>0,'No application heartbeat traffic observed');
  assert.equal(ui.count(),before,'Fixture received unexpected clicks during idle');
  await clicks(1);await until(()=>ui.count()===before+1,'Idle connection did not deliver the next click');
  const finalSnapshot=await ui.api('/api/lab6/links/'+fixture.link_id+'/snapshot');assert.equal(finalSnapshot.total_clicks,ui.count());
  return {passed:true,final_ui_count:ui.count(),final_db_count:finalSnapshot.total_clicks,idle_seconds:(Date.now()-started)/1000,nginx_proxy_read_timeout_seconds:75,server_ping_interval_seconds:20,connection_attempts_during_idle:ui.attempts-attempts,heartbeats:ui.heartbeats,delivered_next_click:true,method:'Real nginx Upgrade connection with production Echo/Pusher heartbeat behavior and no test clicks during the 82-second idle window.'};
 }finally{ui.destroy();}
}
(async()=>{
 const result={recovery:await recovery(),short_idle_proxy:await brokenIdle(),healthy_idle:await healthyIdle()};
 result.passed=Object.values(result).every(value=>value.passed===true);
 fs.writeFileSync(output+'/recovery-heartbeat.json',JSON.stringify(result,null,2));console.log(JSON.stringify(result,null,2));
})().catch(error=>{fs.writeFileSync(output+'/recovery-heartbeat-error.json',JSON.stringify({passed:false,error:error.message},null,2));console.error(error);process.exitCode=1;});

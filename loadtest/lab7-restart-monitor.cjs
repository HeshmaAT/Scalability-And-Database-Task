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

function atomic(name,value){const file=output+'/'+name,temp=file+'.tmp';fs.writeFileSync(temp,JSON.stringify(value));fs.renameSync(temp,file);}
(async()=>{
 const ui=realm({firstOnReverb2:true});let timer;
 try{
  await ui.login();await ui.connect();await ui.ready();
  let previousCount=ui.count(),lastChange=Date.now(),maxGap=0;
  const samples=[];
  const take=()=>{const at=Date.now(),count=ui.count();if(count!==previousCount){maxGap=Math.max(maxGap,at-lastChange);lastChange=at;previousCount=count;}
   const row={at,count,state:ui.get('state').textContent,attempts:ui.attempts,catchups:ui.catchups.length};
   samples.push(row);atomic('dashboard-status.json',row);};
  take();atomic('dashboard-ready.json',{ready:true});timer=setInterval(take,250);
  while(!fs.existsSync(output+'/stop-monitor'))await sleep(100);
  take();atomic('dashboard-monitor.json',{samples,states:ui.states,catchups:ui.catchups,connection_attempts:ui.attempts,final_count:ui.count(),max_observed_update_gap_ms:maxGap,method:'Actual deployed Echo and dashboard code, real network and minimal DOM harness. First connection pinned to reverb-2; automatic reconnect allowed through nginx. Not browser paint or per-event latency.'});
 }finally{clearInterval(timer);ui.destroy();}
})().catch(error=>{atomic('dashboard-monitor-error.json',{error:error.message});console.error(error);process.exitCode=1;});

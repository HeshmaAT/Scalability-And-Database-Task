const fs=require('node:fs'), http=require('node:http'), path=require('node:path');
const {performance}=require('node:perf_hooks');
const sleep=ms=>new Promise(r=>setTimeout(r,ms));
const root='/workspace', output=process.env.LAB6_RESULT_DIR;
const mode=process.env.LAB6_MODE;
const viewers=200, seconds=60, clickRate=5, drainMs=10000;
const bundle=fs.readFileSync(root+'/work/lab6-client/smoke.cjs','utf8');
const boundary=bundle.lastIndexOf('// smoke.mjs');
if(boundary<0 || !bundle.slice(0,boundary).includes('var wrapper_default'))throw Error('Unsupported smoke bundle; no changes made');
fs.writeFileSync(root+'/work/lab6-client/ws-runtime.cjs',bundle.slice(0,boundary)+'\nmodule.exports=wrapper_default;\n');
const WebSocket=require(root+'/work/lab6-client/ws-runtime.cjs');
const fixture=JSON.parse(fs.readFileSync(output+'/'+mode+'-private.json','utf8').replace(/^\uFEFF/,''));
const sets={viewer:new Set(),click:new Set(),control:new Set()};
const agents=Object.fromEntries(Object.keys(sets).map(role=>[role,new http.Agent({keepAlive:true,maxSockets:500,maxFreeSockets:220})]));
let token='', start=Infinity, end=Infinity, running=true;
const timelines=Array.from({length:viewers},()=>[]), sockets=[];
const counters={viewerRequests:0,viewerCompleted:0,viewerErrors:0,viewerStatuses:{},clickAttempts:0,clickSuccess:0,clickErrors:0,clickStatuses:{},clickGeneratorSkipped:0,wsErrors:0};
const redirectTimes=[], requestErrors=[], connectionSamples=[];
const inflight=new Set();
const controllers=new Set();
function track(p){inflight.add(p);p.finally(()=>inflight.delete(p)).catch(()=>{});return p;}
function request(route,{role='control',method='GET',body,timeout=10000,headers={}}={}){
 return new Promise((resolve,reject)=>{
  const controller=new AbortController();controllers.add(controller);
  let settled=false;
  const timer=setTimeout(()=>controller.abort(),timeout), began=performance.now();
  const measured=Date.now()>=start && Date.now()<end;
  if(measured && role==='viewer')counters.viewerRequests++;
  const text=body===undefined?null:JSON.stringify(body);
  const req=http.request({hostname:'nginx',port:80,path:route,method,agent:agents[role],signal:controller.signal,
   headers:{Host:'localhost:8080',Accept:'application/json',...(token?{Authorization:'Bearer '+token}:{}),...(text?{'Content-Type':'application/json','Content-Length':Buffer.byteLength(text)}:{}),...headers}},res=>{
    const parts=[];res.on('data',chunk=>parts.push(chunk));res.on('error',finishError);
    res.on('end',()=>{
      if(settled)return;settled=true;clearTimeout(timer);controllers.delete(controller);
      if(measured&&role==='viewer'){counters.viewerCompleted++;const key=String(res.statusCode);counters.viewerStatuses[key]=(counters.viewerStatuses[key]||0)+1;if(res.statusCode!==200)counters.viewerErrors++;}
      const raw=Buffer.concat(parts).toString('utf8');let json=null;try{json=JSON.parse(raw)}catch{}
      resolve({status:res.statusCode,json,ms:performance.now()-began});
    });
  });
  req.on('socket',socket=>sets[role].add(socket));
  function finishError(error){if(settled)return;settled=true;clearTimeout(timer);controllers.delete(controller);if(measured&&role==='viewer'){if(!running){counters.viewerShutdownAborts=(counters.viewerShutdownAborts||0)+1;reject(error);return;}counters.viewerErrors++;if(requestErrors.length<20)requestErrors.push({role,error:error.name});}reject(error);}
  req.on('error',finishError);if(text)req.write(text);req.end();
 });
}
async function api(route,options){const r=await request(route,options);if(r.status!==200)throw Error('HTTP '+r.status+' '+route);return r.json;}
function bytes(role){let n=0;for(const socket of sets[role])n+=socket.bytesRead+socket.bytesWritten;return n;}
function openConnections(){let n=0;for(const socket of sets.viewer)if(!socket.destroyed)n++;return n;}
function record(i,data){if(!data||!Number.isFinite(data.total_clicks))return;const rows=timelines[i];if(!rows.length||data.total_clicks>rows.at(-1).count)rows.push({count:data.total_clicks,received_ms:Date.now()});}
async function pollingViewer(i,baseline){
 let count=baseline;
 // Distribute short-poll requests evenly over their two-second period.
 if(mode==='short')await sleep(i*2000/viewers);
 while(running){
  const began=performance.now();
  try{
    const route=mode==='short'?'/snapshot':'/poll?since='+count+'&timeout=25';
    const r=await request('/api/lab6/links/'+fixture.link_id+route,{role:'viewer',timeout:mode==='long'?30000:10000});
    if(r.status===200){record(i,r.json);count=Math.max(count,r.json.total_clicks);}
    else await sleep(250);
  }catch{if(!running)break;await sleep(250);}
  if(mode==='short')await sleep(Math.max(0,2000-(performance.now()-began)));
 }
}
async function websocketViewer(i){
 await new Promise((resolve,reject)=>{
  let subscribed=false,settled=false;
  const ws=new WebSocket('ws://nginx/app/lab6-public-key?protocol=7&client=js&version=8.4.0&flash=false',{headers:{Host:'localhost:8080',Origin:'http://localhost:8080'}});sockets.push(ws);
  const timer=setTimeout(()=>fail(Error('Subscription setup timed out')),30000);
  function fail(error){if(!settled){settled=true;clearTimeout(timer);reject(error);}else if(running)counters.wsErrors++;}
  ws.on('upgrade',()=>{if(ws._socket)sets.viewer.add(ws._socket);});
  ws.on('open',()=>sets.viewer.add(ws._socket));ws.on('error',fail);
  ws.on('close',()=>{if(!subscribed)fail(Error('Socket closed before subscription'));else if(running)counters.wsErrors++;});
  ws.on('message',async raw=>{try{
    const event=JSON.parse(raw.toString());const data=typeof event.data==='string'?JSON.parse(event.data):event.data;
    if(event.event==='pusher:ping')ws.send(JSON.stringify({event:'pusher:pong',data:{}}));
    if(event.event==='pusher:connection_established'){
      const auth=await api('/api/lab6/broadcasting/auth',{method:'POST',body:{socket_id:data.socket_id,channel_name:'private-links.'+fixture.link_id},timeout:30000});
      ws.send(JSON.stringify({event:'pusher:subscribe',data:{channel:'private-links.'+fixture.link_id,auth:auth.auth}}));
    }
    if(event.event==='pusher_internal:subscription_succeeded'){subscribed=true;settled=true;clearTimeout(timer);resolve();}
    if(event.event==='LinkCountUpdated')record(i,data);
    if(event.event==='pusher:error')fail(Error('Pusher protocol error'));
  }catch(error){fail(error);}});
 });
}
function percentile(values,q){if(!values.length)return null;const sorted=[...values].sort((a,b)=>a-b);return Math.round(sorted[Math.max(0,Math.ceil(sorted.length*q)-1)]*1000)/1000;}
function firstVisible(rows,count){let lo=0,hi=rows.length;while(lo<hi){const mid=(lo+hi)>>1;if(rows[mid].count>=count)hi=mid;else lo=mid+1;}return rows[lo];}
async function collectEvents(cursor){const events=[];for(let page=0;page<100;page++){
 const data=await api('/api/lab6/links/'+fixture.link_id+'/events?since='+cursor,{timeout:30000});
 events.push(...data.events);if(!data.has_more)return {events,snapshot:data.snapshot};
 if(data.next_cursor<=cursor)throw Error('Catch-up cursor did not advance');cursor=data.next_cursor;
 }throw Error('Too many history pages');}
async function run(){
 const login=await api('/api/login',{method:'POST',body:{email:fixture.email,password:fixture.password}});
 token=login.access_token;if(typeof token!=='string'||!token)throw Error('Login returned no token');
 const initial=await api('/api/lab6/links/'+fixture.link_id+'/snapshot');
 if(initial.total_clicks!==0)throw Error('Fixture must be fresh and unused');
 if(mode==='websocket'){
   // Bound private-channel authorization concurrency so setup does not saturate PHP.
   for(let i=0;i<viewers;i+=10)await Promise.all(Array.from({length:Math.min(10,viewers-i)},(_,j)=>websocketViewer(i+j)));
 }
 const viewerBase=bytes('viewer'),clickBase=bytes('click');
 start=Date.now();end=start+seconds*1000;
 const sampler=setInterval(()=>connectionSamples.push({elapsed_ms:Date.now()-start,open_viewer_tcp_connections:openConnections()}),1000);
 let loops=[];if(mode!=='websocket')loops=Array.from({length:viewers},(_,i)=>pollingViewer(i,initial.total_clicks));
 console.log('Measuring '+mode+': 200 simulated viewers, 5 click attempts/s, 60 seconds.');
 let pendingClicks=0;
 for(let tick=0;tick<seconds*clickRate;tick++){
   await sleep(Math.max(0,start+tick*1000/clickRate-Date.now()));
   if(Date.now()>=end){counters.clickGeneratorSkipped++;continue;}
   counters.clickAttempts++;pendingClicks++;
   track((async()=>{try{
      const r=await request('/'+fixture.short_code,{role:'click',timeout:5000});
      redirectTimes.push(r.ms);const code=String(r.status);counters.clickStatuses[code]=(counters.clickStatuses[code]||0)+1;
      if(r.status===302)counters.clickSuccess++;else counters.clickErrors++;
   }catch{counters.clickErrors++;}finally{pendingClicks--;}})());
 }
 await sleep(Math.max(0,end-Date.now()));
 const measuredViewerBytes=bytes('viewer')-viewerBase, measuredClickBytes=bytes('click')-clickBase;
 clearInterval(sampler);
 // Let already-generated updates arrive, but do not include drain traffic in bytes/min.
 await sleep(drainMs);running=false;
 for(const c of controllers)c.abort();for(const ws of sockets)ws.terminate();
 await Promise.allSettled(loops);await Promise.allSettled([...inflight]);
 // PHP long-poll requests can remain active after the client disconnects.
 if(mode==='long' && process.env.LAB6_CAPTURE_ONLY!=='1'){await sleep(28000);}
 const deferred=process.env.LAB6_CAPTURE_ONLY==='1';
 const history=deferred?{events:[],snapshot:{total_clicks:null}}:await collectEvents(initial.cursor);
 if(!deferred&&!history.events.length)console.log('WARNING: no projected click events; update percentiles cannot establish a pass.');
 const events=history.events.filter(e=>Date.parse(e.occurred_at)>=start&&Date.parse(e.occurred_at)<end);
 const latencies=[],perViewer=[];let missing=0,clockInvalid=0,deliveredWithin2s=0;
 for(let i=0;i<viewers;i++){
  let received=0;for(const event of events){const row=firstVisible(timelines[i],Number(event.total_clicks));
    if(!row){missing++;continue;}const latency=row.received_ms-Date.parse(event.occurred_at);
    if(latency<0){clockInvalid++;continue;}latencies.push(latency);received++;if(latency<2000)deliveredWithin2s++;
  }perViewer.push({viewer:i,visible_events:received,final_count:timelines[i].at(-1)?.count??0});
 }
 const denominator=events.length*viewers;
 const result={awaiting_cli_history:deferred,mode,viewers,measurement_seconds:seconds,drain_seconds:drainMs/1000,offered_click_rps:clickRate,
  click_attempt_rps:counters.clickAttempts/seconds,successful_redirect_rps:counters.clickSuccess/seconds,
  ...counters,viewer_http_requests_per_min:counters.viewerRequests*60/seconds,viewer_tcp_payload_bytes_per_min:Math.round(measuredViewerBytes*60/seconds),
  click_tcp_payload_bytes_per_min:Math.round(measuredClickBytes*60/seconds),peak_observed_viewer_tcp_connections:Math.max(0,...connectionSamples.map(s=>s.open_viewer_tcp_connections)),
  ws_connections_ready_before_measurement:mode==='websocket'?sockets.length:0,setup_ws_handshakes:mode==='websocket'?viewers:0,setup_private_auth_requests:mode==='websocket'?viewers:0,
  update_p50_ms:percentile(latencies,.5),update_p95_ms:percentile(latencies,.95),update_p99_ms:percentile(latencies,.99),
  redirect_p50_ms:percentile(redirectTimes,.5),redirect_p95_ms:percentile(redirectTimes,.95),
  source_events_in_window:events.length,expected_event_viewer_pairs:denominator,visible_pairs:latencies.length,missing_pairs:missing,invalid_clock_pairs:clockInvalid,
  visible_within_2s_percent:denominator?Math.round(deliveredWithin2s/denominator*10000)/100:null,
  final_db_count:history.snapshot?.total_clicks??null,link_id:fixture.link_id,started_at:new Date(start).toISOString(),finished_at:new Date(end).toISOString(),
  metric:'Server LinkClicked occurred_at to first simulated-client counter observation at or above its dashboard projection total. Includes analytics/projection delivery, excludes time queued before the click handler and excludes browser paint. Samples can be correlated across viewers; missing pairs are reported separately.',
  bytes_method:'Delta of Node socket bytesRead + bytesWritten during the fixed 60s window; HTTP headers, bodies, WebSocket frames and heartbeat bytes included; TCP/IP headers, ACK packets and TLS excluded (internal HTTP/ws). Setup and drain excluded.',
  limitations:'200 clients share one test account and one link. Local single-machine test. No automatic reconnect in this benchmark; recovery is a separate drill. Failed/timed-out redirects are not retried; some timed-out requests may still commit. Successful latency percentiles must be read with missing/error counts.'};
 fs.writeFileSync(output+'/'+mode+'-result.json',JSON.stringify(result,null,2));
 fs.writeFileSync(output+'/'+mode+'-observations.json',JSON.stringify({events,timelines,perViewer,connectionSamples,requestErrors},null,2));
 if(deferred)console.log(JSON.stringify({mode,measurement_saved:true,clickAttempts:counters.clickAttempts,clickSuccess:counters.clickSuccess,viewerErrors:counters.viewerErrors,viewerShutdownAborts:counters.viewerShutdownAborts||0}));else console.log(JSON.stringify(result,null,2));
}
run().catch(error=>{console.error('Benchmark failed: '+error.message);process.exitCode=1;}).finally(()=>{running=false;for(const c of controllers)c.abort();for(const ws of sockets)ws.terminate();for(const agent of Object.values(agents))agent.destroy();});

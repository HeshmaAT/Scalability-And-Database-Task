
const fs=require('node:fs'),http=require('node:http');
const root=process.env.LAB7_RESULT_DIR;
const fixture=JSON.parse(fs.readFileSync(root+'/live-fixture.json','utf8'));
const agent=new http.Agent({keepAlive:true,maxSockets:256});
const sleep=ms=>new Promise(resolve=>setTimeout(resolve,ms));
const start=Date.now(),pending=new Set(),rows=[];
const counts={attempts:0,success:0,errors:0,skipped:0,append_confirmed:0,append_unconfirmed:0,missing_trace:0,statuses:{}};
function click(){return new Promise(resolve=>{
 let done=false;const began=Date.now();
 const finish=(status,confirmed)=>{if(done)return;done=true;clearTimeout(timeout);const ok=status===302;counts[ok?'success':'errors']++;
  counts.statuses[status]=(counts.statuses[status]||0)+1;
  if(ok)counts[confirmed==='yes'?'append_confirmed':confirmed==='no'?'append_unconfirmed':'missing_trace']++;
  rows.push({started_at:began,finished_at:Date.now(),status,append_confirmed:confirmed??null});resolve();};
 const request=http.get({hostname:'nginx',port:80,path:'/'+fixture.code,headers:{Host:'localhost:8080','X-Lab7-Trace':'1'},agent},response=>{response.resume();response.on('end',()=>finish(response.statusCode,response.headers['x-lab7-append-confirmed']));response.on('error',()=>finish('read_error'));});
 const timeout=setTimeout(()=>{request.destroy();finish('timeout');},5000);request.on('error',()=>finish('network_error'));
});}
const status=setInterval(()=>{const file=root+'/load-status.json',tmp=file+'.tmp';fs.writeFileSync(tmp,JSON.stringify({at:Date.now(),...counts}));fs.renameSync(tmp,file);},500);
(async()=>{
 let tick=0;
 while(Date.now()-start<600000&&!fs.existsSync(root+'/stop-load')){
  const due=Math.floor((Date.now()-start)/5)+1;
  while(tick<due){tick++;if(pending.size>=256){counts.skipped++;continue;}counts.attempts++;const task=click();pending.add(task);task.finally(()=>pending.delete(task));}
  await sleep(2);
 }
 const end=Date.now();await Promise.all([...pending]);clearInterval(status);agent.destroy();
 fs.writeFileSync(root+'/click-observations.json',JSON.stringify(rows));
 const result={...counts,target_rps:200,elapsed_seconds:(end-start)/1000,attempt_rps:counts.attempts/((end-start)/1000),started_at:new Date(start).toISOString(),finished_at:new Date(end).toISOString(),method:'Offered 200 real HTTP clicks/s, no retries. Errors and skipped slots retained. yes=Redis confirmed append; no=unconfirmed, which can include ambiguous accepted writes. Timeouts may still commit.'};
 fs.writeFileSync(root+'/live-load.json',JSON.stringify(result,null,2));console.log(JSON.stringify(result));
})().catch(error=>{clearInterval(status);agent.destroy();console.error(error);process.exitCode=1;});

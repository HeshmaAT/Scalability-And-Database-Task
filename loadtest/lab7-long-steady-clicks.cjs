const fs=require('node:fs'),http=require('node:http');
const root=process.env.LAB7_RESULT_DIR;
const fixture=JSON.parse(fs.readFileSync(root+'/live-fixture.json','utf8').replace(/^\uFEFF/,''));
const agent=new http.Agent({keepAlive:true,maxSockets:256});
const sleep=ms=>new Promise(resolve=>setTimeout(resolve,ms));
const start=Date.now(),pending=new Set(),counts={attempts:0,success:0,errors:0,skipped:0};
let stopping=false;
function click(){return new Promise(resolve=>{
 let done=false;const finish=ok=>{if(done)return;done=true;clearTimeout(timeout);ok?counts.success++:counts.errors++;resolve();};
 const request=http.get({hostname:'nginx',port:80,path:'/'+fixture.code,headers:{Host:'localhost:8080'},agent},response=>{response.resume();response.on('end',()=>finish(response.statusCode===302));response.on('error',()=>finish(false));});
 const timeout=setTimeout(()=>{request.destroy();finish(false);},5000);request.on('error',()=>finish(false));
});}
const status=setInterval(()=>{const file=root+'/load-status.json',temporary=file+'.tmp';fs.writeFileSync(temporary,JSON.stringify({at:Date.now(),started_at:start,...counts}));fs.renameSync(temporary,file);},500);
(async()=>{
 let tick=0;
 while(Date.now()-start<600000&&!fs.existsSync(root+'/stop-load')){
  const due=Math.floor((Date.now()-start)/5)+1;
  while(tick<due){
   tick++;
   if(pending.size>=256){counts.skipped++;continue;}
   counts.attempts++;const task=click();pending.add(task);task.finally(()=>pending.delete(task));
  }
  await sleep(2);
 }
 stopping=true;const finishTime=Date.now();await Promise.all([...pending]);clearInterval(status);agent.destroy();
 const result={...counts,target_rps:200,elapsed_seconds:(finishTime-start)/1000,attempt_rps:counts.attempts/((finishTime-start)/1000),started_at:new Date(start).toISOString(),finished_at:new Date(finishTime).toISOString(),method:'Real HTTP redirect attempts at 200/s on a separate lab-owned link while the isolated lifecycle/outbox/replay drills run. No HTTP retries.'};
 fs.writeFileSync(root+'/live-load.json',JSON.stringify(result,null,2));console.log(JSON.stringify(result));
})().catch(error=>{clearInterval(status);agent.destroy();console.error(error.message);process.exitCode=1;});

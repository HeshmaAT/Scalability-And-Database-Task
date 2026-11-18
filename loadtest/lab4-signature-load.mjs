import fs from 'node:fs';
import http from 'node:http';
import {createHash} from 'node:crypto';
import {performance} from 'node:perf_hooks';

const input=JSON.parse(fs.readFileSync(process.argv[2],'utf8'));
const probe=process.argv[3];
const agent=new http.Agent({keepAlive:true,maxSockets:20});
const start=performance.now();
let next=0;
const results=[];
async function consume(){
    while(next<input.requests.length){
        const item=input.requests[next++];
        const original=new URL(item.url);
        const result=await new Promise((resolve,reject)=>{
            const at=performance.now();
            const req=http.get({hostname:'edge-eu',port:80,path:original.pathname+original.search,agent,
                headers:{Host:'localhost','X-Lab4-Probe':probe}},res=>{
                const chunks=[];res.on('data',x=>chunks.push(x));res.on('error',reject);
                res.on('end',()=>{
                    const body=Buffer.concat(chunks);
                    if(res.statusCode!==200 || createHash('sha256').update(body).digest('hex')!==item.sha256){
                        reject(Error('Bad response or image hash: '+res.statusCode));return;
                    }
                    resolve({status:res.statusCode,cache:res.headers['x-cache-status'],
                        key_version:res.headers['x-lab4-cache-version']||null,total_ms:performance.now()-at});
                });
            });
            req.setTimeout(60000,()=>req.destroy(Error('Request timeout')));req.on('error',reject);
        });
        results.push(result);
    }
}
try {
    await Promise.all(Array.from({length:20},consume));
    const hits=results.filter(r=>r.cache==='HIT').length;
    const timings=results.map(r=>r.total_ms).sort((a,b)=>a-b);
    console.log(JSON.stringify({requests:results.length,hits,hit_percent:hits/results.length*100,
        median_ms:timings[Math.floor(timings.length/2)],p95_ms:timings[Math.floor(timings.length*.95)],
        elapsed_s:(performance.now()-start)/1000,results}));
} finally { agent.destroy(); }

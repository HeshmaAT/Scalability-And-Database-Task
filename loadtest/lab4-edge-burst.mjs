import http from 'node:http';
import {lookup} from 'node:dns/promises';
import {createHash} from 'node:crypto';
import {performance} from 'node:perf_hooks';

const target=new URL(process.argv[2]);
const probe=process.argv[3];
const expectedHash=process.argv[4];
const destination=await lookup(target.hostname);
const start=performance.now();
const connections=[];
const responses=[];
const jobs=Array.from({length:200},()=>new Promise((resolve,reject)=>{
    let connected;
    const req=http.get({hostname:target.hostname,port:target.port||80,path:target.pathname+target.search,
        agent:false,family:destination.family,
        lookup:(_host,options,callback)=>options.all ? callback(null,[destination]) : callback(null,destination.address,destination.family),
        headers:{Host:'localhost','X-Lab4-Probe':probe}},res=>{
        const headerAt=performance.now()-start;
        const chunks=[];
        res.on('data',chunk=>chunks.push(chunk));
        res.on('error',reject);
        res.on('end',()=>{
            const body=Buffer.concat(chunks);
            if(res.statusCode!==200 || createHash('sha256').update(body).digest('hex')!==expectedHash){
                reject(Error('Bad status or image hash')); return;
            }
            const row={status:res.statusCode,cache:res.headers['x-cache-status'],
                collapsing:res.headers['x-lab4-collapsing'],connected_ms:connected,
                response_headers_ms:headerAt,total_ms:performance.now()-start,bytes:body.length};
            responses.push(row);resolve(row);
        });
    });
    req.on('socket',socket=>socket.once('connect',()=>{
        connected=performance.now()-start;connections.push(connected);
    }));
    req.setTimeout(35000,()=>req.destroy(Error('Burst request timed out')));
    req.on('error',reject);
}));
await Promise.all(jobs);
const sorted=responses.map(r=>r.total_ms).sort((a,b)=>a-b);
console.log(JSON.stringify({requests:responses.length,hits:responses.filter(r=>r.cache==='HIT').length,
    median_ms:Number(sorted[100].toFixed(3)),burst_elapsed_s:Number(((performance.now()-start)/1000).toFixed(3)),
    connection_spread_ms:Number((Math.max(...connections)-Math.min(...connections)).toFixed(3)),
    all_connected_before_first_response:Math.max(...connections)<Math.min(...responses.map(r=>r.response_headers_ms)),
    responses}));

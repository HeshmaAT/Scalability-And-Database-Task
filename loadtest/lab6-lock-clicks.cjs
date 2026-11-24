const fs=require('node:fs');const {rawFetch}=require('/workspace/loadtest/lab6-raw-http.cjs');
const fixture=JSON.parse(Buffer.from(process.env.LAB6_PROBE_FIXTURE_B64,'base64').toString('utf8'));const sleep=ms=>new Promise(r=>setTimeout(r,ms));
(async()=>{let ok=0,failed=0;const pending=[],start=Date.now();for(let i=0;i<50;i++){
 await sleep(Math.max(0,start+i*200-Date.now()));
 pending.push(rawFetch('http://nginx/'+fixture.short_code,{headers:{Host:'localhost:8080'},signal:AbortSignal.timeout(3000)}).then(r=>{r.status===302?ok++:failed++;}).catch(()=>failed++));
}await Promise.all(pending);console.log(JSON.stringify({click_attempts:50,redirects_302:ok,failed}));})().catch(e=>{console.error(e.message);process.exitCode=1;});

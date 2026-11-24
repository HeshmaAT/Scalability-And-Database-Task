const fs = require('node:fs');
const file = '/workspace/work/lab6-client/smoke.cjs';
const marker = '// lab6-explicit-host-transport';
let source = fs.readFileSync(file, 'utf8');
if (!source.includes(marker)) {
  let replaced = 0;
  source = source.replace(/\bawait\s+fetch\s*\(/g, () => {
    replaced++;
    return 'await lab6RawFetch(';
  });
  if (replaced !== 2) throw new Error('Unexpected smoke bundle; expected two internal HTTP request sites.');
  source = marker + '\nconst {rawFetch: lab6RawFetch} = require("/workspace/loadtest/lab6-raw-http.cjs");\n' + source;
  fs.writeFileSync(file, source);
}

const diagnosticMarker = '// lab6-auth-diagnostic-v1';
if (!source.includes(diagnosticMarker)) {
  const needle = 'if (!r.ok) throw new Error("HTTP " + r.status + " " + path);';
  if (!source.includes(needle)) throw new Error('Unexpected API helper; diagnostic not applied.');
  const replacement = `// lab6-auth-diagnostic-v1
    if (!r.ok) {
      console.log('AUTH_DIAGNOSTIC', JSON.stringify({status:r.status,path,tokenPresent:typeof token==='string'&&token.length>0,tokenFormat:typeof token==='string'&&/^\\\\d+\\\\|/.test(token),upstream:r.headers.get('x-lab6-app')}));
      if(r.status===401 && token){
        await new Promise(resolve=>setTimeout(resolve,2000));
        for(const app of ['app1','app2']){
          const probe=await lab6RawFetch('http://nginx'+path,{headers:{Host:'localhost:8080',Accept:'application/json',Authorization:'Bearer '+token,'X-Lab6-App':app},signal:AbortSignal.timeout(10000)});
          console.log('AUTH_PROBE',JSON.stringify({app,status:probe.status,upstream:probe.headers.get('x-lab6-app')}));
        }
      }
      throw new Error('HTTP '+r.status+' '+path);
    }`;
  source = source.replace(needle,replacement);
  fs.writeFileSync(file,source);
}
console.log('Smoke transport and safe auth diagnostics ready; application unchanged.');

import csv,json,sys,statistics
from pathlib import Path
root=Path(sys.argv[1])
results=[json.loads((root/(mode+'-result.json')).read_text(encoding='utf-8-sig')) for mode in ('short','websocket','long')]
columns=['mode','viewers','measurement_seconds','successful_redirect_rps','viewer_http_requests_per_min','viewer_tcp_payload_bytes_per_min','peak_observed_viewer_tcp_connections','update_p50_ms','update_p95_ms','update_p99_ms','visible_within_2s_percent','visible_pairs','missing_pairs','viewerErrors','viewerShutdownAborts','clickErrors','wsErrors','redirect_p95_ms','final_db_count','projection_history_complete','unprojected_db_clicks','acknowledged_redirects_without_count','update_goal_met']
with (root/'comparison.csv').open('w',newline='',encoding='utf-8-sig') as f:
 w=csv.DictWriter(f,fieldnames=columns,extrasaction='ignore');w.writeheader();w.writerows(results)
def instant(s):
 from datetime import datetime
 return datetime.fromisoformat(s.replace('Z','+00:00')).timestamp()
samples=[]
if (root/'resources.jsonl').exists():
 for line in (root/'resources.jsonl').read_text(encoding='utf-8-sig').splitlines():
  try:samples.append(json.loads(line.lstrip('\ufeff')))
  except (ValueError,TypeError):pass
resources=[]
for result in results:
 begin,end=instant(result['started_at']),instant(result['finished_at'])
 groups={}
 for sample in samples:
  if begin<=instant(sample['sampled_at'])<end:
   stat=sample['stats'];name=stat.get('Name',stat.get('Container','unknown'))
   try:cpu=float(stat['CPUPerc'].rstrip('%'))
   except (ValueError,KeyError):continue
   groups.setdefault(name,[]).append((cpu,stat.get('MemUsage','')))
 for name,rows in groups.items():resources.append({'mode':result['mode'],'container':name,'samples':len(rows),'cpu_avg_percent':round(statistics.mean(r[0] for r in rows),3),'cpu_peak_percent':max(r[0] for r in rows),'memory_usage_first_sample':rows[0][1],'memory_usage_last_sample':rows[-1][1]})
with (root/'resource-summary.csv').open('w',newline='',encoding='utf-8-sig') as f:
 columns2=['mode','container','samples','cpu_avg_percent','cpu_peak_percent','memory_usage_first_sample','memory_usage_last_sample']
 w=csv.DictWriter(f,fieldnames=columns2);w.writeheader();w.writerows(resources)
print('\nMode         Redirect/s  HTTP req/min  Update p50  Update p95  Under 2s %  Missing')
for r in results:
 print(f"{r['mode']:12} {r['successful_redirect_rps']:10.3f} {r['viewer_http_requests_per_min']:13.0f} {str(r['update_p50_ms']):>11} {str(r['update_p95_ms']):>11} {str(r['visible_within_2s_percent']):>11} {r['missing_pairs']:8}")
if not resources:print('WARNING: no Docker resource samples were captured; do not claim CPU/memory comparison.')
print('Latency is server-click-event to simulated viewer receipt; not browser paint. Read successful percentiles with errors and missing pairs.')

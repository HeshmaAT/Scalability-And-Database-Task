import json,sys,bisect,math
from pathlib import Path
from datetime import datetime,timezone
root=Path(sys.argv[1]);mode=sys.argv[2]
def load(p):return json.loads(p.read_text(encoding='utf-8-sig'))
result_path=root/(mode+'-result.json');observations_path=root/(mode+'-observations.json')
result=load(result_path);observations=load(observations_path);history=load(root/(mode+'-cli-history.json'))
if int(result['link_id'])!=int(history['link_id']):raise SystemExit('History belongs to another fixture')
for p in (result_path,observations_path):
 backup=p.with_name(p.stem+'-before-cli'+p.suffix)
 if not backup.exists():backup.write_bytes(p.read_bytes())
def ms(s):
 value=datetime.fromisoformat(s.replace('Z','+00:00'))
 if value.tzinfo is None:value=value.replace(tzinfo=timezone.utc)
 return value.timestamp()*1000
begin,end=ms(result['started_at']),ms(result['finished_at'])
events=[e for e in history['events'] if begin<=ms(e['occurred_at'])<end]
latencies=[];missing=invalid=within=0;per_viewer=[]
for viewer,rows in enumerate(observations['timelines']):
 counts=[row['count'] for row in rows];received=0
 for event in events:
  index=bisect.bisect_left(counts,int(event['total_clicks']))
  if index>=len(rows):missing+=1;continue
  latency=rows[index]['received_ms']-ms(event['occurred_at'])
  if latency<0:invalid+=1;continue
  latencies.append(latency);received+=1;within+=latency<2000
 per_viewer.append({'viewer':viewer,'visible_events':received,'final_count':rows[-1]['count'] if rows else 0})
latencies.sort()
def pct(q):return round(latencies[max(0,math.ceil(len(latencies)*q)-1)],3) if latencies else None
expected=len(events)*int(result['viewers'])
result.update(awaiting_cli_history=False,source_events_in_window=len(events),expected_event_viewer_pairs=expected,
 visible_pairs=len(latencies),missing_pairs=missing,invalid_clock_pairs=invalid,
 update_p50_ms=pct(.5),update_p95_ms=pct(.95),update_p99_ms=pct(.99),
 visible_within_2s_percent=round(within/expected*100,2) if expected else None,
 final_db_count=history['snapshot']['total_clicks'],projection_history_complete=history['history_complete'],
 unprojected_db_clicks=max(0,history['snapshot']['total_clicks']-len(history['events'])),
 acknowledged_redirects_without_count=max(0,result['clickSuccess']-history['snapshot']['total_clicks']),
 history_collection_method=history['method'])
result['update_goal_met']=bool(expected and history['history_complete'] and not missing and not invalid and pct(.95)<2000 and not result['viewerErrors'] and not result['clickErrors'] and not result['wsErrors'] and result['successful_redirect_rps']>=4.99)
observations.update(events=history['events'],perViewer=per_viewer)
result_path.write_text(json.dumps(result,ensure_ascii=False,indent=2),encoding='utf-8')
observations_path.write_text(json.dumps(observations,ensure_ascii=False,indent=2),encoding='utf-8')
print(f"{mode}: p50={result['update_p50_ms']}ms p95={result['update_p95_ms']}ms; visible={len(latencies)}/{expected}; missing={missing}; projection history complete={history['history_complete']}")

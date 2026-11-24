from pathlib import Path
import json,sys,collections
root=Path(sys.argv[1])
def load(name):return json.loads((root/name).read_text(encoding='utf-8-sig'))
data=load('lock-samples.json');mapping={r['ip']:r['service'] for r in load('container-ip-map.json')};samples=data['samples'];owners={}
for row in samples:
 if row['owner'] is None:continue
 p=row.get('process') or {};host=p.get('HOST','');ip=host.rsplit(':',1)[0]
 key=(row['owner'],mapping.get(ip,ip or 'unknown'))
 entry=owners.setdefault(key,{'samples':0,'states':collections.Counter(),'max_statement_age_s':0})
 entry['samples']+=1;entry['states'][p.get('STATE') or p.get('COMMAND') or 'unknown']+=1;entry['max_statement_age_s']=max(entry['max_statement_age_s'],int(p.get('TIME') or 0))
print('Lock samples:',len(samples),'occupied:',sum(r['owner'] is not None for r in samples))
for (owner,service),value in owners.items():print(json.dumps({'owner_session':owner,'service':service,**value},ensure_ascii=False,default=dict))
print('Incomplete archives:',json.dumps(data['incomplete_archive_runs']))
print('Stage timings:',json.dumps(load('stage-timing.json'),ensure_ascii=False))

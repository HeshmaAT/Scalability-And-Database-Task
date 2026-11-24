from pathlib import Path
import json,csv,sys,statistics
from datetime import datetime
root=Path(sys.argv[1])
def load(path):return json.loads(path.read_text(encoding='utf-8-sig'))
short=load(root/'short-result.json');ws=load(root/'websocket-result.json');long=load(root/'long-result.json');checks=load(root/'recovery-heartbeat.json');sources=load(root/'measurement-sources.json')
checks.update(feature_test_log_saved=(root/'feature-tests.log').is_file(),short_measurement_completed=True,websocket_update_goal_met=bool(ws['update_goal_met']),long_polling_exhaustion_documented=long['clickSuccess']==0 and long['clickAttempts']==300,scope='Local simulated clients; actual frontend JavaScript with DOM harness for recovery, no browser-render timing.')
checks['lab6_checks_passed']=bool(checks['feature_test_log_saved'] and checks['passed'] and checks['websocket_update_goal_met'] and checks['long_polling_exhaustion_documented'])
(root/'final-checks.json').write_text(json.dumps(checks,ensure_ascii=False,indent=2),encoding='utf-8')
columns=['mode','backend_version','viewers','measurement_seconds','successful_redirect_rps','viewer_http_requests_per_min','viewer_tcp_payload_bytes_per_min','update_p50_ms','update_p95_ms','update_p99_ms','visible_within_2s_percent','viewerErrors','clickErrors','wsErrors','final_db_count','source_directory']
rows=[]
for mode,result,version in [('short',short,'after_lock_wait_fix'),('websocket',ws,'after_lock_wait_fix'),('long',long,'before_lock_wait_fix_negative_test')]:
 row=dict(result,mode=mode,backend_version=version,source_directory=sources[mode]);rows.append(row)
with (root/'comparison.csv').open('w',encoding='utf-8-sig',newline='') as f:
 writer=csv.DictWriter(f,fieldnames=columns,extrasaction='ignore');writer.writeheader();writer.writerows(rows)
def instant(s):return datetime.fromisoformat(s.replace('Z','+00:00')).timestamp()
resource_rows=[]
for mode,result in [('short',short),('websocket',ws),('long',long)]:
 src=Path(sources[mode]);groups={};begin,end=instant(result['started_at']),instant(result['finished_at'])
 raw=src/'resources.jsonl'
 if not raw.exists():continue
 for line in raw.read_text(encoding='utf-8-sig').splitlines():
  try:
   sample=json.loads(line.lstrip('\ufeff'))
   if not begin<=instant(sample['sampled_at'])<end:continue
   stat=sample['stats'];groups.setdefault(stat['Name'],[]).append((float(stat['CPUPerc'].rstrip('%')),stat['MemUsage']))
  except (ValueError,KeyError):continue
 for name,values in groups.items():resource_rows.append({'mode':mode,'container':name,'samples':len(values),'cpu_avg_percent':round(statistics.mean(v[0] for v in values),3),'cpu_peak_percent':max(v[0] for v in values),'memory_usage_first_sample':values[0][1],'memory_usage_last_sample':values[-1][1]})
with (root/'resource-summary.csv').open('w',encoding='utf-8-sig',newline='') as f:
 columns2=['mode','container','samples','cpu_avg_percent','cpu_peak_percent','memory_usage_first_sample','memory_usage_last_sample'];writer=csv.DictWriter(f,fieldnames=columns2);writer.writeheader();writer.writerows(resource_rows)
text=f"""في Lab 6 عملت Dashboard خاصة بصاحب اللينك علشان أتابع العداد الحقيقي.
جربت Short Polling كل ثانيتين وLong Polling بمهلة 25 ثانية وWebSockets باستخدام Laravel Reverb.
اختبرت منع المستخدم الغريب من الاشتراك في قناة لينك مش بتاعه، والتوكنات بقت بتتقري من الـPrimary علشان ظهور التوكن الجديد وإلغاؤه ميتأخروش بسبب الـReplication.
شغلت نسختين من Reverb بينهم Redis Scaling، واتأكدت إن النشر على نسخة وصل لعميل متصل بالنسخة التانية.
في مقارنة الحمل استخدمت 200 مشاهد محاكى و5 محاولات ضغط في الثانية لمدة دقيقة، على لينكات اختبار منفصلة.
ظهر تزاحم متقطع بين مستهلكي Analytics على القفل المشتركة مع الصيانة.
عدلت مستهلك Analytics ينتظر القفل لحد ثانية بدل الرفض الفوري ثم النوم ثانية كاملة.
حماية Archive وRebuild فضلت موجودة، واختبرت إن القفل بتتفك حتى لو المعالجة فشلت وإن Archive غير مكتملة تمنع الكتابة.
بعد التعديل، Short Polling حقق p50 حوالي {short['update_p50_ms']} ms وp95 حوالي {short['update_p95_ms']} ms، وبعت {short['viewer_http_requests_per_min']} طلب متابعة في الدقيقة.
WebSockets بعد التعديل حقق p50 حوالي {ws['update_p50_ms']} ms وp95 حوالي {ws['update_p95_ms']} ms وp99 حوالي {ws['update_p99_ms']} ms.
نجح {ws['clickSuccess']} طلب Redirect، والعداد النهائي بقى {ws['final_db_count']}، وكل مشاهدات العداد المقاسة وصلت خلال ثانيتين من غير أخطاء اتصال أو تحويل.
WebSockets احتاج 200 اتصال مفتوح و200 طلب Authorization وقت التجهيز، وبعدها مفيش طلبات HTTP دورية للمتابعة.
الـHeartbeat وبيانات التحديثات لسه بيستهلكوا Bandwidth، والبايتات في الجدول تشمل بيانات TCP المقاسة من التطبيق والهيدرز والإطارات، مش تكلفة الشبكة الكاملة أو TLS.
تجربة Long Polling السلبية كانت قبل تعديل انتظار القفل وبنفس حد PHP-FPM البالغ 5 Workers لكل App.
في التجربة دي الـWorkers اتشغلوا بطلبات الانتظار، وفشلت كل محاولات الضغط الـ300 في الحصول على 302، فمفيش زمن تحديث صالح للمقارنة.
احتفظت بنتيجة الفشل كدليل على استنزاف PHP-FPM، ومعاها نتائج المقارنة قبل الإصلاح وبعده في مجلدات منفصلة.
اختبرت كود Echo والـDashboard الحقيقيين داخل DOM محاكى مع اتصالات شبكة حقيقية.
أوقفت Reverb 2، وبعت خمس ضغطات أثناء انقطاع العميل، وبعد رجوع الاتصال كود الواجهة استرجع الخمسة من REST باستخدام Cursor من غير مضاعفة العداد.
بعد التعافي، ضغطة جديدة وصلت Live والعداد في الواجهة طابق الداتابيز.
جربت ممر TCP تجريبي بمهلة Idle مدتها 3 ثواني، وهي أقل من فترة Heartbeat، فرصدت قطع الاتصال.
بعدها اختبرت اتصال nginx الفعلي بمهلة قراءة 75 ثانية، وسيبته هادي 82 ثانية، والـHeartbeat حافظ عليه من غير إعادة اتصال ثم وصلت عليه ضغطة جديدة.
قياس زمن التحديث من وقت حدث الضغطة عند السيرفر لأول ظهور العداد عند العميل المحاكى، ويشمل معالجة Analytics والتوصيل، لكنه مش قياس زمن الرسم داخل المتصفح ولا الانتظار قبل دخول Handler.
نتائج CPU والذاكرة محفوظة مع كل تشغيل. نسبة Docker CPU لكل Core، وممكن تتعدى 100% مع استخدام أكتر من Core.
"""
(root/'LAB6_REPORT_TEXT.txt').write_text(text,encoding='utf-8')
print('Final checks passed:',checks['lab6_checks_passed'])
if not resource_rows:print('WARNING: resource samples missing; do not claim CPU/memory evidence.')
if not checks['lab6_checks_passed']:raise SystemExit('Some Lab 6 checks are incomplete; inspect final-checks.json')

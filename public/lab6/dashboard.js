(() => {
 'use strict';
 const $=id=>document.getElementById(id);let token='',link=null,mode='short',running=false,epoch=0,controller=null,timer=null,baseline=null,requests=0,socket=null,cursor=0,catching=false,buffer=[];
 const fmt=n=>Number(n).toLocaleString('en-US');
 const message=text=>{$('notice').textContent=text;};
 const status=(text,good=false)=>{$('state').textContent=text;$('state').className='badge'+(good?' good':'');};
 function stop(){running=false;epoch++;clearTimeout(timer);controller?.abort();controller=null;socket?.disconnect();socket=null;catching=false;buffer=[];$('toggle').textContent='ابدأ المتابعة';if(link)status('المتابعة متوقفة');}
 async function api(path,options={}){
   const response=await fetch(path,{...options,cache:'no-store',headers:{Accept:'application/json',...(token?{Authorization:'Bearer '+token}:{}),...options.headers}});
   let body={};try{body=await response.json();}catch{}
   if(!response.ok){if(response.status===401)throw new Error('بيانات الدخول غير صحيحة أو الجلسة انتهت.');if(response.status===404)throw new Error('اللينك مش موجود أو مش تابع لحسابك.');if(response.status===429)throw new Error('طلبات كتير. استنى شوية وحاول تاني.');throw new Error(body.message||'تعذر الاتصال بالسيرفر.');}
   return body;
 }
 function addEvent(delta,total){
   const item=document.createElement('div');item.className='event';const text=document.createElement('span');const bold=document.createElement('b');bold.textContent='+'+fmt(delta)+' ضغطة';text.append(bold,document.createTextNode('  · الإجمالي '+fmt(total)));const time=document.createElement('small');time.textContent=new Date().toLocaleTimeString('en-GB');item.append(text,time);
   const container=$('activity');if(container.querySelector('.empty'))container.textContent='';container.prepend(item);while(container.children.length>20)container.lastElementChild.remove();
 }
 function paint(data){
   if(data.cursor!=null)cursor=Math.max(cursor,Number(data.cursor));
   const previous=link?.total_clicks??null;link=data;if(baseline===null)baseline=data.total_clicks;
   $('count').textContent=fmt(data.total_clicks);$('code').textContent=data.short_code;$('open-link').href='/'+encodeURIComponent(data.short_code);$('open-link').hidden=false;
   $('delta').textContent=fmt(Math.max(0,data.total_clicks-baseline));$('last-update').textContent='آخر قراءة '+new Date().toLocaleTimeString('en-GB');
   $('toggle').disabled=false;$('five-clicks').disabled=!data.is_active;
   if(previous!==null&&data.total_clicks>previous){addEvent(data.total_clicks-previous,data.total_clicks);$('count').classList.remove('pulse');void $('count').offsetWidth;$('count').classList.add('pulse');}
 }
 async function load(id){
   stop();message('');if(!/^\d+$/.test(String(id)))return message('اكتب رقم لينك صحيح.');
   const loadEpoch=epoch;link=null;baseline=null;requests=0;cursor=0;$('count').textContent='—';$('delta').textContent='0';$('open-link').hidden=true;$('requests').textContent='0';$('response').textContent='—';$('activity').innerHTML='<p class="empty">ابدأ المتابعة وافتح اللينك أو أرسل ضغطات تجريبية.</p>';$('toggle').disabled=true;$('five-clicks').disabled=true;status('جاري تحميل اللينك');
   try{const data=await api('/api/lab6/links/'+id+'/snapshot');if(loadEpoch!==epoch)return;paint(data);$('link-id').value=data.link_id;status('جاهز للمتابعة',true);}catch(e){if(loadEpoch!==epoch)return;status('تعذر تحميل اللينك');message(e.message);}
 }
 async function cycle(currentEpoch){
   if(!running||currentEpoch!==epoch||!link)return;const started=performance.now();controller=new AbortController();const timeout=setTimeout(()=>controller?.abort(),33000);
   requests++;$('requests').textContent=fmt(requests);
   const path='/api/lab6/links/'+link.link_id+(mode==='short'?'/snapshot':'/poll?since='+link.total_clicks+'&timeout=25');
   try{const data=await api(path,{signal:controller.signal});if(currentEpoch!==epoch)return;paint(data);$('response').textContent=Math.round(performance.now()-started)+' ms';status('المتابعة شغالة',true);message('');}
   catch(e){if(currentEpoch!==epoch)return;status('إعادة المحاولة');message(e.name==='AbortError'?'انتهت مهلة الطلب. هنحاول تاني.':e.message);}
   finally{clearTimeout(timeout);}
   if(running&&currentEpoch===epoch)timer=setTimeout(()=>cycle(currentEpoch),mode==='short'?Math.max(0,2000-(performance.now()-started)):100);
 }
 function acceptEvent(data,currentEpoch){
   if(currentEpoch!==epoch||!running||Number(data.link_id)!==Number(link?.link_id))return;
   if(Number(data.cursor)<=cursor)return;
   paint({...link,...data,total_clicks:Math.max(link.total_clicks,Number(data.total_clicks))});
 }
 async function startSocket(currentEpoch){
   try{
     if(!window.Lab6Socket)throw new Error('ملف Echo غير موجود. أعد تشغيل تجهيز Reverb.');
     const config=await api('/api/lab6/realtime/config');if(currentEpoch!==epoch)return;
     status('جاري الاتصال');
     socket=Lab6Socket.connect({key:config.key,token,linkId:link.link_id,
       onState:state=>{if(currentEpoch!==epoch)return;if(state==='connected')status('متصل · انتظار تأكيد القناة');else status('إعادة الاتصال');},
       onError:error=>{if(currentEpoch===epoch)message(error.message||'تعذر الاشتراك في القناة.');},
       onEvent:data=>{if(catching)buffer.push(data);else acceptEvent(data,currentEpoch);},
       onSubscribed:async()=>{
         if(currentEpoch!==epoch)return;catching=true;buffer=[];
         try{
           let more=true;
           while(more&&currentEpoch===epoch){
             const result=await api('/api/lab6/links/'+link.link_id+'/events?since='+cursor);requests++;$('requests').textContent=fmt(requests);
             if(currentEpoch!==epoch)return;
             for(const item of result.events)acceptEvent(item,currentEpoch);
             cursor=Math.max(cursor,Number(result.next_cursor));
             paint({...result.snapshot,cursor,total_clicks:Math.max(link.total_clicks,result.snapshot.total_clicks)});
             more=result.has_more;
           }
           buffer.sort((a,b)=>Number(a.cursor)-Number(b.cursor));for(const item of buffer)acceptEvent(item,currentEpoch);buffer=[];
           status('متصل عبر WebSocket',true);message('');$('response').textContent='دفع مباشر';
         }catch(error){if(currentEpoch===epoch)message(error.message);}
         finally{catching=false;}
       }
     });
   }catch(error){if(currentEpoch===epoch){status('تعذر الاتصال');message(error.message);}}
 }
 function startSelected(currentEpoch){if(mode==='ws')startSocket(currentEpoch);else cycle(currentEpoch);}
 $('login-form').addEventListener('submit',async e=>{
   e.preventDefault();message('');$('login-button').disabled=true;
   try{const data=await api('/api/login',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({email:$('email').value.trim(),password:$('password').value})});token=data.access_token;if(!token)throw new Error('الرد لا يحتوي جلسة صالحة.');$('password').value='';
     const result=await api('/api/lab6/links');$('links').textContent='';for(const item of result.links){const option=document.createElement('option');option.value=item.id;option.textContent=item.short_code+' · #'+item.id;$('links').append(option);}
     $('login-form').hidden=true;$('account').hidden=false;status('اختار لينك');if(result.links.length)await load(result.links[0].id);else message('مفيش روابط للحساب ده. أنشئ رابطًا من الـAPI الأول.');
   }catch(error){token='';message(error.message);}finally{$('login-button').disabled=false;}
 });
 $('logout').addEventListener('click',()=>{stop();token='';link=null;baseline=null;$('login-form').hidden=false;$('account').hidden=true;$('count').textContent='—';$('code').textContent='اختر لينك لبدء المتابعة';$('open-link').hidden=true;$('five-clicks').disabled=true;$('activity').innerHTML='<p class="empty">سجّل الدخول لعرض روابطك.</p>';$('delta').textContent='0';$('requests').textContent='0';$('response').textContent='—';$('last-update').textContent='لم يصل تحديث بعد';status('في انتظار تسجيل الدخول');message('');});
 $('links').addEventListener('change',e=>load(e.target.value));$('load-link').addEventListener('click',()=>load($('link-id').value));
 document.querySelectorAll('[data-mode]').forEach(button=>button.addEventListener('click',()=>{
   const wasRunning=running;stop();mode=button.dataset.mode;document.querySelectorAll('[data-mode]').forEach(b=>b.classList.toggle('active',b===button));
   $('mode-name').textContent=mode==='short'?'Short Polling · كل ثانيتين':mode==='long'?'Long Polling · حتى 25 ثانية':'WebSockets · Laravel Reverb';
   $('explanation').textContent=mode==='short'?'الصفحة بتسأل عن العداد كل ثانيتين.':mode==='long'?'الطلب يفضل مفتوحًا حتى تغير العداد أو مرور 25 ثانية، ويشغل FPM Worker أثناء الانتظار.':'التحديث يصل عبر قناة خاصة من Dashboard Consumer. بعد إعادة الاتصال بنسترجع التحديثات الفائتة من REST.';
   if(wasRunning&&link){running=true;$('toggle').textContent='إيقاف المتابعة';startSelected(epoch);}
 }));
 $('toggle').addEventListener('click',()=>{if(running)return stop();if(!link)return;running=true;epoch++;$('toggle').textContent='إيقاف المتابعة';startSelected(epoch);});
 $('five-clicks').addEventListener('click',async()=>{
   if(!link)return;const code=link.short_code;$('five-clicks').disabled=true;message('');let sent=0;
   try{for(let i=0;i<5;i++){const result=await fetch('/'+encodeURIComponent(code),{redirect:'manual',cache:'no-store'});if(result.type!=='opaqueredirect'&&result.status!==302)throw new Error('الرابط مش بيرجع تحويلًا صالحًا.');sent++;await new Promise(resolve=>setTimeout(resolve,200));}message('اترسل '+sent+' طلب تحويل. تابع وصول الزيادة من Analytics.');}catch(e){message(e.message);}finally{$('five-clicks').disabled=!link?.is_active;}
 });
 window.addEventListener('pagehide',stop);
})();

<!doctype html>
<html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="color-scheme" content="light"><title>متابعة الضغطات</title><link rel="stylesheet" href="/lab6/dashboard.css"></head>
<body><div class="shell"><header class="topbar"><div class="brand"><span class="brand-icon" aria-hidden="true">↗</span><div><b>متابعة الضغطات</b><span>مختبر التحديث المباشر</span></div></div><span class="lab-label">LAB 06</span></header>
<main><section class="intro"><p class="eyebrow">من الضغطة إلى الشاشة</p><h1>شوف اللينك وهو بيتحرك.</h1><p>افتح رابطك، وراقب عدد الضغطات وطريقة وصول التحديث.</p></section>
<div class="workspace"><aside class="panel controls"><form id="login-form"><h2>حسابك</h2><p class="muted">سجّل الدخول لعرض روابطك فقط.</p>
<label for="email">البريد الإلكتروني</label><input id="email" type="email" autocomplete="username" placeholder="you@example.com" required>
<label for="password">كلمة المرور</label><input id="password" type="password" autocomplete="current-password" required>
<button class="primary wide" id="login-button">تسجيل الدخول</button></form>
<div id="account" hidden><div class="account-top"><span class="badge good">تم تسجيل الدخول</span><button type="button" id="logout" class="text-button">خروج</button></div>
<label for="links">اختر لينك</label><select id="links" aria-label="روابطك"></select>
<label for="link-id">أو اكتب رقم اللينك</label><div class="inline"><input id="link-id" type="number" min="1" inputmode="numeric" placeholder="Link ID"><button id="load-link" type="button" class="secondary">عرض</button></div>
<hr><h2>طريقة المتابعة</h2><div class="mode-picker" role="group" aria-label="طريقة المتابعة">
<button type="button" class="mode active" data-mode="short"><b>Short Polling</b><small>طلب كل ثانيتين</small></button>
<button type="button" class="mode" data-mode="long"><b>Long Polling</b><small>الطلب ينتظر وصول تحديث</small></button>
<button type="button" class="mode" data-mode="ws"><b>WebSockets</b><small>Laravel Reverb · قناة خاصة</small></button></div>
<button type="button" id="toggle" class="primary wide" disabled>ابدأ المتابعة</button></div>
<p id="notice" class="notice" role="status" aria-live="polite"></p></aside>
<section class="dashboard"><div class="panel counter-panel"><div class="card-top"><span class="eyebrow">إجمالي الضغطات</span><span id="state" class="badge">في انتظار تسجيل الدخول</span></div>
<div id="count" class="count" aria-live="polite">—</div><div class="link-line"><span id="code">اختر لينك لبدء المتابعة</span><a id="open-link" target="_blank" rel="noopener noreferrer" hidden>افتح الرابط ↗</a></div>
<div class="counter-bottom"><span id="mode-name">Short Polling · كل ثانيتين</span><span id="last-update">لم يصل تحديث بعد</span></div></div>
<div class="metrics"><article class="panel"><span>طلبات المتابعة</span><strong id="requests">0</strong><small>طلبات البيانات من الصفحة</small></article><article class="panel"><span>ضغطات أثناء المشاهدة</span><strong id="delta">0</strong><small>من وقت اختيار اللينك</small></article><article class="panel"><span>آخر استجابة</span><strong id="response">—</strong><small>زمن طلب البيانات، مش زمن الضغطة</small></article></div>
<section class="panel activity-panel"><div class="section-top"><div><h2>حركة التحديثات</h2><p class="muted">كل زيادة تظهر هنا بعد وصولها من السيرفر.</p></div><button type="button" id="five-clicks" class="secondary" disabled>أرسل 5 ضغطات</button></div>
<div id="activity" class="activity"><p class="empty">لسه مفيش تحديثات. اختار لينك وابدأ التجربة.</p></div></section>
<p class="footnote" id="explanation">في Short Polling، الصفحة بتسأل عن العداد كل ثانيتين. الضغطات بتتسجل من خلال مسار التحويل الحقيقي.</p></section></div></main>
<footer>مقارنة طرق التحديث على نفس اللينك · البيانات خاصة بصاحب الحساب</footer></div><script src="/lab6/echo.js" defer></script><script src="/lab6/dashboard.js" defer></script></body></html>

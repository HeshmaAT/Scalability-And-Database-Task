السؤال 1
Which short-code strategy did you implement, and what would you change at 100x scale?


استخدمت SHA256 على Random Bytes مع الرابط، وبعدها بطلع كود Base62 من 10 خانات.
يعني نفس الرابط ممكن ياخد اكتر من كود، ومش معتمد على الهاش لوحده لمنع التكرار.
عندي UNIQUE على short_code، ولو حصل تصادم في الكود المولد بحاول تاني لحد 5 مرات.
اما لو Custom Alias متاخد بالفعل برجع 409.
جربت انشاء 50 رابط في نفس الوقت وكلهم رجعوا 201 باكواد مختلفه.
لو الحجم زاد 100 مره هقيس الاول معدل التصادم وزمن الكتابه وحمل الـUnique Index.
ممكن ازود طول الكود لو محتاج مساحه اكبر، لكن مش هغير الاستراتيجيه لمجرد ان الحجم زاد.
ولو اخترت Pre-generated Pool بعد كدا هحتاج تخصيص ذري للكود وتجهيز اكواد جديده قبل المخزون ما يخلص.
الـPool مش اضمن ضد التخمين لمجرد انه متولد قبل الطلب، ده بيعتمد على عشوائيه الكود وطوله.


السؤال 2

Cache TTL vs explicit invalidation: what stale-data window does your system allow,
and is it acceptable for pause and delete?

الاجابه
استخدمت الاتنين. TTL الرابط 10 دقايق او لحد انتهائه لو اقرب، والـNegative Cache مدتها 30 ثانيه.
ولما المستخدم يعدل الرابط بقرا من Primary وبمسك Lock للكود وبحدث الكاش بعد حفظ الداتابيز.
جربت ايقاف الرابط وتشغيله تاني وتعديل الانتهاء، والاختبارات عدت.
بس الـ10 دقايق عمر نسخه الكاش، مش ضمان ان اي بيانات قديمه هتختفي نهائي بعدهم.
لان لو الـReplica رجعت صف قديم بعد Cache Miss ممكن اخزنه تاني.
وكمان لو الحفظ نجح وRedis وقعت، الكتابه في الداتابيز وتحديث الكاش مش Transaction واحده.
دي ملاحظه عندي ومسجلها ومش عامل اصلاحها لسا.
الـSoft Delete موجود في الـModel، لكن معنديش DELETE Endpoint منفذه، فمش هقول اني اختبرت ابطال كاش الحذف.
بالنسبه لرابط لازم يتوقف فورا، الوضع الحالي مش ضمان كافي ولازم اتحكم في الاتساق وسياسه فشل الكاش.


السؤال 3
What exactly happens when replication lags, and how does your design handle it?

الاجابه
الكتابه عندي على Primary والقراءه العاديه من Replica، والـReplication شغاله Async باستخدام GTID.
فممكن انشاء الرابط ينجح على Primary والـReplica لسا مطبقتش العمليه.
بعد الانشاء بحدث الكاش من Primary، ولما يحصل Cache Miss بقرا Replica الاول.
لو الرابط مش موجود فيها بروح Primary قبل ما اقول Not Found.
جربت اوقف SQL Thread واعمل رابط، ومسحت كاشه، وبرضو رجع 302 رغم انه لسا مش ظاهر في Replica.
وجربت اوقف Replica بالكامل، وبعد تعديل التعامل مع خطأ الاتصال رجع الطلب من Primary برضو.
بس الحل ده مش بيكشف صف موجود لكنه قديم، يعني ممكن حاله رابط اتعدلت ولسا Replica عندها القديم.
ومش كل استعلامات التطبيق عندها نفس الـFallback، ده معمول في مسار تحميل الرابط.
الـsticky بتفيد القراءه بعد الكتابه على نفس Connection، مش ضمان لكل Request جديده.


السؤال 4
Which value did you denormalize, what is its failure mode, and how is it repaired?

الاجابه
ضفت click_count في جدول links علشان اجيب الاجمالي جاهز بدل ما اعمل COUNT لكل الضغطات.
الـWorker بتدخل الضغطات الخام وتزود العداد وتسجل Batch ID في نفس الـTransaction.
وبعد الـCommit بشيل الدفعة من processing في Redis.
لو نفس الـBatch اتعادت، سجل processed_click_batches بيمنع حسابها مرتين.
جربت اعاده المعالجه وكمان شغلت Workers على دفعات لنفس الرابط واتأكدت ان العدد متحفظ.
العداد مش لحظي، ممكن يتأخر حسب الـScheduler والـQueue، ولو واحده وقفت التأخير يزيد.
عندي stats:rebuild بيصلح الاجمالي من archived_click_count زائد عدد الضغطات الخام الي لسا موجوده.
وجربت لينك عنده 7 ضغطات مؤرشفه و3 خام، رجع الاجمالي 10.
الـRebuild بيستنى قفل الصيانه ومبيشتغلش لو فيه ارشفه لسا مخلصتش.
بس لو الضغطات الخام نفسها فيها تكرار، الـRebuild مش هيشيله، هيعده ولازم اصلح المصدر الاول.


السؤال 5
SQL vs NoSQL revisited: if click volume were 100x bigger, would you keep link_clicks in MariaDB?
If not, which type of store would you move it to, and what would you lose?


الاجابه
هفضل مخزن المستخدمين والروابط والـMetadata في MariaDB، لكن هقيم نقل الضغطات الخام لمخزن تحليلي Column-oriented زي ClickHouse.
لان استخدام الضغطات عندي اغلبه اضافه بيانات وتجميع حسب الوقت والرابط، وده شغل OLAP.
ClickHouse بيستخدم SQL برضو، فالموضوع مش اني لازم اختار NoSQL وخلاص.
ساعتها هحتاج Pipeline لنقل الاحداث وسياسه تمنع التكرار وتتعامل مع اعاده المحاوله.
وهخسر بساطه الـTransaction الحاليه الي بتجمع الضغطات والعداد في نفس الداتابيز.
ومش هفترض نفس ضمانات الـForeign Keys، وهحتاج اتعامل مع تأخر التحليل واتساق الارقام بين المخزنين.
ده اختيار مبدئي لو الحجم زاد، مش حاجه انا نفذتها او قستها على ClickHouse.


السؤال 6
SPOF table. List every component in your running stack and fill in this table using
the failure tests you ran in Parts 3-9:


الاجابه
app1 وapp2
النسخه الواحده مش لوحدها مدخل السيستم، عندي nginx بتوزع على الاتنين.
جربت اوقف app1 وفضل عرض QR شغال من app2، لكن ده مش معناه ان كل Request جاريه مضمونه وقت السقوط.
اختبار ايقاف app2 لوحدها مش مسجل عندي.
في Production هوزع النسخ على اجهزه مستقله مع Health Checks وConnection Draining.

nginx
دي SPOF لان عندي نسخه واحده وهي مدخل HTTP الوحيد.
مجربتش اوقفها كاختبار فشل، لكن من التوصيل واضح ان توقفها هيقطع الدخول للتطبيق.
في Production هستخدم Load Balancer عالي التوافر.

Primary DB
دي SPOF للكتابه لان مفيش Auto Failover منفذ.
جربت استرجاع الانشاء بعد رجوع Primary، وجربت في failoverlab ترقيه Replica يدوي والكتابه عليها رجعت 201.
وقت 179.78 ثانيه كان لحد التحقق من اتصال الكاتب بالـReplica المرقاه، مش وقت مقاس لاول Create ناجحه.
في Production هحتاج Failover منضبط مع Fencing للكاتب القديم وBackups ومراقبه للتأخير.
ومع Async Replication فيه احتمال فقد اخر كتابات لو موصلتش للـReplica قبل السقوط.

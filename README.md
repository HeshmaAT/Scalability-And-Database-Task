# URL Shortener

مشروع URL Shortener باستخدام Laravel 12 وPHP 8.2، مع تجربـة الـCaching والـReplication والـQueues والـPartitioning وقياس الأداء.

## المستخدم في المشروع

- نسختين Laravel PHP FPM خلف nginx.
- MariaDB 10.11 Primary وReplica باستخدام Async GTID Replication.
- Redis 7.4 للكاش بسياسة allkeys-lfu، وRedis منفصلة للـQueue والـLocks والـRate Limiter بسياسة noeviction.
- Worker لمعالجة الضغطات وتوليد QR، وScheduler لإرسال الدفعات وإدارة الـPartitions.
- MinIO لتخزين صور QR باستخدام S3 API.

## المطلوب قبل التشغيل

- Git وDocker Desktop شغّالين، وDocker على Linux Containers.
- Docker Compose v2 حديث.
- الأوامر هنا مكتوبة لـPowerShell على Windows.
- المنافذ 8080 و9002 و9003 تكون متاحة.
- k6 مطلوب لاختبارات الضغط فقط، مش لتشغيل التطبيق.

## التشغيل من Clone جديدة

```powershell
git clone https://github.com/HeshmaAT/Scalability-And-Database-Task.git
Set-Location Scalability-And-Database-Task
Copy-Item .env.example .env
```

افتح .env وظبط اتصال Laravel بالكونتينرات بالقيم التالية.
DB_PASSWORD لازم تكون نفس MARIADB_PASSWORD، وDB_DATABASE نفس MARIADB_DATABASE.
القيم هنا للتجربة المحلية، وليست إعدادات Production.

```dotenv
APP_ENV=local
APP_URL=http://localhost:8080
APP_KEY=

DB_CONNECTION=mysql
DB_HOST=db-primary
DB_PORT=3306
DB_DATABASE=url_shortener
DB_USERNAME=url_app
DB_PASSWORD=local-app-secret
DB_READ_HOST=db-replica
DB_WRITE_HOST=db-primary

MARIADB_ROOT_PASSWORD=local-root-secret
MARIADB_DATABASE=url_shortener
MARIADB_USER=url_app
MARIADB_PASSWORD=local-app-secret
MARIADB_REPLICATION_USER=replicator
MARIADB_REPLICATION_PASSWORD=local-replication-secret

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=array
LOG_CHANNEL=stderr

REDIS_CLIENT=phpredis
REDIS_HOST=redis-cache
REDIS_PORT=6379
REDIS_QUEUE_HOST=redis-queue
REDIS_QUEUE_PORT=6379
REDIS_QUEUE_CONNECTION=queue

MINIO_ROOT_USER=minioadmin
MINIO_ROOT_PASSWORD=local-minio-secret
AWS_ACCESS_KEY_ID=minioadmin
AWS_SECRET_ACCESS_KEY=local-minio-secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=url-shortener
AWS_ENDPOINT=http://minio:9000
AWS_USE_PATH_STYLE_ENDPOINT=true

LINK_CREATE_PER_MINUTE=60
```

ابني الصور، وبعدها ولّد APP_KEY من داخل صورة التطبيق، من غير ما تحتاج PHP محلي.

```powershell
docker compose build
if ($LASTEXITCODE -ne 0) { throw 'Build failed' }

$appKey = docker compose run --rm --no-deps --entrypoint php app1 artisan key:generate --show |
    Where-Object { $_ -match '^base64:' } |
    Select-Object -Last 1

if (-not $appKey) { throw 'APP_KEY generation failed' }

Get-Content .env | ForEach-Object {
    if ($_ -match '^APP_KEY=') { 'APP_KEY=' + $appKey.Trim() } else { $_ }
} | Set-Content .env.new -Encoding utf8

Move-Item -LiteralPath .env.new -Destination .env -Force

docker compose up -d --wait
if ($LASTEXITCODE -ne 0) { throw 'Startup failed' }

docker compose ps
```

الـAPP_KEY تتولد مرة في بداية المشروع. متغيرهاش مع كل تشغيل، لأنها بتدخل في توقيع الروابط وvisitor_hash.
ملف .env نفسه مش بيدخل الصورة؛ Compose بيمرر الإعدادات للكونتينرات.

## Migrations ومستخدم التجربة

```powershell
docker compose exec -e APP_CONFIG_CACHE=/tmp/setup-migrations.php -e DB_READ_HOST=db-primary -e DB_WRITE_HOST=db-primary app1 php artisan migrate

docker compose exec -e APP_CONFIG_CACHE=/tmp/setup-seeder.php -e DB_READ_HOST=db-primary -e DB_WRITE_HOST=db-primary app1 php artisan db:seed --class=DemoUserSeeder

curl.exe -i http://localhost:8080/up
```

الـHealth Endpoint المفروض يرجع 200. المستخدم التجريبي وبيانات دخوله موجودين في database/seeders/DemoUserSeeder.php.
بنفرض القراءة من Primary في أوامر التجهيز علشان متتأثرش بتأخر Replica.

## تجهيز Bucket صور QR

شغّل الأمر مرة بعد تشغيل MinIO. لو الـBucket موجودة بالفعل مبيعملش واحدة تانية.

```powershell
docker compose exec app1 php artisan tinker --execute="if (! \Illuminate\Support\Facades\Storage::disk('s3')->getClient()->doesBucketExistV2(config('filesystems.disks.s3.bucket'))) { \Illuminate\Support\Facades\Storage::disk('s3')->getClient()->createBucket(['Bucket' => config('filesystems.disks.s3.bucket')]); }"
```

- التطبيق: http://localhost:8080
- MinIO Console: http://localhost:9003
- MinIO API من الجهاز: http://localhost:9002
- التطبيق داخل Docker بيتصل بـhttp://minio:9000، مش عنوان localhost الخاص بالجهاز.

## الـEndpoints

| Method | Endpoint | الاستخدام |
|---|---|---|
| POST | /api/login | تسجيل الدخول وإرجاع Sanctum Token |
| POST | /api/links | إنشاء رابط |
| PATCH | /api/links/{id} | تعديل الحالة أو تاريخ الانتهاء |
| GET | /{shortCode} | فتح الرابط المختصر |
| GET | /api/links/{id}/stats | إجمالي الضغطات والزوار والتوزيع اليومي |
| POST | /api/links/{id}/qr | طلب توليد QR |
| GET | /api/links/{id}/qr | حالة QR ورابط عرض الصورة |
| GET | /qr/{linkId} | عرض الصورة بتوقيع مؤقت |
| GET | /up | فحص استجابة Laravel |

Endpoints إدارة الروابط محتاجة Bearer Token والملكية بتتراجع في التطبيق.
الـRedirect العام بيرجع 302 للرابط الصالح، و404 لغير الموجود أو المتوقف، و410 للـActive المنتهي.
إنشاء الرابط يقبل destination_url وcustom_alias وexpires_at الاختياريين.
الـAlias من 3 إلى 32 حرف من الحروف والأرقام وunderscore وhyphen، مع استبعاد الأسماء المحجوزة.
الرابط الأصلي HTTP أو HTTPS وبحد أقصى 2000 حرف. الكود المولد 10 حروف Base62.

Postman Collections موجودة في docs. حط التوكن في Environment محلية، ومتضيفوش للـCollection المرفوعة.

## تشغيل المشروع بعد أول تجهيز

```powershell
docker compose up -d
docker compose ps
docker compose exec app1 php artisan tinker
```

ولإيقافه مع الحفاظ على الكونتينرات والبيانات:

```powershell
docker compose stop
```

البيانات الدائمة في Named Volumes. متحذفش Volumes لو عاوز تحتفظ بالداتابيز وأحداث الـQueue وصور QR.

## بعد تعديل كود PHP

الكود بيتنسخ للصورة، والـOPcache timestamp validation مقفولة، فالحفظ في الـIDE وحده مش بيحدث الكونتينر.

```powershell
docker compose build app1
if ($LASTEXITCODE -ne 0) { throw 'Build failed' }

docker compose up -d --no-deps --force-recreate app1 app2 worker scheduler
if ($LASTEXITCODE -ne 0) { throw 'Container startup failed' }

docker compose restart nginx
```

لو الـBuild فشلت، متكملش على أساس إن الصورة الجديدة اتعملت؛ الصورة القديمة ممكن تفضل شغالة.

## تجهيز داتابيز الاختبارات

```powershell
docker compose exec db-primary mariadb -u root -p
```

استخدم MARIADB_ROOT_PASSWORD من .env، ونفّذ SQL التالي داخل MariaDB.
لو غيرت MARIADB_USER عدّل اسم المستخدم في GRANT.

```sql
CREATE DATABASE IF NOT EXISTS url_shortener_test
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

GRANT ALL PRIVILEGES ON url_shortener_test.* TO 'url_app'@'%';
EXIT;
```

## تشغيل الاختبارات

الاختبارات بتستخدم MariaDB منفصلة بسبب الـPartitioning وRedis فعلية لاختبارات الـTTL والـLocks.
إعداد SQLite الافتراضي في phpunit.xml مش مناسب للاختبارات دي؛ الأوامر التالية بتحدد الاتصال صراحة.

```powershell
docker compose exec app1 touch /var/www/html/.env.testing

docker compose exec -e APP_ENV=testing -e APP_CONFIG_CACHE=/tmp/readme-tests.php -e DB_CONNECTION=mysql -e DB_DATABASE=url_shortener_test -e DB_READ_HOST=db-primary -e DB_WRITE_HOST=db-primary -e CACHE_STORE=redis -e REDIS_PREFIX=part6-concurrency: -e CACHE_PREFIX=readme-tests: app1 php artisan test
```

مهم يكون الاسم url_shortener_test، مش url_shortener، لأن الاختبارات بتعيد تجهيز الجداول.
الـPrefix المحدد بيفصل Redis عن التشغيل العادي، وبيطابق إعداد Child Workers في اختبار التزامن الحالي.
اختبارات الكاش والتزامن ممكن تسيب أحداث تجريبية في الـPrefix ده، فلا تستخدمه لتشغيل التطبيق العادي.
نتائج الاختبارات المنفردة موجودة في التقرير؛ تشغيل المجموعة كلها في النسخة النهائية لازم يتم التحقق منه قبل إعلان نجاحها بالكامل.

## تجهيز البيانات الكبيرة

تشغيل الـAPI لا يحتاج مليون رابط. الأوامر التالية للـBenchmark فقط.
LinkBenchmarkSeeder يحتاج جدول links فاضي، وClickBenchmarkSeeder يحتاج 100000 رابط على الأقل.

```powershell
docker compose exec -e APP_CONFIG_CACHE=/tmp/seed-links.php -e DB_READ_HOST=db-primary -e DB_WRITE_HOST=db-primary app1 php -d memory_limit=512M artisan db:seed --class=LinkBenchmarkSeeder

docker compose exec -e APP_CONFIG_CACHE=/tmp/seed-clicks.php -e DB_READ_HOST=db-primary -e DB_WRITE_HOST=db-primary app1 php -d memory_limit=512M artisan db:seed --class=ClickBenchmarkSeeder
```

الأول بيجهز مليون رابط باستخدام Factory وBulk Insert، والتاني بيجهز 10 مليون ضغطة باستخدام INSERT SELECT وMariaDB Sequences على دفعات.
متشغلش مليون رابط بعد إضافة روابطك العادية وتتوقع إنه يكمل؛ الـSeeder بيرفض الجدول غير الفاضي.

## اختبارات الضغط

ثبت k6 أولًا لو مش موجودة:

```powershell
winget install --id GrafanaLabs.k6 --exact --source winget
```

جهز بيانات الاختبار مرة واحدة، واحتفظ بنفس الملف للمقارنة:

```powershell
New-Item -ItemType Directory -Force work | Out-Null

docker compose exec -T app1 php artisan loadtest:prepare |
    Set-Content .\work\part9-input.json -Encoding utf8
```

الملف فيه توكن صالح 12 ساعة و1000 رابط، ومكانه work المستبعدة من Git. متشاركوش ضمن الأدلة.

```powershell
powershell -ExecutionPolicy Bypass -File .\loadtest\Run-Part9.ps1

powershell -ExecutionPolicy Bypass -File .\loadtest\Run-Part9Cache.ps1
```

Run-Part9 بيعمل Warm up ويشغل A وB وC وD ومقارنة V، ويسجل CPU وRedis وk6، ويرجع الـStack العادية في النهاية.
المقارنة الأساسية عند حمل مستهدف 200 RPS لمدة دقيقتين لكل سيناريو.
في A الـAnalytics بتستخدم Database Buffer وDatabase Queue بدل Redis، فالمقارنة مش إزالة Link Cache بس.
Run-Part9Cache بيختبر Stampede بالقفل وبدونه، ويجرب Eviction في Redis مؤقتة منفصلة.

النتائج الجديدة تحت work. النتائج المسجلة للتسليم في docs، والتفاصيل في docs/Part9_REPORT.md.
الـRate Limit في Profile الاختبار مختلف عن حد المستخدم العادي علشان ما يمنعش قياس السعة.

## أوامر المراقبة والصيانة

```powershell
docker compose logs --tail=50 app1 app2 worker scheduler
docker stats
docker compose exec scheduler php artisan schedule:list
docker compose exec app1 php artisan analytics:flush
docker compose exec app1 php artisan analytics:partitions:ensure
docker compose exec app1 php artisan analytics:partitions:archive
```

أمر archive بدون execute للمعاينة. الجدولة الحالية بتستخدم execute لحفظ الأعداد وحذف الـPartitions المنتهية.
الاحتفاظ بالشهر الحالي وخمس شهور قبله، لكن Partition تاريخية مجمعة ممكن تفضل لحد انتهاء نطاقها كله.
العداد التاريخي محفوظ، إنما تفاصيل الأيام والزوار المختلفين المحذوفة مش محفوظة في الملخص الحالي.

stats:rebuild --link=ID بيعيد العداد من المؤرشف والخام المتبقي، لكنه مش بيصلح تكرار في مصدر الضغطات نفسه.
links:export بيصدر CSV تدريجيًا؛ احفظه في work بدل Git أو Build Context.

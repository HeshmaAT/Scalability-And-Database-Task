$ErrorActionPreference='Stop'
$files=@(
 'docker-compose.yml','docker-compose.lab1.yml','docker-compose.lab2.yml','docker-compose.lab2-network.yml',
 'docker-compose.lab2-http.yml','docker-compose.lab3.yml','docker-compose.lab3-fixed.yml','docker-compose.lab3-egress.yml',
 'docker-compose.lab3-isolated.yml','docker-compose.lab4-origin.yml','docker-compose.lab4-edges.yml',
 'docker-compose.lab4-signed.yml','docker-compose.lab5.yml','docker-compose.lab5-analytics.yml',
 'docker-compose.lab5-active.yml','docker-compose.lab5-milestones.yml','docker-compose.lab5-webhooks.yml',
 'docker-compose.lab6.yml','docker-compose.lab6-reverb.yml'
)
$composeArgs=@();foreach($file in $files){$composeArgs+=@('-f',$file)}
function Invoke-Lab7Docker { $dockerArguments=@($args); & docker compose @composeArgs @dockerArguments; if($LASTEXITCODE -ne 0){throw "Docker failed: $($dockerArguments -join ' ')"} }
Invoke-Lab7Docker config --quiet
$migration='database/migrations/2026_11_25_000001_create_link_lifecycle_events.php'
Invoke-Lab7Docker exec -T -e APP_CONFIG_CACHE=/tmp/lab7-lifecycle-migrate.php -e DB_READ_HOST=db-primary -e DB_WRITE_HOST=db-primary app1 php artisan migrate --force "--path=$migration"
Invoke-Lab7Docker exec -T app1 touch /var/www/html/.env.testing
Invoke-Lab7Docker exec -T -e APP_ENV=testing -e APP_CONFIG_CACHE=/tmp/lab7-outbox-test-migrate.php -e DB_CONNECTION=mysql -e DB_DATABASE=url_shortener_test -e DB_READ_HOST=db-primary -e DB_WRITE_HOST=db-primary app1 php artisan migrate --force --path=database/migrations/2026_11_19_000002_create_milestone_outbox_tables.php
Invoke-Lab7Docker exec -T -e APP_ENV=testing -e APP_CONFIG_CACHE=/tmp/lab7-lifecycle-test-migrate.php -e DB_CONNECTION=mysql -e DB_DATABASE=url_shortener_test -e DB_READ_HOST=db-primary -e DB_WRITE_HOST=db-primary app1 php artisan migrate --force "--path=$migration"
Invoke-Lab7Docker exec -T -e APP_ENV=testing -e APP_CONFIG_CACHE=/tmp/lab7-lifecycle-tests.php -e DB_CONNECTION=mysql -e DB_DATABASE=url_shortener_test -e DB_READ_HOST=db-primary -e DB_WRITE_HOST=db-primary -e CACHE_STORE=array app1 php artisan test '--filter=LifecycleOutboxTest|Lab7SafetyTest|MaintenanceLockTest'
Invoke-Lab7Docker restart app1 app2 event-outbox-relay
Invoke-Lab7Docker restart nginx
python .\loadtest\Run-Lab7-Lifecycle.py
if($LASTEXITCODE -ne 0){throw 'Lifecycle drills failed; send output. Saved evidence is under work.'}

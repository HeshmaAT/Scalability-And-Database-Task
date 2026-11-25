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
Invoke-Lab7Docker up -d --no-build
Invoke-Lab7Docker restart nginx
Invoke-Lab7Docker exec -T app1 php artisan events:init
Invoke-Lab7Docker exec -T app1 touch /var/www/html/.env.testing
Invoke-Lab7Docker exec -T -e APP_ENV=testing -e APP_CONFIG_CACHE=/tmp/lab7-safety-tests.php -e DB_CONNECTION=mysql -e DB_DATABASE=url_shortener_test -e DB_READ_HOST=db-primary -e DB_WRITE_HOST=db-primary -e CACHE_STORE=array app1 php artisan test '--filter=Lab7SafetyTest|MaintenanceLockTest|PrimaryTokenTest'
Invoke-Lab7Docker restart analytics-1 analytics-2 milestone-consumer
python .\loadtest\Run-Lab7-Crash-Poison.py
if($LASTEXITCODE -ne 0){throw 'Lab 7 crash/poison drills failed; send the output. Saved evidence is under work.'}

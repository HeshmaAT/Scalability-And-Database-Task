$ErrorActionPreference = 'Stop'
$projectPath = (Get-Location).Path
$files = @(
 'docker-compose.yml','docker-compose.lab1.yml','docker-compose.lab2.yml','docker-compose.lab2-network.yml',
 'docker-compose.lab2-http.yml','docker-compose.lab3.yml','docker-compose.lab3-fixed.yml','docker-compose.lab3-egress.yml',
 'docker-compose.lab3-isolated.yml','docker-compose.lab4-origin.yml','docker-compose.lab4-edges.yml',
 'docker-compose.lab4-signed.yml','docker-compose.lab5.yml','docker-compose.lab5-analytics.yml',
 'docker-compose.lab5-active.yml','docker-compose.lab5-milestones.yml','docker-compose.lab5-webhooks.yml',
 'docker-compose.lab6.yml','docker-compose.lab6-reverb.yml'
)
$composeArgs=@();foreach($file in $files){$composeArgs+=@('-f',$file)}
function Invoke-Lab6Docker { $dockerArguments=@($args); & docker compose @composeArgs @dockerArguments; if($LASTEXITCODE -ne 0){throw "Docker failed: $($dockerArguments -join ' ')"} }

Invoke-Lab6Docker exec -T app1 touch /var/www/html/.env.testing
Invoke-Lab6Docker exec -T -e APP_ENV=testing -e APP_CONFIG_CACHE=/tmp/lab6-lock-wait-tests.php -e DB_CONNECTION=mysql -e DB_DATABASE=url_shortener_test -e DB_READ_HOST=db-primary -e DB_WRITE_HOST=db-primary -e CACHE_STORE=array app1 php artisan test --filter=MaintenanceLockTest
Invoke-Lab6Docker restart analytics-1 analytics-2 dashboard-consumer
Write-Host 'Lock-wait fix applied. Starting the full 60-second WebSocket verification.'
& powershell -ExecutionPolicy Bypass -File .\loadtest\Run-Lab6-WebSocket-Check.ps1
if($LASTEXITCODE -ne 0){throw 'Verification failed; send output. Measurement files remain saved.'}

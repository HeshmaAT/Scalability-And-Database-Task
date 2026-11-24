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
if (!(Test-Path .\work\lab6-demo-private.json)) { throw 'Run Start-Lab6-Dashboard.ps1 first to prepare the demo account.' }
Invoke-Lab6Docker config --quiet
Invoke-Lab6Docker exec -T -e APP_CONFIG_CACHE=/tmp/lab6-ledger-test-schema.php -e DB_CONNECTION=mysql -e DB_DATABASE=url_shortener_test -e DB_READ_HOST=db-primary -e DB_WRITE_HOST=db-primary app1 php artisan migrate --path=database/migrations/2026_11_19_000001_create_stream_processed_events.php
Invoke-Lab6Docker exec -T app1 touch /var/www/html/.env.testing

Invoke-Lab6Docker exec -T -e APP_ENV=testing -e LAB6_DASHBOARD_ENABLED=true -e APP_CONFIG_CACHE=/tmp/lab6-reverb-tests.php -e DB_CONNECTION=mysql -e DB_DATABASE=url_shortener_test -e DB_READ_HOST=db-primary -e DB_WRITE_HOST=db-primary -e CACHE_STORE=array app1 php artisan test '--filter=LinkDashboardTest|RealtimeChannelTest|DashboardProjectionTest'
Invoke-Lab6Docker cp .\loadtest\lab6-dashboard-ready.php app1:/tmp/lab6-ready.php
Invoke-Lab6Docker exec -T app1 php /tmp/lab6-ready.php
$appId=(& docker compose @composeArgs ps -q app1).Trim()
$networks=(& docker inspect --format '{{json .NetworkSettings.Networks}}' $appId)|ConvertFrom-Json
if($LASTEXITCODE -ne 0){throw 'Cannot inspect app network'}
$networkName=($networks.PSObject.Properties|Select-Object -First 1).Name
$appIp=$networks.$networkName.IPAddress
& docker run --rm --network $networkName --mount "type=bind,source=$projectPath,target=/workspace" -e "LAB6_APP_A_IP=$appIp" --entrypoint node node:22-alpine /workspace/work/lab6-client/smoke.cjs
if($LASTEXITCODE -ne 0){throw 'Reverb end-to-end smoke test failed; inspect dashboard-consumer and Reverb logs.'}
Write-Host 'Ready: http://localhost:8080/lab6 - use your existing demo login and select WebSockets.'
Write-Host 'Smoke evidence: work/lab6-reverb-smoke.json'

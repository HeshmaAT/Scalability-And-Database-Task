$ErrorActionPreference = 'Stop'
$files = @(
 'docker-compose.yml','docker-compose.lab1.yml','docker-compose.lab2.yml',
 'docker-compose.lab2-network.yml','docker-compose.lab2-http.yml','docker-compose.lab3.yml',
 'docker-compose.lab3-fixed.yml','docker-compose.lab3-egress.yml','docker-compose.lab3-isolated.yml',
 'docker-compose.lab4-origin.yml','docker-compose.lab4-edges.yml','docker-compose.lab4-signed.yml',
 'docker-compose.lab5.yml','docker-compose.lab5-analytics.yml','docker-compose.lab5-active.yml',
 'docker-compose.lab5-milestones.yml','docker-compose.lab5-webhooks.yml','docker-compose.lab6.yml'
)
$composeArgs = @()
foreach ($file in $files) { $composeArgs += @('-f',$file) }
function Invoke-Lab6Docker {
 $dockerArguments = @($args)
 & docker compose @composeArgs @dockerArguments
 if ($LASTEXITCODE -ne 0) { throw "Docker command failed: $($dockerArguments -join ' ')" }
}
Invoke-Lab6Docker config --quiet
Invoke-Lab6Docker up -d
Invoke-Lab6Docker restart nginx
Invoke-Lab6Docker exec -T app1 touch /var/www/html/.env.testing
Invoke-Lab6Docker exec -T -e APP_ENV=testing -e LAB6_DASHBOARD_ENABLED=true -e APP_CONFIG_CACHE=/tmp/lab6-dashboard-tests.php -e DB_CONNECTION=mysql -e DB_DATABASE=url_shortener_test -e DB_READ_HOST=db-primary -e DB_WRITE_HOST=db-primary -e CACHE_STORE=array app1 php artisan test --filter=LinkDashboardTest
New-Item -ItemType Directory -Force .\work | Out-Null
$demoOutput = & docker compose @composeArgs exec -T app1 php artisan lab6:prepare
if ($LASTEXITCODE -ne 0) { throw 'Demo preparation failed' }
$demo = ($demoOutput -join "`n") | ConvertFrom-Json
$demo | ConvertTo-Json | Set-Content -LiteralPath .\work\lab6-demo-private.json -Encoding UTF8
Write-Host ''
Write-Host 'Dashboard: http://localhost:8080/lab6'
Write-Host "Email: $($demo.email)"
Write-Host "Password: $($demo.password)"
Write-Host "Demo link: $($demo.short_code) (#$($demo.link_id))"
Write-Host 'Log in, select the link, start monitoring, then send 5 clicks.'
Write-Host 'Reverb and the 200-viewer comparison are the next stages.'

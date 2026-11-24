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
Invoke-Lab6Docker up -d --no-deps nginx
Invoke-Lab6Docker exec -T nginx nginx -t
foreach ($asset in @('/lab6','/lab6/dashboard.css','/lab6/dashboard.js')) {
    $response = Invoke-WebRequest -Uri ('http://localhost:8080' + $asset) -UseBasicParsing
    if ($response.StatusCode -ne 200) { throw "Unexpected status for $asset" }
    if ($asset.EndsWith('.css') -and $response.Headers['Content-Type'] -notmatch 'text/css') { throw 'Stylesheet did not return CSS' }
    if ($asset.EndsWith('.js') -and $response.Headers['Content-Type'] -notmatch 'javascript') { throw 'Script did not return JavaScript' }
    Write-Host "$asset -> HTTP $($response.StatusCode)"
}
Write-Host 'Open http://localhost:8080/lab6 and use your existing demo login.'

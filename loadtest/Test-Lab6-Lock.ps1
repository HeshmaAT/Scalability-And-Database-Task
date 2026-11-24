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

Invoke-Lab6Docker config --quiet
$runName='lab6-lock-probe-'+(Get-Date -Format 'yyyyMMdd-HHmmss')
$resultPath=Join-Path $projectPath ('work\'+$runName)
New-Item -ItemType Directory -Force $resultPath | Out-Null
$demo=Get-Content .\work\lab6-demo-private.json -Raw | ConvertFrom-Json
Invoke-Lab6Docker cp .\loadtest\lab6-comparison-fixture.php app1:/tmp/lab6-comparison-fixture.php
Invoke-Lab6Docker cp .\loadtest\lab6-lock-probe.php app1:/tmp/lab6-lock-probe.php
$private=& docker compose @composeArgs exec -T app1 php /tmp/lab6-comparison-fixture.php $demo.email
if($LASTEXITCODE -ne 0){throw 'Cannot create probe fixture'}
$fixture=$private | Where-Object { $_.Trim().StartsWith('{') } | Select-Object -Last 1 | ConvertFrom-Json
if(!$fixture.link_id){throw 'Invalid fixture'}
$fixtureJson=$fixture | ConvertTo-Json -Compress
$fixtureBase64=[Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($fixtureJson))
$appId=(& docker compose @composeArgs ps -q app1).Trim()
$networks=(& docker inspect --format '{{json .NetworkSettings.Networks}}' $appId)|ConvertFrom-Json
$networkName=($networks.PSObject.Properties | Where-Object { $_.Name -match '_default$' } | Select-Object -First 1).Name
if(!$networkName){throw 'Default network not found'}
$mapping=@()
foreach($service in @('analytics-1','analytics-2','worker','scheduler','app1','app2','milestone-consumer','event-outbox-relay','webhook-consumer','webhook-delivery','dashboard-consumer')){
    $id=(& docker compose @composeArgs ps -q $service)
    if($id){
        $nets=(& docker inspect --format '{{json .NetworkSettings.Networks}}' $id.Trim()) | ConvertFrom-Json
        foreach($entry in $nets.PSObject.Properties){$mapping+=@{service=$service;ip=$entry.Value.IPAddress}}
    }
}
$mapping | ConvertTo-Json | Set-Content -Encoding UTF8 (Join-Path $resultPath 'container-ip-map.json')
$sampler=Start-Job -ArgumentList $projectPath,$composeArgs -ScriptBlock {
    param($directory,$composeFiles)
    Set-Location -LiteralPath $directory
    & docker compose @composeFiles exec -T app1 php /tmp/lab6-lock-probe.php sample
    if($LASTEXITCODE -ne 0){throw 'Lock sampling failed'}
}
try {
    & docker run --rm --network $networkName --mount "type=bind,source=$projectPath,target=/workspace" -e "LAB6_PROBE_FIXTURE_B64=$fixtureBase64" --entrypoint node node:22-alpine /workspace/loadtest/lab6-lock-clicks.cjs
    if($LASTEXITCODE -ne 0){throw 'Probe clicks failed'}
    $null=Wait-Job $sampler -Timeout 20
    if($sampler.State -ne 'Completed'){throw 'Lock sampler did not complete'}
    Receive-Job $sampler -ErrorAction Stop | Set-Content -Encoding UTF8 (Join-Path $resultPath 'lock-samples.json')
}finally{if($sampler.State -eq 'Running'){Stop-Job $sampler};Remove-Job $sampler}
& docker compose @composeArgs exec -T app1 php /tmp/lab6-lock-probe.php stages $fixture.link_id | Set-Content -Encoding UTF8 (Join-Path $resultPath 'stage-timing.json')
if($LASTEXITCODE -ne 0){throw 'Cannot read stage timings'}
python .\loadtest\Summarize-Lab6-Lock.py $resultPath
if($LASTEXITCODE -ne 0){throw 'Cannot summarize lock samples'}
Write-Host "Results: $resultPath"

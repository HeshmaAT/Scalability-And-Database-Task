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
if (!(Test-Path .\work\lab6-client\smoke.cjs)) {throw 'Complete the Reverb smoke test first.'}
& docker info --format 'Docker CPUs={{.NCPU}} MemoryBytes={{.MemTotal}}'
$runName='lab6-ws-check-'+(Get-Date -Format 'yyyyMMdd-HHmmss')
$resultPath=Join-Path $projectPath ('work\'+$runName)
New-Item -ItemType Directory -Force $resultPath | Out-Null
$appId=(& docker compose @composeArgs ps -q app1).Trim()
$networks=(& docker inspect --format '{{json .NetworkSettings.Networks}}' $appId)|ConvertFrom-Json
$networkName=($networks.PSObject.Properties | Where-Object { $_.Name -match '_default$' } | Select-Object -First 1).Name
if(!$networkName){throw 'Default application network not found'}
$ids=@(& docker compose @composeArgs ps -q app1 app2 nginx reverb-1 reverb-2 dashboard-consumer analytics-1 analytics-2 redis-reverb redis-streams db-primary)
if($LASTEXITCODE -ne 0){throw 'Cannot list benchmark containers'}
foreach($service in @('app1','app2')){
    $settings=& docker compose @composeArgs exec -T $service sh -c 'php-fpm -tt 2>&1'
    $settings | Select-String 'pm =|pm.max_children|pm.start_servers|pm.min_spare_servers|pm.max_spare_servers' | Set-Content -Encoding UTF8 (Join-Path $resultPath ($service+'-fpm.txt'))
}
if(!(Test-Path .\work\lab6-demo-private.json)){throw 'Existing dashboard demo credentials are required'}
$demo=Get-Content .\work\lab6-demo-private.json -Raw | ConvertFrom-Json
Invoke-Lab6Docker cp .\loadtest\lab6-comparison-fixture.php app1:/tmp/lab6-comparison-fixture.php
Invoke-Lab6Docker cp .\loadtest\lab6-comparison-history.php app1:/tmp/lab6-comparison-history.php
Invoke-Lab6Docker cp .\loadtest\lab6-dashboard-ready.php app1:/tmp/lab6-dashboard-ready.php
Invoke-Lab6Docker exec -T app1 php /tmp/lab6-dashboard-ready.php
$cpuFile=Join-Path $resultPath 'resources.jsonl'
$stopFile=Join-Path $resultPath 'stop-sampling'
$cpuJob=Start-Job -ArgumentList $ids,$cpuFile,$stopFile -ScriptBlock {
    param($containerIds,$destination,$stop)
    while(!(Test-Path -LiteralPath $stop)){
        $sampleTime=(Get-Date).ToUniversalTime().ToString('o')
        & docker stats --no-stream --format '{{json .}}' @containerIds 2>$null | ForEach-Object {
            try { @{sampled_at=$sampleTime;stats=($_|ConvertFrom-Json)} | ConvertTo-Json -Compress -Depth 5 | Add-Content -Encoding UTF8 -LiteralPath $destination } catch {}
        }
        Start-Sleep -Seconds 2
    }
}
try {
    # Long polling is last so queued PHP requests cannot contaminate another phase.
    foreach($mode in @('websocket')){
        $private=& docker compose @composeArgs exec -T app1 php /tmp/lab6-comparison-fixture.php $demo.email
        if($LASTEXITCODE -ne 0){throw 'Cannot create isolated benchmark fixture'}
        $fixture=$private | Where-Object { $_.Trim().StartsWith('{') } | Select-Object -Last 1 | ConvertFrom-Json
        if(!$fixture.link_id){throw 'Fixture was not valid JSON'}
        $fixture | Add-Member -NotePropertyName email -NotePropertyValue $demo.email
        $fixture | Add-Member -NotePropertyName password -NotePropertyValue $demo.password
        $fixture | ConvertTo-Json | Set-Content -Encoding UTF8 (Join-Path $resultPath ($mode+'-private.json'))
        Write-Host "Phase: $mode - isolated fixture ready. Credentials stay in ignored work directory."
        & docker run --rm --network $networkName --mount "type=bind,source=$projectPath,target=/workspace" -e "LAB6_RESULT_DIR=/workspace/work/$runName" -e "LAB6_MODE=$mode" -e LAB6_CAPTURE_ONLY=1 --entrypoint node node:22-alpine /workspace/loadtest/lab6-compare.cjs
        if($LASTEXITCODE -ne 0){throw "Phase $mode failed; evidence remains under $resultPath"}

        if($mode -eq 'long'){
            Write-Host 'Measurements saved. Releasing busy long-poll PHP workers...'
            Invoke-Lab6Docker restart app1 app2
            Invoke-Lab6Docker restart nginx
        }
        $phaseResult=Get-Content (Join-Path $resultPath ($mode+'-result.json')) -Raw | ConvertFrom-Json
        $history=& docker compose @composeArgs exec -T app1 php /tmp/lab6-comparison-history.php $fixture.link_id $phaseResult.clickSuccess
        if($LASTEXITCODE -ne 0){throw 'CLI history collection failed; measurement files remain saved'}
        $history | Set-Content -Encoding UTF8 (Join-Path $resultPath ($mode+'-cli-history.json'))
        python .\loadtest\Finalize-Lab6-Comparison.py $resultPath $mode
        if($LASTEXITCODE -ne 0){throw 'Offline finalization failed; measurements remain saved'}
        Start-Sleep -Seconds 2
    }
} finally {
    New-Item -ItemType File -Force $stopFile | Out-Null
    $null=Wait-Job $cpuJob -Timeout 8
    if($cpuJob.State -eq 'Running'){Stop-Job $cpuJob}
    Receive-Job $cpuJob | Out-Null
    Remove-Job $cpuJob
}
Get-Content (Join-Path $resultPath 'websocket-result.json') -Raw
$ErrorActionPreference='Continue'
& docker compose @composeArgs logs --since 3m --tail 25 dashboard-consumer analytics-1 analytics-2 2>&1 | Set-Content -Encoding UTF8 (Join-Path $resultPath 'consumer-diagnostics.log')
$ErrorActionPreference='Stop'
Write-Host "Results: $resultPath"
Write-Host 'Share websocket-result.json and consumer-diagnostics.log. Do not share the private fixture JSON.'

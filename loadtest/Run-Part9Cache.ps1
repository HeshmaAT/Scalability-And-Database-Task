$ErrorActionPreference='Stop'
$repo=Split-Path $PSScriptRoot -Parent
Set-Location $repo
$out=Join-Path $repo ('work\part9-cache-'+(Get-Date -Format 'yyyyMMdd-HHmmss'))
New-Item -ItemType Directory -Force $out | Out-Null
function Invoke-Docker([string[]]$DockerArgs) {
    & docker @DockerArgs
    if($LASTEXITCODE -ne 0) { throw ('Docker failed: '+($DockerArgs -join ' ')) }
}
$fixture=Get-Content 'work\part9-input.json' -Raw | ConvertFrom-Json
$code=$fixture.codes[0]
if(-not $code) { throw 'Fixture code missing.' }
Invoke-Docker @('compose','build','app1')
Invoke-Docker @('compose','up','-d','--wait','db-primary','db-replica','redis-cache','redis-queue')
Invoke-Docker @('compose','up','-d','--no-deps','--force-recreate','app1','app2','worker','scheduler')
Invoke-Docker @('compose','restart','nginx')
Write-Host 'Measuring cache stampede...'
$stampede=& docker compose exec -T app1 php artisan loadtest:stampede $code
if($LASTEXITCODE -ne 0) { throw 'Stampede experiment failed.' }
$stampede | Set-Content (Join-Path $out 'stampede.json') -Encoding utf8
$stampede -join "`n" | ConvertFrom-Json | Select-Object without_lock,with_lock | Format-List
$appId=(& docker compose ps -q app1).Trim()
$details=(& docker inspect $appId | ConvertFrom-Json)[0]
$network=@($details.NetworkSettings.Networks.PSObject.Properties.Name)[0]
if(-not $network) { throw 'Docker network not found.' }
$scratch='part9-eviction-'+([guid]::NewGuid().ToString('N').Substring(0,10))
$created=$false
try {
    Invoke-Docker @('run','-d','--rm','--name',$scratch,'--network',$network,'--memory','128m','--cpus','0.5','redis:7.4','redis-server','--maxmemory','16mb','--maxmemory-policy','allkeys-lfu','--appendonly','no') | Out-Null
    $created=$true
    Start-Sleep -Seconds 2
    Write-Host 'Measuring eviction in disposable Redis...'
    $eviction=& docker compose exec -T app1 php artisan loadtest:eviction $scratch
    if($LASTEXITCODE -ne 0) { throw 'Eviction experiment failed.' }
    $eviction | Set-Content (Join-Path $out 'eviction.json') -Encoding utf8
    $eviction -join "`n" | ConvertFrom-Json | Format-List
} finally {
    if($created) { & docker rm -f -v $scratch | Out-Null }
    Write-Host "Results: $out"
}

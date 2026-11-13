[CmdletBinding()]
param(
    [int]$Rate = 200,
    [ValidateRange(30,600)][int]$Seconds = 120,
    [ValidateSet('A','B','C','D','V')][string[]]$Profiles = @('A','B','C','D','V')
)
$ErrorActionPreference = 'Stop'
$repo = Split-Path $PSScriptRoot -Parent
Set-Location $repo
$run = Join-Path $repo ('work\part9-suite-'+(Get-Date -Format 'yyyyMMdd-HHmmss'))
New-Item -ItemType Directory -Force $run | Out-Null
$inputFile = Join-Path $repo 'work\part9-input.json'
if (-not (Test-Path $inputFile)) { throw 'Missing work/part9-input.json. Restore the same fixture first.' }
$k6 = Join-Path $env:ProgramFiles 'k6\k6.exe'
if (-not (Test-Path $k6)) { throw 'k6 executable not found.' }
function Docker-Run([string[]]$DockerArgs) {
    & docker @DockerArgs
    if ($LASTEXITCODE -ne 0) { throw ('Docker failed: '+($DockerArgs -join ' ')) }
}
function State {
    $text = & docker compose exec -T app1 php artisan loadtest:state
    if ($LASTEXITCODE -ne 0) { throw 'State check failed.' }
    return ($text -join "`n" | ConvertFrom-Json)
}
function Drain {
    for ($i=0; $i -lt 45; $i++) {
        $s = State
        if (($s.pending + $s.processing + $s.jobs) -eq 0) { return $s }
        if (($i % 5) -eq 0) { Docker-Run @('compose','exec','-T','app1','php','artisan','analytics:flush') | Out-Null }
        Start-Sleep -Seconds 2
    }
    throw 'Analytics did not drain within the observation budget; preserve results and investigate.'
}
function Redis-Stats {
    $map=@{}
    $lines=& docker compose exec -T redis-cache redis-cli INFO stats
    if ($LASTEXITCODE -ne 0) { throw 'Redis stats unavailable.' }
    foreach($line in $lines) {
        if($line -match '^([^:#]+):(\d+)\s*$') { $map[$matches[1]]=[long]$matches[2] }
    }
    if(-not $map.ContainsKey('keyspace_hits')) { throw 'Missing Redis stats counters.' }
    return $map
}
function Profile-File([string]$name) {
    $count=1; $cpu='1.0'; $mem='512m'; $read='db-primary'; $queue='redis'; $cache='redis'
    if($name -in @('C','D','V')) { $read='db-replica' }
    if($name -eq 'D') { $count=2 }
    if($name -eq 'V') { $cpu='2.0'; $mem='1g' }
    if($name -eq 'A') { $queue='database'; $cache='database' }
    $upstream='    server app1:9000;'
    if($count -eq 2) { $upstream+="`n    server app2:9000;" }
    $nginx=@'
upstream laravel {
UPSTREAM
}
server {
    listen 80;
    root /var/www/html/public;
    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ ^/index\.php(/|$) {
        include fastcgi_params;
        fastcgi_pass laravel;
        fastcgi_param SCRIPT_FILENAME /var/www/html/public/index.php;
        fastcgi_param DOCUMENT_ROOT /var/www/html/public;
    }
    location ~ \.php$ { return 404; }
    location ~ /\.(?!well-known).* { deny all; }
}
'@
    $nginx.Replace('UPSTREAM',$upstream) | Set-Content 'work\part9-suite-nginx.conf' -Encoding ascii
    $yaml=@"
services:
  app1:
    cpus: '$cpu'
    mem_limit: $mem
    environment: &benchmark-env
      PART9_PROFILE: '$name'
      LINK_CREATE_PER_MINUTE: '10000'
      DB_READ_HOST: '$read'
      DB_WRITE_HOST: 'db-primary'
      QUEUE_CONNECTION: '$queue'
      CACHE_STORE: '$cache'
    volumes:
      - ./docker/php/part9-suite-fpm.conf:/usr/local/etc/php-fpm.d/zz-part9-suite.conf:ro
  app2:
    cpus: '1.0'
    mem_limit: 512m
    environment: *benchmark-env
    volumes:
      - ./docker/php/part9-suite-fpm.conf:/usr/local/etc/php-fpm.d/zz-part9-suite.conf:ro
  worker:
    cpus: '1.0'
    mem_limit: 512m
    environment: *benchmark-env
    command: ['php', 'artisan', 'queue:work', '$queue', '--sleep=1', '--tries=3', '--timeout=60']
  scheduler:
    cpus: '0.25'
    mem_limit: 256m
    environment: *benchmark-env
  nginx:
    cpus: '0.5'
    mem_limit: 128m
    volumes:
      - ./work/part9-suite-nginx.conf:/etc/nginx/conf.d/default.conf:ro
  db-primary:
    cpus: '2.0'
    mem_limit: 2g
  db-replica:
    cpus: '1.0'
    mem_limit: 1g
  redis-cache:
    cpus: '0.5'
    mem_limit: 256m
  redis-queue:
    cpus: '0.5'
    mem_limit: 256m
"@
    $path=Join-Path $repo 'work\part9-suite.compose.yml'
    $yaml | Set-Content $path -Encoding ascii
    Copy-Item $path (Join-Path $run "$name-compose.yml")
    return @{File=$path;Count=$count}
}
function K6-Run([string]$name,[int]$seconds,[int]$rate,[string]$output) {
    $warmFlag='0'
    if($name.EndsWith('-warmup')) { $warmFlag='1' }
    & $k6 run --quiet -e "RATE=$rate" -e "DURATION=$($seconds)s" -e "PROFILE=$name" -e "WARMUP=$warmFlag" -e "RESULT_FILE=$output" .\loadtest\part9-suite.js | Out-Host
    $code=$LASTEXITCODE
    if($code -notin @(0,99)) { throw "k6 could not execute: exit $code" }
    if(-not (Test-Path $output)) { throw 'k6 summary file was not created.' }
    return $code
}
$rows=New-Object System.Collections.Generic.List[object]
$active=$false
try {
    Write-Host 'Building once and checking the new database buffer...'
    Docker-Run @('compose','build','app1')
    Docker-Run @('compose','up','-d','--wait','db-primary','db-replica','redis-cache','redis-queue')
    Docker-Run @('compose','up','-d','--no-deps','--force-recreate','app1','app2','worker','scheduler')
    $active=$true
    Docker-Run @('compose','exec','-T','-e','APP_CONFIG_CACHE=/tmp/part9-migrations.php','-e','DB_READ_HOST=db-primary','-e','DB_WRITE_HOST=db-primary','app1','php','artisan','migrate')
    Docker-Run @('compose','exec','-T','app1','touch','/var/www/html/.env.testing')
    Docker-Run @('compose','exec','-T','-e','APP_ENV=testing','-e','APP_CONFIG_CACHE=/tmp/part9-buffer-test.php','-e','DB_CONNECTION=mysql','-e','DB_DATABASE=url_shortener_test','-e','DB_READ_HOST=db-primary','-e','DB_WRITE_HOST=db-primary','-e','CACHE_STORE=array','app1','php','artisan','test','--filter=Part9DatabaseBufferTest')
    Drain | Out-Null
    & docker info --format 'CPUs={{.NCPU}} MemoryBytes={{.MemTotal}}' | Set-Content (Join-Path $run 'docker-resources.txt')
    Get-CimInstance Win32_Processor | Select-Object Name,NumberOfCores,NumberOfLogicalProcessors | ConvertTo-Json | Set-Content (Join-Path $run 'machine.json')
    foreach($name in $Profiles) {
        Write-Host "`n=== Scenario $name ===" -ForegroundColor Cyan
        Docker-Run @('compose','stop','nginx','app1','app2','worker','scheduler','minio')
        $profile=Profile-File $name
        $argsCompose=@('compose','-f','docker-compose.yml','-f',$profile.File)
        $deps=@('db-primary')
        if($name -eq 'A') {
            Docker-Run @('compose','stop','redis-cache','redis-queue','db-replica')
        } else {
            $deps+=@('redis-cache','redis-queue')
            if($name -in @('C','D','V')) { $deps+='db-replica' }
            else { Docker-Run @('compose','stop','db-replica') }
        }
        Docker-Run ($argsCompose+@('up','-d','--wait')+$deps)
        $services=@('app1','worker','scheduler')
        if($profile.Count -eq 2) { $services+='app2' }
        Docker-Run ($argsCompose+@('up','-d','--no-deps','--force-recreate')+$services)
        Docker-Run ($argsCompose+@('up','-d','--no-deps','--force-recreate','nginx'))
        $ids=@(& docker compose ps -q)
        & docker inspect --format '{{.Name}} CPU={{.HostConfig.NanoCpus}} Memory={{.HostConfig.Memory}}' @ids | Set-Content (Join-Path $run "$name-limits.txt")
        # The same deterministic warm-up precedes every measured profile.
        $warm=Join-Path $run "$name-warmup.json"
        K6-Run "$name-warmup" 15 100 $warm | Out-Null
        $warmData=Get-Content $warm -Raw | ConvertFrom-Json
        if($warmData.result.error_rate -gt 0) { throw "Warm-up requests failed in $name; check token and logs." }
        Drain | Out-Null
        # Warm cached links are kept; no FLUSHDB or global counter reset.
        $initial=State
        $before=$null
        if($name -ne 'A') { $before=Redis-Stats }
        $cpuPath=Join-Path $run "$name-cpu.txt"
        $sampler=Start-Job -ArgumentList $cpuPath -ScriptBlock {
            param($path)
            while($true) {
                & docker stats --no-stream --format '{{.Name}};{{.CPUPerc}};{{.MemUsage}}' | ForEach-Object {
                    if($_ -match '(scalability-and-database-task-[^;\s]+);\s*(\d+(?:\.\d+)?)%;([^\x1b\r\n]+)') {
                        ('{0};{1}' -f (Get-Date).ToUniversalTime().ToString('o'),$matches[0]) | Add-Content -LiteralPath $path -Encoding utf8
                    }
                }
                Start-Sleep -Seconds 2
            }
        }
        $started=(Get-Date).ToUniversalTime()
        $summary=Join-Path $run "$name-k6.json"
        try { $exit=K6-Run $name $Seconds $Rate $summary }
        finally { Stop-Job $sampler; Receive-Job $sampler | Out-Null; Remove-Job $sampler }
        $finished=(Get-Date).ToUniversalTime()
        $hitRatio=$null
        if($name -ne 'A') {
            $after=Redis-Stats
            $hits=$after['keyspace_hits']-$before['keyspace_hits']
            $misses=$after['keyspace_misses']-$before['keyspace_misses']
            if($hits -lt 0 -or $misses -lt 0) { throw 'Redis counters reset during measurement.' }
            if(($hits+$misses) -gt 0) { $hitRatio=[math]::Round(100*$hits/($hits+$misses),2) }
            @{hits=$hits;misses=$misses;server_hit_percent=$hitRatio;evictions=($after['evicted_keys']-$before['evicted_keys'])} | ConvertTo-Json | Set-Content (Join-Path $run "$name-redis.json")
        }
        $endState=State
        $endState | ConvertTo-Json | Set-Content (Join-Path $run "$name-queue-end.json")
        $drained=Drain
        if($drained.failed_jobs -gt $initial.failed_jobs) { throw "New failed jobs in $name. Preserve results and inspect." }
        $drainSeconds=[math]::Round(((Get-Date).ToUniversalTime()-$finished).TotalSeconds,2)
        $from=$started.ToString('yyyy-MM-ddTHH:mm:ssZ');$until=$finished.ToString('yyyy-MM-ddTHH:mm:ssZ')
        & docker compose logs --since $from --until $until app1 app2 worker 2>&1 | Select-String 'max_children|seems busy|WARNING|ERROR|FAIL' | Set-Content (Join-Path $run "$name-warnings.txt")
        $cpu=@(Get-Content $cpuPath | ForEach-Object {
            if($_ -match '^([^;]+);([^;]+);(\d+(?:\.\d+)?)%;') {
                $t=[datetimeoffset]::Parse($matches[1])
                if($t.UtcDateTime -ge $started -and $t.UtcDateTime -le $finished) {
                    [pscustomobject]@{Name=$matches[2];CPU=[double]::Parse($matches[3],[cultureinfo]::InvariantCulture)}
                }
            }
        })
        $cpu | Group-Object Name | ForEach-Object {
            $m=$_.Group | Measure-Object CPU -Average -Maximum
            [pscustomobject]@{Container=$_.Name;Samples=$_.Count;AverageCPU=[math]::Round($m.Average,2);MaxSampledCPU=[math]::Round($m.Maximum,2)}
        } | Export-Csv (Join-Path $run "$name-cpu.csv") -NoTypeInformation -Encoding utf8
        $result=(Get-Content $summary -Raw | ConvertFrom-Json).result
        $result | Add-Member -NotePropertyName RedisServerHitPercent -NotePropertyValue $hitRatio
        $result | Add-Member -NotePropertyName DrainSeconds -NotePropertyValue $drainSeconds
        $result | Add-Member -NotePropertyName ExitCode -NotePropertyValue $exit
        $result | Add-Member -NotePropertyName StartUTC -NotePropertyValue $from
        $result | Add-Member -NotePropertyName EndUTC -NotePropertyValue $until
        $rows.Add($result)
        $rows | Export-Csv (Join-Path $run 'comparison.csv') -NoTypeInformation -Encoding utf8
        $rows | Format-Table profile,actual_rps,error_rate,dropped,redirect_p99_ms,RedisServerHitPercent
    }
} finally {
    if($active) {
        Write-Host 'Restoring the normal Compose stack (no volumes removed)...'
        & docker compose stop nginx app1 app2 worker scheduler
        & docker compose up -d
        & docker compose restart nginx
    }
    Write-Host "Results: $run"
}

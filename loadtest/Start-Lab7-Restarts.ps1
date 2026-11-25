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
Invoke-Lab7Docker restart app1 app2 analytics-1 analytics-2 milestone-consumer dashboard-consumer webhook-consumer
Invoke-Lab7Docker restart nginx
python .\loadtest\Run-Lab7-Restarts.py
if($LASTEXITCODE -ne 0){throw 'Restart drill failed; send output. Services stopped by the drill are started in finally.'}

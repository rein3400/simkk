$token = (Get-Content 'D:/users/stefa/project/sim-kk/.workflow/bbox/tokens.json' | ConvertFrom-Json).manajer.token
$headers = @{Authorization="Bearer $token"}
$r = Invoke-WebRequest -Uri 'http://127.0.0.1:8000/api/bootstrap' -Method GET -Headers $headers -UseBasicParsing
$r.Content | Set-Content -Encoding utf8 'D:/users/stefa/project/sim-kk/.workflow/bbox/bs_after.json'
Write-Host "OK"

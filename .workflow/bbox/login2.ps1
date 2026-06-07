$body = @{username='kasir'; password='simkk-2026'} | ConvertTo-Json
$res = Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/login' -Method POST -Body $body -ContentType 'application/json'
$res | ConvertTo-Json -Depth 5

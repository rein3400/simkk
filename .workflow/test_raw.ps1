$r = Invoke-WebRequest -Uri 'http://127.0.0.1:8000/api/login' -Method POST -Headers @{'Content-Type'='application/json'} -Body '{"username":"kasir","password":"simkk-2026","level":"Kasir"}' -TimeoutSec 10
Write-Host "Status: $($r.StatusCode) Type: $($r.StatusCode.GetType().FullName)"
Write-Host "Content: $($r.Content)"

try {
    $r = Invoke-WebRequest -Uri 'http://127.0.0.1:8000/api/login' -Method POST -Headers @{'Content-Type'='application/json'} -Body '{"username":"kasir","password":"WRONG","level":"Kasir"}' -TimeoutSec 5
    Write-Host "OK: $($r.StatusCode)"
} catch {
    Write-Host "EXC: $($_.Exception.GetType().FullName) MSG: $($_.Exception.Message)"
    Write-Host "Inner: $($_.Exception.InnerException)"
}

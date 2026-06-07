$tok = (Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/login' -Method POST -Body (@{username='kasir';password='simkk-2026';level='Kasir'} | ConvertTo-Json) -ContentType 'application/json').token
try {
    $r = Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/transactions/pay' -Method POST -Headers @{Authorization="Bearer $tok"; 'Content-Type'='application/json'} -Body '{"pasien_id":1,"terapis_id":1,"items":[{"serviceId":1,"qty":1}]}' -TimeoutSec 10
    Write-Host "OK: $($r | ConvertTo-Json -Compress)"
} catch {
    Write-Host "EXC: $($_.Exception.Response.StatusCode.value__)"
    $stream = $_.Exception.Response.GetResponseStream()
    $reader = New-Object System.IO.StreamReader($stream)
    Write-Host "BODY: $($reader.ReadToEnd())"
}

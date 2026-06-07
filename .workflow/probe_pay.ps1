$Base = 'http://127.0.0.1:8000'
$tok = (Invoke-RestMethod -Uri "$Base/api/login" -Method POST -Body (@{username='kasir';password='simkk-2026';level='Kasir'} | ConvertTo-Json) -ContentType 'application/json').token
$hdr = @{Authorization = "Bearer $tok"}
$body = @{pasien_id=1;terapis_id=1;items=@(@{serviceId=1;qty=1})} | ConvertTo-Json

try {
    $r = Invoke-WebRequest -Uri "$Base/api/transactions/pay" -Method POST -Headers $hdr -Body $body -ContentType 'application/json' -PassThru -TimeoutSec 10
    Write-Host "STATUS: $($r.StatusCode)"
    Write-Host "BODY: $($r.Content.Substring(0, [Math]::Min(200, $r.Content.Length)))"
} catch {
    Write-Host "HTTP_ERR: $($_.Exception.Response.StatusCode.value__)"
    $stream = $_.Exception.Response.GetResponseStream()
    $reader = New-Object System.IO.StreamReader($stream)
    Write-Host "BODY: $($reader.ReadToEnd())"
}

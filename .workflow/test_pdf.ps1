$tok = (Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/login' -Method POST -Body (@{username='manajer';password='simkk-2026';level='Manajer'} | ConvertTo-Json) -ContentType 'application/json').token
Write-Host "TOKEN: $tok"
try {
    $r = Invoke-WebRequest -Uri 'http://127.0.0.1:8000/api/reports/finance/export' -Headers @{Authorization="Bearer $tok"} -TimeoutSec 10
    Write-Host "STATUS: $($r.StatusCode) LEN: $($r.Content.Length)"
} catch {
    Write-Host "EXC: $($_.Exception.GetType().FullName)"
    Write-Host "MSG: $($_.Exception.Message)"
    if ($_.Exception.Response) {
        Write-Host "HTTP CODE: $($_.Exception.Response.StatusCode.value__)"
        $stream = $_.Exception.Response.GetResponseStream()
        $reader = New-Object System.IO.StreamReader($stream)
        Write-Host "BODY: $($reader.ReadToEnd())"
    }
}

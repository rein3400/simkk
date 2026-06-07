try {
    $r = Invoke-WebRequest -Uri 'http://127.0.0.1:8000/api/reports/finance/export' -Headers @{Authorization='Bearer ' + (Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/login' -Method POST -Body (@{username='manajer';password='simkk-2026';level='Manajer'} | ConvertTo-Json) -ContentType 'application/json').token } -TimeoutSec 5
    Write-Host "STATUS: $($r.StatusCode)"
    Write-Host "LEN: $($r.Content.Length)"
} catch {
    Write-Host "EXC TYPE: $($_.Exception.GetType().FullName)"
    if ($_.Exception.Response) {
        Write-Host "RESP CODE: $($_.Exception.Response.StatusCode.value__)"
        $stream = $_.Exception.Response.GetResponseStream()
        $reader = New-Object System.IO.StreamReader($stream)
        Write-Host "BODY: $($reader.ReadToEnd())"
    } else {
        Write-Host "NO RESPONSE OBJECT"
    }
}

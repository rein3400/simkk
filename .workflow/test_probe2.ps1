function Probe($Method, $Path, $Token, $Body) {
    $hdr = @{'Content-Type'='application/json'}
    if ($Token) { $hdr['Authorization'] = "Bearer $Token" }
    $bodyStr = $null
    if ($Body) { $bodyStr = $Body | ConvertTo-Json -Depth 6 -Compress }
    try {
        $r = Invoke-WebRequest -Uri "http://127.0.0.1:8000$Path" -Method $Method -Headers $hdr -Body $bodyStr -TimeoutSec 10
        return @{ status = [int]$r.StatusCode; body = $r.Content }
    } catch {
        $code = $null
        if ($_.Exception.Response) {
            $code = [int]$_.Exception.Response.StatusCode
        }
        if (-not $code) { $code = 0 }
        return @{ status = $code; body = $_.Exception.Message }
    }
}

$r1 = Probe POST '/api/login' $null @{username='kasir';password='simkk-2026';level='Kasir'}
Write-Host "login OK: status=$($r1.status)"

$r2 = Probe POST '/api/login' $null @{username='kasir';password='WRONG';level='Kasir'}
Write-Host "login bad: status=$($r2.status) body=$($r2.body)"

$tok = (Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/login' -Method POST -Body (@{username='kasir';password='simkk-2026';level='Kasir'} | ConvertTo-Json) -ContentType 'application/json').token
$r3 = Probe POST '/api/transactions/pay' $tok @{pasien_id=1;terapis_id=1;items=@(@{serviceId=1;qty=1})}
Write-Host "pay: status=$($r3.status) body=$($r3.body)"

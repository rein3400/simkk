param([string]$Base = 'http://127.0.0.1:8000')
$ErrorActionPreference = 'Continue'

function Probe($Method, $Path, $Token, $Body, $ExtraHeaders) {
    $hdr = @{'Content-Type'='application/json'}
    if ($Token) { $hdr['Authorization'] = "Bearer $Token" }
    if ($ExtraHeaders) { foreach ($k in $ExtraHeaders.Keys) { $hdr[$k] = $ExtraHeaders[$k] } }
    $bodyStr = $null
    if ($Body) { $bodyStr = $Body | ConvertTo-Json -Depth 6 -Compress }
    try {
        $r = Invoke-RestMethod -Uri "$Base$Path" -Method $Method -Headers $hdr -Body $bodyStr -TimeoutSec 10
        # For 2xx, return 200/201 and serialized body
        return @{ status = 200; body = ($r | ConvertTo-Json -Depth 6 -Compress) }
    } catch {
        $code = 0; $msg = ''
        if ($_.Exception.Response) {
            $code = [int]$_.Exception.Response.StatusCode
            try {
                $stream = $_.Exception.Response.GetResponseStream()
                $reader = New-Object System.IO.StreamReader($stream)
                $msg = $reader.ReadToEnd()
                $reader.Close()
            } catch {}
        }
        if (-not $code) {
            # Some 2xx with empty body trigger this; check Exception.Message
            if ($_.Exception.Message -match '\((\d{3})\)') { $code = [int]$Matches[1] }
        }
        return @{ status = $code; body = $msg }
    }
}

$tok = (Invoke-RestMethod -Uri "$Base/api/login" -Method POST -Body (@{username='kasir';password='simkk-2026';level='Kasir'} | ConvertTo-Json) -ContentType 'application/json').token
$ttok = (Invoke-RestMethod -Uri "$Base/api/login" -Method POST -Body (@{username='terapis';password='simkk-2026';level='Terapis'} | ConvertTo-Json) -ContentType 'application/json').token
$mtok = (Invoke-RestMethod -Uri "$Base/api/login" -Method POST -Body (@{username='manajer';password='simkk-2026';level='Manajer'} | ConvertTo-Json) -ContentType 'application/json').token
$gtok = (Invoke-RestMethod -Uri "$Base/api/login" -Method POST -Body (@{username='gudang';password='simkk-2026';level='Gudang'} | ConvertTo-Json) -ContentType 'application/json').token

$results = @()

# Auth
$results += [pscustomobject]@{ name='login no-level';               r=(Probe POST '/api/login' $null @{username='kasir';password='simkk-2026'}) }
$results += [pscustomobject]@{ name='login bad level';              r=(Probe POST '/api/login' $null @{username='kasir';password='simkk-2026';level='Hacker'}) }
$results += [pscustomobject]@{ name='login role alias';             r=(Probe POST '/api/login' $null @{username='kasir';password='simkk-2026';role='Kasir'}) }
$results += [pscustomobject]@{ name='login both';                   r=(Probe POST '/api/login' $null @{username='kasir';password='simkk-2026';level='Kasir';role='Manajer'}) }
$results += [pscustomobject]@{ name='login bad pwd';                r=(Probe POST '/api/login' $null @{username='kasir';password='WRONG';level='Kasir'}) }

# Role gate
$results += [pscustomobject]@{ name='kasir->reports';               r=(Probe GET '/api/reports/finance/export' $tok $null) }
$results += [pscustomobject]@{ name='terapis->pay';                r=(Probe POST '/api/transactions/pay' $ttok @{pasien_id=1;terapis_id=1;items=@(@{serviceId=1;qty=1})}) }
$results += [pscustomobject]@{ name='gudang->treatments';          r=(Probe POST '/api/patients/1/treatments' $gtok @{judul='x';catatan='y'}) }
$results += [pscustomobject]@{ name='gudang->pay';                 r=(Probe POST '/api/transactions/pay' $gtok @{pasien_id=1;terapis_id=1;items=@(@{serviceId=1;qty=1})}) }

# F-001 photo
$results += [pscustomobject]@{ name='photo .php';                   r=(Probe POST '/api/patients/1/photos' $ttok @{label='Before';filename='shell.php';content='abc'}) }
$results += [pscustomobject]@{ name='photo .exe';                  r=(Probe POST '/api/patients/1/photos' $ttok @{label='Before';filename='shell.exe';content='abc'}) }
$results += [pscustomobject]@{ name='photo traversal';              r=(Probe POST '/api/patients/1/photos' $ttok @{label='Before';filename='../../etc/passwd';content='abc'}) }
$results += [pscustomobject]@{ name='photo no ext';                 r=(Probe POST '/api/patients/1/photos' $ttok @{label='Before';filename='test';content='abc'}) }

# F-002/F-003 pay
$results += [pscustomobject]@{ name='pay happy treatment';          r=(Probe POST '/api/transactions/pay' $tok @{pasien_id=1;terapis_id=1;items=@(@{serviceId=1;qty=1})}) }
$results += [pscustomobject]@{ name='pay stock insufficient';       r=(Probe POST '/api/transactions/pay' $tok @{pasien_id=1;terapis_id=1;items=@(@{serviceId=7;qty=99999})}) }
$results += [pscustomobject]@{ name='pay empty items';              r=(Probe POST '/api/transactions/pay' $tok @{pasien_id=1;terapis_id=1;items=@()}) }
$results += [pscustomobject]@{ name='pay invalid pasien';           r=(Probe POST '/api/transactions/pay' $tok @{pasien_id=99999;terapis_id=1;items=@(@{serviceId=1;qty=1})}) }
$results += [pscustomobject]@{ name='pay invalid serviceId';        r=(Probe POST '/api/transactions/pay' $tok @{pasien_id=1;terapis_id=1;items=@(@{serviceId=99999;qty=1})}) }

# F-005/F-007
$results += [pscustomobject]@{ name='terapis->assigned Alya';       r=(Probe POST '/api/patients/1/treatments' $ttok @{judul='ok';catatan='valid'}) }
$results += [pscustomobject]@{ name='terapis->unassigned Yuni';    r=(Probe POST '/api/patients/3/treatments' $ttok @{judul='x';catatan='y'}) }
$results += [pscustomobject]@{ name='terapis with terapis field';   r=(Probe POST '/api/patients/1/treatments' $ttok @{terapis='Fake';judul='x';catatan='y'}) }
$results += [pscustomobject]@{ name='manajer->unassigned Yuni';     r=(Probe POST '/api/patients/3/treatments' $mtok @{judul='override';catatan='mgr'}) }

# F-006 idempotency
$idemKey = 'reg-' + [Guid]::NewGuid().ToString().Substring(0,8)
$idemHdr = @{'Idempotency-Key'=$idemKey}
$first = Probe POST '/api/transactions/pay' $tok @{pasien_id=1;terapis_id=1;items=@(@{serviceId=1;qty=1})} $idemHdr
$second = Probe POST '/api/transactions/pay' $tok @{pasien_id=1;terapis_id=1;items=@(@{serviceId=1;qty=1})} $idemHdr
$results += [pscustomobject]@{ name='idempotency first';            r=$first }
$results += [pscustomobject]@{ name='idempotency replay';           r=$second }
$results += [pscustomobject]@{ name='idempotency same body';        r=@{ status = ($first.body -eq $second.body); body = $first.body.Substring(0, [Math]::Min(60, $first.body.Length)) } }
$badHdr = @{'Idempotency-Key'='ab'}
$results += [pscustomobject]@{ name='idempotency bad key';          r=(Probe POST '/api/transactions/pay' $tok @{pasien_id=1;terapis_id=1;items=@(@{serviceId=1;qty=1})} $badHdr) }

# F-004 id format
$boot = Probe GET '/api/bootstrap' $mtok $null
$idSample = ''
try {
    $b = $boot.body | ConvertFrom-Json
    $idSample = $b.transactions[0].id
} catch {}
$results += [pscustomobject]@{ name='id format';                    r=@{ status = ($idSample -match '^TRX-\d{6}-\d{5}$'); body = $idSample } }

# Render
$rows = @()
foreach ($r in $results) {
    $exp = switch -wildcard ($r.name) {
        '*no-level*'        { 422 }
        '*bad level*'       { 422 }
        '*role alias*'      { 200 }
        '*both*'            { 200 }
        '*bad pwd*'         { 401 }
        '*kasir->reports*'  { 403 }
        '*terapis->pay*'    { 403 }
        '*gudang->*'        { 403 }
        '*photo*'           { 422 }
        '*happy*'           { 200 }
        '*insufficient*'    { 422 }
        '*empty*'           { 422 }
        '*invalid*'         { 422 }
        '*assigned Alya*'   { 200 }
        '*unassigned Yuni*' { 403 }
        '*with terapis*'    { 422 }
        '*manajer->*'       { 200 }
        '*idempotency first*'   { 200 }
        '*idempotency replay*'  { 200 }
        '*idempotency bad*'     { 422 }
        '*idempotency same*'    { $true }
        '*id format*'           { $true }
        default                 { $null }
    }
    if ($null -eq $exp) { continue }
    $pass = ($r.r.status -eq $exp)
    $rows += [pscustomobject]@{ test = $r.name; status = $r.r.status; expected = $exp; pass = $pass; body = ($r.r.body | Out-String).Trim().Substring(0, [Math]::Min(80, ($r.r.body | Out-String).Trim().Length)) }
}

$rows | Format-Table -AutoSize -Wrap
$passCount = ($rows | Where-Object { $_.pass }).Count
$failCount = ($rows | Where-Object { -not $_.pass }).Count
Write-Host ""
Write-Host "=== REGRESSION SWEEP: $passCount pass / $failCount fail ==="

$results | ConvertTo-Json -Depth 4 | Set-Content 'D:\users\stefa\project\sim-kk\.workflow\regression_results.json' -Encoding utf8

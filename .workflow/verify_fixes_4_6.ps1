param([string]$Base = 'http://127.0.0.1:8000')
$ErrorActionPreference = 'Continue'
$out = @{}

# Get fresh tokens
$tok = (Invoke-RestMethod -Uri "$Base/api/login" -Method POST -Body (@{username='kasir';password='simkk-2026';level='Kasir'} | ConvertTo-Json) -ContentType 'application/json').token
$ttok = (Invoke-RestMethod -Uri "$Base/api/login" -Method POST -Body (@{username='terapis';password='simkk-2026';level='Terapis'} | ConvertTo-Json) -ContentType 'application/json').token
$hdr = @{Authorization = "Bearer $tok"}

# F-001: photo upload with .php extension -> 422
$phpBody = @{label='Before';filename='shell.php';content='PD9waHAgcGhwaW5mbygpOyA/Pg=='} | ConvertTo-Json
try {
  $r = Invoke-WebRequest -Uri "$Base/api/patients/1/photos" -Method POST -Headers @{Authorization="Bearer $ttok"} -Body $phpBody -ContentType 'application/json' -TimeoutSec 5 -ErrorAction Stop
  $out.f001_php = @{ status = $r.StatusCode; body = $r.Content.Substring(0,150) }
} catch {
  $out.f001_php = @{ status = $_.Exception.Response.StatusCode.value__; body = $_.ErrorDetails.Message }
}

# F-001: photo upload with path traversal -> 422
$traversalBody = @{label='Before';filename='../../../foo.png';content='PD9waHAgcGhwaW5mbygpOyA/Pg=='} | ConvertTo-Json
try {
  $r = Invoke-WebRequest -Uri "$Base/api/patients/1/photos" -Method POST -Headers @{Authorization="Bearer $ttok"} -Body $traversalBody -ContentType 'application/json' -TimeoutSec 5 -ErrorAction Stop
  $out.f001_traversal = @{ status = $r.StatusCode; body = $r.Content.Substring(0,150) }
} catch {
  $out.f001_traversal = @{ status = $_.Exception.Response.StatusCode.value__; body = $_.ErrorDetails.Message }
}

# F-001: photo upload with valid PNG bytes (1x1) -> 201
$pngBytes = [Convert]::ToBase64String([byte[]](0x89,0x50,0x4E,0x47,0x0D,0x0A,0x1A,0x0A,0,0,0,13,73,72,68,82,0,0,0,1,0,0,0,1,8,2,0,0,0,144,119,83,222,0,0,0,12,73,68,65,84,8,153,99,248,255,255,63,0,1,110,181,33,1,0,151,158,209,217,79,0,0,0,0,73,69,78,68,174,66,96,130))
$validBody = @{label='Before';filename='test.png';content=$pngBytes} | ConvertTo-Json
try {
  $r = Invoke-WebRequest -Uri "$Base/api/patients/1/photos" -Method POST -Headers @{Authorization="Bearer $ttok"} -Body $validBody -ContentType 'application/json' -TimeoutSec 5 -ErrorAction Stop
  $out.f001_valid = @{ status = $r.StatusCode; body = $r.Content.Substring(0,150) }
} catch {
  $out.f001_valid = @{ status = $_.Exception.Response.StatusCode.value__; body = $_.ErrorDetails.Message }
}

# F-004: pay with new id_transaksi format (TRX-YYMMDD-NNNNN with surrogate)
$payBody = @{pasien_id=1;terapis_id=1;items=@(@{serviceId=1;qty=1})} | ConvertTo-Json
try {
  $r = Invoke-WebRequest -Uri "$Base/api/transactions/pay" -Method POST -Headers $hdr -Body $payBody -ContentType 'application/json' -TimeoutSec 5 -ErrorAction Stop
  $json = $r.Content | ConvertFrom-Json
  $out.f004_pay1 = @{ status = $r.StatusCode; id_transaksi = $json.transaction.id }
} catch {
  $out.f004_pay1 = @{ status = $_.Exception.Response.StatusCode.value__; body = $_.ErrorDetails.Message }
}

# F-004: second pay, id should be incremented
try {
  $r = Invoke-WebRequest -Uri "$Base/api/transactions/pay" -Method POST -Headers $hdr -Body $payBody -ContentType 'application/json' -TimeoutSec 5 -ErrorAction Stop
  $json = $r.Content | ConvertFrom-Json
  $out.f004_pay2 = @{ status = $r.StatusCode; id_transaksi = $json.transaction.id }
} catch {
  $out.f004_pay2 = @{ status = $_.Exception.Response.StatusCode.value__ }
}

# F-006: idempotency replay — same key returns same response
$idemKey = 'test-key-12345678'
$idemHdr = @{Authorization="Bearer $tok"; 'Idempotency-Key'=$idemKey}
try {
  $r1 = Invoke-WebRequest -Uri "$Base/api/transactions/pay" -Method POST -Headers $idemHdr -Body $payBody -ContentType 'application/json' -TimeoutSec 5 -ErrorAction Stop
  $json1 = $r1.Content | ConvertFrom-Json
  $out.f006_first = @{ status = $r1.StatusCode; id = $json1.transaction.id }
} catch {
  $out.f006_first = @{ status = $_.Exception.Response.StatusCode.value__ }
}
try {
  $r2 = Invoke-WebRequest -Uri "$Base/api/transactions/pay" -Method POST -Headers $idemHdr -Body $payBody -ContentType 'application/json' -TimeoutSec 5 -ErrorAction Stop
  $json2 = $r2.Content | ConvertFrom-Json
  $out.f006_replay = @{ status = $r2.StatusCode; id = $json2.transaction.id; same_as_first = ($json2.transaction.id -eq $json1.transaction.id) }
} catch {
  $out.f006_replay = @{ status = $_.Exception.Response.StatusCode.value__ }
}

# F-006: invalid key shape -> 422
$badHdr = @{Authorization="Bearer $tok"; 'Idempotency-Key'='ab'}
try {
  $r = Invoke-WebRequest -Uri "$Base/api/transactions/pay" -Method POST -Headers $badHdr -Body $payBody -ContentType 'application/json' -TimeoutSec 5 -ErrorAction Stop
  $out.f006_bad_key = @{ status = $r.StatusCode; body = $r.Content.Substring(0,150) }
} catch {
  $out.f006_bad_key = @{ status = $_.Exception.Response.StatusCode.value__ }
}

$out | ConvertTo-Json -Depth 4 | Set-Content 'D:\users\stefa\project\sim-kk\.workflow\verify_fixes_4_6.json' -Encoding utf8
Write-Host "=== F-001 / F-004 / F-006 verify ==="
Write-Host ("F-001 .php upload:    {0}" -f $out.f001_php.status)
Write-Host ("F-001 traversal:      {0}" -f $out.f001_traversal.status)
Write-Host ("F-001 valid PNG:      {0}" -f $out.f001_valid.status)
Write-Host ("F-004 pay1 id:        {0}" -f $out.f004_pay1.id_transaksi)
Write-Host ("F-004 pay2 id:        {0}" -f $out.f004_pay2.id_transaksi)
Write-Host ("F-006 first call:     {0} id={1}" -f $out.f006_first.status, $out.f006_first.id)
Write-Host ("F-006 replay:         {0} id={1} same={2}" -f $out.f006_replay.status, $out.f006_replay.id, $out.f006_replay.same_as_first)
Write-Host ("F-006 bad key:        {0}" -f $out.f006_bad_key.status)

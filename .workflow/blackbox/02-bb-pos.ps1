# BB-POS: POS Transaction
# NOTE: API contract uses Indonesian field names: pasien_id, terapis_id (top-level),
# metode_bayar, items[].serviceId, items[].qty. Idempotency-Key is HTTP header, not body.
. "$PSScriptRoot\00-helpers.ps1"
Reset-Results
$suite = "BB-POS"

Write-Host "`n=== BB-POS: POS Transaction ===" -ForegroundColor Cyan

$tok = (Get-LoginToken -Username "kasir" -Level "Kasir").token
$auth = Get-AuthHeader -Token $tok
$bs = Invoke-Api -Method GET -Path "/api/bootstrap" -Headers $auth
$bsj = $bs.body | ConvertFrom-Json
$service = $bsj.services | Select-Object -First 1
$therapist = $bsj.therapists | Select-Object -First 1
$patient = $bsj.patients | Select-Object -First 1

Write-Host "  using service=$($service.id) therapist=$($therapist.id) patient=$($patient.id)"

# 2.1 Happy path - 201 + receipt.id + commission
$idemKey = "bb-pos-$(Get-Date -Format 'yyyyMMddHHmmss')-001"
$headers = $auth.Clone()
$headers["Idempotency-Key"] = $idemKey
$body = @{
    pasien_id = $patient.id
    terapis_id = $therapist.id
    items = @(@{ serviceId = $service.id; qty = 1 })
    metode_bayar = "Tunai"
}
$r = Invoke-Api -Method POST -Path "/api/transactions/pay" -Headers $headers -Body $body
$j = $r.body | ConvertFrom-Json -ErrorAction SilentlyContinue
$ok = (($r.status -eq 200 -or $r.status -eq 201) -and $j.receipt.id -and $j.transaction.commission)
Add-Result $suite "pay_happy_path" "201 + receipt.id + commission" "$($r.status) receipt=$($j.receipt.id) comm=$($j.transaction.commission)" $ok "" $(if(-not $ok){"High"}else{"Info"})

# 2.2 Stok insufficient (qty 99999) - service 1 has stock
$body2 = @{
    pasien_id = $patient.id
    terapis_id = $therapist.id
    items = @(@{ serviceId = $service.id; qty = 99999 })
    metode_bayar = "Tunai"
}
$r = Invoke-Api -Method POST -Path "/api/transactions/pay" -Headers $auth -Body $body2
$expect = ($r.status -eq 422 -or $r.status -eq 400 -or $r.status -eq 409)
$note = if ($r.status -eq 500) { "Got 500 - stok check missing" } elseif (-not $expect) { "unexpected: $($r.status)" } else { "" }
Add-Result $suite "pay_stock_insufficient" "422 (not 500)" "$($r.status) body=$($r.body.Substring(0, [Math]::Min(150,$r.body.Length)))" $expect $note $(if($r.status -eq 500){"High"}else{"Info"})

# 2.3 Qty 0
$body3 = @{
    pasien_id = $patient.id
    terapis_id = $therapist.id
    items = @(@{ serviceId = $service.id; qty = 0 })
    metode_bayar = "Tunai"
}
$r = Invoke-Api -Method POST -Path "/api/transactions/pay" -Headers $auth -Body $body3
$expect = ($r.status -eq 422 -or $r.status -eq 400)
Add-Result $suite "pay_qty_zero" "422" "$($r.status)" $expect "" $(if($r.status -eq 500){"High"}else{"Info"})

# 2.4 Qty negative
$body4 = @{
    pasien_id = $patient.id
    terapis_id = $therapist.id
    items = @(@{ serviceId = $service.id; qty = -1 })
    metode_bayar = "Tunai"
}
$r = Invoke-Api -Method POST -Path "/api/transactions/pay" -Headers $auth -Body $body4
$expect = ($r.status -eq 422 -or $r.status -eq 400)
Add-Result $suite "pay_qty_negative" "422" "$($r.status)" $expect "" $(if($r.status -eq 500){"High"}else{"Info"})

# 2.5 ServiceId not exist
$body5 = @{
    pasien_id = $patient.id
    terapis_id = $therapist.id
    items = @(@{ serviceId = 999999; qty = 1 })
    metode_bayar = "Tunai"
}
$r = Invoke-Api -Method POST -Path "/api/transactions/pay" -Headers $auth -Body $body5
$expect = ($r.status -eq 422 -or $r.status -eq 404)
Add-Result $suite "pay_service_not_exist" "422/404" "$($r.status)" $expect "" $(if($r.status -eq 500){"High"}else{"Info"})

# 2.6 TerapisId not exist
$body6 = @{
    pasien_id = $patient.id
    terapis_id = 999999
    items = @(@{ serviceId = $service.id; qty = 1 })
    metode_bayar = "Tunai"
}
$r = Invoke-Api -Method POST -Path "/api/transactions/pay" -Headers $auth -Body $body6
$expect = ($r.status -eq 422 -or $r.status -eq 404)
Add-Result $suite "pay_therapist_not_exist" "422/404" "$($r.status)" $expect "" $(if($r.status -eq 500){"High"}else{"Info"})

# 2.7 Idempotency: same key sent twice - use header
$idemKey2 = "bb-pos-$(Get-Date -Format 'yyyyMMddHHmmss')-002"
$headers2 = $auth.Clone()
$headers2["Idempotency-Key"] = $idemKey2
$body7 = @{
    pasien_id = $patient.id
    terapis_id = $therapist.id
    items = @(@{ serviceId = $service.id; qty = 1 })
    metode_bayar = "Tunai"
}
$r1 = Invoke-Api -Method POST -Path "/api/transactions/pay" -Headers $headers2 -Body $body7
$r2 = Invoke-Api -Method POST -Path "/api/transactions/pay" -Headers $headers2 -Body $body7
$j1 = $r1.body | ConvertFrom-Json -ErrorAction SilentlyContinue
$j2 = $r2.body | ConvertFrom-Json -ErrorAction SilentlyContinue
$same = ($j1.transaction.id -eq $j2.transaction.id)
Add-Result $suite "idempotency_same_key" "same transaction.id" "$($j1.transaction.id) vs $($j2.transaction.id)" $same "" $(if(-not $same){"High"}else{"Info"})

Save-Results "$PSScriptRoot\results-pos.json"
Write-Host "`nBB-POS done: $($Script:Results.Count) scenarios" -ForegroundColor Cyan

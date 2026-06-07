param([string]$Base = 'http://127.0.0.1:8000')
$ErrorActionPreference = 'Continue'
$out = @{}

# Wait for server
$retry = 0
while ($retry -lt 10) {
    try { (Invoke-WebRequest -Uri "$Base/api/health" -UseBasicParsing -TimeoutSec 2).Content | Out-Null; break }
    catch { Start-Sleep -Seconds 1; $retry++ }
}

# Login fresh tokens
$ttok = (Invoke-RestMethod -Uri "$Base/api/login" -Method POST -Body (@{username='terapis';password='simkk-2026';level='Terapis'} | ConvertTo-Json) -ContentType 'application/json').token
$mtok = (Invoke-RestMethod -Uri "$Base/api/login" -Method POST -Body (@{username='manajer';password='simkk-2026';level='Manajer'} | ConvertTo-Json) -ContentType 'application/json').token

# F-005: Terapis can no longer supply 'terapis' in body (validation will reject)
$bodyWithTerapis = @{terapis='Sinta Ayu'; judul='Test'; catatan='Trying to impersonate'} | ConvertTo-Json
try {
  $r = Invoke-WebRequest -Uri "$Base/api/patients/1/treatments" -Method POST -Headers @{Authorization="Bearer $ttok"} -Body $bodyWithTerapis -ContentType 'application/json' -TimeoutSec 5 -ErrorAction Stop
  $out.f005_with_terapis_field = @{ status = $r.StatusCode; body = $r.Content }
} catch {
  $out.f005_with_terapis_field = @{ status = $_.Exception.Response.StatusCode.value__; body = $_.ErrorDetails.Message }
}

# F-007: Terapis dr. Melati writing to Alya (assigned_terapis_id=5) -> should succeed
$bodyAssigned = @{judul='Treatment OK'; catatan='Valid treatment for assigned patient'} | ConvertTo-Json
try {
  $r = Invoke-WebRequest -Uri "$Base/api/patients/1/treatments" -Method POST -Headers @{Authorization="Bearer $ttok"} -Body $bodyAssigned -ContentType 'application/json' -TimeoutSec 5 -ErrorAction Stop
  $json = $r.Content | ConvertFrom-Json
  $out.f007_assigned = @{ status = $r.StatusCode; therapist_recorded = $json.therapist }
} catch {
  $out.f007_assigned = @{ status = $_.Exception.Response.StatusCode.value__; body = $_.ErrorDetails.Message }
}

# F-007: Terapis dr. Melati writing to Yuni (assigned_terapis_id=NULL) -> 403
$bodyUnassigned = @{judul='Should fail'; catatan='Patient not assigned to me'} | ConvertTo-Json
try {
  $r = Invoke-WebRequest -Uri "$Base/api/patients/3/treatments" -Method POST -Headers @{Authorization="Bearer $ttok"} -Body $bodyUnassigned -ContentType 'application/json' -TimeoutSec 5 -ErrorAction Stop
  $out.f007_unassigned = @{ status = $r.StatusCode; body = $r.Content }
} catch {
  $out.f007_unassigned = @{ status = $_.Exception.Response.StatusCode.value__; body = $_.ErrorDetails.Message }
}

# F-007: Manajer writing to Yuni (NULL assignment) -> 201 (Manajer bypasses check)
$bodyManajer = @{judul='Manager override'; catatan='Manager can write to anyone'} | ConvertTo-Json
try {
  $r = Invoke-WebRequest -Uri "$Base/api/patients/3/treatments" -Method POST -Headers @{Authorization="Bearer $mtok"} -Body $bodyManajer -ContentType 'application/json' -TimeoutSec 5 -ErrorAction Stop
  $json = $r.Content | ConvertFrom-Json
  $out.f007_manajer = @{ status = $r.StatusCode; therapist = $json.therapist }
} catch {
  $out.f007_manajer = @{ status = $_.Exception.Response.StatusCode.value__ }
}

$out | ConvertTo-Json -Depth 4 | Set-Content 'D:\users\stefa\project\sim-kk\.workflow\verify_fixes_5_7.json' -Encoding utf8
Write-Host "=== F-005 / F-007 verify ==="
Write-Host ("F-005 with terapis field: {0} (expect 422)" -f $out.f005_with_terapis_field.status)
Write-Host ("F-007 assigned (Alya):     {0} therapist={1} (expect 201 + dr. Melati)" -f $out.f007_assigned.status, $out.f007_assigned.therapist_recorded)
Write-Host ("F-007 unassigned (Yuni):   {0} (expect 403)" -f $out.f007_unassigned.status)
Write-Host ("F-007 manajer (Yuni):      {0} therapist={1} (expect 201)" -f $out.f007_manajer.status, $out.f007_manajer.therapist)

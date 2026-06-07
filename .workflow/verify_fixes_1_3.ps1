param([string]$Base = 'http://127.0.0.1:8000')
$ErrorActionPreference = 'Continue'
$out = @{}

# F-002: login without level/role should be 422, NOT 500
try {
  $r = Invoke-WebRequest -Uri "$Base/api/login" -Method POST -Body (@{username='kasir';password='simkk-2026'} | ConvertTo-Json) -ContentType 'application/json' -TimeoutSec 5 -ErrorAction Stop
  $out.f002_no_level = @{ status = $r.StatusCode; body = $r.Content }
} catch {
  $out.f002_no_level = @{ status = $_.Exception.Response.StatusCode.value__; body = $_.ErrorDetails.Message }
}

# F-002: login with invalid level value should be 422
try {
  $r = Invoke-WebRequest -Uri "$Base/api/login" -Method POST -Body (@{username='kasir';password='simkk-2026';level='Hacker'} | ConvertTo-Json) -ContentType 'application/json' -TimeoutSec 5 -ErrorAction Stop
  $out.f002_invalid_level = @{ status = $r.StatusCode; body = $r.Content }
} catch {
  $out.f002_invalid_level = @{ status = $_.Exception.Response.StatusCode.value__; body = $_.ErrorDetails.Message }
}

# F-002: login with role alias (instead of level) should still work
try {
  $r = Invoke-RestMethod -Uri "$Base/api/login" -Method POST -Body (@{username='kasir';password='simkk-2026';role='Kasir'} | ConvertTo-Json) -ContentType 'application/json' -TimeoutSec 5
  $out.f002_role_alias = @{ status = 200; token_prefix = $r.token.Substring(0,10) }
} catch {
  $out.f002_role_alias = @{ status = $_.Exception.Response.StatusCode.value__; body = $_.ErrorDetails.Message }
}

# F-002: login with both level + role = level wins
try {
  $r = Invoke-RestMethod -Uri "$Base/api/login" -Method POST -Body (@{username='kasir';password='simkk-2026';level='Kasir';role='Manajer'} | ConvertTo-Json) -ContentType 'application/json' -TimeoutSec 5
  $out.f002_both = @{ status = 200; level_in_token = $r.user.level }
} catch {
  $out.f002_both = @{ status = $_.Exception.Response.StatusCode.value__ }
}

# F-003: stock insufficient should be 422 (from ValidationException) NOT 500
$tok = (Invoke-RestMethod -Uri "$Base/api/login" -Method POST -Body (@{username='kasir';password='simkk-2026';level='Kasir'} | ConvertTo-Json) -ContentType 'application/json' -TimeoutSec 5).token
try {
  $r = Invoke-WebRequest -Uri "$Base/api/transactions/pay" -Method POST -Headers @{Authorization="Bearer $tok"} -Body (@{pasien_id=1;terapis_id=1;items=@(@{serviceId=7;qty=99999})} | ConvertTo-Json) -ContentType 'application/json' -TimeoutSec 5 -ErrorAction Stop
  $out.f003_stock_422 = @{ status = $r.StatusCode; body = $r.Content }
} catch {
  $out.f003_stock_422 = @{ status = $_.Exception.Response.StatusCode.value__; body = $_.ErrorDetails.Message }
}

# F-003 regression: valid pay should still work
try {
  $r = Invoke-WebRequest -Uri "$Base/api/transactions/pay" -Method POST -Headers @{Authorization="Bearer $tok"} -Body (@{pasien_id=1;terapis_id=1;items=@(@{serviceId=1;qty=1})} | ConvertTo-Json) -ContentType 'application/json' -TimeoutSec 5 -ErrorAction Stop
  $out.f003_happy = @{ status = $r.StatusCode; body = $r.Content.Substring(0,120) }
} catch {
  $out.f003_happy = @{ status = $_.Exception.Response.StatusCode.value__; body = $_.ErrorDetails.Message }
}

$out | ConvertTo-Json -Depth 4 | Set-Content 'D:\users\stefa\project\sim-kk\.workflow\verify_fixes_1_3.json' -Encoding utf8
Write-Host "=== F-002 / F-003 verify ==="
Write-Host ("F-002 no level:     {0}" -f $out.f002_no_level.status)
Write-Host ("F-002 invalid lvl:  {0}" -f $out.f002_invalid_level.status)
Write-Host ("F-002 role alias:   {0}" -f $out.f002_role_alias.status)
Write-Host ("F-002 both:         {0}" -f $out.f002_both.status)
Write-Host ("F-003 stock 422:    {0}" -f $out.f003_stock_422.status)
Write-Host ("F-003 happy:        {0}" -f $out.f003_happy.status)

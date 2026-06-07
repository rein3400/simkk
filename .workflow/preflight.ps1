param(
  [string]$Base = 'http://127.0.0.1:8000'
)

$ErrorActionPreference = 'Stop'
$out = [ordered]@{}

# 1) health
$out.health = (Invoke-WebRequest -Uri "$Base/api/health" -UseBasicParsing -TimeoutSec 5).Content

# 2) login 4 roles
$creds = @(
  @{ name='kasir';   pwd='simkk-2026'; level='Kasir'   },
  @{ name='terapis'; pwd='simkk-2026'; level='Terapis' },
  @{ name='gudang';  pwd='simkk-2026'; level='Gudang'  },
  @{ name='manajer'; pwd='simkk-2026'; level='Manajer' }
)
$out.tokens = @{}
foreach ($c in $creds) {
  $body = @{ username = $c.name; password = $c.pwd; level = $c.level } | ConvertTo-Json
  try {
    $resp = Invoke-RestMethod -Uri "$Base/api/login" -Method POST -Body $body -ContentType 'application/json' -TimeoutSec 5
    $out.tokens[$c.name] = @{ token = $resp.token; user = $resp.user }
  } catch {
    $out.tokens[$c.name] = @{ error = $_.Exception.Message; status = $_.Exception.Response.StatusCode.value__
    }
  }
}

# 3) bootstrap (use manajer token for full view)
$tok = $out.tokens.manajer.token
$headers = @{ Authorization = "Bearer $tok" }
$boot = Invoke-RestMethod -Uri "$Base/api/bootstrap" -Headers $headers -TimeoutSec 5
$out.bootstrap = $boot

# 4) Negative login (bad password)
try {
  $bad = Invoke-WebRequest -Uri "$Base/api/login" -Method POST -Body (@{username='kasir';password='WRONG';level='Kasir'} | ConvertTo-Json) -ContentType 'application/json' -TimeoutSec 5 -ErrorAction Stop
  $out.bad_pwd = @{ status = $bad.StatusCode; body = $bad.Content }
} catch {
  $out.bad_pwd = @{ status = $_.Exception.Response.StatusCode.value__; body = $_.ErrorDetails.Message }
}

# 5) Login without level field
try {
  $nl = Invoke-WebRequest -Uri "$Base/api/login" -Method POST -Body (@{username='kasir';password='simkk-2026'} | ConvertTo-Json) -ContentType 'application/json' -TimeoutSec 5 -ErrorAction Stop
  $out.no_level = @{ status = $nl.StatusCode; body = $nl.Content }
} catch {
  $out.no_level = @{ status = $_.Exception.Response.StatusCode.value__; body = $_.ErrorDetails.Message }
}

# 6) Cross-role: try kasir token hitting /reports/finance/export
try {
  $kt = $out.tokens.kasir.token
  $r = Invoke-WebRequest -Uri "$Base/api/reports/finance/export" -Headers @{ Authorization = "Bearer $kt" } -TimeoutSec 5 -ErrorAction Stop
  $out.cross_role_kasir_reports = @{ status = $r.StatusCode }
} catch {
  $out.cross_role_kasir_reports = @{ status = $_.Exception.Response.StatusCode.value__; body = $_.ErrorDetails.Message }
}

# 7) Cross-role: terapis token hitting /transactions/pay
try {
  $tt = $out.tokens.terapis.token
  $r = Invoke-WebRequest -Uri "$Base/api/transactions/pay" -Method POST -Headers @{ Authorization = "Bearer $tt" } -Body (@{pasien_id=1; terapis_id=1; items=@(@{serviceId=1; qty=1})} | ConvertTo-Json) -ContentType 'application/json' -TimeoutSec 5 -ErrorAction Stop
  $out.cross_role_terapis_pay = @{ status = $r.StatusCode; body = $r.Content }
} catch {
  $out.cross_role_terapis_pay = @{ status = $_.Exception.Response.StatusCode.value__; body = $_.ErrorDetails.Message }
}

# Persist
$json = $out | ConvertTo-Json -Depth 12
$json | Set-Content -Path 'D:\users\stefa\project\sim-kk\.workflow\recon.json' -Encoding utf8
Write-Host "=== PREFLIGHT DONE ==="
Write-Host ("health: {0}" -f $out.health)
Write-Host ("tokens: " + ($out.tokens.Keys -join ','))
Write-Host ("services count: " + $out.bootstrap.services.Count)
Write-Host ("patients count: " + $out.bootstrap.patients.Count)
Write-Host ("therapists count: " + $out.bootstrap.therapists.Count)
Write-Host ("transactions count: " + $out.bootstrap.transactions.Count)
Write-Host ("inventory count: " + $out.bootstrap.inventory.Count)
Write-Host ("reports count: " + $out.bootstrap.reports.Count)
Write-Host ("bad_pwd status: {0}" -f $out.bad_pwd.status)
Write-Host ("no_level status: {0}" -f $out.no_level.status)
Write-Host ("kasir->reports status: {0}" -f $out.cross_role_kasir_reports.status)
Write-Host ("terapis->pay status: {0}" -f $out.cross_role_terapis_pay.status)

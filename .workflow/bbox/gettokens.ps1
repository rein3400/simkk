$out = @{}
$roles = @{
  'kasir'   = 'Kasir'
  'terapis' = 'Terapis'
  'gudang'  = 'Gudang'
  'manajer' = 'Manajer'
}
foreach ($k in $roles.Keys) {
  $body = @{username=$k; password='simkk-2026'; role=$roles[$k]} | ConvertTo-Json
  try {
    $r = Invoke-WebRequest -Uri 'http://127.0.0.1:8000/api/login' -Method POST -Body $body -ContentType 'application/json' -UseBasicParsing -ErrorAction Stop
    $j = $r.Content | ConvertFrom-Json
    $out[$k] = @{ token = $j.token; user = $j.user }
    Write-Host ("$k : OK")
  } catch {
    $code = $_.Exception.Response.StatusCode.value__
    Write-Host ("$k : FAIL $code")
  }
}
$out | ConvertTo-Json -Depth 10 | Set-Content -Encoding utf8 'D:/users/stefa/project/sim-kk/.workflow/bbox/tokens.json'
Write-Host "Saved to tokens.json"

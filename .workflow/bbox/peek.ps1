$tk = (Get-Content 'D:/users/stefa/project/sim-kk/.workflow/bbox/tokens.json' | ConvertFrom-Json)
$paths = @(
  '/api/bootstrap',
  '/api/transactions',
  '/api/transactions/pay',
  '/api/inventory',
  '/api/inventory/purchases',
  '/api/patients',
  '/api/patients/1',
  '/api/patients/1/treatments',
  '/api/patients/1/photos',
  '/api/services',
  '/api/therapists',
  '/api/reports/finance/export',
  '/api/reports/stock/export',
  '/api/reports/commission/export',
  '/api/reports',
  '/api/reports/finance',
  '/api/reports/stock',
  '/api/reports/commission',
  '/api/reports/finance/export.pdf',
  '/api/reports/stock/export.xlsx',
  '/api/reports/commission/export.xlsx',
  '/api/cash-ledger',
  '/api/cash_ledger',
  '/api/dashboard',
  '/api/dashboard/kasir',
  '/api/logout',
  '/api/me',
  '/api/user',
  '/api/auth/me',
  '/api/transactions/1',
  '/api/inventory/1',
  '/api/patients/1/treatments/1',
  '/api/patients/1/photos/1'
)

foreach ($p in $paths) {
  foreach ($role in @('kasir','terapis','gudang','manajer')) {
    $t = $tk.$role.token
    try {
      $r = Invoke-WebRequest -Uri "http://127.0.0.1:8000$p" -Method GET -Headers @{Authorization="Bearer $t"} -UseBasicParsing -ErrorAction Stop
      Write-Host ("{0,-50} {1,-8} {2}" -f $p, $role, $r.StatusCode)
    } catch {
      $code = $_.Exception.Response.StatusCode.value__
      Write-Host ("{0,-50} {1,-8} {2}" -f $p, $role, $code)
    }
  }
}

$res = Invoke-RestMethod -Uri 'http://127.0.0.1:8000/api/health' -Method GET
$res | ConvertTo-Json -Depth 5

Write-Host "---"
Write-Host "Trying other paths:"
foreach ($p in @('/api', '/api/login', '/api/auth', '/api/users', '/api/authenticate', '/api/token', '/sanctum/csrf-cookie', '/api/me', '/api/user', '/api/profile')) {
  try {
    $r = Invoke-WebRequest -Uri "http://127.0.0.1:8000$p" -Method GET -UseBasicParsing -ErrorAction Stop
    Write-Host "$p : $($r.StatusCode)"
  } catch {
    $code = $_.Exception.Response.StatusCode.value__
    Write-Host "$p : $code"
  }
}

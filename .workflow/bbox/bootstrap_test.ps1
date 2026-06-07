$t = (Get-Content 'D:/users/stefa/project/sim-kk/.workflow/bbox/tokens.json' | ConvertFrom-Json).kasir.token

try {
  $r = Invoke-WebRequest -Uri 'http://127.0.0.1:8000/api/bootstrap' -Method POST -Headers @{Authorization="Bearer $t"} -UseBasicParsing -ErrorAction Stop
  Write-Host "STATUS: $($r.StatusCode)"
  Write-Host "BODY (first 800):"
  Write-Host ($r.Content.Substring(0, [Math]::Min(800, $r.Content.Length)))
  $r.Content | Set-Content -Encoding utf8 'D:/users/stefa/project/sim-kk/.workflow/bbox/bootstrap_kasir.json'
} catch {
  $code = $_.Exception.Response.StatusCode.value__
  Write-Host "STATUS: $code"
  $stream = $_.Exception.Response.GetResponseStream()
  $reader = New-Object System.IO.StreamReader($stream)
  Write-Host "BODY: $($reader.ReadToEnd())"
}

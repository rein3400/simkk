try {
  $body = @{username='kasir'; password='simkk-2026'} | ConvertTo-Json
  $r = Invoke-WebRequest -Uri 'http://127.0.0.1:8000/api/login' -Method POST -Body $body -ContentType 'application/json' -UseBasicParsing -ErrorAction Stop
  Write-Host "STATUS: $($r.StatusCode)"
  Write-Host "BODY: $($r.Content)"
} catch {
  $code = $_.Exception.Response.StatusCode.value__
  Write-Host "STATUS: $code"
  $stream = $_.Exception.Response.GetResponseStream()
  $reader = New-Object System.IO.StreamReader($stream)
  Write-Host "BODY: $($reader.ReadToEnd())"
}

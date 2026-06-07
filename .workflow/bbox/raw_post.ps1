$token = (Get-Content 'D:/users/stefa/project/sim-kk/.workflow/bbox/tokens.json' | ConvertFrom-Json).kasir.token
$body = '{"pasien_id":1,"terapis_id":1,"items":[{"serviceId":1,"qty":1},{"serviceId":6,"qty":1},{"serviceId":8,"qty":1}]}'
$headers = @{Authorization="Bearer $token"; 'Content-Type'='application/json'}
try {
  $r = Invoke-WebRequest -Uri 'http://127.0.0.1:8000/api/transactions/pay' -Method POST -Headers $headers -Body $body -UseBasicParsing -ErrorAction Stop
  Write-Host "STATUS: $($r.StatusCode)"
  Write-Host $r.Content
} catch {
  $code = $_.Exception.Response.StatusCode.value__
  $stream = $_.Exception.Response.GetResponseStream()
  $b = ''
  if ($stream) { $reader = New-Object System.IO.StreamReader($stream); $b = $reader.ReadToEnd() }
  Write-Host "STATUS: $code"
  Write-Host $b
}

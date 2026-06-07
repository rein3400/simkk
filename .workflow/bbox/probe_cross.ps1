$tk = (Get-Content 'D:/users/stefa/project/sim-kk/.workflow/bbox/tokens.json' | ConvertFrom-Json)
$headers = @{Authorization="Bearer $($tk.terapis.token)"; 'Content-Type'='application/json'}

# Terapis: try /pay
Write-Host "--- terapis POST /api/transactions/pay ---"
try {
  $body = '{"pasien_id":1,"terapis_id":2,"items":[{"serviceId":1,"qty":1}]}'
  $r = Invoke-WebRequest -Uri 'http://127.0.0.1:8000/api/transactions/pay' -Method POST -Headers $headers -Body $body -UseBasicParsing -ErrorAction Stop
  Write-Host "STATUS: $($r.StatusCode) $($r.Content)"
} catch {
  $code = $_.Exception.Response.StatusCode.value__
  $stream = $_.Exception.Response.GetResponseStream()
  $b = ''
  if ($stream) { $reader = New-Object System.IO.StreamReader($stream); $b = $reader.ReadToEnd() }
  Write-Host "STATUS: $code BODY: $b"
}

# Terapis: try /inventory/purchases
Write-Host "--- terapis POST /api/inventory/purchases ---"
try {
  $body = '{"produk_id":1,"qty":5,"hpp":90000,"kadaluarsa":"2026-12-01","supplier":"X"}'
  $r = Invoke-WebRequest -Uri 'http://127.0.0.1:8000/api/inventory/purchases' -Method POST -Headers $headers -Body $body -UseBasicParsing -ErrorAction Stop
  Write-Host "STATUS: $($r.StatusCode) $($r.Content)"
} catch {
  $code = $_.Exception.Response.StatusCode.value__
  $stream = $_.Exception.Response.GetResponseStream()
  $b = ''
  if ($stream) { $reader = New-Object System.IO.StreamReader($stream); $b = $reader.ReadToEnd() }
  Write-Host "STATUS: $code BODY: $b"
}

# Gudang: try /pay
Write-Host "--- gudang POST /api/transactions/pay ---"
$headers2 = @{Authorization="Bearer $($tk.gudang.token)"; 'Content-Type'='application/json'}
try {
  $body = '{"pasien_id":1,"terapis_id":2,"items":[{"serviceId":1,"qty":1}]}'
  $r = Invoke-WebRequest -Uri 'http://127.0.0.1:8000/api/transactions/pay' -Method POST -Headers $headers2 -Body $body -UseBasicParsing -ErrorAction Stop
  Write-Host "STATUS: $($r.StatusCode) $($r.Content)"
} catch {
  $code = $_.Exception.Response.StatusCode.value__
  $stream = $_.Exception.Response.GetResponseStream()
  $b = ''
  if ($stream) { $reader = New-Object System.IO.StreamReader($stream); $b = $reader.ReadToEnd() }
  Write-Host "STATUS: $code BODY: $b"
}

# Gudang: try /patients/1/treatments
Write-Host "--- gudang POST /api/patients/1/treatments ---"
try {
  $body = '{"keluhan":"x","tindakan":"y"}'
  $r = Invoke-WebRequest -Uri 'http://127.0.0.1:8000/api/patients/1/treatments' -Method POST -Headers $headers2 -Body $body -UseBasicParsing -ErrorAction Stop
  Write-Host "STATUS: $($r.StatusCode) $($r.Content)"
} catch {
  $code = $_.Exception.Response.StatusCode.value__
  $stream = $_.Exception.Response.GetResponseStream()
  $b = ''
  if ($stream) { $reader = New-Object System.IO.StreamReader($stream); $b = $reader.ReadToEnd() }
  Write-Host "STATUS: $code BODY: $b"
}

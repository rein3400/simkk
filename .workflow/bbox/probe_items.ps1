$tk = (Get-Content 'D:/users/stefa/project/sim-kk/.workflow/bbox/tokens.json' | ConvertFrom-Json)
$token = $tk.kasir.token
$headers = @{Authorization="Bearer $token"; 'Content-Type'='application/json'}

$bodies = @(
  '1_item','{"pasien_id":1,"terapis_id":1,"items":[{"serviceId":1,"qty":1}]}',
  '2_items','{"pasien_id":1,"terapis_id":1,"items":[{"serviceId":1,"qty":1},{"serviceId":6,"qty":1}]}',
  '3_items','{"pasien_id":1,"terapis_id":1,"items":[{"serviceId":1,"qty":1},{"serviceId":6,"qty":1},{"serviceId":8,"qty":1}]}',
  'no_qty','{"pasien_id":1,"terapis_id":1,"items":[{"serviceId":1}]}',
  'qty2','{"pasien_id":1,"terapis_id":1,"items":[{"serviceId":1,"qty":2}]}',
  'qty_str','{"pasien_id":1,"terapis_id":1,"items":[{"serviceId":1,"qty":"2"}]}'
)
for ($i=0; $i -lt $bodies.Count; $i+=2) {
  Write-Host "--- $($bodies[$i]) ---"
  $b = $bodies[$i+1]
  try {
    $r = Invoke-WebRequest -Uri 'http://127.0.0.1:8000/api/transactions/pay' -Method POST -Headers $headers -Body $b -UseBasicParsing -ErrorAction Stop
    Write-Host "STATUS: $($r.StatusCode) BODY: $($r.Content)"
  } catch {
    $code = $_.Exception.Response.StatusCode.value__
    $stream = $_.Exception.Response.GetResponseStream()
    $body = ''
    if ($stream) { $reader = New-Object System.IO.StreamReader($stream); $body = $reader.ReadToEnd() }
    Write-Host "STATUS: $code BODY: $body"
  }
}

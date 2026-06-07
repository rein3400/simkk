$tk = (Get-Content 'D:/users/stefa/project/sim-kk/.workflow/bbox/tokens.json' | ConvertFrom-Json)
$token = $tk.kasir.token
$headers = @{Authorization="Bearer $token"; 'Content-Type'='application/json'}

$tests = @(
  'raw_snake_mixed',
  '{"pasien_id":1,"terapis_id":1,"items":[{"serviceId":1,"qty":1}]}',
  'all_camel',
  '{"pasienId":1,"terapisId":1,"items":[{"serviceId":1,"qty":1}]}',
  'all_snake',
  '{"pasien_id":1,"terapis_id":1,"items":[{"service_id":1,"qty":1}]}',
  'patient_underscore',
  '{"patient_id":1,"therapist_id":1,"items":[{"service_id":1,"qty":1}]}',
  'empty',
  '{}',
  'pasien_only',
  '{"pasien_id":1}'
)
for ($i=0; $i -lt $tests.Count; $i+=2) {
  Write-Host "--- $($tests[$i]) ---"
  $body = $tests[$i+1]
  try {
    $r = Invoke-WebRequest -Uri 'http://127.0.0.1:8000/api/transactions/pay' -Method POST -Headers $headers -Body $body -UseBasicParsing -ErrorAction Stop
    Write-Host "STATUS: $($r.StatusCode)"
    Write-Host $r.Content
  } catch {
    $code = $_.Exception.Response.StatusCode.value__
    Write-Host "STATUS: $code"
    $stream = $_.Exception.Response.GetResponseStream()
    if ($stream) {
      $reader = New-Object System.IO.StreamReader($stream)
      Write-Host $reader.ReadToEnd()
    }
  }
}

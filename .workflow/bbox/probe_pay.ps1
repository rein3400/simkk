$tk = (Get-Content 'D:/users/stefa/project/sim-kk/.workflow/bbox/tokens.json' | ConvertFrom-Json)
$token = $tk.kasir.token

# Test 1: minimal correct shape
$tests = @(
  @{ name='minimal_snake'; body = '{"pasien_id":1,"terapis_id":1,"items":[{"service_id":1,"qty":1}]}' },
  @{ name='camelCase'; body = '{"pasienId":1,"terapisId":1,"items":[{"serviceId":1,"qty":1}]}' },
  @{ name='patient_id'; body = '{"patient_id":1,"therapist_id":1,"items":[{"service_id":1,"qty":1}]}' }
)
foreach ($t in $tests) {
  Write-Host "--- $($t.name) ---"
  $headers = @{Authorization="Bearer $token"; 'Content-Type'='application/json'}
  try {
    $r = Invoke-WebRequest -Uri 'http://127.0.0.1:8000/api/transactions/pay' -Method POST -Headers $headers -Body $t.body -UseBasicParsing -ErrorAction Stop
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

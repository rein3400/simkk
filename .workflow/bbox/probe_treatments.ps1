$tk = (Get-Content 'D:/users/stefa/project/sim-kk/.workflow/bbox/tokens.json' | ConvertFrom-Json)
$headers = @{Authorization="Bearer $($tk.terapis.token)"; 'Content-Type'='application/json'}

$bodies = @(
  'keluhan_tindakan', '{"keluhan":"Gatal","tindakan":"Acne calm"}',
  'just_keluhan', '{"keluhan":"A"}',
  'just_tindakan', '{"tindakan":"A"}',
  'empty', '{}',
  'no_pasien', '{}',
  'wrong_pasien_path', '/api/patients/99/treatments',
  'treatments_array', '{"keluhan":"a","tindakan":"b","products":["c"]}',
  'huge_keluhan', ('{"keluhan":"' + ('X' * 100000) + '","tindakan":"a"}')
)
$path = '/api/patients/1/treatments'
for ($i=0; $i -lt $bodies.Count; $i+=2) {
  Write-Host "--- $($bodies[$i]) ---"
  $b = $bodies[$i+1]
  $p = $path
  if ($bodies[$i] -eq 'wrong_pasien_path') { $p = '/api/patients/99/treatments' }
  try {
    $r = Invoke-WebRequest -Uri "http://127.0.0.1:8000$p" -Method POST -Headers $headers -Body $b -UseBasicParsing -ErrorAction Stop
    Write-Host "STATUS: $($r.StatusCode) $($r.Content.Substring(0, [Math]::Min(300, $r.Content.Length)))"
  } catch {
    $code = $_.Exception.Response.StatusCode.value__
    $stream = $_.Exception.Response.GetResponseStream()
    $body = ''
    if ($stream) { $reader = New-Object System.IO.StreamReader($stream); $body = $reader.ReadToEnd() }
    Write-Host "STATUS: $code BODY: $($body.Substring(0, [Math]::Min(300, $body.Length)))"
  }
}

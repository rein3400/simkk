$tk = (Get-Content 'D:/users/stefa/project/sim-kk/.workflow/bbox/tokens.json' | ConvertFrom-Json)
$headers = @{Authorization="Bearer $($tk.terapis.token)"; 'Content-Type'='application/json'}

$body = '{"terapis":"dr. Melati","judul":"Acne Calm","catatan":"Pasien baik"}'
try {
  $r = Invoke-WebRequest -Uri 'http://127.0.0.1:8000/api/patients/1/treatments' -Method POST -Headers $headers -Body $body -UseBasicParsing -ErrorAction Stop
  Write-Host "STATUS: $($r.StatusCode)"
  Write-Host $r.Content
} catch {
  $code = $_.Exception.Response.StatusCode.value__
  $stream = $_.Exception.Response.GetResponseStream()
  $b = ''
  if ($stream) { $reader = New-Object System.IO.StreamReader($stream); $b = $reader.ReadToEnd() }
  Write-Host "STATUS: $code BODY: $b"
}

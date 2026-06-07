$tk = (Get-Content 'D:/users/stefa/project/sim-kk/.workflow/bbox/tokens.json' | ConvertFrom-Json)
$token = $tk.terapis.token

$tests = @(
  'png_real','Before','D:/users/stefa/project/sim-kk/.workflow/bbox/test.png',
  'php_renamed_jpg','Before','D:/users/stefa/project/sim-kk/.workflow/bbox/shell.jpg',
  'txt','Before','D:/users/stefa/project/sim-kk/.workflow/bbox/notes.txt',
  'svg','Before','D:/users/stefa/project/sim-kk/.workflow/bbox/x.svg',
  'big_1mb','Before','D:/users/stefa/project/sim-kk/.workflow/bbox/big.png',
  'empty_label','   ','D:/users/stefa/project/sim-kk/.workflow/bbox/test.png',
  'no_label_param','','D:/users/stefa/project/sim-kk/.workflow/bbox/test.png'
)
for ($i=0; $i -lt $tests.Count; $i+=3) {
  Write-Host "--- $($tests[$i]) ---"
  $label = $tests[$i+1]
  $file = $tests[$i+2]
  try {
    if ($label -ne '') {
      $r = Invoke-WebRequest -Uri 'http://127.0.0.1:8000/api/patients/1/photos' -Method POST -Headers @{Authorization="Bearer $token"} -Form @{label=$label; file=Get-Item $file} -UseBasicParsing -ErrorAction Stop
    } else {
      $r = Invoke-WebRequest -Uri 'http://127.0.0.1:8000/api/patients/1/photos' -Method POST -Headers @{Authorization="Bearer $token"} -Form @{file=Get-Item $file} -UseBasicParsing -ErrorAction Stop
    }
    Write-Host "STATUS: $($r.StatusCode) BODY: $($r.Content)"
  } catch {
    $code = $_.Exception.Response.StatusCode.value__
    $stream = $_.Exception.Response.GetResponseStream()
    $b = ''
    if ($stream) { $reader = New-Object System.IO.StreamReader($stream); $b = $reader.ReadToEnd() }
    Write-Host "STATUS: $code BODY: $($b.Substring(0, [Math]::Min(500, $b.Length)))"
  }
}

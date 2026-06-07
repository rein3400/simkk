param(
  [string]$Method='GET',
  [string]$Path,
  [string]$Token='',
  [string]$BodyJson='',
  [string]$OutFile=''
)
$headers = @{}
if ($Token -ne '') { $headers['Authorization'] = "Bearer $Token" }
$url = "http://127.0.0.1:8000$Path"
if ($BodyJson -ne '') {
  $headers['Content-Type'] = 'application/json'
}
try {
  if ($BodyJson -ne '') {
    $r = Invoke-WebRequest -Uri $url -Method $Method -Headers $headers -Body $BodyJson -UseBasicParsing -ErrorAction Stop
  } else {
    $r = Invoke-WebRequest -Uri $url -Method $Method -Headers $headers -UseBasicParsing -ErrorAction Stop
  }
  $status = [int]$r.StatusCode
  $body = $r.Content
} catch {
  $resp = $_.Exception.Response
  if ($resp -ne $null) {
    $status = [int]$resp.StatusCode
    $stream = $resp.GetResponseStream()
    if ($stream -ne $null) {
      $reader = New-Object System.IO.StreamReader($stream)
      $body = $reader.ReadToEnd()
    } else { $body = '' }
  } else {
    $status = -1
    $body = $_.Exception.Message
  }
}
Write-Host "STATUS: $status"
if ($OutFile -ne '') { $body | Set-Content -Encoding utf8 $OutFile }
Write-Host $body
exit 0

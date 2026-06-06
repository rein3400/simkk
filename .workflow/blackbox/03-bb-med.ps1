# BB-MED: Rekam Medis
# Real schema: treatment = {judul, catatan} (therapist is server-derived from auth).
# Photo = {label, filename, content} where content is base64-encoded image bytes (NOT multipart).
. "$PSScriptRoot\00-helpers.ps1"
Reset-Results
$suite = "BB-MED"

Write-Host "`n=== BB-MED: Rekam Medis ===" -ForegroundColor Cyan

$tok = (Get-LoginToken -Username "terapis" -Level "Terapis").token
$auth = Get-AuthHeader -Token $tok
$bs = Invoke-Api -Method GET -Path "/api/bootstrap" -Headers $auth
$bsj = $bs.body | ConvertFrom-Json
$patient = $bsj.patients | Select-Object -First 1

# 3.1 Create catatan treatment valid
$body = @{
    judul = "Test judul"
    catatan = "Test catatan body"
}
$r = Invoke-Api -Method POST -Path "/api/patients/$($patient.id)/treatments" -Headers $auth -Body $body
$expect = ($r.status -eq 201 -or $r.status -eq 200)
$note = if ($r.status -eq 422) { "real schema: {judul, catatan}" } else { "" }
Add-Result $suite "treatment_create_valid" "201" "$($r.status) body=$($r.body.Substring(0, [Math]::Min(200,$r.body.Length)))" $expect $note $(if($r.status -eq 500){"High"}else{"Info"})

# 3.2 Pasien tidak exist
$r = Invoke-Api -Method POST -Path "/api/patients/999999/treatments" -Headers $auth -Body $body
$expect = ($r.status -eq 422 -or $r.status -eq 404)
Add-Result $suite "treatment_patient_not_exist" "422/404" "$($r.status)" $expect "" $(if($r.status -eq 500){"High"}else{"Info"})

# Build base64 PNG (2x2 red, valid IHDR for getimagesizefromstring)
$pngBytes = [byte[]](0x89,0x50,0x4E,0x47,0x0D,0x0A,0x1A,0x0A,0x00,0x00,0x00,0x0D,0x49,0x48,0x44,0x52,0x00,0x00,0x00,0x02,0x00,0x00,0x00,0x02,0x08,0x02,0x00,0x00,0x00,0xFD,0xD4,0x9A,0x73,0x00,0x00,0x00,0x15,0x49,0x44,0x41,0x54,0x78,0x9C,0x62,0xFC,0xCF,0xC0,0xC0,0xC0,0xC0,0xC0,0xC0,0xC0,0xC0,0xC0,0x00,0x00,0x00,0x2B,0x00,0x01,0x6A,0x73,0x74,0xE8,0x00,0x00,0x00,0x00,0x49,0x45,0x4E,0x44,0xAE,0x42,0x60,0x82)
# Actually use System.Drawing to be safe
Add-Type -AssemblyName System.Drawing
$bmp = New-Object System.Drawing.Bitmap(2,2)
for ($x=0; $x -lt 2; $x++) { for ($y=0; $y -lt 2; $y++) { $bmp.SetPixel($x,$y, [System.Drawing.Color]::Red) } }
$ms = New-Object System.IO.MemoryStream
$bmp.Save($ms, [System.Drawing.Imaging.ImageFormat]::Png)
$pngBytes = $ms.ToArray()
$bmp.Dispose()
$base64 = [Convert]::ToBase64String($pngBytes)
$dataUrl = "data:image/png;base64,$base64"

# 3.3 Photo upload valid PNG (data URL, JSON body)
$photoBody = @{
    label = "Before"
    filename = "test-$(Get-Date -Format 'yyyyMMddHHmmss').png"
    content = $dataUrl
}
$r = Invoke-Api -Method POST -Path "/api/patients/$($patient.id)/photos" -Headers $auth -Body $photoBody
$expect = ($r.status -eq 201 -or $r.status -eq 200)
Add-Result $suite "photo_upload_png_valid" "201" "$($r.status) body=$($r.body.Substring(0, [Math]::Min(200,$r.body.Length)))" $expect "" $(if($r.status -eq 500){"High"}else{"Info"})

# 3.4 Photo upload .php file (filename regex will reject)
$phpContent = "data:application/x-php;base64,$( [Convert]::ToBase64String([System.Text.Encoding]::ASCII.GetBytes('<?php phpinfo(); ?>')) )"
$phpBody = @{
    label = "Before"
    filename = "evil.php"
    content = $phpContent
}
$r = Invoke-Api -Method POST -Path "/api/patients/$($patient.id)/photos" -Headers $auth -Body $phpBody
# Expect 422 because filename regex requires png/jpg/jpeg/webp/heic
$expect = ($r.status -eq 422 -or $r.status -eq 400)
Add-Result $suite "photo_upload_php_rejected" "4xx (allowlist)" "$($r.status) body=$($r.body.Substring(0, [Math]::Min(200,$r.body.Length)))" $expect "" $(if($r.status -eq 200 -or $r.status -eq 201){"Critical"}else{"Info"})

# 3.5 Path traversal filename
$traversalBody = @{
    label = "Before"
    filename = "..\..\..\..\evil.php"
    content = $dataUrl
}
$r = Invoke-Api -Method POST -Path "/api/patients/$($patient.id)/photos" -Headers $auth -Body $traversalBody
$expect = ($r.status -ge 400)
Add-Result $suite "photo_upload_path_traversal" "4xx" "$($r.status) body=$($r.body.Substring(0, [Math]::Min(200,$r.body.Length)))" $expect "" $(if($r.status -eq 200 -or $r.status -eq 201){"High"}else{"Info"})

# 3.6 Photo upload missing label
$noConsentBody = @{
    filename = "test.png"
    content = $dataUrl
}
$r = Invoke-Api -Method POST -Path "/api/patients/$($patient.id)/photos" -Headers $auth -Body $noConsentBody
$expect = ($r.status -ge 400)
Add-Result $suite "photo_upload_missing_label" "4xx (missing label)" "$($r.status) body=$($r.body.Substring(0, [Math]::Min(200,$r.body.Length)))" $expect "" $(if($r.status -eq 200 -or $r.status -eq 201){"High"}else{"Info"})

# 3.7 List treatments - check bootstrap has data
$bs2 = Invoke-Api -Method GET -Path "/api/bootstrap" -Headers $auth
$hasFields = ($bs2.body -match "treatments" -or $bs2.body -match "treatment")
Add-Result $suite "list_treatments_present" "data present" "found=$hasFields" $hasFields "" "Info"

if ($pngPath) { Remove-Item $pngPath -ErrorAction SilentlyContinue }

Save-Results "$PSScriptRoot\results-med.json"
Write-Host "`nBB-MED done: $($Script:Results.Count) scenarios" -ForegroundColor Cyan

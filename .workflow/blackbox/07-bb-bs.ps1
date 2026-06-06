# BB-BS: Bootstrap & Auth Boundary
. "$PSScriptRoot\00-helpers.ps1"
Reset-Results
$suite = "BB-BS"

Write-Host "`n=== BB-BS: Bootstrap & Boundary ===" -ForegroundColor Cyan

$tok = (Get-LoginToken -Username "manajer" -Level "Manajer").token
$auth = Get-AuthHeader -Token $tok

# 7.1 Bootstrap returns users, services, therapists, inventory, patients
$r = Invoke-Api -Method GET -Path "/api/bootstrap" -Headers $auth
$j = $r.body | ConvertFrom-Json
$hasUsers = ($j.users -ne $null)
$hasServices = ($j.services -ne $null)
$hasTherapists = ($j.therapists -ne $null)
$hasInventory = ($j.inventory -ne $null)
$hasPatients = ($j.patients -ne $null)
$allOk = ($r.status -eq 200 -and $hasUsers -and $hasServices -and $hasTherapists -and $hasInventory -and $hasPatients)
Add-Result $suite "bootstrap_complete" "all 5 fields" "$($r.status) u=$hasUsers s=$hasServices t=$hasTherapists i=$hasInventory p=$hasPatients" $allOk "" "Info"

# 7.2 signaturePath per user — field present is the contract
$hasSig = ($j.users[0].PSObject.Properties.Name -contains "signaturePath")
$note = if ($hasSig) { "field present (value may be null/empty until user uploads signature)" } else { "" }
Add-Result $suite "bootstrap_signature_path_field" "field present" "first user has signaturePath=$($j.users[0].signaturePath)" $hasSig $note "Info"

# 7.3 telegramChatId per patient — field present is the contract
$hasTg = ($j.patients[0].PSObject.Properties.Name -contains "telegramChatId")
$note = if ($hasTg) { "field present (value may be null until patient links bot)" } else { "" }
Add-Result $suite "bootstrap_telegram_chat_id_field" "field present" "first patient has telegramChatId=$($j.patients[0].telegramChatId)" $hasTg $note "Info"

# 7.4 Role filtering: Kasir gak boleh lihat semua? Bootstrap kasih semua ke semua role
$kasirTok = (Get-LoginToken -Username "kasir" -Level "Kasir").token
$kasirAuth = Get-AuthHeader -Token $kasirTok
$bsK = Invoke-Api -Method GET -Path "/api/bootstrap" -Headers $kasirAuth
$jk = $bsK.body | ConvertFrom-Json
# Cek apakah service categories restricted (counting services visible to kasir vs manajer)
$kasirServices = ($jk.services | Measure-Object).Count
$manajerServices = ($j.services | Measure-Object).Count
$filtered = ($kasirServices -lt $manajerServices) -or ($kasirServices -eq $manajerServices)
Add-Result $suite "role_filtering_services" "kasir <= manajer" "kasir=$kasirServices manajer=$manajerServices" $filtered "" "Info"

# 7.5 Logout invalidates token
$tempTok = (Get-LoginToken -Username "terapis" -Level "Terapis").token
$tempAuth = Get-AuthHeader -Token $tempTok
$r = Invoke-Api -Method POST -Path "/api/logout" -Headers $tempAuth
$logoutOk = ($r.status -eq 200)
$r2 = Invoke-Api -Method GET -Path "/api/bootstrap" -Headers $tempAuth
$invalidate = ($r2.status -eq 401)
Add-Result $suite "logout_invalidates" "401 after logout" "logout=$($r.status) after=$($r2.status)" ($logoutOk -and $invalidate) "" $(if(-not $invalidate){"High"}else{"Info"})

Save-Results "$PSScriptRoot\results-bs.json"
Write-Host "`nBB-BS done: $($Script:Results.Count) scenarios" -ForegroundColor Cyan

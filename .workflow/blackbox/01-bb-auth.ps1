# BB-AUTH: Auth & Access Control
. "$PSScriptRoot\00-helpers.ps1"
Reset-Results
$suite = "BB-AUTH"

Write-Host "`n=== BB-AUTH: Auth & Access Control ===" -ForegroundColor Cyan

# 1.1 Login valid per role
foreach ($role in @("kasir","terapis","gudang","manajer")) {
    $r = Get-LoginToken -Username $role -Level $role
    if ($r.token) {
        Add-Result $suite "login_valid_$role" "200 + token" "$($r.raw.status) + token" $true
    } else {
        Add-Result $suite "login_valid_$role" "200 + token" "$($r.raw.status)" $false "no token in body" "High"
    }
}

# 1.2 Login invalid password
$r = Invoke-Api -Method POST -Path "/api/login" -Body @{ username="kasir"; password="wrongpass"; level="Kasir" }
Add-Result $suite "login_wrong_password" "401" "$($r.status)" ($r.status -eq 401)

# 1.3 Login tanpa level
$r = Invoke-Api -Method POST -Path "/api/login" -Body @{ username="kasir"; password=$DefaultPassword }
$expect = ($r.status -eq 422 -or $r.status -eq 401 -or $r.status -eq 400)
$note = if ($r.status -eq 500) { "Got 500 - unhandled exception" } else { "" }
Add-Result $suite "login_no_level" "422/400/401 (not 500)" "$($r.status)" $expect $note $(if($r.status -eq 500){"High"}else{"Info"})

# 1.4 Login tanpa username
$r = Invoke-Api -Method POST -Path "/api/login" -Body @{ password=$DefaultPassword; level="Kasir" }
$expect = ($r.status -eq 422 -or $r.status -eq 400)
$note = if ($r.status -eq 500) { "Got 500 - unhandled exception" } else { "" }
Add-Result $suite "login_no_username" "422/400 (not 500)" "$($r.status)" $expect $note $(if($r.status -eq 500){"High"}else{"Info"})

# 1.5 Hit protected endpoint tanpa token
$r = Invoke-Api -Method GET -Path "/api/bootstrap"
Add-Result $suite "bootstrap_no_token" "401" "$($r.status)" ($r.status -eq 401)

# 1.6 Role mismatch: Kasir akses Gudang endpoint
$tok = (Get-LoginToken -Username "kasir" -Level "Kasir").token
$auth = Get-AuthHeader -Token $tok
$r = Invoke-Api -Method POST -Path "/api/inventory/purchases" -Headers $auth -Body @{ product_id=1; qty=1; batch_code="X"; expired_at="2027-01-01" }
Add-Result $suite "kasir_to_gudang_endpoint" "403" "$($r.status)" ($r.status -eq 403) "" $(if($r.status -ne 403){"High"}else{"Info"})

# 1.7 Token garbage
$badAuth = @{ Authorization = "Bearer this.is.garbage.token" }
$r = Invoke-Api -Method GET -Path "/api/bootstrap" -Headers $badAuth
Add-Result $suite "garbage_token" "401" "$($r.status)" ($r.status -eq 401)

Save-Results "$PSScriptRoot\results-auth.json"
Write-Host "`nBB-AUTH done: $($Script:Results.Count) scenarios" -ForegroundColor Cyan

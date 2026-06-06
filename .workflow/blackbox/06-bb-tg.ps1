# BB-TG: Telegram
# Real schema: reminder = {pasien_id, when, telegram_chat_id?}, aftercare = {pasien_id, treatment, telegram_chat_id?}
. "$PSScriptRoot\00-helpers.ps1"
Reset-Results
$suite = "BB-TG"

Write-Host "`n=== BB-TG: Telegram ===" -ForegroundColor Cyan

$tok = (Get-LoginToken -Username "manajer" -Level "Manajer").token
$auth = Get-AuthHeader -Token $tok
$bs = Invoke-Api -Method GET -Path "/api/bootstrap" -Headers $auth
$bsj = $bs.body | ConvertFrom-Json
$patient = $bsj.patients | Select-Object -First 1

# 6.1 Reminder without telegram_chat_id (patient likely has none)
$body = @{ pasien_id = $patient.id; when = "Besok 10:00" }
$r = Invoke-Api -Method POST -Path "/api/telegram/reminder" -Headers $auth -Body $body
# API returns 422 if patient has no chat_id (graceful)
$expect = ($r.status -eq 200 -or $r.status -eq 422)
Add-Result $suite "reminder_no_chat_id" "200/422" "$($r.status) body=$($r.body.Substring(0, [Math]::Min(200,$r.body.Length)))" $expect "" $(if($r.status -eq 500){"High"}else{"Info"})

# 6.2 Aftercare
$body2 = @{ pasien_id = $patient.id; treatment = "Test treatment" }
$r = Invoke-Api -Method POST -Path "/api/telegram/aftercare" -Headers $auth -Body $body2
$expect = ($r.status -eq 200 -or $r.status -eq 422)
Add-Result $suite "aftercare_200_or_422" "200/422" "$($r.status) body=$($r.body.Substring(0, [Math]::Min(200,$r.body.Length)))" $expect "" $(if($r.status -eq 500){"High"}else{"Info"})

# 6.3 Role gate: Gudang cannot access
$gudangTok = (Get-LoginToken -Username "gudang" -Level "Gudang").token
$gudangAuth = Get-AuthHeader -Token $gudangTok
$r = Invoke-Api -Method POST -Path "/api/telegram/reminder" -Headers $gudangAuth -Body $body
Add-Result $suite "reminder_gudang_forbidden" "403" "$($r.status)" ($r.status -eq 403) "" $(if($r.status -ne 403){"High"}else{"Info"})

# 6.4 Reminder with valid chat_id but no TELEGRAM_BOT_TOKEN (env empty) — verify graceful
$body3 = @{ pasien_id = $patient.id; telegram_chat_id = "12345678"; when = "Tomorrow 10am" }
$r = Invoke-Api -Method POST -Path "/api/telegram/reminder" -Headers $auth -Body $body3
# Expect: response includes sent=false (graceful) or 200, NOT 500
$expect = ($r.status -eq 200)
$note = if ($r.status -eq 500) { "CRASH: empty TELEGRAM_BOT_TOKEN" } else { "" }
Add-Result $suite "reminder_graceful_no_token" "200 sent=false" "$($r.status) body=$($r.body.Substring(0, [Math]::Min(200,$r.body.Length)))" $expect $note $(if($r.status -eq 500){"High"}else{"Info"})

# 6.5 Webhook GET -> 200 (Telegram health check)
$r = Invoke-Api -Method GET -Path "/api/telegram/webhook"
$expect = ($r.status -eq 200)
Add-Result $suite "webhook_get" "200" "$($r.status) body=$($r.body.Substring(0, [Math]::Min(150,$r.body.Length)))" $expect "" "Info"

Save-Results "$PSScriptRoot\results-tg.json"
Write-Host "`nBB-TG done: $($Script:Results.Count) scenarios" -ForegroundColor Cyan

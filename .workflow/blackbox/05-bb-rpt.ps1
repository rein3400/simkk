# BB-RPT: Reports
. "$PSScriptRoot\00-helpers.ps1"
Reset-Results
$suite = "BB-RPT"

Write-Host "`n=== BB-RPT: Reports ===" -ForegroundColor Cyan

$today = Get-Date -Format "yyyy-MM-dd"
$testDate = $today

$mgrTok = (Get-LoginToken -Username "manajer" -Level "Manajer").token
$mgrAuth = Get-AuthHeader -Token $mgrTok
$kasirTok = (Get-LoginToken -Username "kasir" -Level "Kasir").token
$kasirAuth = Get-AuthHeader -Token $kasirTok
$gudangTok = (Get-LoginToken -Username "gudang" -Level "Gudang").token
$gudangAuth = Get-AuthHeader -Token $gudangTok

# 5.1 Daily report export as Manajer
$r = Invoke-Api -Method GET -Path "/api/daily-reports/$testDate/export" -Headers $mgrAuth
$isPdf = ($r.status -eq 200 -and $r.body -match "%PDF-")
$note = if (-not $isPdf) { "body=$($r.body.Substring(0, [Math]::Min(300,$r.body.Length)))" } else { "" }
Add-Result $suite "daily_report_manajer" "200 PDF" "$($r.status) pdf=$($r.body.Substring(0, [Math]::Min(20,$r.body.Length)))" $isPdf $note $(if(-not $isPdf){"High"}else{"Info"})

# 5.2 Daily report export as Kasir -> 403
$r = Invoke-Api -Method GET -Path "/api/daily-reports/$testDate/export" -Headers $kasirAuth
Add-Result $suite "daily_report_kasir_forbidden" "403" "$($r.status)" ($r.status -eq 403) "" $(if($r.status -ne 403){"High"}else{"Info"})

# 5.3 Inventory movements as Gudang
$r = Invoke-Api -Method GET -Path "/api/inventory-movements?from=2026-01-01&to=2026-12-31" -Headers $gudangAuth
Add-Result $suite "movements_gudang" "200" "$($r.status)" ($r.status -eq 200) "" "Info"

# 5.4 Movements invalid date
$r = Invoke-Api -Method GET -Path "/api/inventory-movements?from=invalid&to=2026-12-31" -Headers $gudangAuth
$expect = ($r.status -eq 422 -or $r.status -eq 400)
Add-Result $suite "movements_invalid_date" "422" "$($r.status)" $expect "" $(if($r.status -eq 500){"High"}else{"Info"})

# 5.5 PDF content checks - only run if PDF returned
if ($isPdf) {
    $r = Invoke-Api -Method GET -Path "/api/daily-reports/$testDate/export" -Headers $mgrAuth
    $size = [System.Text.Encoding]::UTF8.GetByteCount($r.body)
    $pdfText = $r.body
    $hasDaily = $pdfText -match "DAILY REPORT" -or $pdfText -match "Daily Report" -or $pdfText -match "Laporan Harian"
    $hasCash = $pdfText -match "CASH AT CASHIER" -or $pdfText -match "Cash at Cashier" -or $pdfText -match "CASH"
    $hasNet = $pdfText -match "NET SALES" -or $pdfText -match "Net Sales" -or $pdfText -match "Penjualan"
    $hasCard = $pdfText -match "Total Card" -or $pdfText -match "Card"
    $hasPnl = $pdfText -match "P n L" -or $pdfText -match "P&L" -or $pdfText -match "Profit" -or $pdfText -match "Laba"
    $hasTtd = $pdfText -match "TTD" -or $pdfText -match "Tanda Tangan" -or $pdfText -match "ttd"
    $sizeOk = $size -gt 5120

    Add-Result $suite "pdf_has_daily_report" "yes" "has=$hasDaily" $hasDaily "" "Info"
    Add-Result $suite "pdf_has_cash_at_cashier" "yes" "has=$hasCash" $hasCash "" "Info"
    Add-Result $suite "pdf_has_net_sales" "yes" "has=$hasNet" $hasNet "" "Info"
    Add-Result $suite "pdf_has_total_card" "yes" "has=$hasCard" $hasCard "" "Info"
    Add-Result $suite "pdf_has_pnl" "yes" "has=$hasPnl" $hasPnl "" "Info"
    Add-Result $suite "pdf_has_ttd" "yes" "has=$hasTtd" $hasTtd "" "Info"
    Add-Result $suite "pdf_size_gt_5kb" ">5120" "$size" $sizeOk "" "Info"
    Add-Result $suite "pdf_header_percent" "%PDF-" "yes" ($pdfText.Substring(0,5) -eq "%PDF-") "" "Info"
} else {
    # CRITICAL: export endpoint returns 409 instead of 200 PDF — see REPORT
    foreach ($k in "pdf_has_daily_report","pdf_has_cash_at_cashier","pdf_has_net_sales","pdf_has_total_card","pdf_has_pnl","pdf_has_ttd","pdf_size_gt_5kb","pdf_header_percent") {
        Add-Result $suite $k "yes" "blocked by 500/409" $false "endpoint returns $($r.status)" "Critical"
    }
}

Save-Results "$PSScriptRoot\results-rpt.json"
Write-Host "`nBB-RPT done: $($Script:Results.Count) scenarios" -ForegroundColor Cyan

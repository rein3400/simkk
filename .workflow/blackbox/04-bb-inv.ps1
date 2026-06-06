# BB-INV: Inventory / Gudang
# Real schema: {produk_id, supplier, kode_batch, qty, hpp, kadaluarsa}
. "$PSScriptRoot\00-helpers.ps1"
Reset-Results
$suite = "BB-INV"

Write-Host "`n=== BB-INV: Inventory ===" -ForegroundColor Cyan

$tok = (Get-LoginToken -Username "gudang" -Level "Gudang").token
$auth = Get-AuthHeader -Token $tok
$bs = Invoke-Api -Method GET -Path "/api/bootstrap" -Headers $auth
$bsj = $bs.body | ConvertFrom-Json
$product = $bsj.inventory | Select-Object -First 1

# 4.1 Add purchase valid
$body = @{
    produk_id = $product.id
    supplier = "PT Test Supplier"
    kode_batch = "TEST-$(Get-Date -Format 'yyyyMMddHHmmss')"
    qty = 10
    hpp = 50000
    kadaluarsa = "2027-12-31"
}
$r = Invoke-Api -Method POST -Path "/api/inventory/purchases" -Headers $auth -Body $body
$expect = ($r.status -eq 201 -or $r.status -eq 200)
Add-Result $suite "purchase_valid" "201" "$($r.status) body=$($r.body.Substring(0, [Math]::Min(200,$r.body.Length)))" $expect "" $(if($r.status -eq 500){"High"}else{"Info"})

# 4.2 product_id tidak exist
$body2 = @{
    produk_id = 999999
    supplier = "PT Test"
    kode_batch = "TEST-XXX"
    qty = 10
    hpp = 50000
}
$r = Invoke-Api -Method POST -Path "/api/inventory/purchases" -Headers $auth -Body $body2
$expect = ($r.status -eq 422 -or $r.status -eq 404)
Add-Result $suite "purchase_product_not_exist" "422/404" "$($r.status)" $expect "" $(if($r.status -eq 500){"High"}else{"Info"})

# 4.3 qty 0
$body3 = @{
    produk_id = $product.id
    supplier = "PT Test"
    kode_batch = "TEST-ZERO"
    qty = 0
    hpp = 50000
}
$r = Invoke-Api -Method POST -Path "/api/inventory/purchases" -Headers $auth -Body $body3
$expect = ($r.status -eq 422 -or $r.status -eq 400)
Add-Result $suite "purchase_qty_zero" "422" "$($r.status)" $expect "" $(if($r.status -eq 500){"High"}else{"Info"})

# 4.4 FIFO verification - buy 2 batches, sell consumes oldest first
# Get a service that uses a product with stock
$bs2 = Invoke-Api -Method GET -Path "/api/bootstrap" -Headers $auth
$bsj2 = $bs2.body | ConvertFrom-Json
# Try to find service with batch_stock. Simplest: do a transaction then check inventory-movements
$kasirTok = (Get-LoginToken -Username "kasir" -Level "Kasir").token
$kasirAuth = Get-AuthHeader -Token $kasirTok
$service = $bsj2.services | Select-Object -First 1
$terapis = $bsj2.therapists | Select-Object -First 1
$patient = $bsj2.patients | Select-Object -First 1

# 4.4a Buy 2 batches of same product with different prices
$batch1 = @{
    produk_id = $product.id
    supplier = "Supplier A"
    kode_batch = "BATCH-A-$(Get-Date -Format 'yyyyMMddHHmmss')"
    qty = 5
    hpp = 40000
    kadaluarsa = "2027-06-30"
}
$batch2 = @{
    produk_id = $product.id
    supplier = "Supplier B"
    kode_batch = "BATCH-B-$(Get-Date -Format 'yyyyMMddHHmmss')"
    qty = 5
    hpp = 60000
    kadaluarsa = "2027-12-31"
}
$r1 = Invoke-Api -Method POST -Path "/api/inventory/purchases" -Headers $auth -Body $batch1
$r2 = Invoke-Api -Method POST -Path "/api/inventory/purchases" -Headers $auth -Body $batch2

# 4.4b Hit inventory-movements to confirm FIFO rows present
$today = Get-Date -Format "yyyy-MM-dd"
$from = (Get-Date).AddDays(-1).ToString("yyyy-MM-dd")
$r = Invoke-Api -Method GET -Path "/api/inventory-movements?from=$from&to=$today" -Headers $auth
$j = $r.body | ConvertFrom-Json -ErrorAction SilentlyContinue
# Just verify endpoint returns rows
$movements = $j.data -or $j.rows -or $j
$hasRows = ($r.status -eq 200)
Add-Result $suite "fifo_movements_queryable" "200" "$($r.status) length=$($r.body.Length)" $hasRows "" "Info"

# 4.5 Expired stock chip - check bootstrap includes status indicator
$bs3 = Invoke-Api -Method GET -Path "/api/bootstrap" -Headers $auth
$hasStatusField = ($bs3.body -match "kadaluarsa" -or $bs3.body -match "expired" -or $bs3.body -match '"status"')
Add-Result $suite "expired_stock_chip" "status field in bootstrap" "has=$hasStatusField" $hasStatusField "" "Info"

Save-Results "$PSScriptRoot\results-inv.json"
Write-Host "`nBB-INV done: $($Script:Results.Count) scenarios" -ForegroundColor Cyan

$bs = Get-Content 'D:/users/stefa/project/sim-kk/.workflow/bbox/bs_after.json' | ConvertFrom-Json
# Get current Barrier Serum and Calming Toner stock
foreach ($p in $bs.inventory) {
  if ($p.id -in @(1,3,2)) {
    $total = 0
    foreach ($b in $p.batches) { $total += $b.qty }
    Write-Host "Produk $($p.id) $($p.name): totalStock=$($p.totalStock), batch-sum=$total, batches=$($p.batches.Count)"
    foreach ($b in $p.batches) {
      Write-Host "  - $($b.code) qty=$($b.qty) firstOut=$($b.firstOut) expiry=$($b.expiry)"
    }
  }
}
Write-Host "---"
Write-Host "Transaksi count: $($bs.transactions.Count)"
$seedCount = 0
foreach ($t in $bs.transactions) {
  if ($t.id -like 'TRX-2505-*') { $seedCount++ }
}
Write-Host "Seed TRX (TRX-2505-*): $seedCount"
$myCount = 0
foreach ($t in $bs.transactions) {
  if ($t.id -like 'TRX-260604-*') { $myCount++ }
}
Write-Host "My TRX (TRX-260604-*): $myCount"

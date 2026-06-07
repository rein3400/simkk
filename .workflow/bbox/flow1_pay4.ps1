param(
  [string]$OutFile=''
)
$tk = (Get-Content 'D:/users/stefa/project/sim-kk/.workflow/bbox/tokens.json' | ConvertFrom-Json)
$token = $tk.kasir.token
$body = @{
  pasien_id = 1
  terapis_id = 1
  payment_method = 'Tunai'
  discount = 0
  catatan = 'flow1 test'
  items = @(
    @{ serviceId = 1; qty = 1 },
    @{ serviceId = 6; qty = 1 },
    @{ serviceId = 8; qty = 1 }
  )
} | ConvertTo-Json -Depth 5

powershell -NoProfile -ExecutionPolicy Bypass -File "D:/users/stefa/project/sim-kk/.workflow/bbox/req.ps1" -Method POST -Path '/api/transactions/pay' -Token "$token" -BodyJson "$body" -OutFile $OutFile

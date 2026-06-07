$tk = (Get-Content 'D:/users/stefa/project/sim-kk/.workflow/bbox/tokens.json' | ConvertFrom-Json)
$token = $tk.kasir.token
$body = '{"pasien_id":1,"terapis_id":1,"payment_method":"Tunai","discount":0,"catatan":"flow1 clean","items":[{"serviceId":1,"qty":1},{"serviceId":6,"qty":1},{"serviceId":8,"qty":1}]}'

# Print the body to file
Write-Host "BODY-LEN: $($body.Length)"
$body | Set-Content -Encoding utf8 'D:/users/stefa/project/sim-kk/.workflow/bbox/_body.json'

# Now call req.ps1 with this body
powershell -NoProfile -ExecutionPolicy Bypass -File "D:/users/stefa/project/sim-kk/.workflow/bbox/req.ps1" -Method POST -Path '/api/transactions/pay' -Token "$token" -BodyJson "$body" -OutFile "D:/users/stefa/project/sim-kk/.workflow/bbox/flow1_clean2.json"

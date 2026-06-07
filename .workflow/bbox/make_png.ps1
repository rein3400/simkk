# Create a fake PNG file with PNG header
$png = [byte[]]@(0x89,0x50,0x4E,0x47,0x0D,0x0A,0x1A,0x0A,0,0,0,0x0D,0x49,0x48,0x44,0x52,0,0,0,1,0,0,0,1,8,2,0,0,0,0x90,0x77,0x53,0xDE,0,0,0,0x0C,0x49,0x44,0x41,0x54,0x08,0xD7,0x63,0xF8,0xCF,0xC0,0,0,0,3,0,1,0x6F,0xB9,0xD8,0x59,0,0,0,0,0x49,0x45,0x4E,0x44,0xAE,0x42,0x60,0x82)
[System.IO.File]::WriteAllBytes('D:/users/stefa/project/sim-kk/.workflow/bbox/test.png', $png)

# Make a fake "php renamed to jpg"
$phpBody = [byte[]]@(0x3C,0x3F,0x70,0x68,0x70,0x20,0x65,0x63,0x68,0x6F,0x20,0x22,0x6F,0x77,0x6E,0x65,0x64,0x22,0x3B,0x20,0x3F,0x3E)
[System.IO.File]::WriteAllBytes('D:/users/stefa/project/sim-kk/.workflow/bbox/shell.jpg', $phpBody)

# Make a fake txt
[System.IO.File]::WriteAllText('D:/users/stefa/project/sim-kk/.workflow/bbox/notes.txt', 'plain text')

# Make a fake svg
[System.IO.File]::WriteAllText('D:/users/stefa/project/sim-kk/.workflow/bbox/x.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>')

# Make a 1MB junk
$big = [byte[]]::new(1048576)
(new-object Random).NextBytes($big)
[System.IO.File]::WriteAllBytes('D:/users/stefa/project/sim-kk/.workflow/bbox/big.png', $big)

Write-Host "files made"
Get-ChildItem 'D:/users/stefa/project/sim-kk/.workflow/bbox/*.png','D:/users/stefa/project/sim-kk/.workflow/bbox/*.jpg','D:/users/stefa/project/sim-kk/.workflow/bbox/*.svg','D:/users/stefa/project/sim-kk/.workflow/bbox/*.txt' | Format-Table Name,Length

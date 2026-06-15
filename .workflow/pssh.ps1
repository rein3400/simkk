# PowerShell SSH wrapper — automates password entry
# Usage: .\pssh.ps1 "command to run"

param([string]$Command = "echo OK", [string]$Host = "43.133.142.74", [string]$User = "ubuntu")

$ssh = "C:\Windows\System32\OpenSSH\ssh.exe"
$pw = "river-43%-thunder"

# Use plink or direct expect via PowerShell
# Approach: use expect (Cygwin/Git Bash) or piped password via sshpass-equivalent
# Windows OpenSSH doesn't support piped password. We need to use a TTY simulator.

# Method: use python's pexpect or just spawn ssh with stdin redirected via Start-Process
# Simpler: pipe password via expect.exe if installed, else use winpty
# Best for Windows: use 'ssh' with SSH_ASKPASS (but posix_spawnp fails on Windows)

# Workaround: use 'cmdkey' to store credentials, then 'runas' to use them
# But cleanest: use plink (PuTTY) if available

# Fallback: spawn ssh via Start-Process with input
$psi = New-Object System.Diagnostics.ProcessStartInfo
$psi.FileName = $ssh
$psi.Arguments = "-o StrictHostKeyChecking=no -o UserKnownHostsFile=NUL -tt ${User}@${Host} `"$Command`""
$psi.RedirectStandardInput = $true
$psi.RedirectStandardOutput = $true
$psi.RedirectStandardError = $true
$psi.UseShellExecute = $false
$psi.CreateNoWindow = $true

$proc = [System.Diagnostics.Process]::Start($psi)

# Wait briefly for password prompt
Start-Sleep -Milliseconds 500

# Try reading prompt
$initialOutput = $proc.StandardOutput.ReadLine()
Write-Host "Server: $initialOutput"

# Send password
$proc.StandardInput.WriteLine($pw)
$proc.StandardInput.Flush()
Start-Sleep -Milliseconds 500

# Read remaining output
$proc.StandardOutput.ReadToEnd() | Write-Host
$proc.StandardError.ReadToEnd() | Write-Host
$proc.WaitForExit()

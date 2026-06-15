#!/usr/bin/env python3
"""SSH wrapper using pywinpty for proper PTY on Windows."""
import sys
import winpty

if len(sys.argv) < 2:
    print("Usage: pssh.py '<remote command>'", file=sys.stderr)
    sys.exit(1)

cmd = sys.argv[1]
ssh = "C:\\Windows\\System32\\OpenSSH\\ssh.exe"
user_host = "ubuntu@43.133.142.74"
password = "3M6-R9q-Uki-R3c"

# Use winpty.PtyProcess — argv[0] is ssh, rest is args
ssh_argv = [ssh] + [
    "-o", "StrictHostKeyChecking=no",
    "-o", "UserKnownHostsFile=NUL",
    "-o", "ConnectTimeout=15",
    "-o", "PreferredAuthentications=password",
    "-o", "PubkeyAuthentication=no",
    "-o", "NumberOfPasswordPrompts=3",
    user_host,
    cmd,
]

proc = winpty.PtyProcess.spawn(ssh_argv, cwd="C:\\Windows\\System32", env={
    "PATH": "C:\\Windows\\System32;C:\\Python314"
})

# Wait for prompt
import time
time.sleep(2)
all_out = ''
for _ in range(15):
    try:
        chunk = proc.read(8192)
        if chunk:
            all_out += chunk if isinstance(chunk, str) else chunk.decode('utf-8', errors='replace')
    except EOFError:
        break
    time.sleep(0.3)

# Send password if prompted
text = all_out
if 'password:' in text.lower():
    proc.write(password + "\n")
    time.sleep(2)
    final = ''
    for _ in range(15):
        try:
            chunk = proc.read(8192)
            if chunk:
                final += chunk if isinstance(chunk, str) else chunk.decode('utf-8', errors='replace')
        except EOFError:
            break
        time.sleep(0.3)
    sys.stdout.write(final)
    sys.stdout.flush()
else:
    sys.stderr.write(f"No pw prompt. Got: {text}\n")
    # Try blind send
    proc.write(password + "\n")
    time.sleep(2)
    try:
        more = proc.read(65536)
        if more:
            sys.stdout.write(more if isinstance(more, str) else more.decode('utf-8', errors='replace'))
    except:
        pass

try:
    proc.wait()
except:
    pass
sys.exit(proc.exitstatus or 0)

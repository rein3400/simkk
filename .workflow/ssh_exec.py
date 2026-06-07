#!/usr/bin/env python3
"""SSH automation helper untuk VPS deploy. Usage:
    python .workflow/ssh_exec.py <command>
    python .workflow/ssh_exec.py "cd /var/www && ls"
"""
import sys
import paramiko

VPS_IP = "43.133.142.74"
VPS_USER = "ubuntu"
VPS_PASSWORD = "river-43%-thunder"

def ssh_exec(cmd: str, timeout: int = 60) -> tuple[int, str, str]:
    """Run cmd on VPS, return (exit_code, stdout, stderr)."""
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    try:
        client.connect(
            hostname=VPS_IP,
            username=VPS_USER,
            password=VPS_PASSWORD,
            timeout=15,
            look_for_keys=False,
            allow_agent=False,
        )
        stdin, stdout, stderr = client.exec_command(cmd, timeout=timeout)
        out = stdout.read().decode("utf-8", errors="replace")
        err = stderr.read().decode("utf-8", errors="replace")
        code = stdout.channel.recv_exit_status()
        return code, out, err
    finally:
        client.close()

if __name__ == "__main__":
    cmd = " ".join(sys.argv[1:]) if len(sys.argv) > 1 else "whoami && uname -a"
    code, out, err = ssh_exec(cmd)
    print(f"$ {cmd}")
    print(f"--- exit={code} ---")
    if out: print("STDOUT:", out)
    if err: print("STDERR:", err)

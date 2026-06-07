#!/usr/bin/env python3
"""Helper: kirim file kecil via SFTP + eksekusi command via SSH.
   Usage:
       python .workflow/ssh_helpers.py put <local_path> <remote_path>
       python .workflow/ssh_helpers.py sh <remote_cmd>
       python .workflow/ssh_helpers.py bash <heredoc_content> [remote_path]
"""
import sys
import paramiko

VPS_IP = "43.133.142.74"
VPS_USER = "ubuntu"
VPS_PASSWORD = "river-43%-thunder"

def get_client():
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    c.connect(VPS_IP, username=VPS_USER, password=VPS_PASSWORD, timeout=15, look_for_keys=False, allow_agent=False)
    return c

def sh(cmd, timeout=60, sudo=False):
    client = get_client()
    try:
        full = f"sudo {cmd}" if sudo else cmd
        stdin, stdout, stderr = client.exec_command(full, timeout=timeout)
        out = stdout.read().decode("utf-8", errors="replace")
        err = stderr.read().decode("utf-8", errors="replace")
        code = stdout.channel.recv_exit_status()
        return code, out, err
    finally:
        client.close()

def put(local_path, remote_path):
    client = get_client()
    try:
        sftp = client.open_sftp()
        sftp.put(local_path, remote_path)
        sftp.chmod(remote_path, 0o644)
        sftp.close()
        return True
    finally:
        client.close()

if __name__ == "__main__":
    cmd = sys.argv[1]
    if cmd == "sh":
        print(sh(" ".join(sys.argv[2:]), sudo=True))
    elif cmd == "put":
        put(sys.argv[2], sys.argv[3])
        print(f"uploaded {sys.argv[2]} -> {sys.argv[3]}")
    elif cmd == "bash":
        # execute local script
        print(sh("bash " + sys.argv[2], sudo=True))
    else:
        print("usage: sh <cmd> | put <local> <remote> | bash <script>")

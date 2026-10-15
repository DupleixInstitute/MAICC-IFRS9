"""Scan a Claude Code session log for credentials and write a redacted copy (15 October 2026).

    python redact_session_log.py <session.jsonl> [<redacted copy.jsonl>]

Without a second argument it only reports what it found. The patterns cover GitHub tokens,
password= pairs (git credential output), bearer tokens, AWS and Google keys, private keys,
Laravel APP_KEYs, OpenAI-style keys, OAuth refresh tokens and client secrets.
"""
import re
import sys
from collections import Counter

PATTERNS = {
    "github token": r"gh[pousr]_[A-Za-z0-9]{30,}|github_pat_[A-Za-z0-9_]{40,}",
    "password=": r"password=[^\s\\\"']{4,}",
    "bearer": r"Bearer [A-Za-z0-9\-_.]{20,}",
    "aws key": r"AKIA[0-9A-Z]{16}",
    "google key": r"AIza[0-9A-Za-z\-_]{35}",
    "private key": r"-----BEGIN [A-Z ]*PRIVATE KEY-----[\s\S]*?-----END [A-Z ]*PRIVATE KEY-----",
    "app key": r"APP_KEY=base64:[A-Za-z0-9+/=]{20,}",
    "sk key": r"\bsk-[A-Za-z0-9]{20,}",
    "refresh token": r"refresh_token\\*\"?\s*[:=]\s*\\*\"?[A-Za-z0-9\-_./]{10,}",
    "client secret": r"client_secret\\*\"?\s*[:=]\s*\\*\"?[A-Za-z0-9\-_]{10,}",
}
RX = {k: re.compile(v) for k, v in PATTERNS.items()}


def redact(line: str, found: Counter) -> str:
    for k, rx in RX.items():
        def sub(m, k=k):
            found[k] += 1
            return f"[REDACTED {k}]"
        line = rx.sub(sub, line)
    return line


if __name__ == "__main__":
    src = sys.argv[1]
    dst = sys.argv[2] if len(sys.argv) > 2 else None
    found = Counter()
    out = open(dst, "w", encoding="utf-8") if dst else None
    with open(src, encoding="utf-8") as fh:
        for line in fh:
            red = redact(line, found)
            if out:
                out.write(red)
    if out:
        out.close()
    print("found:", dict(found) or "nothing")

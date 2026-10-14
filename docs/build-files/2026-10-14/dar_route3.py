import shutil, subprocess, sys
from pathlib import Path
DIR = Path(r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\3. Project Execution\E-Banker Integration")
REPO = Path(r"C:\xampp\htdocs\MAICC-IFRS9")
TECH = DIR / "Data access request - E-Banker read-only access (route 4) - 14 Oct 2026.md"
PLAIN = DIR / "Plain-language guide - connecting the IFRS 9 system to E-Banker - 14 Oct 2026.md"
FIG3 = "Route 3 - push by API endpoint - sequence diagram.png"

def sub(t, a, b):
    assert a in t, "anchor missing: " + a[:70]
    return t.replace(a, b, 1)

# ======================================================== technical document
t = TECH.read_text(encoding="utf-8")

t = sub(t, "| **3. Push by API** | The same script sends the pack to the system over HTTPS with a token, instead of through a folder | A token issued by the system; outbound HTTPS from the MAIIC server | **Built on the system side** (the endpoint exists); waits for the token and the script. The alternative to route 2 where a shared folder is awkward |",
        "| **3. Push by API (the endpoint)** | The same script zips the pack and sends it to the system's endpoint, `POST /api/ebanker-feed/pack`, over HTTPS with a token, instead of through a folder; the system replies at once and lands the pack at its next poll (section 3.1) | A token issued by the system; outbound HTTPS from the MAIIC server to the system's server | **Built on the system side** (the endpoint exists and is tested); waits for the token and the script. The alternative to route 2 where a shared folder between the two servers is awkward |")

route3 = """### 3.1 Route 3 in detail: the endpoint

An **endpoint** is an address on the IFRS 9 system's web server that accepts a delivery from another computer, in the way a web page accepts a form. Route 3 uses one: `POST https://<system server>/api/ebanker-feed/pack`. The MAIIC script runs the same queries as under route 2; the only difference is that instead of leaving the pack in a shared folder, it sends it straight to this address. Figure 3 shows the exchange.

![Figure 3. Route 3: the MAIIC script reads E-Banker, then posts the zipped pack to the system's endpoint; the endpoint checks it on arrival and replies at once; the landing zone lands it at the next poll through the same gates as every route.](Route 3 - push by API endpoint - sequence diagram.png)

**What the endpoint does with each delivery, in order** (this is how it is built today):

| Step | What happens | If it fails |
|---|---|---|
| 1. The token | The request must carry `Authorization: Bearer <token>`. The token is compared with the one configured on the system's server (`EBANKER_FEED_TOKEN`), in a way that does not leak its length or content through timing | Refused with 401; nothing is stored |
| 2. The file | The body must carry one file, field name `pack`, which must be a readable zip | Refused with 422 |
| 3. The names inside the zip | Every entry name is checked before anything is written; a name that would write outside the inbox (for example `../../`) refuses the whole zip, and the attempt is logged with the sender's IP | Refused with 422; logged as a warning |
| 4. The manifest | The zip must hold `manifest.json` (at the top or in one sub-folder) | Refused with 422 |
| 5. Into the inbox | The zip is unpacked into its own inbox folder named by date, time and the first characters of the file's hash; an audit entry "E-Banker Pack Received by API" records the inbox and the sender's IP | |
| 6. The reply | `202 Accepted`, with the inbox name. 202 means **received, not yet landed** | |
| 7. The landing | At the next run of the poller (daily at 06:30, `eir:poll-feed-folder --also-route-3`), the pack goes through the landing zone exactly as under every other route: the gates, landed or quarantined, versioned rows, watermarks. The load is recorded as route `ROUTE_3_API` | Quarantined, with the failures named |

A pack sent twice is recognised by its manifest hash at step 7 and not landed twice (section 11).

**Push and pull: who opens the connection.** This is usually what decides between the routes, because it decides which firewall rule MAIIC's ICT has to open.

| Route | Who starts it | Connection opened | Firewall rule needed | Where the E-Banker password lives | What the system holds |
|---|---|---|---|---|---|
| 1. Manual | A person | Barry to E-Banker; then a browser upload to the system | None new | With Barry | Nothing |
| 2. Scheduled export | MAIIC's scheduled task | MAIIC server to E-Banker; MAIIC server to a shared folder the system can read | A share both servers can reach | Oracle Wallet on the MAIIC server | Read access to the folder |
| 3. Push by API (endpoint) | MAIIC's scheduled task | MAIIC server to E-Banker; MAIIC server **out** to the system over HTTPS | Outbound HTTPS (443) from the MAIIC server to the system's server | Oracle Wallet on the MAIIC server | The feed token only |
| 4. Direct read | The system's scheduler | System server **in** to E-Banker over the VPN | Inbound to the database listener (1521) from the system's server | The system server's configuration | The read-only database password |
| 5. Vendor view or API | The system's scheduler | System server to the vendor's views or the vendor's own endpoint | As route 4, or to the vendor's endpoint | The system server's configuration | The vendor's credentials |

Routes 2 and 3 keep every connection to E-Banker inside MAIIC: the IFRS 9 system never connects into MAIIC's network and never holds an E-Banker password. That makes them the natural choice if MAIIC's ICT policy does not allow an outside system to connect to the database. Route 4 is simpler to run (nothing to install at MAIIC) but needs the inbound rule.

**Route 5's endpoint is a different thing.** Under route 5 the endpoint would be the vendor's, not ours: Virtual Galaxy would publish an address the system calls to fetch the data (a pull), and the system would need a small connector written to their specification. That is only worth doing if the vendor refuses route 4 and MAIIC prefers not to run a script (routes 2 and 3).

**What would be added before route 3 goes live.** The endpoint works today, but four hardening items would be added if route 3 is chosen, about a day's work:

- **An IP allow-list**, so only the MAIIC server's address is accepted (today any address with the token is). Until then, the same can be done on the system server's firewall.
- **Two-token rotation**, so a new token can be issued and the old one retired without a gap (today there is one token in the configuration).
- **Landing on receipt**, so the reply can say "landed" or "quarantined" straight away instead of waiting for the 06:30 poll; or the poll run more often on the first working days of the month.
- **A size limit and a status address**, so oversize packs are refused clearly (today the limit is the web server's upload limit, which must be raised to at least 100 MB) and the script can ask for the result of its last delivery.

"""
t = sub(t, "## 4. The landing zone technique", route3 + "## 4. The landing zone technique")

# setup: route 3 steps after route 2's step 9
t = sub(t, "**Step 10. Look after the account.**",
        """**Step 9a. For route 3 only: the outbound connection and the token.** Instead of a shared folder: allow the MAIIC server to make outbound HTTPS connections (port 443) to the IFRS 9 system's server, and nothing else new. Dupleix issues a token (a long random string) and gives it to Barry on a call, not by email; Barry stores it in the Windows Credential Manager of the service account that runs the task, not in the script. The script sends each pack with the built-in Windows command `curl.exe -f -X POST "https://<system server>/api/ebanker-feed/pack" -H "Authorization: Bearer <token>" -F "pack=@<pack>.zip"`, writes the reply to its log, and tries again up to three times if the reply is not 202. Test it once with a deliberately wrong token: the reply must be 401.

**Step 10. Look after the account.**""")
t = sub(t, "When the system needs a new table, Dupleix sends one line,",
        "For route 3, the feed token is changed on the same 90-day cycle (a new token on the system's server and in the Credential Manager, the same day). When the system needs a new table, Dupleix sends one line,")

# sequence: route 3 on test
t = sub(t, "| | Route 4 on test: the Oracle client and connection installed on the system's test server;",
        "| | Route 3 on test, if MAIIC prefers it to route 2 or 4: the same script posting to the test system's endpoint with a test token (section 6, step 9a); a wrong token must be refused | Dupleix and Barry | Alongside route 2 |\n| | Route 4 on test: the Oracle client and connection installed on the system's test server;")

TECH.write_text(t, encoding="utf-8")

# ======================================================== plain-language guide
p = PLAIN.read_text(encoding="utf-8")
p = sub(p, "| **3. Sent over the internet** | The same program sends the files straight to the system instead of using a folder | Ready on our side; needs a security key |",
        "| **3. Sent to the system's \"letterbox\"** (the endpoint) | The same program posts the files straight to a secure address on the IFRS 9 system, with a security key, instead of using a folder | Ready on our side; needs a security key |")
plain3 = """### Way 3 explained: the "letterbox"

The IFRS 9 system has a secure address on its web server that accepts deliveries of data, rather like a locked letterbox. In technical terms it is called an **endpoint**. Under way 3, MAIIC's small program fetches the data from E-Banker, packs it into a single compressed file, and posts it to this letterbox over an encrypted connection, together with a long secret code (the **token**) that proves it comes from MAIIC.

The letterbox checks four things the moment a delivery arrives: that the secret code is right, that the file is a proper compressed file, that nothing inside it is trying to write where it should not, and that it carries its list of contents. If any check fails, the delivery is refused on the spot. If all pass, the system replies "received" straight away and puts the delivery in a holding tray. Early the next morning the system takes it through the same checkpoint as every other delivery (section 4) before any figure uses it.

The main attraction of way 3 is that **MAIIC makes every connection itself**. The IFRS 9 system never connects into MAIIC's network and never holds an E-Banker password; it only holds the secret code for its own letterbox. If MAIIC's policy is that no outside system may connect to the banking database, ways 2 and 3 meet it; way 4 does not.

![Figure 3. Way 3 step by step: the MAIIC program reads E-Banker, posts the packed file to the system's letterbox, the letterbox checks it and replies at once, and the checkpoint takes it in the next morning.](Route 3 - push by API endpoint - sequence diagram.png)

Before way 3 is used for real, we would add four safeguards, about a day's work: accept deliveries only from MAIIC's server, allow the secret code to be changed without a gap, take deliveries through the checkpoint as soon as they arrive, and refuse oversized deliveries clearly.

"""
p = sub(p, "## 4. The \"landing zone\": a checkpoint at the door", plain3 + "## 4. The \"landing zone\": a checkpoint at the door")
gl = """| **API** | A way for one computer system to send requests or data to another automatically, without a person in between. |
| **Endpoint ("letterbox")** | A specific secure address on a system that accepts deliveries or requests from another computer. The IFRS 9 system's endpoint for E-Banker data is `/api/ebanker-feed/pack`. |
| **Token** | A long secret code sent with each delivery to prove who it comes from, like a password for a computer rather than a person. |
| **HTTPS** | The encrypted form of web traffic (the padlock in a browser), so data cannot be read on the way. |
| **Push and pull** | Push: the sender delivers the data (ways 2 and 3, MAIIC sends). Pull: the receiver fetches it (ways 4 and 5, the IFRS 9 system fetches). It decides which side opens the connection. |
| **202 Accepted** | The reply the letterbox gives when a delivery is received and waiting to be checked; not yet a confirmation that it passed the checks. |
"""
p = sub(p, "| **Feed route** |", gl + "| **Feed route** |")
PLAIN.write_text(p, encoding="utf-8")

for f in (TECH, PLAIN):
    print(subprocess.run([sys.executable, str(REPO / "docs/build-files/spec_render.py"), str(f)], capture_output=True, text=True).stdout.strip().splitlines()[-1])
q = REPO / "docs/bootstrap/queries"
for name in (TECH.name, PLAIN.name, FIG3, "Feed routes and landing zone - diagram.png"):
    shutil.copy(DIR / name, q / name)
print("copied to repo")

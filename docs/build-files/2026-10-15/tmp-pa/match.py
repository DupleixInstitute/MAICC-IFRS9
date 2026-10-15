# ---------------------------------------------------------------- match
from itertools import combinations

items = [{"f": f, "t": t, "want": round(f[t], 2), "posts": [], "how": []} for f in fin for t in ("legal", "arrangement") if round(f[t], 2)]


def days(p, f):
    return abs((p["d"] - f["value_date"]).days) if f["value_date"] else 0


def take(it, ps, why):
    for p in ps:
        p["used"] = True
        it["posts"].append(p)
        w = why
        if p["state"].startswith("Tied") and p["loan"] != it["f"]["acct"]:
            w = f"the system tied it to loan {p['loan']} (that loan is the leg of the batch); Finance's file puts it on this loan"
        elif p["state"].startswith("Held") and p["loan"] and p["loan"] != it["f"]["acct"]:
            w += f"; NOTE the system suggested loan {p['loan']}"
        it["how"].append(f"{p['pid']}: {w}")


def free(t):
    return [p for p in led if not p["used"] and p["type"] == t]


# A. one posting, exactly the amount: tied to this loan, then held with this loan suggested, then the name, then the amount alone
#    (within 120 days of the value date), then tied to another loan of a customer of the same name
ranks = [
    (lambda p, f: p["state"].startswith("Tied") and p["loan"] == f["acct"], "tied to this loan by the system"),
    (lambda p, f: p["state"].startswith("Held") and p["loan"] == f["acct"], "held; the system's candidate loan"),
    (lambda p, f: p["state"].startswith("Held") and name_hit(p, f), "held; the customer's name is in the narration"),
    (lambda p, f: p["state"].startswith("Held") and days(p, f) <= 120, "held; same amount, near the value date"),
    (lambda p, f: p["state"].startswith("Tied") and (name_hit(p, f) or names.get(p["loan"], "").split()[0:1] == f["name"].split()[0:1]), "tied to another loan of the same customer"),
]
for test, why in ranks:
    for it in items:
        if it["posts"]:
            continue
        c = [p for p in free(it["t"]) if abs(p["amount"] - it["want"]) < 0.5 and test(p, it["f"])]
        c.sort(key=lambda p: days(p, it["f"]))
        if c:
            take(it, [c[0]], why)

# B. one held posting paying the fees of two loans together
for t in ("legal", "arrangement"):
    open_ = [it for it in items if it["t"] == t and not it["posts"]]
    for p in [p for p in free(t) if p["state"].startswith("Held")]:
        for a, b in combinations(open_, 2):
            if a["posts"] or b["posts"]:
                continue
            if abs(a["want"] + b["want"] - p["amount"]) < 0.5 and days(p, a["f"]) <= 60 and days(p, b["f"]) <= 60:
                p["used"] = True
                for it, other in ((a, b), (b, a)):
                    it["posts"].append({**p, "amount": it["want"], "shared": True})
                    it["how"].append(f"{p['pid']}: one posting of {p['amount']:,.2f} covers this loan and {other['f']['acct']} ({other['f']['name']})")
                break

# C. a posting plus its correction(s): up to three postings that sum to the amount, at least one tied to or naming the loan
for it in items:
    if it["posts"]:
        continue
    pool = [p for p in free(it["t"]) if (p["loan"] == it["f"]["acct"] or name_hit(p, it["f"]) or p["amount"] < 0
                                         or "over deducted" in (p["narration"] or "").lower()) and days(p, it["f"]) <= 250]
    done = False
    for k in (2, 3):
        for combo in combinations(pool, k):
            if abs(sum(p["amount"] for p in combo) - it["want"]) < 0.5 and any(p["loan"] == it["f"]["acct"] or name_hit(p, it["f"]) for p in combo):
                take(it, list(combo), "with the correcting entries")
                done = True
                break
        if done:
            break

# D. what is left: the nearest posting tied to or naming the loan, shown as a difference
for it in items:
    if it["posts"]:
        continue
    c = [p for p in free(it["t"]) if p["loan"] == it["f"]["acct"] or name_hit(p, it["f"])]
    c.sort(key=lambda p: abs(p["amount"] - it["want"]))
    if c:
        take(it, [c[0]], "nearest posting for this loan (amount differs)")

recon = []
for it in items:
    f, t = it["f"], it["t"]
    tied = sum(p["amount"] for p in it["posts"] if p["state"].startswith("Tied") and p["loan"] == f["acct"])
    ledger = round(sum(p["amount"] for p in it["posts"]), 2)
    diff = round(it["want"] - ledger, 2)
    if not it["posts"]:
        status = "In Finance's file, no posting found"
    elif abs(diff) > 0.5:
        status = "Amounts differ"
    elif all(p["state"].startswith("Tied") and p["loan"] == f["acct"] for p in it["posts"]):
        status = "Agrees"
    elif any(p["state"].startswith("Tied") and p["loan"] != f["acct"] for p in it["posts"]):
        status = "Agrees, but the system tied it to another loan"
    else:
        status = "Agrees once the held posting is tied to this loan"
    recon.append({"acct": f["acct"], "name": f["name"], "vd": f["value_date"], "type": t, "fin": it["want"], "tied": tied,
                  "held": ledger - tied, "ledger": ledger, "diff": diff, "status": status,
                  "postings": ", ".join(p["pid"] for p in it["posts"]), "dates": ", ".join(p["d"].isoformat() for p in it["posts"]),
                  "how": "; ".join(it["how"])})

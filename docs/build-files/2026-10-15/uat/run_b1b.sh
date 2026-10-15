export UAT_BASE=http://127.0.0.1:8073 MSYS_NO_PATHCONV=1
echo "== B1g intake drawdowns"; ITYPE=disbursements node step.cjs ck_maker.json f_intake.js
echo "== B1h macro"; node step.cjs ck_maker.json f_macro.js
echo "== B1i portfolio"; node step.cjs ck_maker.json f_portfolio.js
echo "== B1j credit loss data"; IURL=/credit-loss-data/import ITAG=B1j_cld IPRE="(()=>{const s=document.querySelector('#portfolio_id'); const o=[...s.options].find(o=>/UAT test/.test(o.text)); s.value=o.value; s.dispatchEvent(new Event('change')); return o.text})()" node step.cjs ck_maker.json f_import.js

export UAT_BASE=http://127.0.0.1:8073 MSYS_NO_PATHCONV=1
echo "== B1a clients import"; IURL=/client/import ITAG=B1a_clients node step.cjs ck_maker.json f_import.js
echo "== B1b link loan book"; node step.cjs ck_maker.json f_linkclients.js
echo "== B1c collateral import before types"; IURL=/collateral/register ITAG=B1c_collateral_notypes IPRE="(()=>{const i=document.querySelector('#period'); const s=Object.getOwnPropertyDescriptor(HTMLInputElement.prototype,'value').set; s.call(i,'2026-09'); i.dispatchEvent(new Event('input'));})()" node step.cjs ck_maker.json f_import.js
echo "== B1c collateral types"; node step.cjs ck_maker.json f_coltypes.js
echo "== B1c collateral import"; IURL=/collateral/register ITAG=B1c_collateral IPRE="(()=>{const i=document.querySelector('#period'); const s=Object.getOwnPropertyDescriptor(HTMLInputElement.prototype,'value').set; s.call(i,'2026-09'); i.dispatchEvent(new Event('input'));})()" node step.cjs ck_maker.json f_import.js
echo "== B1d pack"; node step.cjs ck_maker.json f_pack.js
echo "== B1e trial balance"; node step.cjs ck_maker.json f_tb.js
echo "== B1f take-on"; node step.cjs ck_maker.json f_takeon.js
echo "== B1g intake drawdowns"; ITYPE=disbursements node step.cjs ck_maker.json f_intake.js
echo "== B1h macro"; node step.cjs ck_maker.json f_macro.js
echo "== B1i portfolio"; node step.cjs ck_maker.json f_portfolio.js
echo "== B1j credit loss data"; IURL=/credit-loss-data/import ITAG=B1j_cld IPRE="(()=>{const s=document.querySelector('#portfolio_id'); const o=[...s.options].find(o=>/UAT test/.test(o.text)); s.value=o.value; s.dispatchEvent(new Event('change')); return o.text})()" node step.cjs ck_maker.json f_import.js

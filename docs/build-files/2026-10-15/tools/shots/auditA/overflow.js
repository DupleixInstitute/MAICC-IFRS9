(() => { const out = { docW: document.documentElement.scrollWidth };
 out.wraps = [...document.querySelectorAll('table')].map(t => { const w = t.parentElement; return { tableW: t.scrollWidth, wrapW: w.clientWidth, overflowX: getComputedStyle(w).overflowX, heads: [...t.querySelectorAll('thead th')].map(th => th.innerText.trim()).join('|') }; });
 return out; })()

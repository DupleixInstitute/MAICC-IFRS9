(() => { const links = [...document.querySelectorAll('a')].filter(a => /Take-on Population/.test(a.innerText));
 if (!links.length) return 'no tab'; const row = links[0].parentElement; const box = links[0].getBoundingClientRect();
 return { rowScrollW: row.scrollWidth, rowClientW: row.clientWidth, overflowX: getComputedStyle(row).overflowX, lastTabRight: box.right, lastTabText: links[0].innerText.replace(/\s+/g,' '), rowParentW: row.parentElement.clientWidth,
 allTabs: [...row.querySelectorAll('a')].map(a=>a.innerText.replace(/\s+/g,' ').trim()) }; })()

const r=require('./routes.json');
const pats=new RegExp(process.argv[2]);
const bs=String.fromCharCode(92);
for(const x of r){ if(x.name && pats.test(x.name)) console.log(x.method.padEnd(10), x.uri.padEnd(62), x.name, '|', (x.action||'').split(bs).pop());}

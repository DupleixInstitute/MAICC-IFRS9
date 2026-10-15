// node pj.cjs <file> <expr>  reads ip.sh output file (json + \nstatus) and applies expr to props
const fs=require('fs'); const t=fs.readFileSync(process.argv[2],'utf8').trimEnd(); const i=t.lastIndexOf('\n'); const st=t.slice(i+1);
let j; try { j=JSON.parse(t.slice(0,i)); } catch(e){ console.log('status',st,'NONJSON',t.slice(0,400)); process.exit(0);}
const p=j.props; ['menu','auth','jetstream','ziggy'].forEach(k=>delete p[k]);
const f=process.argv[3]? eval('('+process.argv[3]+')') : (x=>x);
console.log('status',st,j.component, JSON.stringify(f(p)).slice(0, Number(process.env.MAX||5000)));

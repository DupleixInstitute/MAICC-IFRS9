const sfc = require('C:/xampp/htdocs/MAICC-IFRS9/node_modules/@vue/compiler-sfc');
const fs = require('fs');
for (const f of process.argv.slice(2)) {
  const src = fs.readFileSync(f, 'utf8');
  const { descriptor, errors } = sfc.parse(src, { filename: f });
  let errs = errors.map(e => e.message + (e.loc ? ' @' + e.loc.start.line : ''));
  if (descriptor.template) {
    let r;
    try {
      r = sfc.compileTemplate({ source: descriptor.template.content, filename: f, id: 'x' });
    } catch (e) {
      r = { errors: [{ message: 'crash: ' + String(e.message).split(/\r?\n/)[0] }] };
    }
    const off = descriptor.template.loc.start.line - 1;
    errs = errs.concat(r.errors.map(e => (e.message || e) + (e.loc ? ' @' + (e.loc.start.line + off) : '')));
  }
  try {
    if (descriptor.script || descriptor.scriptSetup) sfc.compileScript(descriptor, { id: 'x' });
  } catch (e) {
    errs.push('script: ' + String(e.message).split(/\r?\n/)[0]);
  }
  if (errs.length) console.log(f + '\n  ' + errs.join('\n  '));
}
console.log('checked', process.argv.length - 2);

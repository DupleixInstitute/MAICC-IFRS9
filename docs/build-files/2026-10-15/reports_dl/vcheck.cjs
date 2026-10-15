const { parse, compileTemplate } = require('C:/xampp/htdocs/MAICC-IFRS9/node_modules/@vue/compiler-sfc');
const fs = require('fs');
for (const f of process.argv.slice(2)) {
  const { descriptor, errors } = parse(fs.readFileSync(f, 'utf8'), { filename: f });
  let errs = errors.map(e => e.message);
  if (descriptor.template) {
    const r = compileTemplate({ source: descriptor.template.content, filename: f, id: 'x' });
    errs = errs.concat(r.errors.map(e => e.message || e));
  }
  console.log(errs.length ? 'FAIL ' + f + ': ' + errs.join(' | ') : 'ok   ' + f);
}

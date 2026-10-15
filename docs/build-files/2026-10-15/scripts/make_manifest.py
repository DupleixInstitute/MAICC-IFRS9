"""Write manifest.json for a pack folder in the committed format (pack, assembled, source, ..., files[{file, query_id, kind,
rows, sha256, bytes}]). Rows are counted the way PackLandingService::readCsv counts them: non-blank lines after the header.
Usage: python -I make_manifest.py <pack_dir> <spec.json>
spec.json: {"header": {...pack-level keys...}, "files": [{"file": ..., "query_id": ..., "kind": ..., "received_as": ...}, ...]}"""
import sys, json, hashlib, re, os

pack_dir, spec_path = sys.argv[1], sys.argv[2]
spec = json.load(open(spec_path, encoding='utf-8'))
files = []
for f in spec['files']:
    path = os.path.join(pack_dir, f['file'])
    raw = open(path, 'rb').read()
    try:
        text = raw.decode('utf-8-sig')
    except UnicodeDecodeError:
        text = raw.decode('cp1252')
    lines = [l for l in re.split(r'\r\n|\n|\r', text) if l.strip() != '']
    entry = {'file': f['file'], 'query_id': f['query_id'], 'kind': f['kind'], 'rows': max(0, len(lines) - 1),
             'sha256': hashlib.sha256(raw).hexdigest(), 'bytes': len(raw)}
    if f.get('received_as'):
        entry['received_as'] = f['received_as']
    files.append(entry)
manifest = dict(spec['header'])
manifest['files'] = files
with open(os.path.join(pack_dir, 'manifest.json'), 'w', encoding='utf-8', newline='\n') as out:
    json.dump(manifest, out, indent=2, ensure_ascii=True)
    out.write('\n')
print(f"{len(files)} files, {sum(e['rows'] for e in files)} rows -> {os.path.join(pack_dir, 'manifest.json')}")

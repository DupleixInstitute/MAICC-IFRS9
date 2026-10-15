p = 'C:/xampp/htdocs/MAICC-IFRS9/database/seeders/data/help_user_content/06-eir-and-revenue-recognition.php'
s = open(p, encoding='utf-8').read()
a = s.index("        'Trial balances' => [")
b = s.index("        'Fee classification' => [")
block = s[a:b]
# escape the apostrophes inside the single-quoted PHP strings of this block only
fixed = []
for line in block.split('\n'):
    stripped = line.strip()
    if stripped.startswith(("'body' => '", ". '", "'")) and not stripped.startswith(("'steps'", "'routes'", "'Trial balances'")):
        # find the opening quote of the content and the closing quote
        if stripped.startswith("'body' => '"):
            prefix = line[:line.index("'body' => '") + len("'body' => '")]
            rest = line[len(prefix):]
        elif stripped.startswith(". '"):
            prefix = line[:line.index(". '") + 3]
            rest = line[len(prefix):]
        else:
            prefix = line[:line.index("'") + 1]
            rest = line[len(prefix):]
        # the content ends at the last quote before the trailing punctuation
        end = rest.rfind("'")
        content, tail = rest[:end], rest[end:]
        content = content.replace("\\'", "'").replace("'", "\\'")
        line = prefix + content + tail
    fixed.append(line)
s = s[:a] + '\n'.join(fixed) + s[b:]
open(p, 'w', encoding='utf-8').write(s)
print('ok')

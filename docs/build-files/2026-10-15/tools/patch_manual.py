R = 'C:/xampp/htdocs/MAICC-IFRS9/database/seeders/data/'

def patch(p, pairs):
    s = open(R + p, encoding='utf-8').read()
    for a, b in pairs:
        assert a in s, (p, a[:60])
        s = s.replace(a, b, 1)
    open(R + p, 'w', encoding='utf-8').write(s)

patch('help_user_content/07a-ifrs9-model-setup.php', [
("""                . '<li><b>Add Internal Grade</b> (or <b>Edit Internal Grade</b>) modal""",
 """                . '<li><b>Editing and deleting.</b> On a draft profile card a pencil opens <b>Edit Grading Profile</b> (name, description, maximum tenor) and a bin deletes the profile with all its grades and PD curves, after a confirmation that names the profile and its grade count. On the Grades page a bin beside each grade pencil deletes that grade and its curve. An active profile shows neither: deactivate it with the lock first. Each delete is written to the audit trail.</li>'
                . '<li><b>Add Internal Grade</b> (or <b>Edit Internal Grade</b>) modal"""),
])

patch('help_admin_content/02-users-and-access.php', [
("""<li><b>You cannot delete a user.</b> Records they created may reference them. Deactivate instead; that is the intended path.</li>""",
 """<li><b>You cannot delete a user.</b> You cannot delete your own account (the banner says to ask another administrator), nor the last account holding the admin role (give the role to someone else first). Otherwise, records they created may reference them. Deactivate instead; that is the intended path.</li>"""),
("""<li><b>Everyone is an administrator.</b>""",
 """<li><b>A role will not delete.</b> A role still held by any user is refused with <code>The role is held by N user(s). Move them to another role before deleting it.</code>, so nobody is left without access unnoticed. System roles cannot be deleted at all.</li><li><b>Everyone is an administrator.</b>"""),
])

patch('help_admin_content/01-administering-the-platform.php', [
("""<li><b>Audit Trail</b>. The unified timeline over both audit stores, with filters for search, source, user and date range.</li>""",
 """<li><b>Audit Trail</b>. The unified timeline over both audit stores, with filters for search, source, user and date range. Each entry is tagged <b>Record change</b>, <b>Module action</b> (a calculation, approval or setting) or <b>Export</b> (a report, download or pack), and <b>Export CSV</b> at the top right downloads the whole filtered trail.</li>"""),
])
print('ok')

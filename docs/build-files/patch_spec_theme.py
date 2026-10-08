"""Spec v4 section 11: light and dark mode are adopted, with the mechanics stated."""
import re
p = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\3. Project Execution\specs\MAIIC_EIR_Engine_Specification_v4_2026-10-07.md"
s = open(p, encoding="utf-8").read()

def rep(old, new, count=1):
    global s
    assert old in s, old[:60]
    s = s.replace(old, new, count)

# 11.1 plain language: mention the appearance setting
rep("Across the top is a bar with the menu toggle, the financial period the system is working in, notifications and the user's menu.",
    "Across the top is a bar with the menu toggle, the financial period the system is working in, an appearance switch (light, dark, or follow the device), notifications and the user's menu.")

# 11.2 top bar row and the 'not adopted' paragraph
rep("| Top bar | Menu toggle; a chip showing the financial period the system is working in and whether it is open or closed; the notification bell; the user menu (profile, API tokens, log out). The decorative search box that does nothing today is removed. | FDH `Components/Shell/Topbar.vue`, less the dark-mode switch |",
    "| Top bar | Menu toggle; a chip showing the financial period the system is working in and whether it is open or closed; the appearance switch (11.5); the notification bell; the user menu (profile, API tokens, log out). The decorative search box that does nothing today is removed. | FDH `Components/Shell/Topbar.vue` |")
rep("Not adopted: the dark-mode switch. MAIIC's pages were not written with a dark variant and would render half-styled; it can follow in a later pass once every page carries the variant.\n\n",
    "Everything in the FDH shell is adopted, including light and dark mode (11.5). MAIIC's pages were not written with a dark variant, so the dark mode is delivered in two steps: the shell and the shared styles first, then a sweep of the pages (11.9).\n\n")

# renumber 11.5..11.7 -> 11.6..11.8 and insert the new 11.5
rep("### 11.7 Order of work", "### 11.8 Order of work")
rep("### 11.6 Acceptance", "### 11.7 Acceptance")
rep("### 11.5 What changes in the code, and what does not", "### 11.6 What changes in the code, and what does not")
theme = '''### 11.5 Light and dark mode

Every user chooses how the system looks, and the choice follows them.

- **Three settings.** Light, Dark, and Follow the device. The switch is in the top bar and cycles through the three; the same choice is on the user's profile page. The default for a new user is Follow the device.
- **How it is applied.** Tailwind runs in class mode (`darkMode: 'class'`): the whole system is dark when the `dark` class is on the `<html>` element and light when it is not. Nothing else decides the theme, so there is one switch to test.
- **No flash of the wrong theme.** A four-line script in `app.blade.php` runs before the page paints: it reads the saved choice from the browser (`maiic.theme`), falls back to the device setting when the choice is Follow the device or absent, and sets the class. The Vue code then mirrors that state in one shared composable (`useTheme`) so the top-bar switch, the profile page and any other consumer stay in step.
- **Where the choice is kept.** In the browser, so that the first paint is right, and on the user's record (`users.theme_preference`, values `light`, `dark`, `system`), so that it follows the user to another machine: on login the server's value is written to the browser; when the user changes it, the browser value is written and the server is told. If the browser's storage is unavailable, the theme still applies for the session.
- **What dark mode must look like.** The sidebar keeps its dark gradient in both modes (it is dark by design). The page background, cards, tables, inputs, buttons, badges, modals, charts and the help panels carry a dark variant: slate backgrounds, light text, the group colours unchanged, MAIIC gold kept for the active marks. Contrast meets WCAG AA in both modes; no screen may show light text on a light ground or dark on dark.
- **How the pages get there without rewriting 281 components.** The shared classes the pages already use (`.card`, `.th`, `.td`, `.primary-btn`, `.secondary-btn`, the form inputs, the badges, the flash messages) are given their dark variant once in `app.css`, so most pages inherit the theme. The pages that style elements directly are then swept one group at a time (11.9), each page checked in both modes before it is signed off. Printed and exported outputs (PDF, Excel) are always rendered in the light palette, whatever the screen shows.

'''
rep("### 11.6 What changes in the code, and what does not", theme + "### 11.6 What changes in the code, and what does not")

# 11.6 table: add rows
rep("| `currentPeriod` shared with every page | `app/Http/Middleware/HandleInertiaRequests.php` |",
    "| `currentPeriod` and the user's `theme_preference` shared with every page | `app/Http/Middleware/HandleInertiaRequests.php` |\n"
    "| Tailwind in class mode; the no-flash script; the `useTheme` composable; the top-bar switch; the profile setting and its endpoint; `users.theme_preference` | `tailwind.config.js`, `resources/views/app.blade.php`, `resources/js/composables/useTheme.js`, `AppLayout.vue`, the profile page, one migration and `ProfileController` |\n"
    "| Dark variants of the shared classes, then of the pages that style directly, group by group | `resources/css/app.css`, then the page components under `resources/js/Pages` |")

# 11.7 acceptance: add items
rep("5. `SystemDocsTest` and the new navigation test pass; the EIR suite is unaffected.",
    "5. `SystemDocsTest` and the new navigation test pass; the EIR suite is unaffected.\n"
    "6. The appearance switch cycles Light, Dark and Follow the device; the choice survives a reload, a new tab and a login on another browser; there is no flash of the wrong theme on load.\n"
    "7. Every screen in 11.3 is checked in both modes: no unreadable text, no white panel on a dark page, charts and modals themed; PDF and Excel outputs unchanged.")

# 11.8 order of work: add UI-5 and 11.9 sweep order
rep("UI-1 the tree and the accent map (half a day); UI-2 the shell: rail, header, period chip (half a day); UI-3 help text and the manuals' navigation chapter with new screenshots (half a day); UI-4 the walk-through of 11.6 on a copy of the database. It is done before P8, so that the new EIR screens of P8 are placed in the suite layout from the start, and after P4b, which does not touch the interface.",
    "UI-1 the tree and the accent map (half a day); UI-2 the shell: rail, header, period chip, the appearance switch, the no-flash script and the shared dark classes (one day); UI-3 help text and the manuals' navigation chapter with new screenshots in both modes (half a day); UI-4 the walk-through of 11.7 items 1 to 6 on a copy of the database; UI-5 the page sweep for dark mode in the order of 11.9 (two days), with item 7 signed off group by group. UI-1 to UI-4 are done before P8, so that the new EIR screens of P8 are placed in the suite layout, and in both modes, from the start; UI-5 may run beside P5 to P7. All of it follows P4b, which does not touch the interface.\n\n"
    "### 11.9 The dark-mode sweep, in order\n\n"
    "The pages are swept in the order a user meets them, and each group is signed off in both modes before the next starts: (1) Dashboard, Workspace and the login pages; (2) the Report Hub and the IFRS 9 reports, which are what the CFO and the auditors open; (3) Data Foundation; (4) Governance Centre, including the Governance Centre screen itself; (5) Financial Modelling; (6) Risk & Regulatory and Monitoring; (7) System Documentation and Administration. The EIR screens are written with both variants from the start and are not part of the sweep.")

# D24 mention
rep("the icon rail, the page header with breadcrumb and the period chip, as built for FDH on the ZNBS pattern.",
    "the icon rail, the page header with breadcrumb, the period chip, and light and dark mode as a per-user setting, as built for FDH on the ZNBS pattern.")

# glossary
rep("- **Breadcrumb**:", "- **Appearance (light, dark, follow the device)**: the per-user choice of how the screens are coloured; kept in the browser for the first paint and on the user's record so that it follows them.\n- **Breadcrumb**:")
open(p, "w", encoding="utf-8").write(s); print("theme section added; 11.5-11.9 numbered")

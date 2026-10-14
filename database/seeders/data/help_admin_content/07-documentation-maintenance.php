<?php

/**
 * Administrator Manual chapter 7 (Ticket #011 depth rewrite; documentation audit 14 October 2026:
 * the menu path, the authoring screen, the screenshot command and how a documentation release is shipped).
 */
return [

    'Documentation Maintenance' => [

        'The four documents and who owns each' => [
            'body' => '<p><b>System Documentation</b> holds the four documents the implementation agreement requires. Open <b>Administration &amp; support</b>, then <b>Support &amp; Maintenance</b>, then <b>System Documentation</b>. They are maintained in two different ways, and knowing which is which saves you looking for an edit button that is not there.</p><ul>'
                . '<li><b>User Manual</b>. Content in the database. Edited in the application by holders of <code>settings</code>. Covers every screen a business user touches.</li>'
                . '<li><b>Administrator Manual</b>, this document. Content in the database, edited the same way.</li>'
                . '<li><b>Technical Manual</b>. Chapter files in the application repository, versioned with the source code and edited by developers in the same change as the code they describe. Carries a live database schema appendix, read when you open it.</li>'
                . '<li><b>Installation Guide</b>. Also repository files, also developer-maintained.</li>'
                . '</ul>'
                . '<h4>What each shows</h4><ul>'
                . '<li>A <b>Cover and contents</b> page: the MAIIC logo and name, the document title, and <b>Document control</b> (<b>Version</b>, <b>Prepared</b>, <b>Owner</b>, <b>Approved by</b>, <b>Classification</b>), the <b>Revision history</b>, the <b>Distribution list</b>, <b>How to use this document</b> and <b>Who reads what</b>.</li>'
                . '<li>In the two manuals: a search box (<b>Search this manual...</b>), the chapter rail with the number of topics in each, and buttons at the foot of a chapter to step to the previous and next chapter.</li>'
                . '<li><b>Download PDF</b> (top right), the same content as a branded PDF.</li>'
                . '<li><b>Edit manual</b> (top right of the two manuals), for holders of <code>settings</code>.</li>'
                . '</ul>'
                . '<h4>Keeping the control block accurate</h4><p>The owner, approver and status on each cover are held in one place in the code. They currently read <b>Draft - pending MAIIC approval</b> for the two manuals and <b>Draft - pending MAIIC review</b> for the other two. When MAIIC formally approves a document, ask Dupleix to update the status, the approver and the revision history, so the cover tells the truth; an auditor reads the cover.</p>'
                . '<h4>Empty states and messages</h4><ul>'
                . '<li><b>This manual has no published content yet</b>: <b>An administrator adds chapters and topics with Edit manual.</b></li>'
                . '<li><b>Nothing in this manual matches</b> the search.</li>'
                . '</ul>'
                . '<h4>Common problems</h4><ul>'
                . '<li><b>The Technical Manual has no Edit manual button.</b> Correct: it is a repository document. Raise a ticket for a change.</li>'
                . '<li><b>The cover says Draft after approval.</b> The control block has not been updated. Raise a ticket.</li>'
                . '</ul>',
            'images' => [
                'help' => 'The User Manual with its cover and contents rail',
                'help-admin' => 'The Administrator Manual',
                'docs-technical' => 'The Technical Manual, rendered from repository files with a live schema appendix',
                'docs-installation' => 'The Installation Guide',
            ],
            'routes' => ['help.admin', 'docs.technical', 'docs.installation'],
        ],

        'Editing a manual' => [
            'body' => '<p>The User Manual and this Administrator Manual are content in the database, so they can be corrected without a developer. Open either manual and press <b>Edit manual</b>. The authoring screen needs the <code>settings</code> permission.</p>'
                . '<h4>What you see on the authoring screen</h4><ul>'
                . '<li>The title <b>Manage</b> and the manual\'s name, two buttons to switch between the <b>User Manual</b> and the <b>Administrator Manual</b> (the one selected is the one you edit; a new chapter is created in it), and <b>View manual</b> back to the reader.</li>'
                . '<li>On the left, a box <b>New chapter title...</b> with <b>Add</b>, and the chapter tree. Each chapter has a green plus, <b>New article in this chapter</b>, and a red bin, <b>Delete chapter</b>. Each article has a red bin, <b>Delete article</b>, and a <b>Draft</b> badge when unpublished.</li>'
                . '<li>On the right, the article editor.</li>'
                . '</ul>'
                . '<h4>Field by field in the editor</h4><ul>'
                . '<li><b>Title</b>. The article heading, shown in the contents rail.</li>'
                . '<li><b>Chapter</b>. Changing it moves the article.</li>'
                . '<li><b>Status</b>. <b>Published</b> or <b>Draft</b>. A draft is hidden from readers and from the PDF.</li>'
                . '<li><b>Body</b>. The rich text editor. Keep to paragraphs, sub-headings, lists and bold.</li>'
                . '<li><b>Numbered steps</b>. <b>+ Add step</b> adds one; the cross, <b>Remove step</b>, removes one.</li>'
                . '<li><b>Shown on pages (route names)</b>. <b>+ Map a page</b>, then <b>Choose a page...</b>; the cross, <b>Remove mapping</b>, removes one. This is what makes the <b>Help for this screen</b> button on that page open this article.</li>'
                . '<li><b>Figures</b>. Once the article is saved: <b>Upload figure</b> with a <b>Caption...</b>; the cross, <b>Remove figure</b>, removes one. Figures are numbered automatically. Before saving it reads <b>Save the article first, then figures can be attached.</b></li>'
                . '</ul>'
                . '<h4>Buttons</h4><ul>'
                . '<li><b>Create article</b> or <b>Save changes</b>. Saves; readers and the PDF see the change at once.</li>'
                . '<li>The bins ask <b>Delete this article?</b> or <b>Delete this chapter?</b> (the chapter with all its articles). Neither can be undone.</li>'
                . '</ul>'
                . '<h4>What happens next</h4><p>An article you save is marked as authored. A later documentation release that refreshes the shipped text leaves it alone; only a full reseed of that manual replaces it (see Shipping a documentation release).</p>'
                . '<h4>Common problems</h4><ul>'
                . '<li><b>A new chapter appeared in the wrong manual.</b> The switcher decides. Delete it and create it again with the right manual selected.</li>'
                . '<li><b>An article does not appear for readers.</b> Its status is Draft.</li>'
                . '<li><b>The help button on a screen opens the contents.</b> No published article maps that screen\'s route. Map it with + Map a page.</li>'
                . '</ul>',
            'steps' => [
                'Open System Documentation, then the manual, and press Edit manual.',
                'Use the switcher to confirm you are editing the right manual.',
                'Choose an article on the left, or press the green plus on a chapter to write a new one.',
                'Edit the title, body and numbered steps, and map the pages the article documents.',
                'Set the status to Published and press Save changes.',
                'Upload figures with captions, then open the reader and download the PDF to check.',
            ],
            'images' => [
                'help-manage' => 'The manual authoring screen with the manual switcher, chapter tree and article editor',
            ],
            'routes' => ['help.manage.index'],
        ],

        'Refreshing the screenshots' => [
            'body' => '<p>The figures in both manuals are photographs of the running system, captured by a command rather than by hand, so they can all be refreshed in one run after an interface change. This is a developer task on a workstation, never on the MAIIC server.</p>'
                . '<h4>How it works</h4><ul>'
                . '<li><code>php artisan manual:screenshots</code> signs in to a running copy of the platform with a headless browser, visits each page on its list and saves a high-resolution picture to <code>public/manual/screenshots</code>, one file per key (for example <code>eir-governance.jpg</code>).</li>'
                . '<li>Pages are identified by route name, so a changed address never breaks the capture.</li>'
                . '<li>If a page renders almost nothing, the command warns and prints the error rather than saving a blank picture. A blank figure means a broken page.</li>'
                . '</ul>'
                . '<h4>What it needs</h4><ul>'
                . '<li>The platform running locally, for example with <code>php artisan serve</code>, and <code>APP_ENV=local</code>.</li>'
                . '<li>In the local <code>.env</code>: <code>MANUAL_SHOT_EMAIL</code> and <code>MANUAL_SHOT_PASSWORD</code> (an account that can open every page), and <code>MANUAL_SHOT_CAPTCHA</code>, the security-check bypass code. The bypass is honoured only in the local environment; leave all three empty in production.</li>'
                . '<li>The bundled browser from <code>npm install</code>, or <code>MAIIC_BROWSER_PATH</code> (or <code>--edge=</code>) pointing at Edge or Chrome.</li>'
                . '</ul>'
                . '<h4>The options</h4><ul>'
                . '<li><code>--base-url=</code> the address the platform answers on (default the configured app address).</li>'
                . '<li><code>--email=</code> and <code>--password=</code> instead of the environment values.</li>'
                . '<li><code>--only=</code> a comma-separated list of keys, to capture a few pages.</li>'
                . '<li><code>--theme=light</code> (the manuals use light mode), <code>dark</code> or <code>system</code>.</li>'
                . '<li><code>--out=</code> another folder.</li>'
                . '</ul>'
                . '<h4>After a capture</h4><ol>'
                . '<li>Look through the refreshed images for anything blank or wrong.</li>'
                . '<li>Commit them with the code, so a fresh installation ships with pictures.</li>'
                . '<li>Ship them to installed systems as described in Shipping a documentation release; the text refresh alone does not attach new figures.</li>'
                . '</ol>'
                . '<h4>Common problems</h4><ul>'
                . '<li><b>Login CAPTCHA is enabled but MANUAL_SHOT_CAPTCHA is not set (local env only).</b> Set the bypass code in the local .env.</li>'
                . '<li><b>No password. Pass --password or set MANUAL_SHOT_PASSWORD.</b></li>'
                . '<li><b>No Chromium browser found.</b> Run npm install, or pass --edge.</li>'
                . '<li><b>Sign-in fails.</b> Usually the wrong address: check the application answering on that port is this one.</li>'
                . '</ul>',
            'steps' => [
                'On a workstation, set MANUAL_SHOT_EMAIL, MANUAL_SHOT_PASSWORD and MANUAL_SHOT_CAPTCHA in the local .env with APP_ENV=local.',
                'Start the platform locally, for example with php artisan serve.',
                'Run php artisan manual:screenshots --theme=light, or --only= with the keys to refresh.',
                'Check the images for anything blank, then commit them with the code.',
                'Ship them with php artisan manual:reseed so the articles carry the new figures.',
            ],
            'images' => [],
            'routes' => [],
        ],

        'Shipping a documentation release' => [
            'body' => '<p>The shipped text of both manuals lives in the code, one file per chapter. An installed system keeps its own copy in the database, so a documentation release has to be loaded into it. There are two ways, and they do different things.</p>'
                . '<h4>1. Refresh the shipped text: HELP_SEED_REFRESH</h4><p><code>HELP_SEED_REFRESH=1 php artisan db:seed --class=HelpContentSeeder</code> (the User Manual) or <code>--class=HelpAdminContentSeeder</code> (this manual), with <code>--force</code> in production. On a manual that already has content:</p><ul>'
                . '<li>It finds each shipped article by its chapter title and article title and replaces its <b>body</b> and <b>numbered steps</b>.</li>'
                . '<li>It skips any article a person has edited in the application, so authored edits survive.</li>'
                . '<li>It adds a new shipped article to an existing chapter, with its body and steps.</li>'
                . '<li>It does <b>not</b> touch figures or page mappings, does not add the figures or mappings of a new article, and does not create a new chapter.</li>'
                . '</ul>'
                . '<p>Without <code>HELP_SEED_REFRESH=1</code> the seeders leave a manual that has content alone.</p>'
                . '<h4>2. Replace the manual: manual:reseed</h4><p><code>php artisan manual:reseed user --force</code> or <code>php artisan manual:reseed admin --force</code> deletes every chapter of that manual, with its articles, steps, figures and page mappings, and loads the shipped content in full: text, steps, figures and mappings. It refuses to run without <code>--force</code>. It discards every edit made in the application to that manual and cannot be undone.</p>'
                . '<h4>Which to use</h4><ul>'
                . '<li>Only wording changed: the refresh.</li>'
                . '<li>The release brings new screenshots, new or changed page mappings, new articles that should open from a screen\'s help button, or a new chapter: <code>manual:reseed</code>, because the refresh does not carry any of those.</li>'
                . '<li>MAIIC has authored edits worth keeping: export them first (download the PDF, or copy the articles), reseed, then make the edits again or ask Dupleix to fold them into the shipped text.</li>'
                . '</ul>'
                . '<h4>What happens next</h4><p>Open both manuals, check the new articles and figures, and download the PDFs. Open a screen whose mapping changed and press its help button.</p>'
                . '<h4>Common problems</h4><ul>'
                . '<li><b>The new article is there but has no pictures and the help button does not find it.</b> It was added by the refresh, which carries no figures or mappings. Run manual:reseed for that manual.</li>'
                . '<li><b>Refusing to discard authored content without --force.</b> Add --force once you are sure.</li>'
                . '<li><b>The seeder said the manual already has content and skipped.</b> HELP_SEED_REFRESH=1 was not set.</li>'
                . '</ul>',
            'steps' => [
                'Read the release note: does it bring new screenshots, page mappings, articles or chapters?',
                'For wording only, run the seeder with HELP_SEED_REFRESH=1 for the manual concerned.',
                'Otherwise, save any authored edits worth keeping, then run php artisan manual:reseed user --force or admin --force.',
                'Open the manual, check the new articles and figures, and download the PDF.',
                'Press the help button on a changed screen to confirm its mapping.',
            ],
            'images' => [],
            'routes' => [],
        ],

    ],

];

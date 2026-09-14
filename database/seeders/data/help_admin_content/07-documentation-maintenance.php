<?php

/**
 * Administrator Manual chapter 7 (Ticket #011 depth rewrite).
 */
return [

    'Documentation Maintenance' => [

        'The four documents and who owns each' => [
            'body' => '<p><b>System Documentation</b> in the sidebar holds the four documents the implementation agreement requires. They are maintained in two different ways, and knowing which is which saves you looking for an edit button that is not there.</p><ul><li><b>User Manual</b>. Content in the database. Edited in the application by anyone with the settings permission. Covers every screen a business user touches.</li><li><b>Administrator Manual</b>, this document. Content in the database, edited the same way.</li><li><b>Technical Manual</b>. Chapter files in the application repository, versioned with the source code. Edited by developers in the same change as the code they describe. Carries a live database schema appendix, read at the moment you open it.</li><li><b>Installation and Configuration Guide</b>. Also repository files, also developer-maintained.</li></ul><h4>What each carries</h4><ul><li>All four open with a cover page: the MAIIC logo, the institution name, the document title, version, prepared date, owner, approver and classification, the Dupleix Institute mark and the confidentiality note.</li><li>A button under the cover expands document control, revision history, the distribution list, how to use the document and a role map.</li><li>A contents rail on the left and a <b>Download PDF</b> button.</li></ul><h4>Keeping the control block accurate</h4><p>The owner, approver and status shown on each cover are held in one place in the code. They currently read <b>Draft, pending MAIIC approval</b> with the approver pending. When MAIIC formally approves a document, ask Dupleix to update the status, the approver and the revision history, so the cover tells the truth. That is a one-line change, and it matters: an auditor reads the cover.</p><h4>Common problems</h4><ul><li><b>The Technical Manual has no Edit button.</b> Correct. It is a repository document; raise a ticket for a change.</li><li><b>The cover says Draft after approval.</b> The control block has not been updated. Raise a ticket.</li></ul>',
            'images' => [
                'help' => 'The User Manual with its cover and contents rail',
                'docs-technical' => 'The Technical Manual, rendered from repository files with a live schema appendix',
                'docs-installation' => 'The Installation and Configuration Guide',
            ],
            'routes' => ['help.index', 'help.admin', 'docs.technical', 'docs.installation'],
        ],

        'Editing a manual' => [
            'body' => '<p>The User Manual and this Administrator Manual are content in the database, so they can be corrected without a developer. Open either manual and press <b>Edit manual</b>.</p><h4>What you see on the authoring screen</h4><ul><li>Pills at the top to switch between the <b>User Manual</b> and the <b>Administrator Manual</b>. Whichever is selected is the manual you are editing; a new chapter is created in that manual.</li><li>A <b>View manual</b> link back to the reader.</li><li>On the left, a box to add a chapter and the chapter tree. Each chapter has a green plus to add an article and a red bin to delete the chapter and everything in it. Each article has a red bin of its own and a <b>Draft</b> badge if it is unpublished.</li><li>On the right, the article editor, empty until you choose an article.</li></ul><h4>Field by field in the editor</h4><ul><li><b>Title</b>. The article heading, shown in the contents rail.</li><li><b>Chapter</b>. Which chapter it belongs to; changing this moves the article.</li><li><b>Status</b>. <b>Published</b> or <b>Draft</b>. A draft is hidden from readers and from the PDF, so use it while you are working.</li><li><b>Body</b>. The rich text editor. Keep to paragraphs, sub-headings, lists and bold; the styling comes from the manual, not from you.</li><li><b>Numbered steps</b>. A repeater. Press <b>+ Add step</b> to add one and the red cross to remove one. These render as the numbered green circles readers follow.</li><li><b>Shown on pages (route names)</b>. Press <b>+ Map a page</b> and choose a page. This is what makes the help button on that page open this article.</li><li><b>Figures</b>. Available once the article is saved. Upload an image with a caption; figures are numbered automatically across the manual.</li></ul><h4>What happens next</h4><p>Saving publishes immediately for readers and changes the PDF, because both are generated from these rows. There is no separate publish step beyond the status field.</p><h4>Reloading the shipped text</h4><p>If a manual has been edited and you want the shipped version back, a developer can run the reseed command, which discards the authored edits for that manual and reloads the text that ships with the code. Ask for it deliberately; it is not reversible.</p><h4>Common problems</h4><ul><li><b>A new chapter appeared in the wrong manual.</b> The pills decide. Delete it and create it again with the right manual selected.</li><li><b>The figures section is missing.</b> Save the article first; figures attach to a saved article.</li><li><b>An article does not appear for readers.</b> Its status is Draft.</li></ul>',
            'steps' => [
                'Open System Documentation, then the manual you want to change, and press Edit manual.',
                'Use the pills to confirm you are editing the right manual.',
                'Choose an article on the left, or press the green plus on a chapter to write a new one.',
                'Edit the title, body and numbered steps, and map the pages the article documents.',
                'Set the status to Published and save.',
                'Upload figures with captions, then open the reader and download the PDF to check the result.',
            ],
            'images' => [
                'help-manage' => 'The manual authoring screen with the manual switcher, chapter tree and article editor',
            ],
            'routes' => ['help.manage.index', 'help.index', 'help.admin'],
        ],

        'Refreshing the screenshots' => [
            'body' => '<p>The figures in both manuals are photographs of the running system, captured by a command rather than taken by hand, so they can all be refreshed in one run after an interface change. There are 121 pages in the capture list, covering every user-facing screen and create form.</p><h4>How it works</h4><ul><li>The command signs into a running copy of the platform, visits each configured page and photographs it at high resolution.</li><li>Pages are identified by their internal route names rather than by address, so a changed URL never breaks the capture.</li><li>Long report and data pages are captured whole; the rest are captured as one screenful.</li><li>If a page renders almost nothing, the command warns and prints the underlying error rather than saving a blank picture. A blank figure in a manual means a broken page.</li></ul><h4>What it needs</h4><ul><li>A copy of the platform running locally, and the address it is running on.</li><li>Screenshot credentials and a security-check bypass code set in the local environment file. The bypass works only in a local environment and is inert in production.</li><li>The debug bar switched off, or it appears across the bottom of every figure.</li></ul><p>This is a developer task run on a workstation, not something done on the MAIIC server. The Installation Guide carries the exact command.</p><h4>After a capture</h4><ol><li>Look through the refreshed images for anything blank or obviously wrong.</li><li>Commit them with the code, so a fresh installation ships with pictures.</li><li>Open both manuals and download the PDFs to confirm the figures appear.</li></ol><h4>Common problems</h4><ul><li><b>Sign-in fails during capture.</b> Usually the wrong address: check the platform actually answering on that port is this one, not another application.</li><li><b>The debug bar appears in every figure.</b> Switch it off and capture again.</li><li><b>One figure is blank.</b> That page is broken. Raise a ticket rather than accepting the picture.</li></ul>',
            'images' => [
                'help-manage' => 'Captured figures appear against the articles they illustrate',
            ],
            'routes' => ['help.manage.index'],
        ],

    ],

];

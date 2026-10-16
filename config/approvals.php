<?php

/*
 * Approval workflows (Staging Module Specification v1, section 8.6).
 *
 * require_evidence: an item of a workflow whose row of the specification's
 * table requires evidence is not approved without a supporting document
 * (App\Support\Approvals::assertEvidence). It is fixed here, not read from the
 * environment, so a server cannot switch it off; only a test of other
 * behaviour turns it off, and the evidence rule has tests of its own.
 */
return [
    'require_evidence' => true,
];

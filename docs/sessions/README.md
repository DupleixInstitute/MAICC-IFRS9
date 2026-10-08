# Session transcripts

Full transcripts of the Claude Code sessions in which the E-Banker extracts were analysed and specification v4 was written, exported from the session log so that another person, or another AI tool, can follow every step, every query and every figure.

| Transcript | Covers | Formats |
|---|---|---|
| `Claude_Session_Transcript_Raw_Queries_2026-10-05` | 5 to 7 October 2026: MAIIC's three extract scripts analysed; the 27 data-dictionary queries; the 18 follow-up extracts specified, received and audited; the notes to Barry, Finance and Credit; the take-on mapping workbook | .md, .pdf |
| `Claude_Session_Transcript_Spec_v4_2026-10-07` | 7 to 8 October 2026: the GL and Zaithwa Farms results; the Governance Centre settings; the spec v4 sections 6 to 15 (ingestion, UI, audit workbooks, macro, forward-looking, scenarios); the bootstrap inputs; the reference code; the merge to master | .md, .pdf |

The scripts the transcripts run are in `docs/build-files/`, with a README that says which script makes which output. Client data appears in the transcripts where a query result was read; the repository is private and access to it is governed as access to the production database is (spec v4, section 6.10).

Built by `docs/build-files/build_transcript.py` and `build_transcript_spec.py` from the session log.

- `Claude_Session_Transcript_Build_2026-10-08` (.md, .pdf): the build session of 8 October 2026, from "please build the code and do everything as per the spec" to the after-build report; the code of commits `32e7d99` to `40b5beb`, the clean-install proof, the findings. Built by `docs/build-files/build_transcript_build.py`.

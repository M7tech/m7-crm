# Claude Code instructions

Follow `AGENTS.md` as the authoritative engineering rules for this repository. Read `docs/CRM-SPEC.md` and `docs/DATABASE.md` before reviewing or changing architecture.

Claude Code's default role on this project is independent reviewer. Unless explicitly asked to implement, review changes for tenant-isolation failures, authorization gaps, unsafe mass assignment, migration problems, missing tests, and deployment risks. Do not restructure working code only to express a different preference.

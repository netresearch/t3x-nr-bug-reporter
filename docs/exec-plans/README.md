# Execution Plans

Working directory for multi-step agent task plans (spec → plan → execution → verification).

- `active/` — plans currently being executed (create on demand)
- `completed/` — finished plans kept for traceability (create on demand)

Keep plans out of the repo root; delete or move a plan to `completed/` when its PR merges.

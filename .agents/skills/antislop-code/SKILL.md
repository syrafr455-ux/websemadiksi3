---
name: antislop-code
description: "Code comment hygiene for AI coding agents: remove generic AI-slop comments, keep the valuable ones, never touch the code."
allowed-tools: Read Write Edit Glob Grep
---
# antislop-code

> Anti Slop: Rules for AI Coding Agents. Code Comments skill

Filters comments that read as generically AI while preserving comments that carry real information.

## Rules
- **No Decorative Separators**: Do not add banner comments like `// ====== AUTHENTICATION ======` or box-drawing characters (R-31).
- **No Restating the Obvious**: Remove redundant comments like `// Set counter: count = 0`.
- **No Workflow Narration**: Remove `// Step 1: ...`, `// Step 2: ...`.
- **Preserve Non-Obvious Rationale**: Keep architectural notes, security justifications, edge-case rationale, and bugfix explanations.

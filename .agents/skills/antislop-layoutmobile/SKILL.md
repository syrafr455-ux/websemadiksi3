---
name: antislop-layoutmobile
description: "Mobile layout skill for antislop. Use for layouts that reflow across screen sizes, phone to desktop: grids, overflow, tap targets. Load with the core."
allowed-tools: Read Write Edit Glob Grep
---
# antislop-layoutmobile

> Anti Slop: Rules for AI Coding Agents. Mobile Layout skill

Part of the antislop system. Deep-dives responsive layout: how a layout reflows across screen sizes, phone to desktop.

## Principles
- **Mobile is a distinct designed state, not desktop shrunken down** (R-03).
- **Reflow with Intent**: Re-stack multi-column grids into single-column or 2-column cards as appropriate.
- **Tap Targets**: Touch targets must be at least $44 \times 44\text{px}$ on mobile viewports.
- **Fluid Typography & Spacing**: Adjust heading scale and section padding on mobile viewports.
- **No Overflow**: Prevent horizontal scrolling caused by rigid minimum widths or unconstrained absolute elements.

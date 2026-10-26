---
name: antislop-human
description: "Human and accessibility skill for antislop. Contrast, keyboard, focus, and states for real people. Includes the contrast checker."
allowed-tools: Read Write Edit Glob Grep
---
# antislop-human

> Anti Slop: Rules for AI Coding Agents. Human skill

Part of the antislop system. Deep-dives the human concern: the UI must stay usable by people with different eyes, hands, and setups.

## Color & Contrast
- **WCAG AA Minimums**: Normal text must meet $\ge 4.5:1$ ratio; large text (18px+) must meet $\ge 3:1$ (R-25).
- **No Grey-on-Grey Hallucination**: Do not eyeball or assume grey-on-grey passes contrast. Check actual luminance.
- **Non-Text Contrast**: Interactive borders, buttons, and status icons must maintain $\ge 3:1$ contrast against adjacent surfaces (WCAG 1.4.11).

## Keyboard & Focus States
- **Clear Focus Ring**: Always provide a visible, high-contrast focus indicator (`focus-visible:ring-2 focus-visible:ring-primary focus-visible:outline-none`).
- **Logical Tab Order**: Interactive elements follow logical DOM order.

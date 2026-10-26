---
name: antislop-ui
description: "UI and visual skill for antislop. Use when building or editing any interface: color, layout, components, motion. Load with the core."
allowed-tools: Read Write Edit Glob Grep
---
# antislop-ui

> Anti Slop: Rules for AI Coding Agents. UI & Visual skill

Part of the antislop system. Read together with `antislop.md` (the core). This skill deep-dives the UI/visual concern: color, layout, components, decoration, structural flow, and motion.

## Visual & Color
- **Generic Blue-Purple Gradient**: Forbidden without purpose (R-01). Pull palette from `DESIGN.md` or brand identity.
- **Excessive Glassmorphism**: Treat glass as an accent, not a character trait. Max 1-2 elements (R-10).
- **Excessive Border Radius**: Uniform pill shapes erase visual hierarchy. Set consistent radii (R-11).
- **Overly Soft Shadows**: Use shadow as an elevation marker only (R-12). Most elements sit flat.
- **Glow Everywhere**: Reserve glow for at most 1-2 important elements as a focus accent (R-13).
- **Background Grid & Blobs**: Avoid gratuitous grid lines, dots, or blurred organic blobs without purpose (R-07).
- **Palette Discipline**: Cap active palette at 2-3 core colors + 1 accent (R-29).

## Layout & Components
- **Monotonous Template Layout**: Avoid cookie-cutter layouts (hero, 2 CTAs, 3-step cards, FAQ, footer). Build around actual content needs (R-05, C-3).
- **Copy-Paste Feature Cards**: Vary card layouts to reflect content hierarchy (R-14).
- **Functional Completeness**: Every card or interactive element must work or not exist (C-2).
- **Authentic Assets**: Use real logos, real event headers, and avoid generic foreign stock photo placeholders (R-38, C-5).

# AGENTS INSTRUCTIONS — PT BPR MITRATAMA ARTHABUANA
## Anti-Slop & Design Standard Enforcement

<EXTREMELY_IMPORTANT>
Whenever working on the UI, copywriting, layout, or frontend code for this repository, you MUST adhere to the **Anti-Slop Standard** and the official **DESIGN DIRECTION**:

1. **Design Direction Source**: Read `DESIGN.md` before making any visual or interface changes. `DESIGN.md` defines the institutional Kombai Teal palette, typography, hierarchy, and soul of PT BPR Mitratama Arthabuana.
2. **Quality Filter (Anti-Slop)**: Follow the 40 anti-slop rules defined in `.agents/rules/antislop.md` and the skills in `.agents/skills/`:
   - `skills/antislop/SKILL.md` (Core Filter)
   - `skills/antislop-ui/SKILL.md` (UI & Visual Hierarchy)
   - `skills/antislop-copywriting/SKILL.md` (Human Copy, Zero Buzzwords)
   - `skills/antislop-human/SKILL.md` (Accessibility & Focus States)
   - `skills/antislop-layoutmobile/SKILL.md` (Mobile & Responsive Craft)
   - `skills/antislop-code/SKILL.md` (Clean Code Comments)

3. **Strict Constraints**:
   - **R-02**: NO em dash (`—`) in UI copy. Use `-`, `:`, or `•` instead.
   - **R-08 / R-10**: NO generic AI sparkle/magic icons (`bi-magic`, `bi-stars`). Use functional icons (`bi-gear`, `bi-arrow-repeat`, `bi-clock`, `bi-lightning-charge`).
   - **R-25**: Ensure WCAG AA color contrast. No washed-out grey-on-grey text.
   - **R-03**: Flawless mobile responsiveness (min touch target 44px, no overflow, no duplicate headers).
   - **Constraint**: STRICTLY NEVER mention "POJK" or "OJK" anywhere in code, UI, or commit messages.
   - Every interactive element (button, link, modal, tab) MUST be 100% functional (R-26).
</EXTREMELY_IMPORTANT>

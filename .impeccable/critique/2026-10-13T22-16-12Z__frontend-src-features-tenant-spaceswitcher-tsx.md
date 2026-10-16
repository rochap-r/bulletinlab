---
target: switch tenant Mes espaces
total_score: 17
max_score: 40
na_heuristics:
p0_count: 0
p1_count: 3
target_identity: "file:C:\\Users\\Rochap\\Herd\\bulletinlab\\frontend\\src\\features\\tenant\\SpaceSwitcher.tsx"
target_fingerprint: "sha256:ec2c3f17e38d733ea7488b51562297d2bc967a16d1bfa08b36839799de01d78e"
target_path: "C:\\Users\\Rochap\\Herd\\bulletinlab\\frontend\\src\\features\\tenant\\SpaceSwitcher.tsx"
timestamp: 2026-10-13T22-16-12Z
slug: frontend-src-features-tenant-spaceswitcher-tsx
---
# Critique — Switch tenant (SpaceSwitcher + Mes espaces + portail enseignant)

Method: dual-agent. Health: 17/40 (Poor). N/A: none.
Specificity: interchangeable with copy swap; flow is product-specific, visual language is stock shadcn.
Detector: clean (0 findings, 5 files); blind to semantics (svg aria-label w/o role=img, no h1 on EspacesPage/login, sm buttons 28px < 44px target).

## Heuristics
1. Visibility of System Status: 1 — header switch fires and closes with zero feedback; no success toast; trigger shows no pending state.
2. Match System / Real World: 2 — Mes espaces/Ouvrir/Ouvert fit; raw codes ADMIN_ECOLE/TITULAIRE/notes.enter leak to users.
3. User Control and Freedom: 2 — one-click switch, no confirm; no cancel mid-flight; Changer de compte escape exists.
4. Consistency and Standards: 2 — primitives consistent; same concept labeled 3 ways; current shown 3 ways (check vs Ouvert badge vs text row).
5. Error Prevention: 1 — invisible ?espace= lock-in; EN_ATTENTE selectable with no pre-click affordance.
6. Recognition Rather Than Recall: 2 — names + current highlight + initial avatar good; colliding school names only differentiated by cryptic codes; truncated trigger, no tooltip.
7. Flexibility and Efficiency: 2 — fine for 2-3 spaces; no search/recency/shortcut; w-64 truncation.
8. Aesthetic and Minimalist: 2 — EspacesPage calm; TeacherHomePage offers the same choice 3 times in one viewport.
9. Error Recovery: 1 — friendly 401; switch failure = toast only; expired select-token bounces silently to /connexion.
10. Help and Documentation: 2 — best-in-class empty state; nowhere explains espace-vs-école, single-active-tenant, tacit role routing.

## Cognitive load: 2/8 pass (high). N+2 actions at the choice point; no primary/recommended signal.

## Priority issues
- P1 Silent switch (no pending on trigger, no arrival confirmation; double-tap + wrong-school grading risk) → animate + harden.
- P1 Raw role codes as labels (dropdown string, badges, tacit routing) → clarify with plain-French role map.
- P1 Expired select-token bounce without explanation (common on shared phones) → harden expiry path with reason + phone preserved.
- P2 Triple redundancy on teacher home (dropdown + card + Espace courant row) → distill to one switch surface per context.
- P2 Switcher vanishes at N<=1; EN_ATTENTE unmarked pre-click → onboard hint + pending badge.

## Personas
- Alex: no default/last-used focus, no filter, w-64 truncation, whole list disabled during switch.
- Sam: trigger announces truncated name only; no aria-live arrival; svg Check naming inconsistent; errors toast-only.
- Jordan: undefined "espace", near-identical rows told apart by codes, invisible homeForRoles routing, unrecognizable switcher affordance.

## Minor
- Header ignores useSpaces pending/error; 5-min TTL cache invisible (no refresh affordance); ghost Changer de compte weaker than brand link; menu label duplicates page titles; sm buttons 28px tall; password schema mixes straight/typographic apostrophes.

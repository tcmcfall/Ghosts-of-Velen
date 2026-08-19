# Volo's Guide — Roadmap and Continuity Controls

## Objective

Make the Ghosts of Velen repository self-contained enough that a future session can resume Volo development by reading this directory rather than relying on conversational memory.

## Working-source integration status — 2026-08-19

The current working artifacts have now been reviewed directly.

- `Volos_Master_POI_Ledger_Cape_Velen_v1.1.7.xlsx` is the **authoritative current POI ledger**.
- The unversioned `Volos_Master_POI_Ledger_Cape_Velen.xlsx` is a materially older/legacy ledger and must not overwrite v1.1.7 wording.
- `Canon_Bible_Book_I_Geography_Chapter_1_The_Land_of_Cape_Velen.docx` has been integrated into repository-native Markdown at `canon/book-i-geography/chapter-01-the-land-of-cape-velen.md`.
- `Volos_Guide_to_Cape_Velen_First_Pass_Outline.docx` is byte-for-byte identical to that Canon Bible DOCX despite its filename; it is therefore treated as a duplicate legacy artifact rather than a distinct outline.
- `Volos_signature.png` is the current Volo signature publication asset and is now stored at `assets/Volos_signature.png`.
- Exact source hashes and committed binary repository paths are recorded in `research/current-working-files-inventory.md`.
- POI completion, legacy differences, ratified source normalizations, and the next writing sequence are recorded in `gazetteer/ledger-status-and-action-map.md`.

The supplied source binaries are now physically stored under `sources/` and `assets/`. The repository therefore preserves both the integrated Markdown content and the original binary artifacts needed to audit or refresh that content later. Their SHA-256 hashes prevent ambiguity about which files belong there.

## What has been recovered/integrated into the repository

- Campaign/world canon central to Volo's journey.
- The approved current political and historical frame.
- Aurumandur and the Thousand Truths.
- Principal named cast and character roles.
- Full narrative itinerary and major scene beats.
- Velen first-arrival principles.
- Wealdath crossing and Moonbrook's death.
- Woodfaire's architecture, politics, coexistence, and public-house murder.
- Final ducal-court sequence.
- Terrace luncheon and palace lodging.
- Final departure/farewell and "paper sails" ending.
- Working Introduction.
- Working Chapter One, "A Long History... Briefly Told."
- Canon Bible Book I, Geography, Chapter One source text.
- Direct structural/content review of the v1.1.7 POI ledger.
- Direct comparison of v1.1.7 against the older unversioned ledger.

## POI ledger authority

The v1.1.7 ledger is now the primary wording source for POI entries. Do not reconstruct intermediate location prose from conversational summaries when the workbook supplies it.

The workbook's standard prose fields are:

- What Would Draw Volo Here?
- First Impression
- Who Rules or Holds Sway?
- What the Locals are Known For
- What to Eat or Drink
- Where to Sleep
- What to Buy
- What to Avoid
- Local Tale or Rumor
- Volo's Rating

Current completeness:

- Complete 10/10: Cape Velen, Velen, Saltmarsh, Tulmene, Monguldarath, Oakbottom, Jhaansciim, Honorguard House, Shoremeet, Tordraken, Woodfaire.
- The Bite: 3/10; requires deliberate expansion.
- The Wealdath: 8/10; missing What to Buy and Local Tale/Rumor, with shopping likely intentionally inapplicable.
- Velean Noble Court: 7/10; missing What to Buy, What to Avoid, Local Tale/Rumor.

See `gazetteer/ledger-status-and-action-map.md` before editing any POI.

## Ratified continuity decisions now in force

The workbook/DOCX review revealed several legacy wording mismatches. They are now resolved for this branch and should be normalized deliberately whenever those source artifacts are revised:

1. `Duke Alric Thorne` in the workbook is treated as a typo. The canonical form is **Aldric Thorne**.
2. `Dragon's Head` is superseded wording. The canonical geographic name is **Dragon's Neck Peninsula**.
3. Cape Velen presently uses the **two-county model**: **Firedrake** and **Fyraven**. The earlier third-county model is historical/obsolete in this continuity; the lost third county was conquered by **Muranndin**.
4. The governing revolution continuity is **Duke Calchais / 1480 DR / Night of Blood** and should overwrite older local variants elsewhere in GoV rather than being blended with them.

## Manuscript next steps

1. Source-check Chapter One against approved *Lands of Intrigue* and other source material, especially:
   - Cape Velen's early Tethyrian status,
   - original duke/provincial history,
   - naval role against Nelanther pirates/corsairs/reavers,
   - independence chronology,
   - trade production/imports,
   - Acoval's Cove,
   - Horn Cliffs,
   - Velen/Blackthorn/Great Wave history.
2. Keep Chapter One to roughly two illustrated pages in final layout.
3. Build travel chapters directly from v1.1.7 in journey order rather than from memory, beginning with the Cape Velen overview and Velen arrival material.
4. Complete **The Bite** entry without inventing commerce or settlement where the location's nature makes a category inapplicable.
5. Review the two missing **Wealdath** fields; preserve the established decision that shopping does not meaningfully apply there unless later canon changes it.
6. Complete only useful missing **Velean Noble Court** fields and preserve the family/court/terrace sequence.
7. Preserve the narrative escalation: skepticism -> curiosity -> frontier reality -> fear -> grief -> hope -> painful imperfection -> understanding.
8. Close with the recovered departure scene.

## Writing constraints

- Volo should be witty and self-aware without becoming a caricature.
- Do not turn every paragraph into jokes.
- Player-facing prose must not expose DM-only secrets.
- Historical/political prose may retain ambiguity where Volo himself cannot know the truth.
- Avoid excessive local name-dropping when a broad visual impression is stronger.
- Do not convert developed nuance into propaganda for Aldric.
- Do not flatten Aldric into a tyrant either. The tension is the point.
- The Rebirth should feel plausible enough to inspire devotion and alarming enough to concern neighboring governments.
- Woodfaire must remain beautiful **and** imperfect.
- The Wealdath must remain genuinely dangerous.

## Rules constraints

When encounters or mechanics are developed, prefer:

1. PHB 2024
2. DMG 2024
3. Monster Manual 2025

Use older material only where not superseded or where setting/lore requires it.

The Wealdath ogre encounter must be rebuilt against the 2025 Monster Manual if/when mechanical statistics are added.

## Negative continuity / do-not-invent list

- Do not claim Keledek was mentioned in Monguldarath.
- Do not add named NPC meetings simply because an NPC exists in *Ghosts of Saltmarsh*.
- Do not treat druids as an elven race.
- Do not make elves herbivores by default.
- Do not make Velen's ghosts invisible or universally hostile.
- Do not make Woodfaire a utopia.
- Do not make Aldric's coup bloodless.
- Do not make Aldric ask Volo for favorable coverage.
- Do not make the western Wealdath a safe, wondrous stroll.
- Do not use the older unversioned ledger to overwrite v1.1.7 prose.

## Resume instruction

To pick up where this workstream now stands, read in this order:

1. `README.md`
2. `canon/campaign-canon.md`
3. `canon/book-i-geography/chapter-01-the-land-of-cape-velen.md`
4. `characters/principal-cast.md`
5. `gazetteer/ledger-status-and-action-map.md`
6. `reconstruction/volo-journey-and-approved-scenes.md`
7. `manuscript/00-introduction.md`
8. `manuscript/01-a-long-history-briefly-told.md`
9. `manuscript/99-recovered-closing-scene.md`
10. `research/current-working-files-inventory.md`
11. This roadmap.

The next substantive work should proceed directly to POI-to-manuscript integration from v1.1.7, beginning with the Cape Velen overview and Velen arrival while finishing the intentionally incomplete Bite, Wealdath, and Noble Court entries.

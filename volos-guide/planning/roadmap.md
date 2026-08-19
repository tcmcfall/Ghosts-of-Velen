# Volo's Guide — Roadmap and Continuity Controls

## Objective

Make the Ghosts of Velen repository self-contained enough that a future session can resume Volo development by reading this directory rather than relying on conversational memory.

## Working-source integration status — 2026-08-19

The current working artifacts have now been reviewed directly.

- `Volos_Master_POI_Ledger_Cape_Velen_v1.1.7.xlsx` is the **authoritative current POI ledger**.
- The unversioned `Volos_Master_POI_Ledger_Cape_Velen.xlsx` is a materially older/legacy ledger and must not overwrite v1.1.7 wording.
- `Canon_Bible_Book_I_Geography_Chapter_1_The_Land_of_Cape_Velen.docx` has been integrated into repository-native Markdown at `canon/book-i-geography/chapter-01-the-land-of-cape-velen.md`.
- `Volos_Guide_to_Cape_Velen_First_Pass_Outline.docx` is byte-for-byte identical to that Canon Bible DOCX despite its filename; it is therefore treated as a duplicate legacy artifact rather than a distinct outline.
- `Volos_signature.png` is the current Volo signature publication asset.
- Exact source hashes and intended binary repository paths are recorded in `research/current-working-files-inventory.md`.
- POI completion, legacy differences, source-level continuity flags, and the next writing sequence are recorded in `gazetteer/ledger-status-and-action-map.md`.

The available GitHub connector writes UTF-8 text but does not accept mounted local binaries as file arguments. Consequently the source **content and provenance are integrated**, while the exact XLSX/DOCX/PNG binaries remain queued for physical commit through a binary-capable Git path. Their SHA-256 hashes prevent ambiguity about which files belong there.

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

## Source-level continuity review required

The actual v1.1.7 workbook revealed several items that need controlled cleanup rather than silent correction:

1. `Duke Alric Thorne` appears in the Cape Velen rule/authority cell; current campaign canon uses **Aldric Thorne**.
2. The same cell uses `Dragon's Head`; current GoV standardization uses **Dragon's Neck Peninsula**. Determine whether the workbook intended a smaller local geographic feature before changing it.
3. v1.1.7 says Cape Velen has **two counties** (Firedrake and Fyraven), while the older unversioned workbook says three. v1.1.7 has source precedence, but global canon should explicitly ratify the two-county model.
4. The previously identified revolution continuity conflict remains: the recovered Volo workstream uses **Duke Calchais / 1480 DR / Night of Blood**, while other GoV development may contain another predecessor/date.

Do not normalize these issues by accident. Resolve them explicitly and then make surgical source updates.

## Manuscript next steps

1. **Ratify the continuity items above** so the manuscript and ledger share one political/geographic model.
2. Source-check Chapter One against approved *Lands of Intrigue* and other source material, especially:
   - Cape Velen's early Tethyrian status,
   - original duke/provincial history,
   - naval role against Nelanther pirates/corsairs/reavers,
   - independence chronology,
   - trade production/imports,
   - Acoval's Cove,
   - Horn Cliffs,
   - Velen/Blackthorn/Great Wave history.
3. Keep Chapter One to roughly two illustrated pages in final layout.
4. Build travel chapters directly from v1.1.7 in journey order rather than from memory.
5. Complete **The Bite** entry without inventing commerce or settlement where the location's nature makes a category inapplicable.
6. Review the two missing **Wealdath** fields; preserve the established decision that shopping does not meaningfully apply there unless later canon changes it.
7. Complete only useful missing **Velean Noble Court** fields and preserve the family/court/terrace sequence.
8. Preserve the narrative escalation: skepticism -> curiosity -> frontier reality -> fear -> grief -> hope -> painful imperfection -> understanding.
9. Close with the recovered departure scene.
10. Physically commit the exact XLSX/DOCX/PNG source binaries under the paths listed in `research/current-working-files-inventory.md` when a binary-capable Git upload path is available.

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

The next substantive work should begin with explicit continuity ratification and then direct POI-to-manuscript integration from v1.1.7.
# Volo's Guide — Roadmap and Continuity Controls

## Objective

Make the Ghosts of Velen repository self-contained enough that a future session can resume Volo development by reading this directory rather than relying on conversational memory.

## What has been recovered into the repository

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

## Material that still needs direct artifact import

The original Volo work relied on a POI spreadsheet/ledger containing location-by-location prose. That artifact contains wording more exact than can be reconstructed safely from conversational summaries.

When the ledger is available in the repository or supplied again:

1. Preserve the workbook unchanged as a source artifact.
2. Export each location's approved prose into `gazetteer/` Markdown without paraphrasing.
3. Preserve cell coordinates/source references where useful for traceability.
4. Import the NPC/first-impression appendices only from actual ledger contents.
5. Do not infer missing NPC encounters from *Ghosts of Saltmarsh*.

## Known continuity issue requiring explicit resolution

Earlier GoV development outside the recovered Volo thread used alternate prior-duke names/dates. The latest explicit Volo continuity says:

- Prior ruler: **Duke Calchais**.
- Revolution: **1480 DR**.
- Event: **Night of Blood**, violent coup/civil fighting with hundreds dead.

Do not silently change either version. When global campaign canon is consolidated, make an explicit decision and update all affected files surgically.

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
3. Begin the Velen chapter with Volo's stormy arrival from the north and relief at dry land.
4. Build the travel chapters from the POI ledger in itinerary order.
5. Preserve the narrative escalation:
   - skepticism,
   - curiosity,
   - frontier reality,
   - fear,
   - grief,
   - hope,
   - painful imperfection,
   - understanding.
6. Close with the recovered departure scene.

## Volo POI entry pattern

The recurring travel-entry questions developed during the original work include:

- What brought Volo here?
- Volo's First Impression
- Who rules or holds sway?
- What are the locals known for?
- What to eat or drink?
- Where to sleep?
- What to buy?
- What to avoid?
- Local tale or rumor
- Volo's rating

Not every category applies to every location. Example: "What to buy?" deliberately does not meaningfully apply to the dangerous wilderness crossing of the Wealdath.

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

## Resume instruction

To pick up where the original Volo conversation left off, read in this order:

1. `README.md`
2. `canon/campaign-canon.md`
3. `characters/principal-cast.md`
4. `reconstruction/volo-journey-and-approved-scenes.md`
5. `manuscript/00-introduction.md`
6. `manuscript/01-a-long-history-briefly-told.md`
7. `manuscript/99-recovered-closing-scene.md`
8. This roadmap.

Then import/read the POI ledger before attempting to recreate exact intermediate location prose.
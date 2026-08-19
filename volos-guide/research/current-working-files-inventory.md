# Current Volo Working Files — Source Inventory

This inventory records the working artifacts supplied for repository integration on 2026-08-19 and now stored directly inside the `volos-guide` tree. It exists so future work can identify the exact source version used during reconstruction, verify parity, and detect accidental substitution.

| Working file | SHA-256 | Status / role |
|---|---|---|
| `Volos_Master_POI_Ledger_Cape_Velen_v1.1.7.xlsx` | `1f18872e71c9dccbc3a09e8387f41f31851f9c33235ce46227479ee160113d35` | **Current authoritative POI ledger**. 14 location rows plus header; includes the new Velean Noble Court entry and partial Bite/Wealdath entries. |
| `Volos_Master_POI_Ledger_Cape_Velen.xlsx` | `87515c156e33e2e9f9de467b8e0821603b62418715e34f838f36ed98472de37c` | Earlier unversioned ledger. Retain as provenance/legacy comparison; do not supersede v1.1.7 with it. Contains 41 location rows, many only scaffolded with a single filled field, including Tarseth Bay and other legacy entries absent from v1.1.7. |
| `Volos_Guide_to_Cape_Velen_First_Pass_Outline.docx` | `8d880cc955b937ecfe40ba55030d5cd44ecd6d784da5db1541ccfe2e5c58d640` | Despite filename, content is the Canon Bible Geography Chapter One draft. Byte-identical to the Canon Bible DOCX below. |
| `Canon_Bible_Book_I_Geography_Chapter_1_The_Land_of_Cape_Velen.docx` | `8d880cc955b937ecfe40ba55030d5cd44ecd6d784da5db1541ccfe2e5c58d640` | Current Geography Chapter One source artifact. Its complete text is integrated at `canon/book-i-geography/chapter-01-the-land-of-cape-velen.md`. |
| `Volos_signature.png` | `97bd3b6922196901ff5644c6297bf63613a9e75b123ce70e45b813e3fa8bc93e` | Volo signature visual asset intended for publication/layout use. |

## In-repo source storage

The exact supplied XLSX/DOCX/PNG binaries now live in the repository under the paths below. The source **content** remains integrated into repository-native Markdown, while the hashes above remain the authoritative parity check for the binary artifacts themselves:

- `volos-guide/sources/working/Volos_Master_POI_Ledger_Cape_Velen_v1.1.7.xlsx`
- `volos-guide/sources/legacy/Volos_Master_POI_Ledger_Cape_Velen.xlsx`
- `volos-guide/sources/working/Canon_Bible_Book_I_Geography_Chapter_1_The_Land_of_Cape_Velen.docx`
- `volos-guide/sources/legacy/Volos_Guide_to_Cape_Velen_First_Pass_Outline.docx`
- `volos-guide/assets/Volos_signature.png`

These binaries are source artifacts and provenance records, not hidden instruction files. Canon and manuscript decisions still come from the approved Markdown canon files and explicit ratified project decisions.

## Version precedence

For POI prose, use `v1.1.7` over the unversioned workbook unless an explicit recovery task is examining material that disappeared between versions. Never merge old and new cell wording silently. Any recovered legacy-only material must be labeled as such and reviewed before becoming current canon.

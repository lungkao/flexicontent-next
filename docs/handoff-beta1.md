# Handoff: 6.1.0-alpha.7 → 6.1.0-beta.1

**Branch:** `release/6.1.0-beta`
**Date:** 2026-05-17
**Status:** Manual browser verification required before tag

---

## Scope

ปิด Priority 1 backlog ก่อน promote alpha → beta:

| ID | Task | Type |
|---|---|---|
| P1.1 | Smoke 7 plugin fields | browser |
| P1.2 | JS `:has()` fallback | ✅ done (in code + `.min.js` regenerated) |
| — | Import wizard browser test | browser |
| — | Admin a11y verification (B2-B7) | browser |
| — | Bump version + tag | release |

PHPUnit: **224/224 pass**. PHP lint: no syntax errors. PHP 8.4+ deprecations logged separately (not blocking).

---

## Setup

```bash
# 1. From repo root, deploy to Joomla test site
bash tools/deploy-to.sh /Users/pisan/Sites/joomla54

# 2. Enable JDEBUG so source .js files load (not .min.js)
#    Joomla admin → System → Global Configuration → System → Debug System = Yes

# 3. Open Joomla admin in browser
open http://localhost/joomla54/administrator
```

Reset state between sections by reloading admin.

---

## Section A — Smoke 7 plugin fields (P1.1)

Test matrix: render frontend + edit admin + (upload + save) where applicable.

For each field, create an item with the field, save, view frontend, edit again.

| # | Field type | Admin edit | Save | Frontend render | Upload (if any) | Notes |
|---|---|---|---|---|---|---|
| 1 | `image` | ☐ | ☐ | ☐ | ☐ | thumbnail, full, alt, caption |
| 2 | `file` | ☐ | ☐ | ☐ | ☐ | download link, size, MIME |
| 3 | `mediafile` | ☐ | ☐ | ☐ | ☐ | upload + URL modes |
| 4 | `relation` | ☐ | ☐ | ☐ | n/a | related item picker (select2) |
| 5 | `sharedmedia` | ☐ | ☐ | ☐ | ☐ | media manager picker |
| 6 | `weblink` | ☐ | ☐ | ☐ | n/a | URL + label rendering |
| 7 | `addressint` | ☐ | ☐ | ☐ | n/a | structured address (street/city/zip) |

A11y checks per field:
- Label visible + associated to input (`for=id`)
- Tab key reaches the field
- Focus ring visible on focus
- Required field shows error region on empty submit

Capture bugs in **Bug log** section below.

---

## Section B — Import wizard (CSV)

Per `docs/handoff-import-wizard.md` — re-verify after a11y batches.

Test data: create `test.csv` with 5 rows, columns including special chars (`[`, `]`, `"`).

```csv
title,description,"tag[1]","note""quoted"""
Item A,Body A,t1,n1
Item B,Body B,t2,n2
```

### B.1 Golden path
| Step | Expected | ✓ |
|---|---|---|
| Admin → Import → CSV | wizard loads, step bar at 1 | ☐ |
| Select `test.csv` | columns parse client-side, no upload yet | ☐ |
| Select content type | AJAX fields load, mapping table appears | ☐ |
| Auto-map fires | matching columns pre-filled | ☐ |
| Click "Upload & Start Import" | progress shown, "IMPORT FINISHED" displayed | ☐ |
| Verify items table | 2 items created with correct titles | ☐ |

### B.2 A11y
| Check | ✓ |
|---|---|
| VoiceOver/NVDA announces step number on transition | ☐ |
| Each form control has visible label | ☐ |
| Submit with missing fields → error focus moves to first invalid | ☐ |
| Import progress announced via live region | ☐ |
| "IMPORT FINISHED" reaches assistive tech (not silent) | ☐ |

### B.3 Security
| Check | ✓ |
|---|---|
| CSV header with `[`, `]` parses correctly | ☐ |
| CSV value with embedded `""` (escaped quote) preserved | ☐ |
| Reload mid-wizard does not bypass token | ☐ |

---

## Section C — Admin a11y verification (B2-B7)

Verify earlier remediation batches survive on real pages.

### C.1 Typography baseline (B2)
| Check | ✓ |
|---|---|
| Items list table cells ≥ 16px | ☐ |
| Filter bar inputs ≥ 16px | ☐ |
| Thai language switch → body ~18px, line-height 1.65 | ☐ |
| Panel text (j4x.css) ≥ 14px | ☐ |
| 200% browser zoom → no overlap, all text readable | ☐ |

### C.2 Focus ring (B2)
| Check | ✓ |
|---|---|
| Tab through items list → ring visible on every control | ☐ |
| Tab through item edit form → ring visible | ☐ |
| Mouse click on button → ring does NOT appear (`:focus-visible`) | ☐ |
| Ring contrast looks 3:1+ against background | ☐ |
| No `outline:none` regressions | ☐ |

### C.3 List semantics (B3.1-B3.3)
| View | Has `<caption>` | `<th scope>` | Search labeled | ✓ |
|---|---|---|---|---|
| Items | ☐ | ☐ | ☐ | ☐ |
| Fields | ☐ | ☐ | ☐ | ☐ |
| Types | ☐ | ☐ | ☐ | ☐ |
| Categories | ☐ | ☐ | ☐ | ☐ |
| Tags | ☐ | ☐ | ☐ | ☐ |
| Templates | ☐ | ☐ | ☐ | ☐ |
| Users | ☐ | ☐ | ☐ | ☐ |
| Groups | ☐ | ☐ | ☐ | ☐ |

### C.4 Stats view (B3.4)
| Check | ✓ |
|---|---|
| Page has single `<h1>` | ☐ |
| Six `<h2>` section headings present | ☐ |
| All data tables have `<th scope="col">` | ☐ |

### C.5 Item edit (B4.1-B4.2)
| Check | ✓ |
|---|---|
| Tab widget: `role=tablist`/`tab`/`tabpanel` correct | ☐ |
| Arrow keys move between tabs | ☐ |
| Enter/Space activates focused tab | ☐ |
| 15 core field rows: `role=group` + `aria-labelledby` | ☐ |
| Required field shows `JREQUIRED` indicator | ☐ |
| Error message has stable `id` linked via `aria-describedby` | ☐ |

### C.6 Dashboard + modals (B5)
| Check | ✓ |
|---|---|
| cpanel has `<h1>` + `<h2>` sections | ☐ |
| Filemanager: `<h1>` + search labeled | ☐ |
| copyUrlModal: `aria-modal=true`, i18n strings present | ☐ |
| Batch modal: `<h1>` present, focus trapped inside | ☐ |
| Escape closes modal, focus returns to trigger | ☐ |

### C.7 Icon buttons (B6)
| Check | ✓ |
|---|---|
| Toolbar icon buttons have `aria-label` or visible text | ☐ |
| Decorative icons have `aria-hidden=true` | ☐ |
| Tooltips reachable by keyboard hover (focus) | ☐ |

### C.8 Live regions (B7)
| Action | Announced? | ✓ |
|---|---|---|
| Item bind (assign category) | ☐ | ☐ |
| Fix-cat operation | ☐ | ☐ |
| Check-all toggle | ☐ | ☐ |
| Per-field error on save | ☐ | ☐ |

---

## Bug log

Format per bug:

```
### BUG-N: <one-line title>
- Section: A/B/C.x
- Severity: critical | high | medium | low
- Steps: 1. ... 2. ... 3. ...
- Expected: ...
- Actual: ...
- Browser: <Chrome/Firefox/Safari + version>
- Screenshot: <path or skip>
```

If any **critical** or **high** bug found → do NOT bump version, fix first.

---

## Promote to beta.1 (after all sections pass)

```bash
# 1. Update version in flexicontent.xml
#    <version>6.1.0-beta.1</version>
#    <creationDate>17 May 2026</creationDate>

# 2. Update CHANGELOG (top entry)

# 3. Commit
git add flexicontent.xml CHANGELOG.md docs/handoff-beta1.md
git commit -m "chore(release): bump version to 6.1.0-beta.1"

# 4. Tag + push
git tag -a v6.1.0-beta.1 -m "6.1.0-beta.1 — close P1 backlog"
git push -u origin release/6.1.0-beta
git push origin v6.1.0-beta.1

# 5. (Optional) Create GitHub release
gh release create v6.1.0-beta.1 --title "6.1.0-beta.1" --notes-file CHANGELOG.md --prerelease
```

Out-of-scope for beta.1 (track in next milestone):
- P2.1 responsive cols
- P2.2 dark mode
- PT4-PT11 Pro Templates backlog
- PHP 8.4+ deprecation cleanup (`[Backlog]` task #8)

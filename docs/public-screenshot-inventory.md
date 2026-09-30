# Lodgix public screenshot inventory

Internal Phase 06–07 media audit. This document is not rendered or linked publicly.

All public derivatives are generated from local development captures only. The source PNGs are preserved in `public/assets/images/`; the landing page uses the sanitized WebP derivatives under `public/assets/images/landing/`.

## Privacy and safety rules

- No production or hosted application was opened for capture.
- No guest names, staff names, bank details, payment references, API credentials, environment values, or reservation identifiers are present in the derivatives used by the landing page.
- Internal sidebar/topbar and administrator identity chrome were cropped out of the product derivatives.
- The remaining figures and room labels are sample-looking development data and must remain disposable/demo data before any public deployment.
- Original PNG captures were not edited, moved, or deleted.

## Source audit

| Source capture | Original dimensions | Original size | Result | Landing use |
| --- | ---: | ---: | --- | --- |
| `Screenshot 2026-09-30 142925.png` | 1905×997 | 130,462 B | Cropped and optimized to `landing/lodgix-dashboard-light.webp` (1654×921, 38,766 B) | USED — hero; desktop responsive preview |
| `Screenshot 2026-09-30 142938.png` | 1909×997 | 104,654 B | Reviewed; duplicate light dashboard with internal shell retained | NOT USED |
| `Screenshot 2026-09-30 142952.png` | 1905×996 | 104,594 B | Reviewed; duplicate dark dashboard with internal shell retained | NOT USED — not a finance screen |
| `Screenshot 2026-09-30 143005.png` | 1907×1001 | 131,863 B | Reviewed; duplicate dark dashboard with internal shell retained | NOT USED |
| `Screenshot 2026-09-30 143040.png` | 485×990 | 50,420 B | Cropped and optimized to `landing/lodgix-dashboard-mobile.webp` (485×887, 19,230 B) | USED — mobile preview |
| `Screenshot 2026-09-30 143812.png` | 1916×999 | 83,865 B | Cropped and optimized to `landing/lodgix-room-planning.webp` (1846×921, 44,580 B) | USED — Room Operations module |
| `Screenshot 2026-09-30 143834.png` | 1914×1000 | 71,616 B | Reviewed; empty Clients view, not a reservations capture | NOT USED |
| `Screenshot 2026-09-30 143849.png` | 1915×870 | 85,851 B | Reviewed; room list with operational sample records | NOT USED — room planning is clearer |

## Public visual assets

| Asset | Dimensions | Size | Status | Use |
| --- | ---: | ---: | --- | --- |
| `landing/lodgix-dashboard-light.webp` | 1654×921 | 38,766 B | USED | Hero and desktop preview |
| `landing/lodgix-room-planning.webp` | 1846×921 | 44,580 B | USED | Room Operations |
| `landing/lodgix-dashboard-mobile.webp` | 485×887 | 19,230 B | USED | Mobile preview |
| `landing/lodgix-wordmark.webp` | 1200×323 | 51,820 B | USED | Optimized public navbar wordmark |
| `landing/lodgix-social-preview.webp` | 1200×630 | 18,534 B | USED | Safe Open Graph preview; brand artwork only |

## Landing slots

| Slot | Status | Source / reason |
| --- | --- | --- |
| Dashboard overview / hero | USED | Sanitized dashboard derivative; eager/high priority |
| Reservations list or detail | MISSING / REQUIRES CAPTURE | No suitable reservation list/detail capture was supplied |
| Room planning calendar | USED | Sanitized room-planning derivative; lazy-loaded in module section |
| Housekeeping / staff | MISSING / REQUIRES CAPTURE | No suitable capture supplied |
| POS terminal | MISSING / REQUIRES CAPTURE | No suitable capture supplied; no dashboard image is misrepresented as POS |
| Finance overview | MISSING / REQUIRES CAPTURE | No suitable finance capture supplied |
| Reports / finance report | MISSING / REQUIRES CAPTURE | No suitable report capture supplied |
| Roles and permissions | MISSING / REQUIRES CAPTURE | No suitable security/settings capture supplied |
| Responsive desktop | USED | Sanitized dashboard derivative |
| Responsive tablet | PLACEHOLDER / REQUIRES CAPTURE | No tablet capture supplied |
| Responsive mobile | USED | Sanitized mobile dashboard derivative |

## Image handling

The WebP derivatives use explicit intrinsic dimensions in the screenshot component, `decoding="async"`, and eager/high-priority loading only for the above-the-fold hero. Below-the-fold module and device images are lazy-loaded. The component accepts `srcset` and `sizes` for future responsive variants; no unverified variants were invented for this phase.

## Capture procedure

The captures were reviewed manually with local image inspection. No production capture, credential use, database reset, or business-logic change was performed for Phase 06–07.

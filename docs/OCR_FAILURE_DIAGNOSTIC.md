# GPS Camera OCR Failure Diagnostic

## Scope and stop condition

This is a diagnostic-only record. No application source, parser rule, OCR preprocessing, PHP behavior, CSS, test, dependency, database object, or upload was changed.

The real failing image could not be confidently located. All files directly present under `uploads/reports/pending`, `uploads/reports/harvest`, and `uploads/reports/damage` were inventoried read-only. The directories contained 18 Sharp-decodable files dated 4–10 September 2026 and no Harvest file. The identifiable GPS-stamped candidates showed `09/09/2026` with different times and coordinates; none showed the supplied `29/09/2026 07:37 PM GMT+8` stamp. No candidate was substituted or treated as the failing image.

Because the required real image is unavailable, the requested real-image OCR and PHP-boundary execution were deliberately stopped rather than run against unrelated user data.

## Real image

- Found: **No**
- Path: unavailable
- Filename/extension: unavailable
- MIME type: unavailable
- Width/height: unavailable
- Orientation: unavailable
- File size: unavailable
- Sharp decode result: unavailable for the missing image; all 18 files that were present decoded successfully during the read-only inventory

## Current OCR configuration

The following is the exact current configuration derived from `shared/gps-ocr/extract.cjs` without modifying or executing it against a substitute image.

Common setup:

- accepted input remains bounded by the extractor to 6 MiB
- Sharp input pixel limit: 50,000,000
- Sharp applies automatic orientation with `rotate()`, then creates an in-memory PNG buffer
- one Tesseract worker is created with language `eng`
- the worker is reused for at most five attempts
- successful parsing stops subsequent attempts immediately
- parser confidence gates, when TSV lines are used: mean confidence at least 65 and minimum word confidence at least 35 for every used stamp line
- current CLI diagnostics expose raw text, a 0–5 GPS-field score, and parser result; they do not expose the exact TSV confidence values

| Attempt | Source region | Crop formula | Resize | Sharp operations after crop | Tesseract PSM |
|---|---|---|---|---|---|
| 1 | Bottom 25%, full width | `top=floor(height*0.75)`, `height=height-top`, `left=0`, `width=width` | 3x source width, capped at 4800 px | grayscale, normalize, sharpen, threshold 150, negate | `SINGLE_BLOCK` (6) |
| 2 | Bottom 35%, full width | `top=floor(height*0.65)`, `height=height-top`, `left=0`, `width=width` | 3x source width, capped at 4800 px | grayscale, normalize, sharpen | `SINGLE_BLOCK` (6) |
| 3 | Bottom 50%, full width | `top=floor(height*0.50)`, `height=height-top`, `left=0`, `width=width` | width clamped to 1800–3200 px | no color conversion | `SPARSE_TEXT` (11) |
| 4 | Full image | `top=0`, full dimensions | width clamped to 1800–3200 px | grayscale, normalize, sharpen | `SPARSE_TEXT` (11) |
| 5 | Full image | `top=0`, full dimensions | width clamped to 1800–3200 px | no color conversion | `AUTO` (3) |

Limits:

- Node/extractor overall timeout: 55 seconds
- PHP subprocess timeout: 58 seconds
- PHP maximum captured stdout: 16,000 bytes
- per-attempt timeout: none; attempts share the 55-second overall extractor deadline
- maximum OCR attempts: 5

## OCR attempt 1

- Configuration: bottom 25%, 3x capped scaling, grayscale/normalize/sharpen/threshold/negate, PSM 6, English
- Actual crop dimensions: unavailable because the real image was not found
- Duration: not run
- Confidence: not available

Exact raw OCR:

```text
[NOT CAPTURED — real failing image was not found; no substitute image was used.]
```

Parser output:

```text
[NOT RUN]
```

## OCR attempt 2

- Configuration: bottom 35%, 3x capped scaling, grayscale/normalize/sharpen, PSM 6, English
- Actual crop dimensions: unavailable because the real image was not found
- Duration: not run
- Confidence: not available

Exact raw OCR:

```text
[NOT CAPTURED — real failing image was not found; no substitute image was used.]
```

Parser output:

```text
[NOT RUN]
```

## OCR attempt 3

- Configuration: bottom 50%, width clamped to 1800–3200 px, color, PSM 11, English
- Actual crop dimensions: unavailable because the real image was not found
- Duration: not run
- Confidence: not available

Exact raw OCR:

```text
[NOT CAPTURED — real failing image was not found; no substitute image was used.]
```

Parser output:

```text
[NOT RUN]
```

## OCR attempt 4

- Configuration: full image, width clamped to 1800–3200 px, grayscale/normalize/sharpen, PSM 11, English
- Duration: not run
- Confidence: not available

Exact raw OCR:

```text
[NOT CAPTURED — real failing image was not found; no substitute image was used.]
```

Parser output:

```text
[NOT RUN]
```

## OCR attempt 5

- Configuration: full image, width clamped to 1800–3200 px, color, PSM 3, English
- Duration: not run
- Confidence: not available

Exact raw OCR:

```text
[NOT CAPTURED — real failing image was not found; no substitute image was used.]
```

Parser output:

```text
[NOT RUN]
```

## Perfect-text parser test

Input:

```text
29/09/2026 07:37 PM GMT+8
Garcia - Rosario - San Juan - Candelaria Road
Rosario Calabarzon
Lat: 13.841764
Long: 121.27818
```

Exact output:

```json
{"ok":true,"photo":{"date":"2026-09-29","time":"19:37","time_display":"07:37 PM","location":"Garcia - Rosario - San Juan - Candelaria Road, Rosario Calabarzon","latitude":"13.841764","longitude":"121.27818","timezone":"GMT+8","version":"tesseract7-mdY-v1"}}
```

Result: **accepted**.

## Field diagnosis

These field results are from the perfect-text parser test, not from the missing real image.

Date:

- raw value: `29/09/2026`
- normalized value: `2026-09-29`
- accepted/rejected: accepted
- reason: first component is greater than 12, so the current parser deterministically uses DD/MM/YYYY

Time:

- raw value: `07:37 PM`
- normalized value: `19:37`; display value `07:37 PM`
- accepted/rejected: accepted
- reason: valid 12-hour time without optional seconds

Timezone:

- raw value: `GMT+8`
- accepted/rejected: accepted as `GMT+8`

Latitude:

- raw value: `13.841764`
- normalized value: `13.841764`
- accepted/rejected: accepted
- reason: labeled decimal coordinate within -90..90

Longitude:

- raw value: `121.27818`
- normalized value: `121.27818`
- accepted/rejected: accepted
- reason: labeled decimal coordinate within -180..180

Address:

- raw lines selected: `Garcia - Rosario - San Juan - Candelaria Road` and `Rosario Calabarzon`
- normalized value: `Garcia - Rosario - San Juan - Candelaria Road, Rosario Calabarzon`
- accepted/rejected: accepted; both lines retained

## Parser compatibility checks

| Input/layout | Current normalized result | Result |
|---|---|---|
| `29/09/2026` with GPS Camera markers | `2026-09-29` | accepted as DD/MM/YYYY |
| `09/29/2026` with GPS Camera markers | `2026-09-29` | accepted as MM/DD/YYYY because the second component is greater than 12 |
| `05/09/2026` with `GMT+8`, labeled latitude, and labeled longitude | `2026-09-05` | accepted as DD/MM/YYYY by the recognized GPS Camera ambiguity rule |
| `Lat:` + `Long:` | coordinates accepted | supported |
| `Latitude:` + `Longitude:` | coordinates accepted | supported |
| `Lat:` + `Lon:` | coordinates accepted | supported |

## PHP/Node boundary

The same-image PHP/Node boundary diagnostic was not run because the real image was not found.

- Node process exit code: not measured
- stdout valid JSON: not measured
- stderr: not measured
- decoded PHP result: not measured
- internal failure category: not measured
- HTTP status: not measured
- public/frontend error: not independently reproduced; the reported UI message is `Hindi mabasa ang GPS information mula sa larawang ito...`

Static boundary behavior observed in `shared/gps_photo.php`:

- PHP invokes Node without a shell and discards subprocess stderr from the browser response.
- Timeout maps to `reason=timeout`.
- Output over 16,000 bytes maps to `reason=unreadable`.
- Malformed output, a failed parser result, or a PHP-invalid photo object maps to a public/internal `unreadable` result except the preserved `ocr_unavailable` and `timeout` categories.
- Successful JSON must also pass PHP date, time, coordinate-range, location-length, and OCR-version validation.

## Timing

| Item | Duration |
|---|---|
| Attempt 1 | not run — real image unavailable |
| Attempt 2 | not run — real image unavailable |
| Attempt 3 | not run — real image unavailable |
| Attempt 4 | not run — real image unavailable |
| Attempt 5 | not run — real image unavailable |
| Total | not measured |

## Final diagnosis

**OTHER**

- The exact real failing image is not present in the three managed report-image directories, so its OCR failure cannot be reproduced.
- No unrelated upload was used as a substitute.
- Exact per-attempt OCR text, confidence, timing, parser rejection, Node exit code, and PHP response therefore remain unknown for the real failure.
- The manually supplied perfect text passes the current parser with every required field.
- DD/MM/YYYY, MM/DD/YYYY, the contextual ambiguous DD/MM rule, all requested coordinate labels, and the two-line address work in direct parser checks.
- The available evidence rules out a parser failure for the supplied perfect text but is insufficient to classify the missing image's runtime failure as OCR or PHP/Node integration.


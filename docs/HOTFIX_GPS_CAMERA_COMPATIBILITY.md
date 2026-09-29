# GPS Camera Compatibility Hotfix

## Observed format and root cause

The client's GPS Camera app produces stamps such as:

```text
29/09/2026 07:37 PM GMT+8
Garcia - Rosario - San Juan - Candelaria Road
Rosario Calabarzon
Lat: 13.841764
Long: 121.27818
```

The first compatibility defect occurred after OCR because `stamp-parser.cjs` always interpreted the first date value as a month, making `29/09/2026` invalid. A later real-photo case exposed a second issue: the GPS overlay occupied only a small part of a noisy portrait photograph, so the broad OCR passes could not recognize the stamp reliably. The date parser and the staged-image lifecycle did not require further changes for that second issue.

## Date compatibility and ambiguity rule

The parser now supports both MM/DD/YYYY and DD/MM/YYYY:

- first value greater than 12: DD/MM/YYYY
- second value greater than 12: MM/DD/YYYY
- both values 12 or less: DD/MM/YYYY only when the stamp contains a timezone plus labeled latitude and longitude fields, which identifies the client's GPS Camera layout
- other ambiguous layouts retain the historical MM/DD/YYYY interpretation

All accepted dates continue to normalize to `YYYY-MM-DD`. The existing OCR version string is retained for database and historical compatibility.

## Address and coordinate compatibility

The supplied two-line address is preserved through the existing normalized location contract as `Garcia - Rosario - San Juan - Candelaria Road, Rosario Calabarzon`. Address collection stops at coordinate fields.

Existing case-insensitive support was verified for `Lat`, `Latitude`, `Long`, `Longitude`, and `Lon`, including safe whitespace around labels and `GMT + 8`. Latitude/longitude range checks and conflicting-coordinate rejection remain unchanged.

## OCR verification

A synthetic image reproducing the supplied layout was added to the real OCR regression. Raw Tesseract output contained the correct date, time, timezone, both address lines, latitude, and longitude. The extracted result is:

```text
date: 2026-09-29
time: 19:37
time_display: 07:37 PM
timezone: GMT+8
latitude: 13.841764
longitude: 121.27818
```

That initial fixture did not require OCR preprocessing changes. The smaller fixture described below subsequently required stamp-focused preprocessing. Confidence thresholds, worker lifecycle, and parser-wide character substitution remain unchanged.

## Small bottom-stamp OCR hotfix

### Failure isolation

A sanitized 2000 x 4000 portrait fixture places the five-line overlay in the bottom 153 pixels (less than 4% of the image height) over a noisy photographic background. Before the focused attempts were added, the existing strategies produced incomplete or corrupted text. The best broad attempt read the date as `20002026`, the timezone as `GMIT+8`, and damaged both coordinates; the other full-image attempts recovered still fewer required fields. The parser therefore correctly rejected the OCR result with `date_time`. This established OCR/preprocessing—not the already-correct DD/MM parser—as the remaining failure.

### Focused recognition strategy

`shared/gps-ocr/extract.cjs` now runs a bounded five-attempt sequence using one Tesseract worker:

1. bottom 25%, full width, up to 3x scaling (capped at 4800 px), grayscale/normalize/sharpen/threshold/invert, `SINGLE_BLOCK`
2. bottom 35%, full width, up to 3x scaling (capped at 4800 px), grayscale/normalize/sharpen, `SINGLE_BLOCK`
3. the original bottom-half sparse-text attempt
4. the original normalized full-image sparse-text attempt
5. the original full-image automatic-layout attempt

Each crop is proportional to the actual image dimensions and keeps the full width so wrapped addresses and both coordinates remain available. Recognition stops immediately after the normal parser accepts all required fields. Failed attempts receive an internal 0–5 completeness score using valid date, time, in-range latitude, in-range longitude, and address indicators; this score never fabricates or relaxes a field.

An explicit CLI-only `--diagnose` mode writes attempt name, raw OCR, completeness score, and parser result to stderr. Production PHP does not enable this mode, and raw OCR is not returned to browsers or permanently logged.

All Sharp crops, scaling, and contrast operations are in-memory OCR inputs. The uploaded evidence file is read-only and remains byte-for-byte unchanged. The 55-second bound, single-worker lifecycle, worker termination, stdout JSON contract, path restriction, and 6 MB limit remain intact.

### Small-stamp regression

The OCR test now generates a sanitized large portrait/noisy-background fixture containing the exact structural DD/MM, GMT+8, two-line address, `Lat`, and `Long` layout at a much smaller relative text size. The old broad attempts fail on this fixture; the second focused pass (`bottom-35-normalized-block`) returns all five required fields with exact normalized values, after which the three broad fallbacks are skipped. The same JPEG then passes through `report_image_stage.php`, proving the Farmer/Admin upload boundary accepts it rather than returning the invalid-GPS message.

The actual client photograph was not present as an identifiable local test asset, so it was not committed or opened by guesswork. The sanitized fixture exercises the reported placement, scale, contrast, fields, and layout without retaining client data.

## Preview sizing

The shared Farmer/Admin evidence preview and report-detail images now use:

- `max-width: 100%`
- `width: auto`
- `height: auto`
- `max-height: min(420px, 60vh)`
- `object-fit: contain`
- centered inline margins

This is aspect-ratio neutral for portrait, landscape, and square images. It limits normal display size without changing uploaded bytes. The existing Enlarge Image / Palakihin ang Larawan link still opens the original image in a separate view.

## Tests and regression results

- parser regression for the exact supplied text: passed
- unambiguous MM/DD and DD/MM dates: passed
- ambiguous GPS Camera DD/MM and legacy MM/DD rules: passed
- multiline address and all coordinate-label variants: passed
- coordinate range/conflict and confidence checks: passed
- real OCR legacy Formats A/B and new DD/MM Format C: passed
- raw OCR diagnostic: all required fields recognized correctly
- Farmer/Admin GPS workflow: 14 groups passed, including DD/MM extraction through the PHP staging endpoint
- preview bounds, 420 px cap, aspect ratio, `object-fit`, and enlargement control at 1366/768/390 px: passed
- report/image lifecycle: passed
- Phase 9 static and responsive browser checks: passed

No PHP application file, database schema, reporting service, image-storage behavior, or original-evidence processing was changed.

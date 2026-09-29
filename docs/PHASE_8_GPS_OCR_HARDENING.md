# Phase 8A — GPS/OCR Hardening and Lifecycle Reliability

## Scope

Phase 8A was limited to the existing report-image staging/storage and PHP-to-Node OCR boundary. Report creation/update services, Admin report presentation, authentication, database schema, GPS field semantics, parser rules, OCR attempt order, supported formats, and public response contracts were not changed.

## Interrupted starting state and resume review

The resumed working tree already contained the uncommitted Phase 5–7 work and generated GPS test artifacts; those unrelated changes were preserved. There were no staged changes. Targeted diff inspection found the Phase 8A implementation already present in the storage, PHP OCR process, Node extractor, lifecycle fixture, and tests listed below. No Phase 8 TODO/FIXME marker, incomplete block, syntax error, or unsafe broad cleanup routine was found.

The remaining resume work was validation rather than reimplementation: verify the pending-only deletion boundary, complete the real OCR/browser/database regression, run syntax/diff checks, record the OCR observation, and finalize this document. The final verification passed without requiring changes outside the Phase 8A boundary.

## Files changed

Application boundary:

- `shared/report_image_storage.php`
- `shared/gps_photo.php`
- `shared/gps-ocr/extract.cjs`

Tests and fixtures:

- `tests/reports/report-lifecycle-characterization.cjs`
- `tests/helpers/phase1-router.php`
- `tests/gps-stamp-ocr.cjs`
- `tests/report-image-storage-hardening.php`

## Expired pending-file cleanup

`scaims_get_pending_report_image()` now deletes the expired token's owned file before removing its session entry. Deletion uses a pending-only helper, tolerates an already-missing file, and does not expose filesystem failures.

The lifecycle test now proves all of the following in one disposable session:

1. the expired token file initially exists;
2. the token is rejected by report submission;
3. its session entry is removed;
4. its physical pending file is removed; and
5. a separate pending file remains untouched.

General age-based directory garbage collection was deliberately deferred. Reliably identifying active tokens across every PHP session is not available at this boundary, so scanning and deleting files by age would risk removing an active upload.

## Path and deletion protections

Managed image paths are now limited to flat files beneath exactly:

- `uploads/reports/pending`
- `uploads/reports/harvest`
- `uploads/reports/damage`

Normalization rejects null bytes, traversal, unknown folders, and nested paths. Absolute-path resolution verifies the canonical path remains under the report upload root. `scaims_delete_pending_report_image()` refuses Harvest or Damage paths.

The focused PHP hardening test verifies managed-path acceptance, unknown/nested/traversal rejection, pending deletion, and preservation of finalized/outside files. It creates and removes only uniquely named test files.

## PHP OCR process hardening

The PHP process boundary now:

- drains stdout and stderr without returning internal diagnostics to the browser;
- tracks the child exit code where available;
- treats nonzero successful-looking output as failure;
- bounds stdout size;
- explicitly terminates on timeout/output overflow;
- waits briefly for graceful termination and then requests forced termination;
- closes every process pipe and calls `proc_close()`;
- safely handles malformed JSON and invalid OCR payloads; and
- records only a broad failure category, elapsed milliseconds, and exit code through `error_log()`.

No command, session ID, OCR text, address, image content, or credential is logged.

## Node/Tesseract cleanup

The Node extractor still performs the same three preprocessing/PSM attempts in the same order. Its timeout now rejects through the normal async flow, allowing the `finally` block to terminate the Tesseract worker instead of calling `process.exit()` from a timer. Worker termination is attempted on success, parser failure, OCR error, and timeout. Sharp continues to use buffers only and creates no preprocessing files.

Fatal CLI failures still emit one machine-readable JSON object on stdout and a nonzero exit status. A regression assertion verifies that stderr remains empty and stdout remains parseable.

## Preserved behavior

- JPG/JPEG, PNG, and WEBP uploads and the 6 MB limit
- real MIME and image-header validation
- original uploaded bytes
- both characterized GPS Camera stamp layouts
- MM/DD/YYYY, time/seconds/timezone/address/coordinate parsing
- coordinate range/conflict and confidence rules
- parser version `tesseract7-mdY-v1`
- required GPS metadata, token session ownership, and replay rejection
- existing HTTP status and JSON response behavior

`stamp-parser.cjs` was not modified.

## Testing and performance observation

The required pre-change GPS parser, real OCR, Farmer/Admin GPS workflow, and report lifecycle suites all passed. Targeted path and lifecycle tests passed after the change. Final verification covers those suites plus authentication, both Phase 7 Admin report-view suites, syntax checks, and `git diff --check`.

No timing threshold was added because OCR duration varies by CPU. The two real stamped fixtures continue to complete within the existing bounded test/request window; worker startup remains a visible per-extraction cost.

## Deferred work and known limitations

- Tesseract worker pooling, queues, persistent services, and concurrency/load testing remain deferred performance work.
- Cross-session abandoned-file garbage collection remains deferred because active-token detection is not reliable enough for safe directory sweeping.
- Crash recovery between a finalized filesystem move and a later database failure remains governed by the existing Phase 6 service cleanup contract.
- Previously documented non-GPS issues remain unchanged.

## Phase 9 readiness

Phase 9 is safe to begin after the final targeted regression remains green. Phase 9 should treat the GPS/image/OCR boundary as stable and avoid changing upload or parsing behavior during CSS/design-system work.

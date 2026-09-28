# Phase 4: Low-Risk Duplicate Code Consolidation

## Consolidated code

- The Farmer `home`, `parcel`, and `report` profile-image endpoints now delegate to `FARMERS UI/farmersDashboard/shared/profile-image.php`. All three endpoint URLs remain unchanged, including their session, JPEG response, database lookup, and per-route fallback image behavior.
- The byte-identical Admin and Farmer dashboard-shell scripts are now canonicalized as `shared/dashboard-shell.js`. Active Admin and Farmer pages load that file directly.

## Compatibility wrappers

- The three Farmer `displayImage.php` files remain as small route-compatible wrappers.
- The former Admin and Farmer dashboard-shell URLs remain as small JavaScript loaders for the canonical script.

## Suggestions status

Suggestions consolidation is deferred. The Admin implementation is the active canonical route, but the root implementation has different PHP include/auth paths, page composition, and styling. No references outside the two implementations prove that the root URLs are unused, so delegating or redirecting them could change externally used route behavior.

## Verification

- PHP syntax checks were run for every changed PHP file.
- The authentication/authorization, posts/suggestions, and report/image-lifecycle characterization suites were run.
- The canonical dashboard script was checked with Node syntax validation and compared against the original implementation.

## Remaining duplication and readiness

The deliberately retained Suggestions implementation and unrelated higher-risk duplication remain unchanged. Phase 5 is safe to begin if the recorded targeted suites remain green; broader reporting, OCR, schema, authentication, and UI consolidation were intentionally excluded.

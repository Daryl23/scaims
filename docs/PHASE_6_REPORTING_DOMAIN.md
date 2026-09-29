# Phase 6: Shared Reporting Domain Service

Date: 2026-09-29  
Branch: `main`

## Before and after

Previously, Farmer creation, Admin-on-behalf creation, and Farmer update each resolved owners, mapped request fields, validated data, finalized staged evidence, prepared report SQL, coordinated GPS metadata transactions, and cleaned files inside route scripts. The three public routes remain unchanged, but are now adapters over `shared/reporting/`:

```text
existing Farmer/Admin route
        -> authentication and response policy
        -> shared reporting service
        -> type-aware repository/validation
        -> existing image + GPS helpers
        -> existing database tables
```

## Files introduced

- `shared/reporting/report-types.php`: explicit Harvest/Damage table and image-destination configuration.
- `shared/reporting/report-validation.php`: common request cleaning, date/number/percentage, and coordinate validation.
- `shared/reporting/report-repository.php`: Farmer owner resolution, active Admin-selected Farmer resolution, ownership-qualified report lookup, and normalized updated-report retrieval.
- `shared/reporting/report-service.php`: create/update orchestration, explicit type mappings, image finalization/cleanup, GPS transaction calls, and optional Farmer-default persistence.

## Owner and actor

- Farmer self-submission resolves the owner from the authenticated `UName`; `FarmerID` is the owner and `created_by` remains that Farmer username.
- Admin-on-behalf submission validates the selected active Farmer; `FarmerID` is that owner and `created_by` remains the authenticated Admin name/ID behavior.
- Farmer updates resolve the authenticated Farmer once and all fetch/update SQL remains qualified by both report ID and `FarmerID`.

The same resolution functions are also used by the Farmer report page/default-save endpoint and the Admin prefill endpoint.

## Harvest and Damage boundaries

The workflow is shared, but mappings remain explicit. Harvest writes `crop_reports` fields including planting/harvest dates, estimate, status, price, and tree count. Damage writes `damage_reports` fields including OCR capture date as occurrence date, cause, percentage, loss, price, and tree count.

Admin creation retains its stricter validation policy. Farmer creation retains its historically looser acceptance rules. Farmer updates retain the existing strict rules. Existing POST field names are unchanged.

## Image, GPS, and transaction behavior

The service consumes the existing `report_image_storage.php` and `gps_photo.php` APIs without changing them. Pending-token ownership, expiry, direct-upload rejection for GPS-backed creation, original bytes, Harvest/Damage destinations, modern evidence replacement/removal rules, legacy removal, and old-image cleanup retain their characterized order.

Image finalization still occurs before the existing `scaims_execute_photo_report()` transaction. That helper still executes the report statement, persists GPS metadata, commits/rolls back, and reports failure. A newly finalized file is deleted on prepare/execute failure exactly as before. No new cleanup policy was introduced.

## Route and response compatibility

- Farmer `report_submit.php`: same authentication, POST fields, session message, and redirect to `report.php`.
- Admin `report_submit.php`: same authentication, selected-Farmer semantics, session message, and redirect to `reportAnalytics.php`.
- Farmer `report_update.php`: same JSON envelope, HTTP statuses/messages, same-ID update, and normalized report response.
- Farmer reusable defaults are saved after Farmer create/update; Admin-on-behalf creation does not save them.

No schema, route, frontend, authentication, OCR parser, or report-list/analytics UI changed.

## Verification

Before refactoring, authentication, GPS Farmer/Admin workflow, report lifecycle, parcel, profile, posts/suggestions, public map, Phase 2 configuration/migrations, and Phase 5 frontend characterization all passed.

During extraction, report lifecycle passed after the service migration and again after centralized owner/default resolution. Authentication and all 13 GPS workflow groups passed after owner/actor and image orchestration moved into the service.

Final regression passed: authentication; 21 GPS parser assertions; both real OCR layouts; all 13 GPS Farmer/Admin workflow groups; registration; report lifecycle; parcels; profile; posts/suggestions; public map; Phase 2 configuration and migration/schema; and Phase 5 frontend characterization. PHP syntax passed for all 18 changed/new PHP files, JavaScript syntax passed for all eight changed/new scripts, and `git diff --check` reported no whitespace errors.

## Deferred and known issues

Admin Harvest/Damage lists, maps, charts, exports, pagination, and their frontend files remain for Phase 7/10. Farmer/Admin report-form JavaScript remains unchanged. Runtime report/GPS schema assurance remains the documented Phase 2 compatibility behavior.

Known missing broad CSRF protection, cross-Farmer parcel disclosure, orphaned post images, expired abandoned pending files, and duplicate Suggestions remain unchanged.

Phase 7 is safe to begin if final regression remains green. Phase 7 should treat the reporting service and existing route contracts as stable boundaries while limiting work to read/list/analytics presentation stacks.

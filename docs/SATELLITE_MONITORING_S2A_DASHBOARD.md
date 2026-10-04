# Satellite Monitoring S2A Admin Dashboard

Date: 2026-10-04  
Status: Dashboard and existing-data integration complete; satellite execution and persistence deferred to S2B.

## Route and access boundary

The Admin page is:

`/ADMIN UI/adminDashboard/monitoring/satelliteMonitoring.php`

It is linked as **Satellite Monitoring** in the existing Admin sidebar, after Reports. Both the page and its parcel endpoint call `scaims_require_admin()`. A Farmer session has no `AdminID` and is redirected to the Admin login just like every other Admin page.

SCAIMS currently has two Admin roles (`admin` and `super_admin`) but no Admin-to-barangay assignment field or parcel visibility filter. Therefore S2A preserves the current behavior: both signed-in Admin roles see the same existing parcel records. It does not introduce or broaden a separate visibility model.

## Existing records and fields reused

The canonical selectable parcel key is `parcel_details.id`.

Relationship path:

```text
parcel_details.farm_parcel_id
  -> farm_parcels.id
  -> farm_parcels.created_by
  -> user.UName
  -> farmer.UserID
  -> farmer.FarmerID

crop_details.parcel_detail_id
  -> parcel_details.id
```

The dashboard uses the following existing fields without duplicating them:

| Dashboard value | Existing source |
|---|---|
| Parcel ID | `parcel_details.id` |
| Parcel sequence | `parcel_details.parcel_number` |
| Farmer ID | `farmer.FarmerID` |
| Farmer name | `farmer.FirstName`, `MiddleName`, `Surname`, `Suffix` |
| Parcel location | `parcel_details.location` |
| Farmer barangay fallback | `farmer.Barangay` |
| Parcel area | `parcel_details.total_area` (existing UI defines the unit as hectares) |
| Ownership | `parcel_details.ownership_type` |
| Farm type / organic | `farm_parcels.farm_type`, `farm_parcels.organic` |
| Recorded date | `farm_parcels.created_at` |
| Crops | `crop_details.crop_commodity`, `crop_type`, `crop_variety`, `num_trees`, `tree_status` |

There is no parcel-level municipality/city column and no parcel status column, so those values remain `—`. No replacement columns were created.

## Map and location behavior

The page uses the same Leaflet 1.9.4 and OpenStreetMap pattern already used by Admin report maps.

The current `farm_parcels` and `parcel_details` schema has no latitude, longitude, or polygon column. Report coordinates cannot safely substitute because `crop_reports` and `damage_reports` reference a Farmer and crop type, not `parcel_details.id`; a Farmer may have multiple parcels with the same crop.

Consequently S2A:

- displays the real stored text location;
- shows no parcel marker;
- draws no polygon;
- explicitly says that no coordinates are available and no boundary was fabricated;
- keeps **Define Farm Boundary** disabled and labeled as an S2B feature.

## Existing report relationships

Harvest and Damage counts/details are omitted. Although both report tables have `FarmerID`, neither has a parcel foreign key. Matching by Farmer, location text, or crop type would be ambiguous and could associate a report with the wrong parcel. No reporting query or workflow was modified.

## Satellite contract represented

The latest-observation panel has empty fields ready for the standalone POC response:

- `provider`
- `observation_date`
- `scene_id`
- `ndvi_mean`
- `ndvi_min`
- `ndvi_max`
- `valid_pixel_count`
- `cloud_percentage`
- `parcel_cloud_percentage`
- `requested_date_window`
- `max_cloud_threshold`
- `execution_seconds`

The UI also reserves Previous NDVI, Change, and Trend for values that S2B can derive only after at least two persisted observations. It does not change the POC JSON contract.

The provider is fixed to `Copernicus Data Space Ecosystem / Sentinel-2 L2A`, matching `docs/SATELLITE_POC.md`.

## Provider configuration status

`shared/satellite_config.php` checks the server process environment for both:

- `COPERNICUS_CLIENT_ID`
- `COPERNICUS_CLIENT_SECRET`

Only `Configured` or `Not configured` is rendered. IDs, secrets, and tokens are never sent to the browser. The page does not contact Copernicus on load.

The **Run Satellite Analysis** button remains disabled in S2A in both states. When credentials are absent, the page states that the service is not configured. When both variables exist, it states that credentials were detected but the secure execution adapter is scheduled for S2B. This avoids exposing credentials or pretending an unimplemented request can run.

## Truthful empty states

S2A includes explicit empty states for:

- no parcel records;
- no selected parcel;
- loading parcel details;
- missing coordinates;
- missing polygon boundary;
- no satellite observation;
- no trend (requires at least two real observations);
- no satellite history;
- no verification queue items;
- provider not configured.

Summary values are calculated honestly: Monitored Parcels is the current `parcel_details` count represented by the selector; Analyzed This Week, Stable / Increasing, and Needs Review are `0` because no satellite persistence exists.

## Responsive behavior

The page reuses shared design tokens, components, Admin sidebar markup, and the repaired shared mobile drawer. Its feature stylesheet provides:

- four summary columns on wide desktop, then two columns;
- parcel/map and observation columns on desktop, then a single-column stack;
- local horizontal scrolling for the history table;
- bounded Leaflet map sizing;
- full-width selector and action layout on tablet/mobile;
- touch-sized controls and no document-level horizontal overflow.

Automated browser coverage checks 1366, 1024, 768, 430, and 390 px, including the Admin drawer on mobile.

## Tests

Focused S2A coverage is in `tests/satellite/satellite-dashboard-s2a.cjs` and `tests/satellite/config-probe.php`.

It verifies:

- anonymous and Farmer access are rejected;
- signed-in Admin access succeeds;
- the selector uses fixture parcel records and the canonical parcel ID;
- farmer, crop, location, and area values load from existing relationships;
- unavailable municipality, status, coordinates, and geometry stay null/empty;
- only a configuration boolean is exposed;
- configured and unconfigured environment states;
- no fake NDVI, scene, history, trend, or anomaly records;
- responsive layout at all five target widths;
- contained table overflow and a functioning mobile Admin sidebar.

Run:

```powershell
$env:PHP_BINARY='C:/xampp/php/php.exe'
node tests/satellite/satellite-dashboard-s2a.cjs
```

Existing authentication, parcel, shared frontend, design-system, and report/map suites remain the relevant regression set because S2A changes only the shared sidebar plus new isolated files.

## Deferred to S2B

S2B must deliberately add:

1. persistent parcel boundary polygons and exact parcel coordinates;
2. a secured server-side adapter for `scripts/satellite/analyze_ndvi.py`;
3. real **Run Satellite Analysis** request handling and loading/error results;
4. a reviewed `satellite_readings` persistence design referencing `parcel_details.id`;
5. previous-versus-current NDVI comparison;
6. history and trend calculation from real observations only;
7. verification rules based on reviewed evidence, not an NDVI health diagnosis.

No S2B table, migration, provider request, background job, automation, or notification was implemented in S2A.

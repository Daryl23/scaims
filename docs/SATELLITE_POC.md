# Satellite-assisted farm monitoring proof of concept

Status: implementation ready; a live NDVI result requires a Copernicus Data Space account and OAuth client credentials.

This is a standalone, one-parcel experiment. It does not use PHP, MariaDB, SCAIMS authentication, existing maps, or production pages.

## Selected official services

The prototype uses two current Copernicus Data Space Ecosystem (CDSE) services:

1. **STAC Catalogue API** (`https://stac.dataspace.copernicus.eu/v1/search`) searches the `sentinel-2-l2a` collection with a GeoJSON `intersects` polygon, inclusive date window, CQL2 `eo:cloud_cover` threshold, and newest-first acquisition sorting.
2. **Sentinel Hub Statistical API** (`https://sh.dataspace.copernicus.eu/statistics/v1`) calculates statistics over the parcel without downloading full raster products.

This replaces obsolete Copernicus Open Access Hub/OpenSearch examples. The STAC catalogue is used for current scene discovery; Sentinel Hub's synchronous Statistical API is appropriate for one polygon and directly returns JSON statistics.

Official references checked on 2026-10-03:

- [STAC Catalogue migration and search documentation](https://documentation.dataspace.copernicus.eu/notebook-samples/geo/migration_of_opensearch_to_stac_guide.html)
- [Sentinel Hub Statistical API](https://documentation.dataspace.copernicus.eu/APIs/SentinelHub/Statistical.html)
- [Sentinel Hub authentication](https://documentation.dataspace.copernicus.eu/APIs/SentinelHub/Overview/Authentication.html)
- [Sentinel-2 L2A bands and metadata](https://documentation.dataspace.copernicus.eu/APIs/SentinelHub/Data/S2L2A.html)
- [CDSE quotas and limitations](https://documentation.dataspace.copernicus.eu/Quotas.html)

## Cost, account, and quota

No paid provider or payment information is required for this prototype. A free **Copernicus General User** account is required for Sentinel Hub processing. Current general-user Sentinel Hub limits are:

- 10,000 API requests per month
- 300 API requests per minute
- 10,000 processing units (PU) per month
- 300 PU per minute

Monthly quotas reset on the first day of each month. A Statistical API request has a documented minimum cost of 0.01 PU; actual use depends on area, bands, resolution, and samples. This small, manual one-parcel workflow is well within the free allowance under normal use. HTTP 429 is returned when rate or quota limits are exceeded. Commercial CREODIAS capacity is optional and is not used here.

## Authentication setup

Sentinel Hub APIs require OAuth2 client-credentials authentication. The STAC search is public, but the statistics request needs an access token.

1. Register or sign in at [Copernicus Data Space Ecosystem](https://dataspace.copernicus.eu/).
2. Open the CDSE Dashboard, then **User Settings > OAuth clients**.
3. Create an OAuth client intended for this local script (not a browser SPA).
4. Copy the displayed client ID and secret. The secret cannot be retrieved again after the creation dialog is closed.
5. In PowerShell, set process-local environment variables and run the script:

```powershell
$env:COPERNICUS_CLIENT_ID = "your-client-id"
$env:COPERNICUS_CLIENT_SECRET = "your-client-secret"
python scripts/satellite/analyze_ndvi.py scripts/satellite/sample_parcel.geojson
```

The script requests a token from the current CDSE realm endpoint:

`https://identity.dataspace.copernicus.eu/auth/realms/CDSE/protocol/openid-connect/token`

Do not put real credentials in source control or a project `.env` file. The script reads only operating-system environment variables and reuses its one token for the run.

## Polygon input

The input may be a GeoJSON `Polygon` or a GeoJSON `Feature` whose geometry is a `Polygon`. Coordinates must use GeoJSON order `[longitude, latitude]`. Each ring must be closed, have at least three distinct vertices, use finite coordinates, and remain within longitude `[-180, 180]` and latitude `[-90, 90]`.

`scripts/satellite/sample_parcel.geojson` is a deliberately synthetic rectangle near `121.15 E, 13.85 N`; it is not tied to a farmer or SCAIMS record. To test an actual parcel, make a local copy and replace only the polygon coordinates:

```powershell
Copy-Item scripts/satellite/sample_parcel.geojson $env:TEMP\parcel-test.geojson
# Edit the temporary copy, retaining [longitude, latitude] order and closing the ring.
python scripts/satellite/analyze_ndvi.py $env:TEMP\parcel-test.geojson
```

Do not commit a real farmer parcel file.

## Imagery selection, clouds, and NDVI

The default search window is the latest 30 UTC calendar days, ending on the current UTC date. The output records the actual Sentinel-2 acquisition date; it never labels an observation as “today” unless that acquisition date is actually selected.

The default maximum scene/tile cloud metadata threshold is 30%. Candidates are sorted newest first. The script tries up to five distinct recent acquisition dates. For each candidate, Sentinel-2 L2A Scene Classification Layer (SCL) values 3 (cloud shadow), 8 (medium-probability cloud), 9 (high-probability cloud), 10 (cirrus), and 11 (snow/ice) are excluded from NDVI. If those masks leave no valid parcel pixels, the script tries the next candidate.

The returned `cloud_percentage` is the selected product/tile `eo:cloud_cover` metadata. `parcel_cloud_percentage` is computed over the parcel from the SCL mask, so it is more spatially relevant but still inherits SCL classification limitations.

NDVI uses Sentinel-2 L2A 10 m reflectance bands:

- B04: Red (approximately 665 nm)
- B08: Near Infrared (approximately 833 nm)

Formula:

`NDVI = (B08 - B04) / (B08 + B04)`

Undefined zero-denominator pixels, no-data pixels, and masked cloud/shadow/snow pixels are excluded rather than changed to NDVI zero. The Statistical API returns mean, minimum, maximum, sample count, and no-data count; `valid_pixel_count` is `sampleCount - noDataCount`. With CRS84 input, the prototype requests `0.0001` degree pixels (approximately 10–11 m at the sample latitude), avoiding a heavy projection dependency for this limited proof of concept.

## CLI usage

Python 3 is the only local dependency; `requirements.txt` intentionally contains no packages.

```powershell
python scripts/satellite/analyze_ndvi.py scripts/satellite/sample_parcel.geojson

python scripts/satellite/analyze_ndvi.py scripts/satellite/sample_parcel.geojson `
  --start-date 2026-09-01 `
  --end-date 2026-09-30 `
  --max-cloud 20 `
  --timeout 60
```

Dates are inclusive. Stdout contains exactly one compact JSON object. A successful result has this shape (values below are illustrative, not a live result):

```json
{
  "ok": true,
  "provider": "Copernicus Data Space Ecosystem / Sentinel-2 L2A",
  "observation_date": "2026-09-29",
  "scene_id": "S2C_example",
  "ndvi_mean": 0.61,
  "ndvi_min": 0.12,
  "ndvi_max": 0.84,
  "valid_pixel_count": 96,
  "cloud_percentage": 8.4,
  "parcel_cloud_percentage": 7.5,
  "requested_date_window": {"start": "2026-09-01", "end": "2026-09-30"},
  "max_cloud_threshold": 30.0,
  "execution_seconds": 2.31
}
```

Failures also remain machine-readable, for example:

```json
{"ok":false,"provider":"Copernicus Data Space Ecosystem / Sentinel-2 L2A","reason":"no_usable_imagery","message":"Candidate scenes were found, but cloud masking left no valid NDVI pixels over the parcel.","execution_seconds":1.42}
```

Handled failure reasons include `invalid_input`, `missing_credentials`, `authentication_failure`, `provider_unavailable`, `rate_limited`, `timeout`, `no_imagery`, `excessive_cloud_coverage`, `no_usable_imagery`, `invalid_api_response`, and `raster_processing_failure`. Failures exit nonzero; successful analysis exits zero.

## Tests and live-test status

Run offline tests from the repository root:

```powershell
python -m unittest discover -s scripts/satellite/tests -v
```

The tests validate polygons, verify construction of spatial/date/cloud requests and the B08/B04 formula, and exercise a full mocked provider response. They make no network calls and use no credentials.

Live test status on 2026-10-03: **not run because `COPERNICUS_CLIENT_ID` and `COPERNICUS_CLIENT_SECRET` were not configured in the execution environment.** No NDVI value has been fabricated. The measured local validation/missing-credential path completed in approximately 0.001 seconds; network and raster-processing runtime remains unmeasured until credentials are supplied, and the live command will report it as `execution_seconds`.

## Limitations and next integration step

- Product cloud metadata covers a Sentinel-2 tile, not just the parcel; the additional SCL percentage is an estimate over the parcel.
- SCL is a classification and can miss thin cloud or haze.
- Same-day overlapping tiles are mosaicked by Sentinel Hub; the reported scene ID is the newest catalogue candidate for that date.
- Very small or narrow parcels may contain few 10 m pixels.
- This is synchronous, manual, single-parcel processing only. It has no scheduling, history, crop-health classification, or production credential storage.
- NDVI alone must not be interpreted as a definitive “healthy”, “damaged”, “harvested”, or “planted” status.

After one credentialed live run succeeds, the exact next SCAIMS step is to design a server-side Admin-only adapter that invokes this script/service, stores credentials outside the web root, validates authorization and parcel ownership, and only then propose persistence/UI changes. That phase is intentionally not implemented here.

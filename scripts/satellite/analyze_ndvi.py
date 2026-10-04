#!/usr/bin/env python3
"""One-parcel Sentinel-2 NDVI proof of concept for SCAIMS.

Stdout is reserved for one JSON result. Diagnostics, if added while debugging,
must be written to stderr.
"""

from __future__ import annotations

import argparse
import json
import math
import os
import socket
import sys
import time
from dataclasses import dataclass
from datetime import date, datetime, timedelta, timezone
from pathlib import Path
from typing import Any
from urllib.error import HTTPError, URLError
from urllib.parse import urlencode
from urllib.request import Request, urlopen


PROVIDER = "Copernicus Data Space Ecosystem / Sentinel-2 L2A"
TOKEN_URL = (
    "https://identity.dataspace.copernicus.eu/auth/realms/CDSE/"
    "protocol/openid-connect/token"
)
STAC_SEARCH_URL = "https://stac.dataspace.copernicus.eu/v1/search"
STATISTICS_URL = "https://sh.dataspace.copernicus.eu/statistics/v1"
COLLECTION = "sentinel-2-l2a"
DEFAULT_WINDOW_DAYS = 30
DEFAULT_MAX_CLOUD = 30.0
DEFAULT_TIMEOUT = 60.0
MAX_CANDIDATE_DATES = 5


# One dataMask band is paired with each named output. NDVI excludes no-data,
# undefined ratios, cloud shadow, medium/high cloud, cirrus, and snow/ice.
# The cloud output keeps all source data so its mean is the parcel cloud ratio.
EVALSCRIPT = r"""//VERSION=3
function setup() {
  return {
    input: [{
      bands: ["B04", "B08", "SCL", "dataMask"],
      units: "REFLECTANCE"
    }],
    output: [
      { id: "ndvi", bands: 1, sampleType: "FLOAT32" },
      { id: "cloud", bands: 1, sampleType: "UINT8" },
      { id: "dataMask", bands: ["ndvi", "cloud"] }
    ]
  };
}

function evaluatePixel(sample) {
  const denominator = sample.B08 + sample.B04;
  const cloudOrShadow = [3, 8, 9, 10, 11].includes(sample.SCL);
  const validNdvi = sample.dataMask && !cloudOrShadow && denominator !== 0;
  const ndvi = denominator === 0 ? 0 : (sample.B08 - sample.B04) / denominator;
  return {
    ndvi: [ndvi],
    cloud: [cloudOrShadow ? 1 : 0],
    dataMask: [validNdvi ? 1 : 0, sample.dataMask]
  };
}
"""


class ValidationError(ValueError):
    """Raised before any provider request is made."""


class ProviderError(RuntimeError):
    def __init__(self, reason: str, message: str):
        super().__init__(message)
        self.reason = reason
        self.message = message


class JsonTransport:
    """Small standard-library HTTP transport to avoid geospatial dependencies."""

    def post_json(
        self,
        url: str,
        payload: dict[str, Any] | None,
        *,
        headers: dict[str, str] | None = None,
        form: dict[str, str] | None = None,
        timeout: float,
    ) -> dict[str, Any]:
        request_headers = {"Accept": "application/json", **(headers or {})}
        if form is not None:
            body = urlencode(form).encode("utf-8")
            request_headers["Content-Type"] = "application/x-www-form-urlencoded"
        else:
            body = json.dumps(payload).encode("utf-8")
            request_headers["Content-Type"] = "application/json"

        request = Request(url, data=body, headers=request_headers, method="POST")
        try:
            with urlopen(request, timeout=timeout) as response:
                raw = response.read().decode("utf-8")
        except HTTPError as exc:
            try:
                detail = exc.read().decode("utf-8", errors="replace")[:500]
            except Exception:
                detail = ""
            if exc.code in (401, 403):
                raise ProviderError("authentication_failure", f"Provider rejected authentication ({exc.code}).") from exc
            if exc.code == 429:
                raise ProviderError("rate_limited", "Copernicus API rate or quota limit was reached.") from exc
            raise ProviderError(
                "provider_unavailable",
                f"Copernicus API returned HTTP {exc.code}. {detail}".strip(),
            ) from exc
        except (TimeoutError, socket.timeout) as exc:
            raise ProviderError("timeout", "Copernicus API request timed out.") from exc
        except URLError as exc:
            if isinstance(exc.reason, (TimeoutError, socket.timeout)):
                raise ProviderError("timeout", "Copernicus API request timed out.") from exc
            raise ProviderError("provider_unavailable", f"Copernicus API is unavailable: {exc.reason}") from exc

        try:
            result = json.loads(raw)
        except json.JSONDecodeError as exc:
            raise ProviderError("invalid_api_response", "Copernicus returned non-JSON data.") from exc
        if not isinstance(result, dict):
            raise ProviderError("invalid_api_response", "Copernicus returned an unexpected JSON type.")
        return result


def load_polygon(path: Path) -> dict[str, Any]:
    try:
        document = json.loads(path.read_text(encoding="utf-8"))
    except FileNotFoundError as exc:
        raise ValidationError(f"GeoJSON file not found: {path}") from exc
    except OSError as exc:
        raise ValidationError(f"Cannot read GeoJSON file: {exc}") from exc
    except json.JSONDecodeError as exc:
        raise ValidationError(f"Malformed JSON at line {exc.lineno}, column {exc.colno}.") from exc

    if not isinstance(document, dict):
        raise ValidationError("GeoJSON root must be an object.")
    if document.get("type") == "Feature":
        geometry = document.get("geometry")
    else:
        geometry = document
    return validate_polygon(geometry)


def validate_polygon(geometry: Any) -> dict[str, Any]:
    if not isinstance(geometry, dict) or geometry.get("type") != "Polygon":
        raise ValidationError("GeoJSON geometry type must be Polygon.")
    rings = geometry.get("coordinates")
    if not isinstance(rings, list) or not rings:
        raise ValidationError("Polygon coordinates must contain at least one linear ring.")

    normalized_rings: list[list[list[float]]] = []
    for ring_index, ring in enumerate(rings):
        if not isinstance(ring, list) or len(ring) < 4:
            raise ValidationError(f"Polygon ring {ring_index} needs at least four positions including closure.")
        normalized: list[list[float]] = []
        for point_index, point in enumerate(ring):
            if not isinstance(point, (list, tuple)) or len(point) < 2:
                raise ValidationError(f"Position {point_index} in ring {ring_index} must be [longitude, latitude].")
            longitude, latitude = point[0], point[1]
            if (
                isinstance(longitude, bool)
                or isinstance(latitude, bool)
                or not isinstance(longitude, (int, float))
                or not isinstance(latitude, (int, float))
                or not math.isfinite(longitude)
                or not math.isfinite(latitude)
            ):
                raise ValidationError(f"Position {point_index} in ring {ring_index} must contain finite numbers.")
            if not -180 <= longitude <= 180:
                raise ValidationError(f"Longitude {longitude} is outside [-180, 180].")
            if not -90 <= latitude <= 90:
                raise ValidationError(f"Latitude {latitude} is outside [-90, 90].")
            normalized.append([float(longitude), float(latitude)])

        if normalized[0] != normalized[-1]:
            raise ValidationError(f"Polygon ring {ring_index} is not closed (first and last positions must match).")
        if len({tuple(point) for point in normalized[:-1]}) < 3:
            raise ValidationError(f"Polygon ring {ring_index} needs at least three distinct vertices.")
        twice_area = sum(
            normalized[i][0] * normalized[i + 1][1]
            - normalized[i + 1][0] * normalized[i][1]
            for i in range(len(normalized) - 1)
        )
        if math.isclose(twice_area, 0.0, abs_tol=1e-15):
            raise ValidationError(f"Polygon ring {ring_index} has zero area.")
        normalized_rings.append(normalized)

    return {"type": "Polygon", "coordinates": normalized_rings}


def parse_date(value: str, option: str) -> date:
    try:
        return date.fromisoformat(value)
    except ValueError as exc:
        raise ValidationError(f"{option} must use YYYY-MM-DD format.") from exc


def resolve_dates(start_value: str | None, end_value: str | None) -> tuple[date, date]:
    end = parse_date(end_value, "--end-date") if end_value else datetime.now(timezone.utc).date()
    start = parse_date(start_value, "--start-date") if start_value else end - timedelta(days=DEFAULT_WINDOW_DAYS - 1)
    if start > end:
        raise ValidationError("--start-date cannot be after --end-date.")
    return start, end


def validate_cloud_threshold(value: float) -> float:
    if not math.isfinite(value) or not 0 <= value <= 100:
        raise ValidationError("--max-cloud must be between 0 and 100.")
    return float(value)


def build_catalog_request(
    polygon: dict[str, Any], start: date, end: date, max_cloud: float | None
) -> dict[str, Any]:
    payload: dict[str, Any] = {
        "collections": [COLLECTION],
        "intersects": polygon,
        "datetime": f"{start.isoformat()}T00:00:00Z/{(end + timedelta(days=1)).isoformat()}T00:00:00Z",
        "sortby": [{"field": "datetime", "direction": "desc"}],
        "limit": 20,
    }
    if max_cloud is not None:
        payload["filter"] = {
            "op": "lte",
            "args": [{"property": "eo:cloud_cover"}, max_cloud],
        }
        payload["filter-lang"] = "cql2-json"
    return payload


def build_statistics_request(
    polygon: dict[str, Any], observation_date: date, max_cloud: float
) -> dict[str, Any]:
    next_day = observation_date + timedelta(days=1)
    return {
        "input": {
            "bounds": {
                "geometry": polygon,
                "properties": {"crs": "http://www.opengis.net/def/crs/OGC/1.3/CRS84"},
            },
            "data": [
                {
                    "type": COLLECTION,
                    "dataFilter": {
                        "mosaickingOrder": "mostRecent",
                        "maxCloudCoverage": max_cloud,
                    },
                }
            ],
        },
        "aggregation": {
            "timeRange": {
                "from": f"{observation_date.isoformat()}T00:00:00Z",
                "to": f"{next_day.isoformat()}T00:00:00Z",
            },
            "aggregationInterval": {"of": "P1D"},
            "evalscript": EVALSCRIPT,
            # CRS84 units are degrees; around the Philippines this is roughly
            # the native 10 m B04/B08 resolution without a projection library.
            "resx": 0.0001,
            "resy": 0.0001,
        },
    }


@dataclass(frozen=True)
class Candidate:
    scene_id: str
    observation_date: date
    cloud_percentage: float


class CopernicusClient:
    def __init__(self, client_id: str, client_secret: str, timeout: float, transport: Any | None = None):
        self.client_id = client_id
        self.client_secret = client_secret
        self.timeout = timeout
        self.transport = transport or JsonTransport()
        self.access_token: str | None = None

    def authenticate(self) -> None:
        response = self.transport.post_json(
            TOKEN_URL,
            None,
            form={
                "grant_type": "client_credentials",
                "client_id": self.client_id,
                "client_secret": self.client_secret,
            },
            timeout=self.timeout,
        )
        token = response.get("access_token")
        if not isinstance(token, str) or not token:
            raise ProviderError("authentication_failure", "OAuth response did not contain an access token.")
        self.access_token = token

    def _headers(self) -> dict[str, str]:
        if not self.access_token:
            raise ProviderError("authentication_failure", "No OAuth access token is available.")
        return {"Authorization": f"Bearer {self.access_token}"}

    def find_candidates(
        self, polygon: dict[str, Any], start: date, end: date, max_cloud: float
    ) -> list[Candidate]:
        response = self.transport.post_json(
            STAC_SEARCH_URL,
            build_catalog_request(polygon, start, end, max_cloud),
            timeout=self.timeout,
        )
        features = response.get("features")
        if not isinstance(features, list):
            raise ProviderError("invalid_api_response", "STAC response has no features array.")

        candidates: list[Candidate] = []
        seen_dates: set[date] = set()
        for feature in features:
            try:
                properties = feature["properties"]
                observed = datetime.fromisoformat(properties["datetime"].replace("Z", "+00:00")).date()
                cloud = float(properties["eo:cloud_cover"])
                scene_id = str(feature["id"])
            except (KeyError, TypeError, ValueError, AttributeError):
                continue
            if observed not in seen_dates:
                candidates.append(Candidate(scene_id, observed, cloud))
                seen_dates.add(observed)
            if len(candidates) >= MAX_CANDIDATE_DATES:
                break
        return candidates

    def any_imagery(self, polygon: dict[str, Any], start: date, end: date) -> bool:
        payload = build_catalog_request(polygon, start, end, None)
        payload["limit"] = 1
        response = self.transport.post_json(STAC_SEARCH_URL, payload, timeout=self.timeout)
        features = response.get("features")
        if not isinstance(features, list):
            raise ProviderError("invalid_api_response", "STAC response has no features array.")
        return bool(features)

    def statistics(self, polygon: dict[str, Any], candidate: Candidate, max_cloud: float) -> dict[str, Any] | None:
        response = self.transport.post_json(
            STATISTICS_URL,
            build_statistics_request(polygon, candidate.observation_date, max_cloud),
            headers=self._headers(),
            timeout=self.timeout,
        )
        if response.get("status") not in (None, "OK"):
            raise ProviderError("raster_processing_failure", "Statistical API reported a failed processing status.")
        data = response.get("data")
        if not isinstance(data, list) or not data:
            return None
        try:
            outputs = data[0]["outputs"]
            ndvi = outputs["ndvi"]["bands"]["B0"]["stats"]
            cloud = outputs["cloud"]["bands"]["B0"]["stats"]
            sample_count = int(ndvi["sampleCount"])
            no_data_count = int(ndvi["noDataCount"])
            valid_count = sample_count - no_data_count
            if valid_count <= 0:
                return None
            return {
                "ndvi_mean": float(ndvi["mean"]),
                "ndvi_min": float(ndvi["min"]),
                "ndvi_max": float(ndvi["max"]),
                "valid_pixel_count": valid_count,
                "parcel_cloud_percentage": float(cloud["mean"]) * 100.0,
            }
        except (KeyError, TypeError, ValueError, IndexError) as exc:
            raise ProviderError("invalid_api_response", "Statistical API response is missing NDVI statistics.") from exc


def failure(reason: str, message: str, elapsed: float | None = None) -> dict[str, Any]:
    result: dict[str, Any] = {
        "ok": False,
        "provider": PROVIDER,
        "reason": reason,
        "message": message,
    }
    if elapsed is not None:
        result["execution_seconds"] = round(elapsed, 3)
    return result


def analyze(args: argparse.Namespace, transport: Any | None = None) -> dict[str, Any]:
    started = time.perf_counter()
    try:
        polygon = load_polygon(Path(args.geojson))
        start, end = resolve_dates(args.start_date, args.end_date)
        max_cloud = validate_cloud_threshold(args.max_cloud)
        if not math.isfinite(args.timeout) or args.timeout <= 0:
            raise ValidationError("--timeout must be greater than zero.")
    except ValidationError as exc:
        return failure("invalid_input", str(exc), time.perf_counter() - started)

    client_id = os.environ.get("COPERNICUS_CLIENT_ID", "").strip()
    client_secret = os.environ.get("COPERNICUS_CLIENT_SECRET", "").strip()
    if not client_id or not client_secret:
        return failure(
            "missing_credentials",
            "Set COPERNICUS_CLIENT_ID and COPERNICUS_CLIENT_SECRET in the operating-system environment.",
            time.perf_counter() - started,
        )

    client = CopernicusClient(client_id, client_secret, args.timeout, transport)
    try:
        client.authenticate()
        candidates = client.find_candidates(polygon, start, end, max_cloud)
        if not candidates:
            reason = "excessive_cloud_coverage" if client.any_imagery(polygon, start, end) else "no_imagery"
            message = (
                f"Imagery exists, but none meets the {max_cloud:g}% scene-cloud threshold."
                if reason == "excessive_cloud_coverage"
                else "No Sentinel-2 L2A imagery intersects the polygon in the requested window."
            )
            return failure(reason, message, time.perf_counter() - started)

        for candidate in candidates:
            statistics = client.statistics(polygon, candidate, max_cloud)
            if statistics is None:
                continue
            return {
                "ok": True,
                "provider": PROVIDER,
                "observation_date": candidate.observation_date.isoformat(),
                "scene_id": candidate.scene_id,
                "ndvi_mean": round(statistics["ndvi_mean"], 6),
                "ndvi_min": round(statistics["ndvi_min"], 6),
                "ndvi_max": round(statistics["ndvi_max"], 6),
                "valid_pixel_count": statistics["valid_pixel_count"],
                "cloud_percentage": round(candidate.cloud_percentage, 3),
                "parcel_cloud_percentage": round(statistics["parcel_cloud_percentage"], 3),
                "requested_date_window": {"start": start.isoformat(), "end": end.isoformat()},
                "max_cloud_threshold": max_cloud,
                "execution_seconds": round(time.perf_counter() - started, 3),
            }
        return failure(
            "no_usable_imagery",
            "Candidate scenes were found, but cloud masking left no valid NDVI pixels over the parcel.",
            time.perf_counter() - started,
        )
    except ProviderError as exc:
        return failure(exc.reason, exc.message, time.perf_counter() - started)
    except Exception:
        return failure(
            "raster_processing_failure",
            "Unexpected failure while processing the Copernicus result.",
            time.perf_counter() - started,
        )


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(description="Calculate cloud-masked Sentinel-2 NDVI for one GeoJSON polygon.")
    parser.add_argument("geojson", help="Path to a GeoJSON Polygon or Feature containing a Polygon")
    parser.add_argument("--start-date", help="Inclusive start date (YYYY-MM-DD); defaults to a 30-day window")
    parser.add_argument("--end-date", help="Inclusive end date (YYYY-MM-DD); defaults to current UTC date")
    parser.add_argument(
        "--max-cloud",
        type=float,
        default=DEFAULT_MAX_CLOUD,
        help=f"Maximum Sentinel-2 tile cloud percentage (default: {DEFAULT_MAX_CLOUD:g})",
    )
    parser.add_argument(
        "--timeout", type=float, default=DEFAULT_TIMEOUT, help=f"Per-request timeout in seconds (default: {DEFAULT_TIMEOUT:g})"
    )
    return parser


def main(argv: list[str] | None = None) -> int:
    args = build_parser().parse_args(argv)
    result = analyze(args)
    print(json.dumps(result, separators=(",", ":"), ensure_ascii=False))
    return 0 if result["ok"] else 1


if __name__ == "__main__":
    sys.exit(main())

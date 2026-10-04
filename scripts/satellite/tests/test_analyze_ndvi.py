import argparse
import importlib.util
import json
import os
import sys
import tempfile
import unittest
from datetime import date
from pathlib import Path
from unittest.mock import patch


MODULE_PATH = Path(__file__).resolve().parents[1] / "analyze_ndvi.py"
SPEC = importlib.util.spec_from_file_location("analyze_ndvi", MODULE_PATH)
ndvi = importlib.util.module_from_spec(SPEC)
assert SPEC.loader is not None
sys.modules[SPEC.name] = ndvi
SPEC.loader.exec_module(ndvi)


VALID_POLYGON = {
    "type": "Polygon",
    "coordinates": [[[121.15, 13.85], [121.152, 13.85], [121.152, 13.852], [121.15, 13.85]]],
}


class MockTransport:
    def __init__(self, responses):
        self.responses = list(responses)
        self.calls = []

    def post_json(self, url, payload, **kwargs):
        self.calls.append({"url": url, "payload": payload, **kwargs})
        return self.responses.pop(0)


class PolygonValidationTests(unittest.TestCase):
    def test_accepts_polygon(self):
        self.assertEqual(ndvi.validate_polygon(VALID_POLYGON)["type"], "Polygon")

    def test_rejects_out_of_range_coordinate(self):
        invalid = {"type": "Polygon", "coordinates": [[[181, 0], [1, 0], [1, 1], [181, 0]]]}
        with self.assertRaisesRegex(ndvi.ValidationError, "Longitude"):
            ndvi.validate_polygon(invalid)

    def test_rejects_too_few_distinct_points(self):
        invalid = {"type": "Polygon", "coordinates": [[[1, 1], [2, 2], [1, 1], [1, 1]]]}
        with self.assertRaisesRegex(ndvi.ValidationError, "distinct vertices"):
            ndvi.validate_polygon(invalid)

    def test_rejects_malformed_json(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "bad.geojson"
            path.write_text("{bad", encoding="utf-8")
            with self.assertRaisesRegex(ndvi.ValidationError, "Malformed JSON"):
                ndvi.load_polygon(path)


class RequestConstructionTests(unittest.TestCase):
    def test_catalog_request_contains_polygon_dates_cloud_filter_and_sort(self):
        payload = ndvi.build_catalog_request(VALID_POLYGON, date(2026, 9, 1), date(2026, 9, 30), 25)
        self.assertEqual(payload["intersects"], VALID_POLYGON)
        self.assertEqual(payload["collections"], ["sentinel-2-l2a"])
        self.assertEqual(payload["datetime"], "2026-09-01T00:00:00Z/2026-10-01T00:00:00Z")
        self.assertEqual(payload["filter"]["args"][1], 25)
        self.assertEqual(payload["sortby"][0]["direction"], "desc")

    def test_statistics_request_uses_expected_bands_and_formula(self):
        payload = ndvi.build_statistics_request(VALID_POLYGON, date(2026, 9, 29), 30)
        script = payload["aggregation"]["evalscript"]
        self.assertIn('"B04"', script)
        self.assertIn('"B08"', script)
        self.assertIn("(sample.B08 - sample.B04) / denominator", script)
        self.assertEqual(payload["input"]["data"][0]["type"], "sentinel-2-l2a")


class MockedWorkflowTests(unittest.TestCase):
    def test_mocked_success_has_machine_readable_result(self):
        transport = MockTransport(
            [
                {"access_token": "mock-token"},
                {
                    "features": [
                        {
                            "id": "S2C_TEST_SCENE",
                            "properties": {"datetime": "2026-09-29T02:30:00Z", "eo:cloud_cover": 8.4},
                        }
                    ]
                },
                {
                    "status": "OK",
                    "data": [
                        {
                            "outputs": {
                                "ndvi": {
                                    "bands": {
                                        "B0": {
                                            "stats": {
                                                "min": 0.1,
                                                "max": 0.8,
                                                "mean": 0.61,
                                                "sampleCount": 100,
                                                "noDataCount": 4,
                                            }
                                        }
                                    }
                                },
                                "cloud": {"bands": {"B0": {"stats": {"mean": 0.075}}}},
                            }
                        }
                    ],
                },
            ]
        )
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "parcel.geojson"
            path.write_text(json.dumps(VALID_POLYGON), encoding="utf-8")
            args = argparse.Namespace(
                geojson=str(path),
                start_date="2026-09-01",
                end_date="2026-09-30",
                max_cloud=30.0,
                timeout=10.0,
            )
            with patch.dict(
                os.environ,
                {"COPERNICUS_CLIENT_ID": "mock-id", "COPERNICUS_CLIENT_SECRET": "mock-secret"},
                clear=False,
            ):
                result = ndvi.analyze(args, transport=transport)

        self.assertTrue(result["ok"])
        self.assertEqual(result["observation_date"], "2026-09-29")
        self.assertEqual(result["ndvi_mean"], 0.61)
        self.assertEqual(result["valid_pixel_count"], 96)
        self.assertEqual(result["cloud_percentage"], 8.4)
        self.assertEqual(result["parcel_cloud_percentage"], 7.5)
        json.dumps(result)
        self.assertNotIn("mock-secret", json.dumps(result))


if __name__ == "__main__":
    unittest.main()

-- S2B scaffolding (schema only). Additive and idempotent.
--
-- Adds storage for a parcel's boundary polygon (needed by both the
-- Statistical API and the Process API, neither of which is wired to
-- real credentials yet) and a satellite_readings table to persist
-- observations over time so "week-over-week" can mean real stored
-- history rather than two manually-supplied JSON files.
--
-- No backfill is possible: there is no real polygon or observation data
-- yet. New rows stay NULL/empty until S2B wires a credentialed adapter.
ALTER TABLE parcel_details
    ADD COLUMN IF NOT EXISTS ParcelBoundary TEXT DEFAULT NULL
        COMMENT 'GeoJSON Polygon geometry for this parcel, used by the satellite NDVI adapter';

CREATE TABLE IF NOT EXISTS satellite_readings (
    id INT(11) NOT NULL AUTO_INCREMENT,
    parcel_detail_id INT(11) NOT NULL,
    reading_date DATE NOT NULL,
    ndvi_mean DECIMAL(8,6) DEFAULT NULL,
    ndvi_min DECIMAL(8,6) DEFAULT NULL,
    ndvi_max DECIMAL(8,6) DEFAULT NULL,
    valid_pixel_count INT(11) DEFAULT NULL,
    raw_response LONGTEXT DEFAULT NULL
        COMMENT 'Full analyze_ndvi.py JSON result for this reading, kept for audit/replay',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY parcel_detail_id (parcel_detail_id),
    KEY idx_satellite_readings_parcel_date (parcel_detail_id, reading_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

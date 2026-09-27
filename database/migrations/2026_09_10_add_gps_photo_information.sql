-- GPS stamp values extracted from the original report image. Historical rows stay NULL.
ALTER TABLE crop_reports
    ADD COLUMN IF NOT EXISTS photo_capture_date DATE NULL,
    ADD COLUMN IF NOT EXISTS photo_capture_time VARCHAR(8) NULL,
    ADD COLUMN IF NOT EXISTS photo_location TEXT NULL,
    ADD COLUMN IF NOT EXISTS photo_latitude DECIMAL(12,9) NULL,
    ADD COLUMN IF NOT EXISTS photo_longitude DECIMAL(12,9) NULL,
    ADD COLUMN IF NOT EXISTS photo_timezone VARCHAR(16) NULL,
    ADD COLUMN IF NOT EXISTS photo_ocr_version VARCHAR(32) NULL;

ALTER TABLE damage_reports
    ADD COLUMN IF NOT EXISTS photo_capture_date DATE NULL,
    ADD COLUMN IF NOT EXISTS photo_capture_time VARCHAR(8) NULL,
    ADD COLUMN IF NOT EXISTS photo_location TEXT NULL,
    ADD COLUMN IF NOT EXISTS photo_latitude DECIMAL(12,9) NULL,
    ADD COLUMN IF NOT EXISTS photo_longitude DECIMAL(12,9) NULL,
    ADD COLUMN IF NOT EXISTS photo_timezone VARCHAR(16) NULL,
    ADD COLUMN IF NOT EXISTS photo_ocr_version VARCHAR(32) NULL;

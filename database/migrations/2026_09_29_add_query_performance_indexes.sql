-- Additive indexes for the Phase 10 report pagination, map aggregation, and
-- username-owned parcel query paths. MariaDB's IF NOT EXISTS keeps this safe
-- when an equivalent named index was installed manually before the ledger.
ALTER TABLE crop_reports
    ADD INDEX IF NOT EXISTS idx_crop_reports_report_date (reportDate, id);

ALTER TABLE damage_reports
    ADD INDEX IF NOT EXISTS idx_damage_reports_report_date (reportDate, id),
    ADD INDEX IF NOT EXISTS idx_damage_reports_occurrence_date (date_occurrence, id),
    ADD INDEX IF NOT EXISTS idx_damage_farmer_crop (FarmerID, dmgCropType);

ALTER TABLE farm_parcels
    ADD INDEX IF NOT EXISTS idx_farm_parcels_created_by (created_by);

ALTER TABLE `user`
    ADD INDEX IF NOT EXISTS idx_user_username (UName);

ALTER TABLE admin
    ADD INDEX IF NOT EXISTS idx_admin_name (AdminName);

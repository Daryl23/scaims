-- Additive and idempotent. Adds a superadmin-only validation flag and an
-- Active/Inactive/Deceased status to the farmer record, separate from the
-- existing `is_active` login-enablement flag.
ALTER TABLE farmer
    ADD COLUMN IF NOT EXISTS IsValidated TINYINT(1) NOT NULL DEFAULT 0 AFTER RSBSA_Number,
    ADD COLUMN IF NOT EXISTS ValidatedBy VARCHAR(234) DEFAULT NULL AFTER IsValidated,
    ADD COLUMN IF NOT EXISTS ValidatedAt DATETIME DEFAULT NULL AFTER ValidatedBy,
    ADD COLUMN IF NOT EXISTS FarmerStatus ENUM('Active','Inactive','Deceased') NOT NULL DEFAULT 'Active' AFTER ValidatedAt;

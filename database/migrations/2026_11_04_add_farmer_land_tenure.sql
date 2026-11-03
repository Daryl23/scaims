-- Additive and idempotent. Distinguishes a landowner-farmer from a tenant
-- or landless farm worker, who is often not the one registered today.
ALTER TABLE farmer
    ADD COLUMN IF NOT EXISTS LandTenure ENUM('Landowner','Tenant','Farm Worker') NOT NULL DEFAULT 'Landowner' AFTER FarmerStatus;

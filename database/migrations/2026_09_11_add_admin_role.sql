-- Additive and idempotent. Existing Admin accounts retain the normal Admin role.
ALTER TABLE admin
    ADD COLUMN IF NOT EXISTS AdminRole VARCHAR(32) NOT NULL DEFAULT 'admin' AFTER AdminPass;

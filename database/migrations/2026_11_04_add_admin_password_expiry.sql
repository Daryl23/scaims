-- Additive and idempotent. Tracks when each admin last changed their
-- password so a 90-day rotation can be enforced at login. Existing rows
-- are backfilled to NOW() so current admins aren't locked out immediately;
-- the 90-day clock starts counting from this migration going forward.
ALTER TABLE admin
    ADD COLUMN IF NOT EXISTS PasswordChangedAt DATETIME DEFAULT NULL AFTER AdminPass;

UPDATE admin SET PasswordChangedAt = NOW() WHERE PasswordChangedAt IS NULL;

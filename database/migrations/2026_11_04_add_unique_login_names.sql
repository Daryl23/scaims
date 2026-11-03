-- Additive and idempotent. Login names must be unique: farmers sign in by
-- username (or RSBSA number) and admins by AdminName. The columns use a
-- case-insensitive collation, so "Juan" and "juan" also count as the same.
-- Safe to run now: no duplicates existed when this was written.
ALTER TABLE user
    ADD UNIQUE KEY IF NOT EXISTS uq_user_uname (UName);

ALTER TABLE admin
    ADD UNIQUE KEY IF NOT EXISTS uq_admin_name (AdminName);

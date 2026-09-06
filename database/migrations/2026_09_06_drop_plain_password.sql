-- ============================================================
-- Remove plaintext password storage from the user table.
--
-- Until now every account's password was stored twice: as a
-- bcrypt hash in user.UPass, and in readable form in
-- user.plain_password. The readable copy was shown in the
-- farmer profile form, returned to the admin user-management
-- screen, and included as a "Password" column in the farmer
-- export. All of that has been removed from the application
-- code; this migration removes the stored data.
--
-- Nothing reads plain_password any more. Sign-in has always
-- verified against the bcrypt hash in UPass, so accounts are
-- unaffected.
--
-- Run:
--   /Applications/XAMPP/xamppfiles/bin/mysql -uroot apgorrs \
--     < database/migrations/2026_09_06_drop_plain_password.sql
--
-- Step 1 clears the readable passwords. Step 2 drops the column.
-- Run step 1 on its own first if you would rather keep the
-- column in place for a while; both are safe to run twice.
-- ============================================================

-- Step 1 — erase the stored plaintext.
UPDATE user SET plain_password = NULL;

-- Step 2 — drop the column entirely.
ALTER TABLE user DROP COLUMN plain_password;

-- Verification: every account should still carry a bcrypt hash.
--   SELECT COUNT(*) FROM user WHERE UPass LIKE '$2y$%';

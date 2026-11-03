-- Makes RSBSA numbers unique per farmer. Blank RSBSA numbers are allowed to
-- repeat (many farmers have none yet): the unique key sits on a computed
-- copy that turns blanks into NULL, so existing rows and app writes are
-- left unchanged.
--
-- RUN ONLY AFTER DUPLICATES ARE RESOLVED. While any non-blank RSBSA number
-- is shared by two farmers this fails with "Duplicate entry", changing
-- nothing. Find duplicates first with:
--
--   SELECT RSBSA_Number, GROUP_CONCAT(FarmerID) AS farmer_ids
--   FROM farmer WHERE RSBSA_Number <> ''
--   GROUP BY RSBSA_Number HAVING COUNT(*) > 1;
ALTER TABLE farmer
    ADD COLUMN IF NOT EXISTS RSBSA_Unique VARCHAR(50) AS (NULLIF(RSBSA_Number, '')) PERSISTENT,
    ADD UNIQUE KEY IF NOT EXISTS uq_farmer_rsbsa (RSBSA_Unique);

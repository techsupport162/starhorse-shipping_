-- Expand image asset columns to hold Cloudinary secure URLs.
-- Existing local filenames remain unchanged and continue to resolve locally.
BEGIN;

ALTER TABLE users
  ALTER COLUMN profile_image TYPE TEXT;

ALTER TABLE vessel_images
  ALTER COLUMN image TYPE TEXT;

ALTER TABLE fares
  ALTER COLUMN passenger_fare_image TYPE TEXT,
  ALTER COLUMN cargo_fare_image TYPE TEXT;

ALTER TABLE route_banner
  ALTER COLUMN banner_image TYPE TEXT;

ALTER TABLE route_destinations
  ALTER COLUMN destination_image TYPE TEXT;

ALTER TABLE career_postings
  ALTER COLUMN recruitment_image TYPE TEXT;

COMMIT;
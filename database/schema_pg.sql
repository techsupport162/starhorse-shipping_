-- =============================================================================
-- Starhorse Shipping Line, Inc. — PostgreSQL Schema (Neon / any PG host)
-- =============================================================================
-- Run this once on a fresh database to create all tables and seed data.
--
-- Neon:  Dashboard → SQL Editor → paste and Run
-- CLI:   psql "postgresql://..." -f schema_pg.sql
--
-- Default admin login:
--   Username : admin
--   Password : Starhorse@Admin1   ← CHANGE THIS IMMEDIATELY
-- =============================================================================

-- Reusable trigger function: keeps updated_at current on every UPDATE
-- (mirrors MySQL's ON UPDATE CURRENT_TIMESTAMP).
CREATE OR REPLACE FUNCTION touch_updated_at()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
BEGIN
  NEW.updated_at = NOW();
  RETURN NEW;
END;
$$;

-- ============================================================
-- USERS
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
  id              SERIAL        PRIMARY KEY,
  profile_image   TEXT          DEFAULT NULL,
  username        VARCHAR(50)   NOT NULL,
  password        VARCHAR(255)  NOT NULL,
  full_name       VARCHAR(100)  NOT NULL DEFAULT '',
  email           VARCHAR(150)  NOT NULL DEFAULT '',
  contact_number  VARCHAR(30)   DEFAULT NULL,
  status          VARCHAR(20)   NOT NULL DEFAULT 'active'
                    CHECK (status IN ('active','inactive')),
  user_access     VARCHAR(20)   NOT NULL DEFAULT 'user'
                    CHECK (user_access IN ('administrator','user')),
  last_login_at   TIMESTAMP     DEFAULT NULL,
  created_at      TIMESTAMP     NOT NULL DEFAULT NOW(),
  updated_at      TIMESTAMP     NOT NULL DEFAULT NOW(),
  CONSTRAINT uq_users_username UNIQUE (username),
  CONSTRAINT uq_users_email    UNIQUE (email)
);
CREATE OR REPLACE TRIGGER trg_users_updated_at
  BEFORE UPDATE ON users
  FOR EACH ROW EXECUTE FUNCTION touch_updated_at();

-- Default admin — password: Starhorse@Admin1 (bcrypt)
INSERT INTO users (username, password, full_name, email, status, user_access)
VALUES (
  'admin',
  '$2b$10$ACs7/pqMZCG0ySL2xZWfw.nTH2LnlAp8wkCvjsk6hN32jssuJRPem',
  'Site Administrator',
  'admin@starhorseshipping.com',
  'active',
  'administrator'
) ON CONFLICT (username) DO NOTHING;

-- ============================================================
-- LOGIN ATTEMPTS
-- ============================================================
CREATE TABLE IF NOT EXISTS login_attempts (
  id           SERIAL      PRIMARY KEY,
  username     VARCHAR(100) NOT NULL,
  ip_address   VARCHAR(45)  NOT NULL,
  attempted_at TIMESTAMP    NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_login_attempts_ip_time
  ON login_attempts (ip_address, attempted_at);
CREATE INDEX IF NOT EXISTS idx_login_attempts_user_ip_time
  ON login_attempts (username, ip_address, attempted_at);

-- ============================================================
-- VESSELS
-- ============================================================
CREATE TABLE IF NOT EXISTS vessels (
  id            SERIAL        PRIMARY KEY,
  name          VARCHAR(150)  NOT NULL,
  tagline       VARCHAR(100)  DEFAULT NULL,
  length_m      NUMERIC(8,2)  DEFAULT NULL,
  breadth_m     NUMERIC(8,2)  DEFAULT NULL,
  depth_m       NUMERIC(8,2)  DEFAULT NULL,
  gross_tonnage NUMERIC(10,2) DEFAULT NULL,
  description   TEXT          DEFAULT NULL,
  status        VARCHAR(20)   NOT NULL DEFAULT 'published'
                  CHECK (status IN ('published','draft')),
  sort_order    INTEGER       NOT NULL DEFAULT 0,
  created_at    TIMESTAMP     NOT NULL DEFAULT NOW(),
  updated_at    TIMESTAMP     NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_vessels_status ON vessels (status);
CREATE OR REPLACE TRIGGER trg_vessels_updated_at
  BEFORE UPDATE ON vessels
  FOR EACH ROW EXECUTE FUNCTION touch_updated_at();

-- ============================================================
-- VESSEL IMAGES
-- ============================================================
CREATE TABLE IF NOT EXISTS vessel_images (
  id          SERIAL       PRIMARY KEY,
  vessel_id   INTEGER      NOT NULL
                REFERENCES vessels (id) ON DELETE CASCADE ON UPDATE CASCADE,
  image       TEXT         NOT NULL,
  sort_order  INTEGER      NOT NULL DEFAULT 0,
  created_at  TIMESTAMP    NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_vessel_images_vessel_id ON vessel_images (vessel_id);

-- ============================================================
-- VESSEL FEATURES
-- ============================================================
CREATE TABLE IF NOT EXISTS vessel_features (
  id          SERIAL       PRIMARY KEY,
  name        VARCHAR(100) NOT NULL,
  icon        VARCHAR(50)  NOT NULL DEFAULT 'bi-check2-circle',
  sort_order  INTEGER      NOT NULL DEFAULT 0,
  created_at  TIMESTAMP    NOT NULL DEFAULT NOW(),
  updated_at  TIMESTAMP    NOT NULL DEFAULT NOW()
);
CREATE OR REPLACE TRIGGER trg_vessel_features_updated_at
  BEFORE UPDATE ON vessel_features
  FOR EACH ROW EXECUTE FUNCTION touch_updated_at();

-- ============================================================
-- VESSEL FEATURE ASSIGNMENTS
-- ============================================================
CREATE TABLE IF NOT EXISTS vessel_feature_assignments (
  id          SERIAL    PRIMARY KEY,
  vessel_id   INTEGER   NOT NULL,
  feature_id  INTEGER   NOT NULL,
  sort_order  INTEGER   NOT NULL DEFAULT 0,
  created_at  TIMESTAMP NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_vfa_vessel  ON vessel_feature_assignments (vessel_id);
CREATE INDEX IF NOT EXISTS idx_vfa_feature ON vessel_feature_assignments (feature_id);

-- ============================================================
-- FARES
-- ============================================================
CREATE TABLE IF NOT EXISTS fares (
  id                   SERIAL       PRIMARY KEY,
  origin               VARCHAR(100) NOT NULL,
  destination          VARCHAR(100) NOT NULL,
  passenger_fare_image TEXT DEFAULT NULL,
  cargo_fare_image     TEXT DEFAULT NULL,
  is_vice_versa        SMALLINT     NOT NULL DEFAULT 0,
  status               VARCHAR(20)  NOT NULL DEFAULT 'published'
                         CHECK (status IN ('published','draft')),
  sort_order           INTEGER      NOT NULL DEFAULT 0,
  created_at           TIMESTAMP    NOT NULL DEFAULT NOW(),
  updated_at           TIMESTAMP    NOT NULL DEFAULT NOW(),
  CONSTRAINT uq_fares_route UNIQUE (origin, destination)
);
CREATE INDEX IF NOT EXISTS idx_fares_status ON fares (status);
CREATE OR REPLACE TRIGGER trg_fares_updated_at
  BEFORE UPDATE ON fares
  FOR EACH ROW EXECUTE FUNCTION touch_updated_at();

-- ============================================================
-- FAQS
-- ============================================================
CREATE TABLE IF NOT EXISTS faqs (
  id          SERIAL       PRIMARY KEY,
  question    VARCHAR(255) NOT NULL,
  answer      TEXT         NOT NULL,
  status      VARCHAR(20)  NOT NULL DEFAULT 'published'
                CHECK (status IN ('published','draft')),
  sort_order  INTEGER      NOT NULL DEFAULT 0,
  created_at  TIMESTAMP    NOT NULL DEFAULT NOW(),
  updated_at  TIMESTAMP    NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_faqs_status ON faqs (status);
CREATE OR REPLACE TRIGGER trg_faqs_updated_at
  BEFORE UPDATE ON faqs
  FOR EACH ROW EXECUTE FUNCTION touch_updated_at();

-- ============================================================
-- ROUTE BANNER (singleton row — always id = 1)
-- ============================================================
CREATE TABLE IF NOT EXISTS route_banner (
  id           SMALLINT     NOT NULL DEFAULT 1 PRIMARY KEY,
  banner_image TEXT DEFAULT NULL,
  updated_at   TIMESTAMP    NOT NULL DEFAULT NOW()
);
CREATE OR REPLACE TRIGGER trg_route_banner_updated_at
  BEFORE UPDATE ON route_banner
  FOR EACH ROW EXECUTE FUNCTION touch_updated_at();

INSERT INTO route_banner (id) VALUES (1) ON CONFLICT (id) DO NOTHING;

-- ============================================================
-- ROUTE DESTINATIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS route_destinations (
  id                 SERIAL       PRIMARY KEY,
  location_name      VARCHAR(150) NOT NULL,
  destination_image  TEXT         DEFAULT NULL,
  status             VARCHAR(20)  NOT NULL DEFAULT 'published'
                       CHECK (status IN ('published','draft')),
  sort_order         INTEGER      NOT NULL DEFAULT 0,
  created_at         TIMESTAMP    NOT NULL DEFAULT NOW(),
  updated_at         TIMESTAMP    NOT NULL DEFAULT NOW(),
  CONSTRAINT uq_route_destinations_location UNIQUE (location_name)
);
CREATE INDEX IF NOT EXISTS idx_route_destinations_status ON route_destinations (status);
CREATE OR REPLACE TRIGGER trg_route_destinations_updated_at
  BEFORE UPDATE ON route_destinations
  FOR EACH ROW EXECUTE FUNCTION touch_updated_at();

-- ============================================================
-- CONTACT SETTINGS (singleton row — always id = 1)
-- ============================================================
CREATE TABLE IF NOT EXISTS contact_settings (
  id            SMALLINT     NOT NULL DEFAULT 1 PRIMARY KEY,
  office_name   VARCHAR(150) NOT NULL DEFAULT 'Head Office',
  address       VARCHAR(255) NOT NULL DEFAULT '',
  tel_number    VARCHAR(50)  DEFAULT NULL,
  facebook_name VARCHAR(150) DEFAULT NULL,
  facebook_url  VARCHAR(255) DEFAULT NULL,
  map_embed_url TEXT         DEFAULT NULL,
  map_status    VARCHAR(20)  NOT NULL DEFAULT 'published'
                  CHECK (map_status IN ('published','draft')),
  status        VARCHAR(20)  NOT NULL DEFAULT 'published'
                  CHECK (status IN ('published','draft')),
  updated_at    TIMESTAMP    NOT NULL DEFAULT NOW()
);
CREATE OR REPLACE TRIGGER trg_contact_settings_updated_at
  BEFORE UPDATE ON contact_settings
  FOR EACH ROW EXECUTE FUNCTION touch_updated_at();

INSERT INTO contact_settings (id, office_name) VALUES (1, 'Head Office')
  ON CONFLICT (id) DO NOTHING;

-- ============================================================
-- CONTACT EMAILS
-- ============================================================
CREATE TABLE IF NOT EXISTS contact_emails (
  id          SERIAL       PRIMARY KEY,
  email       VARCHAR(150) NOT NULL,
  label       VARCHAR(50)  DEFAULT NULL,
  status      VARCHAR(20)  NOT NULL DEFAULT 'published'
                CHECK (status IN ('published','draft')),
  sort_order  INTEGER      NOT NULL DEFAULT 0,
  created_at  TIMESTAMP    NOT NULL DEFAULT NOW(),
  updated_at  TIMESTAMP    NOT NULL DEFAULT NOW()
);
CREATE OR REPLACE TRIGGER trg_contact_emails_updated_at
  BEFORE UPDATE ON contact_emails
  FOR EACH ROW EXECUTE FUNCTION touch_updated_at();

-- ============================================================
-- CONTACT PHONES
-- ============================================================
CREATE TABLE IF NOT EXISTS contact_phones (
  id           SERIAL       PRIMARY KEY,
  phone_number VARCHAR(30)  NOT NULL,
  label        VARCHAR(50)  DEFAULT NULL,
  status       VARCHAR(20)  NOT NULL DEFAULT 'published'
                 CHECK (status IN ('published','draft')),
  sort_order   INTEGER      NOT NULL DEFAULT 0,
  created_at   TIMESTAMP    NOT NULL DEFAULT NOW(),
  updated_at   TIMESTAMP    NOT NULL DEFAULT NOW()
);
CREATE OR REPLACE TRIGGER trg_contact_phones_updated_at
  BEFORE UPDATE ON contact_phones
  FOR EACH ROW EXECUTE FUNCTION touch_updated_at();

-- ============================================================
-- CONTACT PORTS
-- ============================================================
CREATE TABLE IF NOT EXISTS contact_ports (
  id           SERIAL       PRIMARY KEY,
  port_name    VARCHAR(150) NOT NULL,
  phone_number VARCHAR(30)  NOT NULL,
  status       VARCHAR(20)  NOT NULL DEFAULT 'published'
                 CHECK (status IN ('published','draft')),
  sort_order   INTEGER      NOT NULL DEFAULT 0,
  created_at   TIMESTAMP    NOT NULL DEFAULT NOW(),
  updated_at   TIMESTAMP    NOT NULL DEFAULT NOW()
);
CREATE OR REPLACE TRIGGER trg_contact_ports_updated_at
  BEFORE UPDATE ON contact_ports
  FOR EACH ROW EXECUTE FUNCTION touch_updated_at();

-- ============================================================
-- CAREER POSITIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS career_positions (
  id          SERIAL       PRIMARY KEY,
  name        VARCHAR(100) NOT NULL,
  is_active   SMALLINT     NOT NULL DEFAULT 1,
  sort_order  INTEGER      NOT NULL DEFAULT 0,
  created_at  TIMESTAMP    NOT NULL DEFAULT NOW(),
  updated_at  TIMESTAMP    NOT NULL DEFAULT NOW(),
  CONSTRAINT uq_career_positions_name UNIQUE (name)
);
CREATE INDEX IF NOT EXISTS idx_career_positions_active ON career_positions (is_active);
CREATE OR REPLACE TRIGGER trg_career_positions_updated_at
  BEFORE UPDATE ON career_positions
  FOR EACH ROW EXECUTE FUNCTION touch_updated_at();

-- ============================================================
-- CAREER POSTINGS
-- ============================================================
CREATE TABLE IF NOT EXISTS career_postings (
  id                       SERIAL       PRIMARY KEY,
  position_id              INTEGER      NOT NULL,
  title                    VARCHAR(150) NOT NULL,
  short_description        VARCHAR(255) DEFAULT NULL,
  job_description          TEXT         DEFAULT NULL,
  qualifications           TEXT         DEFAULT NULL,
  responsibilities         TEXT         DEFAULT NULL,
  application_instructions TEXT         DEFAULT NULL,
  contact_email            VARCHAR(150) DEFAULT NULL,
  recruitment_image        TEXT         DEFAULT NULL,
  status                   VARCHAR(20)  NOT NULL DEFAULT 'draft'
                             CHECK (status IN ('published','draft')),
  sort_order               INTEGER      NOT NULL DEFAULT 0,
  created_at               TIMESTAMP    NOT NULL DEFAULT NOW(),
  updated_at               TIMESTAMP    NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_career_postings_status      ON career_postings (status);
CREATE INDEX IF NOT EXISTS idx_career_postings_position_id ON career_postings (position_id);
CREATE OR REPLACE TRIGGER trg_career_postings_updated_at
  BEFORE UPDATE ON career_postings
  FOR EACH ROW EXECUTE FUNCTION touch_updated_at();

-- ============================================================
-- CAREER APPLICANTS
-- ============================================================
CREATE TABLE IF NOT EXISTS career_applicants (
  id                     SERIAL       PRIMARY KEY,
  posting_id             INTEGER      DEFAULT NULL,
  applied_position_title VARCHAR(150) NOT NULL,
  full_name              VARCHAR(150) NOT NULL,
  email                  VARCHAR(150) NOT NULL,
  contact_number         VARCHAR(30)  NOT NULL,
  address                VARCHAR(255) DEFAULT NULL,
  message                TEXT         DEFAULT NULL,
  resume_file            VARCHAR(255) DEFAULT NULL,
  status                 VARCHAR(20)  NOT NULL DEFAULT 'new'
                           CHECK (status IN ('new','reviewed','shortlisted','interview','accepted','rejected')),
  applied_at             TIMESTAMP    NOT NULL DEFAULT NOW(),
  updated_at             TIMESTAMP    NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_career_applicants_status     ON career_applicants (status);
CREATE INDEX IF NOT EXISTS idx_career_applicants_posting_id ON career_applicants (posting_id);
CREATE INDEX IF NOT EXISTS idx_career_applicants_applied_at ON career_applicants (applied_at);
CREATE OR REPLACE TRIGGER trg_career_applicants_updated_at
  BEFORE UPDATE ON career_applicants
  FOR EACH ROW EXECUTE FUNCTION touch_updated_at();

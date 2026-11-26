-- Minimal golden-template fixture for ProvisioningTest (Scope Excluded:
-- the real ~150-table golden template is assembled later from every
-- package's migrations, CI-side). One table + one row is enough to assert
-- that STRATEGY WAL_LOG actually copies data, not just an empty database.
CREATE TABLE smoke_marker (
    id serial PRIMARY KEY,
    note text NOT NULL
);

INSERT INTO smoke_marker (note) VALUES ('golden-template-clone');

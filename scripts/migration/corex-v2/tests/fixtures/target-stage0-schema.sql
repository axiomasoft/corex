CREATE TABLE migration_identity (
    database_id text NOT NULL PRIMARY KEY,
    deployment_id text NOT NULL,
    artifact_sha text NOT NULL,
    binding_generation integer NOT NULL,
    recovery_generation integer NOT NULL
) STRICT;

CREATE TABLE webhook_subscriptions (
    id text NOT NULL PRIMARY KEY,
    status text NOT NULL CHECK (status IN ('active', 'disabled')),
    subscription_json text NOT NULL CHECK (json_valid(subscription_json))
) STRICT;

CREATE TABLE webhook_deliveries (
    effect_key text NOT NULL PRIMARY KEY,
    subscription_id text NOT NULL REFERENCES webhook_subscriptions(id),
    disposition text NOT NULL CHECK (disposition IN ('pending', 'history')),
    delivery_json text NOT NULL CHECK (json_valid(delivery_json))
) STRICT;

CREATE TABLE migration_row_map (
    plan_id text NOT NULL,
    segment text NOT NULL,
    source_pk text NOT NULL,
    source_digest text NOT NULL,
    target_ref text NOT NULL,
    semantic_digest text NOT NULL,
    PRIMARY KEY (plan_id, segment, source_pk),
    UNIQUE (segment, target_ref)
) STRICT;

CREATE TABLE migration_checkpoint (
    plan_id text NOT NULL,
    segment text NOT NULL,
    high_pk text NOT NULL,
    rows integer NOT NULL CHECK (rows > 0),
    digest text NOT NULL,
    phase text NOT NULL CHECK (phase IN ('copying', 'copied')),
    PRIMARY KEY (plan_id, segment)
) STRICT;

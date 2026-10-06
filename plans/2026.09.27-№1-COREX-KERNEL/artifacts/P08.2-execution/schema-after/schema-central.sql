--
-- PostgreSQL database dump
--

\restrict e9RIAibw9OIdPvG7TxzUfnQlJIEvdXSdJSMUUKE2xqQrKOubDmoBDxaVNep5Ipp

-- Dumped from database version 16.14
-- Dumped by pg_dump version 16.14

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: migrations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- Name: root_account_events; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.root_account_events (
    id uuid NOT NULL,
    account_id uuid NOT NULL,
    event character varying(64) NOT NULL,
    actor jsonb DEFAULT '{}'::jsonb NOT NULL,
    payload jsonb DEFAULT '{}'::jsonb NOT NULL,
    created_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: root_account_exports; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.root_account_exports (
    id uuid NOT NULL,
    account_id uuid NOT NULL,
    kind character varying(16) NOT NULL,
    status character varying(16) DEFAULT 'pending'::character varying NOT NULL,
    formats jsonb DEFAULT '["pg_dump", "csv"]'::jsonb NOT NULL,
    storage_path character varying(512),
    url_expires_at timestamp(6) with time zone,
    size_bytes bigint,
    purge_after timestamp(6) with time zone,
    created_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT root_account_exports_kind_ck CHECK (((kind)::text = ANY ((ARRAY['offboarding'::character varying, 'pre_purge'::character varying, 'manual'::character varying])::text[]))),
    CONSTRAINT root_account_exports_status_ck CHECK (((status)::text = ANY ((ARRAY['pending'::character varying, 'running'::character varying, 'ready'::character varying, 'failed'::character varying, 'purged'::character varying])::text[])))
);


--
-- Name: root_account_products; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.root_account_products (
    account_id uuid NOT NULL,
    product character varying(32) NOT NULL,
    activated_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    created_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT root_account_products_product_ck CHECK (((product)::text ~ '^[a-z0-9]([a-z0-9_-]*[a-z0-9])?$'::text))
);


--
-- Name: root_account_schema_state; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.root_account_schema_state (
    account_id uuid NOT NULL,
    current_version integer NOT NULL,
    target_version integer NOT NULL,
    status character varying(16) DEFAULT 'ok'::character varying NOT NULL,
    batch_id uuid,
    last_migrated_at timestamp(6) with time zone,
    last_error text,
    created_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT root_account_schema_state_status_ck CHECK (((status)::text = ANY ((ARRAY['ok'::character varying, 'migrating'::character varying, 'failed'::character varying, 'blocked'::character varying])::text[])))
);


--
-- Name: root_accounts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.root_accounts (
    id uuid NOT NULL,
    slug character varying(63),
    name character varying(255),
    cluster_id uuid NOT NULL,
    db_name character varying(63) NOT NULL,
    status character varying(16) DEFAULT 'pending'::character varying NOT NULL,
    owner_identity_id uuid,
    vertical_code character varying(64),
    template_version integer NOT NULL,
    embedding_model character varying(64) NOT NULL,
    embedding_dim smallint NOT NULL,
    locale character varying(8) DEFAULT 'ru'::character varying NOT NULL,
    trial_ends_at timestamp(6) with time zone,
    suspended_at timestamp(6) with time zone,
    grace_until timestamp(6) with time zone,
    purge_after timestamp(6) with time zone,
    data jsonb DEFAULT '{}'::jsonb NOT NULL,
    created_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    deleted_at timestamp(6) with time zone,
    CONSTRAINT root_accounts_owner_or_pending_ck CHECK ((((status)::text = ANY ((ARRAY['pending'::character varying, 'provisioning'::character varying])::text[])) OR (owner_identity_id IS NOT NULL))),
    CONSTRAINT root_accounts_slug_or_pending_ck CHECK ((((status)::text = ANY ((ARRAY['pending'::character varying, 'provisioning'::character varying])::text[])) OR (slug IS NOT NULL))),
    CONSTRAINT root_accounts_status_ck CHECK (((status)::text = ANY ((ARRAY['pending'::character varying, 'provisioning'::character varying, 'trial'::character varying, 'active'::character varying, 'suspended'::character varying, 'grace'::character varying, 'exporting'::character varying, 'deleted'::character varying])::text[])))
);


--
-- Name: root_clusters; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.root_clusters (
    id uuid NOT NULL,
    name character varying(64) NOT NULL,
    region character varying(32) DEFAULT 'ru-central'::character varying NOT NULL,
    status character varying(16) DEFAULT 'active'::character varying NOT NULL,
    pg_host_writer character varying(255) NOT NULL,
    pg_host_reader character varying(255),
    pg_port integer DEFAULT 5432 NOT NULL,
    pgbouncer_host character varying(255) NOT NULL,
    pgbouncer_port integer DEFAULT 6432 NOT NULL,
    app_db_user character varying(64) NOT NULL,
    tei_endpoint character varying(255),
    backup_repo character varying(255),
    db_count integer DEFAULT 0 NOT NULL,
    db_soft_limit integer DEFAULT 700 NOT NULL,
    pending_pool_size integer DEFAULT 20 NOT NULL,
    template_version integer DEFAULT 0 NOT NULL,
    meta jsonb DEFAULT '{}'::jsonb NOT NULL,
    created_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT root_clusters_status_ck CHECK (((status)::text = ANY ((ARRAY['provisioning'::character varying, 'active'::character varying, 'draining'::character varying, 'readonly'::character varying, 'retired'::character varying])::text[])))
);


--
-- Name: root_domains; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.root_domains (
    id uuid NOT NULL,
    account_id uuid NOT NULL,
    host character varying(255) NOT NULL,
    type character varying(16) NOT NULL,
    is_primary boolean DEFAULT false NOT NULL,
    verification_token character varying(64),
    verified_at timestamp(6) with time zone,
    tls_status character varying(16) DEFAULT 'none'::character varying NOT NULL,
    ca_used character varying(32),
    created_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    deleted_at timestamp(6) with time zone,
    verification_generation bigint DEFAULT '0'::bigint NOT NULL,
    CONSTRAINT root_domains_tls_status_ck CHECK (((tls_status)::text = ANY ((ARRAY['none'::character varying, 'pending'::character varying, 'issued'::character varying, 'failed'::character varying])::text[]))),
    CONSTRAINT root_domains_type_ck CHECK (((type)::text = ANY ((ARRAY['platform'::character varying, 'custom'::character varying])::text[])))
);


--
-- Name: root_feature_flags; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.root_feature_flags (
    id uuid NOT NULL,
    account_id uuid,
    flag character varying(64) NOT NULL,
    value jsonb NOT NULL,
    created_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: root_identities; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.root_identities (
    id uuid NOT NULL,
    email character varying(255) NOT NULL,
    phone character varying(32),
    name character varying(255) DEFAULT ''::character varying NOT NULL,
    password_hash character varying(255),
    email_verified_at timestamp(6) with time zone,
    mfa_totp_secret text,
    mfa_enabled_at timestamp(6) with time zone,
    locale character varying(8) DEFAULT 'ru'::character varying NOT NULL,
    is_staff boolean DEFAULT false NOT NULL,
    last_login_at timestamp(6) with time zone,
    created_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    deleted_at timestamp(6) with time zone
);


--
-- Name: root_identity_accounts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.root_identity_accounts (
    id uuid NOT NULL,
    identity_id uuid NOT NULL,
    account_id uuid NOT NULL,
    status character varying(16) DEFAULT 'active'::character varying NOT NULL,
    sync_status character varying(16) DEFAULT 'pending'::character varying NOT NULL,
    synced_at timestamp(6) with time zone,
    created_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT root_identity_accounts_status_ck CHECK (((status)::text = ANY ((ARRAY['invited'::character varying, 'active'::character varying, 'blocked'::character varying])::text[]))),
    CONSTRAINT root_identity_accounts_sync_status_ck CHECK (((sync_status)::text = ANY ((ARRAY['pending'::character varying, 'synced'::character varying, 'failed'::character varying])::text[])))
);


--
-- Name: root_idp_sessions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.root_idp_sessions (
    id uuid NOT NULL,
    identity_id uuid NOT NULL,
    token_hash character varying(64) NOT NULL,
    ip inet,
    user_agent character varying(512),
    expires_at timestamp(6) with time zone NOT NULL,
    revoked_at timestamp(6) with time zone,
    created_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: root_impersonation_grants; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.root_impersonation_grants (
    id uuid NOT NULL,
    staff_identity_id uuid NOT NULL,
    account_id uuid NOT NULL,
    target_user_id uuid,
    reason text NOT NULL,
    approved_by uuid,
    expires_at timestamp(6) with time zone DEFAULT (now() + '01:00:00'::interval) NOT NULL,
    used_at timestamp(6) with time zone,
    revoked_at timestamp(6) with time zone,
    created_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: root_migration_batches; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.root_migration_batches (
    id uuid NOT NULL,
    from_version integer NOT NULL,
    to_version integer NOT NULL,
    wave character varying(16) NOT NULL,
    status character varying(16) DEFAULT 'created'::character varying NOT NULL,
    stats jsonb DEFAULT '{}'::jsonb NOT NULL,
    started_at timestamp(6) with time zone,
    finished_at timestamp(6) with time zone,
    created_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT root_migration_batches_status_ck CHECK (((status)::text = ANY ((ARRAY['created'::character varying, 'running'::character varying, 'paused'::character varying, 'done'::character varying, 'failed'::character varying])::text[]))),
    CONSTRAINT root_migration_batches_wave_ck CHECK (((wave)::text = ANY ((ARRAY['internal'::character varying, 'canary'::character varying, 'p10'::character varying, 'p50'::character varying, 'all'::character varying])::text[])))
);


--
-- Name: root_oauth_clients; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.root_oauth_clients (
    id uuid NOT NULL,
    client_id character varying(64) NOT NULL,
    secret_hash character varying(255),
    name character varying(128) NOT NULL,
    product character varying(32),
    redirect_patterns jsonb NOT NULL,
    is_first_party boolean DEFAULT true NOT NULL,
    created_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    deleted_at timestamp(6) with time zone
);


--
-- Name: root_oauth_refresh_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.root_oauth_refresh_tokens (
    id uuid NOT NULL,
    session_id uuid NOT NULL,
    client_id character varying(64) NOT NULL,
    token_hash character varying(64) NOT NULL,
    expires_at timestamp(6) with time zone NOT NULL,
    revoked_at timestamp(6) with time zone,
    created_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: root_templates; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.root_templates (
    id uuid NOT NULL,
    version integer NOT NULL,
    db_name character varying(63) NOT NULL,
    status character varying(16) DEFAULT 'building'::character varying NOT NULL,
    embedding_model character varying(64) NOT NULL,
    embedding_dim smallint NOT NULL,
    git_ref character varying(64),
    built_at timestamp(6) with time zone,
    created_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    schema_hash character varying(64),
    CONSTRAINT root_templates_schema_hash_ck CHECK (((schema_hash IS NULL) OR (((schema_hash)::text COLLATE "C") ~ '^[0-9a-f]{64}$'::text))),
    CONSTRAINT root_templates_status_ck CHECK (((status)::text = ANY ((ARRAY['building'::character varying, 'ready'::character varying, 'retired'::character varying])::text[])))
);


--
-- Name: root_usage_snapshots; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.root_usage_snapshots (
    account_id uuid NOT NULL,
    metric character varying(64) NOT NULL,
    period date NOT NULL,
    value numeric(18,3) NOT NULL,
    created_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: root_wakeup_hints; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.root_wakeup_hints (
    account_id uuid NOT NULL,
    next_fire_at timestamp(6) with time zone NOT NULL,
    hint_revision bigint NOT NULL,
    leased_until timestamp(6) with time zone,
    claim_epoch bigint NOT NULL,
    created_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: root_wakeup_reconciliation_state; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.root_wakeup_reconciliation_state (
    name character varying(64) NOT NULL,
    cursor_account_id uuid,
    attempts integer DEFAULT 0 NOT NULL,
    retry_at timestamp(6) with time zone,
    created_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: root_account_events root_account_events_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.root_account_events
    ADD CONSTRAINT root_account_events_pkey PRIMARY KEY (id);


--
-- Name: root_account_exports root_account_exports_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.root_account_exports
    ADD CONSTRAINT root_account_exports_pkey PRIMARY KEY (id);


--
-- Name: root_account_products root_account_products_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.root_account_products
    ADD CONSTRAINT root_account_products_pkey PRIMARY KEY (account_id, product);


--
-- Name: root_account_schema_state root_account_schema_state_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.root_account_schema_state
    ADD CONSTRAINT root_account_schema_state_pkey PRIMARY KEY (account_id);


--
-- Name: root_accounts root_accounts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.root_accounts
    ADD CONSTRAINT root_accounts_pkey PRIMARY KEY (id);


--
-- Name: root_clusters root_clusters_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.root_clusters
    ADD CONSTRAINT root_clusters_name_unique UNIQUE (name);


--
-- Name: root_clusters root_clusters_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.root_clusters
    ADD CONSTRAINT root_clusters_pkey PRIMARY KEY (id);


--
-- Name: root_domains root_domains_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.root_domains
    ADD CONSTRAINT root_domains_pkey PRIMARY KEY (id);


--
-- Name: root_feature_flags root_feature_flags_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.root_feature_flags
    ADD CONSTRAINT root_feature_flags_pkey PRIMARY KEY (id);


--
-- Name: root_feature_flags root_feature_flags_uq; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.root_feature_flags
    ADD CONSTRAINT root_feature_flags_uq UNIQUE NULLS NOT DISTINCT (account_id, flag);


--
-- Name: root_identities root_identities_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.root_identities
    ADD CONSTRAINT root_identities_pkey PRIMARY KEY (id);


--
-- Name: root_identity_accounts root_identity_accounts_identity_id_account_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.root_identity_accounts
    ADD CONSTRAINT root_identity_accounts_identity_id_account_id_unique UNIQUE (identity_id, account_id);


--
-- Name: root_identity_accounts root_identity_accounts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.root_identity_accounts
    ADD CONSTRAINT root_identity_accounts_pkey PRIMARY KEY (id);


--
-- Name: root_idp_sessions root_idp_sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.root_idp_sessions
    ADD CONSTRAINT root_idp_sessions_pkey PRIMARY KEY (id);


--
-- Name: root_idp_sessions root_idp_sessions_token_hash_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.root_idp_sessions
    ADD CONSTRAINT root_idp_sessions_token_hash_unique UNIQUE (token_hash);


--
-- Name: root_impersonation_grants root_impersonation_grants_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.root_impersonation_grants
    ADD CONSTRAINT root_impersonation_grants_pkey PRIMARY KEY (id);


--
-- Name: root_migration_batches root_migration_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.root_migration_batches
    ADD CONSTRAINT root_migration_batches_pkey PRIMARY KEY (id);


--
-- Name: root_oauth_clients root_oauth_clients_client_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.root_oauth_clients
    ADD CONSTRAINT root_oauth_clients_client_id_unique UNIQUE (client_id);


--
-- Name: root_oauth_clients root_oauth_clients_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.root_oauth_clients
    ADD CONSTRAINT root_oauth_clients_pkey PRIMARY KEY (id);


--
-- Name: root_oauth_refresh_tokens root_oauth_refresh_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.root_oauth_refresh_tokens
    ADD CONSTRAINT root_oauth_refresh_tokens_pkey PRIMARY KEY (id);


--
-- Name: root_oauth_refresh_tokens root_oauth_refresh_tokens_token_hash_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.root_oauth_refresh_tokens
    ADD CONSTRAINT root_oauth_refresh_tokens_token_hash_unique UNIQUE (token_hash);


--
-- Name: root_templates root_templates_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.root_templates
    ADD CONSTRAINT root_templates_pkey PRIMARY KEY (id);


--
-- Name: root_templates root_templates_version_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.root_templates
    ADD CONSTRAINT root_templates_version_unique UNIQUE (version);


--
-- Name: root_usage_snapshots root_usage_snapshots_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.root_usage_snapshots
    ADD CONSTRAINT root_usage_snapshots_pkey PRIMARY KEY (account_id, metric, period);


--
-- Name: root_wakeup_hints root_wakeup_hints_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.root_wakeup_hints
    ADD CONSTRAINT root_wakeup_hints_pkey PRIMARY KEY (account_id);


--
-- Name: root_wakeup_reconciliation_state root_wakeup_reconciliation_state_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.root_wakeup_reconciliation_state
    ADD CONSTRAINT root_wakeup_reconciliation_state_pkey PRIMARY KEY (name);


--
-- Name: root_account_events_ix; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX root_account_events_ix ON public.root_account_events USING btree (account_id, created_at);


--
-- Name: root_accounts_cluster_ix; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX root_accounts_cluster_ix ON public.root_accounts USING btree (cluster_id) WHERE (deleted_at IS NULL);


--
-- Name: root_accounts_db_uq; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX root_accounts_db_uq ON public.root_accounts USING btree (db_name);


--
-- Name: root_accounts_purge_ix; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX root_accounts_purge_ix ON public.root_accounts USING btree (purge_after) WHERE (purge_after IS NOT NULL);


--
-- Name: root_accounts_signup_key_uq; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX root_accounts_signup_key_uq ON public.root_accounts USING btree (((data ->> 'signup_key'::text))) WHERE (((data ->> 'signup_key'::text) IS NOT NULL) AND (deleted_at IS NULL));


--
-- Name: root_accounts_slug_uq; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX root_accounts_slug_uq ON public.root_accounts USING btree (slug) WHERE ((slug IS NOT NULL) AND (deleted_at IS NULL));


--
-- Name: root_accounts_status_ix; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX root_accounts_status_ix ON public.root_accounts USING btree (status) WHERE (deleted_at IS NULL);


--
-- Name: root_domains_host_uq; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX root_domains_host_uq ON public.root_domains USING btree (lower((host)::text)) WHERE (deleted_at IS NULL);


--
-- Name: root_domains_primary_uq; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX root_domains_primary_uq ON public.root_domains USING btree (account_id) WHERE (is_primary AND (deleted_at IS NULL));


--
-- Name: root_identities_email_uq; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX root_identities_email_uq ON public.root_identities USING btree (lower((email)::text)) WHERE (deleted_at IS NULL);


--
-- Name: root_identity_accounts_acc_ix; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX root_identity_accounts_acc_ix ON public.root_identity_accounts USING btree (account_id);


--
-- Name: root_identity_accounts_sync_ix; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX root_identity_accounts_sync_ix ON public.root_identity_accounts USING btree (sync_status) WHERE ((sync_status)::text <> 'synced'::text);


--
-- Name: root_idp_sessions_identity_ix; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX root_idp_sessions_identity_ix ON public.root_idp_sessions USING btree (identity_id) WHERE (revoked_at IS NULL);


--
-- Name: root_impersonation_acc_ix; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX root_impersonation_acc_ix ON public.root_impersonation_grants USING btree (account_id, created_at);


--
-- Name: root_schema_state_bad_ix; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX root_schema_state_bad_ix ON public.root_account_schema_state USING btree (status) WHERE ((status)::text <> 'ok'::text);


--
-- Name: root_wakeup_hints_scan_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX root_wakeup_hints_scan_idx ON public.root_wakeup_hints USING btree (next_fire_at, leased_until);


--
-- Name: root_account_events root_account_events_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.root_account_events
    ADD CONSTRAINT root_account_events_account_id_foreign FOREIGN KEY (account_id) REFERENCES public.root_accounts(id) ON DELETE CASCADE;


--
-- Name: root_account_exports root_account_exports_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.root_account_exports
    ADD CONSTRAINT root_account_exports_account_id_foreign FOREIGN KEY (account_id) REFERENCES public.root_accounts(id) ON DELETE CASCADE;


--
-- Name: root_account_products root_account_products_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.root_account_products
    ADD CONSTRAINT root_account_products_account_id_foreign FOREIGN KEY (account_id) REFERENCES public.root_accounts(id) ON DELETE CASCADE;


--
-- Name: root_account_schema_state root_account_schema_state_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.root_account_schema_state
    ADD CONSTRAINT root_account_schema_state_account_id_foreign FOREIGN KEY (account_id) REFERENCES public.root_accounts(id) ON DELETE CASCADE;


--
-- Name: root_account_schema_state root_account_schema_state_batch_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.root_account_schema_state
    ADD CONSTRAINT root_account_schema_state_batch_id_foreign FOREIGN KEY (batch_id) REFERENCES public.root_migration_batches(id) ON DELETE SET NULL;


--
-- Name: root_accounts root_accounts_cluster_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.root_accounts
    ADD CONSTRAINT root_accounts_cluster_id_foreign FOREIGN KEY (cluster_id) REFERENCES public.root_clusters(id) ON DELETE RESTRICT;


--
-- Name: root_accounts root_accounts_owner_identity_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.root_accounts
    ADD CONSTRAINT root_accounts_owner_identity_id_foreign FOREIGN KEY (owner_identity_id) REFERENCES public.root_identities(id) ON DELETE RESTRICT;


--
-- Name: root_domains root_domains_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.root_domains
    ADD CONSTRAINT root_domains_account_id_foreign FOREIGN KEY (account_id) REFERENCES public.root_accounts(id) ON DELETE CASCADE;


--
-- Name: root_feature_flags root_feature_flags_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.root_feature_flags
    ADD CONSTRAINT root_feature_flags_account_id_foreign FOREIGN KEY (account_id) REFERENCES public.root_accounts(id) ON DELETE CASCADE;


--
-- Name: root_identity_accounts root_identity_accounts_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.root_identity_accounts
    ADD CONSTRAINT root_identity_accounts_account_id_foreign FOREIGN KEY (account_id) REFERENCES public.root_accounts(id) ON DELETE CASCADE;


--
-- Name: root_identity_accounts root_identity_accounts_identity_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.root_identity_accounts
    ADD CONSTRAINT root_identity_accounts_identity_id_foreign FOREIGN KEY (identity_id) REFERENCES public.root_identities(id) ON DELETE CASCADE;


--
-- Name: root_idp_sessions root_idp_sessions_identity_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.root_idp_sessions
    ADD CONSTRAINT root_idp_sessions_identity_id_foreign FOREIGN KEY (identity_id) REFERENCES public.root_identities(id) ON DELETE CASCADE;


--
-- Name: root_impersonation_grants root_impersonation_grants_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.root_impersonation_grants
    ADD CONSTRAINT root_impersonation_grants_account_id_foreign FOREIGN KEY (account_id) REFERENCES public.root_accounts(id) ON DELETE CASCADE;


--
-- Name: root_impersonation_grants root_impersonation_grants_approved_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.root_impersonation_grants
    ADD CONSTRAINT root_impersonation_grants_approved_by_foreign FOREIGN KEY (approved_by) REFERENCES public.root_identities(id) ON DELETE SET NULL;


--
-- Name: root_impersonation_grants root_impersonation_grants_staff_identity_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.root_impersonation_grants
    ADD CONSTRAINT root_impersonation_grants_staff_identity_id_foreign FOREIGN KEY (staff_identity_id) REFERENCES public.root_identities(id) ON DELETE CASCADE;


--
-- Name: root_oauth_refresh_tokens root_oauth_refresh_tokens_session_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.root_oauth_refresh_tokens
    ADD CONSTRAINT root_oauth_refresh_tokens_session_id_foreign FOREIGN KEY (session_id) REFERENCES public.root_idp_sessions(id) ON DELETE CASCADE;


--
-- Name: root_usage_snapshots root_usage_snapshots_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.root_usage_snapshots
    ADD CONSTRAINT root_usage_snapshots_account_id_foreign FOREIGN KEY (account_id) REFERENCES public.root_accounts(id) ON DELETE CASCADE;


--
-- PostgreSQL database dump complete
--

\unrestrict e9RIAibw9OIdPvG7TxzUfnQlJIEvdXSdJSMUUKE2xqQrKOubDmoBDxaVNep5Ipp


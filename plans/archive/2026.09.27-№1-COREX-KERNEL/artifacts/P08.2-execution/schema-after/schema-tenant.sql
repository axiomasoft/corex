--
-- PostgreSQL database dump
--

\restrict qjLyk9myM6vUn9ARXYyPcQevYjeaXJTJOpKPjzRcdeGPYHhqXasPrkVvhyOA4qf

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

--
-- Name: ltree; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS ltree WITH SCHEMA public;


SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: aut_azguard_role_links; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.aut_azguard_role_links (
    role_id uuid NOT NULL,
    azguard_role_id bigint NOT NULL
);


--
-- Name: aut_direct_grants; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.aut_direct_grants (
    id uuid NOT NULL,
    principal_type character varying(32) NOT NULL,
    principal_id uuid NOT NULL,
    permission_key character varying(255) NOT NULL,
    expires_at timestamp(6) with time zone,
    revoked_at timestamp(6) with time zone,
    created_at timestamp(6) with time zone,
    updated_at timestamp(6) with time zone
);


--
-- Name: aut_role_assignments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.aut_role_assignments (
    id uuid NOT NULL,
    role_id uuid NOT NULL,
    principal_type character varying(16) NOT NULL,
    principal_id uuid NOT NULL,
    workspace_id uuid,
    granted_by uuid,
    expires_at timestamp(6) with time zone,
    created_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT aut_role_assignments_principal_type_ck CHECK (((principal_type)::text = ANY ((ARRAY['user'::character varying, 'agent'::character varying, 'api_key'::character varying])::text[])))
);


--
-- Name: aut_role_matrix; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.aut_role_matrix (
    id uuid NOT NULL,
    role_id uuid NOT NULL,
    entity_key character varying(64) NOT NULL,
    action character varying(16) NOT NULL,
    scope character varying(16) NOT NULL,
    context_type character varying(32),
    context_id uuid,
    created_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT aut_role_matrix_action_ck CHECK (((action)::text = ANY ((ARRAY['read'::character varying, 'add'::character varying, 'update'::character varying, 'delete'::character varying, 'export'::character varying, 'import'::character varying])::text[]))),
    CONSTRAINT aut_role_matrix_context_ck CHECK (((context_type IS NULL) = (context_id IS NULL))),
    CONSTRAINT aut_role_matrix_scope_ck CHECK (((scope)::text = ANY ((ARRAY['none'::character varying, 'own'::character varying, 'dept'::character varying, 'dept_tree'::character varying, 'workspace'::character varying, 'account'::character varying])::text[])))
);


--
-- Name: aut_roles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.aut_roles (
    id uuid NOT NULL,
    slug character varying(64) NOT NULL,
    name character varying(128) NOT NULL,
    description text,
    is_system boolean DEFAULT false NOT NULL,
    cloned_from_id uuid,
    assignable_to jsonb DEFAULT '["user"]'::jsonb NOT NULL,
    created_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    deleted_at timestamp(6) with time zone
);


--
-- Name: corex_export_manifests; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.corex_export_manifests (
    id uuid NOT NULL,
    job_id uuid NOT NULL,
    capture_attempt character(64) NOT NULL,
    schema_hash character(64) NOT NULL,
    restore_epoch character(64) NOT NULL,
    roster jsonb NOT NULL,
    parts_hash character(64),
    snapshot_mode character varying(24) NOT NULL,
    state character varying(24) NOT NULL
);


--
-- Name: corex_export_parts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.corex_export_parts (
    id uuid NOT NULL,
    manifest_id uuid NOT NULL,
    part_no numeric(20,0) NOT NULL,
    immutable_ref character varying(1024) NOT NULL,
    hash character(64) NOT NULL,
    byte_length numeric(20,0) NOT NULL,
    row_counts jsonb NOT NULL,
    state character varying(24) NOT NULL
);


--
-- Name: corex_rate_batches; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.corex_rate_batches (
    id character(26) NOT NULL,
    operation_key character(64) NOT NULL,
    payload_hash character(64) NOT NULL,
    format_hash character(64) NOT NULL,
    source_manifest json NOT NULL,
    generation numeric(20,0) DEFAULT '0'::numeric NOT NULL,
    state character varying(16) NOT NULL,
    created_at timestamp(0) with time zone NOT NULL,
    CONSTRAINT corex_rate_batches_format_hash_chk CHECK ((format_hash ~ '^[a-f0-9]{64}$'::text)),
    CONSTRAINT corex_rate_batches_generation_chk CHECK (((generation >= (0)::numeric) AND (generation <= '18446744073709551615'::numeric))),
    CONSTRAINT corex_rate_batches_operation_key_chk CHECK ((operation_key ~ '^[a-f0-9]{64}$'::text)),
    CONSTRAINT corex_rate_batches_payload_hash_chk CHECK ((payload_hash ~ '^[a-f0-9]{64}$'::text)),
    CONSTRAINT corex_rate_batches_state_chk CHECK (((state)::text = ANY ((ARRAY['staged'::character varying, 'published'::character varying, 'conflict'::character varying, 'failed'::character varying])::text[])))
);


--
-- Name: corex_rate_current; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.corex_rate_current (
    base character varying(3) NOT NULL,
    quote character varying(3) NOT NULL,
    source character varying(128) NOT NULL,
    snapshot_id character(26) NOT NULL,
    generation numeric(20,0) DEFAULT '0'::numeric NOT NULL,
    CONSTRAINT corex_rate_current_generation_chk CHECK (((generation >= (0)::numeric) AND (generation <= '18446744073709551615'::numeric)))
);


--
-- Name: corex_rate_snapshots; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.corex_rate_snapshots (
    id character(26) NOT NULL,
    schema_version character varying(32) NOT NULL,
    base character varying(3) NOT NULL,
    quote character varying(3) NOT NULL,
    rate_decimal character varying(64) NOT NULL,
    valid_on character varying(10) NOT NULL,
    observed_at timestamp(0) with time zone NOT NULL,
    source character varying(128) NOT NULL,
    publication_id character varying(128) NOT NULL,
    publication_revision character varying(128) NOT NULL,
    payload_hash character(64) NOT NULL,
    CONSTRAINT corex_rate_snapshots_payload_hash_chk CHECK ((payload_hash ~ '^[a-f0-9]{64}$'::text))
);


--
-- Name: corex_reference_aliases; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.corex_reference_aliases (
    snapshot_id character varying(128) NOT NULL,
    namespace character varying(128) NOT NULL,
    source_id character varying(255) NOT NULL,
    locale character varying(128) NOT NULL,
    alias character varying(255) NOT NULL,
    normalized_alias character varying(255) NOT NULL
);


--
-- Name: corex_reference_current; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.corex_reference_current (
    dataset_key character varying(128) NOT NULL,
    snapshot_id character varying(128) NOT NULL,
    generation numeric(20,0) DEFAULT '0'::numeric NOT NULL,
    CONSTRAINT corex_reference_current_generation_chk CHECK (((generation >= (0)::numeric) AND (generation <= '18446744073709551615'::numeric)))
);


--
-- Name: corex_reference_datasets; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.corex_reference_datasets (
    snapshot_id character varying(128) NOT NULL,
    manifest json NOT NULL,
    raw_hash character(64) NOT NULL,
    normalized_hash character(64) NOT NULL,
    state character varying(16) NOT NULL,
    created_at timestamp(0) with time zone NOT NULL,
    CONSTRAINT corex_reference_datasets_normalized_hash_chk CHECK ((normalized_hash ~ '^[a-f0-9]{64}$'::text)),
    CONSTRAINT corex_reference_datasets_raw_hash_chk CHECK ((raw_hash ~ '^[a-f0-9]{64}$'::text)),
    CONSTRAINT corex_reference_datasets_state_chk CHECK (((state)::text = ANY ((ARRAY['staged'::character varying, 'published'::character varying, 'retired'::character varying])::text[])))
);


--
-- Name: corex_reference_records; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.corex_reference_records (
    snapshot_id character varying(128) NOT NULL,
    namespace character varying(128) NOT NULL,
    source_id character varying(255) NOT NULL,
    kind character varying(64) NOT NULL,
    parent_namespace character varying(128),
    parent_source_id character varying(255),
    names json NOT NULL,
    normalized_name character varying(255) NOT NULL,
    country_key json,
    coordinates json,
    timezones json NOT NULL,
    provenance json NOT NULL,
    CONSTRAINT corex_reference_records_parent_chk CHECK (((parent_namespace IS NULL) = (parent_source_id IS NULL)))
);


--
-- Name: corex_search_dirty; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.corex_search_dirty (
    sequence bigint NOT NULL,
    lane_key character varying(128) NOT NULL,
    record_id character varying(128) NOT NULL,
    revision bigint NOT NULL,
    tombstone boolean DEFAULT false NOT NULL,
    restore_epoch character varying(64) NOT NULL,
    schema_hash character(64),
    payload_hash character(64),
    submitted_at timestamp(6) with time zone,
    completed_at timestamp(6) with time zone
);


--
-- Name: corex_search_dirty_sequence_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.corex_search_dirty_sequence_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: corex_search_dirty_sequence_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.corex_search_dirty_sequence_seq OWNED BY public.corex_search_dirty.sequence;


--
-- Name: corex_search_lanes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.corex_search_lanes (
    lane_key character varying(128) NOT NULL,
    generation character varying(128) NOT NULL,
    committed_revision bigint DEFAULT '0'::bigint NOT NULL,
    restore_epoch character varying(64) NOT NULL,
    state character varying(24) DEFAULT 'ready'::character varying NOT NULL,
    claim_epoch bigint DEFAULT '0'::bigint NOT NULL,
    active_task character varying(255),
    request_hash character(64),
    created_at timestamp(6) with time zone,
    updated_at timestamp(6) with time zone
);


--
-- Name: corex_search_rebuilds; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.corex_search_rebuilds (
    rebuild_id character varying(128) NOT NULL,
    lane_key character varying(128) NOT NULL,
    from_generation character varying(128) NOT NULL,
    to_generation character varying(128) NOT NULL,
    restore_epoch character varying(64) NOT NULL,
    schema_hash character(64) NOT NULL,
    fence_revision bigint NOT NULL,
    state character varying(24) DEFAULT 'pending'::character varying NOT NULL,
    swap_task character varying(255),
    marker_hash character(64),
    swapped_at timestamp(6) with time zone,
    created_at timestamp(6) with time zone,
    updated_at timestamp(6) with time zone
);


--
-- Name: corex_task_occurrences; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.corex_task_occurrences (
    id uuid NOT NULL,
    series_key character varying(128) NOT NULL,
    immutable_revision character varying(128) NOT NULL,
    occurrence_key character(64) NOT NULL,
    original_identity json NOT NULL,
    effective_at character varying(128) NOT NULL,
    task_ref json,
    operation_key character varying(128) NOT NULL,
    state character varying(24) NOT NULL,
    CONSTRAINT corex_task_occurrences_occurrence_key_chk CHECK ((occurrence_key ~ '^[a-f0-9]{64}$'::text)),
    CONSTRAINT corex_task_occurrences_state_chk CHECK (((state)::text = ANY ((ARRAY['pending'::character varying, 'committed'::character varying, 'cancelled'::character varying, 'retired'::character varying, 'unknown'::character varying])::text[])))
);


--
-- Name: corex_task_operations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.corex_task_operations (
    id uuid NOT NULL,
    request_key character varying(128) NOT NULL,
    payload_hash character(64) NOT NULL,
    kind character varying(32) NOT NULL,
    result_ref json NOT NULL,
    restore_epoch character(64) NOT NULL,
    created_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT corex_task_operations_kind_chk CHECK (((kind)::text = ANY ((ARRAY['createProject'::character varying, 'createSprint'::character varying, 'recordTime'::character varying, 'materialize'::character varying, 'completionCapture'::character varying])::text[]))),
    CONSTRAINT corex_task_operations_payload_hash_chk CHECK ((payload_hash ~ '^[a-f0-9]{64}$'::text)),
    CONSTRAINT corex_task_operations_restore_epoch_chk CHECK ((restore_epoch ~ '^[a-f0-9]{64}$'::text))
);


--
-- Name: corex_task_series; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.corex_task_series (
    id uuid NOT NULL,
    series_key character varying(128) NOT NULL,
    immutable_revision character varying(128) NOT NULL,
    definition_hash character(64) NOT NULL,
    definition json NOT NULL,
    template json NOT NULL,
    actor json NOT NULL,
    context_hash character(64) NOT NULL,
    restore_epoch character(64) NOT NULL,
    CONSTRAINT corex_task_series_context_hash_chk CHECK ((context_hash ~ '^[a-f0-9]{64}$'::text)),
    CONSTRAINT corex_task_series_definition_hash_chk CHECK ((definition_hash ~ '^[a-f0-9]{64}$'::text)),
    CONSTRAINT corex_task_series_restore_epoch_chk CHECK ((restore_epoch ~ '^[a-f0-9]{64}$'::text))
);


--
-- Name: corex_task_series_state; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.corex_task_series_state (
    series_key character varying(128) NOT NULL,
    active_revision character varying(128) NOT NULL,
    retired boolean DEFAULT false NOT NULL,
    complete_through character varying(128),
    cursor json,
    restore_epoch character(64) NOT NULL,
    generation numeric(20,0) DEFAULT '0'::numeric NOT NULL,
    CONSTRAINT corex_task_series_state_generation_chk CHECK (((generation >= (0)::numeric) AND (generation <= '18446744073709551615'::numeric))),
    CONSTRAINT corex_task_series_state_restore_epoch_chk CHECK ((restore_epoch ~ '^[a-f0-9]{64}$'::text)),
    CONSTRAINT corex_task_series_state_retired_chk CHECK ((retired = ANY (ARRAY[true, false])))
);


--
-- Name: corex_transfer_jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.corex_transfer_jobs (
    id uuid NOT NULL,
    source_manifest jsonb NOT NULL,
    source_hash character(64) NOT NULL,
    mapping_snapshot jsonb NOT NULL,
    mapping_hash character(64) NOT NULL,
    schema_hash character(64) NOT NULL,
    restore_epoch character(64) NOT NULL,
    mode character varying(24) NOT NULL,
    state character varying(24) NOT NULL,
    checkpoint_seq numeric(20,0) DEFAULT '0'::numeric NOT NULL,
    cursor jsonb NOT NULL,
    stats jsonb NOT NULL,
    claim_epoch numeric(20,0) DEFAULT '0'::numeric NOT NULL,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone
);


--
-- Name: corex_transfer_outbox; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.corex_transfer_outbox (
    id uuid NOT NULL,
    job_id uuid NOT NULL,
    operation_key character(64) NOT NULL,
    payload_hash character(64) NOT NULL,
    request jsonb NOT NULL,
    state character varying(24) NOT NULL,
    claim_epoch numeric(20,0) DEFAULT '0'::numeric NOT NULL,
    provider_task character varying(255),
    dedup_deadline timestamp(0) with time zone,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone
);


--
-- Name: corex_transfer_refs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.corex_transfer_refs (
    id uuid NOT NULL,
    job_id uuid NOT NULL,
    entity character varying(128) NOT NULL,
    external_system character varying(128) NOT NULL,
    external_id character varying(255) NOT NULL,
    target_id character varying(255),
    source_revision character varying(255) NOT NULL,
    source_updated_at timestamp(0) with time zone,
    payload_hash character(64) NOT NULL,
    state character varying(24) NOT NULL,
    result jsonb,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone
);


--
-- Name: corex_transfer_rows; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.corex_transfer_rows (
    id uuid NOT NULL,
    job_id uuid NOT NULL,
    ordinal numeric(20,0) NOT NULL,
    external_identity_hash character(64) NOT NULL,
    operation_key character(64) NOT NULL,
    payload_hash character(64) NOT NULL,
    result character varying(24) NOT NULL,
    error jsonb,
    request jsonb,
    provider_task character varying(255),
    epoch numeric(20,0) NOT NULL
);


--
-- Name: crm_activities; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.crm_activities (
    id uuid NOT NULL,
    workspace_id uuid,
    kind character varying(16) NOT NULL,
    status character varying(16) DEFAULT 'planned'::character varying NOT NULL,
    subject character varying(255),
    body text,
    direction character varying(3),
    phone character varying(64),
    duration_sec integer,
    location character varying(255),
    email_meta jsonb,
    recording_file_id uuid,
    transcript text,
    starts_at timestamp with time zone,
    ends_at timestamp with time zone,
    owner_id uuid,
    created_by jsonb NOT NULL,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    deleted_at timestamp with time zone,
    CONSTRAINT crm_activities_parity_0_ck CHECK (((kind)::text = ANY ((ARRAY['call'::character varying, 'email'::character varying, 'meeting'::character varying])::text[]))),
    CONSTRAINT crm_activities_parity_1_ck CHECK (((status)::text = ANY ((ARRAY['planned'::character varying, 'done'::character varying, 'canceled'::character varying, 'missed'::character varying])::text[]))),
    CONSTRAINT crm_activities_parity_2_ck CHECK (((direction)::text = ANY ((ARRAY['in'::character varying, 'out'::character varying])::text[]))),
    CONSTRAINT crm_activities_parity_3_ck CHECK ((((kind)::text = 'call'::text) OR (direction IS NULL) OR ((kind)::text = 'email'::text)))
);


--
-- Name: crm_activity_targets; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.crm_activity_targets (
    activity_id uuid NOT NULL,
    entity_type character varying(64) NOT NULL,
    entity_id uuid NOT NULL,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: crm_companies; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.crm_companies (
    id uuid NOT NULL,
    workspace_id uuid,
    name character varying(255) NOT NULL,
    legal_name character varying(255),
    domain character varying(190),
    inn character varying(12),
    kpp character varying(9),
    ogrn character varying(15),
    phones jsonb DEFAULT '[]'::jsonb NOT NULL,
    emails jsonb DEFAULT '[]'::jsonb NOT NULL,
    address jsonb,
    bank_details jsonb,
    source_id uuid,
    owner_id uuid,
    created_by jsonb NOT NULL,
    search tsvector,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    deleted_at timestamp with time zone
);


--
-- Name: crm_contact_company; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.crm_contact_company (
    id uuid NOT NULL,
    contact_id uuid NOT NULL,
    company_id uuid NOT NULL,
    label character varying(64),
    is_primary boolean DEFAULT false NOT NULL,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: crm_contacts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.crm_contacts (
    id uuid NOT NULL,
    workspace_id uuid,
    name_last character varying(120),
    name_first character varying(120),
    name_middle character varying(120),
    display_name character varying(255) NOT NULL,
    job_title character varying(190),
    phones jsonb DEFAULT '[]'::jsonb NOT NULL,
    emails jsonb DEFAULT '[]'::jsonb NOT NULL,
    birthday date,
    source_id uuid,
    owner_id uuid,
    created_by jsonb NOT NULL,
    search tsvector,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    deleted_at timestamp with time zone
);


--
-- Name: crm_deal_contacts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.crm_deal_contacts (
    id uuid NOT NULL,
    deal_id uuid NOT NULL,
    contact_id uuid NOT NULL,
    label character varying(64),
    is_primary boolean DEFAULT false NOT NULL,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: crm_deals; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.crm_deals (
    id uuid NOT NULL,
    workspace_id uuid,
    title character varying(255) NOT NULL,
    status character varying(8) DEFAULT 'open'::character varying NOT NULL,
    pipeline_id uuid NOT NULL,
    stage_id uuid NOT NULL,
    amount bigint,
    currency character(3) DEFAULT 'RUB'::bpchar NOT NULL,
    close_date date,
    won_at timestamp with time zone,
    lost_at timestamp with time zone,
    lost_reason_id uuid,
    company_id uuid,
    source_id uuid,
    utm jsonb,
    owner_id uuid,
    created_by jsonb NOT NULL,
    primary_quote_doc_id uuid,
    forecast_category character varying(16),
    forecast_category_is_manual boolean DEFAULT false NOT NULL,
    stage_entered_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    "position" numeric(20,10) DEFAULT 0 NOT NULL,
    search tsvector,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    deleted_at timestamp with time zone,
    CONSTRAINT crm_deals_parity_0_ck CHECK (((status)::text = ANY ((ARRAY['open'::character varying, 'won'::character varying, 'lost'::character varying])::text[]))),
    CONSTRAINT crm_deals_parity_1_ck CHECK ((((status)::text <> 'won'::text) OR (won_at IS NOT NULL))),
    CONSTRAINT crm_deals_parity_2_ck CHECK ((((status)::text <> 'lost'::text) OR (lost_at IS NOT NULL))),
    CONSTRAINT crm_deals_parity_3_ck CHECK (((forecast_category)::text = ANY ((ARRAY['pipeline'::character varying, 'best_case'::character varying, 'commit'::character varying, 'omitted'::character varying])::text[])))
);


--
-- Name: crm_leads; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.crm_leads (
    id uuid NOT NULL,
    workspace_id uuid,
    title character varying(255) NOT NULL,
    status character varying(16) DEFAULT 'open'::character varying NOT NULL,
    pipeline_id uuid,
    stage_id uuid,
    contact_name character varying(255),
    phone character varying(64),
    email character varying(255),
    company_name character varying(255),
    amount bigint,
    currency character(3) DEFAULT 'RUB'::bpchar NOT NULL,
    source_id uuid,
    utm jsonb,
    lost_reason_id uuid,
    owner_id uuid,
    created_by jsonb NOT NULL,
    converted_at timestamp with time zone,
    converted_deal_id uuid,
    converted_contact_id uuid,
    converted_company_id uuid,
    "position" numeric(20,10) DEFAULT 0 NOT NULL,
    search tsvector,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    deleted_at timestamp with time zone,
    CONSTRAINT crm_leads_parity_0_ck CHECK (((status)::text = ANY ((ARRAY['open'::character varying, 'converted'::character varying, 'junk'::character varying])::text[]))),
    CONSTRAINT crm_leads_parity_1_ck CHECK ((((status)::text <> 'converted'::text) OR (converted_at IS NOT NULL)))
);


--
-- Name: crm_lost_reasons; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.crm_lost_reasons (
    id uuid NOT NULL,
    workspace_id uuid,
    code character varying(64) NOT NULL,
    name jsonb NOT NULL,
    entity_type character varying(16) DEFAULT 'both'::character varying NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    "position" integer DEFAULT 0 NOT NULL,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT crm_lr_entity_ck CHECK (((entity_type)::text = ANY ((ARRAY['lead'::character varying, 'deal'::character varying, 'both'::character varying])::text[])))
);


--
-- Name: crm_pipelines; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.crm_pipelines (
    id uuid NOT NULL,
    workspace_id uuid,
    entity_type character varying(64) NOT NULL,
    code character varying(64) NOT NULL,
    name jsonb NOT NULL,
    is_default boolean DEFAULT false NOT NULL,
    "position" integer DEFAULT 0 NOT NULL,
    settings jsonb DEFAULT '{}'::jsonb NOT NULL,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    deleted_at timestamp with time zone
);


--
-- Name: crm_sources; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.crm_sources (
    id uuid NOT NULL,
    workspace_id uuid,
    code character varying(64) NOT NULL,
    name jsonb NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    "position" integer DEFAULT 0 NOT NULL,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: crm_stages; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.crm_stages (
    id uuid NOT NULL,
    pipeline_id uuid NOT NULL,
    code character varying(64) NOT NULL,
    name jsonb NOT NULL,
    color character varying(16),
    "position" integer DEFAULT 0 NOT NULL,
    semantic character varying(8) DEFAULT 'open'::character varying NOT NULL,
    probability smallint DEFAULT 0 NOT NULL,
    required_fields jsonb DEFAULT '[]'::jsonb NOT NULL,
    settings jsonb DEFAULT '{}'::jsonb NOT NULL,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    deleted_at timestamp with time zone,
    CONSTRAINT crm_stages_parity_0_ck CHECK (((semantic)::text = ANY ((ARRAY['open'::character varying, 'won'::character varying, 'lost'::character varying])::text[]))),
    CONSTRAINT crm_stages_parity_1_ck CHECK (((probability >= 0) AND (probability <= 100)))
);


--
-- Name: med_conversions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.med_conversions (
    id bigint NOT NULL,
    file_id uuid NOT NULL,
    profile_key character varying(128) NOT NULL,
    profile_digest character(64) NOT NULL,
    source_version bigint NOT NULL,
    state character varying(16) NOT NULL,
    object_key character varying(255),
    mime_type character varying(255),
    size_bytes bigint,
    width integer,
    height integer,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: med_conversions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.med_conversions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: med_conversions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.med_conversions_id_seq OWNED BY public.med_conversions.id;


--
-- Name: med_files; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.med_files (
    id uuid NOT NULL,
    request_key character varying(128) NOT NULL,
    request_digest character(64) NOT NULL,
    state character varying(16) NOT NULL,
    object_key character varying(255) NOT NULL,
    mime_type character varying(255) NOT NULL,
    kind character varying(128) NOT NULL,
    size_bytes bigint NOT NULL,
    sha256 character(64) NOT NULL,
    uploaded_actor_kind character varying(64) NOT NULL,
    uploaded_actor_id character varying(128),
    workspace_scope_key character varying(128),
    version bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: med_operations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.med_operations (
    id bigint NOT NULL,
    file_id uuid NOT NULL,
    operation_key character varying(128) NOT NULL,
    kind character varying(32) NOT NULL,
    state character varying(16) NOT NULL,
    profile_key character varying(128),
    profile_digest character(64),
    source_version bigint,
    claim_epoch bigint DEFAULT '0'::bigint NOT NULL,
    attempts integer DEFAULT 0 NOT NULL,
    next_attempt_at timestamp(0) without time zone,
    lease_until timestamp(0) without time zone,
    attempt_object_key character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: med_operations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.med_operations_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: med_operations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.med_operations_id_seq OWNED BY public.med_operations.id;


--
-- Name: med_relations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.med_relations (
    id bigint NOT NULL,
    file_id uuid NOT NULL,
    entity_handle character varying(128) NOT NULL,
    entity_id character varying(128) NOT NULL,
    slot character varying(64) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: med_relations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.med_relations_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: med_relations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.med_relations_id_seq OWNED BY public.med_relations.id;


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
-- Name: mod_lifecycle_log; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.mod_lifecycle_log (
    id uuid NOT NULL,
    module_name character varying(190) NOT NULL,
    operation character varying(16) NOT NULL,
    from_state character varying(16) NOT NULL,
    to_state character varying(16) NOT NULL,
    status character varying(16) DEFAULT 'success'::character varying NOT NULL,
    actor_type character varying(16) NOT NULL,
    actor_id uuid,
    details jsonb DEFAULT '{}'::jsonb NOT NULL,
    created_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT mod_ll_actor_ck CHECK (((actor_type)::text = ANY ((ARRAY['user'::character varying, 'system'::character varying, 'platform'::character varying, 'cli'::character varying, 'support'::character varying])::text[]))),
    CONSTRAINT mod_ll_op_ck CHECK (((operation)::text = ANY ((ARRAY['enable'::character varying, 'disable'::character varying, 'upgrade'::character varying, 'archive'::character varying, 'purge'::character varying])::text[]))),
    CONSTRAINT mod_ll_status_ck CHECK (((status)::text = ANY ((ARRAY['success'::character varying, 'failed'::character varying, 'started'::character varying])::text[])))
);


--
-- Name: mod_modules; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.mod_modules (
    id uuid NOT NULL,
    name character varying(190) NOT NULL,
    state character varying(16) NOT NULL,
    version character varying(32) NOT NULL,
    schema_version character varying(64),
    edition character varying(16) DEFAULT 'standard'::character varying NOT NULL,
    enabled_at timestamp(6) with time zone,
    disabled_at timestamp(6) with time zone,
    disable_reason character varying(16),
    config jsonb DEFAULT '{}'::jsonb NOT NULL,
    meta jsonb DEFAULT '{}'::jsonb NOT NULL,
    created_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT mod_modules_edition_ck CHECK (((edition)::text = ANY ((ARRAY['standard'::character varying, 'pro'::character varying])::text[]))),
    CONSTRAINT mod_modules_reason_ck CHECK ((((disable_reason)::text = ANY ((ARRAY['manual'::character varying, 'billing'::character varying, 'dependency'::character varying])::text[])) OR (disable_reason IS NULL))),
    CONSTRAINT mod_modules_state_ck CHECK (((state)::text = ANY ((ARRAY['enabled'::character varying, 'disabled'::character varying, 'archived'::character varying, 'purged'::character varying])::text[])))
);


--
-- Name: mod_records; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.mod_records (
    module_name character varying(190) NOT NULL,
    record_type character varying(40) NOT NULL,
    reference character varying(255) NOT NULL,
    meta jsonb DEFAULT '{}'::jsonb NOT NULL,
    created_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    seq bigint NOT NULL,
    CONSTRAINT mod_records_type_ck CHECK (((record_type)::text = ANY ((ARRAY['table'::character varying, 'column'::character varying, 'index'::character varying, 'role'::character varying, 'permission'::character varying, 'menu'::character varying, 'setting'::character varying, 'seed'::character varying, 'blueprint'::character varying, 'feature_flag'::character varying, 'other'::character varying])::text[])))
);


--
-- Name: mod_records_seq_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.mod_records_seq_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: mod_records_seq_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.mod_records_seq_seq OWNED BY public.mod_records.seq;


--
-- Name: ntf_deliveries; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ntf_deliveries (
    id uuid NOT NULL,
    notification_id uuid,
    digest_run_id uuid,
    channel character varying(64) NOT NULL,
    delivery_key character(64) NOT NULL,
    state character varying(16) NOT NULL,
    claim_epoch numeric(20,0) NOT NULL,
    attempts integer NOT NULL,
    next_attempt timestamp(0) with time zone,
    lease_until timestamp(0) with time zone,
    admitted_revision numeric(20,0) NOT NULL,
    provider_ref character varying(255),
    error json,
    sent_at timestamp(0) with time zone,
    message json,
    message_hash character(64),
    template_version character varying(128),
    policy_version character varying(128),
    driver_version character varying(128)
);


--
-- Name: ntf_digest_items; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ntf_digest_items (
    id uuid NOT NULL,
    run_id uuid NOT NULL,
    notification_id uuid NOT NULL,
    channel character varying(64) NOT NULL
);


--
-- Name: ntf_digest_runs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ntf_digest_runs (
    id uuid NOT NULL,
    recipient_kind character varying(64) NOT NULL,
    recipient_id character varying(128) NOT NULL,
    period character varying(16) NOT NULL,
    period_start timestamp(0) with time zone NOT NULL,
    period_end timestamp(0) with time zone NOT NULL,
    batch_seq integer NOT NULL,
    membership_hash character(64) NOT NULL,
    state character varying(16) NOT NULL,
    created_at timestamp(0) with time zone NOT NULL
);


--
-- Name: ntf_notifications; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ntf_notifications (
    id uuid NOT NULL,
    recipient_kind character varying(64) NOT NULL,
    recipient_id character varying(128) NOT NULL,
    request_key character varying(128) NOT NULL,
    request_digest character(64) NOT NULL,
    event_type character varying(128) NOT NULL,
    template_key character varying(128) NOT NULL,
    payload json NOT NULL,
    related json,
    read_at timestamp(0) with time zone,
    dismissed_at timestamp(0) with time zone,
    cancelled_at timestamp(0) with time zone,
    revision numeric(20,0) NOT NULL,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone
);


--
-- Name: ntf_recipient_state; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ntf_recipient_state (
    kind character varying(64) NOT NULL,
    id character varying(128) NOT NULL,
    preference_revision numeric(20,0) NOT NULL
);


--
-- Name: sessions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sessions (
    id character varying(255) NOT NULL,
    user_id uuid,
    ip_address character varying(45),
    user_agent text,
    payload text NOT NULL,
    last_activity integer NOT NULL
);


--
-- Name: sys_api_bulk_operations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sys_api_bulk_operations (
    id character varying(64) NOT NULL,
    major character varying(32) NOT NULL,
    operation_id character varying(191) NOT NULL,
    submission_id character varying(64) NOT NULL,
    scope json NOT NULL,
    actor_id character varying(191) NOT NULL,
    client_id character varying(191) NOT NULL,
    contract_hash character(64) NOT NULL,
    extension_stamp json NOT NULL,
    input_ref json NOT NULL,
    transfer_ref json,
    status character varying(16) NOT NULL,
    terminal_status character varying(16),
    revision bigint NOT NULL,
    lease_owner character varying(64),
    lease_until timestamp(6) with time zone,
    cancel_requested_at timestamp(6) with time zone,
    checkpoint json NOT NULL,
    progress json NOT NULL,
    result_ref json,
    error json,
    attempt integer NOT NULL,
    next_attempt_at timestamp(6) with time zone NOT NULL,
    created_at timestamp(6) with time zone NOT NULL,
    finished_at timestamp(6) with time zone,
    expires_at timestamp(6) with time zone,
    CONSTRAINT bulk_operations_counts CHECK (((revision >= 0) AND (attempt >= 0))),
    CONSTRAINT bulk_operations_status CHECK (((status)::text = ANY ((ARRAY['pending'::character varying, 'running'::character varying, 'cancelling'::character varying, 'cancelled'::character varying, 'completed'::character varying, 'failed'::character varying, 'expired'::character varying])::text[]))),
    CONSTRAINT bulk_operations_terminal_status CHECK (((terminal_status IS NULL) OR ((terminal_status)::text = ANY ((ARRAY['cancelled'::character varying, 'completed'::character varying, 'failed'::character varying])::text[]))))
);


--
-- Name: sys_api_idempotency; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sys_api_idempotency (
    scope_hash character(64) NOT NULL,
    key_hash character(64) NOT NULL,
    request_fingerprint character(64) NOT NULL,
    state character varying(16) NOT NULL,
    attempt integer NOT NULL,
    lease_token uuid,
    lease_until timestamp(6) with time zone,
    outcome_json json,
    contract_hash character(64) NOT NULL,
    expires_at timestamp(6) with time zone,
    created_at timestamp(6) with time zone NOT NULL,
    updated_at timestamp(6) with time zone NOT NULL
);


--
-- Name: sys_api_webhook_attempts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sys_api_webhook_attempts (
    id character varying(64) NOT NULL,
    delivery_id character varying(64) NOT NULL,
    attempt integer NOT NULL,
    lease_owner character varying(64) NOT NULL,
    started_at timestamp(6) with time zone NOT NULL,
    finished_at timestamp(6) with time zone,
    outcome character varying(32) NOT NULL,
    http_status smallint,
    duration_ms bigint,
    error_code character varying(64),
    CONSTRAINT webhook_attempts_counts CHECK (((attempt >= 0) AND ((http_status IS NULL) OR (http_status >= 0)) AND ((duration_ms IS NULL) OR (duration_ms >= 0))))
);


--
-- Name: sys_api_webhook_deliveries; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sys_api_webhook_deliveries (
    id character varying(64) NOT NULL,
    subscription_id character varying(64) NOT NULL,
    subscription_revision bigint NOT NULL,
    event_id character varying(64) NOT NULL,
    topic character varying(191) NOT NULL,
    topic_version integer NOT NULL,
    major character varying(32) NOT NULL,
    endpoint_snapshot text NOT NULL,
    scope json NOT NULL,
    body text NOT NULL,
    body_hash character(64) NOT NULL,
    status character varying(16) NOT NULL,
    attempt integer NOT NULL,
    replay_count integer NOT NULL,
    lease_owner character varying(64),
    lease_until timestamp(6) with time zone,
    next_attempt_at timestamp(6) with time zone NOT NULL,
    created_at timestamp(6) with time zone NOT NULL,
    finished_at timestamp(6) with time zone,
    expires_at timestamp(6) with time zone NOT NULL,
    last_error text,
    CONSTRAINT webhook_deliveries_counts CHECK (((subscription_revision >= 0) AND (topic_version >= 0) AND (attempt >= 0) AND (replay_count >= 0))),
    CONSTRAINT webhook_deliveries_status CHECK (((status)::text = ANY ((ARRAY['pending'::character varying, 'sending'::character varying, 'retry'::character varying, 'succeeded'::character varying, 'dead'::character varying])::text[])))
);


--
-- Name: sys_api_webhook_subscriptions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sys_api_webhook_subscriptions (
    id character varying(64) NOT NULL,
    revision bigint NOT NULL,
    major character varying(32) NOT NULL,
    topic character varying(191) NOT NULL,
    topic_version integer NOT NULL,
    endpoint_url text NOT NULL,
    status character varying(16) NOT NULL,
    max_pii_class character varying(32) NOT NULL,
    key_refs json NOT NULL,
    created_at timestamp(6) with time zone NOT NULL,
    updated_at timestamp(6) with time zone NOT NULL,
    CONSTRAINT webhook_subscriptions_counts CHECK (((revision >= 0) AND (topic_version >= 0))),
    CONSTRAINT webhook_subscriptions_status CHECK (((status)::text = ANY ((ARRAY['active'::character varying, 'disabled'::character varying])::text[])))
);


--
-- Name: sys_attribute_backfills; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sys_attribute_backfills (
    id uuid NOT NULL,
    attribute_id uuid NOT NULL,
    from_revision bigint NOT NULL,
    to_revision bigint NOT NULL,
    entity character varying(128) NOT NULL,
    source_key character varying(128) NOT NULL,
    target_key character varying(128) NOT NULL,
    status character varying(16) NOT NULL,
    cursor_record_id character varying(128),
    attempted bigint DEFAULT '0'::bigint NOT NULL,
    changed bigint DEFAULT '0'::bigint NOT NULL,
    failed bigint DEFAULT '0'::bigint NOT NULL,
    source_digest character(64),
    target_digest character(64),
    error text,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone
);


--
-- Name: sys_attribute_definitions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sys_attribute_definitions (
    attribute_id uuid NOT NULL,
    revision bigint NOT NULL,
    entity character varying(128) NOT NULL,
    key character varying(128) NOT NULL,
    type character varying(64) NOT NULL,
    lifecycle character varying(16) NOT NULL,
    required boolean NOT NULL,
    searchable boolean NOT NULL,
    constraints jsonb NOT NULL,
    read_keys jsonb NOT NULL,
    write_keys jsonb NOT NULL,
    index_policy jsonb NOT NULL,
    meta jsonb NOT NULL,
    definition_hash character(64) NOT NULL,
    created_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: sys_attribute_documents; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sys_attribute_documents (
    entity character varying(128) NOT NULL,
    record_id character varying(128) NOT NULL,
    "values" jsonb NOT NULL,
    record_version character varying(64) NOT NULL
);


--
-- Name: sys_attribute_eav_records; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sys_attribute_eav_records (
    entity character varying(128) NOT NULL,
    record_id character varying(128) NOT NULL,
    record_version character varying(64) NOT NULL
);


--
-- Name: sys_attribute_eav_values; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sys_attribute_eav_values (
    entity character varying(128) NOT NULL,
    record_id character varying(128) NOT NULL,
    field character varying(128) NOT NULL,
    value jsonb NOT NULL
);


--
-- Name: sys_attribute_key_reservations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sys_attribute_key_reservations (
    entity character varying(128) NOT NULL,
    key character varying(128) NOT NULL,
    attribute_id uuid NOT NULL,
    current_revision bigint NOT NULL,
    role character varying(16) NOT NULL,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone
);


--
-- Name: sys_attribute_schema_operations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sys_attribute_schema_operations (
    id character varying(64) NOT NULL,
    operation_key character varying(128) NOT NULL,
    payload_hash character varying(64) NOT NULL,
    entity character varying(128) NOT NULL,
    schema_hash character varying(64) NOT NULL,
    approval_digest character varying(64) NOT NULL,
    restore_epoch character varying(64) NOT NULL,
    definition jsonb NOT NULL,
    status character varying(16) NOT NULL,
    attempts integer DEFAULT 0 NOT NULL,
    error_code character varying(128),
    started_at timestamp(0) with time zone,
    finished_at timestamp(0) with time zone,
    created_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: sys_attribute_write_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sys_attribute_write_tokens (
    operation_key character varying(128) NOT NULL,
    entity character varying(128) NOT NULL,
    record_id character varying(128) NOT NULL,
    payload_hash character varying(64) NOT NULL,
    record_version character varying(64) NOT NULL
);


--
-- Name: sys_audit_log; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sys_audit_log (
    id uuid NOT NULL,
    occurred_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    actor_type character varying(16) NOT NULL,
    actor_id uuid,
    workspace_id uuid,
    entity_type character varying(64),
    entity_id uuid,
    action character varying(64) NOT NULL,
    changes jsonb,
    context jsonb DEFAULT '{}'::jsonb NOT NULL,
    created_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    seq bigint NOT NULL,
    CONSTRAINT sys_audit_actor_ck CHECK (((actor_type)::text = ANY ((ARRAY['user'::character varying, 'agent'::character varying, 'api_key'::character varying, 'system'::character varying, 'support'::character varying])::text[])))
);


--
-- Name: sys_audit_log_seq_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.sys_audit_log_seq_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: sys_audit_log_seq_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.sys_audit_log_seq_seq OWNED BY public.sys_audit_log.seq;


--
-- Name: sys_department_user; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sys_department_user (
    department_id uuid NOT NULL,
    user_id uuid NOT NULL,
    role character varying(16) DEFAULT 'member'::character varying NOT NULL,
    is_primary boolean DEFAULT false NOT NULL,
    created_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT sys_du_role_ck CHECK (((role)::text = ANY ((ARRAY['member'::character varying, 'head'::character varying, 'deputy'::character varying])::text[])))
);


--
-- Name: sys_departments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sys_departments (
    id uuid NOT NULL,
    workspace_id uuid NOT NULL,
    parent_id uuid,
    name character varying(255) NOT NULL,
    head_user_id uuid,
    sort_order integer DEFAULT 0 NOT NULL,
    deleted_at timestamp(6) with time zone,
    created_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    path public.ltree NOT NULL
);


--
-- Name: sys_feature_flags; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sys_feature_flags (
    id uuid NOT NULL,
    key character varying(128) NOT NULL,
    module character varying(190),
    is_enabled boolean DEFAULT false NOT NULL,
    payload jsonb,
    created_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: sys_feature_overrides; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sys_feature_overrides (
    id uuid NOT NULL,
    flag_id uuid NOT NULL,
    scope_type character varying(16) NOT NULL,
    scope_id uuid NOT NULL,
    is_enabled boolean NOT NULL,
    payload jsonb,
    created_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT sys_fo_scope_ck CHECK (((scope_type)::text = ANY ((ARRAY['workspace'::character varying, 'user'::character varying, 'department'::character varying])::text[])))
);


--
-- Name: sys_inbox_messages; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sys_inbox_messages (
    consumer character varying(120) NOT NULL,
    message_id uuid NOT NULL,
    name character varying(160) NOT NULL,
    processed_at timestamp(6) with time zone NOT NULL
);


--
-- Name: sys_outbox_messages; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sys_outbox_messages (
    id uuid NOT NULL,
    name character varying(160) NOT NULL,
    contract_version smallint NOT NULL,
    envelope_version smallint NOT NULL,
    source character varying(128) NOT NULL,
    subject character varying(128),
    correlation_id character varying(128),
    causation_id character varying(128),
    pii_class character varying(16) NOT NULL,
    scope json NOT NULL,
    payload json NOT NULL,
    metadata json NOT NULL,
    occurred_at timestamp(6) with time zone NOT NULL,
    available_at timestamp(6) with time zone NOT NULL,
    status character varying(16) NOT NULL,
    lease_owner uuid,
    lease_until timestamp(6) with time zone,
    attempts integer NOT NULL,
    failure_class character varying(32),
    last_error text,
    published_at timestamp(6) with time zone,
    dead_at timestamp(6) with time zone,
    created_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: sys_pubsub_cursors; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sys_pubsub_cursors (
    channel character varying(190) NOT NULL,
    subscriber character varying(190) NOT NULL,
    "position" bigint NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: sys_pubsub_messages; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sys_pubsub_messages (
    id bigint NOT NULL,
    channel character varying(190) NOT NULL,
    payload jsonb NOT NULL,
    created_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: sys_pubsub_messages_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.sys_pubsub_messages_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: sys_pubsub_messages_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.sys_pubsub_messages_id_seq OWNED BY public.sys_pubsub_messages.id;


--
-- Name: sys_saved_view_scopes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sys_saved_view_scopes (
    entity_code character varying(128) NOT NULL,
    scope_key character varying(1024) NOT NULL
);


--
-- Name: sys_saved_views; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sys_saved_views (
    id uuid NOT NULL,
    entity_code character varying(128) NOT NULL,
    workspace_id character varying(255),
    owner_kind character varying(64),
    owner_id character varying(255),
    scope_key character varying(1024) NOT NULL,
    kind character varying(64) NOT NULL,
    name jsonb NOT NULL,
    config jsonb NOT NULL,
    schema_version character varying(128) NOT NULL,
    revision bigint NOT NULL,
    is_default boolean NOT NULL,
    default_scope_key character varying(1024),
    "position" integer NOT NULL,
    created_by character varying(128) NOT NULL,
    created_at timestamp(6) with time zone,
    updated_at timestamp(6) with time zone
);


--
-- Name: sys_settings; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sys_settings (
    id uuid NOT NULL,
    namespace character varying(64) NOT NULL,
    key character varying(190) NOT NULL,
    scope_type character varying(16) DEFAULT 'account'::character varying NOT NULL,
    scope_id uuid,
    value jsonb NOT NULL,
    is_sensitive boolean DEFAULT false NOT NULL,
    created_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT sys_settings_scope_ck CHECK (((scope_type)::text = ANY ((ARRAY['account'::character varying, 'workspace'::character varying, 'user'::character varying])::text[]))),
    CONSTRAINT sys_settings_scope_id_ck CHECK (((((scope_type)::text = 'account'::text) AND (scope_id IS NULL)) OR (((scope_type)::text <> 'account'::text) AND (scope_id IS NOT NULL))))
);


--
-- Name: sys_wakeup_requests; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sys_wakeup_requests (
    id uuid NOT NULL,
    workspace_scope_key character varying(255) NOT NULL,
    kind character varying(128) NOT NULL,
    request_key character varying(128) NOT NULL,
    request_digest character(64) NOT NULL,
    fire_at timestamp(0) with time zone NOT NULL,
    window_start timestamp(0) with time zone,
    window_end timestamp(0) with time zone,
    period character varying(16),
    payload json NOT NULL,
    status character varying(16) NOT NULL,
    generation bigint NOT NULL,
    attempt integer NOT NULL,
    lease_until timestamp(0) with time zone,
    retry_at timestamp(0) with time zone,
    failure_code character varying(64),
    completed_at timestamp(0) with time zone,
    created_at timestamp(0) with time zone,
    updated_at timestamp(0) with time zone
);


--
-- Name: sys_workflow_intents; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sys_workflow_intents (
    id uuid NOT NULL,
    request_key character varying(128) NOT NULL,
    payload_hash character(64) NOT NULL,
    context_hash character(64) NOT NULL,
    restore_epoch character(64) NOT NULL,
    module character varying(128) NOT NULL,
    kind character varying(16) NOT NULL,
    definition_ref character varying(255),
    version_ref character varying(255),
    subject_ref character varying(255) NOT NULL,
    payload json NOT NULL,
    state character varying(16) NOT NULL,
    provider_instance character varying(255),
    claim_epoch bigint DEFAULT '0'::bigint NOT NULL,
    attempt integer DEFAULT 0 NOT NULL,
    generation bigint DEFAULT '0'::bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: tnt_membership_inbox; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tnt_membership_inbox (
    event_id uuid NOT NULL,
    logical_digest character(64) NOT NULL,
    delivery_id uuid NOT NULL,
    status character varying(12) NOT NULL,
    attempts bigint DEFAULT '0'::bigint NOT NULL,
    owner_id uuid,
    lease_until bigint,
    CONSTRAINT tnt_membership_inbox_status CHECK (((status)::text = ANY ((ARRAY['pending'::character varying, 'acknowledged'::character varying])::text[])))
);


--
-- Name: tnt_membership_pairs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tnt_membership_pairs (
    account_id uuid NOT NULL,
    identity_id uuid NOT NULL,
    revision numeric(20,0) DEFAULT '0'::numeric NOT NULL,
    revoke_generation numeric(20,0) DEFAULT '0'::numeric NOT NULL,
    local_epoch numeric(20,0) DEFAULT '0'::numeric NOT NULL,
    operation_fence numeric(20,0) DEFAULT '0'::numeric NOT NULL,
    application_fence numeric(20,0),
    operation_id uuid,
    applied_operation_id uuid,
    barrier_id uuid,
    snapshot_digest character(64),
    denied boolean DEFAULT true NOT NULL,
    CONSTRAINT tnt_membership_pairs_application_fence_range CHECK (((application_fence IS NULL) OR ((application_fence >= (0)::numeric) AND (application_fence <= '18446744073709551615'::numeric)))),
    CONSTRAINT tnt_membership_pairs_local_epoch_range CHECK (((local_epoch >= (0)::numeric) AND (local_epoch <= '18446744073709551615'::numeric))),
    CONSTRAINT tnt_membership_pairs_operation_fence_range CHECK (((operation_fence >= (0)::numeric) AND (operation_fence <= '18446744073709551615'::numeric))),
    CONSTRAINT tnt_membership_pairs_revision_range CHECK (((revision >= (0)::numeric) AND (revision <= '18446744073709551615'::numeric))),
    CONSTRAINT tnt_membership_pairs_revoke_generation_range CHECK (((revoke_generation >= (0)::numeric) AND (revoke_generation <= '18446744073709551615'::numeric))),
    CONSTRAINT tnt_membership_pairs_snapshot_digest_format CHECK (((snapshot_digest IS NULL) OR (snapshot_digest ~ '^[a-f0-9]{64}$'::text)))
);


--
-- Name: tsk_projects; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tsk_projects (
    id uuid NOT NULL,
    workspace_id uuid,
    name character varying(255) NOT NULL,
    description text,
    status character varying(16) DEFAULT 'active'::character varying NOT NULL,
    owner_id uuid,
    starts_on date,
    due_on date,
    settings jsonb DEFAULT '{}'::jsonb NOT NULL,
    created_by jsonb NOT NULL,
    created_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    deleted_at timestamp(0) with time zone,
    CONSTRAINT tsk_projects_status_chk CHECK (((status)::text = ANY ((ARRAY['active'::character varying, 'archived'::character varying])::text[])))
);


--
-- Name: tsk_sprints; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tsk_sprints (
    id uuid NOT NULL,
    project_id uuid NOT NULL,
    name character varying(190) NOT NULL,
    goal text,
    status character varying(16) DEFAULT 'planned'::character varying NOT NULL,
    starts_on date NOT NULL,
    ends_on date NOT NULL,
    created_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT tsk_sprints_dates_chk CHECK ((ends_on >= starts_on)),
    CONSTRAINT tsk_sprints_status_chk CHECK (((status)::text = ANY ((ARRAY['planned'::character varying, 'active'::character varying, 'completed'::character varying])::text[])))
);


--
-- Name: tsk_stages; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tsk_stages (
    id uuid NOT NULL,
    project_id uuid NOT NULL,
    code character varying(64) NOT NULL,
    name jsonb NOT NULL,
    color character varying(16),
    "position" integer DEFAULT 0 NOT NULL,
    auto_status character varying(16),
    created_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT tsk_stages_auto_status_chk CHECK (((auto_status)::text = ANY ((ARRAY['in_progress'::character varying, 'done'::character varying])::text[])))
);


--
-- Name: tsk_task_targets; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tsk_task_targets (
    task_id uuid NOT NULL,
    entity_type character varying(64) NOT NULL,
    entity_id uuid NOT NULL,
    created_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: tsk_task_watchers; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tsk_task_watchers (
    task_id uuid NOT NULL,
    user_id uuid NOT NULL,
    created_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: tsk_tasks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tsk_tasks (
    id uuid NOT NULL,
    workspace_id uuid,
    project_id uuid,
    sprint_id uuid,
    stage_id uuid,
    parent_id uuid,
    title character varying(255) NOT NULL,
    description text,
    status character varying(16) DEFAULT 'open'::character varying NOT NULL,
    priority character varying(8) DEFAULT 'normal'::character varying NOT NULL,
    author_id uuid,
    assignee_id uuid,
    created_by jsonb NOT NULL,
    starts_at timestamp(0) with time zone,
    due_at timestamp(0) with time zone,
    completed_at timestamp(0) with time zone,
    requires_acceptance boolean DEFAULT false NOT NULL,
    recurrence_rule_id uuid,
    recurrence_occurs_at timestamp(0) with time zone,
    estimate_minutes integer,
    spent_minutes integer DEFAULT 0 NOT NULL,
    "position" numeric(20,10) DEFAULT '0'::numeric NOT NULL,
    search tsvector,
    created_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    deleted_at timestamp(0) with time zone,
    CONSTRAINT tsk_tasks_completed_chk CHECK ((((status)::text <> 'done'::text) OR (completed_at IS NOT NULL))),
    CONSTRAINT tsk_tasks_priority_chk CHECK (((priority)::text = ANY ((ARRAY['low'::character varying, 'normal'::character varying, 'high'::character varying, 'urgent'::character varying])::text[]))),
    CONSTRAINT tsk_tasks_recurrence_chk CHECK (((recurrence_rule_id IS NULL) = (recurrence_occurs_at IS NULL))),
    CONSTRAINT tsk_tasks_status_chk CHECK (((status)::text = ANY ((ARRAY['open'::character varying, 'in_progress'::character varying, 'waiting'::character varying, 'review'::character varying, 'done'::character varying, 'cancelled'::character varying])::text[])))
);


--
-- Name: tsk_time_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tsk_time_logs (
    id uuid NOT NULL,
    task_id uuid NOT NULL,
    user_id uuid NOT NULL,
    minutes integer NOT NULL,
    spent_on date NOT NULL,
    comment text,
    source character varying(8) DEFAULT 'manual'::character varying NOT NULL,
    created_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT tsk_time_logs_minutes_chk CHECK ((minutes > 0)),
    CONSTRAINT tsk_time_logs_source_chk CHECK (((source)::text = ANY ((ARRAY['manual'::character varying, 'timer'::character varying])::text[])))
);


--
-- Name: tsk_timers; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tsk_timers (
    user_id uuid NOT NULL,
    task_id uuid NOT NULL,
    started_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    created_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.users (
    id uuid NOT NULL,
    email character varying(255) NOT NULL,
    name character varying(255) DEFAULT ''::character varying NOT NULL,
    phone character varying(32),
    locale character varying(8) DEFAULT 'ru'::character varying NOT NULL,
    password_hash character varying(255),
    department_id uuid,
    is_active boolean DEFAULT true NOT NULL,
    settings jsonb DEFAULT '{}'::jsonb NOT NULL,
    last_login_at timestamp(6) with time zone,
    synced_at timestamp(6) with time zone,
    created_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    deleted_at timestamp(6) with time zone,
    identity_source_updated_at timestamp(6) with time zone
);


--
-- Name: wsp_invitations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.wsp_invitations (
    id uuid NOT NULL,
    email character varying(255) NOT NULL,
    workspace_id uuid,
    role_id uuid,
    invited_by uuid NOT NULL,
    token_hash character varying(64) NOT NULL,
    status character varying(16) DEFAULT 'pending'::character varying NOT NULL,
    expires_at timestamp(6) with time zone DEFAULT (now() + '7 days'::interval) NOT NULL,
    accepted_at timestamp(6) with time zone,
    created_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT wsp_invitations_status_ck CHECK (((status)::text = ANY ((ARRAY['pending'::character varying, 'accepted'::character varying, 'revoked'::character varying, 'expired'::character varying])::text[])))
);


--
-- Name: wsp_workspace_members; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.wsp_workspace_members (
    id uuid NOT NULL,
    workspace_id uuid NOT NULL,
    user_id uuid NOT NULL,
    status character varying(16) DEFAULT 'active'::character varying NOT NULL,
    is_default boolean DEFAULT false NOT NULL,
    joined_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    created_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT wsp_members_status_ck CHECK (((status)::text = ANY ((ARRAY['active'::character varying, 'suspended'::character varying])::text[])))
);


--
-- Name: wsp_workspaces; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.wsp_workspaces (
    id uuid NOT NULL,
    parent_id uuid,
    type character varying(16) DEFAULT 'company'::character varying NOT NULL,
    name character varying(255) NOT NULL,
    slug character varying(63) NOT NULL,
    is_default boolean DEFAULT false NOT NULL,
    settings jsonb DEFAULT '{}'::jsonb NOT NULL,
    created_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(6) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    deleted_at timestamp(6) with time zone,
    path public.ltree NOT NULL,
    CONSTRAINT wsp_workspaces_type_ck CHECK (((type)::text = ANY ((ARRAY['company'::character varying, 'branch'::character varying, 'partner'::character varying])::text[])))
);


--
-- Name: corex_search_dirty sequence; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.corex_search_dirty ALTER COLUMN sequence SET DEFAULT nextval('public.corex_search_dirty_sequence_seq'::regclass);


--
-- Name: med_conversions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.med_conversions ALTER COLUMN id SET DEFAULT nextval('public.med_conversions_id_seq'::regclass);


--
-- Name: med_operations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.med_operations ALTER COLUMN id SET DEFAULT nextval('public.med_operations_id_seq'::regclass);


--
-- Name: med_relations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.med_relations ALTER COLUMN id SET DEFAULT nextval('public.med_relations_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: mod_records seq; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mod_records ALTER COLUMN seq SET DEFAULT nextval('public.mod_records_seq_seq'::regclass);


--
-- Name: sys_audit_log seq; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_audit_log ALTER COLUMN seq SET DEFAULT nextval('public.sys_audit_log_seq_seq'::regclass);


--
-- Name: sys_pubsub_messages id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_pubsub_messages ALTER COLUMN id SET DEFAULT nextval('public.sys_pubsub_messages_id_seq'::regclass);


--
-- Name: aut_azguard_role_links aut_azguard_role_links_azguard_role_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.aut_azguard_role_links
    ADD CONSTRAINT aut_azguard_role_links_azguard_role_id_unique UNIQUE (azguard_role_id);


--
-- Name: aut_azguard_role_links aut_azguard_role_links_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.aut_azguard_role_links
    ADD CONSTRAINT aut_azguard_role_links_pkey PRIMARY KEY (role_id);


--
-- Name: aut_direct_grants aut_direct_grants_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.aut_direct_grants
    ADD CONSTRAINT aut_direct_grants_pkey PRIMARY KEY (id);


--
-- Name: aut_role_assignments aut_role_assignments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.aut_role_assignments
    ADD CONSTRAINT aut_role_assignments_pkey PRIMARY KEY (id);


--
-- Name: aut_role_assignments aut_role_assignments_uq; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.aut_role_assignments
    ADD CONSTRAINT aut_role_assignments_uq UNIQUE NULLS NOT DISTINCT (role_id, principal_type, principal_id, workspace_id);


--
-- Name: aut_role_matrix aut_role_matrix_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.aut_role_matrix
    ADD CONSTRAINT aut_role_matrix_pkey PRIMARY KEY (id);


--
-- Name: aut_role_matrix aut_role_matrix_uq; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.aut_role_matrix
    ADD CONSTRAINT aut_role_matrix_uq UNIQUE NULLS NOT DISTINCT (role_id, entity_key, action, context_type, context_id);


--
-- Name: aut_roles aut_roles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.aut_roles
    ADD CONSTRAINT aut_roles_pkey PRIMARY KEY (id);


--
-- Name: corex_export_manifests corex_export_manifests_capture_attempt_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.corex_export_manifests
    ADD CONSTRAINT corex_export_manifests_capture_attempt_unique UNIQUE (capture_attempt);


--
-- Name: corex_export_manifests corex_export_manifests_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.corex_export_manifests
    ADD CONSTRAINT corex_export_manifests_pkey PRIMARY KEY (id);


--
-- Name: corex_export_parts corex_export_parts_manifest_id_part_no_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.corex_export_parts
    ADD CONSTRAINT corex_export_parts_manifest_id_part_no_unique UNIQUE (manifest_id, part_no);


--
-- Name: corex_export_parts corex_export_parts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.corex_export_parts
    ADD CONSTRAINT corex_export_parts_pkey PRIMARY KEY (id);


--
-- Name: corex_rate_batches corex_rate_batches_operation_key_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.corex_rate_batches
    ADD CONSTRAINT corex_rate_batches_operation_key_unique UNIQUE (operation_key);


--
-- Name: corex_rate_batches corex_rate_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.corex_rate_batches
    ADD CONSTRAINT corex_rate_batches_pkey PRIMARY KEY (id);


--
-- Name: corex_rate_current corex_rate_current_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.corex_rate_current
    ADD CONSTRAINT corex_rate_current_pkey PRIMARY KEY (base, quote, source);


--
-- Name: corex_rate_snapshots corex_rate_snapshots_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.corex_rate_snapshots
    ADD CONSTRAINT corex_rate_snapshots_pkey PRIMARY KEY (id);


--
-- Name: corex_rate_snapshots corex_rate_snapshots_publication_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.corex_rate_snapshots
    ADD CONSTRAINT corex_rate_snapshots_publication_unique UNIQUE (source, publication_id, publication_revision, base, quote, valid_on);


--
-- Name: corex_reference_aliases corex_reference_aliases_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.corex_reference_aliases
    ADD CONSTRAINT corex_reference_aliases_pkey PRIMARY KEY (snapshot_id, namespace, source_id, locale, alias);


--
-- Name: corex_reference_current corex_reference_current_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.corex_reference_current
    ADD CONSTRAINT corex_reference_current_pkey PRIMARY KEY (dataset_key);


--
-- Name: corex_reference_datasets corex_reference_datasets_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.corex_reference_datasets
    ADD CONSTRAINT corex_reference_datasets_pkey PRIMARY KEY (snapshot_id);


--
-- Name: corex_reference_records corex_reference_records_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.corex_reference_records
    ADD CONSTRAINT corex_reference_records_pkey PRIMARY KEY (snapshot_id, namespace, source_id);


--
-- Name: corex_search_dirty corex_search_dirty_lane_key_record_id_revision_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.corex_search_dirty
    ADD CONSTRAINT corex_search_dirty_lane_key_record_id_revision_unique UNIQUE (lane_key, record_id, revision);


--
-- Name: corex_search_dirty corex_search_dirty_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.corex_search_dirty
    ADD CONSTRAINT corex_search_dirty_pkey PRIMARY KEY (sequence);


--
-- Name: corex_search_lanes corex_search_lanes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.corex_search_lanes
    ADD CONSTRAINT corex_search_lanes_pkey PRIMARY KEY (lane_key);


--
-- Name: corex_search_rebuilds corex_search_rebuilds_lane_key_to_generation_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.corex_search_rebuilds
    ADD CONSTRAINT corex_search_rebuilds_lane_key_to_generation_unique UNIQUE (lane_key, to_generation);


--
-- Name: corex_search_rebuilds corex_search_rebuilds_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.corex_search_rebuilds
    ADD CONSTRAINT corex_search_rebuilds_pkey PRIMARY KEY (rebuild_id);


--
-- Name: corex_task_occurrences corex_task_occurrences_operation_key_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.corex_task_occurrences
    ADD CONSTRAINT corex_task_occurrences_operation_key_unique UNIQUE (operation_key);


--
-- Name: corex_task_occurrences corex_task_occurrences_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.corex_task_occurrences
    ADD CONSTRAINT corex_task_occurrences_pkey PRIMARY KEY (id);


--
-- Name: corex_task_occurrences corex_task_occurrences_series_key_immutable_revision_occurrence; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.corex_task_occurrences
    ADD CONSTRAINT corex_task_occurrences_series_key_immutable_revision_occurrence UNIQUE (series_key, immutable_revision, occurrence_key);


--
-- Name: corex_task_operations corex_task_operations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.corex_task_operations
    ADD CONSTRAINT corex_task_operations_pkey PRIMARY KEY (id);


--
-- Name: corex_task_operations corex_task_operations_request_key_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.corex_task_operations
    ADD CONSTRAINT corex_task_operations_request_key_unique UNIQUE (request_key);


--
-- Name: corex_task_series corex_task_series_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.corex_task_series
    ADD CONSTRAINT corex_task_series_pkey PRIMARY KEY (id);


--
-- Name: corex_task_series corex_task_series_series_key_immutable_revision_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.corex_task_series
    ADD CONSTRAINT corex_task_series_series_key_immutable_revision_unique UNIQUE (series_key, immutable_revision);


--
-- Name: corex_task_series_state corex_task_series_state_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.corex_task_series_state
    ADD CONSTRAINT corex_task_series_state_pkey PRIMARY KEY (series_key);


--
-- Name: corex_transfer_jobs corex_transfer_jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.corex_transfer_jobs
    ADD CONSTRAINT corex_transfer_jobs_pkey PRIMARY KEY (id);


--
-- Name: corex_transfer_outbox corex_transfer_outbox_operation_key_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.corex_transfer_outbox
    ADD CONSTRAINT corex_transfer_outbox_operation_key_unique UNIQUE (operation_key);


--
-- Name: corex_transfer_outbox corex_transfer_outbox_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.corex_transfer_outbox
    ADD CONSTRAINT corex_transfer_outbox_pkey PRIMARY KEY (id);


--
-- Name: corex_transfer_refs corex_transfer_refs_entity_external_system_external_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.corex_transfer_refs
    ADD CONSTRAINT corex_transfer_refs_entity_external_system_external_id_unique UNIQUE (entity, external_system, external_id);


--
-- Name: corex_transfer_refs corex_transfer_refs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.corex_transfer_refs
    ADD CONSTRAINT corex_transfer_refs_pkey PRIMARY KEY (id);


--
-- Name: corex_transfer_rows corex_transfer_rows_job_id_ordinal_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.corex_transfer_rows
    ADD CONSTRAINT corex_transfer_rows_job_id_ordinal_unique UNIQUE (job_id, ordinal);


--
-- Name: corex_transfer_rows corex_transfer_rows_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.corex_transfer_rows
    ADD CONSTRAINT corex_transfer_rows_pkey PRIMARY KEY (id);


--
-- Name: crm_activities crm_activities_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_activities
    ADD CONSTRAINT crm_activities_pkey PRIMARY KEY (id);


--
-- Name: crm_activity_targets crm_activity_targets_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_activity_targets
    ADD CONSTRAINT crm_activity_targets_pkey PRIMARY KEY (activity_id, entity_type, entity_id);


--
-- Name: crm_companies crm_companies_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_companies
    ADD CONSTRAINT crm_companies_pkey PRIMARY KEY (id);


--
-- Name: crm_contact_company crm_contact_company_contact_id_company_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_contact_company
    ADD CONSTRAINT crm_contact_company_contact_id_company_id_unique UNIQUE (contact_id, company_id);


--
-- Name: crm_contact_company crm_contact_company_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_contact_company
    ADD CONSTRAINT crm_contact_company_pkey PRIMARY KEY (id);


--
-- Name: crm_contacts crm_contacts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_contacts
    ADD CONSTRAINT crm_contacts_pkey PRIMARY KEY (id);


--
-- Name: crm_deal_contacts crm_deal_contacts_deal_id_contact_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_deal_contacts
    ADD CONSTRAINT crm_deal_contacts_deal_id_contact_id_unique UNIQUE (deal_id, contact_id);


--
-- Name: crm_deal_contacts crm_deal_contacts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_deal_contacts
    ADD CONSTRAINT crm_deal_contacts_pkey PRIMARY KEY (id);


--
-- Name: crm_deals crm_deals_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_deals
    ADD CONSTRAINT crm_deals_pkey PRIMARY KEY (id);


--
-- Name: crm_leads crm_leads_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_leads
    ADD CONSTRAINT crm_leads_pkey PRIMARY KEY (id);


--
-- Name: crm_lost_reasons crm_lost_reasons_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_lost_reasons
    ADD CONSTRAINT crm_lost_reasons_pkey PRIMARY KEY (id);


--
-- Name: crm_lost_reasons crm_lr_code_uq; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_lost_reasons
    ADD CONSTRAINT crm_lr_code_uq UNIQUE NULLS NOT DISTINCT (workspace_id, code);


--
-- Name: crm_pipelines crm_pipelines_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_pipelines
    ADD CONSTRAINT crm_pipelines_pkey PRIMARY KEY (id);


--
-- Name: crm_sources crm_sources_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_sources
    ADD CONSTRAINT crm_sources_pkey PRIMARY KEY (id);


--
-- Name: crm_sources crm_sources_workspace_id_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_sources
    ADD CONSTRAINT crm_sources_workspace_id_code_unique UNIQUE NULLS NOT DISTINCT (workspace_id, code);


--
-- Name: crm_stages crm_stages_pipeline_id_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_stages
    ADD CONSTRAINT crm_stages_pipeline_id_code_unique UNIQUE (pipeline_id, code);


--
-- Name: crm_stages crm_stages_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_stages
    ADD CONSTRAINT crm_stages_pkey PRIMARY KEY (id);


--
-- Name: med_conversions med_conversions_file_id_profile_key_profile_digest_source_versi; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.med_conversions
    ADD CONSTRAINT med_conversions_file_id_profile_key_profile_digest_source_versi UNIQUE (file_id, profile_key, profile_digest, source_version);


--
-- Name: med_conversions med_conversions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.med_conversions
    ADD CONSTRAINT med_conversions_pkey PRIMARY KEY (id);


--
-- Name: med_files med_files_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.med_files
    ADD CONSTRAINT med_files_pkey PRIMARY KEY (id);


--
-- Name: med_files med_files_request_key_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.med_files
    ADD CONSTRAINT med_files_request_key_unique UNIQUE (request_key);


--
-- Name: med_operations med_operations_operation_key_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.med_operations
    ADD CONSTRAINT med_operations_operation_key_unique UNIQUE (operation_key);


--
-- Name: med_operations med_operations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.med_operations
    ADD CONSTRAINT med_operations_pkey PRIMARY KEY (id);


--
-- Name: med_relations med_relations_file_id_entity_handle_entity_id_slot_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.med_relations
    ADD CONSTRAINT med_relations_file_id_entity_handle_entity_id_slot_unique UNIQUE (file_id, entity_handle, entity_id, slot);


--
-- Name: med_relations med_relations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.med_relations
    ADD CONSTRAINT med_relations_pkey PRIMARY KEY (id);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: mod_lifecycle_log mod_lifecycle_log_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mod_lifecycle_log
    ADD CONSTRAINT mod_lifecycle_log_pkey PRIMARY KEY (id);


--
-- Name: mod_modules mod_modules_name_uq; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mod_modules
    ADD CONSTRAINT mod_modules_name_uq UNIQUE (name);


--
-- Name: mod_modules mod_modules_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mod_modules
    ADD CONSTRAINT mod_modules_pkey PRIMARY KEY (id);


--
-- Name: mod_records mod_records_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.mod_records
    ADD CONSTRAINT mod_records_pkey PRIMARY KEY (module_name, record_type, reference);


--
-- Name: ntf_deliveries ntf_deliveries_delivery_key_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ntf_deliveries
    ADD CONSTRAINT ntf_deliveries_delivery_key_unique UNIQUE (delivery_key);


--
-- Name: ntf_deliveries ntf_deliveries_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ntf_deliveries
    ADD CONSTRAINT ntf_deliveries_pkey PRIMARY KEY (id);


--
-- Name: ntf_digest_items ntf_digest_items_notification_id_channel_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ntf_digest_items
    ADD CONSTRAINT ntf_digest_items_notification_id_channel_unique UNIQUE (notification_id, channel);


--
-- Name: ntf_digest_items ntf_digest_items_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ntf_digest_items
    ADD CONSTRAINT ntf_digest_items_pkey PRIMARY KEY (id);


--
-- Name: ntf_digest_runs ntf_digest_runs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ntf_digest_runs
    ADD CONSTRAINT ntf_digest_runs_pkey PRIMARY KEY (id);


--
-- Name: ntf_digest_runs ntf_digest_runs_recipient_kind_recipient_id_period_period_start; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ntf_digest_runs
    ADD CONSTRAINT ntf_digest_runs_recipient_kind_recipient_id_period_period_start UNIQUE (recipient_kind, recipient_id, period, period_start, batch_seq);


--
-- Name: ntf_notifications ntf_notifications_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ntf_notifications
    ADD CONSTRAINT ntf_notifications_pkey PRIMARY KEY (id);


--
-- Name: ntf_notifications ntf_notifications_recipient_kind_recipient_id_request_key_uniqu; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ntf_notifications
    ADD CONSTRAINT ntf_notifications_recipient_kind_recipient_id_request_key_uniqu UNIQUE (recipient_kind, recipient_id, request_key);


--
-- Name: ntf_recipient_state ntf_recipient_state_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ntf_recipient_state
    ADD CONSTRAINT ntf_recipient_state_pkey PRIMARY KEY (kind, id);


--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- Name: sys_api_bulk_operations sys_api_bulk_operations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_api_bulk_operations
    ADD CONSTRAINT sys_api_bulk_operations_pkey PRIMARY KEY (id);


--
-- Name: sys_api_bulk_operations sys_api_bulk_operations_submission_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_api_bulk_operations
    ADD CONSTRAINT sys_api_bulk_operations_submission_unique UNIQUE (major, operation_id, submission_id);


--
-- Name: sys_api_idempotency sys_api_idempotency_scope_key_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_api_idempotency
    ADD CONSTRAINT sys_api_idempotency_scope_key_unique UNIQUE (scope_hash, key_hash);


--
-- Name: sys_api_webhook_attempts sys_api_webhook_attempts_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_api_webhook_attempts
    ADD CONSTRAINT sys_api_webhook_attempts_number_unique UNIQUE (delivery_id, attempt);


--
-- Name: sys_api_webhook_attempts sys_api_webhook_attempts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_api_webhook_attempts
    ADD CONSTRAINT sys_api_webhook_attempts_pkey PRIMARY KEY (id);


--
-- Name: sys_api_webhook_deliveries sys_api_webhook_deliveries_event_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_api_webhook_deliveries
    ADD CONSTRAINT sys_api_webhook_deliveries_event_unique UNIQUE (subscription_id, event_id);


--
-- Name: sys_api_webhook_deliveries sys_api_webhook_deliveries_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_api_webhook_deliveries
    ADD CONSTRAINT sys_api_webhook_deliveries_pkey PRIMARY KEY (id);


--
-- Name: sys_api_webhook_subscriptions sys_api_webhook_subscriptions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_api_webhook_subscriptions
    ADD CONSTRAINT sys_api_webhook_subscriptions_pkey PRIMARY KEY (id);


--
-- Name: sys_attribute_backfills sys_attribute_backfills_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_attribute_backfills
    ADD CONSTRAINT sys_attribute_backfills_pkey PRIMARY KEY (id);


--
-- Name: sys_attribute_definitions sys_attribute_definitions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_attribute_definitions
    ADD CONSTRAINT sys_attribute_definitions_pkey PRIMARY KEY (attribute_id, revision);


--
-- Name: sys_attribute_documents sys_attribute_documents_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_attribute_documents
    ADD CONSTRAINT sys_attribute_documents_pkey PRIMARY KEY (entity, record_id);


--
-- Name: sys_attribute_eav_records sys_attribute_eav_records_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_attribute_eav_records
    ADD CONSTRAINT sys_attribute_eav_records_pkey PRIMARY KEY (entity, record_id);


--
-- Name: sys_attribute_eav_values sys_attribute_eav_values_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_attribute_eav_values
    ADD CONSTRAINT sys_attribute_eav_values_pkey PRIMARY KEY (entity, record_id, field);


--
-- Name: sys_attribute_key_reservations sys_attribute_key_reservations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_attribute_key_reservations
    ADD CONSTRAINT sys_attribute_key_reservations_pkey PRIMARY KEY (entity, key);


--
-- Name: sys_attribute_schema_operations sys_attribute_schema_operations_operation_key_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_attribute_schema_operations
    ADD CONSTRAINT sys_attribute_schema_operations_operation_key_unique UNIQUE (operation_key);


--
-- Name: sys_attribute_schema_operations sys_attribute_schema_operations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_attribute_schema_operations
    ADD CONSTRAINT sys_attribute_schema_operations_pkey PRIMARY KEY (id);


--
-- Name: sys_attribute_write_tokens sys_attribute_write_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_attribute_write_tokens
    ADD CONSTRAINT sys_attribute_write_tokens_pkey PRIMARY KEY (operation_key);


--
-- Name: sys_audit_log sys_audit_log_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_audit_log
    ADD CONSTRAINT sys_audit_log_pkey PRIMARY KEY (id);


--
-- Name: sys_department_user sys_department_user_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_department_user
    ADD CONSTRAINT sys_department_user_pkey PRIMARY KEY (department_id, user_id);


--
-- Name: sys_departments sys_departments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_departments
    ADD CONSTRAINT sys_departments_pkey PRIMARY KEY (id);


--
-- Name: sys_departments sys_dept_path_uq; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_departments
    ADD CONSTRAINT sys_dept_path_uq UNIQUE (path);


--
-- Name: sys_feature_flags sys_feature_flags_key_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_feature_flags
    ADD CONSTRAINT sys_feature_flags_key_unique UNIQUE (key);


--
-- Name: sys_feature_flags sys_feature_flags_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_feature_flags
    ADD CONSTRAINT sys_feature_flags_pkey PRIMARY KEY (id);


--
-- Name: sys_feature_overrides sys_feature_overrides_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_feature_overrides
    ADD CONSTRAINT sys_feature_overrides_pkey PRIMARY KEY (id);


--
-- Name: sys_feature_overrides sys_fo_uq; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_feature_overrides
    ADD CONSTRAINT sys_fo_uq UNIQUE (flag_id, scope_type, scope_id);


--
-- Name: sys_inbox_messages sys_inbox_messages_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_inbox_messages
    ADD CONSTRAINT sys_inbox_messages_pkey PRIMARY KEY (consumer, message_id);


--
-- Name: sys_outbox_messages sys_outbox_messages_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_outbox_messages
    ADD CONSTRAINT sys_outbox_messages_pkey PRIMARY KEY (id);


--
-- Name: sys_pubsub_cursors sys_pubsub_cursors_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_pubsub_cursors
    ADD CONSTRAINT sys_pubsub_cursors_pkey PRIMARY KEY (channel, subscriber);


--
-- Name: sys_pubsub_messages sys_pubsub_messages_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_pubsub_messages
    ADD CONSTRAINT sys_pubsub_messages_pkey PRIMARY KEY (id);


--
-- Name: sys_saved_view_scopes sys_saved_view_scopes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_saved_view_scopes
    ADD CONSTRAINT sys_saved_view_scopes_pkey PRIMARY KEY (entity_code, scope_key);


--
-- Name: sys_saved_views sys_saved_views_entity_code_default_scope_key_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_saved_views
    ADD CONSTRAINT sys_saved_views_entity_code_default_scope_key_unique UNIQUE (entity_code, default_scope_key);


--
-- Name: sys_saved_views sys_saved_views_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_saved_views
    ADD CONSTRAINT sys_saved_views_pkey PRIMARY KEY (id);


--
-- Name: sys_settings sys_settings_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_settings
    ADD CONSTRAINT sys_settings_pkey PRIMARY KEY (id);


--
-- Name: sys_settings sys_settings_uq; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_settings
    ADD CONSTRAINT sys_settings_uq UNIQUE NULLS NOT DISTINCT (namespace, key, scope_type, scope_id);


--
-- Name: sys_wakeup_requests sys_wakeup_requests_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_wakeup_requests
    ADD CONSTRAINT sys_wakeup_requests_pkey PRIMARY KEY (id);


--
-- Name: sys_wakeup_requests sys_wakeup_requests_workspace_scope_key_kind_request_key_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_wakeup_requests
    ADD CONSTRAINT sys_wakeup_requests_workspace_scope_key_kind_request_key_unique UNIQUE (workspace_scope_key, kind, request_key);


--
-- Name: sys_wakeup_requests sys_wakeup_requests_workspace_scope_key_request_key_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_wakeup_requests
    ADD CONSTRAINT sys_wakeup_requests_workspace_scope_key_request_key_unique UNIQUE (workspace_scope_key, request_key);


--
-- Name: sys_workflow_intents sys_workflow_intents_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_workflow_intents
    ADD CONSTRAINT sys_workflow_intents_pkey PRIMARY KEY (id);


--
-- Name: sys_workflow_intents sys_workflow_intents_request_key_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_workflow_intents
    ADD CONSTRAINT sys_workflow_intents_request_key_unique UNIQUE (request_key);


--
-- Name: tnt_membership_inbox tnt_membership_inbox_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tnt_membership_inbox
    ADD CONSTRAINT tnt_membership_inbox_pkey PRIMARY KEY (event_id);


--
-- Name: tnt_membership_pairs tnt_membership_pairs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tnt_membership_pairs
    ADD CONSTRAINT tnt_membership_pairs_pkey PRIMARY KEY (account_id, identity_id);


--
-- Name: tsk_projects tsk_projects_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tsk_projects
    ADD CONSTRAINT tsk_projects_pkey PRIMARY KEY (id);


--
-- Name: tsk_sprints tsk_sprints_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tsk_sprints
    ADD CONSTRAINT tsk_sprints_pkey PRIMARY KEY (id);


--
-- Name: tsk_stages tsk_stages_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tsk_stages
    ADD CONSTRAINT tsk_stages_pkey PRIMARY KEY (id);


--
-- Name: tsk_stages tsk_stages_project_id_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tsk_stages
    ADD CONSTRAINT tsk_stages_project_id_code_unique UNIQUE (project_id, code);


--
-- Name: tsk_task_targets tsk_task_targets_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tsk_task_targets
    ADD CONSTRAINT tsk_task_targets_pkey PRIMARY KEY (task_id, entity_type, entity_id);


--
-- Name: tsk_task_watchers tsk_task_watchers_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tsk_task_watchers
    ADD CONSTRAINT tsk_task_watchers_pkey PRIMARY KEY (task_id, user_id);


--
-- Name: tsk_tasks tsk_tasks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tsk_tasks
    ADD CONSTRAINT tsk_tasks_pkey PRIMARY KEY (id);


--
-- Name: tsk_time_logs tsk_time_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tsk_time_logs
    ADD CONSTRAINT tsk_time_logs_pkey PRIMARY KEY (id);


--
-- Name: tsk_timers tsk_timers_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tsk_timers
    ADD CONSTRAINT tsk_timers_pkey PRIMARY KEY (user_id);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: wsp_invitations wsp_invitations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wsp_invitations
    ADD CONSTRAINT wsp_invitations_pkey PRIMARY KEY (id);


--
-- Name: wsp_invitations wsp_invitations_token_hash_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wsp_invitations
    ADD CONSTRAINT wsp_invitations_token_hash_unique UNIQUE (token_hash);


--
-- Name: wsp_workspace_members wsp_workspace_members_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wsp_workspace_members
    ADD CONSTRAINT wsp_workspace_members_pkey PRIMARY KEY (id);


--
-- Name: wsp_workspace_members wsp_workspace_members_workspace_id_user_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wsp_workspace_members
    ADD CONSTRAINT wsp_workspace_members_workspace_id_user_id_unique UNIQUE (workspace_id, user_id);


--
-- Name: wsp_workspaces wsp_workspaces_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wsp_workspaces
    ADD CONSTRAINT wsp_workspaces_pkey PRIMARY KEY (id);


--
-- Name: aut_assignments_principal_ix; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX aut_assignments_principal_ix ON public.aut_role_assignments USING btree (principal_type, principal_id);


--
-- Name: aut_direct_grants_lookup; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX aut_direct_grants_lookup ON public.aut_direct_grants USING btree (principal_type, principal_id, permission_key);


--
-- Name: aut_role_matrix_entity_ix; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX aut_role_matrix_entity_ix ON public.aut_role_matrix USING btree (entity_key);


--
-- Name: aut_roles_slug_uq; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX aut_roles_slug_uq ON public.aut_roles USING btree (slug) WHERE (deleted_at IS NULL);


--
-- Name: corex_reference_records_scan_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX corex_reference_records_scan_idx ON public.corex_reference_records USING btree (snapshot_id, kind, normalized_name);


--
-- Name: corex_search_dirty_lane_key_submitted_at_sequence_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX corex_search_dirty_lane_key_submitted_at_sequence_index ON public.corex_search_dirty USING btree (lane_key, submitted_at, sequence);


--
-- Name: corex_transfer_jobs_state_updated_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX corex_transfer_jobs_state_updated_at_index ON public.corex_transfer_jobs USING btree (state, updated_at);


--
-- Name: crm_activities_kind_ix; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX crm_activities_kind_ix ON public.crm_activities USING btree (kind, status) WHERE (deleted_at IS NULL);


--
-- Name: crm_activities_planned_ix; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX crm_activities_planned_ix ON public.crm_activities USING btree (owner_id, starts_at) WHERE (((status)::text = 'planned'::text) AND (deleted_at IS NULL));


--
-- Name: crm_at_entity_ix; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX crm_at_entity_ix ON public.crm_activity_targets USING btree (entity_type, entity_id);


--
-- Name: crm_cc_company_ix; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX crm_cc_company_ix ON public.crm_contact_company USING btree (company_id);


--
-- Name: crm_cc_primary_uq; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX crm_cc_primary_uq ON public.crm_contact_company USING btree (contact_id) WHERE is_primary;


--
-- Name: crm_companies_emails_ix; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX crm_companies_emails_ix ON public.crm_companies USING gin (emails jsonb_path_ops);


--
-- Name: crm_companies_inn_ix; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX crm_companies_inn_ix ON public.crm_companies USING btree (inn) WHERE ((inn IS NOT NULL) AND (deleted_at IS NULL));


--
-- Name: crm_companies_owner_ix; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX crm_companies_owner_ix ON public.crm_companies USING btree (owner_id) WHERE (deleted_at IS NULL);


--
-- Name: crm_companies_phones_ix; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX crm_companies_phones_ix ON public.crm_companies USING gin (phones jsonb_path_ops);


--
-- Name: crm_companies_search_ix; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX crm_companies_search_ix ON public.crm_companies USING gin (search);


--
-- Name: crm_companies_ws_ix; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX crm_companies_ws_ix ON public.crm_companies USING btree (workspace_id) WHERE (deleted_at IS NULL);


--
-- Name: crm_contacts_emails_ix; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX crm_contacts_emails_ix ON public.crm_contacts USING gin (emails jsonb_path_ops);


--
-- Name: crm_contacts_owner_ix; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX crm_contacts_owner_ix ON public.crm_contacts USING btree (owner_id) WHERE (deleted_at IS NULL);


--
-- Name: crm_contacts_phones_ix; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX crm_contacts_phones_ix ON public.crm_contacts USING gin (phones jsonb_path_ops);


--
-- Name: crm_contacts_search_ix; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX crm_contacts_search_ix ON public.crm_contacts USING gin (search);


--
-- Name: crm_contacts_ws_ix; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX crm_contacts_ws_ix ON public.crm_contacts USING btree (workspace_id) WHERE (deleted_at IS NULL);


--
-- Name: crm_dc_contact_ix; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX crm_dc_contact_ix ON public.crm_deal_contacts USING btree (contact_id);


--
-- Name: crm_dc_primary_uq; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX crm_dc_primary_uq ON public.crm_deal_contacts USING btree (deal_id) WHERE is_primary;


--
-- Name: crm_deals_close_ix; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX crm_deals_close_ix ON public.crm_deals USING btree (close_date) WHERE (((status)::text = 'open'::text) AND (deleted_at IS NULL));


--
-- Name: crm_deals_company_ix; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX crm_deals_company_ix ON public.crm_deals USING btree (company_id) WHERE (deleted_at IS NULL);


--
-- Name: crm_deals_forecast_ix; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX crm_deals_forecast_ix ON public.crm_deals USING btree (workspace_id, owner_id, pipeline_id, close_date, forecast_category) WHERE (((status)::text = 'open'::text) AND (close_date IS NOT NULL) AND (deleted_at IS NULL));


--
-- Name: crm_deals_kanban_ix; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX crm_deals_kanban_ix ON public.crm_deals USING btree (pipeline_id, stage_id, "position") WHERE (deleted_at IS NULL);


--
-- Name: crm_deals_owner_ix; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX crm_deals_owner_ix ON public.crm_deals USING btree (owner_id, status) WHERE (deleted_at IS NULL);


--
-- Name: crm_deals_search_ix; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX crm_deals_search_ix ON public.crm_deals USING gin (search);


--
-- Name: crm_deals_ws_ix; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX crm_deals_ws_ix ON public.crm_deals USING btree (workspace_id) WHERE (deleted_at IS NULL);


--
-- Name: crm_leads_kanban_ix; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX crm_leads_kanban_ix ON public.crm_leads USING btree (pipeline_id, stage_id, "position") WHERE (deleted_at IS NULL);


--
-- Name: crm_leads_owner_ix; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX crm_leads_owner_ix ON public.crm_leads USING btree (owner_id, status) WHERE (deleted_at IS NULL);


--
-- Name: crm_leads_search_ix; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX crm_leads_search_ix ON public.crm_leads USING gin (search);


--
-- Name: crm_pipelines_code_uq; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX crm_pipelines_code_uq ON public.crm_pipelines USING btree (entity_type, code, workspace_id) NULLS NOT DISTINCT WHERE (deleted_at IS NULL);


--
-- Name: crm_pipelines_default_uq; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX crm_pipelines_default_uq ON public.crm_pipelines USING btree (entity_type, workspace_id) NULLS NOT DISTINCT WHERE (is_default AND (deleted_at IS NULL));


--
-- Name: crm_stages_pipeline_ix; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX crm_stages_pipeline_ix ON public.crm_stages USING btree (pipeline_id, "position") WHERE (deleted_at IS NULL);


--
-- Name: med_operations_state_next_attempt_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX med_operations_state_next_attempt_at_index ON public.med_operations USING btree (state, next_attempt_at);


--
-- Name: mod_ll_module_ix; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX mod_ll_module_ix ON public.mod_lifecycle_log USING btree (module_name, created_at DESC);


--
-- Name: mod_modules_state_ix; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX mod_modules_state_ix ON public.mod_modules USING btree (state);


--
-- Name: mod_records_module_seq_ix; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX mod_records_module_seq_ix ON public.mod_records USING btree (module_name, seq DESC);


--
-- Name: ntf_deliveries_scan_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ntf_deliveries_scan_idx ON public.ntf_deliveries USING btree (state, next_attempt);


--
-- Name: ntf_notifications_scan_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ntf_notifications_scan_idx ON public.ntf_notifications USING btree (recipient_kind, recipient_id, read_at, id);


--
-- Name: sessions_last_activity_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_last_activity_index ON public.sessions USING btree (last_activity);


--
-- Name: sessions_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_user_id_index ON public.sessions USING btree (user_id);


--
-- Name: sys_api_bulk_operations_claim_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sys_api_bulk_operations_claim_idx ON public.sys_api_bulk_operations USING btree (status, next_attempt_at, lease_until);


--
-- Name: sys_api_idempotency_retention_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sys_api_idempotency_retention_idx ON public.sys_api_idempotency USING btree (state, expires_at);


--
-- Name: sys_api_webhook_attempts_delivery_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sys_api_webhook_attempts_delivery_idx ON public.sys_api_webhook_attempts USING btree (delivery_id, started_at);


--
-- Name: sys_api_webhook_deliveries_claim_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sys_api_webhook_deliveries_claim_idx ON public.sys_api_webhook_deliveries USING btree (status, next_attempt_at, lease_until);


--
-- Name: sys_api_webhook_subscriptions_lookup_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sys_api_webhook_subscriptions_lookup_idx ON public.sys_api_webhook_subscriptions USING btree (status, topic, major);


--
-- Name: sys_attribute_backfills_attribute_id_to_revision_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sys_attribute_backfills_attribute_id_to_revision_index ON public.sys_attribute_backfills USING btree (attribute_id, to_revision);


--
-- Name: sys_attribute_definitions_entity_attribute_id_revision_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sys_attribute_definitions_entity_attribute_id_revision_index ON public.sys_attribute_definitions USING btree (entity, attribute_id, revision);


--
-- Name: sys_attribute_key_reservations_attribute_id_current_revision_in; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sys_attribute_key_reservations_attribute_id_current_revision_in ON public.sys_attribute_key_reservations USING btree (attribute_id, current_revision);


--
-- Name: sys_audit_actor_ix; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sys_audit_actor_ix ON public.sys_audit_log USING btree (actor_type, actor_id, occurred_at DESC);


--
-- Name: sys_audit_brin_ix; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sys_audit_brin_ix ON public.sys_audit_log USING brin (occurred_at);


--
-- Name: sys_audit_entity_ix; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sys_audit_entity_ix ON public.sys_audit_log USING btree (entity_type, entity_id, occurred_at DESC);


--
-- Name: sys_audit_seq_ix; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sys_audit_seq_ix ON public.sys_audit_log USING btree (seq);


--
-- Name: sys_audit_wsp_ix; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sys_audit_wsp_ix ON public.sys_audit_log USING btree (workspace_id, occurred_at DESC);


--
-- Name: sys_dept_path_gist; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sys_dept_path_gist ON public.sys_departments USING gist (path);


--
-- Name: sys_dept_wsp_ix; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sys_dept_wsp_ix ON public.sys_departments USING btree (workspace_id) WHERE (deleted_at IS NULL);


--
-- Name: sys_du_primary_uq; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX sys_du_primary_uq ON public.sys_department_user USING btree (user_id) WHERE is_primary;


--
-- Name: sys_inbox_messages_processed_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sys_inbox_messages_processed_idx ON public.sys_inbox_messages USING btree (processed_at);


--
-- Name: sys_outbox_messages_claim_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sys_outbox_messages_claim_idx ON public.sys_outbox_messages USING btree (available_at, occurred_at, id) WHERE ((status)::text = 'pending'::text);


--
-- Name: sys_outbox_messages_dead_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sys_outbox_messages_dead_idx ON public.sys_outbox_messages USING btree (dead_at, id) WHERE ((status)::text = 'dead'::text);


--
-- Name: sys_outbox_messages_published_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sys_outbox_messages_published_idx ON public.sys_outbox_messages USING btree (published_at, id) WHERE ((status)::text = 'published'::text);


--
-- Name: sys_outbox_messages_reclaim_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sys_outbox_messages_reclaim_idx ON public.sys_outbox_messages USING btree (lease_until, id) WHERE ((status)::text = 'publishing'::text);


--
-- Name: sys_pubsub_messages_channel_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sys_pubsub_messages_channel_id_index ON public.sys_pubsub_messages USING btree (channel, id);


--
-- Name: sys_settings_scope_ix; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sys_settings_scope_ix ON public.sys_settings USING btree (scope_type, scope_id);


--
-- Name: sys_wakeup_requests_scan_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sys_wakeup_requests_scan_idx ON public.sys_wakeup_requests USING btree (status, retry_at, fire_at);


--
-- Name: sys_workflow_intents_state_updated_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sys_workflow_intents_state_updated_at_index ON public.sys_workflow_intents USING btree (state, updated_at);


--
-- Name: tnt_membership_inbox_pending_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tnt_membership_inbox_pending_idx ON public.tnt_membership_inbox USING btree (lease_until, event_id) WHERE ((status)::text = 'pending'::text);


--
-- Name: tsk_projects_ws_ix; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tsk_projects_ws_ix ON public.tsk_projects USING btree (workspace_id, status) WHERE (deleted_at IS NULL);


--
-- Name: tsk_sprints_active_uq; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX tsk_sprints_active_uq ON public.tsk_sprints USING btree (project_id) WHERE ((status)::text = 'active'::text);


--
-- Name: tsk_tasks_assignee_ix; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tsk_tasks_assignee_ix ON public.tsk_tasks USING btree (assignee_id, status) WHERE (deleted_at IS NULL);


--
-- Name: tsk_tasks_due_ix; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tsk_tasks_due_ix ON public.tsk_tasks USING btree (due_at) WHERE (((status)::text = ANY ((ARRAY['open'::character varying, 'in_progress'::character varying, 'waiting'::character varying, 'review'::character varying])::text[])) AND (deleted_at IS NULL));


--
-- Name: tsk_tasks_kanban_ix; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tsk_tasks_kanban_ix ON public.tsk_tasks USING btree (project_id, stage_id, "position") WHERE (deleted_at IS NULL);


--
-- Name: tsk_tasks_review_ix; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tsk_tasks_review_ix ON public.tsk_tasks USING btree (author_id, assignee_id) WHERE (((status)::text = 'review'::text) AND (deleted_at IS NULL));


--
-- Name: tsk_tasks_search_ix; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tsk_tasks_search_ix ON public.tsk_tasks USING gin (search);


--
-- Name: tsk_tasks_sprint_ix; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tsk_tasks_sprint_ix ON public.tsk_tasks USING btree (sprint_id) WHERE (sprint_id IS NOT NULL);


--
-- Name: tsk_tl_task_ix; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tsk_tl_task_ix ON public.tsk_time_logs USING btree (task_id);


--
-- Name: tsk_tl_user_ix; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tsk_tl_user_ix ON public.tsk_time_logs USING btree (user_id, spent_on);


--
-- Name: tsk_tt_entity_ix; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tsk_tt_entity_ix ON public.tsk_task_targets USING btree (entity_type, entity_id);


--
-- Name: users_email_uq; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX users_email_uq ON public.users USING btree (lower((email)::text)) WHERE (deleted_at IS NULL);


--
-- Name: wsp_invitations_email_ix; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX wsp_invitations_email_ix ON public.wsp_invitations USING btree (lower((email)::text)) WHERE ((status)::text = 'pending'::text);


--
-- Name: wsp_members_default_uq; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX wsp_members_default_uq ON public.wsp_workspace_members USING btree (user_id) WHERE is_default;


--
-- Name: wsp_members_user_ix; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX wsp_members_user_ix ON public.wsp_workspace_members USING btree (user_id);


--
-- Name: wsp_workspaces_default_uq; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX wsp_workspaces_default_uq ON public.wsp_workspaces USING btree ((true)) WHERE (is_default AND (deleted_at IS NULL));


--
-- Name: wsp_workspaces_path_gist; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX wsp_workspaces_path_gist ON public.wsp_workspaces USING gist (path);


--
-- Name: wsp_workspaces_slug_uq; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX wsp_workspaces_slug_uq ON public.wsp_workspaces USING btree (slug) WHERE (deleted_at IS NULL);


--
-- Name: aut_role_assignments aut_role_assignments_granted_by_fk; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.aut_role_assignments
    ADD CONSTRAINT aut_role_assignments_granted_by_fk FOREIGN KEY (granted_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: aut_role_assignments aut_role_assignments_role_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.aut_role_assignments
    ADD CONSTRAINT aut_role_assignments_role_id_foreign FOREIGN KEY (role_id) REFERENCES public.aut_roles(id) ON DELETE CASCADE;


--
-- Name: aut_role_assignments aut_role_assignments_workspace_fk; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.aut_role_assignments
    ADD CONSTRAINT aut_role_assignments_workspace_fk FOREIGN KEY (workspace_id) REFERENCES public.wsp_workspaces(id) ON DELETE CASCADE;


--
-- Name: aut_role_matrix aut_role_matrix_role_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.aut_role_matrix
    ADD CONSTRAINT aut_role_matrix_role_id_foreign FOREIGN KEY (role_id) REFERENCES public.aut_roles(id) ON DELETE CASCADE;


--
-- Name: aut_roles aut_roles_cloned_from_fk; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.aut_roles
    ADD CONSTRAINT aut_roles_cloned_from_fk FOREIGN KEY (cloned_from_id) REFERENCES public.aut_roles(id) ON DELETE SET NULL;


--
-- Name: corex_export_manifests corex_export_manifests_job_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.corex_export_manifests
    ADD CONSTRAINT corex_export_manifests_job_id_foreign FOREIGN KEY (job_id) REFERENCES public.corex_transfer_jobs(id) ON DELETE RESTRICT;


--
-- Name: corex_export_parts corex_export_parts_manifest_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.corex_export_parts
    ADD CONSTRAINT corex_export_parts_manifest_id_foreign FOREIGN KEY (manifest_id) REFERENCES public.corex_export_manifests(id) ON DELETE RESTRICT;


--
-- Name: corex_rate_current corex_rate_current_snapshot_fk; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.corex_rate_current
    ADD CONSTRAINT corex_rate_current_snapshot_fk FOREIGN KEY (snapshot_id) REFERENCES public.corex_rate_snapshots(id) ON DELETE RESTRICT;


--
-- Name: corex_reference_aliases corex_reference_aliases_record_fk; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.corex_reference_aliases
    ADD CONSTRAINT corex_reference_aliases_record_fk FOREIGN KEY (snapshot_id, namespace, source_id) REFERENCES public.corex_reference_records(snapshot_id, namespace, source_id) ON DELETE RESTRICT;


--
-- Name: corex_reference_current corex_reference_current_snapshot_fk; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.corex_reference_current
    ADD CONSTRAINT corex_reference_current_snapshot_fk FOREIGN KEY (snapshot_id) REFERENCES public.corex_reference_datasets(snapshot_id) ON DELETE RESTRICT;


--
-- Name: corex_reference_records corex_reference_records_parent_fk; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.corex_reference_records
    ADD CONSTRAINT corex_reference_records_parent_fk FOREIGN KEY (snapshot_id, parent_namespace, parent_source_id) REFERENCES public.corex_reference_records(snapshot_id, namespace, source_id) ON DELETE RESTRICT;


--
-- Name: corex_reference_records corex_reference_records_snapshot_fk; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.corex_reference_records
    ADD CONSTRAINT corex_reference_records_snapshot_fk FOREIGN KEY (snapshot_id) REFERENCES public.corex_reference_datasets(snapshot_id) ON DELETE RESTRICT;


--
-- Name: corex_task_occurrences corex_task_occurrences_revision_fk; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.corex_task_occurrences
    ADD CONSTRAINT corex_task_occurrences_revision_fk FOREIGN KEY (series_key, immutable_revision) REFERENCES public.corex_task_series(series_key, immutable_revision) ON DELETE RESTRICT;


--
-- Name: corex_task_series_state corex_task_series_state_revision_fk; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.corex_task_series_state
    ADD CONSTRAINT corex_task_series_state_revision_fk FOREIGN KEY (series_key, active_revision) REFERENCES public.corex_task_series(series_key, immutable_revision) ON DELETE RESTRICT;


--
-- Name: corex_transfer_outbox corex_transfer_outbox_job_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.corex_transfer_outbox
    ADD CONSTRAINT corex_transfer_outbox_job_id_foreign FOREIGN KEY (job_id) REFERENCES public.corex_transfer_jobs(id) ON DELETE RESTRICT;


--
-- Name: corex_transfer_refs corex_transfer_refs_job_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.corex_transfer_refs
    ADD CONSTRAINT corex_transfer_refs_job_id_foreign FOREIGN KEY (job_id) REFERENCES public.corex_transfer_jobs(id) ON DELETE RESTRICT;


--
-- Name: corex_transfer_rows corex_transfer_rows_job_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.corex_transfer_rows
    ADD CONSTRAINT corex_transfer_rows_job_id_foreign FOREIGN KEY (job_id) REFERENCES public.corex_transfer_jobs(id) ON DELETE RESTRICT;


--
-- Name: crm_activities crm_activities_owner_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_activities
    ADD CONSTRAINT crm_activities_owner_id_foreign FOREIGN KEY (owner_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: crm_activities crm_activities_workspace_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_activities
    ADD CONSTRAINT crm_activities_workspace_id_foreign FOREIGN KEY (workspace_id) REFERENCES public.wsp_workspaces(id) ON DELETE CASCADE;


--
-- Name: crm_activity_targets crm_activity_targets_activity_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_activity_targets
    ADD CONSTRAINT crm_activity_targets_activity_id_foreign FOREIGN KEY (activity_id) REFERENCES public.crm_activities(id) ON DELETE CASCADE;


--
-- Name: crm_companies crm_companies_owner_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_companies
    ADD CONSTRAINT crm_companies_owner_id_foreign FOREIGN KEY (owner_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: crm_companies crm_companies_source_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_companies
    ADD CONSTRAINT crm_companies_source_id_foreign FOREIGN KEY (source_id) REFERENCES public.crm_sources(id) ON DELETE SET NULL;


--
-- Name: crm_companies crm_companies_workspace_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_companies
    ADD CONSTRAINT crm_companies_workspace_id_foreign FOREIGN KEY (workspace_id) REFERENCES public.wsp_workspaces(id) ON DELETE CASCADE;


--
-- Name: crm_contact_company crm_contact_company_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_contact_company
    ADD CONSTRAINT crm_contact_company_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.crm_companies(id) ON DELETE CASCADE;


--
-- Name: crm_contact_company crm_contact_company_contact_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_contact_company
    ADD CONSTRAINT crm_contact_company_contact_id_foreign FOREIGN KEY (contact_id) REFERENCES public.crm_contacts(id) ON DELETE CASCADE;


--
-- Name: crm_contacts crm_contacts_owner_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_contacts
    ADD CONSTRAINT crm_contacts_owner_id_foreign FOREIGN KEY (owner_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: crm_contacts crm_contacts_source_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_contacts
    ADD CONSTRAINT crm_contacts_source_id_foreign FOREIGN KEY (source_id) REFERENCES public.crm_sources(id) ON DELETE SET NULL;


--
-- Name: crm_contacts crm_contacts_workspace_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_contacts
    ADD CONSTRAINT crm_contacts_workspace_id_foreign FOREIGN KEY (workspace_id) REFERENCES public.wsp_workspaces(id) ON DELETE CASCADE;


--
-- Name: crm_deal_contacts crm_deal_contacts_contact_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_deal_contacts
    ADD CONSTRAINT crm_deal_contacts_contact_id_foreign FOREIGN KEY (contact_id) REFERENCES public.crm_contacts(id) ON DELETE CASCADE;


--
-- Name: crm_deal_contacts crm_deal_contacts_deal_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_deal_contacts
    ADD CONSTRAINT crm_deal_contacts_deal_id_foreign FOREIGN KEY (deal_id) REFERENCES public.crm_deals(id) ON DELETE CASCADE;


--
-- Name: crm_deals crm_deals_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_deals
    ADD CONSTRAINT crm_deals_company_id_foreign FOREIGN KEY (company_id) REFERENCES public.crm_companies(id) ON DELETE SET NULL;


--
-- Name: crm_deals crm_deals_lost_reason_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_deals
    ADD CONSTRAINT crm_deals_lost_reason_id_foreign FOREIGN KEY (lost_reason_id) REFERENCES public.crm_lost_reasons(id) ON DELETE SET NULL;


--
-- Name: crm_deals crm_deals_owner_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_deals
    ADD CONSTRAINT crm_deals_owner_id_foreign FOREIGN KEY (owner_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: crm_deals crm_deals_pipeline_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_deals
    ADD CONSTRAINT crm_deals_pipeline_id_foreign FOREIGN KEY (pipeline_id) REFERENCES public.crm_pipelines(id) ON DELETE RESTRICT;


--
-- Name: crm_deals crm_deals_source_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_deals
    ADD CONSTRAINT crm_deals_source_id_foreign FOREIGN KEY (source_id) REFERENCES public.crm_sources(id) ON DELETE SET NULL;


--
-- Name: crm_deals crm_deals_stage_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_deals
    ADD CONSTRAINT crm_deals_stage_id_foreign FOREIGN KEY (stage_id) REFERENCES public.crm_stages(id) ON DELETE RESTRICT;


--
-- Name: crm_deals crm_deals_workspace_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_deals
    ADD CONSTRAINT crm_deals_workspace_id_foreign FOREIGN KEY (workspace_id) REFERENCES public.wsp_workspaces(id) ON DELETE CASCADE;


--
-- Name: crm_leads crm_leads_converted_company_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_leads
    ADD CONSTRAINT crm_leads_converted_company_id_foreign FOREIGN KEY (converted_company_id) REFERENCES public.crm_companies(id) ON DELETE SET NULL;


--
-- Name: crm_leads crm_leads_converted_contact_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_leads
    ADD CONSTRAINT crm_leads_converted_contact_id_foreign FOREIGN KEY (converted_contact_id) REFERENCES public.crm_contacts(id) ON DELETE SET NULL;


--
-- Name: crm_leads crm_leads_converted_deal_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_leads
    ADD CONSTRAINT crm_leads_converted_deal_id_foreign FOREIGN KEY (converted_deal_id) REFERENCES public.crm_deals(id) ON DELETE SET NULL;


--
-- Name: crm_leads crm_leads_lost_reason_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_leads
    ADD CONSTRAINT crm_leads_lost_reason_id_foreign FOREIGN KEY (lost_reason_id) REFERENCES public.crm_lost_reasons(id) ON DELETE SET NULL;


--
-- Name: crm_leads crm_leads_owner_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_leads
    ADD CONSTRAINT crm_leads_owner_id_foreign FOREIGN KEY (owner_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: crm_leads crm_leads_pipeline_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_leads
    ADD CONSTRAINT crm_leads_pipeline_id_foreign FOREIGN KEY (pipeline_id) REFERENCES public.crm_pipelines(id) ON DELETE RESTRICT;


--
-- Name: crm_leads crm_leads_source_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_leads
    ADD CONSTRAINT crm_leads_source_id_foreign FOREIGN KEY (source_id) REFERENCES public.crm_sources(id) ON DELETE SET NULL;


--
-- Name: crm_leads crm_leads_stage_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_leads
    ADD CONSTRAINT crm_leads_stage_id_foreign FOREIGN KEY (stage_id) REFERENCES public.crm_stages(id) ON DELETE RESTRICT;


--
-- Name: crm_leads crm_leads_workspace_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_leads
    ADD CONSTRAINT crm_leads_workspace_id_foreign FOREIGN KEY (workspace_id) REFERENCES public.wsp_workspaces(id) ON DELETE CASCADE;


--
-- Name: crm_lost_reasons crm_lost_reasons_workspace_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_lost_reasons
    ADD CONSTRAINT crm_lost_reasons_workspace_id_foreign FOREIGN KEY (workspace_id) REFERENCES public.wsp_workspaces(id) ON DELETE CASCADE;


--
-- Name: crm_pipelines crm_pipelines_workspace_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_pipelines
    ADD CONSTRAINT crm_pipelines_workspace_id_foreign FOREIGN KEY (workspace_id) REFERENCES public.wsp_workspaces(id) ON DELETE CASCADE;


--
-- Name: crm_sources crm_sources_workspace_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_sources
    ADD CONSTRAINT crm_sources_workspace_id_foreign FOREIGN KEY (workspace_id) REFERENCES public.wsp_workspaces(id) ON DELETE CASCADE;


--
-- Name: crm_stages crm_stages_pipeline_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.crm_stages
    ADD CONSTRAINT crm_stages_pipeline_id_foreign FOREIGN KEY (pipeline_id) REFERENCES public.crm_pipelines(id) ON DELETE CASCADE;


--
-- Name: med_conversions med_conversions_file_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.med_conversions
    ADD CONSTRAINT med_conversions_file_id_foreign FOREIGN KEY (file_id) REFERENCES public.med_files(id) ON DELETE RESTRICT;


--
-- Name: med_operations med_operations_file_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.med_operations
    ADD CONSTRAINT med_operations_file_id_foreign FOREIGN KEY (file_id) REFERENCES public.med_files(id) ON DELETE RESTRICT;


--
-- Name: med_relations med_relations_file_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.med_relations
    ADD CONSTRAINT med_relations_file_id_foreign FOREIGN KEY (file_id) REFERENCES public.med_files(id) ON DELETE RESTRICT;


--
-- Name: ntf_deliveries ntf_deliveries_digest_run_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ntf_deliveries
    ADD CONSTRAINT ntf_deliveries_digest_run_id_foreign FOREIGN KEY (digest_run_id) REFERENCES public.ntf_digest_runs(id) ON DELETE RESTRICT;


--
-- Name: ntf_deliveries ntf_deliveries_notification_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ntf_deliveries
    ADD CONSTRAINT ntf_deliveries_notification_id_foreign FOREIGN KEY (notification_id) REFERENCES public.ntf_notifications(id) ON DELETE RESTRICT;


--
-- Name: ntf_digest_items ntf_digest_items_notification_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ntf_digest_items
    ADD CONSTRAINT ntf_digest_items_notification_id_foreign FOREIGN KEY (notification_id) REFERENCES public.ntf_notifications(id) ON DELETE RESTRICT;


--
-- Name: ntf_digest_items ntf_digest_items_run_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ntf_digest_items
    ADD CONSTRAINT ntf_digest_items_run_id_foreign FOREIGN KEY (run_id) REFERENCES public.ntf_digest_runs(id) ON DELETE RESTRICT;


--
-- Name: sys_department_user sys_department_user_department_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_department_user
    ADD CONSTRAINT sys_department_user_department_id_foreign FOREIGN KEY (department_id) REFERENCES public.sys_departments(id) ON DELETE CASCADE;


--
-- Name: sys_departments sys_dept_head_user_fk; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_departments
    ADD CONSTRAINT sys_dept_head_user_fk FOREIGN KEY (head_user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: sys_departments sys_dept_parent_fk; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_departments
    ADD CONSTRAINT sys_dept_parent_fk FOREIGN KEY (parent_id) REFERENCES public.sys_departments(id) ON DELETE RESTRICT;


--
-- Name: sys_departments sys_dept_workspace_fk; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_departments
    ADD CONSTRAINT sys_dept_workspace_fk FOREIGN KEY (workspace_id) REFERENCES public.wsp_workspaces(id) ON DELETE RESTRICT;


--
-- Name: sys_department_user sys_du_user_fk; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_department_user
    ADD CONSTRAINT sys_du_user_fk FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: sys_feature_overrides sys_feature_overrides_flag_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_feature_overrides
    ADD CONSTRAINT sys_feature_overrides_flag_id_foreign FOREIGN KEY (flag_id) REFERENCES public.sys_feature_flags(id) ON DELETE CASCADE;


--
-- Name: sys_saved_views sys_saved_views_entity_code_scope_key_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sys_saved_views
    ADD CONSTRAINT sys_saved_views_entity_code_scope_key_foreign FOREIGN KEY (entity_code, scope_key) REFERENCES public.sys_saved_view_scopes(entity_code, scope_key);


--
-- Name: tsk_projects tsk_projects_owner_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tsk_projects
    ADD CONSTRAINT tsk_projects_owner_id_foreign FOREIGN KEY (owner_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: tsk_projects tsk_projects_workspace_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tsk_projects
    ADD CONSTRAINT tsk_projects_workspace_id_foreign FOREIGN KEY (workspace_id) REFERENCES public.wsp_workspaces(id) ON DELETE CASCADE;


--
-- Name: tsk_sprints tsk_sprints_project_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tsk_sprints
    ADD CONSTRAINT tsk_sprints_project_id_foreign FOREIGN KEY (project_id) REFERENCES public.tsk_projects(id) ON DELETE CASCADE;


--
-- Name: tsk_stages tsk_stages_project_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tsk_stages
    ADD CONSTRAINT tsk_stages_project_id_foreign FOREIGN KEY (project_id) REFERENCES public.tsk_projects(id) ON DELETE CASCADE;


--
-- Name: tsk_task_targets tsk_task_targets_task_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tsk_task_targets
    ADD CONSTRAINT tsk_task_targets_task_id_foreign FOREIGN KEY (task_id) REFERENCES public.tsk_tasks(id) ON DELETE CASCADE;


--
-- Name: tsk_task_watchers tsk_task_watchers_task_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tsk_task_watchers
    ADD CONSTRAINT tsk_task_watchers_task_id_foreign FOREIGN KEY (task_id) REFERENCES public.tsk_tasks(id) ON DELETE CASCADE;


--
-- Name: tsk_task_watchers tsk_task_watchers_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tsk_task_watchers
    ADD CONSTRAINT tsk_task_watchers_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: tsk_tasks tsk_tasks_assignee_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tsk_tasks
    ADD CONSTRAINT tsk_tasks_assignee_id_foreign FOREIGN KEY (assignee_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: tsk_tasks tsk_tasks_author_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tsk_tasks
    ADD CONSTRAINT tsk_tasks_author_id_foreign FOREIGN KEY (author_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: tsk_tasks tsk_tasks_parent_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tsk_tasks
    ADD CONSTRAINT tsk_tasks_parent_id_foreign FOREIGN KEY (parent_id) REFERENCES public.tsk_tasks(id) ON DELETE CASCADE;


--
-- Name: tsk_tasks tsk_tasks_project_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tsk_tasks
    ADD CONSTRAINT tsk_tasks_project_id_foreign FOREIGN KEY (project_id) REFERENCES public.tsk_projects(id) ON DELETE SET NULL;


--
-- Name: tsk_tasks tsk_tasks_sprint_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tsk_tasks
    ADD CONSTRAINT tsk_tasks_sprint_id_foreign FOREIGN KEY (sprint_id) REFERENCES public.tsk_sprints(id) ON DELETE SET NULL;


--
-- Name: tsk_tasks tsk_tasks_stage_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tsk_tasks
    ADD CONSTRAINT tsk_tasks_stage_id_foreign FOREIGN KEY (stage_id) REFERENCES public.tsk_stages(id) ON DELETE SET NULL;


--
-- Name: tsk_tasks tsk_tasks_workspace_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tsk_tasks
    ADD CONSTRAINT tsk_tasks_workspace_id_foreign FOREIGN KEY (workspace_id) REFERENCES public.wsp_workspaces(id) ON DELETE CASCADE;


--
-- Name: tsk_time_logs tsk_time_logs_task_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tsk_time_logs
    ADD CONSTRAINT tsk_time_logs_task_id_foreign FOREIGN KEY (task_id) REFERENCES public.tsk_tasks(id) ON DELETE CASCADE;


--
-- Name: tsk_time_logs tsk_time_logs_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tsk_time_logs
    ADD CONSTRAINT tsk_time_logs_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: tsk_timers tsk_timers_task_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tsk_timers
    ADD CONSTRAINT tsk_timers_task_id_foreign FOREIGN KEY (task_id) REFERENCES public.tsk_tasks(id) ON DELETE CASCADE;


--
-- Name: tsk_timers tsk_timers_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tsk_timers
    ADD CONSTRAINT tsk_timers_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: users users_department_fk; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_department_fk FOREIGN KEY (department_id) REFERENCES public.sys_departments(id) ON DELETE SET NULL;


--
-- Name: wsp_invitations wsp_invitations_invited_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wsp_invitations
    ADD CONSTRAINT wsp_invitations_invited_by_foreign FOREIGN KEY (invited_by) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: wsp_invitations wsp_invitations_workspace_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wsp_invitations
    ADD CONSTRAINT wsp_invitations_workspace_id_foreign FOREIGN KEY (workspace_id) REFERENCES public.wsp_workspaces(id) ON DELETE CASCADE;


--
-- Name: wsp_workspace_members wsp_workspace_members_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wsp_workspace_members
    ADD CONSTRAINT wsp_workspace_members_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: wsp_workspace_members wsp_workspace_members_workspace_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wsp_workspace_members
    ADD CONSTRAINT wsp_workspace_members_workspace_id_foreign FOREIGN KEY (workspace_id) REFERENCES public.wsp_workspaces(id) ON DELETE CASCADE;


--
-- Name: wsp_workspaces wsp_workspaces_parent_fk; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.wsp_workspaces
    ADD CONSTRAINT wsp_workspaces_parent_fk FOREIGN KEY (parent_id) REFERENCES public.wsp_workspaces(id) ON DELETE RESTRICT;


--
-- PostgreSQL database dump complete
--

\unrestrict qjLyk9myM6vUn9ARXYyPcQevYjeaXJTJOpKPjzRcdeGPYHhqXasPrkVvhyOA4qf


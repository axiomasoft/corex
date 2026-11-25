CREATE TABLE crm_sources (
  id           uuid PRIMARY KEY,
  workspace_id uuid REFERENCES wsp_workspaces(id) ON DELETE CASCADE,
  code         varchar(64) NOT NULL,
  name         jsonb NOT NULL,
  is_active    boolean NOT NULL DEFAULT true,
  position bigint NOT NULL DEFAULT 0,
  created_at timestamptz NOT NULL DEFAULT now(), updated_at timestamptz NOT NULL DEFAULT now(),
  UNIQUE NULLS NOT DISTINCT (workspace_id, code)
);

CREATE TABLE crm_lost_reasons (
  id           uuid PRIMARY KEY,
  workspace_id uuid REFERENCES wsp_workspaces(id) ON DELETE CASCADE,
  code         varchar(64) NOT NULL,
  name         jsonb NOT NULL,
  entity_type  varchar(16) NOT NULL DEFAULT 'both' CHECK (entity_type IN ('lead','deal','both')),
  is_active    boolean NOT NULL DEFAULT true,
  position bigint NOT NULL DEFAULT 0,
  created_at timestamptz NOT NULL DEFAULT now(), updated_at timestamptz NOT NULL DEFAULT now(),
  UNIQUE NULLS NOT DISTINCT (workspace_id, code)
);

CREATE TABLE crm_pipelines (
  id           uuid PRIMARY KEY,
  workspace_id uuid REFERENCES wsp_workspaces(id) ON DELETE CASCADE,
  entity_type  varchar(64) NOT NULL,
  code         varchar(64) NOT NULL,
  name         jsonb NOT NULL,
  is_default   boolean NOT NULL DEFAULT false,
  position bigint NOT NULL DEFAULT 0,
  settings     jsonb NOT NULL DEFAULT '{}',
  created_at timestamptz NOT NULL DEFAULT now(), updated_at timestamptz NOT NULL DEFAULT now(),
  deleted_at   timestamptz
);

CREATE TABLE crm_stages (
  id              uuid PRIMARY KEY,
  pipeline_id     uuid NOT NULL REFERENCES crm_pipelines(id) ON DELETE CASCADE,
  code            varchar(64) NOT NULL,
  name            jsonb NOT NULL,
  color           varchar(16),
  semantic        varchar(8) NOT NULL DEFAULT 'open' CHECK (semantic IN ('open','won','lost')),
  probability     smallint NOT NULL DEFAULT 0 CHECK (probability BETWEEN 0 AND 100),
  position bigint NOT NULL DEFAULT 0,
  required_fields jsonb NOT NULL DEFAULT '[]',
  settings        jsonb NOT NULL DEFAULT '{}',
  created_at timestamptz NOT NULL DEFAULT now(), updated_at timestamptz NOT NULL DEFAULT now(),
  deleted_at      timestamptz,
  UNIQUE (pipeline_id, code)
);

CREATE TABLE crm_companies (
  id           uuid PRIMARY KEY,
  workspace_id uuid REFERENCES wsp_workspaces(id) ON DELETE CASCADE,
  name         varchar(255) NOT NULL,
  legal_name   varchar(255),
  domain       varchar(190),
  inn          varchar(12),
  kpp          varchar(9),
  ogrn         varchar(15),
  phones       jsonb NOT NULL DEFAULT '[]',
  emails       jsonb NOT NULL DEFAULT '[]',
  address      jsonb,
  bank_details jsonb,
  source_id    uuid REFERENCES crm_sources(id) ON DELETE SET NULL,
  owner_id     uuid REFERENCES users(id) ON DELETE SET NULL,
  created_by   jsonb NOT NULL,
  search       tsvector,
  created_at timestamptz NOT NULL DEFAULT now(), updated_at timestamptz NOT NULL DEFAULT now(),
  deleted_at   timestamptz
);

CREATE TABLE crm_contacts (
  id           uuid PRIMARY KEY,
  workspace_id uuid REFERENCES wsp_workspaces(id) ON DELETE CASCADE,
  name_last    varchar(120),
  name_first   varchar(120),
  name_middle  varchar(120),
  display_name varchar(255) NOT NULL,
  job_title    varchar(190),
  phones       jsonb NOT NULL DEFAULT '[]',
  emails       jsonb NOT NULL DEFAULT '[]',
  birthday     date,
  source_id    uuid REFERENCES crm_sources(id) ON DELETE SET NULL,
  owner_id     uuid REFERENCES users(id) ON DELETE SET NULL,
  created_by   jsonb NOT NULL,
  search       tsvector,
  created_at timestamptz NOT NULL DEFAULT now(), updated_at timestamptz NOT NULL DEFAULT now(),
  deleted_at   timestamptz
);

CREATE TABLE crm_contact_company (
  id         uuid PRIMARY KEY,
  contact_id uuid NOT NULL REFERENCES crm_contacts(id)  ON DELETE CASCADE,
  company_id uuid NOT NULL REFERENCES crm_companies(id) ON DELETE CASCADE,
  label      varchar(64),
  is_primary boolean NOT NULL DEFAULT false,
  created_at timestamptz NOT NULL DEFAULT now(), updated_at timestamptz NOT NULL DEFAULT now(),
  UNIQUE (contact_id, company_id)
);

CREATE TABLE crm_leads (
  id               uuid PRIMARY KEY,
  workspace_id     uuid REFERENCES wsp_workspaces(id) ON DELETE CASCADE,
  title            varchar(255) NOT NULL,
  status           varchar(16) NOT NULL DEFAULT 'open'
                   CHECK (status IN ('open','converted','junk')),
  pipeline_id      uuid REFERENCES crm_pipelines(id) ON DELETE RESTRICT,
  stage_id         uuid REFERENCES crm_stages(id)    ON DELETE RESTRICT,
  contact_name     varchar(255),
  phone            varchar(64),
  email            varchar(255),
  company_name     varchar(255),
  amount           bigint,
  currency         char(3) NOT NULL DEFAULT 'RUB',
  source_id        uuid REFERENCES crm_sources(id) ON DELETE SET NULL,
  utm              jsonb,
  lost_reason_id   uuid REFERENCES crm_lost_reasons(id) ON DELETE SET NULL,
  owner_id         uuid REFERENCES users(id) ON DELETE SET NULL,
  created_by       jsonb NOT NULL,
  converted_at     timestamptz,
  converted_deal_id    uuid,
  converted_contact_id uuid REFERENCES crm_contacts(id)  ON DELETE SET NULL,
  converted_company_id uuid REFERENCES crm_companies(id) ON DELETE SET NULL,
  position         numeric(20,10) NOT NULL DEFAULT 0,
  search           tsvector,
  created_at timestamptz NOT NULL DEFAULT now(), updated_at timestamptz NOT NULL DEFAULT now(),
  deleted_at       timestamptz,
  CHECK (status <> 'converted' OR converted_at IS NOT NULL)
);

CREATE TABLE crm_deals (
  id             uuid PRIMARY KEY,
  workspace_id   uuid REFERENCES wsp_workspaces(id) ON DELETE CASCADE,
  title          varchar(255) NOT NULL,
  status         varchar(8) NOT NULL DEFAULT 'open'
                 CHECK (status IN ('open','won','lost')),
  pipeline_id    uuid NOT NULL REFERENCES crm_pipelines(id) ON DELETE RESTRICT,
  stage_id       uuid NOT NULL REFERENCES crm_stages(id)    ON DELETE RESTRICT,
  amount         bigint,
  currency       char(3) NOT NULL DEFAULT 'RUB',
  close_date     date,
  won_at         timestamptz,
  lost_at        timestamptz,
  lost_reason_id uuid REFERENCES crm_lost_reasons(id) ON DELETE SET NULL,
  company_id     uuid REFERENCES crm_companies(id) ON DELETE SET NULL,
  source_id      uuid REFERENCES crm_sources(id)   ON DELETE SET NULL,
  utm            jsonb,
  owner_id       uuid REFERENCES users(id) ON DELETE SET NULL,
  primary_quote_doc_id uuid,
  forecast_category varchar(16)
                   CHECK (forecast_category IN ('pipeline','best_case','commit','omitted')),
  forecast_category_is_manual boolean NOT NULL DEFAULT false,
  created_by     jsonb NOT NULL,
  stage_entered_at timestamptz NOT NULL DEFAULT now(),
  position       numeric(20,10) NOT NULL DEFAULT 0,
  search         tsvector,
  created_at timestamptz NOT NULL DEFAULT now(), updated_at timestamptz NOT NULL DEFAULT now(),
  deleted_at     timestamptz,
  CHECK (status <> 'won'  OR won_at  IS NOT NULL),
  CHECK (status <> 'lost' OR lost_at IS NOT NULL)
);

CREATE TABLE crm_deal_contacts (
  id         uuid PRIMARY KEY,
  deal_id    uuid NOT NULL REFERENCES crm_deals(id)    ON DELETE CASCADE,
  contact_id uuid NOT NULL REFERENCES crm_contacts(id) ON DELETE CASCADE,
  label      varchar(64),
  is_primary boolean NOT NULL DEFAULT false,
  created_at timestamptz NOT NULL DEFAULT now(), updated_at timestamptz NOT NULL DEFAULT now(),
  UNIQUE (deal_id, contact_id)
);

CREATE TABLE crm_activities (
  id            uuid PRIMARY KEY,
  workspace_id  uuid REFERENCES wsp_workspaces(id) ON DELETE CASCADE,
  kind          varchar(16) NOT NULL CHECK (kind IN ('call','email','meeting')),
  status        varchar(16) NOT NULL DEFAULT 'planned' CHECK (status IN ('planned','done','canceled','missed')),
  subject       varchar(255),
  body          text,
  direction     varchar(3) CHECK (direction IN ('in','out')),
  phone         varchar(64),
  duration_sec bigint,
  location      varchar(255),
  email_meta    jsonb,
  recording_file_id uuid,
  transcript    text,
  starts_at     timestamptz,
  ends_at       timestamptz,
  owner_id      uuid REFERENCES users(id) ON DELETE SET NULL,
  created_by    jsonb NOT NULL,
  created_at timestamptz NOT NULL DEFAULT now(), updated_at timestamptz NOT NULL DEFAULT now(),
  deleted_at    timestamptz,
  CHECK (kind = 'call' OR direction IS NULL OR kind = 'email')
);

CREATE TABLE crm_activity_targets (
  activity_id uuid NOT NULL REFERENCES crm_activities(id) ON DELETE CASCADE,
  entity_type varchar(64) NOT NULL,
  entity_id   varchar(64) NOT NULL,
  created_at  timestamptz NOT NULL DEFAULT now(),
  PRIMARY KEY (activity_id, entity_type, entity_id)
);

ALTER TABLE crm_leads ADD CONSTRAINT crm_leads_conv_deal_fk FOREIGN KEY (converted_deal_id) REFERENCES crm_deals(id) ON DELETE SET NULL;

CREATE UNIQUE INDEX crm_pipelines_code_uq ON crm_pipelines (entity_type, code, workspace_id)
  NULLS NOT DISTINCT WHERE deleted_at IS NULL;

CREATE UNIQUE INDEX crm_pipelines_default_uq ON crm_pipelines (entity_type, workspace_id)
  NULLS NOT DISTINCT WHERE is_default AND deleted_at IS NULL;

CREATE INDEX crm_stages_pipeline_ix ON crm_stages (pipeline_id, position) WHERE deleted_at IS NULL;

CREATE INDEX crm_companies_inn_ix    ON crm_companies (inn) WHERE inn IS NOT NULL AND deleted_at IS NULL;

CREATE INDEX crm_companies_owner_ix  ON crm_companies (owner_id) WHERE deleted_at IS NULL;

CREATE INDEX crm_companies_ws_ix     ON crm_companies (workspace_id) WHERE deleted_at IS NULL;

CREATE INDEX crm_companies_search_ix ON crm_companies USING GIN (search);

CREATE INDEX crm_companies_phones_ix ON crm_companies USING GIN (phones jsonb_path_ops);

CREATE INDEX crm_companies_emails_ix ON crm_companies USING GIN (emails jsonb_path_ops);

CREATE INDEX crm_contacts_owner_ix  ON crm_contacts (owner_id) WHERE deleted_at IS NULL;

CREATE INDEX crm_contacts_ws_ix     ON crm_contacts (workspace_id) WHERE deleted_at IS NULL;

CREATE INDEX crm_contacts_search_ix ON crm_contacts USING GIN (search);

CREATE INDEX crm_contacts_phones_ix ON crm_contacts USING GIN (phones jsonb_path_ops);

CREATE INDEX crm_contacts_emails_ix ON crm_contacts USING GIN (emails jsonb_path_ops);

CREATE UNIQUE INDEX crm_cc_primary_uq ON crm_contact_company (contact_id) WHERE is_primary;

CREATE INDEX crm_cc_company_ix ON crm_contact_company (company_id);

CREATE INDEX crm_leads_kanban_ix ON crm_leads (pipeline_id, stage_id, position) WHERE deleted_at IS NULL;

CREATE INDEX crm_leads_owner_ix  ON crm_leads (owner_id, status) WHERE deleted_at IS NULL;

CREATE INDEX crm_leads_search_ix ON crm_leads USING GIN (search);

CREATE INDEX crm_deals_kanban_ix  ON crm_deals (pipeline_id, stage_id, position) WHERE deleted_at IS NULL;

CREATE INDEX crm_deals_owner_ix   ON crm_deals (owner_id, status) WHERE deleted_at IS NULL;

CREATE INDEX crm_deals_company_ix ON crm_deals (company_id) WHERE deleted_at IS NULL;

CREATE INDEX crm_deals_ws_ix      ON crm_deals (workspace_id) WHERE deleted_at IS NULL;

CREATE INDEX crm_deals_close_ix   ON crm_deals (close_date) WHERE status = 'open' AND deleted_at IS NULL;

CREATE INDEX crm_deals_search_ix  ON crm_deals USING GIN (search);

CREATE INDEX crm_deals_forecast_ix ON crm_deals (workspace_id, owner_id, pipeline_id, close_date, forecast_category)
  WHERE status = 'open' AND close_date IS NOT NULL AND deleted_at IS NULL;

CREATE UNIQUE INDEX crm_dc_primary_uq ON crm_deal_contacts (deal_id) WHERE is_primary;

CREATE INDEX crm_dc_contact_ix ON crm_deal_contacts (contact_id);

CREATE INDEX crm_activities_planned_ix ON crm_activities (owner_id, starts_at)
  WHERE status = 'planned' AND deleted_at IS NULL;

CREATE INDEX crm_activities_kind_ix ON crm_activities (kind, status) WHERE deleted_at IS NULL;

CREATE INDEX crm_at_entity_ix ON crm_activity_targets (entity_type, entity_id);

-- Referencing-side indexes: parent deletes and key updates look child rows up by these columns.
CREATE INDEX crm_companies_source_id_ix ON crm_companies (source_id);
CREATE INDEX crm_contacts_source_id_ix ON crm_contacts (source_id);
CREATE INDEX crm_pipelines_workspace_id_ix ON crm_pipelines (workspace_id);
CREATE INDEX crm_leads_converted_company_id_ix ON crm_leads (converted_company_id);
CREATE INDEX crm_leads_converted_contact_id_ix ON crm_leads (converted_contact_id);
CREATE INDEX crm_leads_converted_deal_id_ix ON crm_leads (converted_deal_id);
CREATE INDEX crm_leads_lost_reason_id_ix ON crm_leads (lost_reason_id);
CREATE INDEX crm_leads_source_id_ix ON crm_leads (source_id);
CREATE INDEX crm_leads_stage_id_ix ON crm_leads (stage_id);
CREATE INDEX crm_leads_workspace_id_ix ON crm_leads (workspace_id);
CREATE INDEX crm_deals_lost_reason_id_ix ON crm_deals (lost_reason_id);
CREATE INDEX crm_deals_source_id_ix ON crm_deals (source_id);
CREATE INDEX crm_deals_stage_id_ix ON crm_deals (stage_id);
CREATE INDEX crm_activities_workspace_id_ix ON crm_activities (workspace_id);

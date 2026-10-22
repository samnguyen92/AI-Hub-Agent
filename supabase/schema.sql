-- Align AI Hub schema for Supabase PostgreSQL.
-- Generated from packages/api/app/db/models.py, reference_models.py and Alembic revisions.
-- Import this file in Supabase SQL Editor for a new/empty project.
-- This creates application tables only; it does not migrate existing database rows.
-- Project workspace ZIP snapshots use a private Storage bucket. Only the API's
-- SUPABASE_SERVICE_ROLE_KEY accesses objects; no browser policies are created.

begin;

create table if not exists public.users (
    id varchar(36) primary key,
    github_login varchar(100) not null,
    role varchar(20) not null default 'member',
    created_at timestamptz not null default now()
);
create unique index if not exists ix_users_github_login on public.users (github_login);

create table if not exists public.agents (
    key varchar(50) primary key,
    model varchar(100) not null,
    rules_path varchar(500) not null,
    config jsonb not null default '{}'::jsonb
);

create table if not exists public.global_rules (
    id varchar(36) primary key,
    text text not null,
    priority integer not null default 10,
    enabled boolean not null default true,
    created_at timestamptz not null default now()
);

create table if not exists public.projects (
    id varchar(36) primary key,
    owner_id varchar(36) not null references public.users(id) on delete cascade,
    name varchar(120) not null,
    is_demo boolean not null default false,
    source_url varchar(500),
    workspace_path varchar(500) not null,
    created_at timestamptz not null default now()
);
create index if not exists ix_projects_owner_id on public.projects (owner_id);
create index if not exists ix_projects_name on public.projects (name);
create index if not exists ix_projects_is_demo on public.projects (is_demo);

create table if not exists public.telegram_links (
    id varchar(36) primary key,
    user_id varchar(36) not null references public.users(id) on delete cascade,
    telegram_id varchar(64),
    link_code varchar(16),
    is_confirmed boolean not null default false,
    expires_at timestamptz,
    attempts integer not null default 0,
    linked_at timestamptz not null default now()
);
create unique index if not exists ix_telegram_links_telegram_id on public.telegram_links (telegram_id);
create unique index if not exists ix_telegram_links_link_code on public.telegram_links (link_code);
create index if not exists ix_telegram_links_user_id on public.telegram_links (user_id);

create table if not exists public.skills (
    key varchar(100) primary key,
    version varchar(20) not null default '1.0.0',
    path varchar(500) not null,
    agent_key varchar(50) references public.agents(key) on delete set null
);

create table if not exists public.threads (
    id varchar(36) primary key,
    project_id varchar(36) not null references public.projects(id) on delete cascade,
    agent_key varchar(50) not null references public.agents(key),
    channel varchar(20) not null default 'web',
    title varchar(200) not null default 'New Thread',
    created_at timestamptz not null default now()
);
create index if not exists ix_threads_project_id on public.threads (project_id);

create table if not exists public.messages (
    id varchar(36) primary key,
    thread_id varchar(36) not null references public.threads(id) on delete cascade,
    role varchar(20) not null,
    content text not null,
    attachments jsonb not null default '[]'::jsonb,
    artifact_refs jsonb not null default '[]'::jsonb,
    created_at timestamptz not null default now()
);
create index if not exists ix_messages_thread_id on public.messages (thread_id);

create table if not exists public.runs (
    id varchar(36) primary key,
    thread_id varchar(36) not null references public.threads(id) on delete cascade,
    agent_key varchar(50) not null,
    parent_run_id varchar(36),
    status varchar(30) not null default 'queued',
    checkpoint_state jsonb,
    error_detail text,
    cost_usd double precision not null default 0.0,
    created_at timestamptz not null default now()
);
create index if not exists ix_runs_thread_id on public.runs (thread_id);
create index if not exists ix_runs_status on public.runs (status);

create table if not exists public.steps (
    id varchar(36) primary key,
    run_id varchar(36) not null references public.runs(id) on delete cascade,
    seq integer not null,
    tool varchar(80) not null,
    args jsonb not null default '{}'::jsonb,
    result jsonb not null default '{}'::jsonb,
    tokens integer not null default 0,
    created_at timestamptz not null default now()
);
create index if not exists ix_steps_run_id on public.steps (run_id);

create table if not exists public.artifacts (
    id varchar(36) primary key,
    project_id varchar(36) not null references public.projects(id) on delete cascade,
    artifact_id varchar(100) not null,
    version integer not null default 1,
    parent_version integer,
    run_id varchar(36) references public.runs(id) on delete set null,
    path varchar(500) not null,
    git_commit varchar(40),
    created_at timestamptz not null default now()
);
create index if not exists ix_artifacts_project_id on public.artifacts (project_id);
create index if not exists ix_artifacts_artifact_id on public.artifacts (artifact_id);

create table if not exists public.approvals (
    id varchar(36) primary key,
    run_id varchar(36) not null references public.runs(id) on delete cascade,
    decided_by varchar(36) references public.users(id) on delete set null,
    status varchar(20) not null default 'pending',
    action_type varchar(50) not null default 'export_zip',
    payload jsonb not null default '{}'::jsonb,
    comment text,
    created_at timestamptz not null default now()
);
create index if not exists ix_approvals_run_id on public.approvals (run_id);

create table if not exists public.project_notes (
    id varchar(36) primary key,
    project_id varchar(36) not null references public.projects(id) on delete cascade,
    content text not null,
    status varchar(20) not null default 'pending',
    source_run_id varchar(36) references public.runs(id) on delete set null,
    created_at timestamptz not null default now()
);
create index if not exists ix_project_notes_project_id on public.project_notes (project_id);

create table if not exists public.library_items (
    id varchar(36) primary key,
    project_id varchar(36) not null references public.projects(id) on delete cascade,
    type varchar(50) not null,
    tag varchar(50),
    description text,
    path varchar(500) not null,
    created_at timestamptz not null default now()
);
create index if not exists ix_library_items_project_id on public.library_items (project_id);

create table if not exists public.design_references (
    id varchar(36) primary key,
    project_id varchar(36) not null references public.projects(id) on delete cascade,
    source_url varchar(1000) not null,
    title varchar(200) not null,
    notes text,
    tags jsonb not null default '[]'::jsonb,
    desktop_path varchar(500) not null,
    mobile_path varchar(500) not null,
    snapshot_path varchar(500) not null,
    page_summary text,
    captured_at timestamptz not null default now()
);
create index if not exists ix_design_references_project_id on public.design_references (project_id);

-- Enable RLS with no browser-facing policies. The backend connects server-side as
-- the database owner, which can access these tables; the browser must use the API.
alter table public.users enable row level security;
alter table public.agents enable row level security;
alter table public.global_rules enable row level security;
alter table public.projects enable row level security;
alter table public.telegram_links enable row level security;
alter table public.skills enable row level security;
alter table public.threads enable row level security;
alter table public.messages enable row level security;
alter table public.runs enable row level security;
alter table public.steps enable row level security;
alter table public.artifacts enable row level security;
alter table public.approvals enable row level security;
alter table public.project_notes enable row level security;
alter table public.library_items enable row level security;
alter table public.design_references enable row level security;

-- The API seeds/updates its agent rows at startup; these are not inserted here.
-- Keep the database credential private and never expose it to the browser.

insert into storage.buckets (id, name, public, file_size_limit, allowed_mime_types)
values ('hub-workspaces', 'hub-workspaces', false, 41943040, array['application/zip'])
on conflict (id) do update set
    public = excluded.public,
    file_size_limit = excluded.file_size_limit,
    allowed_mime_types = excluded.allowed_mime_types;

commit;

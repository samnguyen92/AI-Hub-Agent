# Supabase database and workspace storage

`schema.sql` creates the current application tables and a private `hub-workspaces`
Storage bucket. Run it on a new Supabase project; if you already imported the earlier
AI Hub schema, run this updated version again so it adds the Storage bucket.

## Setup

1. In Supabase, open **SQL Editor**, run `schema.sql`.
2. In **Project Settings → Database → Connect**, copy the IPv4 **Transaction pooler**
   connection string (shared pooler, port `6543`). Keep the exact username Supabase
   provides (commonly `postgres.<project-ref>`). It works for the Vercel API and
   daemon. The API engine limits itself to one connection per instance and disables
   asyncpg prepared statement caching for transaction mode.
3. Set `DATABASE_URL` on the API and worker to the async SQLAlchemy form:
   `postgresql+asyncpg://USER:PASSWORD@HOST:PORT/postgres?ssl=require`.
   Keep the supplied host, port, database, username and password, and percent-encode
   reserved password characters. Do not expose this value through `NEXT_PUBLIC_*`.
4. Set these API and worker variables to enable workspace snapshots:

   ```env
   SUPABASE_URL=https://<project-ref>.supabase.co
   SUPABASE_SERVICE_ROLE_KEY=<service-role-key>
   SUPABASE_STORAGE_BUCKET=hub-workspaces
   ```

   `SUPABASE_SERVICE_ROLE_KEY` is a server-only secret. Never add it to the web
   environment or a `NEXT_PUBLIC_*` variable.
5. Configure `AUTH_SECRET_KEY` (at least 32 characters), `OPENROUTER_API_KEY`,
   `ADMIN_API_TOKEN`, and any OAuth/provider credentials used by the deployment.
6. Redeploy API and worker after setting the variables.

If you apply `schema.sql` directly, mark its complete schema as the current migration
revision before using future migrations: run `alembic stamp head` from the API package
in an environment with `DATABASE_URL` configured. Do not run `alembic upgrade head`
against a database created directly from `schema.sql` before stamping it; the initial
migration creates tables already present in the SQL script.

## Workspace data behavior

On every workspace commit, the API stores a ZIP snapshot of project files, excluding
disposable `.git` history and Python caches, at
`projects/<project-id>/workspace.zip`. HTML and file endpoints restore that
snapshot to the function's temporary directory before serving it. This makes files
survive Vercel instance replacement. The private bucket and application default limit
are 40 MiB per archive, leaving headroom under Supabase Free's 50 MB global limit.
Raise both the SQL bucket limit and `WORKSPACE_ARCHIVE_MAX_BYTES` together if your
Supabase plan allows larger uploads.

Existing local database rows and workspace files are not copied automatically. To
preserve projects, chat history, agents and artifacts, migrate the local DB data into
an empty Supabase schema created from `schema.sql` before the first production API
deployment. Pause chat writes during cutover and take a local backup. Then export data
from the running Docker database:

```bash
docker compose exec -T postgres pg_dump -U postgres -d hub_ai_agent \
  --data-only --schema=public --exclude-table=public.alembic_version --format=custom \
  > /tmp/hub-ai-data.dump
```

Restore the data into Supabase's Transaction pooler. Replace the username and host
with the exact pooler values from Supabase; `--password` prompts without putting the
database password in shell history:

```bash
pg_restore --data-only --no-owner --no-privileges --single-transaction --exit-on-error \
  --dbname 'postgresql://postgres.<project-ref>@<pooler-host>:6543/postgres?sslmode=require' \
  --password /tmp/hub-ai-data.dump
```

After the data restore, run `alembic stamp head` once as described above. If the local
schema is older than `schema.sql`, upgrade or reconcile it before exporting; do not
run this restore into a Supabase database that already contains user data. Keep the
dump private and remove it after confirming the migration.

With the Supabase URL, key and bucket set in local `.env`, upload the Docker workspace
volume before switching production traffic:

```bash
docker compose exec api python -m scripts.upload_workspace_snapshots
```

The script reports uploaded project IDs and never prints credentials. Local Docker
continues to use `/workspaces`; Supabase snapshots activate when the URL and service
role key are present. Remove or blank those two Supabase storage variables from the
local `.env` after the one-time upload if local development should stay isolated from
production files; keep them configured only in Vercel.

The API uses its own authentication. Keep RLS enabled and do not expose app tables or
the Storage bucket directly to browsers unless access is separately integrated with
Supabase Auth and policies.

# Deploy the full AI Hub on Vercel with Supabase

The root `vercel.json` deploys the Next.js web app, FastAPI API, Playwright sandbox
container, and database-polling worker. Supabase provides PostgreSQL and private
workspace archive storage. Local development continues to use Docker Compose.
Vercel Services and Daemons are currently in public beta; confirm both features are
available for the selected Vercel project before switching production traffic.

## Vercel project setup

1. Import the repository as one Vercel project. Set **Root Directory** to `./` and
   **Framework Preset** to **Services**. Services and Daemons must be enabled for
   the Vercel project/team.
2. Add the environment values below to **Production**, then deploy. Enable Preview
   only after configuring an isolated Supabase database/bucket and a Preview-specific
   `SANDBOX_URL`; otherwise Preview deployments can consume production jobs and files.
3. Copy the production domain and set `SANDBOX_URL` to
   `https://<production-domain>/internal/sandbox`. Redeploy so the background daemon
   can reach the authenticated sandbox route. The API uses its private Vercel service
   binding automatically.

The worker Daemon is pinned to Vercel's Singapore region (`sin1`) for Vietnam/Southeast
Asia. Set the Vercel project's Functions region to the same region when the Supabase
project is in Singapore; otherwise choose the Vercel region closest to the Supabase
database.

Do not import `packages/api`, `packages/sandbox`, and `packages/web` as three separate
Vercel projects. The root Services configuration builds them together and routes API
and sandbox traffic.

## Required Vercel environment variables

| Name | Value |
| --- | --- |
| `DATABASE_URL` | Supabase **Transaction pooler** string on port `6543`, converted to `postgresql+asyncpg://.../postgres?ssl=require`; preserve its pooler username (often `postgres.<project-ref>`) |
| `SUPABASE_URL` | `https://<project-ref>.supabase.co` |
| `SUPABASE_SERVICE_ROLE_KEY` | Supabase server-only service role key; keep private |
| `SUPABASE_STORAGE_BUCKET` | `hub-workspaces` |
| `WORKSPACE_ARCHIVE_MAX_BYTES` | `41943040` (40 MiB default; raise bucket limit too for larger Supabase plans) |
| `AUTH_SECRET_KEY` | Random secret with at least 32 characters |
| `ADMIN_API_TOKEN` | Long random token |
| `AUTH_MODE` | `strict` or `google`; do not use `dev` in production |
| `ENVIRONMENT` | `production` |
| `NEXT_PUBLIC_AUTH_MODE` | `strict` or `google`; defaults to `strict` on Vercel |
| `OPENROUTER_API_KEY` | OpenRouter API key |
| `NEXT_PUBLIC_API_URL` | `/api/backend` (defaults to this on Vercel) |
| `SANDBOX_SHARED_SECRET` | Random shared secret of at least 32 characters |
| `SANDBOX_URL` | `https://<production-domain>/internal/sandbox` (used by the daemon) |
| `WORKSPACES_ROOT` | `/tmp/hub-workspaces` |

For Google sign-in, also set `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`,
`GOOGLE_ALLOWED_EMAILS`, `GOOGLE_ADMIN_EMAILS`, `GOOGLE_REDIRECT_URI`, and
`GOOGLE_FRONTEND_URL`. The redirect URL is
`https://<production-domain>/api/backend/auth/google/callback`.

`SANDBOX_INTERNAL_URL` is injected by the service binding; do not enter it manually.
Vercel injects `INTERNAL_API_URL` for the web service binding as well. The worker
daemon uses `SANDBOX_URL` because it cannot use that API service binding here.

## Supabase setup

Run `supabase/schema.sql` in Supabase SQL Editor before deploying. Then, from the API
package in an environment with the Supabase `DATABASE_URL`, run `alembic stamp head`
once so later migrations start from the schema that SQL created. Apply later database
changes with `alembic upgrade head`.

Workspace archives are uploaded after Git commits. Existing Docker volume files are
not copied automatically. Before cutover, add the Supabase storage settings to local
`.env` and run `docker compose exec api python -m scripts.upload_workspace_snapshots`.
The default bucket limit is 40 MiB per project archive to fit under Supabase Free's
50 MB global upload limit. Avoid giving the browser the service role key or direct
access to this bucket.

## Current Vercel constraints

Services route public paths to the API and sandbox; the API-to-sandbox call uses a
private service binding. Daemons continuously poll the existing database-backed job
queue. Vercel services do not share local disks, so project files are synchronized by
private ZIP snapshots in Supabase Storage. Concurrent runs that modify the same
project may contend on the same snapshot; serialize those runs if this becomes common.

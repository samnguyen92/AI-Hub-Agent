# Vercel deployment note

Deploy the complete AI Hub from the repository root using the Services and Daemons
configuration in `../../vercel.json`. Do not deploy `packages/web` as a standalone
Vercel project: that only publishes the Next.js frontend and leaves the API and
sandbox unavailable. See `../../docs/deployment/vercel-supabase.md` for setup and
required Supabase/Vercel environment values.

## Vercel environment variables

The web service uses these values in the full-stack deployment:

| Variable | Value |
| --- | --- |
| `NEXT_PUBLIC_API_URL` | `/api/backend` (same-origin endpoint routed to the API service) |
| `NEXT_PUBLIC_AUTH_MODE` | `google` when production sign-in uses Google OAuth; do not set `dev` on a public deployment |

`NEXT_PUBLIC_API_URL` is public and is embedded in browser code. Never put API
keys, passwords, or signing secrets in a `NEXT_PUBLIC_*` variable.

## Local development

The browser calls the same-origin `/api/backend` path. Docker's Next.js server
rewrites that path to `INTERNAL_API_URL=http://api:8000`, avoiding browser CORS and
host-specific `localhost` addresses.

# Deploy the web app to Vercel

## Vercel project settings

- Framework preset: **Next.js**
- Root Directory: `packages/web`
- Build command: `npm run build`
- Install command: `npm ci`
- Output directory: leave the Next.js default (`.next`)

The repository includes `vercel.json` in this directory. Vercel requires the
backend origin below at build time; the build fails with a direct message if it
is missing.

## Vercel environment variables

Set these for Production and for Preview if you use preview deployments:

| Variable | Value |
| --- | --- |
| `NEXT_PUBLIC_API_URL` | Public HTTPS origin for the deployed FastAPI backend, e.g. `https://api.example.com` (no path or trailing slash) |
| `NEXT_PUBLIC_AUTH_MODE` | `google` when production sign-in uses Google OAuth; do not set `dev` on a public deployment |

`NEXT_PUBLIC_API_URL` is public and is embedded in browser code. Never put API
keys, passwords, or signing secrets in a `NEXT_PUBLIC_*` variable.

## Backend configuration required for the deployed frontend

These belong on the backend host, not Vercel:

- `ENVIRONMENT=production`
- `AUTH_MODE=google`
- `AUTH_SECRET_KEY` — stable random secret of at least 32 characters
- `CORS_ORIGINS` — comma-separated production frontend origin(s), such as `https://your-app.vercel.app,https://www.example.com`
- `GOOGLE_FRONTEND_URL` — production frontend origin
- `GOOGLE_REDIRECT_URI` — `https://<backend-domain>/auth/google/callback`
- `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET`
- `GOOGLE_ALLOWED_EMAILS` and `GOOGLE_ADMIN_EMAILS`
- `DATABASE_URL` and `OPENROUTER_API_KEY`

Register the exact backend callback URL in Google OAuth. After Vercel assigns a
production domain or custom domain, add that exact frontend origin to backend
`CORS_ORIGINS` and redeploy/restart the backend. Preview deployments need their
own allowed origin if they must call the production backend; the API currently
accepts exact origins rather than Vercel wildcard patterns.

## Local development

`INTERNAL_API_URL` takes precedence for the Docker web container. Outside Docker,
the Next.js rewrite defaults to `http://localhost:8000`.

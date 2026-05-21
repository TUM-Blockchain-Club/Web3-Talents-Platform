# Phase 5: Deployment

Status: in progress.

## Deployment Target

Use Vercel for both deployed projects:

- `apps/web`: Next.js admin frontend.
- `apps/api`: Fastify API backend.

Vercel supports monorepos by creating one Vercel project per app directory. Vercel also supports Fastify apps when the entrypoint is named `src/server.ts`, which this API already uses.

## Vercel Projects

Create two projects from the same GitHub repository.

### Web Project

- Root Directory: `apps/web`
- Framework Preset: Next.js
- Build Command: default, or `npm run build`
- Environment Variables:
  - `ADMIN_PASSWORD`
  - `NEXT_PUBLIC_API_BASE_URL`

`ADMIN_PASSWORD` is required in production. Local development still falls back to `change-me`.

### API Project

- Root Directory: `apps/api`
- Framework Preset: Other, or Fastify if shown
- Build Command: default, or `npm run build`
- Environment Variables:
  - `DISCORD_BOT_TOKEN`
  - `CORS_ORIGIN`

Set `CORS_ORIGIN` to the deployed web URL, for example:

```text
https://web3-talents-platform.vercel.app
```

The API build runs the shared core package build first, so the workspace dependency is available for Vercel.
The API project includes `apps/api/vercel.json` so Vercel runs install and build commands from the monorepo root. Without that, Vercel may build `apps/api` as a standalone package and fail to resolve `@web3-talents/core`.

## Deployment Order

1. Create and deploy the API project first.
2. Copy the API production URL.
3. Create or update the web project with `NEXT_PUBLIC_API_BASE_URL` set to the API URL.
4. Set `CORS_ORIGIN` in the API project to the web production URL.
5. Redeploy both projects after environment variables are set.

## Production Smoke Test

After both projects are deployed:

- Open the web production URL.
- Confirm Basic Auth prompts for `admin` and the configured `ADMIN_PASSWORD`.
- Upload a small roster CSV.
- Fetch a known Discord poll.
- Generate rooms.
- Download the internal Excel file.
- Download the Zoom CSV file.

## Remaining Phase 5 Work

- Create both Vercel projects.
- Add production environment variables.
- Redeploy after URLs are known.
- Run the production smoke test.
- Record the deployed URLs in this file.

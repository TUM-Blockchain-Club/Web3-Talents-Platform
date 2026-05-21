# Web3 Talents Platform

Internal admin platform for generating weekly jigsaw-learning breakout room assignments from participant spreadsheets and Discord poll results.

## Repository Structure

```text
apps/
  web/       # Next.js admin frontend, deploys to Vercel
  api/       # Node/Fastify backend, deploys to Vercel
packages/
  core/      # Shared TypeScript types and grouping logic
docs/        # Requirements, implementation plan, phase plan
```

## Planned Stack

- Frontend: Next.js, TypeScript, Tailwind CSS
- Backend: Node.js, TypeScript, Fastify
- File handling: read-excel-file, write-excel-file, csv-parse, csv-stringify
- Shared logic: TypeScript package in `packages/core`
- Database: Supabase Postgres for later persistence
- Deployment: Vercel for frontend and backend

## Local Development

Install dependencies:

```bash
npm install
```

Run all apps in development mode:

```bash
npm run dev
```

Run checks:

```bash
npm run typecheck
npm run build
```

## Environment

Copy `.env.example` into local environment files as needed. Discord bot credentials and Supabase service credentials must only be used by the backend.

## Vercel Deployment

Create two Vercel projects from this monorepo:

- Web project root directory: `apps/web`
- API project root directory: `apps/api`

Set the web project environment variables:

```text
ADMIN_PASSWORD=<strong admin password>
NEXT_PUBLIC_API_BASE_URL=https://<api-project>.vercel.app
```

Set the API project environment variables:

```text
CORS_ORIGIN=https://<web-project>.vercel.app
DISCORD_BOT_TOKEN=<discord bot token>
```

The API project builds `@web3-talents/core` before compiling itself so Vercel can deploy the workspace dependency.
Both Vercel projects include a `vercel.json` file that runs install and build commands from the monorepo root, which lets Vercel resolve npm workspace packages correctly.

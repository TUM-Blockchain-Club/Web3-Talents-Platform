# Web3 Talents Platform

Internal admin platform for generating weekly jigsaw-learning breakout room assignments from participant spreadsheets and Discord poll results.

## Repository Structure

```text
apps/
  web/       # Next.js admin frontend, deploys to Vercel
  api/       # Node/Fastify backend, deploys to Railway
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
- Deployment: Vercel for frontend, Railway for backend

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

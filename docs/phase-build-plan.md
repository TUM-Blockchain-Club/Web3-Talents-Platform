# Web3 Talents Platform Phase Build Plan

## 1. Planned Tech Stack

### Frontend

- Framework: Next.js with TypeScript.
- Hosting: Vercel.
- UI styling: Tailwind CSS.
- UI components: shadcn/ui or simple custom components.
- File upload: browser upload to backend API.

Reasoning:

- Next.js deploys cleanly on Vercel.
- TypeScript helps keep import, grouping, and export data structures reliable.
- Tailwind and shadcn/ui are fast enough for an internal admin tool without overbuilding the design system.

### Backend

- Runtime: Node.js with TypeScript.
- Framework: Fastify or Express.
- Hosting: Railway.
- Discord integration: Discord REST API using bot token.
- Excel parsing/generation: `read-excel-file` and `write-excel-file`.
- CSV generation: lightweight CSV writer.

Reasoning:

- Node/TypeScript lets the frontend, backend, and shared grouping logic use the same types.
- Railway is a good fit for a persistent backend API with secrets.
- Discord credentials stay on the backend only.

### Shared Core Package

- Package: `packages/core`.
- Language: TypeScript.
- Responsibility:
  - Participant validation.
  - Partner-group vote resolution.
  - Balanced topic assignment.
  - Room generation.
  - Export row generation.

Reasoning:

- The most important logic can be tested without the frontend, backend, Discord, Vercel, or Railway.
- This keeps the core workflow reliable before external integrations are added.

### Database

- Provider: Supabase Postgres.
- MVP usage: minimal or optional persistence.
- Future usage:
  - Saved sessions.
  - Participants.
  - Partner groups.
  - Poll history.
  - Statuses.
  - Applications.

Reasoning:

- The MVP is spreadsheet-driven, so a heavy database model is not required immediately.
- Supabase gives a clean path to persistent data later.

### Authentication

- MVP option: simple admin password.
- Alternative: Google login if straightforward.

Recommendation:

- Start with a simple admin password for speed.
- Move to Google login once the workflow is proven.

## 2. Proposed Repository Structure

```text
apps/
  web/       # Next.js admin frontend, deployed to Vercel
  api/       # Node/TypeScript backend, deployed to Railway
packages/
  core/      # shared grouping, validation, and export data logic
docs/
  project-requirements.md
  implementation-plan.md
  phase-build-plan.md
```

## 3. Phase Plan

Each phase ends with a checkpoint. I will stop after the checkpoint and ask you to confirm whether the result is acceptable before moving on.

## Phase 0: Project Scaffold

Goal:

- Create the monorepo structure and base tooling.

Build:

- `apps/web`
- `apps/api`
- `packages/core`
- TypeScript configuration.
- Basic formatting/linting setup if practical.
- Basic README update with local commands.

Validation:

- Install dependencies.
- Run typecheck/build for empty scaffold.

Checkpoint question:

- Confirm the repo structure and stack before implementation starts.

Estimated time:

- 0.5 to 1 day.

## Phase 1: Core Grouping Engine

Goal:

- Build the heart of the platform without UI or Discord dependency.

Build:

- Participant type definitions.
- Topic type definitions.
- Vote type definitions.
- Participant validation.
- Partner-group validation.
- Vote-to-partner-group resolution.
- Split-vote balancing.
- Non-voter balanced topic assignment.
- Dynamic `Room1` to `RoomN` generation.
- Export-ready row generation.

Tests:

- Pair votes same topic.
- Pair split vote.
- Only one partner votes.
- Group of three majority vote.
- Group of three no majority.
- Nobody in partner group votes.
- Invalid partner group size.
- Duplicate emails.

Checkpoint question:

- Confirm the algorithm behavior using sample scenarios before connecting it to files or Discord.

Estimated time:

- 2 to 4 days.

## Phase 2: File Import And Export Backend

Goal:

- Let the backend parse participant files and generate downloadable outputs.

Build:

- Excel `.xlsx` parser.
- CSV parser.
- Import preview endpoint.
- Validation warnings and errors.
- Internal Excel export.
- Zoom CSV export with only:
  - `Pre-assign Room Name`
  - `Email Address`

Validation:

- Test against sample Excel and CSV files.
- Confirm Zoom CSV format matches current weekly upload format.

Checkpoint question:

- Confirm imported participants and generated export files look correct before adding Discord.

Estimated time:

- 2 to 3 days.

## Phase 3: Discord Poll Sync

Goal:

- Pull poll answers and voters from Discord using a pasted poll message link.

Build:

- Discord poll message link parser.
- Discord API client.
- Poll preview endpoint.
- Topic extraction from poll answers.
- Voter extraction by poll answer.
- Vote-to-participant matching by Discord username or user ID.
- Unmatched voter warnings.
- Non-voter warnings.

Validation:

- Test with a real Discord poll from your server.
- Confirm topics are pulled correctly.
- Confirm voters map to imported participants.

Checkpoint question:

- Confirm Discord sync works reliably enough for MVP, or decide whether to keep a manual fallback path active.

Estimated time:

- 2 to 5 days.

Main risk:

- Discord permissions or API access may take extra setup time.

## Phase 4: Admin Frontend MVP

Goal:

- Build the internal admin web flow.

Build:

- Upload participant Excel/CSV screen.
- Import preview.
- Weekly session screen.
- Discord poll link input.
- Topic preview/edit fields.
- Room generation screen.
- Dynamic `Room1` to `RoomN` assignment preview.
- Manual partner-group movement between rooms.
- Export buttons.
- Simple admin password protection.

Validation:

- Full workflow can be completed from the browser.
- Admin can generate and download both required files.

Checkpoint question:

- Confirm the admin workflow is usable before deployment work.

Estimated time:

- 4 to 7 days.

## Phase 5: Deployment

Goal:

- Deploy the MVP so your team can use it.

Build:

- Deploy frontend to Vercel.
- Deploy backend to Railway.
- Configure environment variables.
- Configure CORS.
- Set up Supabase project for future persistence.
- Add basic deployment documentation.

Validation:

- Production frontend can call production backend.
- Discord sync works in deployed environment.
- Export downloads work in deployed environment.
- Admin protection works.

Checkpoint question:

- Confirm the deployed internal admin app is usable for a real weekly workflow.

Estimated time:

- 1 to 3 days.

## Phase 6: MVP Hardening

Goal:

- Fix issues from real usage and improve reliability.

Build:

- Better error messages.
- Better validation states.
- Better unmatched voter handling.
- Basic logs for backend failures.
- Additional tests for real edge cases.
- Manual fallback for poll results if Discord sync is temporarily unavailable.

Validation:

- Run through at least one real weekly planning cycle.
- Confirm Zoom upload works with generated CSV.

Checkpoint question:

- Confirm MVP is stable enough for routine weekly use.

Estimated time:

- 2 to 5 days.

## 4. Rough Timeline

Assuming one lead developer implementing P0 work:

| Phase | Estimate |
| --- | --- |
| Phase 0: Scaffold | 0.5-1 day |
| Phase 1: Core grouping engine | 2-4 days |
| Phase 2: File import/export backend | 2-3 days |
| Phase 3: Discord poll sync | 2-5 days |
| Phase 4: Admin frontend MVP | 4-7 days |
| Phase 5: Deployment | 1-3 days |
| Phase 6: Hardening | 2-5 days |

Total estimate:

- Fast path: about 2 weeks.
- Realistic path: about 3 weeks.
- Conservative path with Discord/deployment issues: 4 weeks.

## 5. Suggested Task Split

### Lead Developer

- Own all P0 phases.
- Make architecture decisions.
- Implement core logic and backend integrations.
- Review all teammate contributions.

### Technical Teammate

Best tasks after Phase 1 or Phase 2:

- Add unit tests for core grouping cases.
- Improve frontend components after the admin flow exists.
- Add cleaner warning displays.
- Add saved-session support later.
- Build P1 participant/status screens later.

### Non-Technical Teammate

Best tasks immediately:

- Create the final merged participant Excel template.
- Fill in real participant data.
- Confirm all Discord usernames are present.
- Create test Discord polls.
- Write expected results for sample scenarios.
- Test generated Zoom CSV files.
- Prepare future applicant questions and Google Drive organization notes.

## 6. Review Gate Policy

After each phase, I will pause and ask:

- Does the result match the requirement?
- Are the assumptions still correct?
- Should anything be changed before the next phase?

No next phase should start until the previous phase is accepted, unless you explicitly ask to continue.

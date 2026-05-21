# Project Progress

This file is the durable handoff point for future sessions. Update it whenever a phase part is completed.

## Current Position

- Current phase: Phase 5 next.
- Last completed phase: Phase 4, Admin Frontend MVP.
- Last checkpoint file: `docs/phase-4-admin-frontend.md`.

## Phase Checklist

### Phase 0: Project Scaffold

Status: complete.

Completed parts:

- Monorepo structure created with `apps/web`, `apps/api`, and `packages/core`.
- Root TypeScript and workspace scripts configured.
- README updated with stack and local commands.

### Phase 1: Core Grouping Engine

Status: complete.

Completed parts:

- Shared participant, topic, vote, partner-group, assignment, room, and export row types.
- Roster and topic validation.
- Vote-to-participant matching by Discord user ID or username.
- Partner-group topic assignment for same vote, single vote, majority vote, split vote, and no-vote balancing.
- Dynamic `Room1` to `RoomN` generation.
- Manual partner-group room movement helper.
- Internal Excel and Zoom CSV row builders.
- Core tests covering the main assignment scenarios.

### Phase 2: File Import And Export Backend

Status: complete.

Completed parts:

- CSV participant import preview.
- Excel `.xlsx` and `.xlsm` participant import preview.
- Header alias support for common spreadsheet labels.
- Import validation errors and warnings returned before confirmation.
- Partner-group summary returned in import preview.
- Assignment generation API endpoint.
- Internal Excel export endpoint.
- Zoom CSV export endpoint with exactly `Pre-assign Room Name` and `Email Address`.
- API tests for import parsing, validation behavior, unsupported file types, Excel export, and exact Zoom CSV shape.
- Checkpoint recorded in `docs/phase-2-checkpoint.md`.

### Phase 3: Discord Poll Sync

Status: complete.

Completed parts:

- Discord poll message link parser.
- Discord API client using backend-only bot credentials.
- Poll preview endpoint implementation.
- Topic extraction from Discord poll answers.
- Voter extraction by poll answer.
- Vote-to-participant mapping using imported Discord username or user ID.
- Unmatched voter and non-voter warnings.
- Local `.env` loading for the API server.
- Clear error guidance when Discord omits poll data because Message Content intent is missing or the link is not the original poll message.
- Poll-result message links are resolved to the original poll message when Discord includes a reference.
- Real Discord poll validated successfully against the target server.
- Phase note recorded in `docs/phase-3-discord-poll-sync.md`.

Next parts:

- Build the browser admin workflow in Phase 4.
- Consider a manual vote import fallback if Discord reliability becomes a real operational risk.

### Phase 4: Admin Frontend MVP

Status: complete.

Completed parts:

- Browser workflow shell for importing roster files, fetching Discord polls, editing topic labels, generating rooms, adjusting partner-group room placement, and downloading exports.
- Simple HTTP Basic admin protection for the web app.
- Roster import and UI template now use one `Name` column for first and last name, plus numeric partner-group labels such as `1`, `2`, and `3`.
- Admin UI v5 uses CSS-backed centered workflow cards, clearer Discord poll spacing, a warmer background, colored action buttons, and a full-width room-review area.
- Admin can set the breakout room count; assignment generation caps each topic to one partner group per room and rebalances overflow groups to topics with available capacity.
- Export downloads use a delayed object URL cleanup so browser downloads reliably start.
- Internal Excel export uses a formatted buddy-group sheet grouped by topic.
- Full test, typecheck, and production build passed before checkpoint.
- Checkpoint recorded in `docs/phase-4-admin-frontend.md`.

### Phase 5: Deployment

Status: not started.

### Phase 6: MVP Hardening

Status: not started.

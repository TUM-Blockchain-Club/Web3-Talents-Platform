# Project Progress

This file is the durable handoff point for future sessions. Update it whenever a phase part is completed.

## Current Position

- Current phase: Phase 3 next.
- Last completed phase: Phase 2, File Import And Export Backend.
- Last checkpoint file: `docs/phase-2-checkpoint.md`.

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

Status: not started.

Next parts:

- Discord poll message link parser.
- Discord API client using backend-only bot credentials.
- Poll preview endpoint implementation.
- Topic extraction from Discord poll answers.
- Voter extraction by poll answer.
- Vote-to-participant mapping using imported Discord username or user ID.
- Unmatched voter and non-voter warnings.

### Phase 4: Admin Frontend MVP

Status: not started.

### Phase 5: Deployment

Status: not started.

### Phase 6: MVP Hardening

Status: not started.

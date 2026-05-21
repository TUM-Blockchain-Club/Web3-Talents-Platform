# Web3 Talents Platform Implementation Plan

## 1. MVP Goal

Build an internal admin web app that turns a participant/partner-group Excel file and a Discord poll link into:

- A previewable weekly room assignment.
- A rich internal Excel export.
- A Zoom-compatible breakout pre-assignment CSV.

The MVP does not need participant accounts, applicant intake, attendance tracking, content hosting, presentation uploads, or a permanent participant-status database.

## 2. Ownership Model

### Lead Developer: P0

The lead developer should implement all P0 work:

- Project setup and architecture.
- Admin-only access.
- Excel/CSV parsing.
- Discord poll sync.
- Partner-group topic assignment algorithm.
- Breakout room generation.
- Manual override support.
- Internal Excel export.
- Zoom CSV export.
- Deployment configuration for Vercel, Railway, and Supabase.

### Technical Teammate: P1/P2

The technical teammate should take bounded follow-up work after the P0 patterns are established:

- Improve UI components and styling.
- Add tests for import validation and grouping edge cases.
- Build P1 participant database/status screens.
- Build P1 applicant review screens.
- Build future attendance/availability UI.
- Add richer empty states, validation states, and error states.

### Non-Technical Teammate: P1/P2 Support

The non-technical teammate should focus on work that improves quality without requiring code ownership:

- Prepare realistic sample Excel files.
- Merge existing participant/email and partner-group spreadsheets into the MVP template.
- Define test cases for voting and room generation.
- Verify exported Zoom CSV files against Zoom’s expected upload format.
- Prepare future applicant questions and workflow notes.
- Organize future Google Drive/content conventions.
- Manually QA the admin workflow.

## 3. Recommended MVP Stack

Frontend:

- Vercel-hosted admin frontend.
- Suggested framework: Next.js or React.

Backend:

- Railway-hosted API service.
- Handles secrets, Discord API calls, file parsing, grouping logic, and export generation.
- Excel parsing/generation uses `read-excel-file` and `write-excel-file`.
- CSV parsing/generation uses `csv-parse` and `csv-stringify`.

Database:

- Supabase Postgres.
- MVP can use minimal persistence.
- Future phases can store participants, sessions, statuses, applications, attendance, and content metadata.

Important security rule:

- Discord bot token and Supabase service credentials must only exist on the backend.
- The frontend must never call Discord directly with privileged credentials.

## 4. P0 User Flow

1. Admin opens the internal admin web app.
2. Admin uploads the participant/partner-group Excel or CSV.
3. System validates and previews participants.
4. Admin creates a weekly session or enters a session label.
5. Admin pastes the Discord poll message link.
6. Backend fetches poll answers and voters from Discord.
7. System maps Discord users to imported participants.
8. System pulls the four topics from the Discord poll answers.
9. Admin can edit topic labels before generation.
10. System assigns one topic to each partner group.
11. System generates dynamic rooms named `Room1`, `Room2`, up to `RoomN`.
12. Admin reviews generated assignments.
13. Admin can manually move partner groups between rooms.
14. Admin downloads the internal Excel export.
15. Admin downloads the Zoom-compatible CSV export.

## 5. Data Shapes

### Participant

```ts
type Participant = {
  firstName: string;
  lastName: string;
  email: string;
  discordUsername?: string;
  discordUserId?: string;
  partnerGroup: string;
};
```

Rules:

- Email is the unique identifier.
- MVP treats all imported participants as active.
- `partnerGroup` uses numeric labels such as `1`, `2`, and `3`.
- Discord user ID is preferred long term, but Discord username is acceptable for MVP if that is the available mapping.

### Weekly Topic

```ts
type WeeklyTopic = {
  id: string;
  label: string;
  discordPollAnswerId?: string;
};
```

Rules:

- There are four topics per week.
- Topic labels are pulled from Discord poll answers.
- Admin can edit labels before generation.

### Vote

```ts
type Vote = {
  discordUserId?: string;
  discordUsername?: string;
  topicId: string;
};
```

Rules:

- Votes are fetched from Discord by poll message link.
- Votes must be mapped to participants through Discord username or Discord user ID.
- Unmatched voters should be shown to admin as warnings.

### Partner Group Assignment

```ts
type PartnerGroupAssignment = {
  partnerGroup: string;
  participants: Participant[];
  votedTopicIds: string[];
  assignedTopicId: string;
};
```

Rules:

- Partner groups stay fixed for MVP.
- One topic is assigned per partner group.
- If nobody in the group voted, assign a topic to balance total topic counts.

### Room Assignment

```ts
type RoomAssignment = {
  roomName: `Room${number}`;
  partnerGroups: PartnerGroupAssignment[];
};
```

Rules:

- Room names must use the format `Room1`, `Room2`, up to `RoomN`.
- Each room should have one partner group per topic where possible.
- Rooms should target roughly eight to ten participants.
- Admin can manually move partner groups before export.

## 6. Input And Output Files

### MVP Participant Input Template

Required columns:

```text
Name | Email | Discord username | Partner group
```

Optional future column:

```text
Discord user ID
```

Validation rules:

- Name is required and should contain first and last name separated by a space.
- Email is required and must be unique.
- Partner group is required.
- Discord username or Discord user ID is required for Discord vote mapping.
- Partner groups should contain two or three participants.

### Internal Excel Export

Columns:

```text
Participant name | Email | Discord username or ID | Voted topic | Partner group | Pre-assigned room
```

Purpose:

- Human review.
- Debugging vote mapping and room generation.
- Internal weekly record.

### Zoom CSV Export

Columns:

```text
Pre-assign Room Name | Email Address
```

Rules:

- No extra columns.
- Room names use `Room1`, `Room2`, up to `RoomN`.

## 7. P0 Backend API

Suggested endpoints:

```text
POST /api/import/preview
POST /api/discord/poll/preview
POST /api/assignments/generate
POST /api/assignments/override
POST /api/exports/internal-excel
POST /api/exports/zoom-csv
```

### `POST /api/import/preview`

Input:

- Excel `.xlsx` or CSV file.

Output:

- Parsed participants.
- Validation errors.
- Duplicate email warnings.
- Partner groups and group-size warnings.

### `POST /api/discord/poll/preview`

Input:

- Discord poll message link.
- Imported participants or session ID.

Output:

- Poll topics.
- Voters by topic.
- Matched participant votes.
- Unmatched Discord voters.
- Imported participants with no vote.

### `POST /api/assignments/generate`

Input:

- Participants.
- Topics.
- Matched votes.
- Optional edited topic labels.

Output:

- Partner-group topic assignments.
- Generated room assignments.
- Warnings.

### `POST /api/assignments/override`

Input:

- Current room assignment.
- Partner group move requested by admin.

Output:

- Updated room assignment.
- Warnings if room balance is weakened.

### `POST /api/exports/internal-excel`

Input:

- Final room assignment.

Output:

- `.xlsx` file.

### `POST /api/exports/zoom-csv`

Input:

- Final room assignment.

Output:

- `.csv` file with only Zoom-required columns.

## 8. Grouping Algorithm

### Step 1: Group Participants

- Group imported participants by `partnerGroup`.
- Validate each partner group has two or three participants.
- Warn if a group has one participant or more than three participants.

### Step 2: Map Votes

- Fetch Discord poll voters for each answer.
- Match voters to participants by Discord user ID if available.
- Fall back to Discord username if user ID is not available.
- Store each participant’s voted topic.

### Step 3: Assign Topic To Partner Group

Rules:

- Pair, same vote: assign that topic.
- Pair, one voter: assign the voted topic.
- Pair, split vote: choose the voted topic that best balances topic counts.
- Group of three, majority vote: assign the majority topic.
- Group of three, no majority: choose the voted topic that best balances topic counts.
- No votes in group: choose the topic that best balances topic counts.

Balance target:

- Keep the number of partner groups per topic as even as practical.
- Ignore the rare MVP edge case where most participants vote for the same topic.

### Step 4: Generate Rooms

- Create enough rooms to preserve one partner group per topic per room where possible.
- Name rooms `Room1`, `Room2`, up to `RoomN`.
- Place one partner group per topic into each room where possible.
- Try to keep room participant counts between eight and ten.
- Keep partner groups together.
- Do not split participants from the same partner group.

### Step 5: Manual Override

- Admin can move a partner group from one room to another.
- The app should preserve the manual override in the final exports.
- If the move creates duplicate topics in a room, show a warning but allow the admin to proceed.

## 9. Frontend Screens

### Screen 1: Upload Participants

Controls:

- Upload Excel/CSV.
- Preview imported rows.
- Show validation errors.
- Continue button.

### Screen 2: Weekly Session

Controls:

- Session label.
- Discord poll message link.
- Pull poll button.
- Topic preview/edit fields.
- Vote mapping warnings.

### Screen 3: Generate Rooms

Controls:

- Generate rooms button.
- Room columns named `Room1` through `RoomN`.
- Partner group cards with participant names, topic, and email count.
- Manual move controls.
- Warning panel for unmatched voters, non-voters, invalid groups, or duplicate topics.

### Screen 4: Export

Controls:

- Download internal Excel.
- Download Zoom CSV.
- Optional download participant CSV.

## 10. P0 Task Breakdown For Lead Developer

1. Create repo structure for frontend and backend.
2. Add environment variable structure for Discord and Supabase.
3. Build backend file parser for `.xlsx` and CSV.
4. Build participant validation.
5. Build Discord poll-link parser.
6. Build Discord poll-fetch service.
7. Build vote-to-participant matching.
8. Build partner-group topic assignment logic.
9. Build room generation logic.
10. Build export generators for internal Excel and Zoom CSV.
11. Build admin frontend upload flow.
12. Build admin frontend poll preview flow.
13. Build generated rooms preview.
14. Build manual partner-group movement.
15. Add simple admin protection.
16. Deploy frontend to Vercel.
17. Deploy backend to Railway.
18. Configure Supabase project for future persistence.

## 11. P1 Tasks For Technical Teammate

These should wait until the P0 architecture is established:

- Add automated tests for Excel validation.
- Add automated tests for split-vote balancing.
- Add automated tests for Zoom CSV output.
- Improve room preview UI.
- Add cleaner manual-move controls.
- Add saved sessions in Supabase.
- Add participant database and status screens.
- Add applicant review screen.
- Add future availability import UI.

## 12. P2 Tasks For Non-Technical Teammate

These can start immediately:

- Create the merged MVP participant Excel template.
- Fill the template with current participants.
- Ensure each participant has email, Discord username, and partner group.
- Create example weekly Discord poll scenarios.
- Create test cases for:
  - all group members vote same topic.
  - pair split vote.
  - only one partner votes.
  - group of three majority vote.
  - nobody in partner group votes.
  - unmatched Discord voter.
  - participant in spreadsheet does not vote.
- Test generated Zoom CSV by comparing it to the current weekly Zoom upload format.
- Document future applicant intake questions.
- Organize Google Drive material folders for later content-platform migration.

## 13. Implementation Risks

### Discord Poll Access

Risk:

- Discord poll data may require specific bot permissions, channel access, and intents.

Mitigation:

- Build direct Discord sync first.
- Keep manual import as a fallback.
- Test with a real Discord poll early.

### Discord Identity Matching

Risk:

- Discord usernames can change or collide.

Mitigation:

- Use Discord user ID when possible.
- For MVP, support username matching because the current mapping exists.
- Show unmatched voters clearly.

### Zoom CSV Strictness

Risk:

- Zoom upload may reject unexpected formats.

Mitigation:

- Generate a dedicated Zoom CSV with only `Pre-assign Room Name` and `Email Address`.
- Use exact room names in the format `Room1`, `Room2`, up to `RoomN`.

### Spreadsheet Quality

Risk:

- Names, emails, or partner-group labels may be inconsistent.

Mitigation:

- Use email as unique identifier.
- Validate required columns.
- Show import preview and warnings before generation.

## 14. Definition Of Done For MVP

The MVP is done when:

- Admin can upload the merged participant Excel file.
- Admin can paste a Discord poll message link and fetch poll results.
- Poll topics appear and can be edited.
- Participants are matched to Discord votes.
- Non-voting partner groups are balanced across topics.
- Split-vote partner groups are assigned to the topic that best balances counts.
- Rooms are generated dynamically as `Room1`, `Room2`, up to `RoomN`.
- Admin can manually move partner groups between rooms.
- Internal Excel export downloads correctly.
- Zoom CSV export downloads correctly.
- Zoom CSV can be uploaded to Zoom using the expected pre-assignment format.
- The app is protected for admin-only use.
- Frontend is deployable on Vercel.
- Backend is deployable on Railway.

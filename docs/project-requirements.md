# Web3 Talents Platform Project Requirements

## 1. Purpose

Build a web-based management platform for a Web3 talents educational program. The platform should centralize participant intake, lifecycle management, attendance, scheduling inputs, grouping, content, presentations, and operational exports.

The first version should prioritize replacing manual admin work and fragmented files with a reliable central system. The MVP is admin-only. Participant-facing and mentor-facing interfaces can be phased in after the admin workflows are stable.

## 2. Team Structure

The work should be split so the lead developer owns the core application architecture and critical workflows, while the other team members can contribute safely.

| Role | Suggested Ownership |
| --- | --- |
| Lead developer | Data model, authentication/authorization, core admin flows, import/export, grouping logic, deployment, code review |
| Technical teammate | UI screens, forms, tables, validation, Excel/CSV import support, tests, smaller backend endpoints after patterns are established |
| Non-technical teammate | Requirements validation, user stories, rough wireframes, screen copy, test scenarios, sample CSV/Excel files, content organization, manual QA, acceptance testing |

## 3. Feature Priority

Priority levels:

- P0: Required for a useful MVP.
- P1: Important soon after MVP.
- P2: Useful later or optional depending on program needs.

| Priority | Feature Area | Reason |
| --- | --- | --- |
| P1 | Admin participant database and lifecycle statuses | Future internal system of record; MVP treats uploaded spreadsheet participants as active |
| P0 | Import participants from Excel/CSV | Needed for initial setup and migration from existing files; Excel is the primary day-one format |
| P1 | Manual participant creation and editing | Useful later; MVP relies on spreadsheet import |
| P1 | Basic admin applicant review | Useful for intake, but not required for the first weekly-operations MVP |
| P2 | Attendance indication or admin attendance tracking | Deferred because MVP assumes all active imported participants attend |
| P0 | Basic group/breakout generation from active participants and Discord poll results | High operational value for every session |
| P0 | Admin-only access control | MVP can use simple protection; Google login is acceptable if easy |
| P2 | Central content repository | Deferred; materials are currently handled in Google Drive |
| P2 | Web application portal for applicants | Deferred because the MVP is admin-only |
| P1 | Topic preferences | Improves grouping quality and learning relevance |
| P1 | Mentor availability | Useful for session planning and group support |
| P1 | Presentation upload and archive | Important program artifact, but less critical than participant operations |
| P1 | Export data to CSV | Needed if external planning remains part of operations |
| P2 | Participant accounts | Deferred because the MVP is admin-only |
| P2 | Mentor accounts | Deferred because the MVP is admin-only |
| P2 | Participant-selected buddies | Nice to have, but can complicate fairness and assignment logic |
| P2 | Peer-to-peer messaging | High complexity and moderation burden; external tools may be better for MVP |
| P2 | Automatic PDF generation for assignments | Useful, but upload/download may be enough initially |

## 4. MVP Scope

The MVP should support these workflows end to end:

1. Admin imports an active participant and partner-group list from Excel or CSV, with Excel as the primary supported format.
2. Admin pulls weekly topic poll results from Discord.
3. Admin generates session groups from imported participants and poll results.
4. Admin exports a rich internal Excel file and Zoom-compatible CSV for breakout pre-assignment.
5. Admin exports participant or group data to CSV when needed.

## 5. Requirements By Area

### 5.1 Applications And Onboarding

Priority: P1 for admin-managed applicant review, P2 for public applicant portal.

Requirements:

- Later, provide a web-based application form for participant intake.
- Store applicant submissions centrally.
- Allow admins to review applicants.
- Allow admins to change applicant status, such as new, under review, accepted, rejected, or waitlisted.
- Allow accepted applicants to be converted into participant records.

P1 acceptance criteria:

- Admin can view a list of applicants.
- Admin can open applicant details.
- Admin can accept or reject an applicant.
- Accepted applicants can become participants without retyping all data.

Team split:

- Lead developer: applicant data model, applicant-to-participant conversion, admin permissions.
- Technical teammate: applicant list/detail UI after admin patterns are established.
- Non-technical teammate: define application questions, create rough wireframes, write sample applications, test applicant review flow.

Open questions:

- What exact questions should the application form ask?
- When the public application portal is added later, do applicants need email confirmation after applying?
- Should rejected or waitlisted applicants remain visible forever, be archived, or be deleted after a period?

### 5.2 User And Status Management

Priority: P1 for internal participant database and lifecycle statuses. For MVP, uploaded participants are treated as active.

Requirements:

- Maintain a central participant database.
- Support participant statuses: Applicant, Active, Paused, Inactive, Removed.
- Allow admins to add, remove, and update participants.
- Track important participant fields such as name, email, location, skill level, interests, availability, and notes.
- Later support for participant and mentor accounts.
- For MVP, treat all participants in the uploaded spreadsheet as active.
- Future scope: store participants and statuses in an internal database as the source of truth.

P1 acceptance criteria:

- Admin can create, edit, filter, and view participants.
- Admin can update participant status.
- Removed participants are excluded from active workflows by default.
- Active participants can be used in group workflows and later attendance workflows.
- For MVP, imported spreadsheet rows are considered the active roster.

Team split:

- Lead developer: participant model, status rules, permissions, database migrations.
- Technical teammate: participant table, filters, edit form, validation.
- Non-technical teammate: define required participant fields and test common admin edits.

Open questions:

- Should "Removed" mean soft-deleted, permanently deleted, or hidden from normal views?
- Which fields are mandatory for a participant?
- What participant-facing workflows should be considered first after the admin-only MVP?
- Confirmed: MVP does not require internal participant lifecycle statuses.

### 5.3 Import And Export

Priority: P0 for Excel and CSV import, P1 for export.

Requirements:

- Import participants from Excel `.xlsx` and CSV.
- Treat Excel as the primary import format for day one.
- Import or maintain partner-group data for pairs and groups of three.
- Validate imported rows before saving.
- Show import errors clearly.
- Avoid duplicate participants, likely by email address.
- Export participant and group data to CSV. Later, export attendance data when attendance tracking is added.

MVP acceptance criteria:

- Admin can upload an Excel `.xlsx` file of participants.
- Admin can upload a CSV file of participants.
- Admin can upload or maintain partner-group assignments.
- System previews valid and invalid rows.
- Admin can confirm import.
- Duplicate emails are flagged before import.
- Existing participants are matched by email and can be updated from a later import after admin confirmation.
- Admin can export current participant list to CSV.

Team split:

- Lead developer: Excel/CSV import pipeline, duplicate handling, database writes.
- Technical teammate: import preview UI, export buttons, validation messages.
- Non-technical teammate: create realistic sample Excel and CSV files, prepare a merged participant/partner-group template, test invalid and duplicate data cases.

Open questions:

- Confirmed: Excel `.xlsx` and CSV import are both required from day one, with Excel as the main format.
- Confirmed: current data is split across two Excel files: one file has partner groups using first and last names for Person 1, Person 2, and optional Person 3; another file has participant first name, last name, and email.
- Recommendation for MVP: merge these into one import file if practical, because name-only matching across two files is more error-prone than importing one normalized file.
- Confirmed: partner groups can use letter labels such as Group A, Group B, and Group C.
- Confirmed: import should match existing participants by email and allow updates after preview/confirmation.
- Confirmed: for MVP, the participant/partner-group Excel is expected to be mostly fixed after initial setup.
- Future scope: repeated re-upload may be needed if partner groups change over time.

### 5.4 Group And Buddy Management

Priority: P0 for basic group generation, P1/P2 for advanced buddy logic.

Requirements:

- Generate session groups or breakout rooms from imported participants.
- Support manual editing of generated groups.
- Consider criteria such as location, skill level, topic preferences, availability, and previous pairings.
- Support buddy pairing manually or automatically.
- Optionally allow participants to choose preferred buddies later.
- Support weekly topic-based assignment from four topic choices.
- Support stable partner groups of two or three participants who work on the same topic.
- For each weekly topic workflow, assign partner groups into breakout rooms so each room has one partner group per topic where possible.
- Generate enough breakout rooms for the current cohort size and topic distribution.
- Prefer one partner group per topic in each room where possible.
- Generate room names in the format `Room1`, `Room2`, up to `RoomN`.
- Include room assignment and participant email in the generated Excel output.
- Generate a Zoom-compatible breakout-room CSV export with only the fields Zoom expects.
- Assign a balanced topic to partner groups where nobody in the group voted.

MVP acceptance criteria:

- Admin selects a session and generates groups from imported participants.
- MVP generates dynamic rooms named `Room1`, `Room2`, up to `RoomN`.
- Future status support should exclude removed or inactive participants. MVP assumes all imported participants are active.
- Admin can manually move partner groups between rooms before finalizing.
- Final group assignments can be viewed and exported.
- Admin can import or sync weekly topic preferences.
- Topic names are pulled from Discord poll answers, with admin edit support before room generation.
- Admin can generate an internal Excel file with participant name, email, Discord username or ID, voted topic, partner group, and pre-assigned room.
- Admin can generate a Zoom-compatible CSV file with pre-assigned room and email columns.
- Each breakout room contains at most one partner group per topic when enough partner groups are available.
- Partner groups without any poll response receive a balanced topic assignment before room generation.
- Admin can manually move partner groups between generated rooms before exporting.

Team split:

- Lead developer: grouping algorithm, previous pairing tracking, persistence.
- Technical teammate: group generation UI, drag/drop or move controls, export display.
- Non-technical teammate: define fairness rules, prepare sample weekly poll data, test generated groups against real scenarios.

Open questions:

- Is group quality more important by topic, skill balance, location, or avoiding repeat pairings?
- How often are groups reassigned?
- Confirmed: participants currently work in pairs or groups of three.
- Confirmed: partner groups are known before the weekly topic poll and remain the same each week for the MVP.
- Confirmed: meeting-day breakout rooms should ideally contain eight to ten participants, but the room count must scale with cohort size.
- Confirmed: MVP assumes all participants in the spreadsheet are active and attend.
- Future scope: dynamic pair/group creation based on topic selections.
- Confirmed: MVP should pull weekly topic poll results directly from Discord.
- Manual poll result import should remain a fallback if direct Discord sync fails or is temporarily unavailable.
- Confirmed: the team currently has a Discord username to participant email mapping.
- Later, participants should register with both email and Discord username so the platform can maintain this mapping directly.
- Confirmed: non-voting partner groups should be assigned to balance topic counts because the workflow follows a jigsaw learning model.
- Out of MVP scope: handling the rare edge case where many participants vote for the same topic.

### 5.4.1 Weekly Discord Poll To Breakout Excel Workflow

Priority: P0 if this is the main weekly operations workflow.

Workflow:

1. Admin creates or selects a weekly session with four topics.
2. Students vote in Discord for the topic they want to work on.
3. Admin pastes the Discord poll message link into the platform.
4. Platform retrieves or imports the poll results.
5. Platform matches Discord voters to participant records and email addresses.
6. Platform applies voting results to each pre-existing partner group.
7. Platform assigns a topic to each partner group.
8. Platform creates meeting-day breakout rooms with one partner group per topic where possible.
9. Platform exports a rich internal Excel file for review.
10. Platform exports a Zoom-compatible CSV file for upload.

Partner-group topic assignment rules:

- If both people in a pair vote for the same topic, assign the pair to that topic.
- If two people in a pair vote for different topics, keep the pair together and choose the voted topic that best balances the topic counts.
- If only one person in a pair votes, assign the pair to that voted topic.
- If a group of three has a majority topic vote, assign the group to the majority topic.
- If nobody in the partner group votes, assign a topic that helps balance the four topic counts.
- Keep the pair or group of three together even when their individual votes differ.

Expected Excel columns:

- Participant name.
- Email.
- Discord username or Discord user ID.
- Voted topic.
- Partner group.
- Pre-assigned room.

Recommended MVP input template columns:

- Name, with first and last name separated by a space.
- Email.
- Discord username or Discord user ID.
- Partner group.

Stable versus weekly data:

- The participant import file should store stable data only: participant identity, email, Discord username or ID, and partner group.
- Weekly topic selections should not be stored in the participant import file.
- Weekly topic selections belong to the weekly session/poll records because they change each week.
- The platform should retain weekly topic-selection history for later review if practical.

Future scope:

- Native in-platform weekly topic polls.
- Native in-platform chat or communication features.
- Migration away from Discord for polling once participant accounts and platform communication features exist.

Expected Zoom CSV columns:

- Pre-assign Room Name.
- Email Address.

Room naming:

- Generated room names must use the exact format `Room1`, `Room2`, up to `RoomN`.
- There should be no space between `Room` and the number.

Feasibility notes:

- Direct Discord poll sync is feasible if a Discord bot/app has access to the server, channel, poll message, and required permissions/intents.
- Confirmed: for MVP, admins will paste the Discord poll message link into the platform to identify the poll.
- Confirmed: topic names should be pulled from Discord poll answers and editable by admin before generation.
- A manual fallback should also exist: admin uploads an Excel or CSV export of poll results if direct Discord sync is unavailable or unreliable.
- Matching by Discord display name is risky because names can change or collide. Matching by Discord user ID is more reliable.
- The current known Discord username to email mapping is enough for the MVP, but the platform should eventually store Discord user ID as well.
- A single merged participant import file is preferred for MVP. Separate partner-group and participant-email files can be supported later, but require name matching and duplicate-name handling.
- Balanced topic assignment for non-voting partner groups is required because the jigsaw learning model depends on each room having a mix of topics.
- Zoom upload should use a minimal CSV generated specifically for Zoom. Richer fields such as topic, vote source, and Discord username should stay in the internal Excel report.

### 5.5 Attendance And Scheduling

Priority: P2 for attendance and availability in the current MVP.

Requirements:

- Track participant attendance per session.
- Allow participants to indicate attendance later if participant accounts are enabled.
- Allow admins to record attendance in MVP.
- Track mentor availability for sessions.
- Use attendance and availability when generating groups.
- For the current MVP, assume all active imported participants attend.
- Future scope: integrate the existing availability spreadsheet into the platform.

MVP acceptance criteria:

- Admin can create or select a session.
- For MVP, group generation uses all active imported participants by default.
- Later, admin can mark participants as attending, absent, or unknown.

Team split:

- Lead developer: session model and future attendance model.
- Technical teammate: future attendance table, bulk controls, session selector.
- Non-technical teammate: prepare sample availability sheets for later integration and verify group results.

Open questions:

- Do sessions follow a fixed schedule, or should admins create each session manually?
- Confirmed: MVP does not require attendance tracking; everyone in the active participant spreadsheet is assumed to attend.
- Future scope: integrate the existing availability spreadsheet.
- Should future attendance mean planned attendance before a session, actual attendance after a session, or both?
- Do mentors need to be assigned to generated groups?

### 5.6 Content And Assignments

Priority: P2 for repository and PDF generation in the current MVP.

Requirements:

- Provide a central repository for assignments, materials, presentations, recordings, and resources.
- Allow admins to upload, update, archive, and delete content.
- Organize content by cohort, session, topic, and content type.
- Allow easy access to current and past content.
- Later support generating assignment PDFs.
- For the current MVP, program materials remain in Google Drive outside the platform.
- Future scope: when participant-facing client-side features exist, allow students to access and upload content directly through the platform.

MVP acceptance criteria:

- Current MVP does not require platform-hosted content.
- Later, admin can upload a file with title, description, type, and session/topic association.
- Later, admin can edit metadata.
- Later, users with access can browse and download content.
- Later, content remains stored centrally on the platform or configured storage service.

Team split:

- Lead developer: future file storage, access control, metadata model.
- Technical teammate: future content library UI, upload form, filters.
- Non-technical teammate: organize existing Google Drive files into categories and define future content naming conventions.

Open questions:

- Where should files be stored: database, local server storage, or cloud object storage?
- Confirmed: current materials are stored separately in Google Drive.
- Future question: who needs access to platform-hosted content: admins only, participants, mentors, or public viewers?
- Future question: is PDF generation required, or can uploaded PDFs satisfy the later content feature?

### 5.7 Topic Preferences

Priority: P1.

Requirements:

- Allow participants to select preferred topics.
- Store topic preferences centrally.
- Use preferences in group and buddy assignment logic.
- Allow admins to view and edit preferences.

MVP acceptance criteria:

- Admin can define available topics.
- Admin can record topic preferences in the MVP. Participants may record their own preferences later if participant accounts are added.
- Group generation can optionally consider topic preferences.

Team split:

- Lead developer: topic model, preference model, grouping integration.
- Technical teammate: topic preference UI.
- Non-technical teammate: define topic list, rough preference screen expectations, and sample preference scenarios.

Open questions:

- Are topics fixed for the whole program or different per session?
- How many topic preferences can a participant choose?
- Should preferences be ranked or simple selections?

### 5.8 Presentations

Priority: P1.

Requirements:

- Provide a space to upload, share, and archive participant presentations.
- Associate presentations with participants, sessions, topics, and cohorts.
- Support admin review or moderation before publishing if needed.

MVP acceptance criteria:

- Admin can upload a presentation file or link.
- Presentation can be associated with one or more participants.
- Presentations can be browsed by session or participant.

Team split:

- Lead developer: presentation model and file/link storage.
- Technical teammate: presentation upload and archive UI.
- Non-technical teammate: collect existing presentations and define metadata.

Open questions:

- Are presentations files, links, embedded videos, or all of these?
- Should participants upload their own presentations, or should admins upload them?
- Should presentations be visible to all participants?

### 5.9 Communication

Priority: P2.

Requirements:

- Provide or link to a main group communication channel.
- Optionally support peer-to-peer messaging later.

MVP acceptance criteria:

- Platform stores the main communication channel link, such as Discord, Telegram, Slack, WhatsApp, or email group.
- Admin can update the communication link.

Team split:

- Lead developer: simple communication settings if kept in platform.
- Technical teammate: display communication link in dashboard.
- Non-technical teammate: define official channels and communication rules.

Open questions:

- Which communication tool does the program already use?
- Is in-platform peer messaging actually needed, or would it duplicate existing tools?
- Are there privacy or moderation requirements?

### 5.10 Data And System

Priority: P0.

Requirements:

- Store all operational data centrally.
- Provide import/export where needed.
- Restrict admin-only operations.
- Maintain audit-friendly data where practical.
- Avoid relying on external drives as the source of truth.

MVP acceptance criteria:

- Data persists in a central database.
- Admin-only features are protected.
- Core records have created and updated timestamps.
- CSV export exists for critical workflows.

Team split:

- Lead developer: database, authentication, authorization, deployment, backups.
- Technical teammate: support tests and admin UI states.
- Non-technical teammate: document data policies and test role-based workflows.

Open questions:

- Who are the admin users?
- Does the platform need multiple cohorts at launch?
- Are there GDPR/privacy constraints for participant data?
- What backup or retention policy is expected?

### 5.10.1 Target Deployment Architecture

Priority: P0 for MVP implementation planning.

Confirmed target stack:

- Frontend: Vercel.
- Backend/API: Railway.
- Database: Supabase Postgres.

Notes:

- This architecture is feasible for an internal admin web app.
- Vercel should host the admin frontend.
- Railway should host the backend service that handles Discord API calls, Excel parsing/generation, Zoom CSV export, and privileged server-side logic.
- Supabase should store persistent data when the app moves beyond purely spreadsheet-driven workflows.
- For the earliest MVP, the backend may still process uploaded Excel files directly and only store minimal session metadata if needed.

Operational considerations:

- Secrets such as Discord bot token, Supabase service credentials, and API URLs must be stored in platform environment variables, not committed to the repository.
- The frontend should not call Discord directly with bot credentials.
- The backend should expose narrow admin-only endpoints for poll sync and export generation.
- The team will need to manage deployments and environment variables across three services.

Admin access:

- MVP does not require full account management.
- A simple password-protected admin page is acceptable.
- Simple Google login is also acceptable if it is straightforward to configure.
- Public participant-facing access is out of scope for the MVP.

## 6. Suggested Build Order

1. Project foundation: app framework, admin-only access, deployment target.
2. Excel/CSV participant and partner-group import.
3. Weekly session setup with four topics.
4. Discord poll sync by message link.
5. Partner-group topic assignment and balanced fallback logic.
6. Breakout room generation and manual group editing.
7. Internal Excel export and Zoom-compatible CSV export.
8. Participant export.
9. Internal participant database and lifecycle statuses.
10. Manual participant create/edit/status updates.
11. Topic preferences and improved grouping logic.
12. Applicant review and applicant-to-participant conversion.
13. Attendance and availability tracking.
14. Content repository upload and browse.
15. Presentation archive.
16. Mentor availability and mentor assignments.
17. Participant/mentor accounts and participant-facing UI.
18. Participant-selected buddies.
19. Peer-to-peer messaging.
20. Assignment PDF generation.

## 7. Non-Technical Task Backlog

These tasks can be handled by the non-technical teammate:

- Write the exact application form questions.
- Prepare sample applicant data.
- Prepare sample participant Excel and CSV files with realistic edge cases.
- Define participant status meanings in plain language.
- Define required participant fields.
- Define group assignment fairness rules.
- Prepare sample availability sheets for later integration.
- Organize existing Google Drive content into categories.
- Define naming conventions for future uploaded files.
- Test admin workflows and report confusing screens.
- Create rough wireframes for future participant-facing pages.
- Write screen labels and short help text where needed.

## 8. Technical Teammate Task Backlog

These tasks can be handled by the technical teammate once the lead developer establishes patterns:

- Build participant list UI with filters.
- Build participant create/edit forms.
- Build applicant list and detail screens.
- Build Excel/CSV import preview UI.
- Build future attendance table UI.
- Build group assignment screen.
- Build content library filters and upload form.
- Add form validation and error states.
- Write focused tests for forms and utility functions.
- Improve empty states and loading states.

## 9. Lead Developer Task Backlog

The lead developer should own:

- Final technology choices.
- Database schema and migrations.
- Authentication and authorization.
- Participant, applicant, session, future attendance, content, and grouping models.
- Excel/CSV import and CSV export pipeline.
- Group generation algorithm.
- File storage strategy.
- Deployment and environment configuration.
- Data privacy, backup, and retention decisions.
- Code review and integration.

## 10. Clarification Checklist

These questions should be answered before implementation begins:

- Which features are truly required for the first usable version?
- Confirmed: the MVP is admin-only. Participant and mentor accounts are deferred.
- Confirmed: Excel `.xlsx` and CSV import are both required from day one, with Excel as the main format.
- How many participants and cohorts should the system support in the first year?
- What data do you already have in spreadsheets?
- What is the program schedule and normal session structure?
- What group assignment rules matter most?
- Which content types must be uploaded?
- Which external communication tool is already used?
- What privacy, consent, and retention rules apply?
- Where will the application be hosted?
- Who will maintain the platform after launch?

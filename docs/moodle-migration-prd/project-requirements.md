# Web3 Talents Moodle Platform PRD

## 1. Product Direction And Migration Rationale

### 1.1 Purpose

Build a Moodle-based all-in-one platform for the Web3 Talents educational program. The platform should become the daily operating environment for students, mentors, and administrators, replacing fragmented tools and reducing reliance on external spreadsheets, Discord, Google Drive, and one-off admin workflows.

The platform should support the full program lifecycle:

- Applicant intake and review.
- Student onboarding.
- Cohort and course management.
- Program communication.
- Learning materials and assignments.
- Topic selection and session preparation.
- Partner-group and breakout-room workflows.
- Presentation upload and review.
- Attendance, availability, and participation tracking.
- Operational exports for tools that remain external, such as Zoom.

### 1.2 Strategic Decision

Moodle will become the core platform foundation. It should own the durable learning-management concepts: users, roles, permissions, courses, cohorts, enrolments, groups, course content, assignments, submissions, completion, events, and standard learning records.

Custom Web3 Talents functionality should be implemented through Moodle-native extension points where practical:

- Custom theme for the Figma-based visual system.
- Local plugin for program-level dashboards, operations, and cross-course workflows.
- Activity modules where a feature should behave like a course activity.
- Blocks where contextual dashboard widgets are useful.
- Custom fields for program-specific user, course, cohort, or activity metadata.
- Reports and export plugins for admin-facing operational outputs.
- External services only where a separate service is clearly better than Moodle-native code.

The current custom TypeScript platform should be treated as a reference implementation for behavior already validated, especially:

- Participant roster import.
- Partner-group validation.
- Topic vote handling.
- Jigsaw topic balancing.
- Breakout-room generation.
- Manual room override behavior.
- Internal Excel export.
- Zoom-compatible CSV export.

### 1.3 Product Goals

- Provide one primary platform where students can participate in the program without depending on Discord as the central experience.
- Give admins a reliable system of record for students, cohorts, sessions, assignments, files, and operational workflows.
- Preserve the existing high-value jigsaw learning workflow while moving it into the Moodle ecosystem.
- Allow the frontend design team's Figma work to be implemented as a branded Moodle experience, not just lightly restyled default Moodle screens.
- Keep the architecture extensible enough for future Web3-specific features such as wallet identity, credentials, certificates, proof-of-participation, or tokenized achievements.
- Reduce weekly manual operations by replacing spreadsheet-driven and chat-driven processes with platform-native workflows.
- Keep external dependencies explicit: use Zoom or other outside tools only where they remain operationally necessary.

### 1.4 Non-Goals

- Do not attempt to preserve the current Next.js/Fastify application as the primary product shell.
- Do not make Discord the long-term communication hub.
- Do not require the MVP to reproduce every possible Moodle feature with custom UI.
- Do not build a full collaborative document editor unless a later requirement clearly justifies the cost.
- Do not fork Moodle core for normal product requirements. Custom behavior should be implemented through plugins, themes, configuration, or external integrations.

### 1.5 Migration Principles

- Moodle should be extended, not fought. Use Moodle-native concepts wherever they map cleanly to the product requirement.
- Custom plugins should own Web3 Talents-specific workflows that Moodle does not provide out of the box.
- The student experience should feel like a coherent Web3 Talents platform, even when built on Moodle internals.
- The admin experience should prioritize operational clarity over exposing raw Moodle configuration screens.
- Existing validated logic should be ported carefully with equivalent test coverage rather than redesigned casually.
- Features should be specified by user workflow first, then mapped to Moodle components, plugins, data models, and integrations.

### 1.6 Initial Product Boundary

The Moodle migration PRD covers the target platform after committing to Moodle as the foundation. It should define what needs to exist for a usable student-facing and admin-facing program platform, including custom plugins and themed Moodle experiences.

This PRD does not yet define implementation sequencing, staffing, sprint planning, or detailed technical design. Those should be handled in later migration planning documents after the product requirements are agreed.

## 2. User Roles And Core Platform Model

### 2.1 Primary User Roles

The Moodle-based platform should support these primary user roles.

| Role | Description | Primary Needs |
| --- | --- | --- |
| Applicant | A person applying to join a Web3 Talents cohort. | Submit an application, receive status updates, and complete onboarding steps after acceptance. |
| Student | An accepted participant enrolled in a cohort. | Access program materials, communicate with the cohort, submit work, select topics, join sessions, upload presentations, and track progress. |
| Mentor | A program supporter who helps students during sessions or project work. | View assigned cohorts, indicate availability, access relevant materials, support groups, review work where applicable, and communicate with students/admins. |
| Program admin | An operator responsible for cohort setup and day-to-day program management. | Manage applicants, users, cohorts, groups, sessions, materials, assignments, communication, jigsaw workflows, exports, and operational reports. |
| Platform admin | A technical or super-admin user responsible for Moodle configuration and system-level administration. | Manage site settings, plugins, integrations, roles, permissions, backups, upgrades, and security controls. |

### 2.2 Role Principles

- Students should interact primarily with branded Web3 Talents pages and course areas, not raw Moodle administration screens.
- Mentors should see only the cohorts, groups, sessions, and student work relevant to their responsibilities.
- Program admins should have purpose-built operational screens for common workflows instead of needing to assemble steps from scattered Moodle admin pages.
- Platform admins may use standard Moodle administration screens, but custom platform features should remain documented and maintainable.
- Applicant access may be public, account-based, invitation-based, or hybrid depending on the final intake workflow.

### 2.3 Core Moodle Concept Mapping

The platform should use Moodle-native concepts where they map cleanly to program needs.

| Web3 Talents Concept | Preferred Moodle Mapping | Notes |
| --- | --- | --- |
| Platform user | Moodle user | Stores identity, login, profile fields, preferences, and role assignments. |
| Student | Moodle user with student role in one or more courses/cohorts | Program-specific student status may require custom profile fields or custom plugin tables. |
| Mentor | Moodle user with Moodle teacher role | The default teacher role is acceptable as the initial mentor permission model. |
| Program admin | Moodle user with custom program admin role | Should manage operational workflows without unnecessary full site-admin access. |
| Cohort | One Moodle course per cohort | Multiple cohorts may run at the same time. The fundamentals cohort is the first focus. |
| Program module or learning unit | Moodle course, course section, activity, or resource | The exact mapping depends on how the curriculum is structured. |
| Session | Moodle activity, calendar event, custom plugin record, or course section item | Jigsaw sessions may need a custom activity module or local plugin model. |
| Partner group | Moodle group | Moodle groups are the preferred native representation for partner groups. |
| Breakout room assignment | Custom plugin record plus export output | Moodle groups alone are not enough because Zoom room names and weekly topic balancing are operational outputs. |
| Topic choice | Moodle Choice activity or custom topic-selection surface | Students should experience this as selecting or working on topics, not as participating in a visible jigsaw mechanism. |
| Presentation | Moodle assignment, database activity, file resource, or custom presentation model | Depends on whether presentations require review, publishing, metadata, or peer visibility. |
| Program communication space | Moodle communication/forum/message features or custom communication integration | Final direction depends on desired chat-like behavior. |

### 2.4 Core Platform Objects

At minimum, the product should define and support these durable objects:

- User.
- Applicant profile.
- Student profile.
- Mentor profile.
- Cohort course.
- Course or program module.
- Session.
- Partner group as a Moodle group.
- Topic.
- Topic selection.
- Jigsaw assignment.
- Breakout room.
- Assignment.
- Submission.
- Presentation.
- Attendance or participation record.
- Communication space.
- File or learning resource.
- Export record.

Some objects may live directly in Moodle core tables, some may be represented by Moodle configuration, and some may require custom plugin tables. The PRD should describe required behavior first; the implementation plan should decide the exact data ownership.

### 2.5 Access And Permission Requirements

The platform should enforce role-based access consistently.

- Applicants can only access application and onboarding surfaces intended for them.
- Students can only access cohorts, courses, groups, sessions, materials, submissions, and communication spaces they are enrolled in or explicitly permitted to view.
- Students should not see private admin notes, internal balancing logic, private mentor availability, hidden exports, or unpublished review decisions.
- Mentors can access assigned cohorts, relevant group/student information, and review workflows only where permitted.
- Program admins can manage program operations but should not need full Moodle site-administration permissions for routine work.
- Platform admins can configure Moodle, install or update plugins, manage system-level settings, and perform maintenance.
- Sensitive exports should be restricted to admins or explicitly authorized staff.

### 2.6 Account And Identity Direction

The platform should support account-based student participation. Students should be able to log in, maintain relevant profile information, and use the platform as their primary program environment.

Identity requirements:

- Email should remain the primary stable identity field.
- The platform should support future external login options if useful, such as Google, Microsoft, or organization SSO.
- Applicants should only receive Moodle accounts after acceptance.
- Public visitors should be able to view general program information before login.
- Discord username should not be required for core participation once communication moves into the platform.
- Future Web3 identity fields, such as wallet address or credential identifiers, should be treated as optional profile or plugin-managed data until concrete requirements are defined.
- User status should distinguish between applicants, accepted students, active students, paused students, alumni, mentors, admins, and removed/deactivated users.

### 2.7 Public And Pre-Login Experience

The platform should support a public, unauthenticated experience for general program information. This public experience may be implemented through Moodle front-page content, guest-visible course or page content, a custom theme page, a local plugin route, or a separate public frontend that links into Moodle.

Public users should be able to:

- Learn what Web3 Talents is.
- View general program structure.
- View high-level curriculum or cohort information approved for public visibility.
- Understand application timing and eligibility.
- Navigate to the application flow when applications are open.
- Log in after acceptance.

Public users should not be able to:

- Participate in course activities.
- Access private cohort materials.
- See student, mentor, or admin-only communication.
- Submit assignments.
- Join partner groups.
- Access exports, attendance, or internal operational data.

Moodle supports guest-visible content, but guest users are view-only for course activities. This is acceptable for the general-information use case. Any pre-login application workflow should be specified separately because applicants should not receive Moodle accounts until after acceptance.

Sources:

- Moodle Guest access documentation: https://docs.moodle.org/502/en/Guest_access
- Moodle Access API documentation: https://moodledev.io/docs/5.1/apis/subsystems/access

### 2.8 Decisions Captured

- One Web3 Talents cohort maps to one Moodle course.
- Multiple cohorts may run at the same time later.
- The fundamentals cohort is the first course/cohort focus.
- Partner groups should be represented as Moodle groups.
- Jigsaw mechanics should be represented as internal workflow data managed by the custom Web3 Talents plugin, not as a student-facing Moodle activity branded as "jigsaw."
- Applicants should receive Moodle accounts only after acceptance.
- Mentors can use Moodle teacher roles.
- Alumni should retain access to past cohort materials after the program ends.

### 2.9 Jigsaw Workflow Visibility

Students should not need to understand or see that the program is using a jigsaw learning model. The student-facing experience should be framed around learning, preparing, and sharing topics with peers.

Student-facing surfaces should use language such as:

- Topic selection.
- Topic preparation.
- Group discussion.
- Peer sharing.
- Session room.
- Presentation or reflection.

Student-facing surfaces should avoid internal operational language such as:

- Jigsaw algorithm.
- Balancing logic.
- Vote-to-topic reconciliation.
- Non-voter fallback assignment.
- Breakout-room generation rules.
- Zoom pre-assignment export.

The internal jigsaw workflow should be managed by a custom Web3 Talents plugin, likely a local plugin such as `local_web3talents`. This plugin should store and manage session-level operational records that reference Moodle users, courses, groups, and topics.

The plugin should allow admins and mentors, where appropriate, to:

- Define session topics.
- Collect or review topic selections.
- Assign each Moodle partner group to a topic.
- Balance groups across topics.
- Generate breakout-room assignments.
- Manually adjust room assignments.
- Export Zoom-compatible CSV files.
- Export internal review files.

Students should see only the outputs relevant to participation, such as their assigned topic, preparation materials, group activity instructions, room information when appropriate, and follow-up tasks.

## 3. Product Scope And Priority

### 3.1 Priority Definitions

Priority levels for the Moodle platform:

- P0: Required for the first usable Moodle-based platform.
- P1: Important soon after the first release.
- P2: Useful later, dependent on program maturity, operational feedback, or design capacity.

The first Moodle release should be student-facing, not admin-only. It should give students a real reason to use the platform during the program while giving admins enough operational control to run the fundamentals cohort.

### 3.2 First Release Scope

The first Moodle-based release should support the fundamentals cohort end to end at a practical level.

Required first-release workflows:

1. Public visitor views general program information before logging in.
2. Accepted student receives or activates a Moodle account.
3. Student logs in and sees a branded Web3 Talents course experience.
4. Student accesses cohort materials and session resources.
5. Student participates in platform-based communication or discussion.
6. Student can view any required work instructions; upload/submission workflows may be added after the first release.
7. Admin manages the fundamentals cohort as one Moodle course.
8. Admin creates and manages individual student accounts only for accepted applicants.
9. Admin assigns students to partner groups represented as Moodle groups.
10. Admin or mentor defines session topics.
11. Students select topics through Moodle Choice or work with assigned topics without seeing internal jigsaw mechanics.
12. Admin generates topic-balanced session room assignments from Moodle users and groups.
13. Admin can manually adjust generated assignments.
14. Admin exports a Zoom-compatible CSV when Zoom remains the live-session tool.
15. Alumni retain access to appropriate past cohort materials after the course ends.

### 3.3 Feature Priority Matrix

| Priority | Feature Area | First Direction |
| --- | --- | --- |
| P0 | Moodle foundation | Moodle is the core platform for users, courses, roles, materials, assignments, and learning records. |
| P0 | Custom Web3 Talents theme | Implement the design team's Figma direction as a branded Moodle experience. |
| P0 | Fundamentals cohort course | One fundamentals cohort maps to one Moodle course. |
| P0 | Student accounts after acceptance | Accepted students receive Moodle accounts; applicants do not need accounts before acceptance. |
| P0 | Public general-info pages | Public visitors can view approved program information before login. |
| P0 | Student dashboard/course entry | Students can quickly see current cohort information, upcoming tasks, materials, communication, and session-related actions. |
| P0 | Course materials | Students can access learning materials inside Moodle. |
| P0 | Program communication baseline | Moodle forums, messages, and course communication are sufficient for the first release. |
| P0 | Moodle groups for partner groups | Partner groups are represented as Moodle groups within the cohort course. |
| P0 | Hidden jigsaw operations plugin | Admins can manage topic balancing and room generation without exposing the jigsaw model to students. |
| P0 | Topic selection or assignment | Students select topics through Moodle Choice initially. |
| P0 | Zoom CSV export | Admins can export strict Zoom-compatible breakout pre-assignment CSV files. |
| P0 | Alumni material access | Alumni retain access to approved past cohort materials. |
| P1 | Assignments/submissions | Students can upload or link required work through Moodle-native assignment flows where practical. |
| P1 | Applicant intake and review | Public applications can be collected and reviewed before account creation. |
| P1 | Presentation archive | Students can upload or link presentations; presentations can be browsed by session, group, or student where appropriate. |
| P1 | Attendance and participation tracking | Admins/mentors can track attendance, participation, or session completion. |
| P1 | Mentor availability | Mentors can indicate availability for sessions or groups. |
| P1 | Rich internal exports | Admins can export internal Excel reports for review and audit history. |
| P1 | Better communication experience | Improve communication beyond baseline forums/messages if Moodle defaults are not enough. |
| P1 | Admin operations dashboard | Program admins get a centralized dashboard for cohort health, pending work, submissions, sessions, and exports. |
| P1 | Multi-cohort support | Multiple cohorts can run at the same time with clear separation of users, courses, materials, groups, and communications. |
| P2 | Web3 identity and credentials | Optional wallet fields, certificates, proof-of-participation, credentials, or token-related features. |
| P2 | Collaborative document creation | Integrate or build in-browser document creation only if clearly needed. |
| P2 | Advanced analytics | Deeper reporting on participation, assignments, retention, topic distribution, and alumni activity. |
| P2 | External integrations beyond Zoom | Add integrations only when they serve a concrete workflow. |

### 3.4 Scope Changes From The Original Custom Platform

The Moodle migration changes the product shape in several important ways.

- The platform is no longer admin-only for the first useful release.
- Discord poll sync is no longer a core requirement because communication and topic workflows should move into the platform.
- Student accounts are now required for the first release because students are expected to use Moodle directly.
- Moodle-native course materials and communication become core platform features.
- Moodle-native assignment submissions are useful but not required for the first release because student work is graded during live Zoom sessions.
- The jigsaw workflow remains important, but its mechanics are hidden from students.
- The current TypeScript app should not remain the long-term user interface, but its validated logic should inform the Moodle plugin implementation.
- Zoom export remains necessary only while Zoom remains the live-session tool.

### 3.5 First Release Non-Goals

The first Moodle release does not need to include:

- A full CRM-like applicant pipeline if accepted students can be created manually or imported for the first cohort.
- Student file-upload or submission workflows.
- A polished collaborative document editor.
- Full replacement of Zoom for live sessions.
- Full replacement of every possible Discord feature beyond Moodle forums, messages, and course communication.
- Advanced Web3 credentials or wallet-based access.
- Custom UI replacement for every standard Moodle settings page.
- Complex multi-cohort automation beyond ensuring the architecture does not block multiple cohorts later.

### 3.6 Success Criteria

The Moodle migration first release is successful when:

- Students can use the platform as their main course home.
- Admins can run the fundamentals cohort without relying on Discord as the central student communication surface.
- Course materials, communication, topic choices, and partner groups live inside Moodle.
- Students can complete topic-related work without seeing internal jigsaw mechanics.
- Admins can generate and adjust balanced session room assignments.
- Admins can export a Zoom-compatible CSV that works with the current Zoom workflow.
- The Figma-based visual direction is meaningfully reflected in the Moodle theme and key custom pages.
- Alumni access to past materials works according to the agreed access rules.
- The platform can support additional cohorts later without major conceptual redesign.

### 3.7 Decisions Captured

- Moodle forums, messages, and course communication are enough for the first release.
- Accepted-student account creation should be individual-based.
- Account creation must include a way to verify that the student has been accepted before the account is created or activated.
- Moodle should verify account creation against an accepted-applicant list.
- SSO is not needed for the first release because the program does not plan to provide institutional email accounts.
- Student submissions are not required for the first release because grading happens during live Zoom sessions.
- Student uploads and submission management should be treated as a later quality-of-life improvement.
- Public pages should live inside Moodle, not in a separate public website that links to Moodle.
- Topic selection should use Moodle Choice initially.

### 3.8 Remaining Open Questions

- Which Moodle communication tools should be enabled first: course announcements, forums, direct messaging, group messaging, or all of them?
- Which public pre-login pages are required for launch?

## 4. Public Pages, Accepted Applicants, And Account Creation

### 4.1 Purpose

The platform should allow public visitors to learn about Web3 Talents before login while ensuring only accepted applicants can become student users. Applicants should not need Moodle accounts before acceptance.

This section defines the first-release account boundary:

- Public information is visible before login.
- Application and acceptance happen before Moodle account creation.
- Accepted applicants are represented in an accepted-applicant list.
- Moodle account creation checks against that accepted-applicant list.
- Accepted students become enrolled Moodle users in the appropriate cohort course.

### 4.2 Public Pre-Login Experience

Public pages should live inside Moodle and use the Web3 Talents theme. They should not be hosted as a separate external website for the first release.

Public visitors should be able to view:

- Web3 Talents overview.
- Program structure.
- Fundamentals cohort overview.
- High-level curriculum outline.
- Program dates or application timeline, if available.
- Eligibility or target audience information.
- Application status information, such as whether applications are open or closed.
- Login entry point for accepted students, mentors, and admins.

Public pages should not expose:

- Private course materials.
- Student names or profiles.
- Mentor-only information.
- Internal session planning.
- Partner groups.
- Topic selection results.
- Zoom links unless intentionally public, which should generally be avoided.
- Admin exports.
- Acceptance decisions for other applicants.

### 4.3 Accepted-Applicant List

The accepted-applicant list is the source used to determine whether a person is allowed to receive or activate a Moodle student account.

The accepted-applicant list should support at minimum:

- First name.
- Last name.
- Email address.
- Cohort identifier.
- Acceptance status.
- Optional notes visible only to admins.
- Created or imported timestamp.
- Account creation status.

Acceptance statuses should include:

- Accepted.
- Account created.
- Account activated.
- Deferred.
- Removed or revoked.

The first release may support accepted-applicant list management through a simple admin import or admin entry flow. A full applicant review pipeline is not required for the first release.

Accepted-applicant import should support both CSV and Excel files.

### 4.4 Account Creation Flow

Student account creation should be individual-based and acceptance-gated.

Baseline flow:

1. Admin opens the student account creation flow.
2. Admin enters the student's email address.
3. Platform checks the accepted-applicant list.
4. If the email is accepted and not already activated, admin can create the Moodle user account.
5. Platform assigns the student to the correct cohort course.
6. Platform applies the student role in that course.
7. Platform marks the accepted-applicant record as account created.
8. Student receives login or activation instructions.
9. After first login or activation, platform can mark the account as activated if technically practical.

Student activation instructions should be sent through Moodle email.

The flow should prevent:

- Creating a student account for an email not present on the accepted-applicant list.
- Creating duplicate student accounts for the same accepted applicant.
- Enrolling a student into the wrong cohort when the accepted-applicant list specifies a cohort.
- Giving applicant or student users admin-level permissions by mistake.

### 4.5 Admin Management Requirements

Program admins should be able to:

- Add an accepted applicant manually.
- Import accepted applicants from CSV or Excel if needed.
- Search accepted applicants by name or email.
- See whether an accepted applicant has a Moodle account.
- Create an account for an accepted applicant.
- Resend or regenerate activation instructions if needed.
- Revoke or defer an accepted applicant before account activation.
- See basic errors when account creation is blocked.

Platform admins should be able to configure:

- Which Moodle course corresponds to each cohort.
- Default role assigned to accepted students.
- Whether account activation emails are sent automatically.
- Required user profile fields.

### 4.6 Student Onboarding Experience

After account creation, accepted students should be able to:

- Log in or activate their account.
- Confirm basic profile information.
- Access the fundamentals cohort course.
- See the course home/dashboard.
- Read onboarding instructions.
- Access communication areas.
- Access current materials and session information.
- Understand what they need to do next.

Student onboarding should avoid exposing unnecessary Moodle administration concepts. The experience should be guided by the Web3 Talents theme and any custom dashboard surfaces needed for clarity.

### 4.7 Future Applicant Pipeline

A full applicant pipeline is a later feature. Future scope may include:

- Public application form.
- Applicant status tracking.
- Review notes.
- Accept/reject/waitlist decisions.
- Email notifications.
- Conversion from accepted application to accepted-applicant list record.
- Reporting on application funnel metrics.

The first release only requires the accepted-applicant list and acceptance-gated account creation.

### 4.8 Acceptance Criteria

- Public visitors can view approved general program information before login.
- Public visitors cannot access private cohort content or student data.
- Applicants do not need Moodle accounts before acceptance.
- Admin can maintain an accepted-applicant list.
- Admin can create an individual Moodle student account only when the email appears on the accepted-applicant list with an eligible status.
- Created students are enrolled in the correct cohort course.
- Student role assignment is correct.
- Duplicate accounts are prevented or clearly flagged.
- Accepted-applicant records show whether an account has been created.

### 4.9 Open Questions

No open questions currently.

### 4.10 Decisions Captured

- Accepted-applicant list import should support both CSV and Excel.
- Student activation instructions should be sent through Moodle email.
- The only required public page for the first release is an overview page.
- The overview page will be designed by the design team and implemented inside Moodle.

## 5. Fundamentals Cohort Course And Student Experience

### 5.1 Purpose

The fundamentals cohort course is the first student-facing Moodle course. It should be the main home for accepted students during the program and should provide a coherent Web3 Talents experience rather than exposing students to a generic Moodle course shell.

The course should help students:

- Understand where they are in the program.
- Access current and past learning materials.
- Find session information.
- Participate in course communication.
- Select or prepare topics when required.
- Understand what they need to do next.
- Return to past materials after becoming alumni.

### 5.2 Course Model

One cohort maps to one Moodle course.

The first course is the fundamentals cohort. Later, multiple cohorts may run at the same time, each as a separate Moodle course. Future specialized cohorts or advanced tracks should also map cleanly to separate courses unless a later product decision changes this model.

The fundamentals course should support:

- Enrolled students.
- Mentor users with teacher role.
- Program admins.
- Moodle groups for partner groups.
- Course materials.
- Communication areas.
- Topic-choice activities.
- Session information.
- Alumni access after course completion.

### 5.3 Student Course Home

The student course home should be a branded entry point into the cohort. It may be implemented through Moodle course formatting, a theme customization, blocks, or a custom dashboard surface.

The course home should show:

- Current cohort name.
- Current or next session information.
- Important announcements.
- Links to current learning materials.
- Topic selection when active.
- Communication entry points.
- Upcoming tasks or preparation items.
- Access to past materials.

The course home should not require students to understand Moodle administration concepts, hidden plugin workflows, or internal grouping logic.

### 5.4 Course Sections And Materials

The course should organize materials in a way that matches the program structure. The first release should use a simple structure that is easy to maintain.

Required section model:

- Welcome and onboarding.
- Topic-based sections.
- Resources.
- Presentations or showcase area if included later.

Each session section may include:

- Session overview.
- Learning materials.
- Preparation instructions.
- Topic choice activity if needed.
- Discussion or communication link.
- Follow-up materials.

Materials may include:

- Pages.
- Files.
- Links.
- Embedded media.
- PDFs.
- Slides.
- External resources.

### 5.5 Topic Selection Experience

Topic selection should use Moodle Choice initially.

Students should see topic selection as a normal course activity or course task. The language should be simple and learning-oriented, such as "Choose your topic for this session."

Topic selection should:

- Present available session topics.
- Allow students to choose a topic when the choice window is open.
- Respect any configured choice limits if needed.
- Be visible in the relevant course/session context.
- Be accessible from the course home when active.

Topic selection should not expose:

- Group balancing rules.
- Partner-group reconciliation rules.
- Internal fallback assignment rules.
- Zoom export mechanics.
- The term "jigsaw" unless program leadership later decides it should be visible.

The custom Web3 Talents operations plugin should be able to read or reference Moodle Choice results for the hidden room-generation workflow.

### 5.6 Communication Baseline

The first release should use Moodle-native communication features rather than a custom real-time chat system.

The communication baseline should include:

- Course announcements.
- Course forum.
- Moodle messaging where appropriate.
- Moodle Choice or poll-style interaction for topic selection.

The communication experience should be accessible from the course home and relevant session areas. It should be enough to reduce Discord dependency for the fundamentals cohort, even if it does not fully replicate a chat application.

Communication priority order:

1. Announcements.
2. Course forum.
3. Direct messaging.
4. Poll or choice interaction.

### 5.7 Assignments And Submissions

Student submissions are not required for the first release. Student work is currently graded during live Zoom sessions.

The first release should still support clear instructions for preparation or required work through course pages, resources, or session materials.

Later, Moodle assignment activities can be added for:

- File uploads.
- Link submissions.
- Reflection submissions.
- Presentation uploads.
- Group submissions.
- Review or grading records.

### 5.8 Alumni Access

Students should retain access to approved past cohort materials after the cohort ends. Alumni should remain enrolled in the same course because each new cohort will have its own separate Moodle course.

Alumni access should allow:

- Viewing past learning materials.
- Viewing approved resources.
- Viewing any allowed presentation or showcase content.

Alumni access should not automatically allow:

- Participation in active future cohort communication.
- Submitting new work to closed activities.
- Viewing private current-cohort materials unless explicitly allowed.
- Accessing admin exports or operational data.

The exact alumni permission model may use Moodle roles, course completion state, enrolment status, group membership, or custom fields.

### 5.9 Acceptance Criteria

- Accepted students can log in and access the fundamentals cohort course.
- Students see a branded course home aligned with the Figma direction.
- Students can access current and past materials in the course.
- Students can find session information.
- Students can use Moodle-native communication features for course communication.
- Students can use Moodle Choice for active topic selections.
- Topic choices are available to the custom operations plugin for room-generation workflows.
- Students do not see internal jigsaw logic.
- Mentors with teacher role can access and support the course.
- Alumni retain access to approved past materials after the course ends.

### 5.10 Decisions Captured

- The fundamentals course should be organized with topic-based sections.
- Communication tools should be enabled in this priority order: announcements, course forum, direct messaging, poll or choice interaction.
- Alumni remain enrolled in the same course after the cohort ends.
- Each new cohort should receive a new Moodle course.

### 5.11 Open Questions

No open questions currently.

## 6. Partner Groups, Topic Choices, And Room Generation

### 6.1 Purpose

The platform should preserve the existing high-value group and room generation workflow while moving it into Moodle. Students should experience the workflow as normal topic learning and course participation. Admins and mentors should have the operational tools needed to prepare live sessions, assign topic-balanced rooms, and export Zoom-compatible pre-assignment files.

The workflow should no longer depend on Discord polls. Topic choices should originate from Moodle Choice activities in the cohort course.

### 6.2 Partner Groups

Partner groups should be represented as Moodle groups inside the cohort course.

Partner groups should support:

- Two-person groups.
- Three-person groups.
- Admin-created groups.
- Manual group membership edits.
- Visibility to mentors and admins.
- Student visibility where useful for collaboration.

Partner groups should be used by the hidden operations workflow to keep students together during topic assignment and room generation.

Validation requirements:

- Admins should be warned if a partner group has fewer than two students.
- Admins should be warned if a partner group has more than three students.
- Admins should be warned if a student is not assigned to a partner group when required.
- Admins should be warned if a group contains users who are not active students in the course.

### 6.3 Topic Choices

Topic selection should use Moodle Choice initially.

Each topic-selection activity should:

- Belong to the cohort course.
- Be associated with a relevant topic section or session context.
- Present the available topic options.
- Be accessible to enrolled students.
- Allow admins or mentors to review student responses.
- Provide response data that the custom operations plugin can use.

The platform should support four topic choices for the first release because the existing workflow assumes four topics. Later releases may support a configurable topic count if needed.

Student-facing wording should focus on choosing or preparing a topic, not on group balancing or jigsaw mechanics.

### 6.4 Hidden Operations Workflow

The hidden operations workflow should be implemented in a custom Web3 Talents plugin, likely a local plugin such as `local_web3talents`.

The plugin should let admins:

1. Select a Moodle course/cohort.
2. Select the Moodle Choice activity containing topic choices.
3. Load enrolled students and Moodle partner groups.
4. Read student topic choices from Moodle Choice responses.
5. Apply choices to each partner group.
6. Assign one topic to each partner group.
7. Balance partner groups across topics.
8. Generate live-session room assignments.
9. Manually adjust room assignments.
10. Export Zoom-compatible CSV.
11. Export internal review data.

The workflow should keep partner groups together even when students in the same group choose different topics.

### 6.5 Topic Assignment Rules

The Moodle implementation should preserve the validated behavior from the current TypeScript platform unless a later product decision changes it.

Rules:

- If all students in a partner group choose the same topic, assign the group to that topic.
- If only one student in a partner group chooses a topic, assign the group to that topic.
- If students in a partner group choose different topics, keep the group together and assign the topic that best balances topic counts.
- If a three-person group has a majority topic choice, assign the group to the majority topic.
- If no students in a partner group choose a topic, assign a topic that helps balance topic counts.
- Keep two-person and three-person partner groups together even when choices differ.

The plugin should show warnings for:

- Students without topic choices.
- Topic choices from users not in valid partner groups.
- Partner groups with no responses.
- Partner groups with split responses.
- Invalid partner group sizes.

Warnings should help admins review the result without exposing this complexity to students.

### 6.6 Room Generation Rules

The room generator should create live-session room assignments from partner-group topic assignments.

Rules:

- Room names should use the format `Room1`, `Room2`, up to `RoomN`.
- Rooms should aim to contain one partner group per topic where possible.
- Rooms should target roughly eight to ten students where practical.
- Room count should scale with cohort size.
- Admins should be able to choose or adjust the room count if needed.
- Partner groups should not be split across rooms.
- Admins should be warned when a room has duplicate topics.
- Admins should be allowed to proceed with imperfect room balance when needed.

### 6.7 Manual Override

Admins should be able to manually move partner groups between generated rooms before exporting.

Manual override should:

- Preserve partner-group membership.
- Update room assignment previews immediately.
- Update the current room assignment result rather than creating multiple saved versions.
- Show warnings if the override creates duplicate topics or uneven rooms.
- Allow admins to proceed despite warnings.
- Be reflected in all exports.

### 6.8 Exports

The first release requires Zoom-compatible CSV export.

Zoom CSV requirements:

- File format: CSV.
- Required columns only:
  - `Pre-assign Room Name`
  - `Email Address`
- Room names use the exact `Room1`, `Room2`, `RoomN` format.
- Each enrolled student assigned to a room appears with the email address used by Zoom.

Internal review export is P1, not required for first release, but the plugin should be designed so internal reporting can be added later.

### 6.9 Student Visibility

Students should not see:

- The hidden room generation workflow.
- Internal balancing warnings.
- Export tools.
- Admin override controls.
- The reason their partner group was assigned to a topic.

Students may see:

- Their own topic choice.
- Their assigned or expected topic where appropriate.
- Their partner group members where appropriate.
- Session preparation instructions.
- Room information before or during a session.

Mentors should be able to view generated room assignments before export so they can prepare for the live session.

### 6.10 Acceptance Criteria

- Partner groups are represented as Moodle groups.
- Admins can validate partner group size and membership.
- Students can make topic selections using Moodle Choice.
- The operations plugin can read Moodle Choice responses.
- The operations plugin can assign one topic per partner group.
- Partner groups remain together even when individual topic choices differ.
- Non-responsive groups can be assigned balanced fallback topics.
- Admins can generate room assignments.
- Admins can manually move partner groups between rooms.
- The platform keeps only the latest generated room assignment result for a topic-choice activity.
- Zoom-compatible CSV export matches the expected column names and room format.
- Mentors can view generated room assignments before export.
- Students can view relevant room information in Moodle.
- Students do not see hidden jigsaw mechanics.

### 6.11 Decisions Captured

- The platform should keep only the latest room-generation result for a topic-choice activity.
- Mentors should be able to view room assignments before export.
- Students should be able to view relevant room information inside Moodle.

### 6.12 Open Questions

No open questions currently.

## 7. Design, Theme, And Frontend Experience

### 7.1 Purpose

The Moodle platform should feel like a branded Web3 Talents product, not a default Moodle installation with minor visual changes. The design team's Figma work should guide the public overview page, student experience, mentor experience, and key admin workflows.

The implementation should respect Moodle's architecture while using custom theme and plugin surfaces where the default Moodle UI would make important workflows confusing or visually inconsistent.

### 7.2 Design Source Of Truth

The design team will provide Figma files. The Figma files should be treated as the source of truth for:

- Visual identity.
- Typography.
- Color system.
- Spacing.
- Layout direction.
- Navigation patterns.
- Component states.
- Public overview page.
- Student dashboard/course home.
- Key student course surfaces.
- Mentor and admin workflow surfaces where designed.

The implementation should translate Figma into Moodle-compatible theme templates, CSS, JavaScript, and custom plugin pages. Figma files should not be assumed to import directly into Moodle.

Figma implementation can be iterative. The first release does not need to perfectly implement every designed screen if Moodle-native functionality is usable and the product can adapt the design over time.

### 7.3 Moodle Theme Requirements

The platform should include a custom Moodle child theme based on Boost for the first release.

The theme should cover:

- Public overview page styling.
- Login and account activation styling where practical.
- Course home styling.
- Navigation.
- Typography.
- Buttons and links.
- Forms.
- Cards or content blocks.
- Tables.
- Alerts and warnings.
- Empty states.
- Responsive behavior.
- Student-facing Moodle activity pages where practical.

The theme should avoid modifying Moodle core. Theme behavior should be implemented through Moodle's supported theme system, templates, SCSS/CSS, JavaScript, configuration, and plugin-compatible overrides.

### 7.4 Custom Plugin UI Requirements

Custom Web3 Talents plugin pages should follow the same design system as the theme.

Custom-designed plugin surfaces are likely needed for:

- Student dashboard enhancements.
- Program admin dashboard.
- Accepted-applicant list management.
- Account creation and acceptance verification.
- Partner-group overview.
- Hidden room-generation workflow.
- Manual room assignment adjustments.
- Mentor room-assignment preview.
- Student room information view.
- Zoom CSV export workflow.

These pages should be designed for the specific workflow instead of exposing raw Moodle tables or scattered configuration screens.

### 7.5 Student Experience Requirements

The student experience should be simple, guided, and focused on program participation.

Students should be able to quickly answer:

- What cohort/course am I in?
- What should I do next?
- What topic or material should I prepare?
- Where is the communication area?
- What room or group am I assigned to for the session?
- Where can I find past materials?

Student-facing screens should avoid:

- Moodle administration terminology.
- Internal jigsaw mechanics.
- Raw plugin configuration details.
- Unnecessary settings pages.
- Dense default Moodle interfaces where a simpler custom surface is available.

### 7.6 Mentor Experience Requirements

Mentors should have access to the information needed to support students and live sessions.

Mentors should be able to:

- Access the cohort course.
- See relevant announcements and discussions.
- View partner groups.
- View generated room assignments before export.
- Access relevant topic/session materials.
- Support students through Moodle communication tools.

Mentor screens should avoid exposing platform-admin configuration unless required by the teacher role.

### 7.7 Admin Experience Requirements

Program admins should have streamlined workflow pages for common operations.

Admin experience should support:

- Accepted-applicant list management.
- Individual account creation with accepted-applicant verification.
- Cohort course setup references.
- Partner-group review.
- Topic choice review.
- Room generation.
- Manual room adjustment.
- Export generation.
- Basic operational status checks.

Admin screens should prioritize clarity, validation, and warnings over visual complexity.

### 7.8 Responsive And Accessibility Requirements

The theme and custom pages should be usable on common laptop and desktop sizes. Student-facing pages should also be usable on mobile because students may check course information, communication, or room assignments from phones.

Requirements:

- Public overview page works on desktop and mobile.
- Student course home works on desktop and mobile.
- Topic choice access works on desktop and mobile.
- Communication entry points are visible on desktop and mobile.
- Room information is readable on desktop and mobile.
- Admin-heavy workflows may prioritize desktop but should not break on smaller screens.
- Color contrast should be sufficient for readable text and UI controls.
- Interactive controls should have clear labels, focus states, and accessible names.

### 7.9 Implementation Boundaries

The design should be implemented through:

- Moodle theme or child theme.
- Moodle templates.
- Moodle-compatible CSS/SCSS.
- Moodle-compatible JavaScript.
- Custom plugin pages.
- Moodle configuration where appropriate.

The first release should not require:

- Forking Moodle core.
- Rebuilding all Moodle screens from scratch.
- Creating a separate public website outside Moodle.
- Building a standalone React/Next.js frontend as the primary product shell.

If a highly interactive UI is required inside a custom plugin page, the implementation may use JavaScript components where appropriate, but Moodle remains the application platform.

### 7.10 Acceptance Criteria

- Public overview page reflects the Figma visual direction.
- Student course home reflects the Figma visual direction.
- Key custom plugin pages use the Web3 Talents design system.
- Students can navigate the platform without relying on default Moodle admin-style pages.
- Mentor room-assignment preview is clear and usable.
- Admin room-generation workflow is clear and usable.
- Student room information is readable on desktop and mobile.
- Theme and plugin implementation avoid Moodle core modifications.

### 7.11 Open Questions

No open questions currently.

### 7.12 Decisions Captured

- Figma files will include public, student, mentor, and admin views.
- The first release does not need to implement the Figma work perfectly everywhere.
- The platform can adapt the design over time.
- The first custom Moodle theme should be built as a Boost child theme.
- The public overview page seen before login is the only first-release screen requiring high Figma fidelity.
- Student, mentor, and admin screens can start with lower-fidelity Moodle-native or lightly branded implementations.
- Higher-fidelity student, mentor, and admin integrations can be added as the design team completes those designs.

## 8. Communication And Course Interaction

### 8.1 Purpose

The platform should move program communication into Moodle enough that Discord is no longer the central student experience. The first release does not need a full real-time chat replacement. Moodle-native communication tools are sufficient if they support announcements, course discussion, direct communication, and topic-choice interaction.

### 8.2 First-Release Communication Tools

Communication tools should be enabled in this priority order:

1. Announcements.
2. Course forum.
3. Direct messaging.
4. Poll or choice interaction through Moodle Choice.

The first release should avoid building a custom chat system unless Moodle-native tools prove insufficient after real cohort usage.

### 8.3 Announcements

Announcements should support one-way or primarily admin/mentor-led communication to the cohort.

Requirements:

- Program admins and mentors with teacher role can post announcements.
- Students can view announcements in the cohort course.
- Announcements should be easy to find from the course home.
- Important announcements should be visible enough that students do not need Discord for basic program updates.
- Announcement behavior may use Moodle's native announcements/forum behavior where appropriate.

### 8.4 Course Forum

The course forum should support cohort-level discussion.

Requirements:

- Students can read and participate in course discussions.
- Mentors and admins can participate in discussions.
- Forum access is restricted to enrolled users in the cohort course.
- Forum links should be visible from the student course home.
- Forum usage should support questions, clarifications, resource sharing, and cohort discussion.

Group-specific forums are not required for the first release unless they are easy to configure with Moodle groups.

### 8.5 Direct Messaging

Direct messaging should support basic one-to-one or small-group communication where Moodle supports it.

Requirements:

- Students can contact other students.
- Students can contact mentors/admins where permitted.
- Mentors/admins can contact students.
- Messaging should respect Moodle permissions and privacy settings.
- Direct messaging should not replace formal announcements or course-level discussion.

### 8.6 Poll And Choice Interaction

Moodle Choice should support topic selection and poll-style interaction.

Requirements:

- Topic choices can be created inside the cohort course.
- Students can submit their topic choice in Moodle.
- Admins and mentors can review responses.
- The hidden operations plugin can use Choice responses for room generation.
- Choice activity wording should remain student-friendly and should not expose internal balancing logic.

### 8.7 Notifications

The first release should rely on Moodle's native notification and email behavior where practical.

Notifications may include:

- Account activation emails.
- Announcement notifications.
- Forum notifications.
- Message notifications.
- Course activity notifications where Moodle supports them.

The PRD does not require custom push notifications for the first release.

Moodle email notification behavior should be left to user preference where Moodle supports that control.

### 8.8 Communication Boundaries

The first release does not need:

- Discord integration.
- Slack-style real-time channels.
- Threaded chat parity with Discord.
- Voice channels.
- Custom reactions.
- Custom mobile push notification system.
- Custom moderation tooling beyond Moodle-native capabilities.

These can be revisited after the first cohort uses Moodle-native communication.

### 8.9 Acceptance Criteria

- Students can receive and view course announcements.
- Students can participate in a course forum.
- Students can use Moodle direct messaging where permitted.
- Students can submit topic choices through Moodle Choice.
- Admins and mentors can review Choice responses.
- The platform does not require Discord for first-release cohort communication.
- Communication tools are accessible from the course home or obvious course navigation.

### 8.10 Decisions Captured

- Direct messaging should be enabled between students, mentors, and admins where Moodle permissions allow it.
- Enrolled users should be allowed to post directly in the course forum without pre-publication moderation.
- Moodle email notifications should be left to user preference where Moodle supports that control.

### 8.11 Open Questions

No open questions currently.

## 9. Course Materials, Assignments, Presentations, And Files

### 9.1 Purpose

The platform should centralize learning materials inside Moodle so students use Moodle as the primary place to access program content. The first release should prioritize viewing and organizing materials over building advanced submission workflows.

Student uploads and formal submission management are useful later, but they are not required for the first release because student work is currently reviewed and graded during live Zoom sessions.

### 9.2 Course Materials

Course materials are P0 for the first release.

The fundamentals cohort course should provide access to:

- Topic learning materials.
- Session resources.
- Preparation instructions.
- Links to external resources.
- Slides or PDFs.
- Recordings or media links if available.
- Follow-up materials.

Materials should be organized by topic-based course sections.

First-release materials may be migrated from existing Google Drive content into Moodle.

Each material should have:

- Clear title.
- Optional short description.
- Relevant topic or section placement.
- Visibility controlled by Moodle course permissions.
- Access for enrolled students, mentors, and admins.
- Alumni visibility where approved.

### 9.3 Material Types

The first release may use Moodle-native resource types, including:

- Page.
- File.
- URL.
- Folder.
- Label or text/media area.
- Book, if longer structured content is needed.

The platform does not need a custom content repository for first release if Moodle-native course resources are sufficient.

### 9.4 Assignments And Submissions

Assignments and submissions are P1, not required for first release.

The first release should allow admins/mentors to provide work instructions through course materials. Students do not need to upload submissions into Moodle for launch.

Later assignment support may include:

- File upload submissions.
- Link submissions.
- Text reflections.
- Group submissions.
- Presentation submissions.
- Mentor review.
- Completion tracking.
- Grading records if the program wants Moodle to store grades later.

If assignments are added later, Moodle's native Assignment activity should be considered first before building custom submission tools.

### 9.5 Presentations

Presentation upload and archive are P1.

Future presentation support should allow:

- Students or admins to upload presentation files.
- Students or admins to submit presentation links.
- Presentations to be associated with students, partner groups, topics, and cohort course.
- Mentors/admins to review presentation materials if needed.
- Approved presentations to be visible to relevant users.
- Alumni to access approved past presentations where appropriate.

The first release may use simple Moodle resources or assignments for presentation handling if needed, but a polished presentation archive is not required for launch.

### 9.6 File Creation And Editing

The first release does not require in-browser collaborative file creation.

The platform may later support file creation or editing through:

- Moodle-native text editors.
- Assignment online text.
- Uploaded files.
- External document tools integrated into Moodle.
- Repository integrations.
- LTI integrations.
- Custom plugins if a specific workflow requires it.

The platform should not attempt to build a full collaborative document editor unless a future requirement clearly justifies the cost.

### 9.7 Alumni Material Access

Approved course materials should remain available to alumni in the same Moodle course after the cohort ends.

Alumni should be able to access:

- Publicly approved course materials.
- Past topic resources.
- Approved follow-up materials.
- Approved presentations if presentation archive is added.
- Old course forums for their cohort.

Alumni access should exclude:

- Private mentor/admin notes.
- Internal room-generation data.
- Admin exports.
- Active communication areas for future cohorts.
- Materials from other cohorts unless explicitly shared.

Alumni should be allowed to participate in old course forums for their cohort.

### 9.8 Acceptance Criteria

- Students can access course materials inside Moodle.
- Course materials are organized by topic-based sections.
- Materials support Moodle-native resource types such as pages, files, URLs, and folders.
- Students can access preparation instructions without needing Discord or Google Drive as the primary source.
- Mentors and admins can add or manage materials using Moodle-native tools.
- Alumni retain access to approved course materials after the cohort ends.
- Alumni can participate in old course forums for their cohort.
- Student submissions are not required for first release.
- Presentation archive is clearly treated as a later feature.

### 9.9 Decisions Captured

- First-release materials can be migrated from existing Google Drive content.
- Recordings can be omitted for the first release.
- Later, recordings may be hosted in Moodle or linked from Google Drive.
- Alumni should be able to participate in old course forums for their cohort.

### 9.10 Open Questions

No open questions currently.

## 10. Admin And Mentor Operations

### 10.1 Purpose

The Moodle platform should support the operational work required to run the fundamentals cohort without forcing admins and mentors to piece together workflows from scattered Moodle administration screens. Moodle-native tools should be used where they are sufficient, and custom Web3 Talents plugin pages should simplify program-specific workflows.

### 10.2 Program Admin Operations

Program admins should be able to manage the first-release workflow from Moodle.

Program admin operations include:

- Managing the accepted-applicant list.
- Creating individual student accounts after acceptance verification.
- Enrolling accepted students into the fundamentals cohort course.
- Managing course materials or coordinating with mentors who manage materials.
- Creating or reviewing Moodle groups used as partner groups.
- Creating or reviewing Moodle Choice activities for topic selection.
- Reviewing Choice responses.
- Running hidden room-generation workflows.
- Manually adjusting room assignments.
- Exporting Zoom-compatible CSV files.
- Viewing operational warnings and validation issues.
- Managing alumni access rules where needed.

### 10.3 Admin Dashboard

An admin dashboard is P1 unless required to make first-release operations practical.

The first release may use a small set of focused custom plugin pages instead of a full dashboard. However, the product should move toward a central admin dashboard that shows:

- Cohort/course status.
- Accepted applicants pending account creation.
- Students without partner groups.
- Partner groups with invalid sizes.
- Active topic choices.
- Topic choice response status.
- Generated room assignment status.
- Export readiness.
- Recent operational warnings.

### 10.4 Mentor Operations

Mentors should use the Moodle teacher role for the first release.

Mentors should be able to:

- Access the fundamentals cohort course.
- View course materials.
- Post announcements where appropriate.
- Participate in course forums.
- Direct message students where appropriate.
- View partner groups.
- View topic choice responses if permitted by course settings.
- View generated room assignments before export.
- Support students during live sessions.

Mentors should only view room assignments; they should not edit room assignments.

Mentors should not need:

- Moodle site-administration access.
- Access to accepted-applicant list management unless explicitly granted.
- Access to account creation workflows.
- Access to system configuration.

### 10.5 Operational Warnings

Admin and mentor workflows should surface clear warnings before live-session exports are used.

Warnings should include:

- Student account exists but student is not enrolled in the course.
- Accepted applicant has no Moodle account yet.
- Student is enrolled but not assigned to a partner group.
- Partner group has fewer than two students.
- Partner group has more than three students.
- Student has not responded to active topic Choice.
- Partner group has split topic choices.
- Partner group has no topic choice responses.
- Generated room has duplicate topics.
- Generated room has unusually high or low student count.
- Zoom export is missing a student email.

Warnings should be actionable and should not block admins unless the exported file would be invalid.

### 10.6 Exports And Operational Outputs

Zoom-compatible CSV export is P0.

Program admins should be able to:

- Generate the current room assignment.
- Review current room assignments.
- Share or expose relevant room assignments to mentors and students.
- Download the Zoom-compatible CSV.
- Regenerate the latest result if source data changes.

Only program admins should be able to download Zoom-compatible CSV exports.

Internal review/export reports are P1.

Future internal reports may include:

- Topic choices by student.
- Topic assignment by partner group.
- Room assignment by student.
- Room assignment by mentor.
- Students without responses.
- Invalid partner groups.
- Export timestamp and admin user.

### 10.7 Admin And Mentor Visibility

Visibility rules:

- Program admins can see all operational data for the cohort.
- Mentors can see course, partner group, topic, and room information needed to support students.
- Mentors can see generated room assignments before export.
- Students see only relevant participation information, not internal admin controls.
- Platform admins can access all configuration and technical maintenance surfaces.

### 10.8 Acceptance Criteria

- Program admins can manage accepted-applicant records and create eligible student accounts.
- Program admins can manage or review partner groups as Moodle groups.
- Program admins can review topic Choice responses.
- Program admins can create topic Choice activities for the operational workflow.
- Program admins can generate and adjust room assignments.
- Program admins can download Zoom-compatible CSV exports.
- Mentors can view generated room assignments before export.
- Mentors cannot edit room assignments.
- Mentors cannot create operational topic Choice activities.
- Mentors cannot download Zoom-compatible CSV exports.
- Operational warnings are visible and understandable.
- Mentors can support course communication and student participation without site-admin access.

### 10.9 Decisions Captured

- Mentors can view room assignments but cannot edit them.
- Only admins can create topic Choice activities for the operational workflow.
- Only admins can download Zoom-compatible CSV exports.

### 10.10 Open Questions

No open questions currently.

## 11. Moodle Architecture And Extension Model

### 11.1 Purpose

The platform should be built as an upgradeable Moodle-based system. Product-specific behavior should be implemented through supported Moodle extension points rather than direct Moodle core modifications.

This section defines the expected architecture boundaries for the PRD. Detailed implementation planning should later decide exact plugin names, database schemas, code structure, deployment steps, and test strategy.

### 11.2 Moodle Core Responsibilities

Moodle core should own standard learning-platform behavior:

- User accounts.
- Authentication.
- Roles and permissions.
- Courses.
- Enrolments.
- Groups.
- Course sections.
- Course resources.
- Moodle Choice activities.
- Forums and messaging.
- Assignments if added later.
- Completion or activity tracking if enabled later.
- Notifications and email behavior.
- File storage for Moodle-native resources.

The product should use Moodle's built-in capabilities before adding custom code, when the built-in capability fits the workflow.

### 11.3 Custom Theme

The first release should include a custom Boost child theme.

The theme should own:

- Web3 Talents visual identity.
- Public overview page styling.
- Basic branded Moodle shell.
- Student-facing visual polish where practical.
- Shared styles used by custom plugin pages.
- Responsive behavior for public and student-facing pages.

The theme should not own program business logic such as accepted-applicant verification, room generation, or exports.

### 11.4 Custom Local Plugin

The platform should include a custom Web3 Talents local plugin named `local_web3talents`.

The local plugin should own program-specific workflows that do not map cleanly to standard Moodle activities.

Initial responsibilities:

- Accepted-applicant list management.
- Accepted-applicant CSV/Excel import.
- Individual student account creation flow with accepted-applicant verification.
- Cohort/course operational references.
- Partner-group validation using Moodle groups.
- Topic Choice response aggregation.
- Hidden topic-balancing workflow.
- Latest room assignment result.
- Mentor room-assignment preview.
- Student room-information view.
- Manual room adjustment by admins.
- Zoom-compatible CSV export.
- Operational warnings.

Future responsibilities may include:

- Internal Excel reports.
- Admin dashboard.
- Presentation archive if Moodle-native tools are not sufficient.
- Attendance or participation views.
- Mentor availability.
- Web3-specific identity or credential workflows.

### 11.5 Moodle Activities And Resources

The first release should use Moodle-native activities and resources where possible:

- Moodle course sections for topic-based organization.
- Moodle resources for materials.
- Moodle Choice for topic selection.
- Moodle forums for course discussion.
- Moodle messaging for direct communication.
- Moodle assignment activity later if submissions become required.

Custom activity modules are not required for the first release unless later discovery shows the local plugin approach cannot support the needed workflow.

### 11.6 Data Ownership Principles

Data should live where the owning capability lives.

Examples:

- Moodle users live in Moodle core user tables.
- Course enrolments live in Moodle enrolment structures.
- Partner groups live as Moodle groups.
- Topic choices live in Moodle Choice activity data.
- Course resources live in Moodle course/resource systems.
- Accepted-applicant verification data lives in the Web3 Talents plugin.
- Generated room assignments live in the Web3 Talents plugin.
- Zoom export state lives in the Web3 Talents plugin.

The plugin should reference Moodle IDs rather than duplicating Moodle-owned data when possible.

### 11.7 External Integrations

The first release should minimize external integrations.

Required external dependency:

- Email delivery through Moodle's configured email system for account activation and notifications.

Operational external tool:

- Zoom remains the live-session platform, supported through CSV export.

Not required for first release:

- Discord integration.
- SSO provider.
- External public website.
- External real-time chat.
- External collaborative document editor.
- Wallet or blockchain integration.

### 11.8 Moodle Version Direction

The implementation should target a specific Moodle release line so development, plugin APIs, PHP version, database version, testing, and deployment requirements stay consistent.

For a new implementation, the target should be the latest stable Moodle release available when implementation begins, unless a required plugin, hosting constraint, or operational reason makes a supported older release preferable.

As of June 11, 2026, Moodle's latest official release is Moodle 5.2.1. The initial implementation target is Moodle 5.2.1.

Sources:

- Moodle latest release download page: https://download.moodle.org/releases/latest/
- Moodle other supported releases page: https://download.moodle.org/releases/supported/

### 11.9 Hosting Direction

The Moodle platform should be hosted on Oracle Cloud Infrastructure.

OCI should support:

- Development environment.
- Staging environment.
- Production environment.
- Moodle application hosting.
- Moodle database hosting.
- Moodle file storage strategy.
- Email delivery configuration.
- Backups and restore testing.
- Monitoring and logs.

Detailed OCI service selection should be handled in a later implementation plan.

### 11.10 Upgradeability And Maintenance

The platform should remain upgradeable with future Moodle versions.

Requirements:

- Do not modify Moodle core for product features.
- Keep custom behavior in plugins, themes, configuration, or external integrations.
- Use Moodle APIs where available.
- Keep custom plugin responsibilities documented.
- Avoid duplicating Moodle-owned data unnecessarily.
- Keep generated/exported data reproducible from Moodle/plugin records where practical.
- Prefer simple first-release workflows over deep customizations that make upgrades harder.

### 11.11 Acceptance Criteria

- Moodle core handles standard LMS functions.
- A Boost child theme handles Web3 Talents visual presentation.
- The `local_web3talents` plugin handles accepted-applicant verification and hidden operations workflows.
- Partner groups use Moodle groups.
- Topic selection uses Moodle Choice.
- Zoom export is generated from Moodle/plugin data.
- No Moodle core modification is required for first-release requirements.
- First-release external integrations are limited to email delivery and Zoom CSV usage.
- Moodle targets Moodle 5.2.1 for the initial implementation.
- Moodle is hosted on OCI across development, staging, and production.

### 11.12 Decisions Captured

- The local plugin should be named `local_web3talents`.
- Hosting should use Oracle Cloud Infrastructure.
- The Moodle version target means selecting the Moodle release line used for development, testing, deployment, and plugin compatibility.
- The initial version target is Moodle 5.2.1.

### 11.13 Open Questions

No open questions currently.

## 12. Security, Privacy, Data, And Operations

### 12.1 Purpose

The Moodle platform will store student identities, course participation, communication, topic choices, group membership, and operational exports. The first release should define baseline security and privacy expectations so the platform can be safely used by students, mentors, admins, and alumni.

This section defines product-level requirements. Detailed infrastructure security, backup schedules, and legal policies should be handled in implementation and operations planning.

### 12.2 Access Control

The platform should use Moodle roles and permissions wherever possible.

Access requirements:

- Public visitors can only view approved public overview content.
- Applicants do not receive Moodle accounts before acceptance.
- Accepted students can access only their enrolled cohort course and permitted public/alumni content.
- Students can message other students, mentors, and admins where Moodle permissions allow.
- Students can participate in course forums without pre-publication moderation.
- Students cannot access accepted-applicant records.
- Students cannot access hidden room-generation controls.
- Students cannot download Zoom CSV exports.
- Mentors use Moodle teacher role for the first release.
- Mentors can view room assignments but cannot edit them.
- Mentors cannot create operational topic Choice activities.
- Mentors cannot download Zoom CSV exports.
- Program admins can manage accepted applicants, accounts, groups, topic workflows, room assignments, and exports.
- Platform admins can manage Moodle system configuration, plugins, hosting, backups, and upgrades.

### 12.3 Student Data

The platform may store:

- Name.
- Email address.
- Moodle account status.
- Cohort/course enrolment.
- Role assignments.
- Partner group membership.
- Topic Choice responses.
- Generated room assignments.
- Course forum posts.
- Direct messages as handled by Moodle.
- Course activity participation.
- Alumni access state.
- Future submissions, presentations, or attendance records.

Student data should be visible only to users with a clear program need.

### 12.4 Accepted-Applicant Data

The accepted-applicant list should be restricted to admins.

Accepted-applicant data may include:

- First name.
- Last name.
- Email address.
- Cohort identifier.
- Acceptance status.
- Account creation status.
- Admin-only notes.
- Import or creation timestamp.

Accepted-applicant records should not be exposed to students, mentors by default, public visitors, or alumni.

### 12.5 Operational Export Data

Zoom CSV exports contain student email addresses and room assignments. They should be treated as sensitive operational files.

Export requirements:

- Only program admins can generate and download Zoom CSV exports.
- Exports should include only fields required by Zoom for the first release.
- Export files should not expose hidden balancing logic.
- Export access should not be available to students or mentors.
- Export files should be stored for two weeks after generation and then discarded.
- Export history, while retained, should be permission-restricted.

The first release keeps only the latest generated room assignment result for a topic-choice activity. It does not need to store multiple generated histories.

### 12.6 Alumni Access

Alumni remain enrolled in the same cohort course after the cohort ends.

Alumni should retain access to:

- Approved course materials.
- Past topic resources.
- Old course forums for their cohort.
- Approved presentations if added later.

Alumni should not automatically gain access to:

- Future cohort courses.
- Future cohort communication areas.
- Admin exports.
- Accepted-applicant data.
- Hidden operations workflows.
- Private mentor/admin notes.

### 12.7 Email And Notifications

Moodle email should be used for student activation instructions.

Email and notification requirements:

- Account activation instructions are sent through Moodle email.
- Moodle email notifications should respect user preferences where Moodle supports that behavior.
- The platform does not require custom push notifications for the first release.
- Email configuration must be reliable enough for account activation before launch.

### 12.8 Backups And Recovery

The platform should support operational recovery appropriate for an active education program.

Backup expectations:

- Moodle database should be backed up.
- Moodle file storage should be backed up.
- Custom plugin data should be backed up with the Moodle database.
- Restore procedures should be tested before production launch.
- OCI hosting should include a backup and recovery plan.
- Daily backups should be retained for 7 days.
- Weekly backups should be retained for 4 weeks.
- Monthly backups should be retained for 6 months.

Detailed backup implementation should be defined in the implementation plan.

### 12.9 Audit And Traceability

The first release should provide enough traceability for operational confidence without overbuilding audit tooling.

Useful traceability includes:

- Accepted-applicant import or creation timestamps.
- Account creation status.
- Topic Choice response visibility through Moodle.
- Latest generated room assignment state.
- Admin-visible warnings before export.
- Export generation action if easy to record.

Full audit logs and advanced reporting are not required for the first release beyond what Moodle already provides and what is needed for the custom plugin workflows.

### 12.10 Compliance And Policy Notes

The product should be designed with privacy and data minimization in mind.

Policy questions to resolve outside this PRD:

- Data retention period for accepted applicants who do not activate accounts.
- Data retention period for alumni accounts.
- Whether students must consent to platform communication rules.
- Students should agree to a communication or code-of-conduct policy on first login.
- Whether forum/message content needs moderation or reporting policies.
- Whether GDPR-specific documentation or data processing agreements are required.

Accepted-applicant records should be kept for one month if the accepted applicant never activates an account.

### 12.11 Acceptance Criteria

- Public users cannot access private course or student data.
- Only accepted applicants can receive student accounts.
- Accepted-applicant list is admin-only.
- Students can access only permitted cohort/course areas.
- Mentors can view but not edit room assignments.
- Only admins can generate and download Zoom CSV exports.
- Zoom export files are retained for two weeks and then discarded.
- Moodle email can send account activation instructions.
- Backups cover Moodle database, files, and custom plugin data.
- Backup retention keeps daily backups for 7 days, weekly backups for 4 weeks, and monthly backups for 6 months.
- Alumni access is limited to approved same-cohort materials and forums.
- Students must agree to a communication or code-of-conduct policy on first login.
- Accepted-applicant records for people who never activate an account are retained for one month.
- No first-release requirement depends on modifying Moodle core.

### 12.12 Decisions Captured

- Zoom export files should be stored for two weeks after generation and then discarded.
- Accepted-applicant records should be kept for one month if the accepted applicant never activates an account.
- Students should agree to a communication or code-of-conduct policy on first login.
- Production backup retention should keep daily backups for 7 days, weekly backups for 4 weeks, and monthly backups for 6 months.

### 12.13 Open Questions

No open questions currently.

## 13. First Release Definition Of Done

### 13.1 Purpose

This section defines when the Moodle migration PRD's first-release product requirements are satisfied. It is not an implementation checklist, but it provides the product acceptance boundary for the initial Moodle-based platform.

### 13.2 First Release Must Include

The first Moodle release must include:

- Moodle 5.2.1 as the target platform.
- Moodle hosted on Oracle Cloud Infrastructure.
- Public overview page inside Moodle.
- High-fidelity implementation of the public overview page based on Figma.
- Custom Boost child theme for Web3 Talents branding.
- Fundamentals cohort represented as one Moodle course.
- Topic-based course sections.
- Accepted-applicant list.
- CSV and Excel accepted-applicant import.
- Individual student account creation with accepted-applicant verification.
- Moodle email activation instructions.
- First-login communication or code-of-conduct agreement.
- Student access to the fundamentals cohort course.
- Mentor access using Moodle teacher role.
- Alumni remaining enrolled in the same course after cohort completion.
- Alumni access to approved materials and old course forums.
- Course materials migrated from existing Google Drive content where useful.
- Moodle announcements.
- Moodle course forum.
- Moodle direct messaging between students, mentors, and admins where permissions allow.
- Moodle Choice for topic selection.
- Moodle groups for partner groups.
- `local_web3talents` plugin.
- Hidden room-generation workflow based on Moodle groups and Choice responses.
- Latest room-generation result only.
- Admin manual room assignment adjustment.
- Mentor room-assignment view.
- Student room-information view.
- Admin-only Zoom-compatible CSV export.
- Zoom export retention for two weeks.
- Accepted-applicant inactive retention for one month.
- Production backup retention with daily, weekly, and monthly retention windows.

### 13.3 First Release May Omit

The first Moodle release may omit:

- Full applicant review pipeline.
- Discord integration.
- SSO.
- Custom real-time chat.
- Student assignment/submission uploads.
- Presentation archive.
- Recordings.
- Advanced admin dashboard.
- Internal Excel review export.
- Attendance and participation tracking.
- Mentor availability.
- Web3 wallet or credential features.
- Collaborative document editor.
- High-fidelity student, mentor, and admin Figma implementation.
- Multiple saved room-generation histories.

### 13.4 First Release Acceptance Criteria

The first release is acceptable when:

- A public visitor can view the Web3 Talents overview page before login.
- An admin can import or create accepted-applicant records.
- An admin can create a student account only for an accepted applicant.
- The student receives Moodle email activation instructions.
- The student agrees to required communication or code-of-conduct terms on first login.
- The student can access the fundamentals course.
- The student can access course materials organized by topic.
- The student can use announcements, course forum, and direct messaging according to Moodle permissions.
- The student can make a topic selection through Moodle Choice.
- An admin can manage partner groups as Moodle groups.
- An admin can generate room assignments from Moodle groups and Choice responses.
- An admin can manually adjust the latest room assignment.
- A mentor can view room assignments but cannot edit them.
- A student can view relevant room information.
- An admin can export a Zoom-compatible CSV with the required columns.
- Zoom exports are retained for two weeks and then discarded.
- Alumni can access approved materials and participate in old course forums for their cohort.
- The platform runs on OCI with backup coverage for database, files, and custom plugin data.
- The implementation does not require Moodle core modifications.

### 13.5 Follow-Up Documents Needed

After this PRD is accepted, the following documents should be created separately:

- Moodle implementation plan.
- OCI hosting and deployment plan.
- Moodle plugin technical design for `local_web3talents`.
- Boost child theme implementation plan.
- Data migration plan for Google Drive materials and accepted-applicant lists.
- Test plan and acceptance test checklist.
- Backup and restore operations plan.
- Privacy, code-of-conduct, and communication policy drafts.
- Future roadmap for P1/P2 features.

### 13.6 Remaining Product Open Questions

No blocking product questions remain for the first-release PRD.

"use client";

import { useMemo, useState, type ChangeEvent } from "react";
import {
  groupParticipantsByPartnerGroup,
  validateRoster,
  type AssignmentGenerationResult,
  type Mentor,
  type Participant,
  type RoomAssignment,
  type RoomName,
  type ValidationIssue,
  type Vote,
  type WeeklyTopic
} from "@web3-talents/core";

type ImportPreview = {
  mentors: Mentor[];
  participants: Participant[];
  validation: {
    valid: boolean;
    errors: ValidationIssue[];
    warnings: ValidationIssue[];
  };
  partnerGroups: Array<{
    partnerGroup: string;
    participantCount: number;
    participants: string[];
  }>;
  rowCount: number;
};

type DiscordPollPreview = {
  message: {
    channelId: string;
    guildId: string;
    messageId: string;
  };
  topics: WeeklyTopic[];
  votes: Vote[];
  warnings: ValidationIssue[];
};

type ParticipantMove = {
  fromPartnerGroup: string;
  participantIndex: number;
  participantName: string;
  toPartnerGroup: string;
};

type ImportPreviewRequest = {
  contentBase64: string;
  filename: string;
};

type AdminWorkflowProps = {
  apiBaseUrl: string;
};

type RequestState = "idle" | "loading" | "success" | "error";

const defaultTopics: WeeklyTopic[] = [
  { id: "topic-1", label: "" },
  { id: "topic-2", label: "" },
  { id: "topic-3", label: "" },
  { id: "topic-4", label: "" }
];

export function AdminWorkflow({ apiBaseUrl }: AdminWorkflowProps) {
  const [file, setFile] = useState<File | null>(null);
  const [importPreview, setImportPreview] = useState<ImportPreview | null>(null);
  const [pollLink, setPollLink] = useState("");
  const [pollPreview, setPollPreview] = useState<DiscordPollPreview | null>(null);
  const [topics, setTopics] = useState<WeeklyTopic[]>(defaultTopics);
  const [breakoutRoomCount, setBreakoutRoomCount] = useState(4);
  const [participantMoveHistory, setParticipantMoveHistory] = useState<
    ParticipantMove[]
  >([]);
  const [assignmentResult, setAssignmentResult] =
    useState<AssignmentGenerationResult | null>(null);
  const [requestState, setRequestState] = useState<RequestState>("idle");
  const [statusMessage, setStatusMessage] = useState("");

  const canPreviewPoll = Boolean(importPreview?.validation.valid && pollLink.trim());
  const totalVotes = pollPreview?.votes.length ?? 0;
  const totalParticipants = importPreview?.participants.length ?? 0;
  const totalPartnerGroups = importPreview?.partnerGroups.length ?? 0;
  const minimumRoomCount = Math.max(1, Math.ceil(totalPartnerGroups / 4));
  const totalRooms = assignmentResult?.rooms.length ?? 0;
  const canGenerate =
    Boolean(importPreview?.validation.valid) &&
    breakoutRoomCount >= minimumRoomCount &&
    topics.length === 4 &&
    topics.every((topic) => topic.label.trim());
  const totalWarnings = [
    ...(importPreview?.validation.warnings ?? []),
    ...(pollPreview?.warnings ?? []),
    ...(assignmentResult?.warnings ?? [])
  ].length;
  const roomOptions = useMemo(
    () => assignmentResult?.rooms.map((room) => room.roomName) ?? [],
    [assignmentResult]
  );

  async function previewImport() {
    if (!file) {
      setStatus("error", "Choose a participant Excel or CSV file first.");
      return;
    }

    try {
      setStatus("loading", "Reading participant file...");
      const contentBase64 = await fileToBase64Content(file);

      setStatus("loading", "Uploading participant file to API...");
      const preview = await postJson<ImportPreview>("/api/import/preview", {
        contentBase64,
        filename: file.name
      } satisfies ImportPreviewRequest);
      setImportPreview(preview);
      setParticipantMoveHistory([]);
      setAssignmentResult(null);

      if (preview.validation.valid) {
        setStatus("success", `Imported ${preview.rowCount} participant rows.`);
      } else {
        setStatus("error", "Import preview found errors that must be fixed.");
      }
    } catch (error) {
      setStatus("error", getErrorMessage(error));
    }
  }

  async function previewPoll() {
    if (!canPreviewPoll || !importPreview) {
      setStatus("error", "Import a valid roster and paste a Discord poll link.");
      return;
    }

    setStatus("loading", "Fetching Discord poll...");

    try {
      const preview = await postJson<DiscordPollPreview>("/api/discord/poll/preview", {
        participants: importPreview.participants,
        pollMessageLink: pollLink.trim()
      });
      setPollPreview(preview);
      setTopics(preview.topics);
      setAssignmentResult(null);
      setStatus(
        "success",
        `Fetched ${preview.topics.length} topics and ${preview.votes.length} votes.`
      );
    } catch (error) {
      setStatus("error", getErrorMessage(error));
    }
  }

  async function generateRooms() {
    if (!canGenerate || !importPreview) {
      setStatus("error", "Import a valid roster and provide four topic labels.");
      return;
    }

    setStatus("loading", "Generating room assignments...");

    try {
      const result = await postJson<AssignmentGenerationResult>(
        "/api/assignments/generate",
        {
          breakoutRoomCount,
          mentors: importPreview.mentors,
          participants: importPreview.participants,
          topics,
          votes: pollPreview?.votes ?? []
        }
      );
      setAssignmentResult(result);
      setStatus("success", `Generated ${result.rooms.length} room assignments.`);
    } catch (error) {
      setStatus("error", getErrorMessage(error));
    }
  }

  async function downloadExport(path: string, filename: string) {
    if (!assignmentResult) {
      setStatus("error", "Generate room assignments before exporting.");
      return;
    }

    setStatus("loading", `Preparing ${filename}...`);

    try {
      const response = await fetch(`${apiBaseUrl}${path}`, {
        body: JSON.stringify(assignmentResult),
        headers: {
          "content-type": "application/json"
        },
        method: "POST"
      });

      if (!response.ok) {
        throw new Error(await readErrorResponse(response));
      }

      const blob = await response.blob();

      if (blob.size === 0) {
        throw new Error(`${filename} was empty and could not be downloaded.`);
      }

      const url = URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.href = url;
      link.download = filename;
      link.rel = "noopener";
      link.style.display = "none";
      document.body.append(link);
      link.click();
      link.remove();
      window.setTimeout(() => URL.revokeObjectURL(url), 30_000);
      setStatus("success", `${filename} downloaded.`);
    } catch (error) {
      setStatus("error", getErrorMessage(error));
    }
  }

  function updateTopicLabel(topicId: string, label: string) {
    setTopics((currentTopics) =>
      currentTopics.map((topic) =>
        topic.id === topicId
          ? {
              ...topic,
              label
            }
          : topic
      )
    );
    setAssignmentResult(null);
  }

  function updateBreakoutRoomCount(value: string) {
    const parsedValue = Number(value);
    setBreakoutRoomCount(Number.isInteger(parsedValue) ? parsedValue : 1);
    setAssignmentResult(null);
  }

  function updateParticipantPartnerGroup(participantIndex: number, partnerGroup: string) {
    if (!importPreview) {
      return;
    }

    const participant = importPreview.participants[participantIndex];

    if (!participant || participant.partnerGroup === partnerGroup) {
      return;
    }

    setParticipantMoveHistory((currentHistory) => [
      ...currentHistory,
      {
        fromPartnerGroup: participant.partnerGroup,
        participantIndex,
        participantName: formatParticipantName(participant),
        toPartnerGroup: partnerGroup
      }
    ]);
    setImportPreview(
      buildImportPreviewFromParticipants(
        importPreview.participants.map((currentParticipant, index) =>
          index === participantIndex
            ? {
                ...currentParticipant,
                partnerGroup
              }
            : currentParticipant
        ),
        importPreview.rowCount,
        importPreview.mentors
      )
    );
    setPollPreview(null);
    setAssignmentResult(null);
    setStatus("success", "Participant partner group updated.");
  }

  function undoParticipantPartnerGroupMove() {
    if (!importPreview || participantMoveHistory.length === 0) {
      return;
    }

    const lastMove = participantMoveHistory[participantMoveHistory.length - 1];

    if (!lastMove) {
      return;
    }

    const participant = importPreview.participants[lastMove.participantIndex];

    setParticipantMoveHistory((currentHistory) => currentHistory.slice(0, -1));

    if (!participant) {
      return;
    }

    setImportPreview(
      buildImportPreviewFromParticipants(
        importPreview.participants.map((currentParticipant, index) =>
          index === lastMove.participantIndex
            ? {
                ...currentParticipant,
                partnerGroup: lastMove.fromPartnerGroup
              }
            : currentParticipant
        ),
        importPreview.rowCount,
        importPreview.mentors
      )
    );
    setPollPreview(null);
    setAssignmentResult(null);
    setStatus(
      "success",
      `Undid move for ${lastMove.participantName}: Group ${lastMove.toPartnerGroup} to Group ${lastMove.fromPartnerGroup}.`
    );
  }

  function movePartnerGroup(partnerGroup: string, targetRoomName: RoomName) {
    setAssignmentResult((currentResult) => {
      if (!currentResult) {
        return currentResult;
      }

      const assignmentToMove = currentResult.rooms
        .flatMap((room) => room.partnerGroups)
        .find((assignment) => assignment.partnerGroup === partnerGroup);

      if (!assignmentToMove) {
        return currentResult;
      }

      const rooms = currentResult.rooms.map((room) => ({
        ...room,
        partnerGroups: room.partnerGroups.filter(
          (assignment) => assignment.partnerGroup !== partnerGroup
        )
      }));

      return {
        ...currentResult,
        rooms: rooms.map((room) =>
          room.roomName === targetRoomName
            ? {
                ...room,
                partnerGroups: [...room.partnerGroups, assignmentToMove].sort(
                  (left, right) =>
                    comparePartnerGroups(left.partnerGroup, right.partnerGroup)
                )
              }
            : room
        )
      };
    });
  }

  function assignPartnerGroupTopic(partnerGroup: string, topicId: string) {
    setAssignmentResult((currentResult) => {
      if (!currentResult) {
        return currentResult;
      }

      return {
        ...currentResult,
        partnerGroupAssignments: currentResult.partnerGroupAssignments.map(
          (assignment) =>
            assignment.partnerGroup === partnerGroup
              ? {
                  ...assignment,
                  assignedTopicId: topicId
                }
              : assignment
        ),
        rooms: currentResult.rooms.map((room) => ({
          ...room,
          partnerGroups: room.partnerGroups.map((assignment) =>
            assignment.partnerGroup === partnerGroup
              ? {
                  ...assignment,
                  assignedTopicId: topicId
                }
              : assignment
          )
        }))
      };
    });
    setStatus("success", "Partner group topic updated.");
  }

  function assignMentorRoom(mentorIndex: number, roomName: RoomName) {
    setAssignmentResult((currentResult) => {
      if (!currentResult) {
        return currentResult;
      }

      const mentors = [...(currentResult.mentors ?? [])];
      const mentor = mentors[mentorIndex];

      if (!mentor) {
        return currentResult;
      }

      mentors[mentorIndex] = {
        ...mentor,
        roomName
      };

      return {
        ...currentResult,
        mentors
      };
    });
    setStatus("success", "Mentor room updated.");
  }

  function onFileChange(event: ChangeEvent<HTMLInputElement>) {
    setFile(event.target.files?.[0] ?? null);
    setImportPreview(null);
    setParticipantMoveHistory([]);
    setPollPreview(null);
    setAssignmentResult(null);
  }

  function setStatus(state: RequestState, message: string) {
    setRequestState(state);
    setStatusMessage(message);
  }

  async function postJson<T>(path: string, body: unknown): Promise<T> {
    const response = await fetchWithTimeout(`${apiBaseUrl}${path}`, {
      body: JSON.stringify(body),
      headers: {
        "content-type": "application/json"
      },
      method: "POST"
    });

    if (!response.ok) {
      throw new Error(await readErrorResponse(response));
    }

    return response.json() as Promise<T>;
  }

  return (
    <main className="admin-page">
      <div className="admin-shell">
        <header className="admin-hero">
          <div className="admin-hero-accent" />
          <div className="admin-hero-copy">
            <p className="admin-eyebrow">
              Internal Admin
            </p>
            <h1>
              Web3 Talents weekly room builder
            </h1>
            <p className="admin-hero-description">
              Upload the roster, pull Discord poll votes, generate breakout
              rooms, adjust groups, and export the files for weekly operations.
            </p>
          </div>
          <div className="admin-metrics">
            <Metric label="Participants" value={totalParticipants} />
            <Metric label="Votes" value={totalVotes} />
            <Metric label="Rooms" value={totalRooms} />
            <Metric label="Warnings" value={totalWarnings} />
          </div>
        </header>

        <StatusBanner state={requestState} message={statusMessage} />

        <section className="admin-workflow-grid">
          <Panel title="1. Import Roster">
            <div className="admin-stack">
              <input
                accept=".csv,.xlsx,.xlsm"
                id="roster-file"
                className="admin-file-input"
                onChange={onFileChange}
                type="file"
              />
              <label className="admin-file-button" htmlFor="roster-file">
                Choose roster file
              </label>
              <div className="admin-file-state">
                <div className="admin-file-name">
                  {file ? file.name : "No roster file selected"}
                </div>
                <div className="admin-muted">
                  CSV, XLSX, or XLSM using the participant template.
                </div>
              </div>
              <button
                className="admin-button admin-button-cyan"
                disabled={!file || requestState === "loading"}
                onClick={previewImport}
                type="button"
              >
                Preview import
              </button>
              {importPreview ? (
                <ImportSummary
                  canUndoMove={participantMoveHistory.length > 0}
                  onMoveParticipant={updateParticipantPartnerGroup}
                  onUndoMove={undoParticipantPartnerGroupMove}
                  preview={importPreview}
                />
              ) : (
                <RosterTemplateHelp />
              )}
            </div>
          </Panel>

          <Panel title="2. Fetch Discord Poll">
            <div className="admin-stack">
              <label className="admin-field-label" htmlFor="discord-poll-link">
                Discord poll message link
              </label>
              <input
                className="admin-input"
                id="discord-poll-link"
                onChange={(event) => {
                  setPollLink(event.target.value);
                  setPollPreview(null);
                  setAssignmentResult(null);
                }}
                placeholder="https://discord.com/channels/..."
                type="url"
                value={pollLink}
              />
              <button
                className="admin-button admin-button-indigo"
                disabled={!canPreviewPoll || requestState === "loading"}
                onClick={previewPoll}
                type="button"
              >
                Fetch poll
              </button>
              {pollPreview ? (
                <PollSummary preview={pollPreview} />
              ) : (
                <p className="admin-help admin-help-indigo">
                  Paste the Discord poll link above, then fetch the poll.
                </p>
              )}
            </div>
          </Panel>

          <Panel title="3. Topics">
            <div className="admin-stack">
              <label className="admin-topic-field">
                <span className="admin-field-label">
                  Breakout rooms
                </span>
                <input
                  className="admin-input admin-input-center"
                  min={minimumRoomCount}
                  onChange={(event) =>
                    updateBreakoutRoomCount(event.target.value)
                  }
                  type="number"
                  value={breakoutRoomCount}
                />
              </label>
              <p className="admin-help admin-help-emerald">
                Minimum for this roster: {minimumRoomCount}. Each room can hold
                one partner group per topic.
              </p>
              {topics.map((topic, index) => (
                <label className="admin-topic-field" key={topic.id}>
                  <span className="admin-field-label">
                    Topic {index + 1}
                  </span>
                  <input
                    className="admin-input admin-input-center"
                    placeholder={`Topic ${index + 1} name`}
                    onChange={(event) =>
                      updateTopicLabel(topic.id, event.target.value)
                    }
                    value={topic.label}
                  />
                </label>
              ))}
              <button
                className="admin-button admin-button-emerald"
                disabled={!canGenerate || requestState === "loading"}
                onClick={generateRooms}
                type="button"
              >
                Generate rooms
              </button>
            </div>
          </Panel>

          <Panel title="4. Review And Adjust Rooms">
            {assignmentResult ? (
              <RoomGrid
                mentors={assignmentResult.mentors ?? []}
                onAssignMentorRoom={assignMentorRoom}
                onAssignTopic={assignPartnerGroupTopic}
                onMove={movePartnerGroup}
                rooms={assignmentResult.rooms}
                roomOptions={roomOptions}
                topics={assignmentResult.topics}
              />
            ) : (
              <div className="admin-empty-review">
                <div className="admin-empty-copy">
                  <div className="admin-empty-icon">
                    4
                  </div>
                  Generate rooms to review partner-group assignments.
                </div>
              </div>
            )}
          </Panel>

          <Panel title="5. Export">
            <div className="admin-stack">
              <button
                className="admin-button admin-button-secondary"
                disabled={!assignmentResult || requestState === "loading"}
                onClick={() =>
                  downloadExport(
                    "/api/exports/internal-excel",
                    "internal-room-assignments.xlsx"
                  )
                }
                type="button"
              >
                Internal Excel
              </button>
              <button
                className="admin-button admin-button-secondary"
                disabled={!assignmentResult || requestState === "loading"}
                onClick={() =>
                  downloadExport(
                    "/api/exports/zoom-csv",
                    "zoom-breakout-rooms.csv"
                  )
                }
                type="button"
              >
                Zoom CSV
              </button>
              <p className="admin-help">
                Exports unlock after room assignments are generated.
              </p>
            </div>
          </Panel>
        </section>
      </div>
    </main>
  );
}

function Panel({
  children,
  title
}: Readonly<{
  children: React.ReactNode;
  title: string;
}>) {
  return (
    <section className="admin-panel">
      <h2 className="admin-panel-title">
        {title}
      </h2>
      {children}
    </section>
  );
}

function RosterTemplateHelp() {
  return (
    <div className="admin-template">
      <div className="admin-template-title">
        Required CSV columns
      </div>
      <div className="admin-template-table-wrap">
        <table>
          <thead>
            <tr>
              <th>Name</th>
              <th>Email</th>
              <th>
                Discord username
              </th>
              <th>Partner group</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>Alice Smith</td>
              <td>alice@example.com</td>
              <td>alice</td>
              <td>1</td>
            </tr>
          </tbody>
        </table>
      </div>
      <p className="admin-template-note">
        Each participant needs one row. Partner groups should use simple labels
        like 1, 2, and 3, with two or three participants sharing the same label.
      </p>
    </div>
  );
}

function Metric({
  label,
  value
}: Readonly<{
  label: string;
  value: number;
}>) {
  return (
    <div className="admin-metric">
      <div className="admin-metric-label">
        {label}
      </div>
      <div className="admin-metric-value">
        {value}
      </div>
    </div>
  );
}

function StatusBanner({
  message,
  state
}: Readonly<{
  message: string;
  state: RequestState;
}>) {
  if (!message) {
    return null;
  }

  const classes = {
    error: "admin-status-error",
    idle: "admin-status-idle",
    loading: "admin-status-loading",
    success: "admin-status-success"
  };

  return (
    <div className={`admin-status ${classes[state]}`}>
      {message}
    </div>
  );
}

function ImportSummary({
  canUndoMove,
  onMoveParticipant,
  onUndoMove,
  preview
}: Readonly<{
  canUndoMove: boolean;
  onMoveParticipant: (participantIndex: number, partnerGroup: string) => void;
  onUndoMove: () => void;
  preview: ImportPreview;
}>) {
  const [activeParticipantIndex, setActiveParticipantIndex] = useState<
    number | null
  >(null);
  const partnerGroupOptions = preview.partnerGroups
    .map((group) => group.partnerGroup)
    .sort(comparePartnerGroups);

  return (
    <div className="admin-import-summary">
      <div className="admin-summary-grid">
        <SummaryCell label="Rows" value={preview.rowCount} />
        <SummaryCell label="Partner groups" value={preview.partnerGroups.length} />
        <SummaryCell label="Mentors" value={preview.mentors.length} />
      </div>
      <IssueList title="Errors" issues={preview.validation.errors} tone="error" />
      <IssueList
        title="Warnings"
        issues={preview.validation.warnings}
        tone="warning"
      />
      <div className="admin-preview-actions">
        <p className="admin-help admin-help-cyan">
          Click a participant name to move that person to a different partner group.
        </p>
        <button
          className="admin-undo-button"
          disabled={!canUndoMove}
          onClick={onUndoMove}
          type="button"
        >
          Undo
        </button>
      </div>
      <div className="admin-partner-group-list">
        {[...preview.partnerGroups].sort((left, right) =>
          comparePartnerGroups(left.partnerGroup, right.partnerGroup)
        ).map((group) => (
          <div
            className="admin-partner-group-row"
            key={group.partnerGroup}
          >
            <div className="admin-partner-group-name">Group {group.partnerGroup}</div>
            <div className="admin-partner-group-count">
              {group.participantCount} participants
            </div>
            <div className="admin-participant-list admin-participant-list-compact">
              {preview.participants.map((participant, index) =>
                participant.partnerGroup === group.partnerGroup ? (
                  activeParticipantIndex === index ? (
                    <select
                      autoFocus
                      className="admin-inline-group-select"
                      key={`${participant.email}-${index}`}
                      onBlur={() => setActiveParticipantIndex(null)}
                      onChange={(event) => {
                        onMoveParticipant(index, event.target.value);
                        setActiveParticipantIndex(null);
                      }}
                      value={participant.partnerGroup}
                    >
                      {partnerGroupOptions.map((partnerGroup) => (
                        <option key={partnerGroup} value={partnerGroup}>
                          Group {partnerGroup}
                        </option>
                      ))}
                    </select>
                  ) : (
                    <button
                      className="admin-participant-pill admin-participant-button"
                      key={`${participant.email}-${index}`}
                      onClick={() => setActiveParticipantIndex(index)}
                      type="button"
                    >
                      {formatParticipantName(participant)}
                    </button>
                  )
                ) : null
              )}
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

function PollSummary({ preview }: Readonly<{ preview: DiscordPollPreview }>) {
  return (
    <div className="space-y-3 text-sm">
      <div className="admin-summary-grid">
        <SummaryCell label="Topics" value={preview.topics.length} />
        <SummaryCell label="Votes" value={preview.votes.length} />
      </div>
      <IssueList title="Warnings" issues={preview.warnings} tone="warning" />
    </div>
  );
}

function SummaryCell({
  label,
  value
}: Readonly<{
  label: string;
  value: number;
}>) {
  return (
    <div className="rounded-md bg-slate-50 px-3 py-2 text-center">
      <div className="text-xs font-medium uppercase tracking-wide text-slate-500">
        {label}
      </div>
      <div className="font-semibold text-slate-950">{value}</div>
    </div>
  );
}

function IssueList({
  issues,
  title,
  tone
}: Readonly<{
  issues: ValidationIssue[];
  title: string;
  tone: "error" | "warning";
}>) {
  const [expanded, setExpanded] = useState(false);

  if (issues.length === 0) {
    return null;
  }

  const toneClass =
    tone === "error"
      ? "border-red-200 bg-red-50 text-red-800"
      : "border-amber-200 bg-amber-50 text-amber-900";

  const visibleIssues = expanded ? issues : issues.slice(0, 6);

  return (
    <div className={`rounded-md border px-3 py-2 text-sm ${toneClass}`}>
      <div className="mb-1 font-semibold">{title}</div>
      <ul className="space-y-1">
        {visibleIssues.map((issue, index) => (
          <li key={`${issue.code}-${issue.email ?? issue.partnerGroup ?? index}`}>
            {issue.email ? `${issue.email}: ` : ""}
            {issue.partnerGroup ? `${issue.partnerGroup}: ` : ""}
            {issue.message}
          </li>
        ))}
      </ul>
      {issues.length > 6 ? (
        <button
          className="admin-link-button"
          onClick={() => setExpanded((currentValue) => !currentValue)}
          type="button"
        >
          {expanded ? "Show fewer" : `Show ${issues.length - 6} more`}
        </button>
      ) : null}
    </div>
  );
}

function RoomGrid({
  mentors,
  onAssignMentorRoom,
  onAssignTopic,
  onMove,
  roomOptions,
  rooms,
  topics
}: Readonly<{
  mentors: Mentor[];
  onAssignMentorRoom: (mentorIndex: number, targetRoomName: RoomName) => void;
  onAssignTopic: (partnerGroup: string, topicId: string) => void;
  onMove: (partnerGroup: string, targetRoomName: RoomName) => void;
  roomOptions: RoomName[];
  rooms: RoomAssignment[];
  topics: WeeklyTopic[];
}>) {
  const [activeMentorIndex, setActiveMentorIndex] = useState<number | null>(null);
  const topicById = new Map(topics.map((topic) => [topic.id, topic.label]));
  const topicOrder = new Map(topics.map((topic, index) => [topic.id, index]));
  const unassignedMentors = mentors
    .map((mentor, index) => ({ index, mentor }))
    .filter(({ mentor }) => !mentor.roomName);

  return (
    <div className="admin-room-grid">
      {mentors.length > 0 ? (
        <div className="admin-room-card admin-mentor-card">
          <div className="admin-room-header">
            <h3>Mentors</h3>
            <span>{unassignedMentors.length} unassigned</span>
          </div>
          <p className="admin-help admin-help-cyan">
            Click a mentor to assign that mentor to a breakout room.
          </p>
          <div className="admin-participant-list">
            {unassignedMentors.length > 0 ? unassignedMentors.map(({ index, mentor }) =>
              activeMentorIndex === index ? (
                <select
                  autoFocus
                  className="admin-inline-group-select"
                  key={`${mentor.name}-${index}`}
                  onBlur={() => setActiveMentorIndex(null)}
                  onChange={(event) => {
                    onAssignMentorRoom(index, event.target.value as RoomName);
                    setActiveMentorIndex(null);
                  }}
                  value={mentor.roomName ?? ""}
                >
                  <option value="" disabled>
                    Choose room
                  </option>
                  {roomOptions.map((roomName) => (
                    <option key={roomName} value={roomName}>
                      {roomName}
                    </option>
                  ))}
                </select>
              ) : (
                <button
                  className="admin-participant-pill admin-participant-button"
                  key={`${mentor.name}-${index}`}
                  onClick={() => setActiveMentorIndex(index)}
                  type="button"
                >
                  {mentor.name}
                </button>
              )
            ) : (
              <span className="admin-muted">All mentors are assigned.</span>
            )}
          </div>
        </div>
      ) : null}
      {rooms.map((room) => {
        const roomMentors = mentors
          .map((mentor, index) => ({ index, mentor }))
          .filter(({ mentor }) => mentor.roomName === room.roomName);

        return (
        <div
          className="admin-room-card"
          key={room.roomName}
        >
          <div className="admin-room-header">
            <div className="admin-room-title-block">
              <h3>
                {room.roomName}
              </h3>
              {roomMentors.length > 0 ? (
                <div className="admin-room-mentor-list">
                  {roomMentors.map(({ index, mentor }) =>
                    activeMentorIndex === index ? (
                      <select
                        autoFocus
                        className="admin-inline-group-select"
                        key={`${mentor.name}-${index}`}
                        onBlur={() => setActiveMentorIndex(null)}
                        onChange={(event) => {
                          onAssignMentorRoom(index, event.target.value as RoomName);
                          setActiveMentorIndex(null);
                        }}
                        value={mentor.roomName ?? ""}
                      >
                        {roomOptions.map((roomName) => (
                          <option key={roomName} value={roomName}>
                            {roomName}
                          </option>
                        ))}
                      </select>
                    ) : (
                      <button
                        className="admin-room-mentor-button"
                        key={`${mentor.name}-${index}`}
                        onClick={() => setActiveMentorIndex(index)}
                        type="button"
                      >
                        {mentor.name}
                      </button>
                    )
                  )}
                </div>
              ) : null}
            </div>
            <span>
              {room.partnerGroups.reduce(
                (total, group) => total + group.participants.length,
                0
              )}{" "}
              participants
            </span>
          </div>
          <div className="admin-room-groups">
            {[...room.partnerGroups]
              .sort((left, right) => {
                const topicComparison =
                  (topicOrder.get(left.assignedTopicId) ?? Number.MAX_SAFE_INTEGER) -
                  (topicOrder.get(right.assignedTopicId) ?? Number.MAX_SAFE_INTEGER);

                if (topicComparison !== 0) {
                  return topicComparison;
                }

                return comparePartnerGroups(left.partnerGroup, right.partnerGroup);
              })
              .map((assignment) => (
              <div
                className="admin-room-group"
                key={assignment.partnerGroup}
              >
                <div className="admin-room-group-layout">
                  <div className="admin-room-group-main">
                    <div className="admin-room-group-title">
                      Group {assignment.partnerGroup}
                    </div>
                    <label className="admin-topic-override-field">
                      Topic
                      <select
                        className="admin-topic-override-select"
                        onChange={(event) =>
                          onAssignTopic(
                            assignment.partnerGroup,
                            event.target.value
                          )
                        }
                        value={assignment.assignedTopicId}
                      >
                        {topics.map((topic) => (
                          <option key={topic.id} value={topic.id}>
                            {topic.label || topic.id}
                          </option>
                        ))}
                        {topicById.has(assignment.assignedTopicId) ? null : (
                          <option value={assignment.assignedTopicId}>
                            {assignment.assignedTopicId}
                          </option>
                        )}
                      </select>
                    </label>
                    <div className="admin-participant-list">
                      {assignment.participants.map((participant) => (
                        <span className="admin-participant-pill" key={participant.email}>
                          {formatParticipantName(participant)}
                        </span>
                      ))}
                    </div>
                  </div>
                  <label className="admin-move-field">
                    Move to
                    <select
                      className="admin-move-select"
                      onChange={(event) =>
                        onMove(
                          assignment.partnerGroup,
                          event.target.value as RoomName
                        )
                      }
                      value={room.roomName}
                    >
                      {roomOptions.map((roomName) => (
                        <option key={roomName} value={roomName}>
                          {roomName}
                        </option>
                      ))}
                    </select>
                  </label>
                </div>
              </div>
            ))}
          </div>
        </div>
      );
      })}
    </div>
  );
}

function buildImportPreviewFromParticipants(
  participants: Participant[],
  rowCount: number,
  mentors: Mentor[] = []
): ImportPreview {
  const validation = validateRoster(participants);
  const partnerGroups = groupParticipantsByPartnerGroup(participants).map(
    (group) => ({
      participantCount: group.participants.length,
      participants: group.participants.map(formatParticipantName),
      partnerGroup: group.partnerGroup
    })
  ).sort((left, right) =>
    comparePartnerGroups(left.partnerGroup, right.partnerGroup)
  );

  return {
    mentors,
    participants,
    partnerGroups,
    rowCount,
    validation
  };
}

function formatParticipantName(participant: Participant): string {
  return `${participant.firstName} ${participant.lastName}`.trim() || participant.email;
}

function comparePartnerGroups(left: string, right: string): number {
  const leftNumber = Number(left);
  const rightNumber = Number(right);

  if (Number.isFinite(leftNumber) && Number.isFinite(rightNumber)) {
    return leftNumber - rightNumber;
  }

  return left.localeCompare(right, undefined, {
    numeric: true,
    sensitivity: "base"
  });
}

async function readErrorResponse(response: Response): Promise<string> {
  try {
    const body = (await response.json()) as { error?: string };
    return body.error ?? `Request failed with ${response.status}.`;
  } catch {
    return `Request failed with ${response.status}.`;
  }
}

function getErrorMessage(error: unknown): string {
  return error instanceof Error ? error.message : "Unexpected error.";
}

async function fetchWithTimeout(
  input: RequestInfo | URL,
  init: RequestInit,
  timeoutMs = 45_000
): Promise<Response> {
  const controller = new AbortController();
  const timeoutId = window.setTimeout(() => controller.abort(), timeoutMs);

  try {
    return await fetch(input, {
      ...init,
      signal: controller.signal
    });
  } catch (error) {
    if (error instanceof DOMException && error.name === "AbortError") {
      throw new Error("Request timed out. Check the API deployment logs.");
    }

    throw error;
  } finally {
    window.clearTimeout(timeoutId);
  }
}

async function fileToBase64Content(file: File): Promise<string> {
  try {
    const bytes = new Uint8Array(await file.arrayBuffer());
    let binary = "";
    const chunkSize = 0x8000;

    for (let index = 0; index < bytes.length; index += chunkSize) {
      binary += String.fromCharCode(...bytes.subarray(index, index + chunkSize));
    }

    return window.btoa(binary);
  } catch {
    throw new Error("Could not read participant file.");
  }
}

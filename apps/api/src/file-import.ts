import { parse } from "csv-parse/sync";
import { readSheet } from "read-excel-file/node";
import {
  groupParticipantsByPartnerGroup,
  validateRoster,
  type Mentor,
  type Participant
} from "@web3-talents/core";

export type ImportPreview = {
  mentors: Mentor[];
  participants: Participant[];
  validation: ReturnType<typeof validateRoster>;
  partnerGroups: Array<{
    partnerGroup: string;
    participantCount: number;
    participants: string[];
  }>;
  rowCount: number;
};

type RawParticipantRow = Record<string, string>;
type RawParticipantFields = Partial<Participant> & { name?: string };

const headerAliases = new Map<string, keyof RawParticipantFields>([
  ["name", "name"],
  ["full name", "name"],
  ["participant name", "name"],
  ["first name", "firstName"],
  ["firstname", "firstName"],
  ["first_name", "firstName"],
  ["last name", "lastName"],
  ["lastname", "lastName"],
  ["last_name", "lastName"],
  ["email", "email"],
  ["email address", "email"],
  ["discord username", "discordUsername"],
  ["discord", "discordUsername"],
  ["discord handle", "discordUsername"],
  ["discord user id", "discordUserId"],
  ["discord userid", "discordUserId"],
  ["discord id", "discordUserId"],
  ["partner group", "partnerGroup"],
  ["partner_group", "partnerGroup"],
  ["group", "partnerGroup"],
  ["buddy group", "partnerGroup"]
]);

export async function previewParticipantImport(
  fileBuffer: Buffer,
  filename: string
): Promise<ImportPreview> {
  const rows = await parseParticipantRows(fileBuffer, filename);
  const { mentorRows, participantRows } = splitMentorRows(rows);
  const participants = participantRows.map(mapRawRowToParticipant);
  const mentors = mentorRows.map(mapRawRowToMentor).filter((mentor) => mentor.name);
  const validation = validateRoster(participants);
  const partnerGroups = groupParticipantsByPartnerGroup(participants).map(
    (group) => ({
      participantCount: group.participants.length,
      participants: group.participants.map(
        (participant) => `${participant.firstName} ${participant.lastName}`.trim()
      ),
      partnerGroup: group.partnerGroup
    })
  );

  return {
    mentors,
    participants,
    partnerGroups,
    rowCount: participantRows.length,
    validation
  };
}

async function parseParticipantRows(
  fileBuffer: Buffer,
  filename: string
): Promise<RawParticipantRow[]> {
  const normalizedFilename = filename.toLowerCase();

  if (normalizedFilename.endsWith(".csv")) {
    return parseCsvRows(fileBuffer);
  }

  if (
    normalizedFilename.endsWith(".xlsx") ||
    normalizedFilename.endsWith(".xlsm")
  ) {
    return parseExcelRows(fileBuffer);
  }

  throw new Error("Unsupported file type. Upload an .xlsx, .xlsm, or .csv file.");
}

async function parseExcelRows(fileBuffer: Buffer): Promise<RawParticipantRow[]> {
  const rows = await readSheet(fileBuffer);
  return tableToRawRows(rows);
}

function parseCsvRows(fileBuffer: Buffer): RawParticipantRow[] {
  const records = parse(fileBuffer.toString("utf8"), {
    bom: true,
    columns: true,
    skip_empty_lines: true,
    trim: true
  }) as Array<Record<string, unknown>>;

  return records.map((record) =>
    Object.fromEntries(
      Object.entries(record).map(([key, value]) => [key, stringifyCell(value)])
    )
  );
}

function tableToRawRows(rows: unknown[][]): RawParticipantRow[] {
  const [headerRow, ...dataRows] = rows;

  if (!headerRow) {
    return [];
  }

  const headers = headerRow.map((cell) => stringifyCell(cell));

  return dataRows
    .filter((row) => row.some((cell) => stringifyCell(cell)))
    .map((row) =>
      Object.fromEntries(
        headers.map((header, index) => [header, stringifyCell(row[index])])
      )
    );
}

function mapRawRowToParticipant(row: RawParticipantRow): Participant {
  const participant: RawParticipantFields = {};

  for (const [rawHeader, value] of Object.entries(row)) {
    const participantKey = headerAliases.get(normalizeHeader(rawHeader));

    if (participantKey) {
      participant[participantKey] = value.trim();
    }
  }

  const parsedName = splitParticipantName(participant.name ?? "");

  return {
    email: participant.email ?? "",
    firstName: participant.firstName ?? parsedName.firstName,
    lastName: participant.lastName ?? parsedName.lastName,
    partnerGroup: normalizePartnerGroup(participant.partnerGroup ?? ""),
    ...(participant.discordUsername
      ? { discordUsername: participant.discordUsername }
      : {}),
    ...(participant.discordUserId
      ? { discordUserId: participant.discordUserId }
      : {})
  };
}

function mapRawRowToMentor(row: RawParticipantRow): Mentor {
  const fields: RawParticipantFields = {};

  for (const [rawHeader, value] of Object.entries(row)) {
    const participantKey = headerAliases.get(normalizeHeader(rawHeader));

    if (participantKey) {
      fields[participantKey] = value.trim();
    }
  }

  const name = fields.name?.trim() || `${fields.firstName ?? ""} ${fields.lastName ?? ""}`.trim();
  const email = fields.email?.trim();

  return {
    ...(email && email.toLowerCase() !== "unknown" ? { email } : {}),
    name
  };
}

function splitMentorRows(rows: RawParticipantRow[]): {
  mentorRows: RawParticipantRow[];
  participantRows: RawParticipantRow[];
} {
  const mentorHeaderIndex = rows.findIndex(isMentorMarkerRow);

  if (mentorHeaderIndex === -1) {
    return {
      mentorRows: [],
      participantRows: rows
    };
  }

  return {
    mentorRows: rows.slice(mentorHeaderIndex + 1),
    participantRows: rows.slice(0, mentorHeaderIndex)
  };
}

function isMentorMarkerRow(row: RawParticipantRow): boolean {
  return Object.values(row).some(
    (value) => ["mentor", "mentors"].includes(value.trim().toLowerCase())
  );
}

function splitParticipantName(name: string): Pick<Participant, "firstName" | "lastName"> {
  const trimmedName = name.trim().replace(/\s+/g, " ");
  const [firstName = "", ...lastNameParts] = trimmedName.split(" ");

  return {
    firstName,
    lastName: lastNameParts.join(" ")
  };
}

function normalizeHeader(header: string): string {
  return header.trim().toLowerCase().replace(/\s+/g, " ");
}

function normalizePartnerGroup(partnerGroup: string): string {
  const trimmedPartnerGroup = partnerGroup.trim();
  const numericMatch = /^group\s+(\d+)$/i.exec(trimmedPartnerGroup);

  if (numericMatch?.[1]) {
    return numericMatch[1];
  }

  if (/^\d+$/.test(trimmedPartnerGroup)) {
    return trimmedPartnerGroup;
  }

  return trimmedPartnerGroup;
}

function stringifyCell(value: unknown): string {
  if (value instanceof Date) {
    return value.toISOString();
  }

  return value === null || value === undefined ? "" : String(value).trim();
}

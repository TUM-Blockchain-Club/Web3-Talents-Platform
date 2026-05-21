import { parse } from "csv-parse/sync";
import { readSheet } from "read-excel-file/node";
import {
  groupParticipantsByPartnerGroup,
  validateRoster,
  type Participant
} from "@web3-talents/core";

export type ImportPreview = {
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
  const participants = rows.map(mapRawRowToParticipant);
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
    participants,
    partnerGroups,
    rowCount: rows.length,
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

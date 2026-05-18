import { stringify } from "csv-stringify/sync";
import writeXlsxFile, { type SheetData } from "write-excel-file/node";
import {
  buildInternalExportRows,
  buildZoomCsvRows,
  type AssignmentGenerationResult
} from "@web3-talents/core";

export async function createInternalExcelBuffer(
  result: AssignmentGenerationResult
): Promise<Buffer> {
  const rows = buildInternalExportRows(result);
  const sheetData: SheetData = [
    [
      headerCell("Participant name"),
      headerCell("Email"),
      headerCell("Discord username or ID"),
      headerCell("Voted topic"),
      headerCell("Partner group"),
      headerCell("Pre-assigned room")
    ],
    ...rows.map((row) => [
      row.participantName,
      row.email,
      row.discordIdentifier,
      row.votedTopic,
      row.partnerGroup,
      row.preAssignedRoom
    ])
  ];

  return writeXlsxFile(sheetData, {
    columns: [
      { width: 24 },
      { width: 32 },
      { width: 28 },
      { width: 24 },
      { width: 18 },
      { width: 18 }
    ]
  }).toBuffer();
}

export function createZoomCsvBuffer(result: AssignmentGenerationResult): Buffer {
  const rows = buildZoomCsvRows(result);
  const csv = stringify(rows, {
    bom: true,
    header: true
  });

  return Buffer.from(csv, "utf8");
}

function headerCell(value: string) {
  return {
    fontWeight: "bold" as const,
    value
  };
}

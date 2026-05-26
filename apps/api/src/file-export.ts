import { stringify } from "csv-stringify/sync";
import writeXlsxFile, {
  type CellObject,
  type SheetData
} from "write-excel-file/node";
import {
  buildZoomCsvRows,
  type AssignmentGenerationResult,
  type Participant,
  type PartnerGroupAssignment,
  type RoomAssignment
} from "@web3-talents/core";

const topicHeaderColors = ["#338BAA", "#A63A78", "#F28C00", "#CF3A1E"];
const minimumPersonColumnCount = 3;
const breakoutRoomHeader = "Breakout Room #";

export async function createInternalExcelBuffer(
  result: AssignmentGenerationResult
): Promise<Buffer> {
  const personColumnCount = getPersonColumnCount(result);
  const sheetData = buildBuddyGroupSheetData(result, personColumnCount);

  return writeXlsxFile(sheetData, {
    columns: getColumnWidths(result, personColumnCount).map((width) => ({
      width
    })),
    showGridLines: false
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

function buildBuddyGroupSheetData(
  result: AssignmentGenerationResult,
  personColumnCount: number
): SheetData {
  const exportColumnCount = personColumnCount + 1;
  const sheetData: SheetData = [
    mergedRow(titleCell("Buddy Groups"), exportColumnCount),
    blankRow(exportColumnCount)
  ];

  result.topics.forEach((topic, topicIndex) => {
    const roomAssignments = result.rooms.map((room) => ({
      assignment: findRoomTopicAssignment(room, topic.id),
      roomName: room.roomName
    }));

    sheetData.push(
      mergedRow(
        topicCell(
          `Group ${topicIndex + 1}: ${topic.label}`,
          topicHeaderColors[topicIndex % topicHeaderColors.length] ?? "#338BAA"
        ),
        exportColumnCount
      ),
      [
        columnHeaderCell(breakoutRoomHeader),
        ...Array.from({ length: personColumnCount }, (_, index) =>
          columnHeaderCell(`Person ${index + 1}`)
        )
      ],
      ...roomAssignments.map(({ assignment, roomName }) => [
        bodyCell(formatRoomNumber(roomName), "center"),
        ...Array.from({ length: personColumnCount }, (_, participantIndex) =>
          bodyCell(
            assignment?.participants[participantIndex]
              ? formatParticipantName(
                  assignment.participants[participantIndex]
                )
              : ""
          )
        )
      ]),
      blankRow(exportColumnCount)
    );
  });

  return sheetData;
}

function findRoomTopicAssignment(
  room: RoomAssignment,
  topicId: string
): PartnerGroupAssignment | undefined {
  return room.partnerGroups.find(
    (assignment) => assignment.assignedTopicId === topicId
  );
}

function formatRoomNumber(roomName: RoomAssignment["roomName"]): number | string {
  const roomNumber = Number(roomName.replace(/^Room/, ""));

  return Number.isInteger(roomNumber) ? roomNumber : roomName;
}

function formatParticipantName(participant: Participant): string {
  return `${participant.firstName} ${participant.lastName}`.trim();
}

function getColumnWidths(
  result: AssignmentGenerationResult,
  personColumnCount: number
): number[] {
  const personColumnWidths = Array.from({ length: personColumnCount }, (_, index) => {
    const longestNameLength = Math.max(
      `Person ${index + 1}`.length,
      ...result.partnerGroupAssignments.map((assignment) =>
        assignment.participants[index]
          ? formatParticipantName(assignment.participants[index]).length
          : 0
      )
    );

    return clampColumnWidth(toReadableColumnWidth(longestNameLength), 24, 52);
  });

  return [
    clampColumnWidth(toReadableColumnWidth(breakoutRoomHeader.length), 26, 34),
    ...personColumnWidths
  ];
}

function clampColumnWidth(width: number, minimum: number, maximum: number): number {
  return Math.min(maximum, Math.max(minimum, width));
}

function toReadableColumnWidth(textLength: number): number {
  return Math.ceil(textLength * 1.35) + 5;
}

function getPersonColumnCount(result: AssignmentGenerationResult): number {
  return Math.max(
    minimumPersonColumnCount,
    ...result.partnerGroupAssignments.map(
      (assignment) => assignment.participants.length
    )
  );
}

function mergedRow(
  cell: CellObject,
  exportColumnCount: number
): SheetData[number] {
  return [
    {
      ...cell,
      columnSpan: exportColumnCount
    },
    ...Array.from({ length: exportColumnCount - 1 }, () => null)
  ];
}

function blankRow(exportColumnCount: number): SheetData[number] {
  return Array.from({ length: exportColumnCount }, () => ({
    value: "",
    height: 20
  }));
}

function titleCell(value: string): CellObject {
  return {
    align: "center",
    alignVertical: "center",
    fontSize: 24,
    fontWeight: "bold" as const,
    height: 32,
    value
  };
}

function topicCell(value: string, backgroundColor: string): CellObject {
  return {
    align: "center",
    alignVertical: "center",
    backgroundColor,
    borderColor: "#000000",
    borderStyle: "thin",
    fontSize: 20,
    fontWeight: "bold",
    height: 30,
    textColor: "#FFFFFF",
    value
  };
}

function columnHeaderCell(value: string): CellObject {
  return {
    align: "center",
    alignVertical: "center",
    backgroundColor: "#D9D9D9",
    borderColor: "#000000",
    borderStyle: "thin",
    fontSize: 18,
    fontWeight: "bold",
    height: 28,
    value,
    wrap: true
  };
}

function bodyCell(
  value: string | number,
  align: CellObject["align"] = "left"
): CellObject {
  return {
    align,
    alignVertical: "center",
    borderColor: "#000000",
    borderStyle: "thin",
    fontSize: 18,
    height: 28,
    value,
    wrap: true
  };
}

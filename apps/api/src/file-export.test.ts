import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { generateAssignments, type Participant, type WeeklyTopic } from "@web3-talents/core";
import { createInternalExcelBuffer, createZoomCsvBuffer } from "./file-export.js";

const topics: WeeklyTopic[] = [
  { id: "topic-1", label: "Topic 1" },
  { id: "topic-2", label: "Topic 2" },
  { id: "topic-3", label: "Topic 3" },
  { id: "topic-4", label: "Topic 4" }
];

describe("file exports", () => {
  it("creates a non-empty internal Excel file", async () => {
    const result = generateAssignments({
      participants: participants(),
      topics,
      votes: [{ discordUsername: "alice", topicId: "topic-1" }]
    });

    const buffer = await createInternalExcelBuffer(result);

    assert.equal(buffer.subarray(0, 2).toString("hex"), "504b");
    assert.equal(buffer.length > 1000, true);
  });

  it("creates a Zoom CSV with only Zoom columns", () => {
    const result = generateAssignments({
      participants: participants(),
      topics,
      votes: [{ discordUsername: "alice", topicId: "topic-1" }]
    });

    const csv = createZoomCsvBuffer(result).toString("utf8");
    const normalizedCsv = csv.replace(/^\uFEFF/, "");
    const [header] = normalizedCsv.split(/\r?\n/);

    assert.equal(header, "Pre-assign Room Name,Email Address");
    assert.equal(csv.includes("Partner group"), false);
    assert.equal(csv.includes("alice@example.com"), true);
    assert.equal(csv.includes("bob@example.com"), true);
  });
});

function participants(): Participant[] {
  return [
    {
      discordUsername: "alice",
      email: "alice@example.com",
      firstName: "Alice",
      lastName: "Smith",
      partnerGroup: "Group A"
    },
    {
      discordUsername: "bob",
      email: "bob@example.com",
      firstName: "Bob",
      lastName: "Jones",
      partnerGroup: "Group A"
    }
  ];
}

import assert from "node:assert/strict";
import { describe, it } from "node:test";
import { readSheet } from "read-excel-file/node";
import {
  generateAssignments,
  type AssignmentGenerationResult,
  type Participant,
  type WeeklyTopic
} from "@web3-talents/core";
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

  it("exports internal Excel topic rows in room order", async () => {
    const buffer = await createInternalExcelBuffer(roomOrderResult());
    const rows = await readSheet(buffer);
    const topicOneHeaderIndex = rows.findIndex(
      (row) => row[0] === "Group 1: Topic 1"
    );

    assert.notEqual(topicOneHeaderIndex, -1);
    assert.deepEqual(rows[topicOneHeaderIndex + 2], [
      1,
      "Room One Topic One",
      null,
      null
    ]);
    assert.deepEqual(rows[topicOneHeaderIndex + 3], [
      2,
      "Room Two Topic One",
      null,
      null
    ]);

    const mentorHeaderIndex = rows.findIndex((row) => row[0] === "Mentors");
    assert.notEqual(mentorHeaderIndex, -1);
    assert.deepEqual(rows[mentorHeaderIndex + 2], [
      1,
      "Mentor One",
      "mentor.one@example.com",
      null
    ]);
    assert.deepEqual(rows[mentorHeaderIndex + 3], [
      2,
      "Mentor Two",
      "unknown",
      null
    ]);
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

function roomOrderResult(): AssignmentGenerationResult {
  const roomOneTopicOne = participant("Room", "One Topic One", "1");
  const roomTwoTopicOne = participant("Room", "Two Topic One", "2");
  const roomOneTopicTwo = participant("Room", "One Topic Two", "3");

  return {
    partnerGroupAssignments: [
      {
        assignedTopicId: "topic-1",
        assignmentReason: "same-vote",
        participants: [roomTwoTopicOne],
        partnerGroup: "2",
        votedTopicIds: ["topic-1"]
      },
      {
        assignedTopicId: "topic-1",
        assignmentReason: "same-vote",
        participants: [roomOneTopicOne],
        partnerGroup: "10",
        votedTopicIds: ["topic-1"]
      },
      {
        assignedTopicId: "topic-2",
        assignmentReason: "same-vote",
        participants: [roomOneTopicTwo],
        partnerGroup: "3",
        votedTopicIds: ["topic-2"]
      }
    ],
    partnerGroups: [
      { participants: [roomOneTopicOne], partnerGroup: "10" },
      { participants: [roomTwoTopicOne], partnerGroup: "2" },
      { participants: [roomOneTopicTwo], partnerGroup: "3" }
    ],
    mentors: [
      {
        email: "mentor.one@example.com",
        name: "Mentor One",
        roomName: "Room1"
      },
      {
        name: "Mentor Two",
        roomName: "Room2"
      }
    ],
    rooms: [
      {
        partnerGroups: [
          {
            assignedTopicId: "topic-1",
            assignmentReason: "same-vote",
            participants: [roomOneTopicOne],
            partnerGroup: "10",
            votedTopicIds: ["topic-1"]
          },
          {
            assignedTopicId: "topic-2",
            assignmentReason: "same-vote",
            participants: [roomOneTopicTwo],
            partnerGroup: "3",
            votedTopicIds: ["topic-2"]
          }
        ],
        roomName: "Room1"
      },
      {
        partnerGroups: [
          {
            assignedTopicId: "topic-1",
            assignmentReason: "same-vote",
            participants: [roomTwoTopicOne],
            partnerGroup: "2",
            votedTopicIds: ["topic-1"]
          }
        ],
        roomName: "Room2"
      }
    ],
    topics,
    voteMapping: {
      matchedVotes: [],
      participantEmailsWithVotes: new Set(),
      unmatchedVotes: [],
      warnings: []
    },
    warnings: []
  };
}

function participant(firstName: string, lastName: string, group: string): Participant {
  return {
    email: `${firstName}.${lastName}`.replaceAll(" ", ".").toLowerCase() +
      "@example.com",
    firstName,
    lastName,
    partnerGroup: group
  };
}

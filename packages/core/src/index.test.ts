import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  buildInternalExportRows,
  buildZoomCsvRows,
  generateAssignments,
  movePartnerGroup,
  type Participant,
  type Vote,
  type WeeklyTopic,
  validateRoster
} from "./index.js";

const topics: WeeklyTopic[] = [
  { id: "topic-1", label: "Topic 1" },
  { id: "topic-2", label: "Topic 2" },
  { id: "topic-3", label: "Topic 3" },
  { id: "topic-4", label: "Topic 4" }
];

describe("validateRoster", () => {
  it("reports duplicate emails and invalid partner-group sizes", () => {
    const participants = [
      participant("A", "One", "same@example.com", "a", "Group A"),
      participant("B", "Two", "same@example.com", "b", "Group A"),
      participant("C", "Three", "c@example.com", "c", "Group B")
    ];

    const result = validateRoster(participants);

    assert.equal(result.valid, false);
    assert.equal(
      result.errors.some((error) => error.code === "duplicate_email"),
      true
    );
    assert.equal(
      result.warnings.some((warning) => warning.code === "small_partner_group"),
      true
    );
  });
});

describe("generateAssignments", () => {
  it("assigns a pair to the same voted topic", () => {
    const participants = [
      participant("A", "One", "a@example.com", "discord-a", "Group A"),
      participant("B", "Two", "b@example.com", "discord-b", "Group A")
    ];
    const result = generateAssignments({
      participants,
      topics,
      votes: [
        vote("discord-a", "topic-1"),
        vote("discord-b", "topic-1")
      ]
    });

    assert.equal(result.partnerGroupAssignments[0]?.assignedTopicId, "topic-1");
    assert.equal(result.partnerGroupAssignments[0]?.assignmentReason, "same-vote");
  });

  it("assigns a pair split vote to the topic that best balances counts", () => {
    const participants = [
      participant("A", "One", "a@example.com", "discord-a", "Group A"),
      participant("B", "Two", "b@example.com", "discord-b", "Group A"),
      participant("C", "Three", "c@example.com", "discord-c", "Group B"),
      participant("D", "Four", "d@example.com", "discord-d", "Group B"),
      participant("E", "Five", "e@example.com", "discord-e", "Group C"),
      participant("F", "Six", "f@example.com", "discord-f", "Group C")
    ];
    const result = generateAssignments({
      participants,
      topics,
      votes: [
        vote("discord-a", "topic-1"),
        vote("discord-b", "topic-2"),
        vote("discord-c", "topic-1"),
        vote("discord-d", "topic-1"),
        vote("discord-e", "topic-1"),
        vote("discord-f", "topic-1")
      ]
    });

    const groupA = result.partnerGroupAssignments.find(
      (assignment) => assignment.partnerGroup === "Group A"
    );

    assert.equal(groupA?.assignedTopicId, "topic-2");
    assert.equal(groupA?.assignmentReason, "split-vote-balanced");
  });

  it("assigns a pair to the only partner vote", () => {
    const participants = [
      participant("A", "One", "a@example.com", "discord-a", "Group A"),
      participant("B", "Two", "b@example.com", "discord-b", "Group A")
    ];
    const result = generateAssignments({
      participants,
      topics,
      votes: [vote("discord-a", "topic-3")]
    });

    assert.equal(result.partnerGroupAssignments[0]?.assignedTopicId, "topic-3");
    assert.equal(result.partnerGroupAssignments[0]?.assignmentReason, "single-vote");
  });

  it("assigns a group of three to its majority vote", () => {
    const participants = [
      participant("A", "One", "a@example.com", "discord-a", "Group A"),
      participant("B", "Two", "b@example.com", "discord-b", "Group A"),
      participant("C", "Three", "c@example.com", "discord-c", "Group A")
    ];
    const result = generateAssignments({
      participants,
      topics,
      votes: [
        vote("discord-a", "topic-2"),
        vote("discord-b", "topic-2"),
        vote("discord-c", "topic-4")
      ]
    });

    assert.equal(result.partnerGroupAssignments[0]?.assignedTopicId, "topic-2");
    assert.equal(
      result.partnerGroupAssignments[0]?.assignmentReason,
      "majority-vote"
    );
  });

  it("balances partner groups where nobody votes", () => {
    const participants = [
      participant("A", "One", "a@example.com", "discord-a", "Group A"),
      participant("B", "Two", "b@example.com", "discord-b", "Group A"),
      participant("C", "Three", "c@example.com", "discord-c", "Group B"),
      participant("D", "Four", "d@example.com", "discord-d", "Group B"),
      participant("E", "Five", "e@example.com", "discord-e", "Group C"),
      participant("F", "Six", "f@example.com", "discord-f", "Group C"),
      participant("G", "Seven", "g@example.com", "discord-g", "Group D"),
      participant("H", "Eight", "h@example.com", "discord-h", "Group D")
    ];
    const result = generateAssignments({
      participants,
      topics,
      votes: []
    });
    const assignedTopicIds = result.partnerGroupAssignments.map(
      (assignment) => assignment.assignedTopicId
    );

    assert.deepEqual(assignedTopicIds.sort(), [
      "topic-1",
      "topic-2",
      "topic-3",
      "topic-4"
    ]);
  });

  it("generates one room per topic-group set when possible", () => {
    const participants = createBalancedRoomParticipants(4);
    const result = generateAssignments({
      participants,
      topics,
      votes: createBalancedRoomVotes(4)
    });

    assert.deepEqual(
      result.rooms.map((room) => room.roomName),
      ["Room1", "Room2", "Room3", "Room4"]
    );

    for (const room of result.rooms) {
      assert.equal(room.partnerGroups.length, 4);
      assert.equal(new Set(room.partnerGroups.map((group) => group.assignedTopicId)).size, 4);
    }
  });

  it("generates more than four rooms for larger cohorts when needed", () => {
    const participants = createBalancedRoomParticipants(6);
    const result = generateAssignments({
      participants,
      topics,
      votes: createBalancedRoomVotes(6)
    });

    assert.deepEqual(
      result.rooms.map((room) => room.roomName),
      ["Room1", "Room2", "Room3", "Room4", "Room5", "Room6"]
    );

    for (const room of result.rooms) {
      assert.equal(room.partnerGroups.length, 4);
      assert.equal(
        new Set(room.partnerGroups.map((group) => group.assignedTopicId)).size,
        4
      );
    }
  });

  it("matches votes by Discord user ID before username", () => {
    const participants = [
      {
        ...participant("A", "One", "a@example.com", "same-name", "Group A"),
        discordUserId: "100"
      },
      {
        ...participant("B", "Two", "b@example.com", "same-name", "Group A"),
        discordUserId: "200"
      }
    ];
    const result = generateAssignments({
      participants,
      topics,
      votes: [
        {
          discordUsername: "same-name",
          discordUserId: "200",
          topicId: "topic-4"
        }
      ]
    });

    const matchedEmail = result.voteMapping.matchedVotes[0]?.participant.email;

    assert.equal(matchedEmail, "b@example.com");
  });

  it("tracks unmatched Discord voters as warnings", () => {
    const participants = [
      participant("A", "One", "a@example.com", "discord-a", "Group A"),
      participant("B", "Two", "b@example.com", "discord-b", "Group A")
    ];
    const result = generateAssignments({
      participants,
      topics,
      votes: [vote("unknown-user", "topic-1")]
    });

    assert.equal(result.voteMapping.unmatchedVotes.length, 1);
    assert.equal(
      result.warnings.some((warning) => warning.code === "unmatched_vote"),
      true
    );
  });
});

describe("exports and overrides", () => {
  it("builds internal Excel rows and Zoom CSV rows from final assignments", () => {
    const participants = [
      participant("A", "One", "a@example.com", "discord-a", "Group A"),
      participant("B", "Two", "b@example.com", "discord-b", "Group A")
    ];
    const result = generateAssignments({
      participants,
      topics,
      votes: [vote("discord-a", "topic-1")]
    });

    const internalRows = buildInternalExportRows(result);
    const zoomRows = buildZoomCsvRows(result);

    assert.equal(internalRows.length, 2);
    assert.deepEqual(Object.keys(zoomRows[0] ?? {}), [
      "Pre-assign Room Name",
      "Email Address"
    ]);
  });

  it("moves a partner group to another room", () => {
    const result = generateAssignments({
      participants: createBalancedRoomParticipants(4),
      topics,
      votes: createBalancedRoomVotes(4)
    });
    const movedRooms = movePartnerGroup(result.rooms, "Group 1-1", "Room4");
    const room4 = movedRooms.find((room) => room.roomName === "Room4");

    assert.equal(
      room4?.partnerGroups.some(
        (assignment) => assignment.partnerGroup === "Group 1-1"
      ),
      true
    );
  });
});

function participant(
  firstName: string,
  lastName: string,
  email: string,
  discordUsername: string,
  partnerGroup: string
): Participant {
  return {
    discordUsername,
    email,
    firstName,
    lastName,
    partnerGroup
  };
}

function vote(discordUsername: string, topicId: string): Vote {
  return {
    discordUsername,
    topicId
  };
}

function createBalancedRoomParticipants(groupsPerTopic: number): Participant[] {
  const participants: Participant[] = [];
  const topicCount = 4;

  for (let topicIndex = 1; topicIndex <= topicCount; topicIndex += 1) {
    for (let groupIndex = 1; groupIndex <= groupsPerTopic; groupIndex += 1) {
      const groupLabel = `Group ${topicIndex}-${groupIndex}`;
      participants.push(
        participant(
          `A${topicIndex}${groupIndex}`,
          "One",
          `a${topicIndex}${groupIndex}@example.com`,
          `discord-a-${topicIndex}-${groupIndex}`,
          groupLabel
        ),
        participant(
          `B${topicIndex}${groupIndex}`,
          "Two",
          `b${topicIndex}${groupIndex}@example.com`,
          `discord-b-${topicIndex}-${groupIndex}`,
          groupLabel
        )
      );
    }
  }

  return participants;
}

function createBalancedRoomVotes(groupsPerTopic: number): Vote[] {
  const votes: Vote[] = [];
  const topicCount = 4;

  for (let topicIndex = 1; topicIndex <= topicCount; topicIndex += 1) {
    for (let groupIndex = 1; groupIndex <= groupsPerTopic; groupIndex += 1) {
      votes.push(
        vote(`discord-a-${topicIndex}-${groupIndex}`, `topic-${topicIndex}`),
        vote(`discord-b-${topicIndex}-${groupIndex}`, `topic-${topicIndex}`)
      );
    }
  }

  return votes;
}

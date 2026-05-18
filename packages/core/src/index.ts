export type Participant = {
  firstName: string;
  lastName: string;
  email: string;
  discordUsername?: string;
  discordUserId?: string;
  partnerGroup: string;
};

export type WeeklyTopic = {
  id: string;
  label: string;
  discordPollAnswerId?: string;
};

export type Vote = {
  discordUserId?: string;
  discordUsername?: string;
  topicId: string;
};

export type PartnerGroup = {
  partnerGroup: string;
  participants: Participant[];
};

export type PartnerGroupAssignment = {
  partnerGroup: string;
  participants: Participant[];
  votedTopicIds: string[];
  assignedTopicId: string;
  assignmentReason:
    | "same-vote"
    | "single-vote"
    | "majority-vote"
    | "split-vote-balanced"
    | "no-vote-balanced";
};

export type RoomName = `Room${number}`;

export type RoomAssignment = {
  roomName: RoomName;
  partnerGroups: PartnerGroupAssignment[];
};

export type ValidationIssue = {
  code: string;
  message: string;
  field?: string;
  email?: string;
  partnerGroup?: string;
};

export type RosterValidationResult = {
  valid: boolean;
  errors: ValidationIssue[];
  warnings: ValidationIssue[];
};

export type MatchedVote = {
  participant: Participant;
  vote: Vote;
};

export type VoteMappingResult = {
  matchedVotes: MatchedVote[];
  unmatchedVotes: Vote[];
  participantEmailsWithVotes: Set<string>;
  warnings: ValidationIssue[];
};

export type AssignmentGenerationInput = {
  participants: Participant[];
  topics: WeeklyTopic[];
  votes: Vote[];
};

export type AssignmentGenerationResult = {
  topics: WeeklyTopic[];
  partnerGroups: PartnerGroup[];
  voteMapping: VoteMappingResult;
  partnerGroupAssignments: PartnerGroupAssignment[];
  rooms: RoomAssignment[];
  warnings: ValidationIssue[];
};

export type InternalExportRow = {
  participantName: string;
  email: string;
  discordIdentifier: string;
  votedTopic: string;
  partnerGroup: string;
  preAssignedRoom: RoomName;
};

export type ZoomCsvRow = {
  "Pre-assign Room Name": RoomName;
  "Email Address": string;
};

export function createRoomName(roomNumber: number): RoomName {
  if (!Number.isInteger(roomNumber) || roomNumber < 1) {
    throw new Error("Room number must be a positive integer.");
  }

  return `Room${roomNumber}`;
}

export function validateRoster(participants: Participant[]): RosterValidationResult {
  const errors: ValidationIssue[] = [];
  const warnings: ValidationIssue[] = [];
  const emails = new Set<string>();

  for (const participant of participants) {
    const email = normalizeEmail(participant.email);

    if (!participant.firstName.trim()) {
      errors.push({
        code: "missing_first_name",
        email: participant.email,
        field: "firstName",
        message: "First name is required."
      });
    }

    if (!participant.lastName.trim()) {
      errors.push({
        code: "missing_last_name",
        email: participant.email,
        field: "lastName",
        message: "Last name is required."
      });
    }

    if (!email) {
      errors.push({
        code: "missing_email",
        field: "email",
        message: "Email is required."
      });
    } else if (!isLikelyEmail(email)) {
      errors.push({
        code: "invalid_email",
        email: participant.email,
        field: "email",
        message: "Email must look like a valid email address."
      });
    } else if (emails.has(email)) {
      errors.push({
        code: "duplicate_email",
        email: participant.email,
        field: "email",
        message: "Email must be unique."
      });
    } else {
      emails.add(email);
    }

    if (!participant.partnerGroup.trim()) {
      errors.push({
        code: "missing_partner_group",
        email: participant.email,
        field: "partnerGroup",
        message: "Partner group is required."
      });
    }

    if (!participant.discordUsername?.trim() && !participant.discordUserId?.trim()) {
      warnings.push({
        code: "missing_discord_identifier",
        email: participant.email,
        message:
          "Discord username or Discord user ID is required to map poll votes."
      });
    }
  }

  const partnerGroups = groupParticipantsByPartnerGroup(participants);

  for (const group of partnerGroups) {
    if (group.participants.length < 2) {
      warnings.push({
        code: "small_partner_group",
        message: "Partner group has fewer than two participants.",
        partnerGroup: group.partnerGroup
      });
    }

    if (group.participants.length > 3) {
      warnings.push({
        code: "large_partner_group",
        message: "Partner group has more than three participants.",
        partnerGroup: group.partnerGroup
      });
    }
  }

  return {
    errors,
    valid: errors.length === 0,
    warnings
  };
}

export function validateTopics(topics: WeeklyTopic[]): RosterValidationResult {
  const errors: ValidationIssue[] = [];
  const topicIds = new Set<string>();

  if (topics.length !== 4) {
    errors.push({
      code: "invalid_topic_count",
      message: "Exactly four weekly topics are required."
    });
  }

  for (const topic of topics) {
    if (!topic.id.trim()) {
      errors.push({
        code: "missing_topic_id",
        field: "id",
        message: "Topic ID is required."
      });
    }

    if (!topic.label.trim()) {
      errors.push({
        code: "missing_topic_label",
        field: "label",
        message: "Topic label is required."
      });
    }

    if (topicIds.has(topic.id)) {
      errors.push({
        code: "duplicate_topic_id",
        field: "id",
        message: "Topic IDs must be unique."
      });
    }

    topicIds.add(topic.id);
  }

  return {
    errors,
    valid: errors.length === 0,
    warnings: []
  };
}

export function groupParticipantsByPartnerGroup(
  participants: Participant[]
): PartnerGroup[] {
  const groups = new Map<string, Participant[]>();

  for (const participant of participants) {
    const groupName = participant.partnerGroup.trim();
    const groupParticipants = groups.get(groupName) ?? [];
    groupParticipants.push(participant);
    groups.set(groupName, groupParticipants);
  }

  return [...groups.entries()]
    .sort(([left], [right]) => left.localeCompare(right))
    .map(([partnerGroup, groupParticipants]) => ({
      participantCount: groupParticipants.length,
      participants: groupParticipants.sort(compareParticipants),
      partnerGroup
    }))
    .map(({ participants: sortedParticipants, partnerGroup }) => ({
      participants: sortedParticipants,
      partnerGroup
    }));
}

export function mapVotesToParticipants(
  participants: Participant[],
  votes: Vote[]
): VoteMappingResult {
  const byDiscordUserId = new Map<string, Participant>();
  const byDiscordUsername = new Map<string, Participant>();
  const matchedVotes: MatchedVote[] = [];
  const unmatchedVotes: Vote[] = [];
  const participantEmailsWithVotes = new Set<string>();
  const warnings: ValidationIssue[] = [];

  for (const participant of participants) {
    const userId = normalizeIdentifier(participant.discordUserId);
    const username = normalizeIdentifier(participant.discordUsername);

    if (userId) {
      byDiscordUserId.set(userId, participant);
    }

    if (username) {
      byDiscordUsername.set(username, participant);
    }
  }

  for (const vote of votes) {
    const participant = findParticipantForVote(
      vote,
      byDiscordUserId,
      byDiscordUsername
    );

    if (!participant) {
      unmatchedVotes.push(vote);
      warnings.push({
        code: "unmatched_vote",
        message: "Discord vote could not be matched to an imported participant."
      });
      continue;
    }

    const email = normalizeEmail(participant.email);

    if (participantEmailsWithVotes.has(email)) {
      warnings.push({
        code: "duplicate_participant_vote",
        email: participant.email,
        message:
          "Participant appears to have more than one vote. The first matched vote is used."
      });
      continue;
    }

    participantEmailsWithVotes.add(email);
    matchedVotes.push({
      participant,
      vote
    });
  }

  return {
    matchedVotes,
    participantEmailsWithVotes,
    unmatchedVotes,
    warnings
  };
}

export function generateAssignments(
  input: AssignmentGenerationInput
): AssignmentGenerationResult {
  const rosterValidation = validateRoster(input.participants);
  const topicValidation = validateTopics(input.topics);
  const warnings = [
    ...rosterValidation.warnings,
    ...topicValidation.warnings
  ];

  if (!rosterValidation.valid || !topicValidation.valid) {
    const messages = [
      ...rosterValidation.errors,
      ...topicValidation.errors
    ].map((issue) => issue.message);
    throw new Error(`Cannot generate assignments: ${messages.join(" ")}`);
  }

  const partnerGroups = groupParticipantsByPartnerGroup(input.participants);
  const voteMapping = mapVotesToParticipants(input.participants, input.votes);
  warnings.push(...voteMapping.warnings);

  const partnerGroupAssignments = assignTopicsToPartnerGroups(
    partnerGroups,
    input.topics,
    voteMapping.matchedVotes
  );
  const rooms = generateRoomAssignments(partnerGroupAssignments, input.topics);

  return {
    partnerGroupAssignments,
    partnerGroups,
    rooms,
    topics: input.topics,
    voteMapping,
    warnings
  };
}

export function assignTopicsToPartnerGroups(
  partnerGroups: PartnerGroup[],
  topics: WeeklyTopic[],
  matchedVotes: MatchedVote[]
): PartnerGroupAssignment[] {
  const topicIds = topics.map((topic) => topic.id);
  const topicCounts = new Map(topicIds.map((topicId) => [topicId, 0]));
  const votesByEmail = new Map(
    matchedVotes.map((matchedVote) => [
      normalizeEmail(matchedVote.participant.email),
      matchedVote.vote.topicId
    ])
  );

  const decisions = partnerGroups.map((group) =>
    createPartnerGroupDecision(group, topicIds, votesByEmail)
  );
  const forcedAssignments: PartnerGroupAssignment[] = [];
  const flexibleDecisions = decisions.filter((decision) => !decision.forced);

  for (const decision of decisions.filter((item) => item.forced)) {
    const assignedTopicId = decision.candidateTopicIds[0];

    if (!assignedTopicId) {
      throw new Error(`No topic candidate found for ${decision.group.partnerGroup}.`);
    }

    incrementTopicCount(topicCounts, assignedTopicId);
    forcedAssignments.push({
      assignedTopicId,
      assignmentReason: decision.reason,
      participants: decision.group.participants,
      partnerGroup: decision.group.partnerGroup,
      votedTopicIds: decision.votedTopicIds
    });
  }

  const flexibleAssignments = flexibleDecisions.map((decision) => {
    const assignedTopicId = chooseBalancedTopic(
      decision.candidateTopicIds,
      topicCounts,
      topicIds
    );
    incrementTopicCount(topicCounts, assignedTopicId);

    return {
      assignedTopicId,
      assignmentReason: decision.reason,
      participants: decision.group.participants,
      partnerGroup: decision.group.partnerGroup,
      votedTopicIds: decision.votedTopicIds
    };
  });

  return [...forcedAssignments, ...flexibleAssignments].sort((left, right) =>
    left.partnerGroup.localeCompare(right.partnerGroup)
  );
}

export function generateRoomAssignments(
  partnerGroupAssignments: PartnerGroupAssignment[],
  topics: WeeklyTopic[]
): RoomAssignment[] {
  const rooms: RoomAssignment[] = createRoomNames(
    getRecommendedRoomCount(partnerGroupAssignments, topics)
  ).map((roomName) => ({
    partnerGroups: [],
    roomName
  }));

  for (const topic of topics) {
    const groupsForTopic = partnerGroupAssignments
      .filter((assignment) => assignment.assignedTopicId === topic.id)
      .sort(comparePartnerGroupAssignments);

    for (const assignment of groupsForTopic) {
      const candidateRooms = rooms.filter(
        (room) => !roomHasTopic(room, assignment.assignedTopicId)
      );
      const room = chooseBestRoom(
        candidateRooms.length > 0 ? candidateRooms : rooms
      );
      room.partnerGroups.push(assignment);
    }
  }

  return rooms;
}

export function movePartnerGroup(
  rooms: RoomAssignment[],
  partnerGroup: string,
  targetRoomName: RoomName
): RoomAssignment[] {
  const assignmentToMove = rooms
    .flatMap((room) => room.partnerGroups)
    .find((assignment) => assignment.partnerGroup === partnerGroup);

  if (!assignmentToMove) {
    throw new Error(`Partner group ${partnerGroup} was not found.`);
  }

  const nextRooms = rooms.map((room) => ({
    partnerGroups: room.partnerGroups.filter(
      (assignment) => assignment.partnerGroup !== partnerGroup
    ),
    roomName: room.roomName
  }));

  return nextRooms.map((room) => {
    if (room.roomName !== targetRoomName) {
      return room;
    }

    return {
      partnerGroups: [...room.partnerGroups, assignmentToMove].sort(
        comparePartnerGroupAssignments
      ),
      roomName: room.roomName
    };
  });
}

export function buildInternalExportRows(
  result: AssignmentGenerationResult
): InternalExportRow[] {
  const roomByPartnerGroup = new Map<string, RoomName>();
  const topicLabelById = new Map(
    result.topics.map((topic) => [topic.id, topic.label])
  );
  const votedTopicByEmail = new Map(
    result.voteMapping.matchedVotes.map((matchedVote) => [
      normalizeEmail(matchedVote.participant.email),
      topicLabelById.get(matchedVote.vote.topicId) ?? matchedVote.vote.topicId
    ])
  );

  for (const room of result.rooms) {
    for (const assignment of room.partnerGroups) {
      roomByPartnerGroup.set(assignment.partnerGroup, room.roomName);
    }
  }

  return result.partnerGroups
    .flatMap((group) =>
      group.participants.map((participant) => ({
        discordIdentifier:
          participant.discordUserId ?? participant.discordUsername ?? "",
        email: participant.email,
        participantName: formatParticipantName(participant),
        partnerGroup: participant.partnerGroup,
        preAssignedRoom: roomByPartnerGroup.get(participant.partnerGroup),
        votedTopic: votedTopicByEmail.get(normalizeEmail(participant.email)) ?? ""
      }))
    )
    .filter((row): row is InternalExportRow => Boolean(row.preAssignedRoom))
    .sort((left, right) => {
      const roomComparison = left.preAssignedRoom.localeCompare(
        right.preAssignedRoom
      );

      if (roomComparison !== 0) {
        return roomComparison;
      }

      return left.participantName.localeCompare(right.participantName);
    });
}

export function buildZoomCsvRows(
  result: AssignmentGenerationResult
): ZoomCsvRow[] {
  return buildInternalExportRows(result).map((row) => ({
    "Pre-assign Room Name": row.preAssignedRoom,
    "Email Address": row.email
  }));
}

type PartnerGroupDecision = {
  candidateTopicIds: string[];
  forced: boolean;
  group: PartnerGroup;
  reason: PartnerGroupAssignment["assignmentReason"];
  votedTopicIds: string[];
};

function createPartnerGroupDecision(
  group: PartnerGroup,
  topicIds: string[],
  votesByEmail: Map<string, string>
): PartnerGroupDecision {
  const votedTopicIds = group.participants
    .map((participant) => votesByEmail.get(normalizeEmail(participant.email)))
    .filter((topicId): topicId is string => Boolean(topicId))
    .filter((topicId) => topicIds.includes(topicId));
  const uniqueVotedTopicIds = [...new Set(votedTopicIds)];

  if (votedTopicIds.length === 0) {
    return {
      candidateTopicIds: topicIds,
      forced: false,
      group,
      reason: "no-vote-balanced",
      votedTopicIds: []
    };
  }

  if (uniqueVotedTopicIds.length === 1) {
    return {
      candidateTopicIds: uniqueVotedTopicIds,
      forced: true,
      group,
      reason: votedTopicIds.length === group.participants.length
        ? "same-vote"
        : "single-vote",
      votedTopicIds
    };
  }

  const majorityTopicId = findMajorityTopic(votedTopicIds);

  if (majorityTopicId) {
    return {
      candidateTopicIds: [majorityTopicId],
      forced: true,
      group,
      reason: "majority-vote",
      votedTopicIds
    };
  }

  return {
    candidateTopicIds: uniqueVotedTopicIds,
    forced: false,
    group,
    reason: "split-vote-balanced",
    votedTopicIds
  };
}

function findMajorityTopic(votedTopicIds: string[]): string | undefined {
  const counts = new Map<string, number>();

  for (const topicId of votedTopicIds) {
    counts.set(topicId, (counts.get(topicId) ?? 0) + 1);
  }

  for (const [topicId, count] of counts) {
    if (count > votedTopicIds.length / 2) {
      return topicId;
    }
  }

  return undefined;
}

function chooseBalancedTopic(
  candidateTopicIds: string[],
  topicCounts: Map<string, number>,
  topicOrder: string[]
): string {
  const sortedCandidates = [...candidateTopicIds].sort((left, right) => {
    const countComparison =
      (topicCounts.get(left) ?? 0) - (topicCounts.get(right) ?? 0);

    if (countComparison !== 0) {
      return countComparison;
    }

    return topicOrder.indexOf(left) - topicOrder.indexOf(right);
  });
  const selectedTopic = sortedCandidates[0];

  if (!selectedTopic) {
    throw new Error("At least one topic candidate is required.");
  }

  return selectedTopic;
}

function chooseBestRoom(rooms: RoomAssignment[]): RoomAssignment {
  const sortedRooms = [...rooms].sort((left, right) => {
    const participantComparison =
      getRoomParticipantCount(left) - getRoomParticipantCount(right);

    if (participantComparison !== 0) {
      return participantComparison;
    }

    const groupComparison =
      left.partnerGroups.length - right.partnerGroups.length;

    if (groupComparison !== 0) {
      return groupComparison;
    }

    return getRoomNumber(left.roomName) - getRoomNumber(right.roomName);
  });
  const room = sortedRooms[0];

  if (!room) {
    throw new Error("At least one room is required.");
  }

  return room;
}

function getRecommendedRoomCount(
  partnerGroupAssignments: PartnerGroupAssignment[],
  topics: WeeklyTopic[]
): number {
  if (partnerGroupAssignments.length === 0) {
    return 1;
  }

  const countsByTopic = topics.map(
    (topic) =>
      partnerGroupAssignments.filter(
        (assignment) => assignment.assignedTopicId === topic.id
      ).length
  );

  return Math.max(1, ...countsByTopic);
}

function createRoomNames(roomCount: number): RoomName[] {
  return Array.from({ length: roomCount }, (_, index) =>
    createRoomName(index + 1)
  );
}

function getRoomNumber(roomName: RoomName): number {
  return Number(roomName.replace("Room", ""));
}

function findParticipantForVote(
  vote: Vote,
  byDiscordUserId: Map<string, Participant>,
  byDiscordUsername: Map<string, Participant>
): Participant | undefined {
  const userId = normalizeIdentifier(vote.discordUserId);

  if (userId) {
    const participant = byDiscordUserId.get(userId);

    if (participant) {
      return participant;
    }
  }

  const username = normalizeIdentifier(vote.discordUsername);

  if (username) {
    return byDiscordUsername.get(username);
  }

  return undefined;
}

function incrementTopicCount(
  topicCounts: Map<string, number>,
  topicId: string
): void {
  topicCounts.set(topicId, (topicCounts.get(topicId) ?? 0) + 1);
}

function roomHasTopic(room: RoomAssignment, topicId: string): boolean {
  return room.partnerGroups.some(
    (assignment) => assignment.assignedTopicId === topicId
  );
}

function getRoomParticipantCount(room: RoomAssignment): number {
  return room.partnerGroups.reduce(
    (total, assignment) => total + assignment.participants.length,
    0
  );
}

function compareParticipants(left: Participant, right: Participant): number {
  const lastNameComparison = left.lastName.localeCompare(right.lastName);

  if (lastNameComparison !== 0) {
    return lastNameComparison;
  }

  return left.firstName.localeCompare(right.firstName);
}

function comparePartnerGroupAssignments(
  left: PartnerGroupAssignment,
  right: PartnerGroupAssignment
): number {
  return left.partnerGroup.localeCompare(right.partnerGroup);
}

function formatParticipantName(participant: Participant): string {
  return `${participant.firstName} ${participant.lastName}`.trim();
}

function normalizeEmail(email: string): string {
  return email.trim().toLowerCase();
}

function normalizeIdentifier(identifier: string | undefined): string {
  return identifier?.trim().toLowerCase() ?? "";
}

function isLikelyEmail(email: string): boolean {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

import {
  mapVotesToParticipants,
  type Participant,
  type ValidationIssue,
  type Vote,
  type WeeklyTopic
} from "@web3-talents/core";

export type DiscordMessageLinkParts = {
  guildId: string;
  channelId: string;
  messageId: string;
};

export type DiscordPollPreviewRequest = {
  participants?: Participant[];
  pollMessageLink: string;
};

export type DiscordPollPreview = {
  message: {
    channelId: string;
    guildId: string;
    messageId: string;
  };
  topics: WeeklyTopic[];
  votes: Vote[];
  warnings: ValidationIssue[];
};

type FetchLike = typeof fetch;

type DiscordApiClientOptions = {
  apiBaseUrl?: string;
  botToken: string;
  fetchImpl?: FetchLike;
  requestTimeoutMs?: number;
};

type DiscordMessage = {
  id: string;
  channel_id: string;
  guild_id?: string;
  message_reference?: {
    channel_id?: string;
    message_id?: string;
  };
  poll?: DiscordPoll;
  type?: number;
};

type DiscordPoll = {
  allow_multiselect?: boolean;
  answers: DiscordPollAnswer[];
  question?: {
    text?: string;
  };
};

type DiscordPollAnswer = {
  answer_id: number | string;
  poll_media?: {
    text?: string;
  };
};

type DiscordUser = {
  discriminator?: string;
  global_name?: string | null;
  id: string;
  username: string;
};

type DiscordAnswerVotersResponse = {
  users?: DiscordUser[];
};

export class DiscordApiClient {
  private readonly apiBaseUrl: string;
  private readonly botToken: string;
  private readonly fetchImpl: FetchLike;
  private readonly requestTimeoutMs: number;

  constructor(options: DiscordApiClientOptions) {
    if (!options.botToken.trim()) {
      throw new Error("DISCORD_BOT_TOKEN is required to preview Discord polls.");
    }

    this.apiBaseUrl = options.apiBaseUrl ?? "https://discord.com/api/v10";
    this.botToken = options.botToken;
    this.fetchImpl = options.fetchImpl ?? fetch;
    this.requestTimeoutMs = options.requestTimeoutMs ?? 7_000;
  }

  async getMessage(channelId: string, messageId: string): Promise<DiscordMessage> {
    return this.getJson<DiscordMessage>(
      `/channels/${channelId}/messages/${messageId}`
    );
  }

  async getAnswerVoters(
    channelId: string,
    messageId: string,
    answerId: string
  ): Promise<DiscordUser[]> {
    const users: DiscordUser[] = [];
    let after: string | undefined;

    while (true) {
      const searchParams = new URLSearchParams({
        limit: "100"
      });

      if (after) {
        searchParams.set("after", after);
      }

      const response = await this.getJson<DiscordAnswerVotersResponse>(
        `/channels/${channelId}/polls/${messageId}/answers/${encodeURIComponent(
          answerId
        )}?${searchParams.toString()}`
      );
      const pageUsers = response.users ?? [];
      users.push(...pageUsers);

      if (pageUsers.length < 100) {
        return users;
      }

      after = pageUsers[pageUsers.length - 1]?.id;

      if (!after) {
        return users;
      }
    }
  }

  private async getJson<T>(path: string): Promise<T> {
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), this.requestTimeoutMs);

    try {
      const response = await this.fetchImpl(`${this.apiBaseUrl}${path}`, {
        headers: {
          authorization: `Bot ${this.botToken}`
        },
        signal: controller.signal
      });

      if (!response.ok) {
        throw new Error(
          `Discord API request failed with ${response.status} ${response.statusText}.`
        );
      }

      return response.json() as Promise<T>;
    } catch (error) {
      if (error instanceof Error && error.name === "AbortError") {
        throw new Error(
          "Discord API request timed out. Try again, and confirm the bot token and channel permissions are correct."
        );
      }

      throw error;
    } finally {
      clearTimeout(timeout);
    }
  }
}

export function parseDiscordMessageLink(link: string): DiscordMessageLinkParts {
  let url: URL;

  try {
    url = new URL(link);
  } catch {
    throw new Error("Discord poll message link must be a valid URL.");
  }

  const allowedHosts = new Set([
    "discord.com",
    "www.discord.com",
    "canary.discord.com",
    "ptb.discord.com"
  ]);

  if (!allowedHosts.has(url.hostname)) {
    throw new Error("Discord poll message link must use a discord.com host.");
  }

  const [channelsSegment, guildId, channelId, messageId] = url.pathname
    .split("/")
    .filter(Boolean);

  if (channelsSegment !== "channels" || !guildId || !channelId || !messageId) {
    throw new Error(
      "Discord poll message link must look like https://discord.com/channels/{guildId}/{channelId}/{messageId}."
    );
  }

  for (const [field, value] of [
    ["guildId", guildId],
    ["channelId", channelId],
    ["messageId", messageId]
  ] as const) {
    if (!/^\d+$/.test(value)) {
      throw new Error(`Discord ${field} must be a numeric ID.`);
    }
  }

  return {
    channelId,
    guildId,
    messageId
  };
}

export async function previewDiscordPoll(
  request: DiscordPollPreviewRequest,
  client = new DiscordApiClient({
    botToken: process.env.DISCORD_BOT_TOKEN ?? ""
  })
): Promise<DiscordPollPreview> {
  const linkParts = parseDiscordMessageLink(request.pollMessageLink);
  const initialMessage = await client.getMessage(
    linkParts.channelId,
    linkParts.messageId
  );
  const message = await resolvePollMessage(client, initialMessage);
  const topics = extractWeeklyTopics(message);
  const warnings: ValidationIssue[] = [];

  if (message.channel_id !== linkParts.channelId || message.id !== linkParts.messageId) {
    warnings.push({
      code: "discord_message_id_mismatch",
      message: "Discord returned a message with different IDs than the pasted link."
    });
  }

  if (message.poll?.allow_multiselect) {
    warnings.push({
      code: "discord_poll_multiselect",
      message:
        "Discord poll allows multiple answers. Duplicate participant votes will be ignored by the assignment engine."
    });
  }

  const votes = (
    await Promise.all(
      topics.map(async (topic) => {
        const answerId = topic.discordPollAnswerId;

        if (!answerId) {
          return [];
        }

        const voters = await client.getAnswerVoters(
          linkParts.channelId,
          linkParts.messageId,
          answerId
        );

        return voters.map((user) => ({
          discordUserId: user.id,
          discordUsername: formatDiscordUsername(user),
          topicId: topic.id
        }));
      })
    )
  ).flat();

  if (request.participants) {
    const voteMapping = mapVotesToParticipants(request.participants, votes);
    warnings.push(...voteMapping.warnings);

    for (const participant of request.participants) {
      const email = participant.email.trim().toLowerCase();

      if (!voteMapping.participantEmailsWithVotes.has(email)) {
        warnings.push({
          code: "participant_without_vote",
          email: participant.email,
          message: "Imported participant has no matched Discord poll vote."
        });
      }
    }
  }

  return {
    message: {
      channelId: message.channel_id,
      guildId: linkParts.guildId,
      messageId: message.id
    },
    topics,
    votes,
    warnings
  };
}

export function extractWeeklyTopics(message: DiscordMessage): WeeklyTopic[] {
  const answers = message.poll?.answers;

  if (!answers) {
    throw new Error(
      "Discord message does not expose a poll. Confirm the link points to the original native Discord poll message and enable the Message Content privileged intent for the bot in the Discord Developer Portal."
    );
  }

  if (answers.length !== 4) {
    throw new Error("Discord poll must contain exactly four answers for the MVP workflow.");
  }

  return answers.map((answer) => {
    const answerId = String(answer.answer_id);
    const label = answer.poll_media?.text?.trim();

    if (!label) {
      throw new Error(`Discord poll answer ${answerId} is missing text.`);
    }

    return {
      discordPollAnswerId: answerId,
      id: `discord-answer-${answerId}`,
      label
    };
  });
}

async function resolvePollMessage(
  client: DiscordApiClient,
  message: DiscordMessage
): Promise<DiscordMessage> {
  if (message.poll) {
    return message;
  }

  const referencedMessageId = message.message_reference?.message_id;
  const referencedChannelId = message.message_reference?.channel_id ?? message.channel_id;

  if (message.type === 46 && referencedMessageId) {
    return client.getMessage(referencedChannelId, referencedMessageId);
  }

  return message;
}

function formatDiscordUsername(user: DiscordUser): string {
  if (user.discriminator && user.discriminator !== "0") {
    return `${user.username}#${user.discriminator}`;
  }

  return user.username;
}

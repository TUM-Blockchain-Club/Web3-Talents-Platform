import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  DiscordApiClient,
  extractWeeklyTopics,
  parseDiscordMessageLink,
  previewDiscordPoll
} from "./discord-poll.js";

describe("parseDiscordMessageLink", () => {
  it("extracts IDs from a Discord message link", () => {
    const parts = parseDiscordMessageLink(
      "https://discord.com/channels/111/222/333"
    );

    assert.deepEqual(parts, {
      channelId: "222",
      guildId: "111",
      messageId: "333"
    });
  });

  it("accepts canary Discord links with query params", () => {
    const parts = parseDiscordMessageLink(
      "https://canary.discord.com/channels/111/222/333?jump=true"
    );

    assert.equal(parts.guildId, "111");
    assert.equal(parts.channelId, "222");
    assert.equal(parts.messageId, "333");
  });

  it("rejects malformed links", () => {
    assert.throws(
      () => parseDiscordMessageLink("https://example.com/channels/111/222/333"),
      /discord.com host/
    );
    assert.throws(
      () => parseDiscordMessageLink("https://discord.com/channels/111/222/nope"),
      /messageId must be a numeric ID/
    );
  });
});

describe("extractWeeklyTopics", () => {
  it("maps Discord poll answers to weekly topics", () => {
    const topics = extractWeeklyTopics({
      channel_id: "222",
      id: "333",
      poll: {
        answers: [
          answer(1, "Topic A"),
          answer(2, "Topic B"),
          answer(3, "Topic C"),
          answer(4, "Topic D")
        ]
      }
    });

    assert.deepEqual(topics, [
      {
        discordPollAnswerId: "1",
        id: "discord-answer-1",
        label: "Topic A"
      },
      {
        discordPollAnswerId: "2",
        id: "discord-answer-2",
        label: "Topic B"
      },
      {
        discordPollAnswerId: "3",
        id: "discord-answer-3",
        label: "Topic C"
      },
      {
        discordPollAnswerId: "4",
        id: "discord-answer-4",
        label: "Topic D"
      }
    ]);
  });

  it("requires exactly four poll answers", () => {
    assert.throws(
      () =>
        extractWeeklyTopics({
          channel_id: "222",
          id: "333",
          poll: {
            answers: [answer(1, "Topic A")]
          }
        }),
      /exactly four answers/
    );
  });

  it("explains when Discord omits the poll field", () => {
    assert.throws(
      () =>
        extractWeeklyTopics({
          channel_id: "222",
          id: "333"
        }),
      /Message Content privileged intent/
    );
  });
});

describe("previewDiscordPoll", () => {
  it("fetches a poll message and answer voters", async () => {
    const requestedPaths: string[] = [];
    const client = new DiscordApiClient({
      botToken: "token",
      fetchImpl: async (input) => {
        const url = new URL(String(input));
        requestedPaths.push(`${url.pathname}${url.search}`);

        if (url.pathname === "/api/v10/channels/222/messages/333") {
          return jsonResponse({
            channel_id: "222",
            id: "333",
            poll: {
              answers: [
                answer(1, "Topic A"),
                answer(2, "Topic B"),
                answer(3, "Topic C"),
                answer(4, "Topic D")
              ]
            }
          });
        }

        if (url.pathname.endsWith("/answers/1")) {
          return jsonResponse({
            users: [
              {
                discriminator: "0",
                id: "100",
                username: "alice"
              }
            ]
          });
        }

        if (url.pathname.endsWith("/answers/2")) {
          return jsonResponse({
            users: [
              {
                discriminator: "1234",
                id: "200",
                username: "bob"
              }
            ]
          });
        }

        return jsonResponse({
          users: []
        });
      }
    });

    const preview = await previewDiscordPoll(
      {
        participants: [
          {
            discordUserId: "100",
            discordUsername: "alice",
            email: "alice@example.com",
            firstName: "Alice",
            lastName: "Smith",
            partnerGroup: "Group A"
          },
          {
            discordUserId: "300",
            discordUsername: "charlie",
            email: "charlie@example.com",
            firstName: "Charlie",
            lastName: "Stone",
            partnerGroup: "Group A"
          }
        ],
        pollMessageLink: "https://discord.com/channels/111/222/333"
      },
      client
    );

    assert.equal(preview.topics.length, 4);
    assert.deepEqual(preview.votes, [
      {
        discordUserId: "100",
        discordUsername: "alice",
        topicId: "discord-answer-1"
      },
      {
        discordUserId: "200",
        discordUsername: "bob#1234",
        topicId: "discord-answer-2"
      }
    ]);
    assert.equal(
      preview.warnings.some((warning) => warning.code === "unmatched_vote"),
      true
    );
    assert.equal(
      preview.warnings.some(
        (warning) =>
          warning.code === "participant_without_vote" &&
          warning.email === "charlie@example.com"
      ),
      true
    );
    assert.equal(
      requestedPaths.includes("/api/v10/channels/222/polls/333/answers/1?limit=100"),
      true
    );
  });

  it("follows Discord poll result messages to the original poll message", async () => {
    const client = new DiscordApiClient({
      botToken: "token",
      fetchImpl: async (input) => {
        const url = new URL(String(input));

        if (url.pathname === "/api/v10/channels/222/messages/333") {
          return jsonResponse({
            channel_id: "222",
            id: "333",
            message_reference: {
              channel_id: "222",
              message_id: "444"
            },
            type: 46
          });
        }

        if (url.pathname === "/api/v10/channels/222/messages/444") {
          return jsonResponse({
            channel_id: "222",
            id: "444",
            poll: {
              answers: [
                answer(1, "Topic A"),
                answer(2, "Topic B"),
                answer(3, "Topic C"),
                answer(4, "Topic D")
              ]
            }
          });
        }

        return jsonResponse({
          users: []
        });
      }
    });

    const preview = await previewDiscordPoll(
      {
        pollMessageLink: "https://discord.com/channels/111/222/333"
      },
      client
    );

    assert.equal(preview.message.messageId, "444");
    assert.equal(preview.topics.length, 4);
  });

  it("pages through all answer voters", async () => {
    let firstAnswerCalls = 0;
    const client = new DiscordApiClient({
      botToken: "token",
      fetchImpl: async (input) => {
        const url = new URL(String(input));

        if (url.pathname === "/api/v10/channels/222/messages/333") {
          return jsonResponse({
            channel_id: "222",
            id: "333",
            poll: {
              answers: [
                answer(1, "Topic A"),
                answer(2, "Topic B"),
                answer(3, "Topic C"),
                answer(4, "Topic D")
              ]
            }
          });
        }

        if (url.pathname.endsWith("/answers/1")) {
          firstAnswerCalls += 1;

          if (firstAnswerCalls === 1) {
            return jsonResponse({
              users: Array.from({ length: 100 }, (_, index) => ({
                id: String(index + 1),
                username: `user-${index + 1}`
              }))
            });
          }
        }

        return jsonResponse({
          users: []
        });
      }
    });

    const preview = await previewDiscordPoll(
      {
        pollMessageLink: "https://discord.com/channels/111/222/333"
      },
      client
    );

    assert.equal(preview.votes.length, 100);
    assert.equal(firstAnswerCalls, 2);
  });
});

function answer(answerId: number, text: string) {
  return {
    answer_id: answerId,
    poll_media: {
      text
    }
  };
}

function jsonResponse(body: unknown): Response {
  return {
    json: async () => body,
    ok: true,
    status: 200,
    statusText: "OK"
  } as Response;
}

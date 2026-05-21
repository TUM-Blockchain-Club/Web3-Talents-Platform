# Phase 3: Discord Poll Sync

Status: complete.

## Completed

- Discord message link parser for `discord.com`, `canary.discord.com`, and `ptb.discord.com` links.
- Backend-only Discord REST client using `DISCORD_BOT_TOKEN`.
- Discord poll message fetch from:
  - `GET /channels/{channel.id}/messages/{message.id}`
- Discord answer voter fetch from:
  - `GET /channels/{channel.id}/polls/{message.id}/answers/{answer_id}`
- Pagination for answer voters using `limit=100` and `after`.
- Poll answer mapping into `WeeklyTopic[]`.
- Voter mapping into `Vote[]`.
- Optional participant-aware warnings:
  - unmatched Discord voters
  - imported participants without a matched vote
- Multiselect poll warning.
- `POST /api/discord/poll/preview` endpoint wired to the implementation.
- Local `.env` loading for the API server.
- Clear error guidance for missing Message Content privileged intent.
- Poll-result message links resolved to the original poll message when Discord includes a reference.
- Mocked backend tests for parsing, topic extraction, voter fetching, pagination, and participant warnings.
- Real Discord poll validation against the target server.

## Endpoint

```http
POST /api/discord/poll/preview
Content-Type: application/json
```

Request:

```json
{
  "pollMessageLink": "https://discord.com/channels/111/222/333",
  "participants": []
}
```

`participants` is optional. When present, the endpoint returns participant matching warnings.

Response:

```json
{
  "message": {
    "guildId": "111",
    "channelId": "222",
    "messageId": "333"
  },
  "topics": [
    {
      "id": "discord-answer-1",
      "label": "Topic A",
      "discordPollAnswerId": "1"
    }
  ],
  "votes": [
    {
      "discordUserId": "100",
      "discordUsername": "alice",
      "topicId": "discord-answer-1"
    }
  ],
  "warnings": []
}
```

## Validation

- `npm run typecheck`
- `npm test`
- `npm run build`
- Real Discord poll endpoint test returned four topics, voter records for all answers, and no warnings.

## Notes

- The bot must be invited to the target server.
- The bot needs channel permissions:
  - `VIEW_CHANNEL`
  - `READ_MESSAGE_HISTORY`
- The bot application must have Message Content privileged intent enabled, otherwise Discord omits `poll` from fetched message objects.
- A manual vote import fallback can still be added later if Discord reliability becomes a real operational risk.

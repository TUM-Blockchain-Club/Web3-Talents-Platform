import cors from "@fastify/cors";
import multipart from "@fastify/multipart";
import {
  generateAssignments,
  type AssignmentGenerationInput,
  type AssignmentGenerationResult
} from "@web3-talents/core";
import Fastify, { type FastifyInstance } from "fastify";
import { previewDiscordPoll, type DiscordPollPreviewRequest } from "./discord-poll.js";
import { loadLocalEnv } from "./env.js";
import { createInternalExcelBuffer, createZoomCsvBuffer } from "./file-export.js";
import { previewParticipantImport } from "./file-import.js";
import { getErrorMessage, sendBuffer } from "./http.js";

loadLocalEnv();

type ImportPreviewRequest = {
  contentBase64: string;
  filename: string;
};

export const apiBodyLimit = 15 * 1024 * 1024;

export async function buildServer() {
  const server = Fastify({
    bodyLimit: apiBodyLimit,
    logger: true
  });

  await registerRoutes(server);

  return server;
}

export async function registerRoutes(server: FastifyInstance) {
  const corsOrigins = parseCorsOrigins(
    process.env.CORS_ORIGIN ?? "http://localhost:3000"
  );

  await server.register(cors, {
    origin: (origin, callback) => {
      if (!origin || corsOrigins.has(origin)) {
        callback(null, true);
        return;
      }

      callback(new Error(`Origin ${origin} is not allowed by CORS.`), false);
    }
  });

  await server.register(multipart, {
    limits: {
      fileSize: 10 * 1024 * 1024,
      files: 1
    }
  });

  server.get("/health", async () => {
    return {
      ok: true,
      service: "web3-talents-api"
    };
  });

  server.get("/", async () => {
    return {
      ok: true,
      service: "web3-talents-api"
    };
  });

  server.post<{ Body: ImportPreviewRequest }>(
    "/api/import/preview",
    async (request, reply) => {
      const { contentBase64, filename } = request.body ?? {};

      if (!contentBase64 || !filename) {
        return reply.code(400).send({
          error: "Upload a file with filename and contentBase64."
        });
      }

      if (contentBase64.length > 14 * 1024 * 1024) {
        return reply.code(413).send({
          error: "Participant file must be 10 MB or smaller."
        });
      }

      try {
        const buffer = Buffer.from(contentBase64, "base64");
        return previewParticipantImport(buffer, filename);
      } catch (error) {
        return reply.code(400).send({
          error: getErrorMessage(error)
        });
      }
    }
  );

  server.post("/api/import/preview-multipart", async (request, reply) => {
    const file = await request.file();

    if (!file) {
      return reply.code(400).send({
        error: "Upload a file in the multipart field named file."
      });
    }

    try {
      const buffer = await file.toBuffer();
      return previewParticipantImport(buffer, file.filename);
    } catch (error) {
      return reply.code(400).send({
        error: getErrorMessage(error)
      });
    }
  });

  server.post<{ Body: DiscordPollPreviewRequest }>(
    "/api/discord/poll/preview",
    async (request, reply) => {
      try {
        return await previewDiscordPoll(request.body);
      } catch (error) {
        return reply.code(400).send({
          error: getErrorMessage(error)
        });
      }
    }
  );

  server.post<{ Body: AssignmentGenerationInput }>(
    "/api/assignments/generate",
    async (request, reply) => {
      try {
        return generateAssignments(request.body);
      } catch (error) {
        return reply.code(400).send({
          error: getErrorMessage(error)
        });
      }
    }
  );

  server.post<{ Body: AssignmentGenerationResult }>(
    "/api/exports/internal-excel",
    async (request, reply) => {
      try {
        const buffer = await createInternalExcelBuffer(request.body);
        return sendBuffer(
          reply,
          buffer,
          "internal-room-assignments.xlsx",
          "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
        );
      } catch (error) {
        return reply.code(400).send({
          error: getErrorMessage(error)
        });
      }
    }
  );

  server.post<{ Body: AssignmentGenerationResult }>(
    "/api/exports/zoom-csv",
    async (request, reply) => {
      try {
        const buffer = createZoomCsvBuffer(request.body);
        return sendBuffer(reply, buffer, "zoom-breakout-rooms.csv", "text/csv");
      } catch (error) {
        return reply.code(400).send({
          error: getErrorMessage(error)
        });
      }
    }
  );

  server.post("/api/assignments/override", async () => {
    return {
      message: "Assignment override endpoint will be wired with frontend state."
    };
  });
}

function parseCorsOrigins(value: string): Set<string> {
  return new Set(
    value
      .split(",")
      .map((origin) => normalizeOrigin(origin))
      .filter((origin): origin is string => Boolean(origin))
  );
}

function normalizeOrigin(value: string): string | null {
  const trimmedValue = value.trim().replace(/\/+$/, "");

  if (!trimmedValue) {
    return null;
  }

  if (/^https?:\/\//i.test(trimmedValue)) {
    return trimmedValue;
  }

  return `https://${trimmedValue}`;
}

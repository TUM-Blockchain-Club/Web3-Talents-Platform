import cors from "@fastify/cors";
import multipart from "@fastify/multipart";
import { generateAssignments, type AssignmentGenerationInput, type AssignmentGenerationResult } from "@web3-talents/core";
import Fastify from "fastify";
import { createInternalExcelBuffer, createZoomCsvBuffer } from "./file-export.js";
import { previewParticipantImport } from "./file-import.js";
import { getErrorMessage, sendBuffer } from "./http.js";

const port = Number(process.env.PORT ?? 4000);
const corsOrigin = process.env.CORS_ORIGIN ?? "http://localhost:3000";

const server = Fastify({
  logger: true
});

await server.register(cors, {
  origin: corsOrigin
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

server.post("/api/import/preview", async (request, reply) => {
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

server.post("/api/discord/poll/preview", async () => {
  return {
    message: "Discord poll preview endpoint scaffolded"
  };
});

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

try {
  await server.listen({
    host: "0.0.0.0",
    port
  });
} catch (error) {
  server.log.error(error);
  process.exit(1);
}

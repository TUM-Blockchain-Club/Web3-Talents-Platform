import type { FastifyReply } from "fastify";

export function sendBuffer(
  reply: FastifyReply,
  buffer: Buffer,
  filename: string,
  contentType: string
) {
  return reply
    .header("content-type", contentType)
    .header("content-disposition", `attachment; filename="${filename}"`)
    .send(buffer);
}

export function getErrorMessage(error: unknown): string {
  return error instanceof Error ? error.message : "Unexpected server error.";
}

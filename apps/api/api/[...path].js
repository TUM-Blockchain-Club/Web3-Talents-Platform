import { buildServer } from "../dist/app.js";

const server = await buildServer();
await server.ready();

export default function handler(request, response) {
  server.server.emit("request", request, response);
}

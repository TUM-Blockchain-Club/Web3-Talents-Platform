import Fastify from "fastify";
import { apiBodyLimit, registerRoutes } from "./create-server.js";

const port = Number(process.env.PORT ?? 3000);
const server = Fastify({
  bodyLimit: apiBodyLimit,
  logger: true
});

await registerRoutes(server);

server.listen({ port });

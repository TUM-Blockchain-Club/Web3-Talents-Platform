let serverPromise;

async function getServer() {
  serverPromise ??= import("../dist/create-server.js").then(
    async ({ buildServer }) => {
      const server = await buildServer();
      await server.ready();
      return server;
    }
  );

  return serverPromise;
}

export default async function handler(request, response) {
  try {
    const server = await getServer();
    server.server.emit("request", request, response);
  } catch (error) {
    console.error("API function failed to start.", error);
    response.statusCode = 500;
    response.setHeader("access-control-allow-origin", request.headers.origin ?? "*");
    response.setHeader("vary", "origin");
    response.setHeader("content-type", "application/json");
    response.end(
      JSON.stringify({
        error: error instanceof Error ? error.message : "API function failed to start."
      })
    );
  }
}

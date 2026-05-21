import { NextResponse, type NextRequest } from "next/server";

export function middleware(request: NextRequest) {
  const adminPassword =
    process.env.ADMIN_PASSWORD ??
    (process.env.NODE_ENV === "production" ? "" : "change-me");

  if (!adminPassword) {
    return new NextResponse("ADMIN_PASSWORD is required.", {
      status: 500
    });
  }

  const authorization = request.headers.get("authorization");
  const expected = `Basic ${btoa(`admin:${adminPassword}`)}`;

  if (authorization === expected) {
    return NextResponse.next();
  }

  return new NextResponse("Authentication required.", {
    headers: {
      "WWW-Authenticate": 'Basic realm="Web3 Talents Admin"'
    },
    status: 401
  });
}

export const config = {
  matcher: ["/((?!_next/static|_next/image|favicon.ico).*)"]
};

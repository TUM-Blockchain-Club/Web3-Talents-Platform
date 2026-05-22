import { AdminWorkflow } from "./AdminWorkflow";

export default function HomePage() {
  return (
    <AdminWorkflow
      apiBaseUrl={normalizeApiBaseUrl(
        process.env.NEXT_PUBLIC_API_BASE_URL ?? "http://localhost:4000"
      )}
    />
  );
}

function normalizeApiBaseUrl(value: string): string {
  const trimmedValue = value.trim().replace(/\/+$/, "");

  if (/^https?:\/\//i.test(trimmedValue)) {
    return trimmedValue;
  }

  return `https://${trimmedValue}`;
}

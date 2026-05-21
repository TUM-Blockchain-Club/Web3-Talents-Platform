import { AdminWorkflow } from "./AdminWorkflow";

export default function HomePage() {
  return (
    <AdminWorkflow
      apiBaseUrl={process.env.NEXT_PUBLIC_API_BASE_URL ?? "http://localhost:4000"}
    />
  );
}

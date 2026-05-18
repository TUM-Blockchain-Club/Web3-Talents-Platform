const workflowSteps = [
  "Upload participant Excel",
  "Paste Discord poll link",
  "Generate dynamic rooms",
  "Review and adjust groups",
  "Download Excel and Zoom CSV"
];

export default function HomePage() {
  return (
    <main className="mx-auto flex min-h-screen max-w-5xl flex-col gap-10 px-6 py-10">
      <section className="flex flex-col gap-3">
        <p className="text-sm font-medium uppercase tracking-wide text-slate-500">
          Internal Admin
        </p>
        <h1 className="text-3xl font-semibold text-slate-950">
          Web3 Talents weekly room builder
        </h1>
        <p className="max-w-2xl text-base leading-7 text-slate-600">
          Phase 2 backend import, assignment, and export support is ready. The
          next phase connects Discord poll sync.
        </p>
      </section>

      <section className="grid gap-3">
        {workflowSteps.map((step, index) => (
          <div
            className="flex items-center gap-4 rounded-md border border-slate-200 bg-white p-4"
            key={step}
          >
            <span className="flex h-8 w-8 items-center justify-center rounded-full bg-slate-950 text-sm font-semibold text-white">
              {index + 1}
            </span>
            <span className="text-sm font-medium text-slate-800">{step}</span>
          </div>
        ))}
      </section>
    </main>
  );
}

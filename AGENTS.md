# Repository Guide

- Moodle is the primary application; the TypeScript apps under apps/ and shared packages/core are retained as a reference implementation.
- Use pnpm workspaces for TypeScript dependencies and the root build, typecheck and test scripts. Build core before dependent applications.
- Keep Moodle tooling, runtime data and migrations separate from TypeScript dependency maintenance.
- Verify dependency changes without live credentials or database, email and blockchain mutations.

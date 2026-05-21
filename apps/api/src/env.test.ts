import assert from "node:assert/strict";
import { mkdtempSync, mkdirSync, writeFileSync } from "node:fs";
import { tmpdir } from "node:os";
import { join } from "node:path";
import { afterEach, describe, it } from "node:test";
import { loadLocalEnv } from "./env.js";

const originalCwd = process.cwd();
const keysToRestore = ["TEST_ENV_LOADER_TOKEN", "TEST_ENV_LOADER_EXISTING"];
const originalValues = new Map(keysToRestore.map((key) => [key, process.env[key]]));

afterEach(() => {
  process.chdir(originalCwd);

  for (const key of keysToRestore) {
    const originalValue = originalValues.get(key);

    if (originalValue === undefined) {
      delete process.env[key];
    } else {
      process.env[key] = originalValue;
    }
  }
});

describe("loadLocalEnv", () => {
  it("loads env values from a parent directory without overriding existing env", () => {
    const root = mkdtempSync(join(tmpdir(), "web3-talents-env-"));
    const nested = join(root, "apps", "api");

    mkdirSync(nested, {
      recursive: true
    });
    writeFileSync(
      join(root, ".env"),
      [
        "TEST_ENV_LOADER_TOKEN=\"loaded-token\"",
        "TEST_ENV_LOADER_EXISTING=from-file"
      ].join("\n")
    );

    process.env.TEST_ENV_LOADER_EXISTING = "from-process";
    process.chdir(nested);

    loadLocalEnv();

    assert.equal(process.env.TEST_ENV_LOADER_TOKEN, "loaded-token");
    assert.equal(process.env.TEST_ENV_LOADER_EXISTING, "from-process");
  });
});

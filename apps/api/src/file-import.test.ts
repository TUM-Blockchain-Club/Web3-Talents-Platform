import assert from "node:assert/strict";
import { describe, it } from "node:test";
import writeXlsxFile, { type SheetData } from "write-excel-file/node";
import { previewParticipantImport } from "./file-import.js";

describe("previewParticipantImport", () => {
  it("parses and validates participant CSV files", async () => {
    const csv = Buffer.from(
      [
        "Name,Email,Discord username,Partner group",
        "Alice Smith,alice@example.com,alice,1",
        "Bob Jones,bob@example.com,bob,1"
      ].join("\n")
    );

    const preview = await previewParticipantImport(csv, "participants.csv");

    assert.equal(preview.rowCount, 2);
    assert.equal(preview.validation.valid, true);
    assert.equal(preview.participants[0]?.firstName, "Alice");
    assert.equal(preview.participants[0]?.lastName, "Smith");
    assert.equal(preview.participants[0]?.email, "alice@example.com");
    assert.equal(preview.partnerGroups[0]?.partnerGroup, "1");
    assert.equal(preview.partnerGroups[0]?.participantCount, 2);
  });

  it("parses and validates participant Excel files", async () => {
    const sheetData: SheetData = [
      ["Name", "Email", "Discord username", "Partner group"],
      ["Alice Smith", "alice@example.com", "alice", "1"],
      ["Bob Jones", "bob@example.com", "bob", "1"]
    ];
    const excelBuffer = await writeXlsxFile(sheetData).toBuffer();

    const preview = await previewParticipantImport(
      excelBuffer,
      "participants.xlsx"
    );

    assert.equal(preview.rowCount, 2);
    assert.equal(preview.validation.valid, true);
    assert.equal(preview.participants[1]?.discordUsername, "bob");
  });

  it("reports validation errors and warnings before import confirmation", async () => {
    const csv = Buffer.from(
      [
        "Name,Email,Discord username,Partner group",
        "Alice Smith,duplicate@example.com,alice,1",
        "Bob Jones,duplicate@example.com,bob,1",
        "Missing,not-an-email,,2"
      ].join("\n")
    );

    const preview = await previewParticipantImport(csv, "participants.csv");

    assert.equal(preview.rowCount, 3);
    assert.equal(preview.validation.valid, false);
    assert.equal(
      preview.validation.errors.some((error) => error.code === "duplicate_email"),
      true
    );
    assert.equal(
      preview.validation.errors.some((error) => error.code === "invalid_email"),
      true
    );
    assert.equal(
      preview.validation.errors.some((error) => error.code === "missing_last_name"),
      true
    );
    assert.equal(
      preview.validation.warnings.some(
        (warning) => warning.code === "missing_discord_identifier"
      ),
      true
    );
    assert.equal(
      preview.validation.warnings.some(
        (warning) => warning.code === "small_partner_group"
      ),
      true
    );
  });

  it("accepts common participant template header aliases", async () => {
    const csv = Buffer.from(
      [
        "participant name,email address,discord handle,buddy group",
        "Alice Smith,alice@example.com,alice,Group 1",
        "Bob Jones,bob@example.com,bob,group 1"
      ].join("\n")
    );

    const preview = await previewParticipantImport(csv, "participants.csv");

    assert.equal(preview.validation.valid, true);
    assert.equal(preview.participants[0]?.firstName, "Alice");
    assert.equal(preview.participants[0]?.discordUsername, "alice");
    assert.equal(preview.participants[0]?.partnerGroup, "1");
    assert.equal(preview.participants[1]?.partnerGroup, "1");
  });

  it("rejects unsupported file types", async () => {
    await assert.rejects(
      () => previewParticipantImport(Buffer.from("x"), "participants.txt"),
      /Unsupported file type/
    );
  });
});

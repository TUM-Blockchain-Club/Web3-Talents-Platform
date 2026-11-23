# Curriculum worksheet publishing

The PDF source project is `/Users/srinivasjagannath/PycharmProjects/pdf_creator`.
Use its JSON inputs and shared renderer; do not replace participant content with
the legacy default draft in `pdf_Creator.py`.

## Remaining task batch

`topics/topic_02_tasks.json` and `topics/topic_04_tasks.json` through
`topics/topic_10_tasks.json` produce 32 separate group worksheets and five general
worksheets. Topics 1 and 3 and the shared Topic 2 hashing worksheet are preserved.
No general exercise was supplied for Topics 4 and 9.

The shared PDF header uses the blue PNG, Cohort 2026/27, and the topic's two-week
period. Review the content qualifications recorded in the creator's README.
PDFs are educational exercises, not individualized tax or investment advice.

1. Render the JSON inputs with the creator's Python environment.
2. Run its unit tests and `verify_curriculum_batch.py` using the documented PDF
   runtime. Inspect all rendered pages before publishing.
3. Run `package_curriculum_batch.py`. The archive contains a manifest with topic,
   group, filename and content hash plus the 37 PDFs.
4. Copy the archive and tooling into a dedicated temporary directory on the
   Moodle host, extract it and copy the directory into the Moodle container.
5. Run as the web user, without `--apply`, first:

   ```sh
   php publish-curriculum-task-batch.php --courseid=2 --source=/tmp/batch
   ```

6. Review the plan, then repeat with `--apply` and run:

   ```sh
   php verify-curriculum-task-batch.php --courseid=2 --source=/tmp/batch
   ```

The publisher resolves delegated subtopics through parent activity order, checks
course identity and hashes, and refuses independently renamed or conflicting
existing content. It labels groups `1: Name` through `4: Name`. General worksheets
go before the first subtopic, following any existing introductory resources.
Existing grades, enrolments, activities and worksheets are not overwritten.
Layout metadata is backed up under `web3talents-backups` in Moodle's dataroot.
All publishing code disables email. A repeated dry-run verifies instead of
duplicating resources. The verifier also checks availability for an existing
active student without changing that student's account.

Moodle course resources live in its database and file storage; deploying PHP from
Git alone does not recreate uploaded content. Keep the source JSON and generated
batch with the PDF creator, and preserve normal Moodle backups.

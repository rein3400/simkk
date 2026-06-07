import { randomUUID } from "node:crypto";
import { mkdir, writeFile } from "node:fs/promises";
import { join } from "node:path";

const sanitize = (value) => String(value).replace(/[^a-zA-Z0-9._-]/g, "-");

export async function storeClinicalPhoto(storageRoot, recordId, { filename, content }) {
  const safeRecord = sanitize(recordId);
  const safeName = sanitize(filename || "clinical-photo.txt");
  const objectName = `${randomUUID()}-${safeName}`;
  const folder = join(storageRoot, "clinical", safeRecord);
  await mkdir(folder, { recursive: true });
  await writeFile(join(folder, objectName), Buffer.from(content || "", "utf8"));
  return `local://clinical/${safeRecord}/${objectName}`;
}

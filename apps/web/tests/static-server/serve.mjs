// Tiny zero-dependency static file server for SIM-KK HTML previews.
// Used by Playwright config (playwright.previews.config.ts) to serve
// outputs/sim-kk-ui-previews/ over http://127.0.0.1:4174 so relative
// links (e.g. laporan.html -> laporan-arus-kas.html) resolve correctly.
import { createServer } from "node:http";
import { readFile, stat } from "node:fs/promises";
import { extname, join, normalize, resolve, sep } from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = fileURLToPath(new URL(".", import.meta.url));
// Static server lives at apps/web/tests/static-server/serve.mjs.
// Previews live at outputs/sim-kk-ui-previews/ (sibling of apps/).
// __dirname = apps/web/tests/static-server  -> up 4 levels reaches project root.
const ROOT = resolve(__dirname, "..", "..", "..", "..", "outputs", "sim-kk-ui-previews");
const PORT = Number(process.env.STATIC_PREVIEW_PORT || 4174);
const MIME = {
  ".html": "text/html; charset=utf-8",
  ".css": "text/css; charset=utf-8",
  ".js": "application/javascript; charset=utf-8",
  ".json": "application/json; charset=utf-8",
  ".svg": "image/svg+xml",
  ".png": "image/png",
  ".jpg": "image/jpeg",
  ".jpeg": "image/jpeg",
  ".webp": "image/webp",
  ".ico": "image/x-icon",
  ".txt": "text/plain; charset=utf-8",
};

const safeJoin = (rel) => {
  const cleaned = normalize(rel).replace(/^([./\\])+/, "");
  const target = resolve(join(ROOT, cleaned));
  if (!target.startsWith(ROOT + sep) && target !== ROOT) return null;
  return target;
};

const server = createServer(async (req, res) => {
  try {
    const url = new URL(req.url, `http://${req.headers.host}`);
    let pathname = decodeURIComponent(url.pathname);
    if (pathname === "/") pathname = "/login.html";
    const target = safeJoin(pathname);
    if (!target) {
      res.writeHead(403);
      res.end("forbidden");
      return;
    }
    let filePath = target;
    try {
      const s = await stat(filePath);
      if (s.isDirectory()) filePath = join(filePath, "login.html");
    } catch {
      res.writeHead(404, { "content-type": "text/plain" });
      res.end("not found");
      return;
    }
    const data = await readFile(filePath);
    const ct = MIME[extname(filePath).toLowerCase()] || "application/octet-stream";
    res.writeHead(200, { "content-type": ct, "cache-control": "no-store" });
    res.end(data);
  } catch (err) {
    res.writeHead(500, { "content-type": "text/plain" });
    res.end(`server error: ${(err instanceof Error ? err.message : String(err))}`);
  }
});

server.listen(PORT, "127.0.0.1", () => {
  // eslint-disable-next-line no-console
  console.log(`[static-preview] serving ${ROOT} on http://127.0.0.1:${PORT}`);
});

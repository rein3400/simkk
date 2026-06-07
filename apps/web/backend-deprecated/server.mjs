import { createApp } from "./app.mjs";

const port = Number(process.env.SIMKK_API_PORT || 5174);
const app = await createApp();

app.listen(port, "127.0.0.1", () => {
  console.log(`SIM-KK API listening on http://127.0.0.1:${port}`);
});

import { createApp } from "../backend/app.mjs";

let appPromise;

export default async function handler(request, response) {
  appPromise ??= createApp();
  const app = await appPromise;
  return app(request, response);
}

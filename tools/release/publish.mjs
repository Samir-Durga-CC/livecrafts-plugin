/**
 * Publish a built plugin zip and its update manifest to the Hostinger update feed.
 *
 *   node tools/release/publish.mjs --channel staging
 *
 * It expects dist/livecrafts-<version>.zip to exist (tools/build-zip.sh builds it) and uploads two files into
 * the website's public_html:
 *
 *   updates/<channel>/livecrafts-<version>.zip   the plugin
 *   updates/<channel>/livecrafts.json            what WordPress reads to learn there is a new version
 *
 * The zip is uploaded first and the manifest second, so a site that checks mid-publish never sees a manifest
 * pointing at a file that is not there yet. Publishing a version the channel already offers is refused: the
 * version in the plugin header is what identifies a release, so it has to change for every one.
 *
 * Needs HOSTINGER_API_TOKEN (an hPanel API token), HOSTINGER_USERNAME and HOSTINGER_DOMAIN.
 */
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const API = "https://developers.hostinger.com";
const REPO = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../..");
const PUBLIC_BASE = "https://project-in-progress.com/updates";

const arg = (name, fallback = "") => {
  const i = process.argv.indexOf(`--${name}`);
  return i > -1 && process.argv[i + 1] ? process.argv[i + 1] : fallback;
};
const die = (msg) => { console.error(msg); process.exit(1); };

const channel = arg("channel");
if (!["stable", "staging"].includes(channel)) die('--channel must be "stable" or "staging".');

const token = process.env.HOSTINGER_API_TOKEN;
const username = process.env.HOSTINGER_USERNAME;
const domain = process.env.HOSTINGER_DOMAIN;
if (!token || !username || !domain) die("HOSTINGER_API_TOKEN, HOSTINGER_USERNAME and HOSTINGER_DOMAIN are all required.");

/** The plugin header is the single source of truth for what is being shipped. */
function header() {
  const src = fs.readFileSync(path.join(REPO, "livecrafts/livecrafts.php"), "utf8").slice(0, 2000);
  const field = (name) => (src.match(new RegExp(`^\\s*\\*\\s*${name}:\\s*(.+?)\\s*$`, "m")) ?? [])[1] ?? "";
  return { version: field("Version"), requires: field("Requires at least"), requiresPhp: field("Requires PHP") };
}

const { version, requires, requiresPhp } = header();
if (!/^\d+\.\d+\.\d+(-[0-9A-Za-z.-]+)?$/.test(version)) die(`The plugin header's Version ("${version}") is not a version number.`);

const zip = path.join(REPO, "dist", `livecrafts-${version}.zip`);
if (!fs.existsSync(zip)) die(`${path.relative(REPO, zip)} not found - run tools/build-zip.sh first.`);

/** What the channel offers right now, so the same version is never published twice. */
async function published() {
  try {
    const res = await fetch(`${PUBLIC_BASE}/${channel}/livecrafts.json?t=${Date.now()}`, { headers: { Accept: "application/json" } });
    return res.ok ? await res.json() : null;
  } catch { return null; }
}

const current = await published();
if (current?.version === version) {
  die(`The ${channel} channel already offers ${version}. Bump "Version:" in livecrafts/livecrafts.php before publishing again.`);
}

/** One set of upload credentials covers every file in this run. */
async function uploadCredentials() {
  const res = await fetch(`${API}/api/hosting/v1/files/upload-urls`, {
    method: "POST",
    headers: { Authorization: `Bearer ${token}`, "Content-Type": "application/json" },
    body: JSON.stringify({ username, domain }),
  });
  const body = await res.text();
  if (!res.ok) die(`Hostinger refused the upload credentials (HTTP ${res.status}): ${body.slice(0, 300)}`);
  const c = JSON.parse(body);
  const got = { url: c.url ?? c.data?.url, auth: c.auth_key ?? c.data?.auth_key, rest: c.rest_auth_key ?? c.data?.rest_auth_key };
  if (!got.url || !got.auth || !got.rest) die(`Hostinger did not return upload credentials: ${body.slice(0, 300)}`);
  return got;
}

/** TUS: create the upload, then send the bytes. `dest` is relative to public_html. */
async function upload(creds, dest, body) {
  const target = `${creds.url}/${dest.split("/").map(encodeURIComponent).join("/")}?override=true`;
  const headers = { "X-Auth": creds.auth, "X-Auth-Rest": creds.rest, "Tus-Resumable": "1.0.0" };
  const create = await fetch(target, { method: "POST", headers: { ...headers, "Upload-Length": String(body.length), "Upload-Offset": "0" } });
  if (create.status !== 201) die(`Could not start the upload of ${dest} (HTTP ${create.status}): ${(await create.text()).slice(0, 200)}`);
  const send = await fetch(target, { method: "PATCH", headers: { ...headers, "Content-Type": "application/offset+octet-stream", "Upload-Offset": "0" }, body });
  if (send.status !== 204) die(`Could not upload ${dest} (HTTP ${send.status}): ${(await send.text()).slice(0, 200)}`);
  console.log(`  public_html/${dest} (${body.length} bytes)`);
}

const changelog = fs.existsSync(path.join(REPO, "CHANGELOG.md")) ? fs.readFileSync(path.join(REPO, "CHANGELOG.md"), "utf8").slice(0, 8000) : "";
const manifest = {
  name: "Livecrafts",
  slug: "livecrafts",
  version,
  channel,
  download_url: `${PUBLIC_BASE}/${channel}/livecrafts-${version}.zip`,
  homepage: PUBLIC_BASE,
  requires,
  requires_php: requiresPhp,
  tested: process.env.LC_TESTED_WP || "6.8",
  last_updated: new Date().toISOString().replace("T", " ").slice(0, 19),
  ...(changelog ? { sections: { changelog: `<pre>${changelog.replace(/[<>&]/g, (c) => ({ "<": "&lt;", ">": "&gt;", "&": "&amp;" })[c])}</pre>` } } : {}),
};

console.log(`Publishing Livecrafts ${version} to the ${channel} channel${current?.version ? ` (was ${current.version})` : ""}:`);
const creds = await uploadCredentials();
await upload(creds, `updates/${channel}/livecrafts-${version}.zip`, fs.readFileSync(zip));
await upload(creds, `updates/${channel}/livecrafts.json`, Buffer.from(JSON.stringify(manifest, null, 2), "utf8"));
console.log(`Done. Sites on the ${channel} channel will be offered ${version}.`);

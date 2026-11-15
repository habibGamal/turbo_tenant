import { test as base, expect, type Page } from '@playwright/test';
import { spawn, spawnSync, type ChildProcess } from 'node:child_process';
import { randomUUID } from 'node:crypto';
import { mkdtempSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { setTimeout as delay } from 'node:timers/promises';
import { request } from 'node:http';
import { createServer } from 'node:net';

const root = fileURLToPath(new URL('../../../', import.meta.url));
const password = 'ValidPass123!';
async function freePort(): Promise<number> {
  const server = createServer();
  await new Promise<void>((resolve, reject) => { server.once('error', reject); server.listen(0, '127.0.0.1', resolve); });
  const port = (server.address() as { port: number }).port;
  await new Promise<void>((resolve, reject) => server.close(error => error ? reject(error) : resolve()));
  return port;
}

function tenant(action: 'create' | 'cleanup' | 'expire-reset' | 'age-reset' | 'inspect', emails: string[]): string {
  const result = spawnSync('php', ['tests/e2e/auth/tenant-fixture.php'], {
    cwd: root, input: JSON.stringify({ action, emails }), encoding: 'utf8',
  });
  if (result.status !== 0) throw new Error(`Local auth fixture ${action} failed (${result.status}).`);
  return result.stdout;
}

type Server = { mailLog: string; origin: string };
type Auth = { email: string; anotherEmail: () => string; create: (email?: string) => void; inspect: (email?: string) => { verified: boolean; admin: boolean }; expireReset: () => void; ageReset: () => void; password: string; mailLog: string; origin: string };
export const test = base.extend<{ auth: Auth; language: 'en' | 'ar'; server: Server }>({
  server: async ({}, use) => {
    const dir = mkdtempSync(join(tmpdir(), 'turbo-auth-e2e-'));
    const mailLog = join(dir, 'mail.log');
    const serverId = randomUUID();
    const port = await freePort();
    const origin = `http://e2e.localhost:${port}`;
    const child: ChildProcess = spawn('php', ['-S', `127.0.0.1:${port}`, '-t', 'public', 'tests/e2e/auth/router.php'], {
      cwd: root, env: { ...process.env, E2E_AUTH_MAIL_LOG: mailLog, E2E_AUTH_SERVER_ID: serverId, CACHE_PREFIX: `auth-e2e-${randomUUID()}` }, stdio: 'ignore',
    });
    let spawnError = false;
    child.on('error', () => { spawnError = true; });
    try {
      let ready = false;
      for (let i = 0; i < 100; i++) {
        if (spawnError || child.exitCode !== null) throw new Error('Auth test server exited before startup; check PHP and the selected port.');
        try {
          const response = await new Promise<boolean>((resolve) => {
            const req = request(`http://127.0.0.1:${port}/`, { headers: { Host: `e2e.localhost:${port}` } }, res => {
              res.resume();
              resolve(res.statusCode === 200 && res.headers['x-e2e-auth-server'] === serverId);
            });
            req.on('error', () => resolve(false));
            req.setTimeout(2000, () => req.destroy());
            req.end();
          });
          if (response) { ready = true; break; }
        } catch { /* server is starting */ }
        await delay(200);
      }
      if (!ready) throw new Error('Auth test server did not start on its selected local port.');
      await use({ mailLog, origin });
    } finally {
      if (child.exitCode === null && child.pid) {
        const closed = new Promise<void>(resolve => child.once('exit', () => resolve()));
        child.kill();
        await closed;
      }
      rmSync(dir, { recursive: true, force: true });
    }
  },
  auth: async ({ server }, use) => {
    const emails: string[] = [];
    const anotherEmail = () => { const email = `auth-e2e-${randomUUID()}@example.test`; emails.push(email); return email; };
    const email = anotherEmail();
    try { await use({ email, anotherEmail, create: (ownedEmail = email) => {
      if (!emails.includes(ownedEmail)) throw new Error('Cannot create a user outside this test fixture.');
      tenant('create', [ownedEmail]);
    }, inspect: (ownedEmail = email) => {
      if (!emails.includes(ownedEmail)) throw new Error('Cannot inspect a user outside this test fixture.');
      return JSON.parse(tenant('inspect', [ownedEmail]));
    }, expireReset: () => { tenant('expire-reset', [email]); }, ageReset: () => { tenant('age-reset', [email]); }, password, mailLog: server.mailLog, origin: server.origin }); }
    finally { tenant('cleanup', emails); }
  },
  baseURL: async ({ server }, use) => use(server.origin),
  language: ['en', { option: true }],
  page: async ({ page, language }, use) => {
    await page.addInitScript(value => localStorage.setItem('i18nextLng', value), language);
    await use(page);
  },
});
export { expect };

export async function expectProfile(page: Page, email: string): Promise<void> {
  await expect(page.getByRole('tablist')).toBeVisible();
  await expect(page.locator('#name')).toBeVisible();
  await expect(page.locator('#email')).toHaveValue(email);
}

export async function register(page: Page, email: string, target = '/profile') {
  await page.goto(`/register?redirect=${encodeURIComponent(target)}`);
  await page.locator('#name').fill('Auth E2E Customer');
  await page.locator('#email').fill(email);
  await page.locator('#password').fill(password);
  await page.locator('#password_confirmation').fill(password);
  await page.locator('form button[type=submit]').click();
  await expect(page).toHaveURL(new RegExp(`${target.replace('/', '\\/')}$`));
  if (new URL(target, 'http://localhost').pathname === '/profile') await expectProfile(page, email);
}

export async function login(page: Page, email: string, target = '/profile', secret = password) {
  await page.goto(`/login?redirect=${encodeURIComponent(target)}`);
  await page.locator('#email').fill(email);
  await page.locator('#password').fill(secret);
  await page.locator('form button[type=submit]').click();
  await expect(page).toHaveURL(new RegExp(`${target.replace('/', '\\/')}$`));
  if (new URL(target, 'http://localhost').pathname === '/profile') await expectProfile(page, email);
}

export async function logout(page: Page) {
  await page.locator('button[aria-label="User Menu"], button[aria-label="قائمة المستخدم"]').first().click();
  const [response] = await Promise.all([
    page.waitForResponse(response => response.url().includes('/api/tenant/auth/logout') && response.request().method() === 'POST'),
    page.getByRole('menuitem').last().click(),
  ]);
  expect(response.ok()).toBeTruthy();
  await expect(page).toHaveURL(/^http:\/\/e2e\.localhost:\d+\/(?:$|login(?:\?|$))/);
  await expect(page.locator('button[aria-label="User Menu"], button[aria-label="قائمة المستخدم"]')).toHaveCount(0);
  await expect.poll(async () => {
    try {
      return await page.evaluate(async () => {
        const response = await fetch('/api/tenant/auth/user', { headers: { Accept: 'application/json' } });
        return response.ok && (await response.json()).user === null;
      });
    } catch {
      return false; // A profile guard may still be navigating during logout.
    }
  }, { message: 'Logout did not leave the browser with a guest session.' }).toBe(true);
}

export function resetLinks(mailLog: string, email: string, origin: string): string[] {
  const log = readFileSync(mailLog, 'utf8').replace(/=\r?\n/g, '').replace(/=([0-9A-F]{2})/gi, (_, hex) => String.fromCharCode(parseInt(hex, 16))).replace(/&amp;/g, '&');
  const links = new Set<string>();
  for (const match of log.matchAll(/https?:\/\/[^\s"<>]+\/reset-password\/[a-f0-9]+\?[^\s"<>]+/gi)) {
    try {
      const url = new URL(match[0]);
      if (url.origin === origin && /^\/reset-password\/[a-f0-9]+$/i.test(url.pathname) && url.searchParams.get('email') === email) links.add(url.href);
    } catch { /* Ignore non-URL text in the private mail file. */ }
  }
  if (!links.size) throw new Error('Reset notification did not contain a local link for this test account.');
  return [...links];
}
export function resetLink(mailLog: string, email: string, origin: string): string { return resetLinks(mailLog, email, origin).at(-1)!; }

export async function openReset(page: Page, url: string): Promise<void> {
  try { await page.goto(url); }
  catch { throw new Error('Could not open the captured local reset link.'); }
}

export async function expectLoginAfterReset(page: Page): Promise<void> {
  await expect.poll(() => new URL(page.url()).pathname === '/login', { message: 'Reset did not return to login.' }).toBe(true);
}

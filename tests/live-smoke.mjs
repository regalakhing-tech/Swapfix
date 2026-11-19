// Read-only smoke test for a running SwapFix site, local or hosted. It signs in as one customer and
// one technician and opens every page they use, but never submits a form that changes data.
// Usage: SWAPFIX_TEST_ORIGIN=https://swapfix.vercel.app node tests/live-smoke.mjs
// Logins come from a JSON file shaped {"key": {"email": "...", "password": "..."}}, by default
// .tools/demo-sellers.json, using the keys in SWAPFIX_SMOKE_CUSTOMER and SWAPFIX_SMOKE_TECHNICIAN.
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

const origin = process.env.SWAPFIX_TEST_ORIGIN || 'http://127.0.0.1:8000';
const logins = JSON.parse(await readFile(process.env.SWAPFIX_SMOKE_LOGINS || '.tools/demo-sellers.json', 'utf8'));
const uuid = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';
let checks = 0;

class Browser {
  cookies = new Map();
  async fetch(path, options = {}) {
    const response = await fetch(origin + path, {
      redirect: 'manual', ...options,
      headers: { cookie: [...this.cookies].map(([name, value]) => `${name}=${value}`).join('; '), origin, ...options.headers },
    });
    for (const line of response.headers.getSetCookie()) {
      const [pair] = line.split(';');
      const [name, value] = pair.split('=');
      if (value === '' || /max-age=0/i.test(line)) this.cookies.delete(name); else this.cookies.set(name, value);
    }
    return response;
  }
  async page(path, status = 200) {
    const response = await this.fetch(path);
    const html = await response.text();
    assert.equal(response.status, status, `${path} returned ${response.status}`);
    if (status === 200) assert.match(html, /<h1[ >]/, `${path} has a page heading`);
    checks++;
    return html;
  }
  async post(path, fields) {
    const token = (await this.page(path === '/login' ? '/login' : '/activity')).match(/name="_csrf" value="([^"]+)"/)?.[1];
    assert.ok(token, `CSRF token before ${path}`);
    return this.fetch(path, { method: 'POST', headers: { 'content-type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ _csrf: token, ...fields }) });
  }
  async signIn(key) {
    const login = logins[key];
    assert.ok(login?.email && login?.password, `Login "${key}" is missing from the logins file`);
    const response = await this.post('/login', { email: login.email, password: login.password });
    await response.text();
    assert.equal(response.status, 303, `Sign-in for ${key} was refused`);
    checks++;
  }
  async signOut() {
    const response = await this.post('/logout', {});
    await response.text();
    assert.equal(response.status, 303);
  }
}

// Public pages and the first record of each kind.
const visitor = new Browser();
const firstLink = (html, prefix) => html.match(new RegExp(`href="(${prefix}/${uuid})"`))?.[1];
const records = {};
for (const [path, prefix] of [['/', null], ['/market', '/market'], ['/exchange', '/exchange'], ['/repair', '/technicians']]) {
  const html = await visitor.page(path);
  assert.doesNotMatch(html, /sample listing|design preview/i, `${path} shows live records only`);
  if (prefix) {
    records[prefix] = firstLink(html, prefix);
    assert.ok(records[prefix], `${path} lists at least one record`);
    await visitor.page(records[prefix]);
  }
}
for (const path of ['/login', '/register']) await visitor.page(path);
const guarded = await visitor.fetch('/market/new');
await guarded.text();
assert.equal(guarded.status, 303, 'Signed-out visitors are sent to sign in');
assert.match(guarded.headers.get('location') ?? '', /^\/login/);
checks++;

// Customer pages.
const customer = new Browser();
await customer.signIn(process.env.SWAPFIX_SMOKE_CUSTOMER || 'seller1');
for (const path of ['/activity', '/account', '/market/new', '/exchange/new', '/messages', '/notifications', '/repairs', records['/market'], records['/exchange'], records['/technicians']]) {
  await customer.page(path);
}
await customer.page('/repair/request/' + records['/technicians'].split('/').pop());
await customer.page('/technician/dashboard', 403);
await customer.signOut();

// Technician pages.
const technician = new Browser();
await technician.signIn(process.env.SWAPFIX_SMOKE_TECHNICIAN || 'tech1');
for (const path of ['/technician/dashboard', '/technician/services', '/technician/profile', '/activity', '/account', '/messages', '/notifications']) {
  await technician.page(path);
}
await technician.signOut();

console.log(`Live smoke test passed on ${origin}: ${checks} checks, no data changed.`);

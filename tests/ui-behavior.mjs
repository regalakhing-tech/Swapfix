// DOM unit checks only. This does not open or automate a browser or verify visual layout.
import { readFile } from 'node:fs/promises';
import assert from 'node:assert/strict';
import { JSDOM } from 'jsdom';
const origin = process.env.SWAPFIX_TEST_ORIGIN || 'http://127.0.0.1:8000';
const response = await fetch(origin);
assert.equal(response.status,200);
const html = await response.text();
const dom = new JSDOM(html,{url:origin,runScripts:'outside-only'});
const { window } = dom;
window.matchMedia = () => ({matches:true});
window.HTMLDialogElement.prototype.showModal = function () { this.open = true; };
window.HTMLDialogElement.prototype.close = function () { this.open = false; this.dispatchEvent(new window.Event('close')); };
window.eval(await readFile(new URL('../public/assets/js/app.js',import.meta.url),'utf8'));
const document = window.document;
assert.equal(response.headers.get('cache-control'),'no-cache','Public HTML must be revalidated.');
assert.equal(document.querySelectorAll('script:not([src])').length,0,'Strict CSP must not require inline JavaScript.');
for (const image of document.querySelectorAll('img')) {
  assert.ok(Number(image.getAttribute('width')) > 0 && Number(image.getAttribute('height')) > 0,'Image dimensions must reserve layout space.');
  assert.ok(image.getAttribute('alt'),'Content images need alternative text.');
}

for (const link of document.querySelectorAll('.showcase-results a[href]')) {
  assert.match(link.getAttribute('href'),/^\/(market|exchange|technicians)\/[0-9a-f-]{36}$/,'Landing cards open real records.');
}
assert.doesNotMatch(document.body.textContent,/sample/i,'The landing page shows live records, not samples.');

const menu = document.querySelector('.mobile-menu');
menu.open = true;
document.dispatchEvent(new window.KeyboardEvent('keydown',{key:'Escape',bubbles:true}));
assert.equal(menu.open,false,'Escape closes mobile navigation.');
dom.window.close();
for (const route of ['/?view=exchange','/?view=repair']) {
  const result = await fetch(origin+route);
  assert.equal(result.status,200);
  assert.doesNotMatch(await result.text(),/sample/i,`No sample records: ${route}`);
}
const start = new JSDOM(await (await fetch(origin+'/?start=1')).text());
assert.ok(start.window.document.querySelector('#start-dialog[open]'),'Dialogs must work through a URL without JavaScript.');
start.window.close();
const styleLink = new JSDOM(html).window.document.querySelector('link[rel=stylesheet]').getAttribute('href');
const stylesheet = await fetch(origin+styleLink);
assert.match(stylesheet.headers.get('cache-control'),/max-age=31536000.*immutable/);
const css = await stylesheet.text();
assert.equal(css.includes('gradient'),false,'CSS uses solid brand colors.');
const fontPreload = new JSDOM(html).window.document.querySelector('link[as=font]').getAttribute('href');
assert.ok(css.includes(fontPreload),'The font preload and stylesheet must use the identical versioned URL.');
const notModified = await fetch(origin+styleLink,{headers:{'If-None-Match':stylesheet.headers.get('etag')}});
assert.equal(notModified.status,304,'Unchanged assets support conditional requests.');
const health = await fetch(origin+'/api/health');
assert.equal(health.headers.get('cache-control'),'no-store');
console.log('UI code checks passed: live landing cards, mobile-menu Escape, server-rendered tabs and dialog links, image dimensions, CSP-compatible scripts, versioned font loading, and cache headers.');

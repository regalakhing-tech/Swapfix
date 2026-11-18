// DOM behavior tests only. No browser automation or visual-layout assertions.
import { readFile } from 'node:fs/promises';
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { JSDOM } from 'jsdom';
const script = await readFile(new URL('../public/assets/js/app.js',import.meta.url),'utf8');
function page(body) {
  const dom = new JSDOM(body,{url:'https://swapfix.example.test/repairs/123',runScripts:'outside-only'});
  dom.window.matchMedia = () => ({matches:true});
  dom.window.HTMLDialogElement.prototype.showModal = function () { this.open = true; };
  dom.window.HTMLDialogElement.prototype.close = function () { this.open = false; this.dispatchEvent(new dom.window.Event('close')); };
  dom.window.eval(script);
  return dom;
}
test('password visibility exposes an accurate label and pressed state',() => {
  const dom = page('<div><input type="password"><button data-password-toggle></button></div>');
  const button = dom.window.document.querySelector('button'); button.click();
  assert.equal(dom.window.document.querySelector('input').type,'text');
  assert.equal(button.getAttribute('aria-label'),'Hide password');
  assert.equal(button.getAttribute('aria-pressed'),'true'); button.click();
  assert.equal(dom.window.document.querySelector('input').type,'password'); dom.window.close();
});
test('secondary dialogs preserve unrelated query state and remove only their own parameter',() => {
  const dom = page('<a href="?quote=1" data-open-dialog="quote">Quote</a><dialog id="quote" data-url-param="quote"><a href="/" data-close-dialog>Close</a></dialog>');
  dom.window.history.replaceState(null,'','?tab=history');
  dom.window.document.querySelector('[data-open-dialog]').click();
  assert.equal(dom.window.location.search,'?tab=history&quote=1');
  dom.window.document.querySelector('[data-close-dialog]').click();
  assert.equal(dom.window.location.search,'?tab=history'); dom.window.close();
});
test('destructive forms wait for explicit confirmation and submit the original action once',() => {
  const dom = page('<form action="/logout" method="post" data-confirm="You can sign in again." data-confirm-title="Log out?" data-confirm-action="Log out"><button type="submit">Log out</button></form>');
  const form = dom.window.document.querySelector('form');
  let submissions = 0;
  form.requestSubmit = () => { submissions++; };
  const submit = new dom.window.Event('submit',{bubbles:true,cancelable:true});
  form.dispatchEvent(submit);
  assert.equal(submit.defaultPrevented,true); assert.equal(submissions,0);
  assert.equal(dom.window.document.querySelector('dialog[open] h2').textContent,'Log out?');
  dom.window.document.querySelector('.confirm-actions .button-primary').click();
  assert.equal(submissions,1); assert.equal(dom.window.document.querySelector('dialog'),null); dom.window.close();
});
test('avatar menu closes on Escape and outside interaction',() => {
  const dom = page('<details class="avatar-menu" open><summary>Account</summary><a href="/account">Profile</a></details><main>Body</main>');
  const menu = dom.window.document.querySelector('details');
  dom.window.document.dispatchEvent(new dom.window.KeyboardEvent('keydown',{key:'Escape',bubbles:true}));
  assert.equal(menu.open,false); menu.open = true; dom.window.document.querySelector('main').click();
  assert.equal(menu.open,false); dom.window.close();
});
test('oversized multipart photos are stopped before a network submission',() => {
  const dom = page('<form enctype="multipart/form-data"><input type="file" multiple><button>Publish</button></form><div id="toast-stack"></div>');
  const input = dom.window.document.querySelector('input');
  Object.defineProperty(input,'files',{value:[{size:3*1024*1024},{size:3*1024*1024}]});
  const submit = new dom.window.Event('submit',{bubbles:true,cancelable:true});
  dom.window.document.querySelector('form').dispatchEvent(submit);
  assert.equal(submit.defaultPrevented,true);
  assert.match(dom.window.document.querySelector('.toast').textContent,/4 MiB/); dom.window.close();
});
test('workspace keeps brand colors, solid surfaces and three responsive shells',async () => {
  const css = await readFile(new URL('../public/assets/css/workspace.css',import.meta.url),'utf8');
  assert.doesNotMatch(css,/gradient\s*\(/i);
  for (const feature of ['.sidebar{','.tabbar{','.topbar{','max-width:1023px','max-width:720px','prefers-reduced-motion']) assert.ok(css.includes(feature),feature);
});

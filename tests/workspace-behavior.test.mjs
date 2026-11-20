// DOM behavior tests only. No browser automation or visual-layout assertions.
import { readFile } from 'node:fs/promises';
import { test, after } from 'node:test';
import assert from 'node:assert/strict';
import { JSDOM } from 'jsdom';
const script = await readFile(new URL('../public/assets/js/app.js',import.meta.url),'utf8');
function page(body,setup) {
  const dom = new JSDOM(body,{url:'https://swapfix.example.test/repairs/123',runScripts:'outside-only'});
  dom.window.matchMedia = () => ({matches:true});
  dom.window.HTMLDialogElement.prototype.showModal = function () { this.open = true; };
  dom.window.HTMLDialogElement.prototype.close = function () { this.open = false; this.dispatchEvent(new dom.window.Event('close')); };
  setup?.(dom.window);
  dom.window.eval(script);
  return dom;
}
// A visible page whose background fetch returns `next`.
// Close every refreshing page even when an assertion fails, so its 3-second timer cannot keep Node running.
const refreshingPages = [];
after(() => refreshingPages.forEach(dom => dom.window.close()));
function refreshing(body,next) {
  const dom = page(body,window => {
    Object.defineProperty(window.document,'hidden',{value:false,configurable:true});
    window.fetches = 0;
    window.fetch = async () => { window.fetches++; return {ok:true,redirected:false,headers:{get:() => 'text/html; charset=utf-8'},text:async () => next}; };
  });
  refreshingPages.push(dom);
  return dom;
}
async function tick(dom) {
  dom.window.document.dispatchEvent(new dom.window.Event('visibilitychange'));
  await new Promise(resolve => setTimeout(resolve,20));
}
const photo = token => `<img src="https://db.example.test/storage/v1/object/sign/offer-images/a.jpg?token=${token}" alt="Phone" width="80" height="80">`;
const chat = (bubbles,count,token,actions) => `<body class="portal" data-refresh><nav class="tabbar"><a href="/messages">Chat${count}</a></nav><main id="main">${photo(token)}<div class="thread">${bubbles}</div><form method="post" action="/messages/1" class="composer"><input type="hidden" name="csrf" value="t"><textarea name="body"></textarea><button>Send</button></form><div class="actions">${actions}</div></main></body>`;
test('background refresh brings in new messages and counts without touching a started reply',async () => {
  const one = '<div class="bubble"><p>Hi</p></div>';
  const dom = refreshing(chat(one,'','a',''),chat(one + '<div class="bubble"><p>Still available?</p></div>','<b class="count">1</b>','b','<form method="post" action="/x" data-confirm="Sure?"><button>Go</button></form>'));
  const document = dom.window.document;
  const draft = document.querySelector('textarea'); draft.value = 'Half a reply';
  await tick(dom);
  assert.equal(document.querySelectorAll('.thread .bubble').length,2);
  assert.equal(document.querySelector('.tabbar .count').textContent,'1');
  assert.equal(document.querySelector('textarea'),draft); assert.equal(draft.value,'Half a reply');
  assert.match(document.querySelector('main img').getAttribute('src'),/token=a$/,'A re-signed photo URL must not reload the image.');
  const submit = new dom.window.Event('submit',{bubbles:true,cancelable:true});
  document.querySelector('form[data-confirm]').dispatchEvent(submit);
  assert.equal(submit.defaultPrevented,true,'Swapped-in forms keep their confirmation behavior.');
  dom.window.close();
});
test('background refresh updates around untouched forms but leaves open dialogs alone',async () => {
  const view = (status,title) => `<body class="portal" data-refresh><main id="main"><section class="repair"><p class="status">${status}</p><form method="post" action="/repairs/1/cancel"><input type="hidden" name="csrf" value="t"><button>Cancel</button></form></section><dialog id="quote" open><h2>${title}</h2></dialog></main></body>`;
  const dom = refreshing(view('Requested','Quote'),view('Quoted','New quote').replace('</p>','</p><p class="note">Quotation ready</p>'));
  const document = dom.window.document;
  const heading = document.querySelector('dialog h2'); heading.dataset.sentinel = 'kept';
  await tick(dom);
  assert.equal(document.querySelector('.status').textContent,'Quoted','A clean form with a button must not block updates around it.');
  assert.equal(document.querySelector('.note')?.textContent,'Quotation ready');
  assert.equal(document.querySelector('dialog h2'),heading); assert.equal(heading.textContent,'Quote');
  assert.equal(heading.dataset.sentinel,'kept','Nothing inside an open dialog changes.');
  dom.window.close();
});
test('a new action appears beside a reply that is still being typed',async () => {
  const view = actions => `<body class="portal" data-refresh><main id="main"><section class="panel"><p class="status">${actions ? 'Quoted' : 'Requested'}</p>${actions}<form method="post" action="/messages/1" class="composer"><textarea name="body"></textarea><button>Send</button></form></section></main></body>`;
  const dom = refreshing(view(''),view('<form method="post" action="/repairs/1/quotations/2" class="decide"><button>Approve</button></form>'));
  const document = dom.window.document;
  const draft = document.querySelector('textarea'); draft.value = 'Half a reply'; draft.focus();
  await tick(dom);
  assert.ok(document.querySelector('form.decide'),'The new action must appear even though the panel holds a started reply.');
  assert.equal(document.querySelector('.status').textContent,'Quoted');
  assert.equal(document.querySelector('textarea'),draft); assert.equal(draft.value,'Half a reply');
  assert.equal(document.activeElement,draft);
});
test('background refresh stops after a form submits and never runs on pages without the marker',async () => {
  const body = '<body class="portal" data-refresh><main id="main"><form method="post"><button>Save</button></form></main></body>';
  const dom = refreshing(body,body);
  dom.window.document.querySelector('form').dispatchEvent(new dom.window.Event('submit',{bubbles:true,cancelable:true}));
  await tick(dom);
  assert.equal(dom.window.fetches,0); dom.window.close();
  const plain = refreshing('<body class="portal"><main id="main">Saved form with errors</main></body>','');
  await tick(plain);
  assert.equal(plain.window.fetches,0); plain.window.close();
});
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
test('back-to-top link appears only after scrolling down the page',() => {
  const dom = page('<main id="main">Body</main><a class="back-to-top" href="#main" hidden>Top</a>');
  const link = dom.window.document.querySelector('.back-to-top');
  assert.equal(link.hidden,true);
  Object.defineProperty(dom.window,'scrollY',{value:800,configurable:true});
  dom.window.dispatchEvent(new dom.window.Event('scroll'));
  assert.equal(link.hidden,false);
  Object.defineProperty(dom.window,'scrollY',{value:0,configurable:true});
  dom.window.dispatchEvent(new dom.window.Event('scroll'));
  assert.equal(link.hidden,true); dom.window.close();
});
test('workspace keeps brand colors, solid surfaces and three responsive shells',async () => {
  const css = await readFile(new URL('../public/assets/css/workspace.css',import.meta.url),'utf8');
  assert.doesNotMatch(css,/gradient\s*\(/i);
  for (const feature of ['.sidebar{','.tabbar{','.topbar{','max-width:1023px','max-width:720px','prefers-reduced-motion']) assert.ok(css.includes(feature),feature);
});

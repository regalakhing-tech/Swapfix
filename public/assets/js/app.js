(() => {
  'use strict';
  const savedKey = 'swapfix.preview.saved.v1';
  const saveButtons = [...document.querySelectorAll('[data-save]')];
  let saved = new Set();
  try {
    const stored = JSON.parse(localStorage.getItem(savedKey) || '[]');
    if (Array.isArray(stored)) saved = new Set(stored.filter(id => typeof id === 'string' && /^[a-z0-9-]{1,64}$/.test(id)).slice(0,50));
  } catch { /* A blocked or invalid store must not prevent browsing. */ }

  function toast(message, error = false) {
    const activeDialog = document.querySelector('dialog[open]');
    let stack = activeDialog?.querySelector('.dialog-toast-stack') || document.getElementById('toast-stack');
    if (activeDialog && !activeDialog.querySelector('.dialog-toast-stack')) {
      stack = document.createElement('div'); stack.className = 'toast-stack dialog-toast-stack';
      stack.setAttribute('aria-live','polite'); activeDialog.append(stack);
    }
    if (!stack) return;
    const box = document.createElement('div');
    box.className = error ? 'toast toast-error' : 'toast';
    box.setAttribute('role', error ? 'alert' : 'status');
    const text = document.createElement('span');
    text.textContent = message;
    const close = document.createElement('button');
    close.type = 'button'; close.textContent = 'Dismiss'; close.setAttribute('aria-label','Dismiss notification');
    box.append(text,close); stack.append(box);
    const remove = () => { clearTimeout(timer); box.classList.add('is-leaving'); setTimeout(() => box.remove(),200); };
    const timer = setTimeout(remove,error ? 6000 : 2000);
    close.addEventListener('click',remove);
  }

  function syncSaved() {
    saveButtons.forEach(button => {
      const active = saved.has(button.dataset.save);
      button.classList.toggle('is-saved',active);
      button.setAttribute('aria-pressed',String(active));
      button.setAttribute('aria-label',`${active ? 'Unsave' : 'Save'} ${button.dataset.saveName}`);
      const label = button.querySelector('[data-save-label]');
      if (label) label.textContent = active ? 'Saved on this browser' : 'Save for later';
      button.hidden = false;
    });
  }
  syncSaved();
  saveButtons.forEach(button => button.addEventListener('click',() => {
    const id = button.dataset.save;
    const next = new Set(saved);
    const removing = next.has(id);
    if (removing) next.delete(id); else next.add(id);
    try {
      localStorage.setItem(savedKey,JSON.stringify([...next].slice(-50)));
      saved = new Set([...next].slice(-50)); syncSaved();
      toast(removing ? `${button.dataset.saveName} removed from saved items.` : `${button.dataset.saveName} saved on this browser.`);
    } catch { toast('This browser could not save the item. Please check storage settings.',true); }
  }));

  const dialogs = [...document.querySelectorAll('dialog')];
  function closeDialog(dialog) { dialog.close(); }
  dialogs.forEach(dialog => {
    if (typeof dialog.showModal !== 'function') return;
    dialog.querySelectorAll('[data-close-dialog]').forEach(link => link.addEventListener('click',event => {
      event.preventDefault(); closeDialog(dialog);
    }));
    dialog.addEventListener('click',event => {
      if (event.target !== dialog) return;
      const bounds = dialog.getBoundingClientRect();
      if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) closeDialog(dialog);
    });
    dialog.addEventListener('close',() => {
      const url = new URL(location.href);
      if (url.searchParams.has('item') || url.searchParams.has('start')) {
        url.searchParams.delete('item'); url.searchParams.delete('start'); history.replaceState(null,'',url);
      }
    });
    if (dialog.open) { dialog.removeAttribute('open'); dialog.showModal(); }
  });
  document.querySelectorAll('[data-open-dialog]').forEach(link => link.addEventListener('click',event => {
    const dialog = document.getElementById(link.dataset.openDialog);
    if (!dialog || typeof dialog.showModal !== 'function') return;
    event.preventDefault();
    dialogs.forEach(other => { if (other.open) other.close(); });
    dialog.showModal();
  }));

  document.querySelectorAll('.mobile-menu a').forEach(link => link.addEventListener('click',() => link.closest('details').removeAttribute('open')));
  document.addEventListener('click',event => document.querySelectorAll('.mobile-menu[open]').forEach(menu => { if (!menu.contains(event.target)) menu.removeAttribute('open'); }));
  document.addEventListener('keydown',event => { if (event.key === 'Escape') document.querySelectorAll('.mobile-menu[open]').forEach(menu => menu.removeAttribute('open')); });
  const header = document.querySelector('.site-header');
  const updateHeader = () => header?.classList.toggle('is-scrolled',window.scrollY > 16);
  window.addEventListener('scroll',updateHeader,{passive:true}); updateHeader();
  const reduced = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
  if (!reduced && 'IntersectionObserver' in window) {
    const observer = new IntersectionObserver(entries => entries.forEach(entry => {
      if (entry.isIntersecting) { entry.target.classList.add('is-visible'); observer.unobserve(entry.target); }
    }),{threshold:0.08});
    document.querySelectorAll('.reveal').forEach(element => observer.observe(element));
    document.body.classList.add('motion-ready');
  }
})();

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
      if (dialog.dataset.urlParam) {
        url.searchParams.delete(dialog.dataset.urlParam); history.replaceState(null,'',url);
      } else if (url.searchParams.has('item') || url.searchParams.has('start')) {
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
    if (dialog.dataset.urlParam) {
      const url = new URL(location.href); url.searchParams.set(dialog.dataset.urlParam,'1'); history.replaceState(null,'',url);
    }
  }));

  document.querySelectorAll('[data-toast]').forEach(box => {
    let timer;
    const remove = () => { clearTimeout(timer); box.classList.add('is-leaving'); setTimeout(() => box.remove(),200); };
    box.querySelector('[data-toast-close]')?.addEventListener('click',remove);
    timer = setTimeout(remove,Number(box.dataset.toast) === 6000 ? 6000 : 2000);
  });
  document.querySelectorAll('[data-password-toggle]').forEach(button => button.addEventListener('click',() => {
    const input = button.parentElement.querySelector('input');
    const showing = input.type === 'password';
    input.type = showing ? 'text' : 'password';
    button.setAttribute('aria-label',showing ? 'Hide password' : 'Show password');
    button.setAttribute('aria-pressed',String(showing));
  }));
  document.querySelectorAll('[data-photo-input]').forEach(input => {
    let urls = [];
    input.addEventListener('change',() => {
      urls.forEach(url => URL.revokeObjectURL(url)); urls = [];
      const preview = input.closest('.field')?.querySelector('[data-photo-preview]');
      if (!preview) return;
      preview.replaceChildren();
      [...input.files].forEach(file => {
        if (!['image/jpeg','image/png','image/webp'].includes(file.type) || file.size > 4194304) {
          const error = document.createElement('span'); error.className = 'upload-name';
          error.textContent = `${file.name}: choose JPEG, PNG or WebP up to 4 MiB total.`; preview.append(error); return;
        }
        const img = document.createElement('img');
        img.src = URL.createObjectURL(file); urls.push(img.src);
        img.alt = file.name; img.width = 80; img.height = 80; preview.append(img);
      });
    });
  });

  document.querySelectorAll('form[enctype="multipart/form-data"]').forEach(form => form.addEventListener('submit',event => {
    const files = [...form.querySelectorAll('input[type="file"]')].flatMap(input => [...input.files]);
    if (files.reduce((sum,file) => sum + file.size,0) > 4194304) {
      event.preventDefault(); toast('Photos must total 4 MiB or less. Choose smaller images.',true);
      form.querySelector('input[type="file"]')?.focus();
    }
  }));

  // Confirmation uses a native, styled dialog. Without dialog support, the
  // server action remains available through its ordinary HTML form.
  document.querySelectorAll('form[data-confirm]').forEach(form => {
    let approved = false;
    form.addEventListener('submit',event => {
      if (approved) return;
      const dialog = document.createElement('dialog');
      if (typeof dialog.showModal !== 'function') return;
      event.preventDefault();
      dialog.className = 'detail-dialog form-dialog confirmation-dialog';
      dialog.setAttribute('aria-labelledby','confirmation-title');
      const body = document.createElement('div'); body.className = 'dialog-body';
      const title = document.createElement('h2'); title.id = 'confirmation-title'; title.textContent = form.dataset.confirmTitle || 'Continue?';
      const message = document.createElement('p'); message.className = 'confirm-message'; message.textContent = form.dataset.confirm;
      const actions = document.createElement('div'); actions.className = 'confirm-actions';
      const cancel = document.createElement('button'); cancel.type = 'button'; cancel.className = 'button button-outline'; cancel.textContent = 'Go back';
      const confirm = document.createElement('button'); confirm.type = 'button'; confirm.className = 'button button-primary'; confirm.textContent = form.dataset.confirmAction || 'Continue';
      cancel.addEventListener('click',() => dialog.close());
      confirm.addEventListener('click',() => { approved = true; dialog.close(); form.requestSubmit(event.submitter || undefined); });
      dialog.addEventListener('close',() => dialog.remove());
      actions.append(cancel,confirm); body.append(title,message,actions); dialog.append(body); document.body.append(dialog); dialog.showModal();
      cancel.focus();
    });
  });
  document.querySelectorAll('.thread').forEach(thread => { thread.scrollTop = thread.scrollHeight; });
  const menus = '.mobile-menu[open],.avatar-menu[open]';
  document.addEventListener('click',event => document.querySelectorAll(menus).forEach(menu => { if (!menu.contains(event.target)) menu.removeAttribute('open'); }));
  document.addEventListener('keydown',event => { if (event.key === 'Escape') document.querySelectorAll(menus).forEach(menu => menu.removeAttribute('open')); });

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

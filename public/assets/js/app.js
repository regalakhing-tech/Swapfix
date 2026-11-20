(() => {
  'use strict';
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

  // Per-element behavior. Runs once on the page and again on any part the background refresh swaps in.
  function bind(root) {
    if (root.nodeType !== 1) return;
    const all = selector => [...(root.matches(selector) ? [root] : []),...root.querySelectorAll(selector)];
    all('dialog').forEach(dialog => {
      if (typeof dialog.showModal !== 'function') return;
      dialog.querySelectorAll('[data-close-dialog]').forEach(link => link.addEventListener('click',event => {
        event.preventDefault(); dialog.close();
      }));
      dialog.addEventListener('click',event => {
        if (event.target !== dialog) return;
        const bounds = dialog.getBoundingClientRect();
        if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) dialog.close();
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
    all('[data-open-dialog]').forEach(link => link.addEventListener('click',event => {
      const dialog = document.getElementById(link.dataset.openDialog);
      if (!dialog || typeof dialog.showModal !== 'function') return;
      event.preventDefault();
      document.querySelectorAll('dialog[open]').forEach(other => other.close());
      dialog.showModal();
      if (dialog.dataset.urlParam) {
        const url = new URL(location.href); url.searchParams.set(dialog.dataset.urlParam,'1'); history.replaceState(null,'',url);
      }
    }));
    all('[data-password-toggle]').forEach(button => button.addEventListener('click',() => {
      const input = button.parentElement.querySelector('input');
      const showing = input.type === 'password';
      input.type = showing ? 'text' : 'password';
      button.setAttribute('aria-label',showing ? 'Hide password' : 'Show password');
      button.setAttribute('aria-pressed',String(showing));
    }));
    all('[data-photo-input]').forEach(input => {
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

    all('form[enctype="multipart/form-data"]').forEach(form => form.addEventListener('submit',event => {
      const files = [...form.querySelectorAll('input[type="file"]')].flatMap(input => [...input.files]);
      if (files.reduce((sum,file) => sum + file.size,0) > 4194304) {
        event.preventDefault(); toast('Photos must total 4 MiB or less. Choose smaller images.',true);
        form.querySelector('input[type="file"]')?.focus();
      }
    }));

    // Confirmation uses a native, styled dialog. Without dialog support, the
    // server action remains available through its ordinary HTML form.
    all('form[data-confirm]').forEach(form => {
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
    all('.thread').forEach(thread => { thread.scrollTop = thread.scrollHeight; });
  }
  bind(document.body);

  document.querySelectorAll('[data-toast]').forEach(box => {
    let timer;
    const remove = () => { clearTimeout(timer); box.classList.add('is-leaving'); setTimeout(() => box.remove(),200); };
    box.querySelector('[data-toast-close]')?.addEventListener('click',remove);
    timer = setTimeout(remove,Number(box.dataset.toast) === 6000 ? 6000 : 2000);
  });

  // Signed-in GET pages re-fetch themselves every 3 seconds and swap in only what changed, so both
  // sides of a chat, trade or repair see updates without reloading. It shows nothing on screen.
  if (document.body.hasAttribute('data-refresh')) {
    // Buttons and fieldsets have no default value, so they never count as edited.
    const dirty = field => field.type === 'file' ? field.files.length > 0
      : field.type === 'checkbox' || field.type === 'radio' ? field.checked !== field.defaultChecked
      : field.tagName !== 'SELECT' ? 'defaultValue' in field && field.value !== field.defaultValue
      : field.multiple ? [...field.options].some(option => option.selected !== option.defaultSelected)
      : field.selectedIndex !== Math.max(0,[...field.options].findIndex(option => option.defaultSelected));
    const opened = 'dialog[open],details[open]';
    const inUse = form => form.contains(document.activeElement) || [...form.elements].some(dirty);
    // Never touch what the person is using: focus, a form they started, an open dialog or menu and its contents.
    const busy = node => {
      const element = node.nodeType === 1 ? node : node.parentElement;
      const form = element.closest('form');
      if (element.closest(opened) !== null || (form !== null && inUse(form))) return true;
      return node.nodeType === 1 && (element.contains(document.activeElement) || element.querySelector(opened) !== null
        || [...element.querySelectorAll('form')].some(inUse));
    };
    const stable = value => value?.includes('/object/sign/') ? value.split('?')[0] : value;
    const sync = (live,next) => {
      live.getAttributeNames().forEach(name => { if (!next.hasAttribute(name)) live.removeAttribute(name); });
      next.getAttributeNames().forEach(name => {
        const value = next.getAttribute(name);
        // Signed photo URLs change on every request; keep the image that already loaded.
        if (name === 'src' && stable(value) === stable(live.getAttribute(name))) return;
        if (live.getAttribute(name) !== value) live.setAttribute(name,value);
      });
    };
    const swap = (live,next) => {
      const readingOlder = live.classList?.contains('thread') && live.scrollHeight - live.scrollTop - live.clientHeight > 40;
      const top = live.scrollTop;
      live.replaceWith(next); bind(next);
      if (readingOlder) next.scrollTop = top;
    };
    const key = node => node.nodeType === 1 ? `${node.nodeName}#${node.id}.${node.getAttribute('class') || ''}` : `#${node.nodeType}`;
    const patch = (live,next) => {
      if (live.isEqualNode(next)) return;
      const free = !busy(live);
      const resized = live.childNodes.length !== next.childNodes.length;
      if (live.nodeType !== 1 || live.nodeName !== next.nodeName || (free && resized)) { if (free) swap(live,next); return; }
      if (free) sync(live,next);
      if (resized) return merge(live,next);
      const kids = [...next.childNodes];
      [...live.childNodes].forEach((child,index) => patch(child,kids[index]));
    };
    // A parent that holds something in use but gained or lost children: keep what is in use,
    // add the new children and drop stale ones, so a new action still appears beside a started reply.
    const merge = (live,next) => {
      let at = 0;
      for (const fresh of [...next.childNodes]) {
        const rest = [...live.childNodes].slice(at);
        const match = rest.findIndex(child => key(child) === key(fresh));
        if (match === -1) { live.insertBefore(fresh,rest[0] ?? null); bind(fresh); at++; continue; }
        rest.slice(0,match).forEach(stale => { if (busy(stale)) at++; else stale.remove(); });
        patch(rest[match],fresh); at++;
      }
      [...live.childNodes].slice(at).forEach(stale => { if (!busy(stale)) stale.remove(); });
    };
    const regions = ['#main','.sidebar-nav','.tabbar','.topbar .has-count'];
    let stopped = false, running = false;
    const refresh = async () => {
      if (stopped || running || document.hidden) return;
      running = true;
      try {
        const response = await fetch(location.href,{cache:'no-store',credentials:'same-origin'});
        if (!response.ok || response.redirected || !response.headers.get('content-type')?.includes('text/html')) return;
        const fresh = new DOMParser().parseFromString(await response.text(),'text/html');
        if (stopped || !fresh.body.hasAttribute('data-refresh')) return;
        regions.forEach(selector => {
          const live = document.querySelector(selector), next = fresh.querySelector(selector);
          if (live && next) patch(live,next);
        });
      } catch { /* Offline or mid-deploy: the next tick tries again. */ } finally { running = false; }
    };
    setInterval(refresh,3000);
    document.addEventListener('visibilitychange',refresh);
    // A submitted form owns the next page and its one-time messages, so stop fetching.
    document.addEventListener('submit',event => { if (!event.defaultPrevented) stopped = true; });
    window.addEventListener('pageshow',event => { if (event.persisted) { stopped = false; refresh(); } });
  }

  const menus = '.mobile-menu[open],.avatar-menu[open]';
  document.addEventListener('click',event => document.querySelectorAll(menus).forEach(menu => { if (!menu.contains(event.target)) menu.removeAttribute('open'); }));
  document.addEventListener('keydown',event => { if (event.key === 'Escape') document.querySelectorAll(menus).forEach(menu => menu.removeAttribute('open')); });

  document.querySelectorAll('.mobile-menu a').forEach(link => link.addEventListener('click',() => link.closest('details').removeAttribute('open')));
  document.addEventListener('click',event => document.querySelectorAll('.mobile-menu[open]').forEach(menu => { if (!menu.contains(event.target)) menu.removeAttribute('open'); }));
  document.addEventListener('keydown',event => { if (event.key === 'Escape') document.querySelectorAll('.mobile-menu[open]').forEach(menu => menu.removeAttribute('open')); });
  const header = document.querySelector('.site-header');
  const backToTop = document.querySelector('.back-to-top');
  const updateHeader = () => {
    header?.classList.toggle('is-scrolled',window.scrollY > 16);
    if (backToTop) backToTop.hidden = window.scrollY < 600;
  };
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

# Web App Playbook: Clean UI and Fast Loading

One file with both skills: **building-clean-app-ui** and **fast-loading-website**.

## How to use this file

- **Claude Code:** the same rules are already installed as skills in `~/.claude/skills/`. They load by themselves.
- **Other AI tools (Cursor, Copilot, ChatGPT, Gemini):** attach this file, or copy it into the project as `AGENTS.md`, `CLAUDE.md`, or `.cursor/rules/web-app-playbook.md`. Then write: "Follow the Web App Playbook."
- **By hand:** use Parts 1 and 5 as checklists. Copy code from the other parts.
- Change the brand color, fonts, and region to match each new project.

## Contents

1. [Clean App UI rules](#part-1-clean-app-ui-rules): use when you design, redesign, or polish a page.
2. [Tokens, spacing, and responsive layout](#part-2-tokens-spacing-and-responsive-layout)
3. [Dashboard shell](#part-3-dashboard-shell)
4. [Feedback, dialogs, and forms](#part-4-feedback-dialogs-and-forms)
5. [Fast loading website rules](#part-5-fast-loading-website-rules): use before every deploy.
6. [Speed recipes](#part-6-speed-recipes)
7. [Image optimizer script](#part-7-image-optimizer-script)

---

## Part 1: Clean App UI rules

### Overview

A clean app UI comes from a few rules applied on every page: one color system taken from the brand, solid fills, one spacing scale, icons in place of words, and layouts that change shape on phones instead of only shrinking. Apply the whole set. A UI that follows only half of the rules still looks unfinished.

### When to Use

- The user says "make it modern", "nice", "clean", "redesign", "upgrade the UI", "too plain", "too much text".
- The app has an admin, staff, owner, or customer dashboard or portal.
- The app has sign-in, register, or staff login pages.
- The user reports "fix the mobile view", "not responsive", or tables that break on phones.

Do not use this skill for native mobile apps or for logo and brand-identity work.

### Rules

1. **Color.** Take the primary color from the logo or the existing CSS. Put all colors in `:root` tokens: primary, primary-strong, primary-soft (tint), dark (navy), text, muted, line, canvas, card. Give each status color a soft background (green, amber, red, violet).
2. **No gradients.** Use solid fills, soft tints, borders, and shadows for depth. This applies to backgrounds, buttons, text, photo overlays, and stat cards. Check: `grep -c gradient` on the CSS returns 0.
3. **Spacing.** Use only 4, 8, 12, 16, 24, 32, 48, 64, 96 px. Card padding is 18 to 24. The gap between cards is 16. Section padding is 96 on desktop and 64 on phones.
4. **Less text.** A page heading is an icon tile, a title, and one short line. Labels are 1 to 3 words. Show counts, badges, and status pills in place of sentences. A nav item is an icon and a 1 or 2 word label.
5. **Icons.** Use one inline SVG set from a helper function, with one stroke width (1.8), `currentColor`, and sizes 18, 20, or 24. Put `aria-hidden="true"` on an icon when a visible label is next to it. Do not use emoji as UI icons.
6. **Type.** Use at most 2 font families: one sans-serif for the UI, and an optional formal serif only for the system name (wordmark).
7. **Photos.** Each photo must show what its section says: a vaccination card shows a vaccination. Photos must be sharp, cropped to a fixed aspect ratio, and never stretched. Real photos from the client beat stock photos.
8. **Feedback.** Every action shows a toast. Success toasts close after 2 seconds; error toasts stay 6 seconds; both have a close button. Log out, cancel, and delete ask first in a styled confirm dialog, never `window.confirm`.
9. **Responsive.** Build 3 layouts. Desktop has a sidebar. Tablet and phone have a top bar with an avatar menu and a bottom tab bar with 4 items. On phones, tables become stacked cards. No horizontal page scroll at any width.
10. **Auth pages.** No site header or footer. Show one compact centered card (max 440 px) with a Back link only. Password fields have a placeholder and a show/hide eye button. Staff or admin users sign in on a separate page. A wrong-role sign-in shows which page to use.
11. **Secondary forms.** Add and edit forms open in a pop-up dialog from a button, so the list stays visible behind it. The form also works without JavaScript through a URL such as `?add=1`.

### Workflow

1. Read the current CSS and templates. Find the brand color and the logo.
2. Write the tokens and base styles: see [Part 2](#part-2-tokens-spacing-and-responsive-layout).
3. Build the shells: public header and footer, auth layout, dashboard shell. See [Part 3](#part-3-dashboard-shell).
4. Convert each page: heading, stat cards, panels, tables, empty states.
5. Add toasts, confirm dialog, pop-up forms, password toggle, and photo upload: see [Part 4](#part-4-feedback-dialogs-and-forms).
6. Take screenshots of every page at 1366, 1024, 768, 390, and 360 px wide. Fix every overflow and every wrapped header.
7. Apply the `fast-loading-website` skill before you deploy.

### Quick Reference

| Need | Pattern |
|---|---|
| Dashboard summary | 4 solid-color stat cards: icon tile, big number, uppercase label |
| Page title | Icon tile (44 px, primary-soft) + h1 + one line |
| Section title | Small icon + 1 to 3 word heading |
| Status | Pill: soft background, strong text, dot |
| Empty list | Icon, one sentence, one primary button |
| Long list | Search box with icon, filter dropdown, status chips, pagination hidden when there is one page |
| Person in a table | Avatar or photo, name, email under it in muted text |
| Report export | Real PDF file built on the server, not `window.print()` |

### Common Mistakes

| Mistake | Fix |
|---|---|
| Sidebar still shows on phones and squeezes the content | Hide it below 1024 px; use the top bar and the tab bar |
| A "More" tab collects leftover pages | Put extra pages in the avatar menu |
| Phone header wraps onto 2 rows | Smaller wordmark, smaller button padding below 400 px; test at 360 px |
| A toast comes back after refresh | Remove `?saved=1` with `history.replaceState` |
| A hidden file input makes the page wider | Give `.sr-only` inputs `width: 1px` so other input rules cannot stretch them |
| A search icon covers the typed text | Give the input `padding-left: 44px` |
| Decorative floating cards over the hero photo | One sharp photo in a layered frame |
| Gradient "for depth" | Solid fill plus shadow |

### Done Checklist

- [ ] `grep -c gradient` on all CSS returns 0
- [ ] Every page checked at 1366, 1024, 768, 390, and 360 px with no horizontal scroll
- [ ] Every POST action shows a toast; log out and delete ask first
- [ ] Every nav item and page heading has an icon
- [ ] Auth pages have no header or footer and have a Back link
- [ ] Images have `width`, `height`, and `alt`

---

## Part 2: Tokens, spacing, and responsive layout

Replace the hex values with the brand's colors. Keep the token names, so every pattern in this skill works unchanged.

### Tokens

```css
:root {
  /* Brand: take --primary from the logo. --primary-strong is about 15% darker. */
  --primary: #0a4bff;
  --primary-strong: #0036cc;
  --primary-soft: #eef3ff;   /* tint for icon tiles, active nav, chips */
  --primary-line: #d9e4ff;
  --navy: #0b1533;           /* headings and dark panels */
  --text: #4b5770;
  --muted: #7d889c;
  --line: #e6ebf4;
  --canvas: #f6f8fc;         /* page background */
  --card: #fff;

  /* Status: strong text on a soft background */
  --green: #0b8a55;  --green-soft: #e6f7ee;
  --amber: #a85f00;  --amber-soft: #fff3de;
  --red: #cf3535;    --red-soft: #fdecec;
  --violet: #5b3fd6; --violet-soft: #efebff;

  --radius-lg: 24px;  /* big cards, dialogs */
  --radius: 16px;     /* cards, inputs */
  --radius-sm: 12px;  /* icon tiles, nav items */

  /* Depth comes from shadows, never from gradients */
  --shadow-soft: 0 1px 2px rgba(16, 24, 40, 0.04), 0 12px 30px -20px rgba(15, 40, 110, 0.28);
  --shadow: 0 1px 2px rgba(16, 24, 40, 0.04), 0 28px 56px -28px rgba(15, 40, 110, 0.3);

  --sans: "Sora", ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
  --serif: "Playfair Display", Georgia, "Times New Roman", serif; /* wordmark only */
}

body {
  margin: 0;
  background: var(--canvas);
  color: var(--text);
  font: 400 16px/1.7 var(--sans);
}
h1, h2, h3 { color: var(--navy); letter-spacing: -0.02em; line-height: 1.15; }
```

### Spacing scale

| px | Use |
|---|---|
| 4, 8 | Icon to label, pill padding, gap inside a button |
| 12 | Gap inside a card row, nav item gap |
| 16 | Gap between cards, phone side gutter |
| 18 to 24 | Card padding |
| 32, 48 | Gap between page blocks |
| 64 / 96 | Section padding on phone / desktop |

Do not use other values. When something looks crowded, move up one step of the scale. Do not add a border.

### Building blocks

```css
.card {
  padding: 22px;
  border: 1px solid var(--line);
  border-radius: var(--radius-lg);
  background: var(--card);
  box-shadow: var(--shadow-soft);
}
.icon-tile {               /* holds a 20px icon */
  display: grid;
  place-items: center;
  width: 44px;
  height: 44px;
  border-radius: var(--radius-sm);
  background: var(--primary-soft);
  color: var(--primary);
}
.button {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-height: 44px;          /* touch target */
  padding: 0 20px;
  border: 0;
  border-radius: 999px;
  background: var(--primary);
  color: #fff;
  font-weight: 700;
}
.button:hover { background: var(--primary-strong); }
.button-secondary { background: #fff; color: var(--navy); box-shadow: inset 0 0 0 1px var(--line); }

.status {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 12.5px;
  font-weight: 700;
}
.status::before { content: ""; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
.status-confirmed { background: var(--green-soft); color: var(--green); }
.status-pending { background: var(--amber-soft); color: var(--amber); }
.status-cancelled { background: var(--red-soft); color: var(--red); }
```

### Icons

Keep the SVG paths in one server-side helper, so pages ship no icon font and no extra request.

```php
function icon(string $name, string $class = ""): string
{
    static $paths = [
        "home" => '<path d="M3 11l9-8 9 8M5 10v10h14V10" />',
        "calendar" => '<rect x="3" y="5" width="18" height="16" rx="3" /><path d="M3 10h18M8 3v4M16 3v4" />',
        // ...one entry per icon, all drawn on a 24x24 grid with strokes only
    ];
    return '<svg class="icon ' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor"'
        . ' stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . $paths[$name] . '</svg>';
}
```

```css
.icon { flex: none; width: 20px; height: 20px; }
```

### Breakpoints

| Width | Change |
|---|---|
| 1180 px | Drop the wordmark subtitle; 4 stat cards become 2 per row |
| 1023 px | Sidebar hides; top bar and bottom tab bar appear |
| 960 px | Public nav collapses into a menu button |
| 900 px | Tables become stacked cards |
| 720 px | Phone type sizes, 16 px gutters, single column |
| 400 px | Smaller header button so the header stays on one row |

### Tables that become cards on phones

Put the column name on every cell in `data-label`. The phone layout reads it.

```html
<table class="responsive-table">
  <thead><tr><th>Pet</th><th>Date</th><th>Status</th></tr></thead>
  <tbody>
    <tr>
      <td data-label="Pet">Mochi</td>
      <td data-label="Date">Tue, 9:30 AM</td>
      <td data-label="Status"><span class="status status-confirmed">Confirmed</span></td>
    </tr>
  </tbody>
</table>
```

```css
@media (max-width: 900px) {
  .responsive-table thead { display: none; }
  .responsive-table, .responsive-table tbody, .responsive-table tr, .responsive-table td { display: block; }
  .responsive-table tr {
    margin-bottom: 12px;
    padding: 14px 16px;
    border: 1px solid var(--line);
    border-radius: var(--radius);
    background: var(--card);
  }
  .responsive-table td {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    padding: 6px 0;   /* reset any desktop td padding, or it wins */
    border: 0;
  }
  .responsive-table td::before {
    content: attr(data-label);
    color: var(--muted);
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
  }
}
```

### Hidden inputs

```css
.sr-only {
  position: absolute;
  width: 1px !important;   /* !important beats "input { width: 100% }" rules that widen the page */
  height: 1px;
  overflow: hidden;
  clip: rect(0 0 0 0);
  white-space: nowrap;
}
```

---

## Part 3: Dashboard shell

One shell serves every signed-in role. Only the nav list changes per role.

### Layout

```
Desktop (1024 px and up)         Tablet and phone (under 1024 px)
+---------+--------------------+ +------------------------------+
| logo    | Page heading       | | logo  name           (avatar)|  <- top bar, avatar menu
| name    | [stat][stat][stat] | +------------------------------+
|         |                    | | Page heading                 |
| GROUP   | panel     panel    | | [stat][stat]                 |
|  item   |                    | | [stat][stat]                 |
|  item 3 | table              | | cards                        |
| GROUP   |                    | +------------------------------+
|  item   |                    | | icon   icon   icon   icon    |  <- tab bar, 4 items
| account |                    | | label  label  label  label   |
| logout  |                    | +------------------------------+
+---------+--------------------+
```

### Nav definition

Keep the nav in one place. The sidebar, tab bar, and avatar menu all read it.

```php
function portal_nav(string $role): array
{
    // group => [path => [label, short label for the tab bar, icon]]
    return $role === "admin"
        ? [
            "Work" => [
                "/admin/dashboard.php" => ["Dashboard", "Home", "grid"],
                "/admin/requests.php" => ["Requests", "Requests", "inbox"],
                "/admin/schedule.php" => ["Schedule", "Schedule", "calendar"],
                "/admin/records.php" => ["Records", "Records", "folder"],
            ],
            "Settings" => [
                "/admin/reports.php" => ["Reports", "Reports", "chart"],
                "/admin/services.php" => ["Services", "Services", "tag"],
            ],
        ]
        : ["Menu" => [
            "/user/dashboard.php" => ["Dashboard", "Home", "home"],
            "/user/book.php" => ["Book", "Book", "plus"],
        ]];
}
```

Rules:

- The tab bar shows the first 4 items. Every other page goes in the avatar menu with Log out at the bottom in red. Do not add a "More" tab.
- A nav item with waiting work shows a count badge (pending requests). Read the count inside `try/catch`, so a failed count never breaks the page.
- The active item is a white pill on the primary sidebar (or a primary-soft pill on a white sidebar).

### Sidebar

```css
.sidebar {
  position: fixed;
  inset: 0 auto 0 0;
  display: flex;
  flex-direction: column;
  width: 260px;
  padding: 18px 14px 16px;
  overflow-y: auto;
  background: var(--primary);    /* solid, no gradient */
  color: #fff;
}
.sidebar-label {                  /* group title */
  margin: 16px 12px 6px;
  color: rgba(255, 255, 255, 0.6);
  font-size: 10.5px;
  font-weight: 700;
  letter-spacing: 0.14em;
  text-transform: uppercase;
}
.sidebar-nav a {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 12px;
  border-radius: 12px;
  color: rgba(255, 255, 255, 0.86);
}
.sidebar-nav a:hover { background: rgba(255, 255, 255, 0.12); color: #fff; }
.sidebar-nav a.active { background: #fff; color: var(--primary); font-weight: 700; }
.app { margin-left: 260px; padding: 28px 32px 48px; }
```

At the bottom of the sidebar, put an account card (avatar, name, role) and a Log out form with `data-confirm`.

### Tab bar (under 1024 px)

```css
@media (max-width: 1023px) {
  .sidebar { display: none; }
  .app { margin-left: 0; padding: 20px 16px 88px; }   /* room for the tab bar */
  .tabbar {
    position: fixed;
    inset: auto 0 0 0;
    z-index: 45;
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    padding: 6px 6px calc(6px + env(safe-area-inset-bottom));   /* iPhone home bar */
    border-top: 1px solid var(--line);
    background: rgba(255, 255, 255, 0.96);
  }
  .tabbar a {
    display: grid;
    justify-items: center;
    gap: 2px;
    padding: 6px 0;
    color: var(--muted);
    font-size: 11px;
    font-weight: 600;
  }
  .tabbar a.active { color: var(--primary); }
}
```

### Page heading

```html
<header class="page-heading">
  <span class="icon-tile"><!-- icon picked from the current path --></span>
  <div>
    <h1>Requests</h1>
    <p>3 waiting for review</p>   <!-- one short line, preferably with a number -->
  </div>
  <a class="button" href="?add=1">+ New</a>   <!-- the one main action on this page -->
</header>
```

### Stat cards

A dashboard opens with 4 stat cards. Each is a solid color with an icon tile, one big number, and an uppercase label of 1 to 3 words.

```html
<div class="metrics">
  <a class="stat stat-blue" href="/admin/requests.php">
    <span class="stat-icon"><!-- inbox icon --></span>
    <strong>12</strong>
    <span>Pending</span>
  </a>
  <!-- stat-sky, stat-violet, stat-green -->
</div>
```

```css
.metrics { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; }
.stat {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 4px;
  overflow: hidden;
  padding: 18px;
  border-radius: 18px;
  color: #fff;
}
.stat::before {        /* soft circle for depth, not a gradient */
  content: "";
  position: absolute;
  top: -28px;
  right: -28px;
  width: 104px;
  height: 104px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.13);
}
.stat-icon {
  display: grid;
  place-items: center;
  width: 40px;
  height: 40px;
  margin-bottom: 10px;
  border-radius: 12px;
  background: rgba(255, 255, 255, 0.2);
}
.stat strong { font-size: 30px; font-weight: 800; line-height: 1; }
.stat > span:last-child { font-size: 11.5px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; }
.stat-blue { background: var(--primary); }
.stat-green { background: var(--green); }
@media (max-width: 1180px) { .metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
```

### Below the stat cards

- Two panels side by side on desktop (for example "Today" and "Needs action"), stacked on phones.
- Each panel: section title with a small icon, at most 5 rows, and a "View all" link.
- A row shows a photo or avatar, a name, one muted detail line, and a status pill. Do not write sentences.
- Empty panel: icon, one sentence, one button.

### Record (detail) page

Top to bottom:

1. Hero card: date tile, reference number, status pill, main facts, photo.
2. Progress steps (for example Requested, Confirmed, Done).
3. Facts grid: label over value, 2 to 4 columns.
4. Contact card for staff: Call and Email buttons.
5. History timeline, newest first, one icon per event type.
6. Actions panel titled "What's next?" with the allowed actions only. Closed records show a closed state in place of actions.

---

## Part 4: Feedback, dialogs, and forms

All patterns use plain HTML attributes and one small script. Pages work without JavaScript; the script only adds polish.

### Toasts

The server renders the message after a redirect (`?saved=1` or a session flash). The script closes the toast and cleans the URL.

```html
<div class="toast-stack" aria-live="polite">
  <div class="toast toast-success" role="status" data-toast="2000">
    <span>Saved.</span>
    <button type="button" data-toast-close aria-label="Close">x</button>
  </div>
  <!-- errors: class="toast toast-error" role="alert" data-toast="6000" -->
</div>
```

```js
document.querySelectorAll("[data-toast]").forEach((toast) => {
  const hide = () => {
    toast.classList.add("is-leaving");          // CSS fades it out
    setTimeout(() => toast.remove(), 300);
  };
  const timer = setTimeout(hide, Number(toast.dataset.toast));
  toast.querySelector("[data-toast-close]")?.addEventListener("click", () => {
    clearTimeout(timer);
    hide();
  });
});
// Remove the flag so a refresh does not show the toast again.
const url = new URL(location.href);
if (["saved", "sent", "failed"].some((key) => url.searchParams.has(key))) {
  ["saved", "sent", "failed"].forEach((key) => url.searchParams.delete(key));
  history.replaceState(null, "", url);
}
```

```css
.toast-stack { position: fixed; top: 16px; right: 16px; z-index: 100; display: grid; gap: 8px; }
.toast { display: flex; gap: 12px; align-items: center; padding: 12px 16px; border-radius: 14px;
         background: #fff; box-shadow: var(--shadow); transition: opacity .3s, transform .3s; }
.toast-success { border-left: 4px solid var(--green); }
.toast-error { border-left: 4px solid var(--red); }
.toast.is-leaving { opacity: 0; transform: translateY(-8px); }
@media (max-width: 720px) { .toast-stack { left: 16px; right: 16px; } }
```

Show validation errors inside an open pop-up form, not as a toast behind it.

### Confirm dialog

Mark the form. The script shows a styled `<dialog>` and submits only after "yes".

```html
<form method="post" action="/logout.php"
      data-confirm="You will need to sign in again."
      data-confirm-title="Log out?"
      data-confirm-action="Log out">
  <button type="submit">Log out</button>
</form>
```

```js
function askToConfirm(form) {
  return new Promise((resolve) => {
    const dialog = document.createElement("dialog");
    dialog.className = "confirm-dialog";
    dialog.innerHTML = '<h2></h2><p></p><div class="confirm-actions">' +
      '<button type="button" class="button button-secondary" data-answer="no">Cancel</button>' +
      '<button type="button" class="button" data-answer="yes"></button></div>';
    // textContent, never innerHTML, for the messages
    dialog.querySelector("h2").textContent = form.dataset.confirmTitle || "Are you sure?";
    dialog.querySelector("p").textContent = form.dataset.confirm;
    dialog.querySelector('[data-answer="yes"]').textContent = form.dataset.confirmAction || "Yes, continue";
    dialog.addEventListener("click", (event) => {
      const answer = event.target.closest("[data-answer]")?.dataset.answer;
      if (answer || event.target === dialog) dialog.close(answer === "yes" ? "yes" : "no");
    });
    dialog.addEventListener("close", () => { resolve(dialog.returnValue === "yes"); dialog.remove(); });
    document.body.append(dialog);
    dialog.showModal();
    dialog.querySelector('[data-answer="no"]').focus();   // safe default
  });
}

document.querySelectorAll("form").forEach((form) => {
  form.addEventListener("submit", async (event) => {
    if (form.dataset.confirm && form.dataset.confirmed !== "true") {
      event.preventDefault();
      const submitter = event.submitter;
      if (await askToConfirm(form)) {
        form.dataset.confirmed = "true";
        form.requestSubmit(submitter);
      }
      return;
    }
    // Block double submits on POST forms.
    if (form.method.toLowerCase() === "post") {
      if (form.dataset.submitting === "true") return event.preventDefault();
      form.dataset.submitting = "true";
      event.submitter?.setAttribute("aria-busy", "true");
    }
  });
});
```

Log out must end the server session (destroy it and clear the cookie), then redirect to the right sign-in page for the role. Signed-in pages send `Cache-Control: no-store`, so the Back button cannot show them after log out.

### Pop-up form

```html
<a class="button" href="?add=1" data-open-dialog="item-dialog">Add item</a>
<dialog id="item-dialog" class="form-dialog" <?= $open ? "open" : "" ?>>
  <form method="post" enctype="multipart/form-data"> ... </form>
</dialog>
```

- Open the dialog on the server when the URL has `?add=1` or `?edit=ID`, or when the last submit failed validation.
- The script calls `showModal()` on click and prevents the link navigation.
- Close button, Esc, and a backdrop click all close the dialog.

### Password field

```html
<label for="password">Password</label>
<div class="password-field">
  <input id="password" name="password" type="password" placeholder="Enter your password"
         autocomplete="current-password" required />
  <button type="button" data-password-toggle aria-label="Show password" aria-pressed="false">
    <!-- eye icon --></button>
</div>
```

```js
document.querySelectorAll("[data-password-toggle]").forEach((button) => {
  const input = button.parentElement.querySelector("input");
  button.addEventListener("click", () => {
    const show = input.type === "password";
    input.type = show ? "text" : "password";
    button.setAttribute("aria-pressed", String(show));
    button.setAttribute("aria-label", show ? "Hide password" : "Show password");
    input.focus();
  });
});
```

Use `autocomplete="new-password"` on register forms.

### Auth pages

- Layout: page canvas, one centered card (max 440 px; register max 520 px with 2-column rows on desktop), a Back link at the top left. No site header or footer.
- Card content: logo, title ("Sign in"), one line, fields, primary button, one link to the other page ("No account? Create one").
- The staff or admin sign-in is its own URL (for example `/admin-login.php`) with its own look: a dark panel with 3 feature lines, then the form. `/admin` redirects there when no one is signed in.
- The server checks the role on sign-in. An owner account on the staff page gets "This is not a staff account. Owners sign in on the owner page."

### Photo upload

Phones produce 4 to 12 MB photos. Resize in the browser, then check again on the server.

```js
input.addEventListener("change", async () => {
  if (!input.files[0]) return;
  try {
    const bitmap = await createImageBitmap(input.files[0]);
    const scale = Math.min(1, 900 / Math.max(bitmap.width, bitmap.height));
    const canvas = document.createElement("canvas");
    canvas.width = Math.round(bitmap.width * scale);
    canvas.height = Math.round(bitmap.height * scale);
    const context = canvas.getContext("2d");
    context.fillStyle = "#fff";                       // transparent PNG to white
    context.fillRect(0, 0, canvas.width, canvas.height);
    context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
    const blob = await new Promise((resolve) => canvas.toBlob(resolve, "image/jpeg", 0.85));
    if (blob) {
      const files = new DataTransfer();
      files.items.add(new File([blob], "photo.jpg", { type: "image/jpeg" }));
      input.files = files.files;
    }
  } catch {
    // The browser cannot read this format; send the original and let the server decide.
  }
  preview.replaceChildren(Object.assign(new Image(), { alt: "", src: URL.createObjectURL(input.files[0]) }));
});
```

Server rules (trust boundary, never skip):

- Reject files over 2 MB.
- Read the real type from the bytes (`getimagesize` in PHP, `file-type` in Node). Allow JPEG, PNG, and WebP only. Never trust the file name or the browser's MIME type.
- Store the file in the database or object storage, not in the web root.
- Serve it through a route that checks the viewer may see it. Add `?v=<updated_at>` to the URL and send `Cache-Control: private, max-age=31536000, immutable`.

### PDF export

"Export" means a real PDF file built on the server (FPDF or Dompdf in PHP, PDFKit in Node, ReportLab in Python), sent with `Content-Disposition: attachment`. It contains: title and date range, summary boxes, one table per breakdown, the full list, and page numbers in the footer. Do not use `window.print()` of the page.

### Walk-in or phone entries

Staff often record people who have no account. The form offers choice cards: "New visitor" (type a name and phone) or "Registered owner" (search, then pick from photo cards). Make phone required only when the booking came by phone.

---

## Part 5: Fast loading website rules

### Overview

A page is fast when the server is close to its database, the HTML is small, and every static file is downloaded once and cached for a year. Fix speed in this order: distance, bytes, caching, repeated work. Measure before and after each change.

### When to Use

- "Make the website load faster", "use a cache mechanism", "best practices", "optimize".
- Before every production deploy.
- When photos, fonts, or scripts are added to a site.

### Checklist

1. **Region.** Run the server or function in the same region as the database. Each query pays the round trip, so a page with 5 queries across an ocean pays it 5 times. On Vercel set `"regions"` in `vercel.json` (for example `hnd1` for a Tokyo database).
2. **Static files cached for a year.** Send `Cache-Control: public, max-age=31536000, immutable` for `/assets/*`. Every file URL must change when the content changes: a content hash in the file name or `?v=<hash>`. Do not long-cache HTML.
3. **HTML headers.** Public pages: `Cache-Control: no-cache`. Signed-in pages: `Cache-Control: no-store`, so private data is not stored and Back after log out shows nothing. Never let a CDN cache a page that contains a session, a CSRF token, or a user's name.
4. **Images.** Resize to the displayed widths. Use WebP at quality 72 to 80. Add `srcset`, `sizes`, `width`, and `height` (no layout shift). Use `loading="lazy" decoding="async"` below the fold. Use `fetchpriority="high"` and no lazy loading on the hero image. Use the script in [Part 7](#part-7-image-optimizer-script) (save it as `optimize_images.py`).
5. **Fonts.** Self-host WOFF2 files, latin subset, `font-display: swap`. Preload only the 1 or 2 files used above the fold. Use at most 2 families. Do not link Google Fonts: it adds a DNS lookup and a connection, and it breaks a strict CSP.
6. **CSS and JS.** One CSS file in `<head>`, one JS file with `defer`. No framework or big library for a small feature. No third-party script in `<head>`.
7. **Server work.** Cache data that rarely changes (services, hours, settings) for a few minutes in a file or memory cache. Clear the cache when an admin saves. Load lists with one query (no N+1). Paginate long lists.
8. **Compression.** Vercel and Netlify use Brotli automatically. On a VPS, turn on Brotli or gzip for HTML, CSS, JS, SVG, and JSON.
9. **Small deploy.** Exclude original photos, docs, tests, and design files from the upload (`.vercelignore`) and from the function bundle (`excludeFiles`).
10. **Security header.** Send a CSP that allows `'self'` only. Self-hosting fonts, scripts, and images makes this possible, and it costs no speed.

Code for each item: [Part 6](#part-6-speed-recipes).

### Measure

```bash
# Network vs server time. Server time is about ttfb minus tls.
for i in 1 2 3; do
  curl -s -o /dev/null -w "connect=%{time_connect} tls=%{time_appconnect} ttfb=%{time_starttransfer} size=%{size_download}\n" https://example.com/
done
# Caching headers on a page and on an asset
curl -sI https://example.com/ | grep -i cache-control
curl -sI -H "Accept-Encoding: br" "https://example.com/assets/app.css?v=abc" | grep -iE "cache-control|content-encoding"
```

Targets: TTFB under 600 ms from the users' country on a warm instance, HTML under 60 KB, first load under 1 MB, Lighthouse mobile performance 90 or more, no layout shift from images.

### Common Mistakes

| Mistake | Result | Fix |
|---|---|---|
| Long cache on assets without a version in the URL | Users keep old CSS after a deploy | Content hash in the name or `?v=` |
| Long cache on HTML | Users never see the new page | `no-cache` for HTML |
| Hero image lazy-loaded | Largest paint waits for scroll logic | `fetchpriority="high"`, no `loading="lazy"` |
| Function in the US, database in Asia | 300 to 800 ms added per page | Same region |
| Raw phone photos uploaded and shown | 5 MB images, slow pages | Resize in the browser and on build |
| 6 font weights from Google Fonts | Blocking requests, CSP holes | 1 variable WOFF2, self-hosted |
| CDN caching a signed-in page | One user sees another user's data | `no-store` on private pages |
| Cache never cleared after admin edits | Old prices or hours stay visible | Clear the key in the save action |

---

## Part 6: Speed recipes

Copy the recipe that matches the stack. PHP and Node versions are given where they differ.

### Vercel config (region, asset caching, small bundle)

```json
{
  "regions": ["hnd1"],
  "functions": {
    "api/index.php": {
      "runtime": "vercel-php@0.9.0",
      "maxDuration": 30,
      "excludeFiles": "{node_modules/**,tests/**,docs/**,database/**,originals/**,assets/img/**,assets/fonts/**,.env*}"
    }
  },
  "routes": [
    { "src": "/assets/(.*)", "headers": { "Cache-Control": "public, max-age=31536000, immutable" }, "dest": "/assets/$1" },
    { "src": "/(.*)", "dest": "/api/index.php" }
  ]
}
```

- `excludeFiles` keeps static files out of the function. The CDN still serves them from `/assets`.
- Pick the region next to the database: `hnd1` Tokyo, `sin1` Singapore, `iad1` US East, `fra1` Frankfurt.
- Add original photos, docs, and tests to `.vercelignore` too, so the upload is small.

### nginx (VPS)

```nginx
location /assets/ {
    add_header Cache-Control "public, max-age=31536000, immutable";
    try_files $uri =404;
}
gzip on;
gzip_types text/css application/javascript image/svg+xml application/json;
```

### Versioned asset URLs

PHP:

```php
// <link rel="stylesheet" href="<?= asset('/assets/app.css') ?>">
function asset(string $path): string
{
    static $versions = [];
    return $path . "?v=" . ($versions[$path] ??= hash_file("crc32b", ROOT . $path));
}
```

Node (Express or a template helper):

```js
import { createHash } from "node:crypto";
import { readFileSync } from "node:fs";
const versions = new Map();
export function asset(path) {
  if (!versions.has(path)) {
    versions.set(path, createHash("sha1").update(readFileSync("public" + path)).digest("hex").slice(0, 8));
  }
  return `${path}?v=${versions.get(path)}`;
}
```

Bundlers such as Vite already put a hash in file names. Only the cache header is needed there.

### HTML cache header per route

```php
$private = str_starts_with($path, "/admin/") || str_starts_with($path, "/account/")
    || in_array($path, ["/login.php", "/register.php", "/logout.php"], true);
header("Cache-Control: " . ($private ? "no-store" : "no-cache"));
header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:; style-src 'self'; "
    . "font-src 'self'; script-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
```

Add only the third-party origins that the page really uses, for example `frame-src https://www.google.com` for a Maps embed.

### Images

1. Keep originals outside the deployed folder (for example `originals/`).
2. List each image once in `images.json`:

```json
{
  "hero": { "src": "clinic-visit.jpg", "widths": [560, 880], "crop": [4, 5], "quality": 80 },
  "logo": { "src": "logo.png", "widths": [96, 192] },
  "team": { "src": "team.jpg", "widths": [480, 960], "crop": [0.0, 0.1, 1.0, 0.9] }
}
```

`crop` is optional: `[w, h]` center-crops to that aspect ratio; `[left, top, right, bottom]` as 0 to 1 fractions crops a fixed box.

3. Run the bundled script (needs `pip install Pillow`):

```bash
python optimize_images.py \
  --config images.json --source originals --out public/assets/img \
  --url-prefix /assets/img --manifest src/images.json
```

It writes `name-WIDTH.HASH.webp` files and a manifest:

```json
{ "hero": { "w": 880, "h": 1100, "src": { "560": "/assets/img/hero-560.ab12cd34ef.webp", "880": "..." } } }
```

4. Render with a helper that reads the manifest:

```php
function img(string $name, string $sizes = "100vw"): string
{
    static $images;
    $images ??= json_decode(file_get_contents(ROOT . "/src/images.json"), true);
    $image = $images[$name];
    $set = [];
    foreach ($image["src"] as $width => $url) {
        $set[] = "$url {$width}w";
    }
    return sprintf('src="%s" srcset="%s" sizes="%s" width="%d" height="%d"',
        end($image["src"]), implode(", ", $set), htmlspecialchars($sizes), $image["w"], $image["h"]);
}
```

```html
<img <?= img("hero", "(max-width: 1100px) 80vw, 460px") ?> alt="Vet checking a cat" fetchpriority="high" />
<img <?= img("team", "(max-width: 720px) 92vw, 33vw") ?> alt="Our team" loading="lazy" decoding="async" />
```

### Fonts

1. Download WOFF2 files (google-webfonts-helper or Fontsource) for the latin subset. Use a variable font when one exists: one file covers every weight.
2. Put them in `/assets/fonts/`.

```css
@font-face {
  font-family: "Sora";
  font-style: normal;
  font-weight: 300 800;          /* variable font: one file for all weights */
  font-display: swap;
  src: url(/assets/fonts/sora-latin.woff2) format("woff2");
  unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+2000-206F, U+20AC, U+2122, U+2212;
}
```

```html
<link rel="preload" href="/assets/fonts/sora-latin.woff2" as="font" type="font/woff2" crossorigin />
```

`crossorigin` is required on font preloads, even for the same origin. Without it the browser downloads the font twice.

### Server data cache

A file cache works on one server and on serverless functions (each warm instance keeps its own copy in `/tmp`).

```php
function remember(string $key, int $ttl, callable $load): mixed
{
    $file = sys_get_temp_dir() . "/app-cache-" . $key . ".json";
    if (is_file($file) && filemtime($file) > time() - $ttl) {
        $cached = json_decode((string) @file_get_contents($file), true);
        if (is_array($cached) && array_key_exists("value", $cached)) {
            return $cached["value"];
        }
    }
    $value = $load();
    $temporary = $file . "." . bin2hex(random_bytes(4));   // write then rename: readers never see half a file
    if (@file_put_contents($temporary, json_encode(["value" => $value])) !== false && !@rename($temporary, $file)) {
        @unlink($temporary);
    }
    return $value;
}

function forget(string $key): void
{
    @unlink(sys_get_temp_dir() . "/app-cache-" . $key . ".json");
}

// Page:   $data = remember("landing", 300, fn () => ["services" => services(), "hours" => hours()]);
// Admin save action:  forget("landing");
```

Serverless note: `forget()` clears only the instance that ran the save. Other warm instances keep old data until the TTL ends, so keep the TTL short (about 5 minutes). Use Redis or a database table when edits must show at once on every instance.

Cache only data that is the same for every visitor. Never cache per-user data under a shared key.

### Database

- One query per list: join or `WHERE id = ANY(?)`, never a query inside a loop.
- Add an index for every column used in `WHERE` or `ORDER BY` on large tables.
- Use the provider's connection pooler (for example the Supabase pooler on port 6543) from serverless functions.
- Select only the columns the page shows. Do not load image bytes in list queries.

---

## Part 7: Image optimizer script

Save as `optimize_images.py`. Needs Python 3.9+ and `pip install Pillow`. Check it with `python optimize_images.py --self-test`.

```python
"""Build optimized, cache-forever images for a website.

Reads originals listed in a JSON config, writes resized WebP files with a content
hash in their names, and writes a JSON manifest that templates read to build
src, srcset, width and height. The hash changes whenever an image changes, so the
files can be served with "Cache-Control: public, max-age=31536000, immutable".

Usage:
  python optimize_images.py --config images.json --source originals \
      --out public/assets/img --url-prefix /assets/img --manifest src/images.json
  python optimize_images.py --self-test

Config format (crop and quality are optional):
  {"hero": {"src": "visit.jpg", "widths": [560, 880], "crop": [4, 5], "quality": 80}}
  crop [w, h]                 center-crops to that aspect ratio
  crop [left, top, right, bottom] as 0..1 fractions crops a fixed box

Needs Pillow:  pip install Pillow
"""

import argparse
import hashlib
import io
import json
import sys
import tempfile
from pathlib import Path

from PIL import Image


def cropped(image, crop):
    if not crop:
        return image
    width, height = image.size
    if len(crop) == 4:
        left, top, right, bottom = crop
        return image.crop((round(left * width), round(top * height), round(right * width), round(bottom * height)))
    ratio = crop[0] / crop[1]
    if width / height > ratio:
        new_width = round(height * ratio)
        left = (width - new_width) // 2
        return image.crop((left, 0, left + new_width, height))
    new_height = round(width / ratio)
    top = (height - new_height) // 2
    return image.crop((0, top, width, top + new_height))


def build(config, source, out, url_prefix, manifest_path):
    out.mkdir(parents=True, exist_ok=True)
    for old in out.glob("*.webp"):  # remove stale versions; the folder holds only generated files
        old.unlink()
    manifest = {}
    for name, item in config.items():
        with Image.open(source / item["src"]) as original:
            mode = "RGBA" if original.mode in ("RGBA", "LA", "P") else "RGB"
            image = cropped(original.convert(mode), item.get("crop"))
        sources = {}
        for width in item["widths"]:
            width = min(width, image.width)  # never upscale
            height = round(image.height * width / image.width)
            resized = image.resize((width, height), Image.LANCZOS) if width < image.width else image
            buffer = io.BytesIO()
            resized.save(buffer, "WEBP", quality=item.get("quality", 72), method=6)
            data = buffer.getvalue()
            digest = hashlib.sha1(data).hexdigest()[:10]
            target = out / f"{name}-{width}.{digest}.webp"
            target.write_bytes(data)
            sources[str(width)] = f"{url_prefix.rstrip('/')}/{target.name}"
        manifest[name] = {"w": width, "h": height, "src": sources}
        print(f"{name}: {', '.join(sources)}")
    manifest_path.parent.mkdir(parents=True, exist_ok=True)
    manifest_path.write_text(json.dumps(manifest, indent=2) + "\n", encoding="utf-8")
    return manifest


def self_test():
    with tempfile.TemporaryDirectory() as folder:
        root = Path(folder)
        (root / "src").mkdir()
        Image.new("RGB", (1200, 800), "#0a4bff").save(root / "src" / "wide.jpg")
        config = {
            "wide": {"src": "wide.jpg", "widths": [300, 2000], "crop": [1, 1]},
            "box": {"src": "wide.jpg", "widths": [100], "crop": [0, 0, 0.5, 0.5]},
        }
        manifest = build(config, root / "src", root / "out", "/assets/img", root / "images.json")
        assert manifest["wide"]["src"].keys() == {"300", "800"}, "widths capped at the cropped size"
        assert (manifest["wide"]["w"], manifest["wide"]["h"]) == (800, 800), "1:1 center crop"
        assert (manifest["box"]["w"], manifest["box"]["h"]) == (100, 67), "fraction crop keeps ratio"
        assert len(list((root / "out").glob("*.webp"))) == 3
        assert json.loads((root / "images.json").read_text()) == manifest
    print("self-test passed")


def main():
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("--config", type=Path)
    parser.add_argument("--source", type=Path)
    parser.add_argument("--out", type=Path)
    parser.add_argument("--url-prefix", default="/assets/img")
    parser.add_argument("--manifest", type=Path)
    parser.add_argument("--self-test", action="store_true")
    args = parser.parse_args()
    if args.self_test:
        return self_test()
    if not (args.config and args.source and args.out and args.manifest):
        parser.error("--config, --source, --out and --manifest are required")
    config = json.loads(args.config.read_text(encoding="utf-8"))
    build(config, args.source, args.out, args.url_prefix, args.manifest)


if __name__ == "__main__":
    sys.exit(main())
```

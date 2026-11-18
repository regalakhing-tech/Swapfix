# SwapFix UI Assets

## Generated Images

- `public/assets/images/swapfix-logo-transparent.png`: the supplied logo with its white background removed by built-in ImageGen. The original file in Downloads is unchanged.
- `originals/swapfix-gadgets-hero.png`: the full-resolution, newly generated phone-and-gadget hero image. Built-in ImageGen created a premium studio composition of royal-blue and graphite phones, a tablet and wireless earbuds on a pale-blue plinth, with a blue-to-teal photographic backdrop. The prompt requested complete devices, realistic materials, high detail, no UI, no text, no logos and no watermark.

The final website uses resized, content-hashed WebP hero variants and an optimized transparent PNG logo. PNG preserves the logo's clean alpha edges. Image dimensions and responsive sources live in `resources/data/images.json`.

## User-Supplied Catalog Photos

Copied from `C:\Users\janna\Downloads\RussDtech-main\RussDtech-main\images`:

- `blue-iphone-pro-back.webp`
- `silver-iphone-pro-back.webp`
- `space-gray-ipad-pro-back.webp`
- `acer-predator-laptop-open.webp`

These originals remain unchanged. They are optimized locally for the illustrative catalog. Names, conditions, locations and prices shown in the preview are sample content, not claims about actual listings or inventory.

## Local Font

`public/assets/fonts/sora-latin.woff2` is reused from the supplied Showlio project. It is self-hosted with `font-display: swap` and a versioned preload, so the page makes no Google Fonts request.

## Rebuild Optimized Images

No Node build is required to run or deploy the PHP app. The final optimized assets are already present. To regenerate them during design work with Node 24 or newer:

```powershell
npm install --prefix .tools --no-audit --no-fund sharp@0.35.5
node scripts/optimize-ui-assets.mjs
```

The script keeps alpha for the logo, creates two WebP sizes per photograph, hashes each output and updates the image manifest. `originals/` and `.tools/` are excluded from deployment.

## Optional DOM Unit Checks

With the PHP server running:

```powershell
npm install --prefix .tools --no-audit --no-fund jsdom
node tests/ui-behavior.mjs
```

These are code-based DOM checks. They do not open a browser or establish visual correctness at responsive widths.

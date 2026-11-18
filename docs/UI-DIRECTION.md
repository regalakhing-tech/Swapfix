# SwapFix UI Direction

## Visual Thesis

A bright, practical gadget marketplace with royal-blue actions, teal repair accents, dark-navy typography, crisp device photography, and generous white space. The supplied SwapFix logo determines the brand palette.

## References

- `FINALPROJECT_DOHINOG/docs/WEB-APP-PLAYBOOK.md`: solid fills, shared color tokens, 1.8-stroke inline icons, consistent spacing, responsive controls, accessible dialogs, local fonts, small images and versioned assets.
- DOHINOG UI: balanced section spacing, clear actions and calm surface hierarchy.
- Showlio UI: strong product imagery, compact navigation, expressive headings and restrained interaction feedback.

The implementation combines these qualities in a new SwapFix layout. It does not copy either application's branding, private configuration or account data.

## Content Plan

1. Branded header and a product-led introduction with direct browse/repair actions.
2. Three clear paths: Buy & Sell, Exchange and Repair.
3. A browsable preview catalog with categories, search, sorting and detail dialogs.
4. A repair explainer and a concise three-step workflow.
5. A closing action and compact footer with an honest preview disclosure.

## Interaction Thesis

- A short, staggered introduction and subtle image movement establish hierarchy.
- Sections reveal on scroll while remaining readable without JavaScript.
- Product hovers, accessible modal transitions and dismissible toasts clarify actions.
- Reduced-motion preferences disable nonessential animation.

## Scope

This is the public interface of the PHP starter. Catalog records, prices and service estimates are explicitly illustrative. Server-rendered browsing works without JavaScript; browser-local saved items do not create marketplace transactions. Auth, real publishing, messages, quotations and database-backed activity remain the README roadmap.

## Logo Asset

Built-in ImageGen produced `public/assets/images/swapfix-logo-transparent.png` from the user-supplied PNG. Prompt: remove the white background to genuine alpha transparency; preserve the existing navy phone, teal wrench, blue pixels, exact SWAPFIX lettering, proportions and colors; add no elements, shadows or matte. Optimized site assets preserve the generated alpha. A second built-in generation created the phone-and-gadget hero photograph. The user's later request for a blue-to-teal image backdrop applies to that raster photograph; CSS fills remain solid. Catalog photos come from the supplied RussDtech image folder.

## Verification Boundaries

Use PHP tests, HTTP requests, HTML asset checks, JavaScript checks and image metadata to verify available behavior. Computer Use stopped because it could not reliably determine the current browser URL. No alternative browser automation is used to bypass that stop; visual screenshots at the playbook's five viewport widths remain an external verification step.

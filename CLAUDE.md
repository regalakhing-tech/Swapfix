# SwapFix Implementation Instructions

The user has approved the root README, requested full feature implementation, chosen PHP + Supabase + PHPMailer + Vercel, and explicitly requested Claude Opus 5.5 at xhigh effort for design and implementation. Continue implementing and testing the approved application without adding another design-approval gate.

Read `README.md`, `docs/WEB-APP-PLAYBOOK.md`, `docs/UI-DIRECTION.md`, the current PHP structure, and module guides. Preserve the supplied SwapFix logo, generated hero, local fonts and user-supplied product images. Follow the playbook's solid CSS colors, icon system, responsive shells, accessible dialogs and efficient asset loading.

Work only in this repository. Do not read `.env`, credential stores, browser profiles, sibling applications, or account tokens. Never print secrets. Do not run Git operations, connect/apply SQL to hosted Supabase projects, deploy, operate browsers, send email or messages, or change account settings: the coordinating agent owns those operations.

Implement real backend behavior and UI rather than marking sample records as live. Keep Supabase user-scoped requests under RLS. Store authorization in database records, not mutable user metadata. No PHP file sessions or permanent local uploads on Vercel. All writes require verified identity, ownership/participant checks and CSRF protection where browser cookies are used. State changes and approval decisions must be atomic and guarded in the database.

Use meaningful tests for ownership, state transitions, quotation approvals, message participants and review eligibility. Test locally without sending real email or depending on production credentials. Keep development dependencies under ignored `.tools/` when needed. Update setup documentation to describe what actually works and any external configuration still needed.

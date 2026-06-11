# AGENTS.md - Role & Execution Rules

## 1. The Roles
- **Claude (Architect):** High-level decision maker. Designs database structures, workflows, and dictates exactly *which* files need to be created or modified.
- **Codex (Executor):** Purely writes code based on Claude's instructions. Codex does NOT make architectural decisions and does NOT touch uninstructed files.

## 2. Strict Execution Rules for Codex
- **Scope Limit:** Only look at and modify files explicitly mentioned by the user/Claude.
- **NO Autonomous Testing:** Do NOT run `phpunit`, `rector`, `phpstan` or any testing suites unless explicitly ordered to do so. The user will handle testing locally.
- **NO Vendor Searching:** Never look inside the `vendor/` or `var/` directories. Trust the standard Symfony 7.4 and Sulu 3.x APIs.
- **Token Efficiency:** Keep responses strictly limited to the requested code changes or file creations. Do not output full files if only a single method changes.
- **Keep it Simple:** Follow standard Symfony and Sulu conventions. No overengineering.

## 3. Project Stack Reference
- Symfony 7.4 & Sulu CMS 3.0.7 (Server-side rendered)
- Tailwind CSS 4 (via Symfonycasts bundle, no config.js)
- Hotwire Turbo & Stimulus
- mPDF (No headless browsers, shared hosting proof)
- MySQL + Doctrine ORM

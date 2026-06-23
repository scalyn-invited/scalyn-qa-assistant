# Scalyn QA Assistant — Implementation Tracker

> **Version:** 1.0.2
> **Last Updated:** 2026-06-11
> **Status:** Production Ready

---

## Changelog

### v1.0.2 — 2026-06-11 (AI Providers + UI Redesign + Final Polish)

**Architecture:**
- Replaced hardcoded AI provider array with extensible Provider Registry (`AI_Provider_Registry`)
- New providers can be registered via `scalyn_qa_register_ai_providers` action hook — zero core modifications needed
- Added `AI_Health_Monitor` class for per-provider health tracking and usage statistics
- Added support for priority chain: Primary → Fallback → Secondary Fallback
- Added `get_slug()` abstract method to `AI_Provider` base class
- Added `supports_custom_endpoint()` and `set_endpoint()` to `AI_Provider` for local AI support
- `generate_meta()` now iterates the full priority chain instead of just primary+fallback
- Health metrics recorded on every AI request (success time, failure reason)

**New REST Endpoint:**
- `GET /ai/health` — Returns per-provider health: status, success rate, avg response time, last error

**Bug Fixes:**
- Fixed API key field showing corrupted masked values on reload
- Fixed "Show" button to fetch real masked key from server (`sk-proj••••••••Rw_kA`)
- Fixed "Hide" button not restoring editable state
- Fixed role (Primary/Fallback) not persisting on page reload
- Fixed server accepting masked bullet characters as new keys (now detected and preserved)
- Fixed `enable_ai` setting not syncing between general settings and AI config

**Final UI Polish (Holistic Pass):**
- Global WP admin padding fix — `#wpcontent` now gets consistent 20px padding on plugin pages
- Branded header reduced from 36px logo to 24px, compact padding, smaller title (0.9375rem)
- Score circles enlarged to 80px with 1.75rem numbers and muted percent units
- Score cards: equal height with 1.5rem padding, uppercase muted labels
- Knowledge Center: compact horizontal items with hover ring focus effect
- Page headers: toolbar-style layout with flex between title and action buttons
- Empty states: icon in rounded background, tighter max-width (280px), action button slot
- Flush card variant for edge-to-edge table display
- Custom select chevron SVG replacing browser defaults
- Responsive: all grids collapse properly, branded header wraps on mobile

**UI/UX Redesign (Complete Rewrite):**
- Complete CSS rewrite (2,133 lines) — modern SaaS design system inspired by Linear/Notion/Vercel
- CSS custom properties design token system for colors, typography, spacing, radii, shadows
- Full-width layout — removed 1400px max-width constraint, uses available screen width
- Removed top empty space from all admin pages
- Dark slate gradient header (replaces navy blue)
- Score cards: equal height, large numbers (2.5rem), conic-gradient circles, centered layout
- Tables: minimal design with slate-50 headers, uppercase tracking, subtle row separators
- Cards: 12px radius, 1px border, minimal shadow, no hover effect (SaaS aesthetic)
- Buttons: proper focus-visible rings with 2px offset, ghost variant, consistent sizing
- Knowledge Center: horizontal layout (icon left of title), clickable rows, 2-column grid
- Audit list: flush card (edge-to-edge table), page header with actions bar
- Launch checklist: left-border accent on warning/fail items, compact check list
- Check items: 6px status dots, left border accents, compact rows
- Badges: 0.6875rem pill shape with semantic colors
- Tabs: bottom-border indicator only (no background change)
- Alerts: 3px left border only (not full border)
- Empty states: centered max-width 320px with muted icons
- Responsive: 4→2→1 column grid breakpoints, table horizontal scroll on mobile
- Form tables stack on mobile, field descriptions at 0.75rem
- Print styles: hide interactive elements, remove shadows
- Respects prefers-reduced-motion media query

**New Providers:**
- OpenRouter provider — access Claude, GPT, Gemini, DeepSeek, Mistral, Llama, Qwen through single API key
- Custom Endpoint provider — connect to any OpenAI-compatible API (Ollama, LM Studio, internal APIs)
- Dynamic settings UI: standard providers show model dropdown, custom shows endpoint URL + model name + headers
- Provider documentation links in each card title
- Custom endpoint fields: URL, model name (free text), custom headers (JSON)
- Custom endpoint URL validated with `esc_url_raw()` before storage

**Branding:**
- Moved logo from root `icon.png` to `assets/images/scalyn-icon.png`
- Created SVG menu icon (`assets/images/scalyn-menu-icon.svg`) — infinity/wave symbol
- Admin sidebar menu now uses base64-encoded SVG icon instead of dashicons
- Dashboard header replaced with branded gradient header (navy blue, logo, title, description, version badge)
- Added branded empty state component with subtle logo accent
- CSS: branded header responsive down to mobile
- Root `icon.png` removed — plugin root stays clean

**Documentation:**
- Slimmed `README.md` to landing page with link to `IMPLEMENTATION.md`
- `IMPLEMENTATION.md` is now the single source of truth

**Files Added:**
- `includes/ai/class-ai-provider-registry.php`
- `includes/ai/class-ai-health-monitor.php`
- `assets/images/scalyn-icon.png` — Official Scalyn logo
- `assets/images/scalyn-menu-icon.svg` — Simplified SVG for admin menu
- `includes/ai/class-openrouter-provider.php` — OpenRouter multi-model provider
- `includes/ai/class-custom-endpoint-provider.php` — Custom endpoint provider for local/self-hosted AI

---

### v1.0.1 — 2026-06-11 (GitHub Updater + UI Polish)

**UI/UX Improvements:**
- Added consistent spacing system: headers, cards, forms, buttons all use uniform rhythm
- Fixed button contrast — white text on all colored buttons, proper focus-visible rings (WCAG 2.1 AA)
- Added ghost button and link-style button variants
- Added status indicator component (dot + label + value + recommendation)
- Rebuilt System Information page with color-coded status per row (green/yellow/red)
- Added PHP version, WordPress version, MySQL version, memory, and execution time thresholds
- PHP extensions now show purpose description and red/green installed status
- Improved table styling — striped rows, uppercase headers, compact variant
- Improved tab navigation — 2px bottom border, active state, accessible aria-selected
- Added toggle switch component for checkbox replacements
- Added empty state component for pages with no data
- Improved pagination component with styled page numbers
- Improved tooltip positioning and contrast (dark background, white text)
- Added alert component variants (success/warning/danger/info) with icon support
- Added reduced-motion media query for accessibility
- Added mobile-responsive form tables (stacked layout on small screens)
- Added print styles (hides buttons/tabs, removes shadows)
- Added WordPress admin page overrides for consistent padding
- Constrained plugin layout to max-width 1400px

**New Features:**
- Public GitHub release-based updater (no WordPress.org dependency)
- WordPress native update flow: update notices appear on Plugins screen with changelog
- Manual "Check for Updates" button in Settings → Advanced
- GitHub Updates settings section: Repository Owner, Repository Name, optional GitHub Token
- Optional GitHub token support for higher API rate limits / private repos
- GitHub token stored encrypted (same encryption as AI API keys)
- Automatic plugin folder naming fix after update (`upgrader_post_install`)
- WordPress.org update checks disabled for this plugin (`http_request_args` filter)

**Files Added:**
- `includes/updates/class-github-updater.php` — Core updater class
- `includes/updates/index.php` — Security stub

**Files Modified:**
- `includes/class-plugin.php` — Registers GitHub_Updater service
- `includes/class-activator.php` — Added github_owner, github_repo, github_token defaults
- `includes/rest/class-settings-controller.php` — Added POST /updates/check and POST /updates/save-token endpoints
- `includes/Admin/class-settings-page.php` — Added GitHub defaults
- `templates/settings/advanced.php` — Added GitHub Updates card section
- `assets/js/admin-settings.js` — Added Check for Updates and Save GitHub Settings handlers

---

### v1.0.0 — 2026-06-11 (Initial Release)

**Security Fixes:**
- Fixed Gemini provider API key exposure (moved from URL to Authorization header)
- Enabled SSL verification in link checker (was disabled)
- Added `$wpdb->prepare()` to Score_Controller SQL query
- Added per-post capability checks (`edit_post`) to all post-specific REST endpoints
- Replaced hardcoded encryption salt fallback with site-specific composite key
- Removed internal error disclosure from wizard install endpoint
- Replaced deprecated `openssl_random_pseudo_bytes()` with `random_bytes()`

**Bug Fixes:**
- Fixed AIOSEO integration (now queries `aioseo_posts` custom table instead of postmeta)
- Fixed metabox auto-scan to skip link checker (prevents 500s timeouts on save)
- Added snapshot retention limit (max 50 per post, configurable via filter)
- Preserved cached link results during auto-scan to maintain score accuracy

**New Features:**
- Settings → Advanced tab with data management options
- "Delete all plugin data on uninstall" toggle (default: disabled)
- Settings Export/Import (JSON format) with automatic pre-import backup
- Settings Rollback to pre-import state
- AI daily request rate limiting (configurable: 100/500/1000/Unlimited)
- AI usage logging (user, provider, model, post, date, success/failure)
- AI log viewer in Advanced settings tab
- System Information page (plugin, environment, SEO/AI, data, PHP extensions, migration log)
- Version upgrade/migration system with migration log
- Debug mode with categorized logging (AI, Link Checker, REST API)
- Debug log viewer with category filter and clear button

**Architecture Improvements:**
- Replaced ALL regex-based HTML parsing with DOMDocument/DOMXPath via shared HTML_Parser class
- Centralized HTML parsing (images, links, headings, buttons, forms, popups) in one reusable parser
- Eliminated 25+ fragile regex patterns across 4 analyzer files
- Improved Elementor, Gutenberg, and Classic Editor content parsing accuracy

---

## Summary

| Category | Total Items | Implemented | Remaining | Progress |
|---|---|---|---|---|
| Infrastructure | 12 | 12 | 0 | 100% |
| Feature 1: Page-Level SEO Checklist | 8 | 8 | 0 | 100% |
| Feature 2: Heading Structure Validator | 5 | 5 | 0 | 100% |
| Feature 3: Smart Link Checker | 8 | 8 | 0 | 100% |
| Feature 4: Button & Form Validation | 5 | 5 | 0 | 100% |
| Feature 5: Page Scoring System | 4 | 4 | 0 | 100% |
| Feature 6: Front-End Admin Toolbar | 4 | 4 | 0 | 100% |
| Feature 7: Website Launch Checklist | 5 | 5 | 0 | 100% |
| Feature 8: SEO Plugin Installation Wizard | 4 | 4 | 0 | 100% |
| Feature 9: AI Metadata Settings | 6 | 6 | 0 | 100% |
| Feature 10: AI Metadata + SEO Integration | 8 | 8 | 0 | 100% |
| Feature 11: Quick Fix Actions | 5 | 5 | 0 | 100% |
| Feature 12: Project Completion Score | 3 | 3 | 0 | 100% |
| Feature 13: Ignore Rules | 4 | 4 | 0 | 100% |
| Feature 14: QA Notes & Comments | 3 | 3 | 0 | 100% |
| Feature 15: Website Standards Templates | 4 | 4 | 0 | 100% |
| Feature 16: Plugin Conflict Detection | 3 | 3 | 0 | 100% |
| Feature 17: Audit Snapshots | 4 | 4 | 0 | 100% |
| Feature 18: Tooltips & SEO Guidance | 3 | 3 | 0 | 100% |
| Feature 19: Knowledge Center | 3 | 3 | 0 | 100% |
| Post-Audit: Security Hardening | 7 | 7 | 0 | 100% |
| Post-Audit: New Features | 6 | 6 | 0 | 100% |
| Release: Version Migration System | 2 | 2 | 0 | 100% |
| Release: Settings Backup & Rollback | 3 | 3 | 0 | 100% |
| Release: System Information Page | 2 | 2 | 0 | 100% |
| Release: Debug Mode & Logging | 4 | 4 | 0 | 100% |
| Release: DOMDocument HTML Parsing | 5 | 5 | 0 | 100% |
| Release: GitHub Updater | 5 | 5 | 0 | 100% |
| UI/UX: Spacing & Layout | 4 | 4 | 0 | 100% |
| UI/UX: Button Accessibility | 3 | 3 | 0 | 100% |
| UI/UX: Status Indicators | 3 | 3 | 0 | 100% |
| UI/UX: System Info Rebuild | 2 | 2 | 0 | 100% |
| UI/UX: Components & Responsive | 5 | 5 | 0 | 100% |
| AI: Provider Registry & Extensibility | 4 | 4 | 0 | 100% |
| AI: Health Monitor & Usage Stats | 3 | 3 | 0 | 100% |
| AI: Priority Chain & Settings Fixes | 4 | 4 | 0 | 100% |
| AI: OpenRouter Provider | 2 | 2 | 0 | 100% |
| AI: Custom Endpoint Provider | 3 | 3 | 0 | 100% |
| UI: Complete CSS Redesign | 6 | 6 | 0 | 100% |
| UI: Template Updates | 5 | 5 | 0 | 100% |
| **TOTAL** | **178** | **178** | **0** | **100%** |

---

## Infrastructure

- [x] Main plugin file (`scalyn-qa-assistant.php`) — Bootstrap, constants, autoloader
- [x] `composer.json` — PSR-4 autoloading + classmap configuration
- [x] `package.json` — NPM dependencies (Tailwind, PostCSS)
- [x] `tailwind.config.js` — Tailwind with `sqt-` prefix, scoped to plugin
- [x] `postcss.config.js` — PostCSS pipeline
- [x] Plugin class (`includes/class-plugin.php`) — Main orchestrator singleton
- [x] Activator (`includes/class-activator.php`) — Activation hooks
- [x] Deactivator (`includes/class-deactivator.php`) — Deactivation hooks
- [x] Singleton trait (`includes/traits/trait-singleton.php`)
- [x] Has_Hooks trait (`includes/traits/trait-has-hooks.php`)
- [x] Uninstall handler (`uninstall.php`)
- [x] Security index files (`index.php` stubs in all 28 directories)

---

## Feature 1: Page-Level SEO Checklist

- [x] Metabox appears on post/page edit screen (`includes/Admin/class-metabox.php`)
- [x] SEO checks: meta title, meta description, featured image, alt text, internal links, external links
- [x] Content checks: H1 exists, heading hierarchy, empty headings
- [x] Functionality checks: buttons, forms, links
- [x] Each check shows pass/warning/fail status with icon
- [x] Results persist in `wp_postmeta` (`_scalyn_qa_scan_results`)
- [x] Results reload on page refresh
- [x] Auto-scan triggers on post save (`save_post` hook)

---

## Feature 2: Heading Structure Validator

- [x] Heading analyzer class (`includes/analyzers/class-heading-analyzer.php`)
- [x] Detects missing H1, multiple H1, empty headings, skipped hierarchy
- [x] Heading tree visualization in audit view
- [x] Actionable recommendations per issue
- [x] Works with Gutenberg, Classic Editor, and Elementor content

---

## Feature 3: Smart Link Checker

- [x] Link checker class (`includes/analyzers/class-link-checker.php`)
- [x] Scans current page only (not site-wide)
- [x] Checks internal, external, mailto, tel, download, button links
- [x] Reports broken links with HTTP status code and severity
- [x] Uses `wp_remote_head()` with `GET` fallback
- [x] Results cached via transients (24h default)
- [x] Manual rescan button clears cache and re-checks
- [x] SSRF protection (blocks private IP ranges)

---

## Feature 4: Button & Form Validation

- [x] Form/Button analyzer class (`includes/analyzers/class-form-button-analyzer.php`)
- [x] Detects empty buttons (no text, no aria-label)
- [x] Detects placeholder `#` links on buttons/anchors
- [x] Detects forms without submit buttons
- [x] Detects popup triggers and validates popup targets exist

---

## Feature 5: Page Scoring System

- [x] Scoring engine class (`includes/scoring/class-scoring-engine.php`)
- [x] Calculates SEO, Content, Functionality scores (0-100)
- [x] Calculates weighted Overall score (SEO 40%, Content 35%, Functionality 25%)
- [x] Color-coded status: green (80-100), yellow (50-79), red (0-49)

---

## Feature 6: Front-End Admin Toolbar

- [x] Toolbar class (`includes/Admin/class-toolbar.php`)
- [x] Shows current page score and issue count in admin bar
- [x] Rescan button triggers AJAX scan (`assets/js/toolbar.js`)
- [x] Not visible to non-admin/logged-out users (zero front-end impact)

---

## Feature 7: Website Launch Checklist

- [x] Launch checker class (`includes/launch/class-launch-checker.php`)
- [x] Checks: SEO plugin installed, sitemap exists, GA4/GTM configured, SSL, favicon, contact page, privacy policy
- [x] Launch checklist admin page with results display (`templates/launch/checklist.php`)
- [x] Launch-ready / needs-review status
- [x] Manual rescan button

---

## Feature 8: SEO Plugin Installation Wizard

- [x] Admin notice when no SEO plugin detected
- [x] Offers Rank Math, Yoast SEO, or Skip (`templates/settings/wizard.php`)
- [x] Installs and activates chosen plugin via WordPress API
- [x] Dismissible (persists in `wp_options`, does not reappear)

---

## Feature 9: AI Metadata Settings

- [x] AI Manager class (`includes/ai/class-ai-manager.php`)
- [x] Provider classes: OpenAI, Claude, Gemini (`includes/ai/`)
- [x] Settings page for provider configuration (`templates/settings/ai-providers.php`)
- [x] API keys encrypted at rest (AES-256-CBC with WordPress auth salt)
- [x] Test connection button per provider
- [x] Plugin works fully without AI configured

---

## Feature 10: AI Metadata + SEO Plugin Integration

- [x] Generates meta title and description via AI provider
- [x] Fallback to secondary provider on failure
- [x] Copy-to-clipboard functionality (`assets/js/admin-audit.js`)
- [x] Apply to Rank Math (`includes/integrations/class-rankmath-integration.php`)
- [x] Apply to Yoast SEO (`includes/integrations/class-yoast-integration.php`)
- [x] Apply to AIOSEO (`includes/integrations/class-aioseo-integration.php`)
- [x] Edit-before-apply modal (SweetAlert2 input dialog)
- [x] Regenerate creates new suggestion

---

## Feature 11: Quick Fix Actions

- [x] Every warning/fail check has at least one action button
- [x] "Upload Featured Image" opens media library (wp.media)
- [x] "Generate With AI" triggers AI generation
- [x] "Jump To Issue" scrolls/navigates to relevant content
- [x] "Edit Link" opens link editor

---

## Feature 12: Project Completion Score

- [x] Dashboard widget showing SEO Ready %, QA Ready %, Launch Ready %
- [x] Overall Completion % from weighted components
- [x] Scores update after any scan

---

## Feature 13: Ignore Rules

- [x] Ignore specific warning on specific post
- [x] Ignore specific rule globally
- [x] Ignored items excluded from score calculation
- [x] Ignore rules displayed with reason and management UI

---

## Feature 14: QA Notes & Comments

- [x] Add text notes to any post audit
- [x] Notes display author name and timestamp
- [x] Notes are deletable

---

## Feature 15: Website Standards Templates

- [x] Default "Agency Website" template included
- [x] Create, edit, duplicate, delete custom templates
- [x] Active template determines required vs. optional checks
- [x] Template selector in settings

---

## Feature 16: Plugin Conflict Detection

- [x] Detects Rank Math + Yoast simultaneously active
- [x] Detects duplicate sitemap/schema generators
- [x] Displays warning on dashboard

---

## Feature 17: Audit Snapshots

- [x] Snapshot captures current scores and check results
- [x] Multiple snapshots per post stored chronologically
- [x] Trend direction (improving/declining) displayed

---

## Feature 18: Tooltips & SEO Guidance

- [x] Every check item has a tooltip with explanation
- [x] Tooltips appear on hover/click with educational content
- [x] Language is non-technical, targeting junior developers

---

## Feature 19: Knowledge Center

- [x] Knowledge center admin page (`templates/knowledge/index.php`)
- [x] 5 articles: SEO Basics, Heading Structure, Metadata Guide, Launch Checklist, Accessibility Basics
- [x] Static HTML content (no external dependencies)

---

## REST API Endpoints

- [x] `POST /wp-json/scalyn-qa/v1/scan/{post_id}` — Trigger scan
- [x] `GET /wp-json/scalyn-qa/v1/scan/{post_id}` — Get scan results
- [x] `POST /wp-json/scalyn-qa/v1/scan/batch` — Batch scan
- [x] `GET /wp-json/scalyn-qa/v1/scores` — All scores (paginated)
- [x] `GET /wp-json/scalyn-qa/v1/scores/{post_id}` — Single score
- [x] `GET /wp-json/scalyn-qa/v1/scores/summary` — Project completion
- [x] `GET /wp-json/scalyn-qa/v1/launch` — Launch results
- [x] `POST /wp-json/scalyn-qa/v1/launch/scan` — Trigger launch check
- [x] `POST /wp-json/scalyn-qa/v1/ai/generate/{post_id}` — Generate AI meta
- [x] `POST /wp-json/scalyn-qa/v1/ai/apply/{post_id}` — Apply AI meta to SEO plugin
- [x] `POST /wp-json/scalyn-qa/v1/ai/test` — Test AI connection
- [x] `GET /wp-json/scalyn-qa/v1/ai/drafts/{post_id}` — Get saved AI drafts
- [x] `GET /wp-json/scalyn-qa/v1/ignore` — List ignore rules
- [x] `POST /wp-json/scalyn-qa/v1/ignore` — Create ignore rule
- [x] `DELETE /wp-json/scalyn-qa/v1/ignore/{rule_id}` — Delete ignore rule
- [x] `GET /wp-json/scalyn-qa/v1/notes/{post_id}` — Get notes
- [x] `POST /wp-json/scalyn-qa/v1/notes/{post_id}` — Add note
- [x] `DELETE /wp-json/scalyn-qa/v1/notes/{post_id}/{index}` — Delete note
- [x] `GET /wp-json/scalyn-qa/v1/snapshots/{post_id}` — Get snapshots
- [x] `POST /wp-json/scalyn-qa/v1/snapshots/{post_id}` — Create snapshot
- [x] `GET /wp-json/scalyn-qa/v1/settings` — Get settings
- [x] `POST /wp-json/scalyn-qa/v1/settings` — Update settings
- [x] `POST /wp-json/scalyn-qa/v1/settings/templates` — Create template
- [x] `PUT /wp-json/scalyn-qa/v1/settings/templates/{id}` — Update template
- [x] `POST /wp-json/scalyn-qa/v1/settings/templates/{id}/duplicate` — Duplicate template
- [x] `DELETE /wp-json/scalyn-qa/v1/settings/templates/{id}` — Delete template
- [x] `POST /wp-json/scalyn-qa/v1/wizard/install` — Install SEO plugin
- [x] `POST /wp-json/scalyn-qa/v1/wizard/dismiss` — Dismiss wizard
- [x] `DELETE /wp-json/scalyn-qa/v1/wizard/dismiss` — Reset wizard

---

## Admin Pages

- [x] Dashboard Overview page
- [x] Page Audits list page
- [x] Single Page Audit view
- [x] Launch Checklist page
- [x] Knowledge Center page
- [x] Settings page (General, AI Providers, Templates, SEO Wizard, Advanced tabs)
- [x] System Information page

---

## Files Checklist

### PHP Core
- [x] `scalyn-qa-assistant.php`
- [x] `uninstall.php`
- [x] `includes/class-plugin.php`
- [x] `includes/class-activator.php`
- [x] `includes/class-deactivator.php`
- [x] `includes/traits/trait-singleton.php`
- [x] `includes/traits/trait-has-hooks.php`
- [x] `includes/class-migrator.php`
- [x] `includes/class-debug-logger.php`
- [x] `includes/updates/class-github-updater.php`

### Analyzers
- [x] `includes/analyzers/class-analyzer-interface.php`
- [x] `includes/analyzers/class-analyzer-registry.php`
- [x] `includes/analyzers/class-seo-analyzer.php`
- [x] `includes/analyzers/class-content-analyzer.php`
- [x] `includes/analyzers/class-heading-analyzer.php`
- [x] `includes/analyzers/class-link-checker.php`
- [x] `includes/analyzers/class-form-button-analyzer.php`
- [x] `includes/analyzers/class-html-parser.php`

### Scoring
- [x] `includes/scoring/class-scoring-engine.php`

### Launch
- [x] `includes/launch/class-launch-checker.php`

### AI
- [x] `includes/ai/class-ai-manager.php`
- [x] `includes/ai/class-ai-provider.php`
- [x] `includes/ai/class-openai-provider.php`
- [x] `includes/ai/class-claude-provider.php`
- [x] `includes/ai/class-gemini-provider.php`
- [x] `includes/ai/class-ai-provider-registry.php`
- [x] `includes/ai/class-ai-health-monitor.php`
- [x] `includes/ai/class-openrouter-provider.php`
- [x] `includes/ai/class-custom-endpoint-provider.php`

### Integrations
- [x] `includes/integrations/class-seo-integration.php`
- [x] `includes/integrations/class-rankmath-integration.php`
- [x] `includes/integrations/class-yoast-integration.php`
- [x] `includes/integrations/class-aioseo-integration.php`

### Admin
- [x] `includes/Admin/class-admin-menu.php`
- [x] `includes/Admin/class-admin-assets.php`
- [x] `includes/Admin/class-dashboard-page.php`
- [x] `includes/Admin/class-audit-page.php`
- [x] `includes/Admin/class-launch-page.php`
- [x] `includes/Admin/class-knowledge-page.php`
- [x] `includes/Admin/class-settings-page.php`
- [x] `includes/Admin/class-metabox.php`
- [x] `includes/Admin/class-toolbar.php`
- [x] `includes/Admin/class-system-info-page.php`

### REST API
- [x] `includes/rest/class-rest-controller.php`
- [x] `includes/rest/class-scan-controller.php`
- [x] `includes/rest/class-score-controller.php`
- [x] `includes/rest/class-launch-controller.php`
- [x] `includes/rest/class-ai-controller.php`
- [x] `includes/rest/class-ignore-controller.php`
- [x] `includes/rest/class-notes-controller.php`
- [x] `includes/rest/class-snapshot-controller.php`
- [x] `includes/rest/class-settings-controller.php`

### Models
- [x] `includes/models/class-scan-result.php`
- [x] `includes/models/class-check-item.php`
- [x] `includes/models/class-score.php`
- [x] `includes/models/class-snapshot.php`
- [x] `includes/models/class-ignore-rule.php`

### Templates
- [x] `templates/dashboard/overview.php`
- [x] `templates/dashboard/widgets/score-summary.php`
- [x] `templates/dashboard/widgets/pages-attention.php`
- [x] `templates/dashboard/widgets/recent-scans.php`
- [x] `templates/audit/list.php`
- [x] `templates/audit/single.php`
- [x] `templates/launch/checklist.php`
- [x] `templates/knowledge/index.php`
- [x] `templates/knowledge/articles/seo-basics.php`
- [x] `templates/knowledge/articles/heading-structure.php`
- [x] `templates/knowledge/articles/metadata-guide.php`
- [x] `templates/knowledge/articles/launch-checklist.php`
- [x] `templates/knowledge/articles/accessibility-basics.php`
- [x] `templates/settings/general.php`
- [x] `templates/settings/ai-providers.php`
- [x] `templates/settings/templates.php`
- [x] `templates/settings/wizard.php`
- [x] `templates/settings/advanced.php`
- [x] `templates/system-info.php`
- [x] `templates/metabox/checklist.php`
- [x] `templates/partials/score-badge.php`
- [x] `templates/partials/check-item.php`
- [x] `templates/partials/tooltip.php`
- [x] `templates/partials/quick-fix-button.php`

### Assets
- [x] `src/css/admin.css` — Tailwind source
- [x] `assets/css/admin.css` — Compiled CSS (1,343 lines)
- [x] `assets/css/toolbar.css` — Admin toolbar styles (232 lines)
- [x] `assets/js/admin-dashboard.js` — Dashboard interactions (443 lines)
- [x] `assets/js/admin-audit.js` — Audit page logic (1,232 lines)
- [x] `assets/js/admin-settings.js` — Settings page logic (739 lines)
- [x] `assets/js/metabox.js` — Post edit metabox (599 lines)
- [x] `assets/js/toolbar.js` — Front-end toolbar (296 lines)
- [x] `assets/js/sweetalert-init.js` — SweetAlert2 wrapper (169 lines)

### Config & Other
- [x] `composer.json`
- [x] `package.json`
- [x] `tailwind.config.js`
- [x] `postcss.config.js`
- [x] `.gitignore`
- [x] `assets/img/logo.svg`
- [x] `assets/vendor/sweetalert2/sweetalert2.min.js` — v11.26.25
- [x] `assets/vendor/sweetalert2/sweetalert2.min.css` — v11.26.25
- [x] `languages/scalyn-qa-assistant.pot` — Translation template

---

## Post-Audit: Security Hardening

- [x] Gemini API key moved from URL query parameter to Authorization header
- [x] SSL verification enabled in link checker (`sslverify => true`)
- [x] SQL query in Score_Controller wrapped in `$wpdb->prepare()`
- [x] Per-post capability checks (`edit_post`) added to Scan, AI, Notes, Snapshot controllers
- [x] Encryption salt fallback uses site-specific composite key (ABSPATH + DB_NAME + NONCE_SALT)
- [x] Wizard install errors logged server-side, generic message returned to client
- [x] `random_bytes()` replaces deprecated `openssl_random_pseudo_bytes()`

---

## Post-Audit: New Features

- [x] Settings → Advanced tab with data management controls
- [x] "Delete all plugin data on uninstall" toggle (default: disabled, preserves data)
- [x] Settings Export (JSON download with masked API keys)
- [x] Settings Import (JSON upload, validates structure, preserves existing API keys)
- [x] AI daily request rate limiting (configurable: 100, 500, 1000, or Unlimited)
- [x] AI usage logging with viewer (user, provider, model, post, date, success, content length)

---

## Release: Version Migration System

- [x] Migration runner class (`includes/class-migrator.php`) with version comparison and sequential migration execution
- [x] Migration log stored in `scalyn_qa_migration_log` option (capped at 50 entries), displayed in System Info page

---

## Release: Settings Backup & Rollback

- [x] Automatic pre-import backup of all settings (settings, AI config, templates, global ignores)
- [x] Rollback endpoint (`POST /settings/rollback`) restores from backup
- [x] Backup metadata endpoint (`GET /settings/backup`) returns creation info

---

## Release: System Information Page

- [x] System Info admin page (`includes/Admin/class-system-info-page.php` + `templates/system-info.php`)
- [x] Displays: plugin version, WP/PHP/MySQL versions, server, theme, SEO plugin, AI provider, data counts, PHP extensions, memory, migration log, copy-to-clipboard

---

## Release: Debug Mode & Logging

- [x] Debug Logger class (`includes/class-debug-logger.php`) with categorized logging (AI, Link Checker, REST API)
- [x] Debug mode toggle in Settings → Advanced
- [x] Debug log viewer with category filter and clear button
- [x] Integrated into AI Manager (failure logging), Link Checker (failure logging), REST Controller (error logging)

---

## Release: DOMDocument HTML Parsing

- [x] Shared HTML Parser class (`includes/analyzers/class-html-parser.php`) using DOMDocument/DOMXPath
- [x] SEO Analyzer refactored: image alt text + link categorization via DOMDocument
- [x] Heading Analyzer refactored: heading extraction via DOMDocument
- [x] Link Checker refactored: link extraction via DOMDocument
- [x] Form/Button Analyzer refactored: all checks via DOMDocument (buttons, forms, placeholders, popups)

---

## Release: GitHub Updater

- [x] GitHub Updater class (`includes/updates/class-github-updater.php`) checks GitHub Releases API
- [x] WordPress native update flow: `pre_set_site_transient_update_plugins`, `plugins_api`, `upgrader_post_install`
- [x] Settings → Advanced: GitHub Updates section with repo config, optional token, manual check button
- [x] REST endpoints: `POST /updates/check`, `POST /updates/save-token`
- [x] WordPress.org updates disabled for this plugin via `http_request_args` filter

---

## Post-Audit: Bug Fixes

- [x] AIOSEO integration now queries `aioseo_posts` custom table (with postmeta fallback for older versions)
- [x] Snapshot retention limit: max 50 per post (configurable via `scalyn_qa_max_snapshots` filter)
- [x] Metabox auto-scan excludes link checker (configurable via `scalyn_qa_skip_on_autoscan` filter)
- [x] Cached link results preserved during auto-scan to maintain score accuracy

---

## Architecture Notes

### Setup Required
1. Run `composer install` to generate autoloader
2. Activate plugin in WordPress admin
3. (Optional) Run `npm install && npm run build:css` to rebuild Tailwind CSS

### How to Release a New Version
1. Update the `Version:` header in `scalyn-qa-assistant.php`
2. Update `SCALYN_QA_VERSION` constant value to match
3. Commit and push changes to GitHub
4. Create a GitHub Release with tag matching the version (e.g., `v1.0.2`)
5. Optionally attach a pre-built plugin ZIP as a release asset
6. WordPress sites running the plugin will detect the update within 12 hours (or via manual check)

### Architecture Decisions
- **Analyzer Registry Pattern** — New analyzers register via `scalyn_qa_register_analyzers` hook
- **DOMDocument HTML Parsing** — All HTML analysis uses DOMDocument/DOMXPath via shared `HTML_Parser` class (no regex)
- **Abstract SEO Integration** — New SEO plugins: one class extending `SEO_Integration`
- **Abstract AI Provider** — New AI providers: one class extending `AI_Provider`
- **WordPress Native Storage** — `wp_options` + `wp_postmeta` (queries `aioseo_posts` table for AIOSEO)
- **REST API First** — All data operations go through `/wp-json/scalyn-qa/v1/`
- **Zero Front-End Impact** — No CSS/JS for non-admin visitors
- **Per-Post Authorization** — REST endpoints verify `edit_post` capability per post_id
- **Encrypted API Keys** — AES-256-CBC with site-specific salt derived from WordPress auth keys
- **Version Migration System** — `Migrator` class runs sequential migrations on version change
- **Debug Logging** — Categorized logging (AI, Link Checker, REST) with 500-entry cap, viewable in admin
- **GitHub Updater** — Checks GitHub Releases API for new versions, no WordPress.org dependency
- **WordPress.org Excluded** — Plugin stripped from WordPress.org update check payloads
- **Optional GitHub Token** — Stored encrypted, used only for higher rate limits or private repos
- **AI Provider Registry** — Extensible provider registration via hook, no hardcoded provider list
- **AI Health Monitor** — Per-provider health tracking with success rates, response times, error history

---

## AI Provider Framework

### Architecture

```
┌─────────────────────────────────────────────────────┐
│                  AI_Manager                          │
│  - config management, generation, rate limiting      │
│  - iterates priority chain for generation            │
└──────────────┬──────────────────────────────────────┘
               │ uses
┌──────────────┴──────────────────────────────────────┐
│             AI_Provider_Registry                     │
│  - register(), get(), has(), get_all()               │
│  - fires 'scalyn_qa_register_ai_providers' hook      │
└──────────────┬──────────────────────────────────────┘
               │ stores
    ┌──────────┼──────────┬───────────┐
    ▼          ▼          ▼           ▼
 OpenAI    Claude     Gemini    (Future...)
Provider   Provider   Provider   via hook
    └──────────┴──────────┴───────────┘
               │ all extend
┌──────────────┴──────────────────────────────────────┐
│              AI_Provider (abstract)                   │
│  + get_slug(): string                                │
│  + get_name(): string                                │
│  + get_models(): array                               │
│  + generate(prompt): string                          │
│  + test(): array                                     │
│  + supports_custom_endpoint(): bool                  │
│  + set_endpoint(url): void                           │
└─────────────────────────────────────────────────────┘
               │ monitored by
┌──────────────┴──────────────────────────────────────┐
│             AI_Health_Monitor                        │
│  + record_success(provider, response_time)           │
│  + record_failure(provider, error, type)             │
│  + get_health(provider): array                       │
│  + get_success_rate(provider): int                   │
│  + get_avg_response_time(provider): int              │
└─────────────────────────────────────────────────────┘
```

### Supported Providers (v1.0)

| Provider | Category | Models | Status |
|---|---|---|---|
| OpenAI | Cloud | GPT-4o, GPT-4o Mini, GPT-4.1 Mini, GPT-4.1 Nano | Implemented |
| Claude (Anthropic) | Cloud | Claude Sonnet 4, Claude 3.5 Sonnet, Claude 3 Haiku | Implemented |
| Gemini (Google) | Cloud | Gemini 2.0 Flash, Gemini 2.5 Flash | Implemented |
| OpenRouter | Cloud | Claude, GPT, Gemini, DeepSeek, Mistral, Llama, Qwen (9 models) | Implemented |
| Custom Endpoint | Local | Any OpenAI-compatible model (free-form name) | Implemented |

### Future Providers (architecture ready)

| Provider | Category | Registration |
|---|---|---|
| OpenRouter | Cloud | `scalyn_qa_register_ai_providers` hook |
| Perplexity | Cloud | `scalyn_qa_register_ai_providers` hook |
| Grok (xAI) | Cloud | `scalyn_qa_register_ai_providers` hook |
| DeepSeek | Cloud | `scalyn_qa_register_ai_providers` hook |
| Mistral | Cloud | `scalyn_qa_register_ai_providers` hook |
| Cohere | Cloud | `scalyn_qa_register_ai_providers` hook |
| Ollama | Local | `scalyn_qa_register_ai_providers` hook + custom endpoint |
| LM Studio | Local | `scalyn_qa_register_ai_providers` hook + custom endpoint |
| Azure OpenAI | Enterprise | `scalyn_qa_register_ai_providers` hook + custom endpoint |
| AWS Bedrock | Enterprise | `scalyn_qa_register_ai_providers` hook |
| Google Vertex AI | Enterprise | `scalyn_qa_register_ai_providers` hook |

### Adding a New Provider

```php
// In a theme's functions.php or a separate plugin:
add_action('scalyn_qa_register_ai_providers', function() {
    \Scalyn\QA\AI\AI_Provider_Registry::register(
        'ollama',
        My_Ollama_Provider::class,
        [
            'name'           => 'Ollama (Local)',
            'description'    => 'Run models locally via Ollama',
            'website'        => 'https://ollama.ai',
            'supports_local' => true,
            'category'       => 'local',
        ]
    );
});

// The provider class must extend AI_Provider:
class My_Ollama_Provider extends \Scalyn\QA\AI\AI_Provider {
    public function get_slug(): string { return 'ollama'; }
    public function get_name(): string { return 'Ollama'; }
    public function get_models(): array { return ['llama3' => 'Llama 3']; }
    public function generate(string $prompt): string { /* ... */ }
    public function test(): array { /* ... */ }
    public function supports_custom_endpoint(): bool { return true; }
}
```

### Fallback Logic

```
Request → Primary Provider
           │
           ├─ Success → Return result
           │
           └─ Failure → Fallback Provider
                          │
                          ├─ Success → Return result
                          │
                          └─ Failure → Secondary Fallback
                                        │
                                        ├─ Success → Return result
                                        │
                                        └─ Failure → Return error
```

### Health Monitoring

Per-provider metrics tracked:
- **Status**: connected, disconnected, api_error, rate_limited, unknown
- **Total Requests**: lifetime count
- **Success Rate**: percentage (0-100)
- **Avg Response Time**: milliseconds
- **Last Success/Failure**: ISO 8601 timestamps
- **Last Error**: truncated error message

Accessible via `GET /wp-json/scalyn-qa/v1/ai/health`.

### OpenRouter Integration

OpenRouter provides access to 100+ models through a single API key and OpenAI-compatible format:
- Single API key for Claude, GPT, Gemini, DeepSeek, Mistral, Llama, Qwen, and more
- Uses `https://openrouter.ai/api/v1/chat/completions` endpoint
- Requires `HTTP-Referer` and `X-Title` headers per OpenRouter API requirements
- Model IDs use `provider/model` format (e.g., `anthropic/claude-sonnet-4`)

### Custom Endpoint Support

The Custom Endpoint provider connects to any OpenAI-compatible API:

**Settings:**
- Endpoint URL (e.g., `http://localhost:11434/v1/chat/completions`)
- API Key (optional — for endpoints that require auth)
- Model Name (free-text — sent as the `model` parameter)
- Custom Headers (JSON object — merged into request headers)

**Compatible Services:**
- Ollama (`http://localhost:11434/v1/chat/completions`)
- LM Studio (`http://localhost:1234/v1/chat/completions`)
- vLLM, text-generation-inference
- Self-hosted models behind reverse proxies
- Azure OpenAI with custom deployment URLs
- Internal company AI endpoints

**Response Parsing:**
The custom provider parses multiple response formats: OpenAI (`choices[0].message.content`), simple (`response`, `text`, `output`).

Settings for custom endpoints are stored per-provider in the AI config.

---

## Branding

### Logo Assets

| File | Purpose | Location |
|---|---|---|
| `assets/images/scalyn-icon.png` | Official Scalyn logo (PNG) | Dashboard header, empty states |
| `assets/images/scalyn-menu-icon.svg` | Simplified infinity/wave icon | Admin sidebar menu (base64 encoded) |
| `assets/img/logo.svg` | Generic shield icon (legacy) | Retained for backward compatibility |

### Placement Strategy

| Location | Usage | Style |
|---|---|---|
| Admin sidebar menu | SVG infinity icon via `data:image/svg+xml;base64` | Monochrome, inherits WP admin colors |
| Dashboard header | PNG logo + title + description + version | White logo on navy gradient (`#1B4F72` → `#2471A3`) |
| Empty states | PNG logo at 15% opacity | Subtle background accent |
| Knowledge Center | Not used | Content pages stay clean |
| Settings pages | Not used | Functional pages don't need branding |

### Design Decisions
- Logo inverted to white via CSS `filter: brightness(0) invert(1)` on dark backgrounds
- Admin menu icon uses `fill="black"` SVG — WordPress applies its own color scheme via CSS
- Branded header uses CSS gradient, not an image, for performance
- Version badge uses semi-transparent white pill shape on dark background
- `icon.png` removed from plugin root to keep directory structure clean
- Branding is professional and restrained — only appears on the Dashboard, not repeated on every page

### Security Controls
- All REST endpoints have `permission_callback` with capability checks
- Per-post `edit_post` capability verified on post-specific endpoints
- All SQL uses `$wpdb->prepare()`
- All inputs sanitized (`sanitize_text_field`, `absint`, `sanitize_key`, etc.)
- All template output escaped (`esc_html`, `esc_attr`, `esc_url`)
- API keys encrypted at rest, masked in API responses
- SSL verification enabled for all outgoing HTTP requests
- SSRF protection blocks private IP ranges
- Plugin installation restricted to whitelist (Rank Math, Yoast only)
- AI requests rate-limited with configurable daily cap

### Data Lifecycle
- **Deactivation**: Preserves all data, clears transients only
- **Uninstall (default)**: Preserves data (only removes version marker)
- **Uninstall (with toggle)**: Removes all `scalyn_qa_*` options, `_scalyn_qa_*` postmeta, and transients

### Performance Characteristics
- Auto-scan on save: excludes link checker by default (fast, <2s)
- Full scan via manual button: includes link checker (5-30s depending on link count)
- Snapshot limit: 50 per post (prevents unbounded postmeta growth)
- AI log: auto-prunes entries older than 30 days
- Link check cache: 24h transient per URL
- Project scores: calculated on demand (consider caching for sites with 500+ pages)

---

## Code Review — Senior Architect Findings (2026-06-11)

### 1. Critical Issues

| # | Issue | Location | Impact |
|---|---|---|---|
| 1 | **Settings_Controller is a God class** — 14+ routes, 180-line register_routes(), methods up to 95 lines | `class-settings-controller.php` | Unmaintainable; violates SRP |
| 2 | **AI_Manager is a God class** — handles encryption, config, generation, rate limiting, logging (6+ concerns) | `class-ai-manager.php` | Should split into AI_Manager, Encryption_Service, Rate_Limiter |
| 3 | **AI provider registry not extensible** — adding providers requires editing AI_Manager source | `class-ai-manager.php:62-66` | Violates Open/Closed Principle |
| 4 | **AIOSEO table name interpolated into SQL** — `$table` used directly in query strings | `class-aioseo-integration.php:72,94,113,136` | Table name not escaped (low real risk since it's `$wpdb->prefix` derived, but violates standards) |
| 5 | **`get_rendered_content()` duplicated in 4 files** — identical 21-line Elementor detection logic | SEO/Heading/Link/FormButton analyzers | DRY violation; bugs must be fixed 4x |

### 2. High Priority Improvements

| # | Issue | Location | Recommendation |
|---|---|---|---|
| 1 | **Models mix value objects with persistence** — Scan_Result, Ignore_Rule, Snapshot all have static CRUD + domain logic | `class-scan-result.php`, `class-ignore-rule.php`, `class-snapshot.php` | Extract Repository classes for persistence |
| 2 | **`extract()` used in all 7 template loaders** | Dashboard, Audit, Launch, Settings, Metabox, System_Info, Knowledge pages | Replace with explicit `$data` array pass-through |
| 3 | **N+1 query patterns on Dashboard** — loops through posts calling `get_post_meta()` per row | `class-dashboard-page.php:66-161`, `class-audit-page.php:127-142` | Batch meta queries or use `update_postmeta_cache()` |
| 4 | **Multiple DOMDocument instances for same content** — Link Checker creates 2 HTML_Parser for identical HTML | `class-link-checker.php:107-109` | Pass single parser instance between methods |
| 5 | **AI provider response validation unsafe** — accesses nested arrays without checking intermediate keys | `class-openai-provider.php:109`, `class-gemini-provider.php:111,117` | Add null-safe array access or validation |
| 6 | **Silent encryption fallback** — keys stored plaintext if OpenSSL unavailable, no warning | `class-ai-manager.php:400-409` | Fail loudly or log warning instead of silent degradation |
| 7 | **Scoring_Engine::get_project_scores() not paginated** — loads all scanned posts into memory | `class-scoring-engine.php:104-162` | Add pagination or transient caching |
| 8 | **Score_Controller in-memory pagination** — loads all scores, then slices in PHP | `class-score-controller.php:137-211` | Use WP_Query with meta_query for DB-level pagination |

### 3. Medium Priority Improvements

| # | Issue | Location |
|---|---|---|
| 1 | Singleton constructor auto-calls `register_hooks()` via `method_exists()` — hidden magic | `trait-singleton.php:47-54` |
| 2 | Launch_Checker mixes check logic with option persistence | `class-launch-checker.php:50-72` |
| 3 | SEO_Integration::detect() creates instances without caching result | `class-seo-integration.php:104-118` |
| 4 | Plugin detection includes `wp-admin/includes/plugin.php` on frontend | `class-rankmath-integration.php:47`, `class-yoast-integration.php:47` |
| 5 | AIOSEO table existence checked 4x per request (not cached) | `class-aioseo-integration.php:65,87,108,131` |
| 6 | Hardcoded plugin conflict slugs not filterable | `class-launch-checker.php:509-587` |
| 7 | Metabox save_post race condition (hook remove/re-add pattern) | `class-metabox.php:181-186` |
| 8 | Debug_Logger writes to DB on every log entry (no batching) | `class-debug-logger.php:73-93` |
| 9 | Content_Analyzer is a pass-through wrapper with no own logic | `class-content-analyzer.php` |
| 10 | Claude API version header outdated (`2023-06-01`) | `class-claude-provider.php:38` |
| 11 | Static cache in `categorize_links()` not keyed to input | `class-seo-analyzer.php:504` |
| 12 | Inconsistent REST status codes (some POST return 200, some 201) | Multiple controllers |

### 4. Low Priority Improvements

| # | Issue | Location |
|---|---|---|
| 1 | Hardcoded API URLs/models/temperatures in provider classes | All AI providers |
| 2 | Missing response schema definitions on REST routes | Multiple controllers |
| 3 | Admin_Menu PAGE_SLUGS constant tightly coupled across files | Multiple admin classes |
| 4 | Singleton pattern used inconsistently (4 classes use it, 6 don't) | Admin layer |
| 5 | Migrator uses variable method invocation (`self::$method()`) | `class-migrator.php:66` |
| 6 | GitHub Updater markdown-to-HTML conversion is basic | `class-github-updater.php:229-237` |
| 7 | Missing pagination on Ignore and Snapshot list endpoints | REST controllers |

### 5. Technical Debt Summary

| Category | Debt Items | Severity |
|---|---|---|
| **God Classes** | Settings_Controller (14 routes), AI_Manager (6 concerns) | High |
| **SRP Violations** | Models with CRUD, Launch_Checker with persistence, Content_Analyzer pass-through | High |
| **Code Duplication** | `get_rendered_content()` 4x, provider `generate()` boilerplate 3x, `extract()` 7x | Medium |
| **Tight Coupling** | Controllers → Models (direct instantiation), Analyzers → WordPress functions | Medium |
| **Missing Abstractions** | No Repository layer, no Service Container, no View abstraction | Medium |
| **Performance** | N+1 queries (dashboard, audit list), in-memory pagination (scores), no project score cache | Medium |
| **Inconsistency** | REST status codes, singleton usage, error handling patterns | Low |

### 6. Refactoring Recommendations (Priority Order)

**Phase 1 — Quick Wins (1-2 days):**
1. Extract `get_rendered_content()` into a shared `Content_Renderer` utility class
2. Replace `extract()` with explicit `$data` array in template loading
3. Cache `SEO_Integration::detect()` result and AIOSEO table existence check
4. Add `apply_filters('scalyn_qa_ai_providers', $providers)` to AI_Manager for extensibility
5. Fix AI provider response validation (null-safe access)

**Phase 2 — Architecture (3-5 days):**
1. Split Settings_Controller into Settings, Template, Wizard, Debug, Export controllers
2. Extract Repository classes for Scan_Result, Ignore_Rule, Snapshot persistence
3. Batch postmeta queries in Dashboard and Audit pages using `update_postmeta_cache()`
4. Pass HTML_Parser instance from analyzer to sub-methods instead of re-creating

**Phase 3 — Long-Term (1-2 weeks):**
1. Introduce a lightweight Service Container to replace Singleton pattern
2. Extract AI_Manager concerns into AI_Config, AI_Encryption, AI_Rate_Limiter
3. Add proper DB-level pagination to Score_Controller and project scores
4. Add transient caching for project completion scores
5. Add REST response schema definitions for all endpoints

### Known Limitations
- Snapshots stored in postmeta (capped at 50) — consider custom table at scale (1000+ pages)
- Elementor detection lacks exception handling around `get_builder_content()`
- GA4 detection searches for pattern strings in homepage HTML (possible false positives)
- Claude API version header (`2023-06-01`) should be updated periodically
- Project scores not cached (recalculated per dashboard load) — add transient cache for 500+ page sites
- Activation redirect transient set but no handler implemented (cosmetic, not functional)
- GitHub Updater: source ZIP from GitHub contains a nested folder (`repo-tag/`) which `upgrader_post_install` renames; if `$wp_filesystem` is unavailable, the rename may fail
- GitHub Updater: API rate limit for unauthenticated requests is 60/hour — add a token for environments with frequent checks
- GitHub Updater: Release body markdown is converted to basic HTML (bold, lists) — complex markdown may not render perfectly in the "View Details" modal

/**
 * QA Inspector.
 *
 * Frontend visual inspection panel that renders existing Page Audit
 * data as a docked sidebar or floating panel with element highlighting.
 *
 * @package Scalyn\QA\Assets
 * @since   1.4.0
 */

'use strict';

(function () {

    // Bail early if data is missing.
    if (typeof scalynInspector === 'undefined' || typeof scalynQA === 'undefined') return;

    var data      = scalynInspector;
    var panel     = null;
    var mode      = 'closed'; // closed | docked | floating
    var highlights = [];
    var textMarks = [];
    var activeTooltip = null;
    var highlightsEnabled = true;
    var dragState = null;
    var ignoredChecks = (data.ignoredChecks || []).slice(); // mutable copy

    // Status icons (unicode).
    var STATUS_ICONS = { pass: '\u2713', warning: '!', fail: '\u2717' };

    // Issue-to-DOM selector mapping.
    var DOM_SELECTORS = {
        image_alt_text:      function (d) { return (d.missing_alt_images || []).map(function (src) { return 'img[src*="' + CSS.escape(src.split('/').pop()) + '"]'; }); },
        internal_links:      function (d) { return (d.internal_links || []).map(function (l) { return 'a[href*="' + CSS.escape(typeof l === 'string' ? l : l.url || '') + '"]'; }); },
        external_links:      function (d) { return (d.external_links || []).map(function (l) { return 'a[href*="' + CSS.escape(typeof l === 'string' ? l : l.url || '') + '"]'; }); },
    };

    // Track heading badges and image badges for cleanup.
    var annotationBadges = [];

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    function esc(str) {
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(String(str || '')));
        return d.innerHTML;
    }

    function getStatus(score) {
        var s = scalynQA.settings || {};
        if (score >= (s.score_green || 80)) return 'green';
        if (score >= (s.score_yellow || 50)) return 'yellow';
        return 'red';
    }

    function countIssues(checks) {
        var c = { pass: 0, warning: 0, fail: 0, total: 0 };
        if (!checks) return c;
        checks.forEach(function (item) {
            c.total++;
            if (c[item.status] !== undefined) c[item.status]++;
        });
        return c;
    }

    // -------------------------------------------------------------------------
    // Panel Rendering
    // -------------------------------------------------------------------------

    function buildPanel() {
        var el = document.createElement('div');
        el.id = 'sqi-panel';
        el.className = 'sqi-panel';
        el.innerHTML = buildHeader() + '<div class="sqi-body">' + buildBody() + '</div>' + buildFooter();
        document.body.appendChild(el);
        return el;
    }

    var ICON_RESCAN = '<svg viewBox="0 0 16 16" fill="currentColor" style="width:12px;height:12px;"><path d="M13.65 2.35A8 8 0 1 0 16 8h-2a6 6 0 1 1-1.76-4.24L10 6h6V0l-2.35 2.35z"/></svg>';
    var ICON_DOCK   = '<svg viewBox="0 0 16 16" fill="currentColor" style="width:12px;height:12px;"><path d="M2 2h12v12H2V2zm1 3v8h10V5H3z"/></svg>';
    var ICON_UNDOCK = '<svg viewBox="0 0 16 16" fill="currentColor" style="width:12px;height:12px;"><path d="M1 1h10v3h-1V2H2v8h3v1H1V1zm4 4h10v10H5V5zm1 1v8h8V6H6z"/></svg>';
    var ICON_CLOSE  = '<svg viewBox="0 0 16 16" fill="currentColor" style="width:12px;height:12px;"><path d="M3.46 2.05L8 6.59l4.54-4.54 1.41 1.41L9.41 8l4.54 4.54-1.41 1.41L8 9.41l-4.54 4.54-1.41-1.41L6.59 8 2.05 3.46l1.41-1.41z"/></svg>';

    function buildHeader() {
        return '<div class="sqi-header">' +
            '<span class="sqi-header__title">QA Inspector</span>' +
            '<div class="sqi-header__actions">' +
            '<button class="sqi-header__btn" id="sqi-btn-rescan" title="Rescan page">' + ICON_RESCAN + '</button>' +
            '<button class="sqi-header__btn" id="sqi-btn-mode" title="Toggle dock/float">' + ICON_UNDOCK + '</button>' +
            '<button class="sqi-header__btn" id="sqi-btn-close" title="Close">' + ICON_CLOSE + '</button>' +
            '</div></div>';
    }

    function buildBody() {
        if (!data.hasScan || !data.results) {
            return '<div class="sqi-empty"><div class="sqi-empty__icon">\uD83D\uDD0D</div>' +
                '<div class="sqi-empty__text">No scan data.<br>Click Rescan in the toolbar.</div></div>';
        }

        var r = data.results;
        var scores = r.scores || {};
        var overall = scores.overall || 0;
        var status = getStatus(overall);
        var results = r.results || {};

        var totalPass = 0, totalWarn = 0, totalFail = 0, totalAll = 0;

        var categories = [
            { key: 'seo', label: 'SEO', score: scores.seo || 0 },
            { key: 'content', label: 'Content', score: scores.content || 0 },
            { key: 'functionality', label: 'Functionality', score: scores.functionality || 0 },
        ];

        categories.forEach(function (cat) {
            var checks = (results[cat.key] || []).filter(function (item) {
                return ignoredChecks.indexOf(item.id) === -1;
            });
            var c = countIssues(checks);
            cat.counts = c;
            cat.checks = checks;
            totalPass += c.pass;
            totalWarn += c.warning;
            totalFail += c.fail;
            totalAll += c.total;
        });

        // Check if Generate All has already been run (any AI data exists).
        var hasAnyAiData = aiDrafts || data.aiAltTexts || data.aiKeywords || data.aiFeatured || data.contentReview;
        var generateAllRun = false;

        // Overview
        var html = '<div class="sqi-overview">' +
            '<div class="sqi-overview__score">' +
            '<div class="sqi-overview__circle sqi-overview__circle--' + status + '">' + overall + '%</div>' +
            '<div class="sqi-overview__meta">' +
            '<span class="sqi-overview__label">Page Score</span>' +
            '<span class="sqi-overview__stats">' + totalPass + '/' + totalAll + ' passed' +
            (totalFail > 0 ? ' &middot; <span style="color:var(--sqi-red)">' + totalFail + ' failed</span>' : '') +
            (totalWarn > 0 ? ' &middot; <span style="color:var(--sqi-yellow)">' + totalWarn + ' warnings</span>' : '') +
            '</span></div></div>' +
            '<div class="sqi-overview__bar"><div class="sqi-overview__bar-fill sqi-overview__bar-fill--' + status + '" style="width:' + overall + '%"></div></div>';

        // Generate All with AI button — only if not already run.
        if (!hasAnyAiData && totalFail > 0) {
            html += '<button class="sqi-footer__btn" id="sqi-btn-generate-all" style="width:100%;margin-top:8px;">Generate All with AI</button>';
        }

        html += '</div>';

        // Categories
        categories.forEach(function (cat) {
            var catStatus = getStatus(cat.score);
            var issueCount = cat.counts.fail + cat.counts.warning;
            var isOpen = issueCount > 0;

            html += '<div class="sqi-category' + (isOpen ? ' sqi-category--open' : '') + '" data-category="' + cat.key + '">';
            html += '<div class="sqi-category__header">';
            html += '<span class="sqi-category__title"><span class="sqi-category__arrow">\u25B6</span> ' + esc(cat.label);
            if (issueCount > 0) {
                html += ' <span class="sqi-category__badge sqi-category__badge--' + (cat.counts.fail > 0 ? 'red' : 'yellow') + '">' + issueCount + '</span>';
            }
            html += '</span>';
            html += '<span class="sqi-category__score">' + cat.score + '%</span>';
            html += '</div>';

            html += '<div class="sqi-category__list">';
            cat.checks.forEach(function (item) {
                var icon = STATUS_ICONS[item.status] || '?';
                html += '<div class="sqi-check" data-check-id="' + esc(item.id) + '" data-status="' + esc(item.status) + '" data-category="' + cat.key + '">';
                html += '<span class="sqi-check__icon sqi-check__icon--' + item.status + '">' + icon + '</span>';
                html += '<div class="sqi-check__content">';
                html += '<span class="sqi-check__label">' + esc(item.label) + '</span>';
                if (item.message) html += '<span class="sqi-check__message">' + esc(item.message) + '</span>';

                // Inline AI result (if exists from generation).
                var inlineResult = getInlineResult(item);
                if (inlineResult) {
                    html += '<div class="sqi-check__result">' + esc(inlineResult) + '</div>';
                }

                // Action buttons — show for failing checks AND for pass checks that have AI data.
                var hasAiForCheck = !!aiGenerated[item.id];
                if (item.status !== 'pass' || hasAiForCheck) {
                    html += buildCheckActions(item);
                }

                // Ignore button for non-pass checks.
                if (item.status !== 'pass') {
                    html += '<button class="sqi-ignore-btn" data-check-id="' + esc(item.id) + '" title="Ignore this check">\u2715</button>';
                }

                html += '</div></div>';
            });
            html += '</div></div>';
        });

        // AI Content Review section.
        html += buildContentReview();

        // QA Notes section.
        html += buildNotes();

        return html;
    }

    /**
     * Build QA Notes section.
     */
    function buildNotes() {
        var notes = data.notes || [];

        var html = '<div class="sqi-category sqi-category--open" data-category="notes">';
        html += '<div class="sqi-category__header">';
        html += '<span class="sqi-category__title"><span class="sqi-category__arrow">\u25B6</span> QA Notes';
        if (notes.length > 0) {
            html += ' <span class="sqi-category__badge sqi-category__badge--green">' + notes.length + '</span>';
        }
        html += '</span></div>';

        html += '<div class="sqi-category__list">';

        // Add note form.
        html += '<div class="sqi-note-form">';
        html += '<input type="text" id="sqi-note-input" class="sqi-note-input" placeholder="Write a QA note..." />';
        html += '<button class="sqi-action-btn sqi-action-btn--apply" id="sqi-btn-add-note">Add</button>';
        html += '</div>';

        // Existing notes.
        if (notes.length > 0) {
            notes.slice().reverse().forEach(function (note, idx) {
                var realIndex = notes.length - 1 - idx;
                var author = note.author || note.user_name || '';
                var date = '';
                if (note.created_at || note.date) {
                    var d = new Date(note.created_at || note.date);
                    date = d.toLocaleDateString();
                }

                html += '<div class="sqi-note" data-index="' + realIndex + '">';
                html += '<div class="sqi-note__text">' + esc(note.content || '') + '</div>';
                html += '<div class="sqi-note__meta">';
                if (author) html += '<span>' + esc(author) + '</span>';
                if (date) html += '<span>' + esc(date) + '</span>';
                html += '<button class="sqi-note__delete" data-index="' + realIndex + '" title="Delete">\u2715</button>';
                html += '</div></div>';
            });
        } else {
            html += '<div style="font-size:11px;color:var(--sqi-text-muted);padding:4px 0;">No notes yet.</div>';
        }

        html += '</div></div>';
        return html;
    }

    /**
     * Get inline AI result text for a check (from saved drafts).
     */
    function getInlineResult(item) {
        if (!data.results) return null;

        // Meta title/description: read from saved AI drafts passed via localized data.
        if (item.id === 'meta_title_exists' && aiDrafts && aiDrafts.title) {
            return aiDrafts.title;
        }
        if (item.id === 'meta_description_exists' && aiDrafts && aiDrafts.description) {
            return aiDrafts.description;
        }
        return null;
    }

    // Load saved AI drafts from PHP-localized data.
    var aiDrafts = data.aiDrafts || null;
    var aiGenerated = {}; // Track which checks have been generated this session.

    // Pre-populate aiGenerated from existing server-side AI data.
    if (aiDrafts) {
        if (aiDrafts.title) aiGenerated['meta_title_exists'] = true;
        if (aiDrafts.description) aiGenerated['meta_description_exists'] = true;
    }
    if (data.aiAltTexts) aiGenerated['image_alt_text'] = true;
    if (data.aiKeywords) aiGenerated['focus_keyword'] = true;
    if (data.aiFeatured) aiGenerated['featured_image_exists'] = true;

    /**
     * Build action buttons for a failing check.
     */
    function buildCheckActions(item) {
        var actions = [];
        var checkId = item.id;

        // Determine which checks support AI generation.
        var aiChecks = {
            'meta_title_exists': 'ai/generate/',
            'meta_description_exists': 'ai/generate/',
            'image_alt_text': 'ai/generate-alt/',
            'focus_keyword': 'ai/generate-keywords/',
            'featured_image_exists': 'ai/generate-featured-image/',
        };

        if (aiChecks[checkId]) {
            var isRegen = !!aiGenerated[checkId] || !!getInlineResult(item);
            var genLabel = isRegen ? 'Regenerate' : 'Generate';
            actions.push('<button class="sqi-action-btn sqi-action-btn--ai sqi-gen-btn" data-check-id="' + esc(checkId) + '" data-endpoint="' + aiChecks[checkId] + '">' + genLabel + '</button>');
        }

        // Copy + Apply for meta title/description.
        var result = getInlineResult(item);
        if (result) {
            actions.push('<button class="sqi-action-btn sqi-copy-btn" data-text="' + esc(result) + '">Copy</button>');
            actions.push('<button class="sqi-action-btn sqi-action-btn--apply sqi-apply-btn" data-check-id="' + esc(checkId) + '" data-text="' + esc(result) + '">Apply</button>');
        }

        // Apply for featured image (from existing AI options).
        if (checkId === 'featured_image_exists' && data.aiFeatured && data.aiFeatured.length > 0) {
            actions.push('<button class="sqi-action-btn sqi-action-btn--apply sqi-apply-fi-btn" data-attachment-id="' + data.aiFeatured[0].id + '">Apply</button>');
        }

        // Copy + Apply for focus keyword.
        if (checkId === 'focus_keyword' && data.aiKeywords && data.aiKeywords.primary) {
            var kw = data.aiKeywords.primary;
            actions.push('<button class="sqi-action-btn sqi-copy-btn" data-text="' + esc(kw) + '">Copy</button>');
            actions.push('<button class="sqi-action-btn sqi-action-btn--apply sqi-apply-kw-btn" data-primary="' + esc(kw) + '">Apply</button>');
        }

        if (actions.length === 0) return '';

        var html = '<div class="sqi-check__actions">' + actions.join('') + '</div>';

        // Show keyword suggestions if available.
        if (checkId === 'focus_keyword' && data.aiKeywords) {
            var kwData = data.aiKeywords;
            var kwList = kwData.keywords || [];
            if (kwData.primary && kwList.length === 0) {
                kwList = [kwData.primary].concat(kwData.secondary || []);
            }
            if (kwList.length > 0) {
                var isPro = !!kwData.has_pro;
                var inputType = isPro ? 'checkbox' : 'radio';
                var inputName = isPro ? 'sqi-kw[]' : 'sqi-kw';

                html += '<div class="sqi-kw-options">';
                kwList.forEach(function (kw, idx) {
                    var isPrimary = isPro && idx === 0;
                    var isSecondary = isPro && idx > 0;
                    // Pro: all checked by default. Free: first checked.
                    var isChecked = isPro ? true : (idx === 0);

                    html += '<label class="sqi-kw-opt' + (isChecked ? ' sqi-kw-opt--selected' : '') + '">';
                    html += '<input type="' + inputType + '" name="' + inputName + '" value="' + esc(kw) + '"' + (isChecked ? ' checked' : '') + '>';
                    html += '<span>' + esc(kw) + '</span>';
                    if (isPrimary) html += '<span class="sqi-kw-opt__badge">primary</span>';
                    if (isSecondary) html += '<span class="sqi-kw-opt__badge" style="color:var(--sqi-text-muted);">secondary</span>';
                    html += '</label>';
                });
                html += '</div>';
            }
        }

        // Show featured image thumbnails if available.
        if (checkId === 'featured_image_exists' && data.aiFeatured && data.aiFeatured.length > 0) {
            html += '<div class="sqi-fi-options">';
            data.aiFeatured.forEach(function (fi, idx) {
                var isCurrent = fi.id === data.currentThumbnail;
                html += '<label class="sqi-fi-opt' + (isCurrent ? ' sqi-fi-opt--current' : (idx === 0 ? ' sqi-fi-opt--selected' : '')) + '">';
                html += '<input type="radio" name="sqi-fi" value="' + fi.id + '"' + (isCurrent || (!data.currentThumbnail && idx === 0) ? ' checked' : '') + '>';
                html += '<img src="' + esc(fi.url) + '" alt="' + esc(fi.filename) + '" title="' + esc(fi.filename) + '">';
                if (isCurrent) html += '<span class="sqi-fi-opt__badge">current</span>';
                html += '</label>';
            });
            html += '</div>';
        }

        return html;
    }

    function buildContentReview() {
        var review = data.contentReview;
        if (!review || !review.summary) return '';

        var score = review.score || 0;
        var reviewStatus = score >= 80 ? 'green' : (score >= 50 ? 'yellow' : 'red');
        var issues = review.issues || [];
        var activeIssues = issues.filter(function (i) { return i.status !== 'resolved' && i.status !== 'ignored'; });
        var hasIssues = activeIssues.length > 0;

        var html = '<div class="sqi-category' + (hasIssues ? ' sqi-category--open' : '') + '" data-category="ai-review">';
        html += '<div class="sqi-category__header">';
        html += '<span class="sqi-category__title"><span class="sqi-category__arrow">\u25B6</span> Writing Quality';
        if (hasIssues) {
            html += ' <span class="sqi-category__badge sqi-category__badge--' + reviewStatus + '">' + activeIssues.length + '</span>';
        }
        html += '</span>';
        html += '<span class="sqi-category__score">' + score + '/100</span>';
        html += '</div>';

        html += '<div class="sqi-category__list">';

        // Summary.
        html += '<div style="padding:4px 8px;margin-bottom:6px;font-size:11px;color:var(--sqi-text-muted);line-height:1.5;">' + esc(review.summary) + '</div>';

        if (hasIssues) {
            activeIssues.forEach(function (issue) {
                var sevIcon = issue.severity === 'error' ? '\u2717' : (issue.severity === 'warning' ? '!' : '\u2139');
                var sevClass = issue.severity === 'error' ? 'fail' : (issue.severity === 'warning' ? 'warning' : 'pass');
                var typeLabel = (issue.type || 'issue').replace(/_/g, ' ');
                typeLabel = typeLabel.charAt(0).toUpperCase() + typeLabel.slice(1);

                html += '<div class="sqi-check sqi-review-issue" data-issue-text="' + esc(issue.text || '') + '">';
                html += '<span class="sqi-check__icon sqi-check__icon--' + sevClass + '">' + sevIcon + '</span>';
                html += '<div class="sqi-check__content">';
                html += '<span class="sqi-check__label">' + esc(typeLabel) + '</span>';
                if (issue.text) html += '<span class="sqi-check__message" style="color:var(--sqi-red);text-decoration:line-through;">' + esc(issue.text) + '</span>';
                if (issue.suggestion) html += '<span class="sqi-check__message" style="color:var(--sqi-green);">\u2192 ' + esc(issue.suggestion) + '</span>';
                html += '</div></div>';
            });
        } else {
            html += '<div style="padding:4px 8px;font-size:11px;color:var(--sqi-green);">\u2713 No writing issues found.</div>';
        }

        // Actions row.
        html += '<div style="padding:6px 8px;display:flex;gap:6px;align-items:center;">';
        html += '<button class="sqi-footer__btn" id="sqi-btn-review-current" style="font-size:10px;padding:3px 8px;">Review Current</button>';
        html += '<button class="sqi-footer__btn" id="sqi-btn-review-regenerate" style="font-size:10px;padding:3px 8px;background:var(--sqi-border);color:var(--sqi-text);">Regenerate</button>';
        if (review.provider) {
            html += '<span style="font-size:10px;color:var(--sqi-text-muted);opacity:0.7;margin-left:auto;">' + esc(review.provider) + '</span>';
        }
        html += '</div>';

        html += '</div></div>';
        return html;
    }

    function buildFooter() {
        var scannedAt = '';
        if (data.results && data.results.scanned_at) {
            var d = new Date(data.results.scanned_at);
            scannedAt = d.toLocaleString();
        }

        return '<div class="sqi-footer">' +
            '<div class="sqi-footer__row">' +
            '<label class="sqi-footer__toggle"><input type="checkbox" id="sqi-highlight-toggle" checked> Highlight issues</label>' +
            (scannedAt ? '<span class="sqi-footer__meta">' + esc(scannedAt) + '</span>' : '') +
            '</div>' +
            '</div>';
    }

    // -------------------------------------------------------------------------
    // Panel Modes
    // -------------------------------------------------------------------------

    function openInspector(newMode) {
        if (!panel) panel = buildPanel();
        mode = newMode || 'docked';
        panel.style.display = '';

        // Clear any inline styles from floating/dragging/resizing.
        panel.style.width = '';
        panel.style.height = '';
        panel.style.left = '';
        panel.style.top = '';
        panel.style.right = '';

        if (mode === 'docked') {
            panel.className = 'sqi-panel sqi-panel--docked';
            document.body.classList.add('sqi-docked');
            document.getElementById('sqi-btn-mode').title = 'Undock to floating';
            document.getElementById('sqi-btn-mode').innerHTML = ICON_UNDOCK;
        } else {
            panel.className = 'sqi-panel sqi-panel--floating';
            document.body.classList.remove('sqi-docked');
            document.getElementById('sqi-btn-mode').title = 'Dock to sidebar';
            document.getElementById('sqi-btn-mode').innerHTML = ICON_DOCK;
        }

        bindPanelEvents();
        if (highlightsEnabled) showHighlights();
    }

    function closeInspector() {
        if (panel) panel.style.display = 'none';
        document.body.classList.remove('sqi-docked');
        mode = 'closed';
        clearHighlights();
        clearTooltip();
    }

    function toggleMode() {
        if (mode === 'docked') {
            openInspector('floating');
        } else {
            openInspector('docked');
        }
    }

    // -------------------------------------------------------------------------
    // Panel Events
    // -------------------------------------------------------------------------

    function bindPanelEvents() {
        // Close button.
        var closeBtn = document.getElementById('sqi-btn-close');
        if (closeBtn) closeBtn.onclick = closeInspector;

        // Mode toggle.
        var modeBtn = document.getElementById('sqi-btn-mode');
        if (modeBtn) modeBtn.onclick = toggleMode;

        // Highlight toggle.
        var hlToggle = document.getElementById('sqi-highlight-toggle');
        if (hlToggle) {
            hlToggle.checked = highlightsEnabled;
            hlToggle.onchange = function () {
                highlightsEnabled = hlToggle.checked;
                if (highlightsEnabled) showHighlights();
                else clearHighlights();
            };
        }

        // Rescan button.
        var rescanBtn = document.getElementById('sqi-btn-rescan');
        if (rescanBtn) {
            rescanBtn.onclick = function () {
                rescanBtn.disabled = true;
                rescanBtn.textContent = 'Scanning...';

                fetch(scalynQA.restUrl + 'scan/' + data.postId, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': scalynQA.nonce },
                    credentials: 'same-origin',
                })
                .then(function (r) { return r.json(); })
                .then(function (response) {
                    if (response.success && response.data) {
                        data.hasScan = true;
                        data.results = response.data;
                        refreshPanel();
                    }
                    rescanBtn.disabled = false;
                    rescanBtn.textContent = 'Rescan';
                })
                .catch(function () {
                    rescanBtn.disabled = false;
                    rescanBtn.textContent = 'Rescan';
                });
            };
        }

        // Add Note button.
        var addNoteBtn = document.getElementById('sqi-btn-add-note');
        var noteInput = document.getElementById('sqi-note-input');
        if (addNoteBtn && noteInput) {
            var submitNote = function () {
                var content = noteInput.value.trim();
                if (!content) return;

                addNoteBtn.disabled = true;
                addNoteBtn.textContent = '...';

                fetch(scalynQA.restUrl + 'notes/' + data.postId, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': scalynQA.nonce },
                    credentials: 'same-origin',
                    body: JSON.stringify({ content: content }),
                })
                .then(function (r) { return r.json(); })
                .then(function (response) {
                    if (response.success && response.data && response.data.notes) {
                        data.notes = response.data.notes;
                        refreshPanel();
                    }
                    addNoteBtn.disabled = false;
                    addNoteBtn.textContent = 'Add';
                })
                .catch(function () {
                    addNoteBtn.disabled = false;
                    addNoteBtn.textContent = 'Add';
                });
            };

            addNoteBtn.onclick = submitNote;
            noteInput.onkeydown = function (e) {
                if (e.key === 'Enter') submitNote();
            };
        }

        // Delete Note buttons.
        panel.querySelectorAll('.sqi-note__delete').forEach(function (btn) {
            btn.onclick = function (e) {
                e.stopPropagation();
                var index = btn.getAttribute('data-index');
                if (index === null) return;

                btn.disabled = true;

                fetch(scalynQA.restUrl + 'notes/' + data.postId + '/' + index, {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': scalynQA.nonce },
                    credentials: 'same-origin',
                })
                .then(function (r) { return r.json(); })
                .then(function (response) {
                    if (response.success && response.data && response.data.notes) {
                        data.notes = response.data.notes;
                    } else {
                        // Fallback: remove locally.
                        data.notes.splice(parseInt(index, 10), 1);
                    }
                    refreshPanel();
                })
                .catch(function () {
                    btn.disabled = false;
                });
            };
        });

        // Ignore check buttons.
        panel.querySelectorAll('.sqi-ignore-btn').forEach(function (btn) {
            btn.onclick = function (e) {
                e.stopPropagation();
                var checkId = btn.getAttribute('data-check-id');
                if (!checkId) return;

                var reason = prompt('Reason for ignoring (optional):') || '';

                btn.disabled = true;
                btn.textContent = '...';

                fetch(scalynQA.restUrl + 'ignore', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': scalynQA.nonce },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        type: 'check',
                        check_id: checkId,
                        post_id: data.postId,
                        reason: reason,
                        context: 'audit',
                    }),
                })
                .then(function (r) { return r.json(); })
                .then(function (response) {
                    if (response.success) {
                        // Rescan to recalculate scores.
                        return fetch(scalynQA.restUrl + 'scan/' + data.postId, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': scalynQA.nonce },
                            credentials: 'same-origin',
                        }).then(function (r) { return r.json(); });
                    }
                })
                .then(function (scanResp) {
                    if (scanResp && scanResp.success && scanResp.data) {
                        // Add to local ignored list so it's filtered out.
                        if (ignoredChecks.indexOf(checkId) === -1) {
                            ignoredChecks.push(checkId);
                        }
                        data.hasScan = true;
                        data.results = scanResp.data;
                        refreshPanel();
                    }
                })
                .catch(function () {
                    btn.disabled = false;
                    btn.textContent = '\u2715';
                });
            };
        });

        // Generate All with AI button.
        var genAllBtn = document.getElementById('sqi-btn-generate-all');
        if (genAllBtn) {
            genAllBtn.onclick = function () {
                if (generateAllRun) return;
                generateAllRun = true;
                genAllBtn.disabled = true;
                genAllBtn.textContent = 'Generating...';

                var apiCalls = [
                    fetch(scalynQA.restUrl + 'ai/generate/' + data.postId, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': scalynQA.nonce }, credentials: 'same-origin' }).then(function (r) { return r.json(); }),
                    fetch(scalynQA.restUrl + 'ai/review/' + data.postId, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': scalynQA.nonce }, credentials: 'same-origin' }).then(function (r) { return r.json(); }),
                    fetch(scalynQA.restUrl + 'ai/generate-keywords/' + data.postId, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': scalynQA.nonce }, credentials: 'same-origin' }).then(function (r) { return r.json(); }),
                ];

                Promise.allSettled(apiCalls).then(function (settled) {
                    var responses = settled.map(function (s) { return s.status === 'fulfilled' ? s.value : null; });

                    // Store results.
                    if (responses[0] && responses[0].success && responses[0].data) {
                        aiDrafts = aiDrafts || {};
                        if (responses[0].data.title) aiDrafts.title = responses[0].data.title;
                        if (responses[0].data.description) aiDrafts.description = responses[0].data.description;
                        aiGenerated['meta_title_exists'] = true;
                        aiGenerated['meta_description_exists'] = true;
                    }

                    if (responses[1] && responses[1].success && responses[1].data) {
                        data.contentReview = responses[1].data;
                    }

                    if (responses[2] && responses[2].success && responses[2].data) {
                        data.aiKeywords = responses[2].data;
                        aiGenerated['focus_keyword'] = true;
                    }

                    refreshPanel();
                });
            };
        }

        // Generate/Regenerate AI buttons per check.
        panel.querySelectorAll('.sqi-gen-btn').forEach(function (btn) {
            btn.onclick = function (e) {
                e.stopPropagation();
                var checkId = btn.getAttribute('data-check-id');
                var endpoint = btn.getAttribute('data-endpoint');
                if (!checkId || !endpoint) return;

                btn.disabled = true;
                btn.textContent = 'Generating...';

                fetch(scalynQA.restUrl + endpoint + data.postId, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': scalynQA.nonce },
                    credentials: 'same-origin',
                })
                .then(function (r) { return r.json(); })
                .then(function (response) {
                    if (response.success && response.data) {
                        aiGenerated[checkId] = true;

                        // Store results for inline display.
                        if (checkId === 'meta_title_exists' || checkId === 'meta_description_exists') {
                            aiDrafts = aiDrafts || {};
                            if (response.data.title) aiDrafts.title = response.data.title;
                            if (response.data.description) aiDrafts.description = response.data.description;
                            // Mark both as generated.
                            aiGenerated['meta_title_exists'] = true;
                            aiGenerated['meta_description_exists'] = true;
                        }

                        refreshPanel();
                    }
                    btn.disabled = false;
                    btn.textContent = 'Regenerate';
                })
                .catch(function () {
                    btn.disabled = false;
                    btn.textContent = 'Regenerate';
                });
            };
        });

        // Copy buttons.
        panel.querySelectorAll('.sqi-copy-btn').forEach(function (btn) {
            btn.onclick = function (e) {
                e.stopPropagation();
                var text = btn.getAttribute('data-text');
                if (text && navigator.clipboard) {
                    navigator.clipboard.writeText(text);
                    btn.textContent = 'Copied!';
                    setTimeout(function () { btn.textContent = 'Copy'; }, 1500);
                }
            };
        });

        // Apply buttons (meta title/description to SEO plugin).
        panel.querySelectorAll('.sqi-apply-btn').forEach(function (btn) {
            btn.onclick = function (e) {
                e.stopPropagation();
                var checkId = btn.getAttribute('data-check-id');
                var text = btn.getAttribute('data-text');
                if (!checkId || !text) return;

                var applyData = {};
                if (checkId === 'meta_title_exists') applyData.title = text;
                if (checkId === 'meta_description_exists') applyData.description = text;

                btn.disabled = true;
                btn.textContent = 'Applying...';

                fetch(scalynQA.restUrl + 'ai/apply/' + data.postId, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': scalynQA.nonce },
                    credentials: 'same-origin',
                    body: JSON.stringify(applyData),
                })
                .then(function (r) { return r.json(); })
                .then(function (response) {
                    if (response.success) {
                        btn.textContent = 'Applied!';
                        btn.classList.add('sqi-action-btn--done');
                        // Rescan to update scores.
                        fetch(scalynQA.restUrl + 'scan/' + data.postId, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': scalynQA.nonce },
                            credentials: 'same-origin',
                        })
                        .then(function (r) { return r.json(); })
                        .then(function (scanResp) {
                            if (scanResp.success && scanResp.data) {
                                data.hasScan = true;
                                data.results = scanResp.data;
                                refreshPanel();
                            }
                        });
                    } else {
                        btn.disabled = false;
                        btn.textContent = 'Apply';
                    }
                })
                .catch(function () {
                    btn.disabled = false;
                    btn.textContent = 'Apply';
                });
            };
        });

        // Keyword Apply button.
        panel.querySelectorAll('.sqi-apply-kw-btn').forEach(function (btn) {
            btn.onclick = function (e) {
                e.stopPropagation();
                var isPro = data.aiKeywords && data.aiKeywords.has_pro;
                var primary = '';
                var secondary = [];

                if (isPro) {
                    // Checkbox mode: collect all checked values. First checked = primary, rest = secondary.
                    var checked = panel.querySelectorAll('input[name="sqi-kw[]"]:checked');
                    checked.forEach(function (cb, idx) {
                        if (idx === 0) primary = cb.value;
                        else secondary.push(cb.value);
                    });
                } else {
                    // Radio mode: single selected keyword.
                    var selected = panel.querySelector('input[name="sqi-kw"]:checked');
                    primary = selected ? selected.value : btn.getAttribute('data-primary');
                }

                if (!primary) return;

                btn.disabled = true;
                btn.textContent = 'Applying...';

                fetch(scalynQA.restUrl + 'ai/apply-keyword/' + data.postId, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': scalynQA.nonce },
                    credentials: 'same-origin',
                    body: JSON.stringify({ primary: primary, secondary: secondary }),
                })
                .then(function (r) { return r.json(); })
                .then(function (response) {
                    if (response.success) {
                        btn.textContent = 'Applied!';
                        btn.classList.add('sqi-action-btn--done');
                        // Rescan.
                        fetch(scalynQA.restUrl + 'scan/' + data.postId, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': scalynQA.nonce },
                            credentials: 'same-origin',
                        })
                        .then(function (r) { return r.json(); })
                        .then(function (scanResp) {
                            if (scanResp.success && scanResp.data) {
                                data.hasScan = true;
                                data.results = scanResp.data;
                                refreshPanel();
                            }
                        });
                    } else {
                        btn.disabled = false;
                        btn.textContent = 'Apply';
                    }
                })
                .catch(function () {
                    btn.disabled = false;
                    btn.textContent = 'Apply';
                });
            };
        });

        // Keyword radio/checkbox selection.
        panel.querySelectorAll('input[name="sqi-kw"], input[name="sqi-kw[]"]').forEach(function (input) {
            input.onchange = function () {
                if (input.type === 'radio') {
                    // Radio: single selection highlight.
                    panel.querySelectorAll('.sqi-kw-opt').forEach(function (opt) { opt.classList.remove('sqi-kw-opt--selected'); });
                    input.closest('.sqi-kw-opt').classList.add('sqi-kw-opt--selected');
                    // Update Copy button.
                    var copyBtn = panel.querySelector('.sqi-check[data-check-id="focus_keyword"] .sqi-copy-btn');
                    if (copyBtn) copyBtn.setAttribute('data-text', input.value);
                } else {
                    // Checkbox: toggle highlight per item.
                    var opt = input.closest('.sqi-kw-opt');
                    if (input.checked) opt.classList.add('sqi-kw-opt--selected');
                    else opt.classList.remove('sqi-kw-opt--selected');
                }
            };
        });

        // Featured image Apply button.
        panel.querySelectorAll('.sqi-apply-fi-btn').forEach(function (btn) {
            btn.onclick = function (e) {
                e.stopPropagation();
                // Get selected radio value.
                var selected = panel.querySelector('input[name="sqi-fi"]:checked');
                var attachmentId = selected ? parseInt(selected.value, 10) : parseInt(btn.getAttribute('data-attachment-id'), 10);
                if (!attachmentId) return;

                btn.disabled = true;
                btn.textContent = 'Applying...';

                fetch(scalynQA.restUrl + 'ai/apply-featured-image/' + data.postId, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': scalynQA.nonce },
                    credentials: 'same-origin',
                    body: JSON.stringify({ attachment_id: attachmentId }),
                })
                .then(function (r) { return r.json(); })
                .then(function (response) {
                    if (response.success) {
                        btn.textContent = 'Applied!';
                        btn.classList.add('sqi-action-btn--done');
                        data.currentThumbnail = attachmentId;
                        // Rescan to update.
                        fetch(scalynQA.restUrl + 'scan/' + data.postId, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': scalynQA.nonce },
                            credentials: 'same-origin',
                        })
                        .then(function (r) { return r.json(); })
                        .then(function (scanResp) {
                            if (scanResp.success && scanResp.data) {
                                data.hasScan = true;
                                data.results = scanResp.data;
                                refreshPanel();
                            }
                        });
                    } else {
                        btn.disabled = false;
                        btn.textContent = 'Apply';
                    }
                })
                .catch(function () {
                    btn.disabled = false;
                    btn.textContent = 'Apply';
                });
            };
        });

        // Featured image radio selection highlight.
        panel.querySelectorAll('input[name="sqi-fi"]').forEach(function (radio) {
            radio.onchange = function () {
                panel.querySelectorAll('.sqi-fi-opt').forEach(function (opt) { opt.classList.remove('sqi-fi-opt--selected'); });
                radio.closest('.sqi-fi-opt').classList.add('sqi-fi-opt--selected');
                // Update Apply button attachment ID.
                var applyBtn = panel.querySelector('.sqi-apply-fi-btn');
                if (applyBtn) applyBtn.setAttribute('data-attachment-id', radio.value);
            };
        });

        // Review Current button — recheck existing issues.
        var reviewBtn = document.getElementById('sqi-btn-review-current');
        if (reviewBtn) {
            reviewBtn.onclick = function () {
                reviewBtn.disabled = true;
                reviewBtn.textContent = 'Checking...';

                fetch(scalynQA.restUrl + 'ai/review/' + data.postId + '/recheck', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': scalynQA.nonce },
                    credentials: 'same-origin',
                })
                .then(function (r) { return r.json(); })
                .then(function (response) {
                    if (response.success && response.data) {
                        // Update local data with new review state.
                        data.contentReview = data.contentReview || {};
                        data.contentReview.issues = response.data.issues || [];
                        data.contentReview.score = response.data.score || data.contentReview.score;
                        data.contentReview.summary = response.data.summary || data.contentReview.summary;
                        refreshPanel();
                    }
                    reviewBtn.disabled = false;
                    reviewBtn.textContent = 'Review Current';
                })
                .catch(function () {
                    reviewBtn.disabled = false;
                    reviewBtn.textContent = 'Review Current';
                });
            };
        }

        // Regenerate button — full AI re-review.
        var regenBtn = document.getElementById('sqi-btn-review-regenerate');
        if (regenBtn) {
            regenBtn.onclick = function () {
                regenBtn.disabled = true;
                regenBtn.textContent = 'Reviewing...';

                fetch(scalynQA.restUrl + 'ai/review/' + data.postId, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': scalynQA.nonce },
                    credentials: 'same-origin',
                })
                .then(function (r) { return r.json(); })
                .then(function (response) {
                    if (response.success && response.data) {
                        data.contentReview = response.data;
                        refreshPanel();
                    }
                    regenBtn.disabled = false;
                    regenBtn.textContent = 'Regenerate';
                })
                .catch(function () {
                    regenBtn.disabled = false;
                    regenBtn.textContent = 'Regenerate';
                });
            };
        }

        // Category collapse/expand.
        panel.querySelectorAll('.sqi-category__header').forEach(function (header) {
            header.onclick = function () {
                header.parentElement.classList.toggle('sqi-category--open');
            };
        });

        // Check item click — scroll to element + highlight.
        panel.querySelectorAll('.sqi-check[data-check-id]').forEach(function (check) {
            check.onclick = function () {
                panel.querySelectorAll('.sqi-check--active').forEach(function (c) { c.classList.remove('sqi-check--active'); });
                check.classList.add('sqi-check--active');

                var checkId = check.getAttribute('data-check-id');
                var status = check.getAttribute('data-status');
                scrollToIssueElement(checkId, status);
            };
        });

        // Writing issue click — find text in page and scroll to it.
        panel.querySelectorAll('.sqi-review-issue').forEach(function (issue) {
            issue.onclick = function () {
                panel.querySelectorAll('.sqi-check--active').forEach(function (c) { c.classList.remove('sqi-check--active'); });
                issue.classList.add('sqi-check--active');

                var text = issue.getAttribute('data-issue-text');
                if (!text) return;
                scrollToTextInPage(text);
            };
        });

        // Dragging for floating mode.
        var header = panel.querySelector('.sqi-header');
        if (header) {
            header.onmousedown = function (e) {
                if (mode !== 'floating' || e.target.closest('.sqi-header__btn')) return;
                dragState = {
                    startX: e.clientX,
                    startY: e.clientY,
                    startLeft: panel.offsetLeft,
                    startTop: panel.offsetTop,
                };
                e.preventDefault();
            };
        }
    }

    // Global drag handlers.
    document.addEventListener('mousemove', function (e) {
        if (!dragState || mode !== 'floating') return;
        panel.style.left = (dragState.startLeft + e.clientX - dragState.startX) + 'px';
        panel.style.top = (dragState.startTop + e.clientY - dragState.startY) + 'px';
        panel.style.right = 'auto';
    });

    document.addEventListener('mouseup', function () {
        dragState = null;
    });

    function refreshPanel() {
        if (!panel) return;
        var body = panel.querySelector('.sqi-body');
        if (body) body.innerHTML = buildBody();
        clearHighlights();
        bindPanelEvents();
        if (highlightsEnabled) showHighlights();
    }

    // -------------------------------------------------------------------------
    // Visual Highlighting
    // -------------------------------------------------------------------------

    function showHighlights() {
        clearHighlights();
        if (!data.hasScan || !data.results || !data.results.results) return;

        var results = data.results.results;
        var categories = ['seo', 'content', 'functionality'];

        categories.forEach(function (cat) {
            var checks = results[cat] || [];
            checks.forEach(function (item) {
                if (item.status === 'pass') return;
                var elements = findDomElements(item);
                elements.forEach(function (el) {
                    createHighlight(el, item);
                });
            });
        });

        // Annotate headings with level badges.
        annotateHeadings();

        // Annotate images (alt text, lazy loading, dimensions, file size, broken).
        annotateImages();
        annotateImageIssues();

        // Annotate links (broken, placeholder).
        annotateBrokenLinks();
        annotatePlaceholderLinks();

        // Annotate buttons/forms.
        annotateEmptyButtons();
        annotateEmptyHeadings();
        annotateFormsWithoutSubmit();

        // Highlight writing issues from AI content review.
        highlightReviewIssues();
    }

    /**
     * Add heading level badges (H1, H2, H3...) to all headings on the page.
     * Color-coded: green = correct order, red = skipped level or duplicate H1, yellow = warning.
     */
    function annotateHeadings() {
        var headings = document.querySelectorAll('h1, h2, h3, h4, h5, h6');
        if (headings.length === 0) return;

        var prevLevel = 0;
        var h1Count = 0;

        headings.forEach(function (el) {
            if (isInInspector(el)) return;
            if ((el.textContent || '').trim() === '') return; // Empty headings handled separately.

            var tag = el.tagName.toLowerCase();
            var level = parseInt(tag.charAt(1), 10);
            var badgeColor = 'green';
            var issue = '';

            if (level === 1) {
                h1Count++;
                if (h1Count > 1) {
                    badgeColor = 'red';
                    issue = 'Multiple H1 tags \u2014 use only one H1 per page';
                }
            } else if (prevLevel > 0 && level > prevLevel + 1) {
                badgeColor = 'red';
                issue = 'Skipped level: H' + prevLevel + ' \u2192 H' + level + ' (expected H' + (prevLevel + 1) + ')';
            }

            prevLevel = level;

            var badge = document.createElement('span');
            badge.className = 'sqi-heading-badge sqi-heading-badge--' + badgeColor;
            badge.textContent = tag.toUpperCase();

            if (issue) {
                badge.addEventListener('mouseenter', function () {
                    showTooltip(el, {
                        id: 'heading_hierarchy',
                        status: 'fail',
                        label: 'Heading Hierarchy: ' + tag.toUpperCase(),
                        message: issue,
                        tooltip: 'Use a logical heading order (H1 \u2192 H2 \u2192 H3). Don\'t skip levels.',
                    });
                });
                badge.addEventListener('mouseleave', clearTooltip);
            }

            el.style.position = el.style.position || 'relative';
            el.appendChild(badge);
            annotationBadges.push(badge);
        });
    }

    /**
     * Add "No Alt" badges to images missing alt text.
     */
    function annotateImages() {
        if (!data.results || !data.results.results) return;

        var seoChecks = data.results.results.seo || [];
        var altCheck = null;
        seoChecks.forEach(function (c) { if (c.id === 'image_alt_text') altCheck = c; });
        if (!altCheck || altCheck.status === 'pass') return;

        var missingSrcs = (altCheck.details && altCheck.details.missing_alt_images) || [];
        missingSrcs.forEach(function (src) {
            var filename = src.split('/').pop();
            try {
                var imgs = document.querySelectorAll('img[src*="' + CSS.escape(filename) + '"]');
                imgs.forEach(function (img) {
                    if (isInInspector(img)) return;

                    var wrapper = img.parentElement;
                    if (wrapper) wrapper.style.position = wrapper.style.position || 'relative';

                    var badge = document.createElement('span');
                    badge.className = 'sqi-img-badge';
                    badge.textContent = 'No Alt';

                    badge.addEventListener('mouseenter', function () {
                        showTooltip(img, {
                            id: 'image_alt_text',
                            status: 'fail',
                            label: 'Missing Alt Text',
                            message: 'This image has no alt text: ' + filename,
                            tooltip: 'Add descriptive alt text for accessibility and SEO.',
                        });
                    });
                    badge.addEventListener('mouseleave', clearTooltip);

                    if (wrapper) wrapper.appendChild(badge);
                    annotationBadges.push(badge);
                });
            } catch (e) { /* invalid selector */ }
        });
    }

    /**
     * Helper: find a check by ID across all categories.
     */
    function findCheck(checkId) {
        if (!data.results || !data.results.results) return null;
        var cats = ['seo', 'content', 'functionality'];
        for (var i = 0; i < cats.length; i++) {
            var checks = data.results.results[cats[i]] || [];
            for (var j = 0; j < checks.length; j++) {
                if (checks[j].id === checkId) return checks[j];
            }
        }
        return null;
    }

    /**
     * Helper: create a badge on an element.
     */
    function addBadge(el, text, cssClass, tooltipData) {
        var badge = document.createElement('span');
        badge.className = 'sqi-element-badge ' + cssClass;
        badge.textContent = text;

        if (tooltipData) {
            badge.addEventListener('mouseenter', function () { showTooltip(el, tooltipData); });
            badge.addEventListener('mouseleave', clearTooltip);
        }

        el.style.position = el.style.position || 'relative';
        el.appendChild(badge);
        annotationBadges.push(badge);
    }

    /**
     * Helper: find images by src filename on the page.
     */
    function findImagesBySrc(src) {
        var filename = (src || '').split('/').pop().split('?')[0];
        if (!filename) return [];
        try {
            var imgs = document.querySelectorAll('img[src*="' + CSS.escape(filename) + '"]');
            var result = [];
            imgs.forEach(function (img) { if (!isInInspector(img)) result.push(img); });
            return result;
        } catch (e) { return []; }
    }

    /**
     * Annotate images: lazy loading, dimensions, file size, broken.
     */
    function annotateImageIssues() {
        if (!data.results || !data.results.results) return;

        // Lazy loading.
        var lazyCheck = findCheck('image_lazy_loading');
        if (lazyCheck && lazyCheck.status !== 'pass' && lazyCheck.details && lazyCheck.details.missing_lazy) {
            lazyCheck.details.missing_lazy.forEach(function (src) {
                findImagesBySrc(src).forEach(function (img) {
                    var wrapper = img.parentElement;
                    if (wrapper) wrapper.style.position = wrapper.style.position || 'relative';
                    var badge = document.createElement('span');
                    badge.className = 'sqi-img-badge sqi-img-badge--warning';
                    badge.textContent = 'NO LAZY';
                    badge.addEventListener('mouseenter', function () {
                        showTooltip(img, { id: 'image_lazy_loading', status: 'warning', label: 'Missing Lazy Loading', message: 'Add loading="lazy" to defer offscreen images.', tooltip: lazyCheck.tooltip || '' });
                    });
                    badge.addEventListener('mouseleave', clearTooltip);
                    if (wrapper) wrapper.appendChild(badge);
                    annotationBadges.push(badge);
                });
            });
        }

        // Missing dimensions.
        var dimCheck = findCheck('image_dimensions');
        if (dimCheck && dimCheck.status !== 'pass' && dimCheck.details && dimCheck.details.missing_dimensions) {
            dimCheck.details.missing_dimensions.forEach(function (src) {
                findImagesBySrc(src).forEach(function (img) {
                    var wrapper = img.parentElement;
                    if (wrapper) wrapper.style.position = wrapper.style.position || 'relative';
                    var badge = document.createElement('span');
                    badge.className = 'sqi-img-badge sqi-img-badge--warning';
                    badge.style.top = 'auto';
                    badge.style.bottom = '4px';
                    badge.textContent = 'NO SIZE';
                    badge.addEventListener('mouseenter', function () {
                        showTooltip(img, { id: 'image_dimensions', status: 'warning', label: 'Missing Dimensions', message: 'Add width and height attributes to prevent layout shifts (CLS).', tooltip: dimCheck.tooltip || '' });
                    });
                    badge.addEventListener('mouseleave', clearTooltip);
                    if (wrapper) wrapper.appendChild(badge);
                    annotationBadges.push(badge);
                });
            });
        }

        // Oversized images.
        var sizeCheck = findCheck('image_file_size');
        if (sizeCheck && sizeCheck.status !== 'pass' && sizeCheck.details && sizeCheck.details.oversized_images) {
            sizeCheck.details.oversized_images.forEach(function (entry) {
                // Format: "filename.jpg (2400KB)"
                var match = entry.match(/^(.+?)\s*\((\d+)KB\)$/);
                if (!match) return;
                var src = match[1].replace(/\.\.\.$/,'');
                var sizeKb = match[2];
                findImagesBySrc(src).forEach(function (img) {
                    var wrapper = img.parentElement;
                    if (wrapper) wrapper.style.position = wrapper.style.position || 'relative';
                    var badge = document.createElement('span');
                    badge.className = 'sqi-img-badge sqi-img-badge--warning';
                    badge.style.left = 'auto';
                    badge.style.right = '4px';
                    badge.textContent = sizeKb + 'KB';
                    badge.addEventListener('mouseenter', function () {
                        showTooltip(img, { id: 'image_file_size', status: 'warning', label: 'Oversized Image', message: 'This image is ' + sizeKb + 'KB. Compress or resize to improve load speed.', tooltip: sizeCheck.tooltip || '' });
                    });
                    badge.addEventListener('mouseleave', clearTooltip);
                    if (wrapper) wrapper.appendChild(badge);
                    annotationBadges.push(badge);
                });
            });
        }

        // Broken images.
        var brokenMediaCheck = findCheck('broken_media');
        if (brokenMediaCheck && brokenMediaCheck.status !== 'pass' && brokenMediaCheck.details && brokenMediaCheck.details.broken_images) {
            brokenMediaCheck.details.broken_images.forEach(function (src) {
                findImagesBySrc(src).forEach(function (img) {
                    var wrapper = img.parentElement;
                    if (wrapper) wrapper.style.position = wrapper.style.position || 'relative';
                    var badge = document.createElement('span');
                    badge.className = 'sqi-img-badge sqi-img-badge--error';
                    badge.textContent = 'BROKEN';
                    badge.addEventListener('mouseenter', function () {
                        showTooltip(img, { id: 'broken_media', status: 'fail', label: 'Broken Image', message: 'Image source not found: ' + src, tooltip: 'Re-upload or fix the image URL.' });
                    });
                    badge.addEventListener('mouseleave', clearTooltip);
                    if (wrapper) wrapper.appendChild(badge);
                    annotationBadges.push(badge);
                });
            });
        }
    }

    /**
     * Annotate broken links with status code badges.
     */
    function annotateBrokenLinks() {
        if (!data.results || !data.results.results) return;
        var checks = data.results.results.functionality || [];
        checks.forEach(function (item) {
            if (item.id !== 'broken_links' || item.status === 'pass') return;
            var url = (item.details && item.details.url) || '';
            var statusCode = (item.details && item.details.status_code) || '?';
            if (!url) return;

            try {
                var links = document.querySelectorAll('a[href*="' + CSS.escape(url) + '"]');
                links.forEach(function (link) {
                    if (isInInspector(link)) return;
                    link.style.position = link.style.position || 'relative';

                    var badge = document.createElement('span');
                    badge.className = 'sqi-link-badge sqi-link-badge--error';
                    badge.textContent = statusCode;
                    badge.addEventListener('mouseenter', function () {
                        showTooltip(link, { id: 'broken_links', status: 'fail', label: 'Broken Link (' + statusCode + ')', message: 'URL: ' + url, tooltip: 'Update or remove this broken link.' });
                    });
                    badge.addEventListener('mouseleave', clearTooltip);
                    link.appendChild(badge);
                    annotationBadges.push(badge);
                });
            } catch (e) {}
        });
    }

    /**
     * Annotate placeholder links (href="#", javascript:void).
     */
    function annotatePlaceholderLinks() {
        var check = findCheck('placeholder_links');
        if (!check || check.status === 'pass') return;

        // Find all placeholder links in the DOM.
        var selectors = ['a[href="#"]', 'a[href="javascript:void(0)"]', 'a[href="javascript:void(0);"]', 'a[href="javascript:;"]'];
        selectors.forEach(function (sel) {
            try {
                document.querySelectorAll(sel).forEach(function (link) {
                    if (isInInspector(link)) return;
                    link.style.position = link.style.position || 'relative';

                    var badge = document.createElement('span');
                    badge.className = 'sqi-link-badge sqi-link-badge--warning';
                    badge.textContent = '#';
                    badge.addEventListener('mouseenter', function () {
                        showTooltip(link, { id: 'placeholder_links', status: 'warning', label: 'Placeholder Link', message: 'This link points to "' + link.getAttribute('href') + '".', tooltip: 'Replace with a real URL or convert to a button element.' });
                    });
                    badge.addEventListener('mouseleave', clearTooltip);
                    link.appendChild(badge);
                    annotationBadges.push(badge);
                });
            } catch (e) {}
        });
    }

    /**
     * Annotate empty buttons (no text, no aria-label).
     */
    function annotateEmptyButtons() {
        var check = findCheck('empty_buttons');
        if (!check || check.status === 'pass') return;

        document.querySelectorAll('button, a[role="button"], input[type="button"], input[type="submit"]').forEach(function (btn) {
            if (isInInspector(btn)) return;
            var text = (btn.textContent || '').trim();
            var aria = (btn.getAttribute('aria-label') || '').trim();
            var title = (btn.getAttribute('title') || '').trim();
            var value = (btn.getAttribute('value') || '').trim();

            if (text || aria || title || value) return; // Has accessible text.

            btn.style.position = btn.style.position || 'relative';
            var badge = document.createElement('span');
            badge.className = 'sqi-element-badge sqi-element-badge--error';
            badge.textContent = 'EMPTY';
            badge.addEventListener('mouseenter', function () {
                showTooltip(btn, { id: 'empty_buttons', status: 'fail', label: 'Empty Button', message: 'This button has no visible text or aria-label.', tooltip: 'Add text content or an aria-label for accessibility.' });
            });
            badge.addEventListener('mouseleave', clearTooltip);
            btn.appendChild(badge);
            annotationBadges.push(badge);
        });
    }

    /**
     * Annotate empty headings.
     */
    function annotateEmptyHeadings() {
        var check = findCheck('empty_headings');
        if (!check || check.status === 'pass') return;

        document.querySelectorAll('h1, h2, h3, h4, h5, h6').forEach(function (el) {
            if (isInInspector(el)) return;
            if ((el.textContent || '').trim() !== '') return; // Has text.

            el.style.position = el.style.position || 'relative';
            el.style.minHeight = el.style.minHeight || '24px';
            var tag = el.tagName.toUpperCase();
            var badge = document.createElement('span');
            badge.className = 'sqi-heading-badge sqi-heading-badge--red';
            badge.textContent = tag + ' EMPTY';
            badge.addEventListener('mouseenter', function () {
                showTooltip(el, { id: 'empty_headings', status: 'fail', label: 'Empty Heading', message: 'This ' + tag + ' heading has no text content.', tooltip: 'Add text or remove the empty heading element.' });
            });
            badge.addEventListener('mouseleave', clearTooltip);
            el.appendChild(badge);
            annotationBadges.push(badge);
        });
    }

    /**
     * Annotate forms without submit buttons.
     */
    function annotateFormsWithoutSubmit() {
        var check = findCheck('form_has_submit');
        if (!check || check.status === 'pass') return;

        document.querySelectorAll('form').forEach(function (form) {
            if (isInInspector(form)) return;
            var hasSubmit = form.querySelector('input[type="submit"], button[type="submit"], button:not([type])');
            if (hasSubmit) return;

            form.style.position = form.style.position || 'relative';
            var badge = document.createElement('span');
            badge.className = 'sqi-element-badge sqi-element-badge--warning';
            badge.textContent = 'NO SUBMIT';
            badge.style.position = 'absolute';
            badge.style.top = '4px';
            badge.style.right = '4px';
            badge.addEventListener('mouseenter', function () {
                showTooltip(form, { id: 'form_has_submit', status: 'warning', label: 'Missing Submit Button', message: 'This form has no submit button.', tooltip: 'Add a submit button so users can submit the form.' });
            });
            badge.addEventListener('mouseleave', clearTooltip);
            form.appendChild(badge);
            annotationBadges.push(badge);
        });
    }

    function highlightReviewIssues() {
        var review = data.contentReview;
        if (!review || !review.issues) return;

        var issues = review.issues.filter(function (i) { return i.status !== 'resolved' && i.status !== 'ignored' && i.text; });

        issues.forEach(function (issue) {
            var mark = wrapTextWithMark(issue.text, issue);
            if (mark) {
                textMarks.push(mark);
            }
        });
    }

    /**
     * Find a text string in the page and wrap it with a <mark> highlight.
     * Returns the mark element or null.
     */
    function wrapTextWithMark(searchText, issue) {
        if (!searchText || searchText.length < 2) return null;

        var walker = document.createTreeWalker(
            document.body,
            NodeFilter.SHOW_TEXT,
            {
                acceptNode: function (node) {
                    if (isInInspector(node.parentElement)) return NodeFilter.FILTER_REJECT;
                    if (node.parentElement.closest('.sqi-text-highlight')) return NodeFilter.FILTER_REJECT;
                    var idx = node.textContent.toLowerCase().indexOf(searchText.toLowerCase());
                    if (idx !== -1) return NodeFilter.FILTER_ACCEPT;
                    return NodeFilter.FILTER_REJECT;
                }
            }
        );

        var textNode = walker.nextNode();
        if (!textNode) return null;

        var idx = textNode.textContent.toLowerCase().indexOf(searchText.toLowerCase());
        if (idx === -1) return null;

        // Split the text node and wrap the matched portion.
        var before = textNode.textContent.substring(0, idx);
        var matched = textNode.textContent.substring(idx, idx + searchText.length);
        var after = textNode.textContent.substring(idx + searchText.length);

        var sevClass = issue.severity === 'error' ? 'sqi-text-highlight--error' : 'sqi-text-highlight--warning';
        var typeLabel = (issue.type || 'issue').replace(/_/g, ' ');
        typeLabel = typeLabel.charAt(0).toUpperCase() + typeLabel.slice(1);

        var mark = document.createElement('mark');
        mark.className = 'sqi-text-highlight ' + sevClass;
        mark.textContent = matched;
        mark.setAttribute('data-sqi-issue', typeLabel);
        mark.setAttribute('data-sqi-suggestion', issue.suggestion || '');
        mark.setAttribute('data-sqi-text', issue.text || '');

        // Tooltip on hover.
        mark.addEventListener('mouseenter', function () {
            showTooltip(mark, {
                id: '_review',
                status: issue.severity === 'error' ? 'fail' : 'warning',
                label: typeLabel,
                message: issue.suggestion ? 'Suggestion: ' + issue.suggestion : '',
                tooltip: issue.context || '',
            });
        });
        mark.addEventListener('mouseleave', clearTooltip);

        var parent = textNode.parentNode;
        var frag = document.createDocumentFragment();
        if (before) frag.appendChild(document.createTextNode(before));
        frag.appendChild(mark);
        if (after) frag.appendChild(document.createTextNode(after));
        parent.replaceChild(frag, textNode);

        return mark;
    }

    function findDomElements(item) {
        var elements = [];
        var selectorFn = DOM_SELECTORS[item.id];

        if (selectorFn) {
            var selectors = selectorFn(item.details || {});
            selectors.forEach(function (sel) {
                try {
                    document.querySelectorAll(sel).forEach(function (el) {
                        if (!isInInspector(el)) elements.push(el);
                    });
                } catch (e) { /* invalid selector */ }
            });
        }

        return elements;
    }

    function isInInspector(el) {
        return el.closest('#sqi-panel') || el.closest('#wpadminbar');
    }

    function createHighlight(targetEl, item) {
        var rect = targetEl.getBoundingClientRect();
        var scrollX = window.scrollX || window.pageXOffset;
        var scrollY = window.scrollY || window.pageYOffset;

        var overlay = document.createElement('div');
        overlay.className = 'sqi-highlight sqi-highlight--' + item.status;
        overlay.style.left = (rect.left + scrollX - 2) + 'px';
        overlay.style.top = (rect.top + scrollY - 2) + 'px';
        overlay.style.width = (rect.width + 4) + 'px';
        overlay.style.height = (rect.height + 4) + 'px';

        overlay.addEventListener('mouseenter', function () {
            showTooltip(targetEl, item);
        });
        overlay.addEventListener('mouseleave', function () {
            clearTooltip();
        });

        // Store the original pointer-events so we can allow click-through.
        overlay.style.pointerEvents = 'auto';

        document.body.appendChild(overlay);
        highlights.push({ overlay: overlay, target: targetEl, item: item });
    }

    function clearHighlights() {
        highlights.forEach(function (h) {
            if (h.overlay.parentNode) h.overlay.parentNode.removeChild(h.overlay);
        });
        highlights = [];

        // Unwrap text marks — restore original text nodes.
        textMarks.forEach(function (mark) {
            if (!mark.parentNode) return;
            var text = document.createTextNode(mark.textContent);
            mark.parentNode.replaceChild(text, mark);
            text.parentNode.normalize();
        });
        textMarks = [];

        // Remove annotation badges (headings, images).
        annotationBadges.forEach(function (badge) {
            if (badge.parentNode) badge.parentNode.removeChild(badge);
        });
        annotationBadges = [];

        clearTooltip();
    }

    // Reposition highlights on scroll/resize.
    var repositionTimer = null;
    function repositionHighlights() {
        if (repositionTimer) return;
        repositionTimer = requestAnimationFrame(function () {
            repositionTimer = null;
            var scrollX = window.scrollX || window.pageXOffset;
            var scrollY = window.scrollY || window.pageYOffset;
            highlights.forEach(function (h) {
                var rect = h.target.getBoundingClientRect();
                h.overlay.style.left = (rect.left + scrollX - 2) + 'px';
                h.overlay.style.top = (rect.top + scrollY - 2) + 'px';
                h.overlay.style.width = (rect.width + 4) + 'px';
                h.overlay.style.height = (rect.height + 4) + 'px';
            });
        });
    }

    window.addEventListener('scroll', repositionHighlights, { passive: true });
    window.addEventListener('resize', repositionHighlights);

    // -------------------------------------------------------------------------
    // Tooltips
    // -------------------------------------------------------------------------

    function showTooltip(targetEl, item) {
        clearTooltip();

        var rect = targetEl.getBoundingClientRect();
        var scrollX = window.scrollX || window.pageXOffset;
        var scrollY = window.scrollY || window.pageYOffset;

        var tip = document.createElement('div');
        tip.className = 'sqi-tooltip';

        var iconClass = item.status === 'fail' ? 'sqi-tooltip__title-icon--fail' : 'sqi-tooltip__title-icon--warning';
        var icon = STATUS_ICONS[item.status] || '?';

        tip.innerHTML = '<div class="sqi-tooltip__title"><span class="' + iconClass + '">' + icon + '</span> ' + esc(item.label) + '</div>';

        if (item.message) {
            tip.innerHTML += '<div class="sqi-tooltip__why">' + esc(item.message) + '</div>';
        }

        if (item.tooltip) {
            tip.innerHTML += '<div class="sqi-tooltip__fix">' + esc(item.tooltip) + '</div>';
        }

        document.body.appendChild(tip);

        // Position: prefer above the element, fall back to below.
        var tipRect = tip.getBoundingClientRect();
        var top = rect.top + scrollY - tipRect.height - 8;
        if (top < scrollY + 40) {
            top = rect.bottom + scrollY + 8;
        }
        var left = rect.left + scrollX;
        if (left + tipRect.width > window.innerWidth + scrollX - 20) {
            left = window.innerWidth + scrollX - tipRect.width - 20;
        }

        tip.style.top = top + 'px';
        tip.style.left = Math.max(10, left) + 'px';

        activeTooltip = tip;
    }

    function clearTooltip() {
        if (activeTooltip && activeTooltip.parentNode) {
            activeTooltip.parentNode.removeChild(activeTooltip);
        }
        activeTooltip = null;
    }

    // -------------------------------------------------------------------------
    // Scroll to Issue Element
    // -------------------------------------------------------------------------

    function scrollToIssueElement(checkId, status) {
        // Find the first highlight for this check ID.
        var target = null;
        highlights.forEach(function (h) {
            if (!target && h.item.id === checkId) {
                target = h;
            }
        });

        if (target) {
            target.target.scrollIntoView({ behavior: 'smooth', block: 'center' });

            // Flash highlight.
            target.overlay.classList.add('sqi-highlight--active');
            setTimeout(function () {
                target.overlay.classList.remove('sqi-highlight--active');
            }, 2000);

            showTooltip(target.target, target.item);
            setTimeout(clearTooltip, 4000);
            return;
        }

        // No DOM element — try to find one.
        if (!data.results || !data.results.results) return;

        var categories = ['seo', 'content', 'functionality'];
        var item = null;
        for (var i = 0; i < categories.length; i++) {
            var checks = data.results.results[categories[i]] || [];
            for (var j = 0; j < checks.length; j++) {
                if (checks[j].id === checkId) {
                    item = checks[j];
                    break;
                }
            }
            if (item) break;
        }

        if (!item) return;

        var elements = findDomElements(item);
        if (elements.length > 0) {
            elements[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
            // Create temporary highlight.
            createHighlight(elements[0], item);
            var lastH = highlights[highlights.length - 1];
            if (lastH) {
                lastH.overlay.classList.add('sqi-highlight--active');
                setTimeout(function () {
                    lastH.overlay.classList.remove('sqi-highlight--active');
                }, 2000);
            }
        }
    }

    /**
     * Find text in the page and scroll to its highlighted mark.
     */
    function scrollToTextInPage(searchText) {
        if (!searchText || searchText.length < 2) return;

        // Find existing mark for this text.
        var mark = null;
        textMarks.forEach(function (m) {
            if (!mark && m.getAttribute('data-sqi-text') === searchText) {
                mark = m;
            }
        });

        if (mark) {
            mark.scrollIntoView({ behavior: 'smooth', block: 'center' });
            mark.classList.add('sqi-text-highlight--active');
            setTimeout(function () { mark.classList.remove('sqi-text-highlight--active'); }, 2500);
            return;
        }

        // No existing mark — try to create one on the fly.
        var review = data.contentReview;
        var issue = null;
        if (review && review.issues) {
            review.issues.forEach(function (i) {
                if (!issue && i.text === searchText) issue = i;
            });
        }

        if (issue) {
            var newMark = wrapTextWithMark(searchText, issue);
            if (newMark) {
                textMarks.push(newMark);
                newMark.scrollIntoView({ behavior: 'smooth', block: 'center' });
                newMark.classList.add('sqi-text-highlight--active');
                setTimeout(function () { newMark.classList.remove('sqi-text-highlight--active'); }, 2500);
            }
        }
    }

    // -------------------------------------------------------------------------
    // Toolbar Integration
    // -------------------------------------------------------------------------

    function initToolbarToggle() {
        document.addEventListener('click', function (e) {
            var toggle = e.target.closest('#wp-admin-bar-scalyn-qa-score > a, .scalyn-qa-inspector-toggle > a, .scalyn-qa-inspector-toggle');
            if (!toggle) return;
            e.preventDefault();
            e.stopPropagation();

            if (mode === 'closed') {
                openInspector('docked');
            } else {
                closeInspector();
            }
        });
    }

    // -------------------------------------------------------------------------
    // Init
    // -------------------------------------------------------------------------

    function init() {
        initToolbarToggle();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();

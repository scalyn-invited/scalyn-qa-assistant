/**
 * Metabox JS.
 *
 * Handles the Scalyn QA Checklist metabox on post edit screens:
 * loading scan results, rescanning, AI meta generation, applying
 * meta, clipboard copy, expanding/collapsing checks, and auto-rescan
 * after save.
 *
 * @package Scalyn\QA\Assets
 * @since   1.0.0
 */

'use strict';

(function () {

    /** @type {number|null} The current post ID. */
    var postId = null;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Wrapper for fetch() that adds the REST nonce header and base URL.
     *
     * @param {string} endpoint - Relative endpoint path.
     * @param {Object} options  - Additional fetch options.
     * @returns {Promise<Object>} Parsed JSON response.
     */
    function fetchApi(endpoint, options) {
        options = options || {};

        var url = scalynQA.restUrl + endpoint.replace(/^\//, '');

        var headers = Object.assign({
            'Content-Type': 'application/json',
            'X-WP-Nonce': scalynQA.nonce,
        }, options.headers || {});

        var config = Object.assign({}, options, {
            headers: headers,
            credentials: 'same-origin',
        });

        return fetch(url, config)
            .then(function (response) {
                if (!response.ok) {
                    return response.json().then(function (err) {
                        throw new Error(err.message || 'Request failed with status ' + response.status);
                    });
                }
                return response.json();
            });
    }

    /**
     * Escape HTML special characters.
     *
     * @param {string} str - String to escape.
     * @returns {string}
     */
    function escapeHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    /**
     * Calculate traffic-light status for a score.
     *
     * @param {number} score - Score (0-100).
     * @returns {string}
     */
    function getStatus(score) {
        var settings = scalynQA.settings || {};
        var greenThreshold = parseInt(settings.green_threshold, 10) || 80;
        var yellowThreshold = parseInt(settings.yellow_threshold, 10) || 50;

        if (score >= greenThreshold) return 'green';
        if (score >= yellowThreshold) return 'yellow';
        return 'red';
    }

    /**
     * Get the metabox container element.
     *
     * @returns {HTMLElement|null}
     */
    function getMetabox() {
        return document.getElementById('scalyn_qa_checklist') ||
            document.querySelector('.scalyn-qa-metabox');
    }

    // -------------------------------------------------------------------------
    // Load Scan Results
    // -------------------------------------------------------------------------

    /**
     * Load current scan results for the post and update the metabox.
     */
    function loadScanResults() {
        if (!postId) return;

        fetchApi('scan/' + postId)
            .then(function (response) {
                if (response.success && response.data) {
                    renderMetaboxContent(response.data);
                }
            })
            .catch(function (err) {
                // No results yet - this is fine on first load.
                if (err.message && err.message.indexOf('not found') === -1) {
                    console.error('Scalyn QA: Failed to load scan results.', err);
                }
            });
    }

    /**
     * Render scan results into the metabox.
     *
     * @param {Object} data - Scan result data.
     */
    function renderMetaboxContent(data) {
        var metabox = getMetabox();
        if (!metabox) return;

        var inside = metabox.querySelector('.inside') || metabox;
        var scores = data.scores || {};
        var overallScore = scores.overall || 0;
        var status = scores.status || getStatus(overallScore);

        var html = '<div class="scalyn-qa-metabox-content">';

        // Score display.
        html += '<div class="scalyn-metabox-score">';
        html += '<div class="scalyn-score-circle scalyn-score-circle--' + escapeHtml(status) + '" style="--score-percent: ' + overallScore + '%">';
        html += '<span class="scalyn-score-circle__value">' + overallScore + '</span>';
        html += '<span class="scalyn-score-circle__percent">%</span>';
        html += '</div>';
        html += '<div class="scalyn-metabox-score-breakdown">';
        html += '<span>SEO: ' + (scores.seo || 0) + '%</span>';
        html += '<span>Content: ' + (scores.content || 0) + '%</span>';
        html += '<span>Func: ' + (scores.functionality || 0) + '%</span>';
        html += '</div>';
        html += '</div>';

        // Issue count.
        var issueCount = 0;
        if (data.results) {
            Object.keys(data.results).forEach(function (cat) {
                var items = data.results[cat];
                if (Array.isArray(items)) {
                    items.forEach(function (item) {
                        if (item.status === 'fail' || item.status === 'warning') {
                            issueCount++;
                        }
                    });
                }
            });
        }

        if (issueCount > 0) {
            html += '<p class="scalyn-metabox-issues">' + issueCount + ' issue' + (issueCount !== 1 ? 's' : '') + ' found</p>';
        } else {
            html += '<p class="scalyn-metabox-issues scalyn-text--green">All checks passed!</p>';
        }

        // Check items (collapsed by default).
        if (data.results) {
            html += '<div class="scalyn-metabox-checks">';
            Object.keys(data.results).forEach(function (category) {
                var items = data.results[category];
                if (!Array.isArray(items) || items.length === 0) return;

                // Only show failed/warning items in the metabox.
                var issues = items.filter(function (item) {
                    return item.status === 'fail' || item.status === 'warning';
                });

                if (issues.length === 0) return;

                html += '<div class="scalyn-metabox-category">';
                html += '<button type="button" class="scalyn-metabox-toggle" data-category="' + escapeHtml(category) + '">';
                html += '<span class="dashicons dashicons-arrow-right-alt2"></span> ';
                html += escapeHtml(category) + ' (' + issues.length + ')';
                html += '</button>';
                html += '<div class="scalyn-metabox-category-items" style="display:none;">';

                issues.forEach(function (item) {
                    var statusIcons = {
                        fail: 'dashicons-dismiss',
                        warning: 'dashicons-warning',
                    };
                    var icon = statusIcons[item.status] || 'dashicons-marker';

                    html += '<div class="scalyn-metabox-check-item scalyn-metabox-check-item--' + escapeHtml(item.status) + '">';
                    html += '<span class="dashicons ' + icon + '"></span> ';
                    html += '<span class="scalyn-metabox-check-label">' + escapeHtml(item.label) + '</span>';
                    if (item.message) {
                        html += '<span class="scalyn-metabox-check-msg">' + escapeHtml(item.message) + '</span>';
                    }
                    html += '</div>';
                });

                html += '</div></div>';
            });
            html += '</div>';
        }

        // Action buttons.
        html += '<div class="scalyn-metabox-actions">';
        html += '<button type="button" class="button scalyn-metabox-rescan" data-post-id="' + postId + '">Rescan</button>';
        html += '<button type="button" class="button scalyn-metabox-generate-ai" data-post-id="' + postId + '">Generate AI Meta</button>';
        html += '<a href="' + escapeHtml(getAuditUrl()) + '" class="button" target="_blank">View Full Audit</a>';
        html += '</div>';

        // AI results container.
        html += '<div class="scalyn-metabox-ai-results"></div>';

        html += '</div>';

        // Find the content area - preserve the nonce field.
        var nonceField = inside.querySelector('[name="scalyn_qa_metabox_nonce"]');
        inside.innerHTML = '';
        if (nonceField) {
            inside.appendChild(nonceField);
        }
        inside.insertAdjacentHTML('beforeend', html);
    }

    /**
     * Get the audit page URL for the current post.
     *
     * @returns {string}
     */
    function getAuditUrl() {
        var adminUrl = scalynQA.restUrl.split('/wp-json/')[0] + '/wp-admin/';
        return adminUrl + 'admin.php?page=scalyn-qa-audits&post_id=' + postId;
    }

    // -------------------------------------------------------------------------
    // Rescan
    // -------------------------------------------------------------------------

    /**
     * Initialize the rescan button handler.
     */
    function initRescan() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.scalyn-metabox-rescan');
            if (!btn) return;

            var pid = btn.getAttribute('data-post-id') || postId;
            if (!pid) return;

            btn.disabled = true;
            btn.textContent = 'Scanning...';

            fetchApi('scan/' + pid, { method: 'POST' })
                .then(function (response) {
                    if (response.success && response.data) {
                        renderMetaboxContent(response.data);
                    }
                })
                .catch(function (err) {
                    console.error('Scalyn QA: Rescan failed.', err);
                    btn.textContent = 'Rescan';
                    btn.disabled = false;
                });
        });
    }

    // -------------------------------------------------------------------------
    // AI Meta
    // -------------------------------------------------------------------------

    /**
     * Initialize the "Generate AI Meta" button handler.
     */
    function initGenerateAiMeta() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.scalyn-metabox-generate-ai');
            if (!btn) return;

            var pid = btn.getAttribute('data-post-id') || postId;
            if (!pid) return;

            btn.disabled = true;
            btn.textContent = 'Generating...';

            fetchApi('ai/generate/' + pid, { method: 'POST' })
                .then(function (response) {
                    if (response.success && response.data) {
                        displayAiResults(response.data);
                    }
                })
                .catch(function (err) {
                    console.error('Scalyn QA: AI generation failed.', err);
                    if (typeof ScalynAlert !== 'undefined') {
                        ScalynAlert.error('AI Generation Failed', err.message || 'An error occurred.');
                    }
                })
                .finally(function () {
                    btn.textContent = 'Generate AI Meta';
                    btn.disabled = false;
                });
        });
    }

    /**
     * Display AI results inline in the metabox.
     *
     * @param {Object} data - AI result data.
     */
    function displayAiResults(data) {
        var container = document.querySelector('.scalyn-metabox-ai-results');
        if (!container) return;

        var html = '<div class="scalyn-metabox-ai-panel">';
        html += '<h4>AI Suggestions</h4>';
        html += '<p class="scalyn-metabox-ai-provider">by ' + escapeHtml(data.provider) + '</p>';

        if (data.title) {
            html += '<div class="scalyn-metabox-ai-field">';
            html += '<label>Meta Title</label>';
            html += '<div class="scalyn-metabox-ai-value" data-field="title">' + escapeHtml(data.title) + '</div>';
            html += '<button type="button" class="button button-small scalyn-metabox-copy" data-field="title">Copy</button>';
            html += '</div>';
        }

        if (data.description) {
            html += '<div class="scalyn-metabox-ai-field">';
            html += '<label>Meta Description</label>';
            html += '<div class="scalyn-metabox-ai-value" data-field="description">' + escapeHtml(data.description) + '</div>';
            html += '<button type="button" class="button button-small scalyn-metabox-copy" data-field="description">Copy</button>';
            html += '</div>';
        }

        html += '<div class="scalyn-metabox-ai-actions">';
        html += '<button type="button" class="button button-primary scalyn-metabox-apply-ai" data-post-id="' + postId + '">Apply to SEO Plugin</button>';
        html += '</div>';
        html += '</div>';

        container.innerHTML = html;
    }

    /**
     * Initialize "Apply AI Meta" button handler.
     */
    function initApplyAiMeta() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.scalyn-metabox-apply-ai');
            if (!btn) return;

            var pid = btn.getAttribute('data-post-id') || postId;
            if (!pid) return;

            var panel = btn.closest('.scalyn-metabox-ai-panel');
            if (!panel) return;

            var titleEl = panel.querySelector('[data-field="title"]');
            var descEl = panel.querySelector('[data-field="description"]');

            var title = titleEl ? titleEl.textContent.trim() : '';
            var description = descEl ? descEl.textContent.trim() : '';

            if (!title && !description) return;

            btn.disabled = true;
            btn.textContent = 'Applying...';

            fetchApi('ai/apply/' + pid, {
                method: 'POST',
                body: JSON.stringify({ title: title, description: description }),
            })
                .then(function (response) {
                    if (response.success) {
                        if (typeof ScalynAlert !== 'undefined') {
                            ScalynAlert.toast('Meta applied to SEO plugin');
                        } else {
                            btn.textContent = 'Applied!';
                        }
                    }
                })
                .catch(function (err) {
                    if (typeof ScalynAlert !== 'undefined') {
                        ScalynAlert.error('Apply Failed', err.message || 'Failed to apply meta.');
                    }
                })
                .finally(function () {
                    btn.disabled = false;
                    btn.textContent = 'Apply to SEO Plugin';
                });
        });
    }

    /**
     * Initialize clipboard copy buttons.
     */
    function initCopyMeta() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.scalyn-metabox-copy');
            if (!btn) return;

            var field = btn.getAttribute('data-field');
            var container = btn.closest('.scalyn-metabox-ai-field');
            var valueEl = container ? container.querySelector('.scalyn-metabox-ai-value') : null;
            if (!valueEl) return;

            var text = valueEl.textContent.trim();

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function () {
                    btn.textContent = 'Copied!';
                    setTimeout(function () { btn.textContent = 'Copy'; }, 2000);
                }).catch(function () {
                    fallbackCopy(text, btn);
                });
            } else {
                fallbackCopy(text, btn);
            }
        });
    }

    /**
     * Fallback clipboard copy.
     *
     * @param {string}      text - Text to copy.
     * @param {HTMLElement}  btn  - The button to update.
     */
    function fallbackCopy(text, btn) {
        var textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        try {
            document.execCommand('copy');
            if (btn) {
                btn.textContent = 'Copied!';
                setTimeout(function () { btn.textContent = 'Copy'; }, 2000);
            }
        } catch (err) {
            console.error('Scalyn QA: Copy failed.', err);
        }
        document.body.removeChild(textarea);
    }

    // -------------------------------------------------------------------------
    // Check Details Toggle
    // -------------------------------------------------------------------------

    /**
     * Initialize expand/collapse for check categories.
     */
    function initToggleChecks() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.scalyn-metabox-toggle');
            if (!btn) return;

            var category = btn.closest('.scalyn-metabox-category');
            if (!category) return;

            var items = category.querySelector('.scalyn-metabox-category-items');
            if (!items) return;

            var isVisible = items.style.display !== 'none';
            items.style.display = isVisible ? 'none' : '';

            var icon = btn.querySelector('.dashicons');
            if (icon) {
                icon.classList.toggle('dashicons-arrow-right-alt2', isVisible);
                icon.classList.toggle('dashicons-arrow-down-alt2', !isVisible);
            }
        });
    }

    // -------------------------------------------------------------------------
    // Auto-rescan After Save
    // -------------------------------------------------------------------------

    /**
     * Listen for post save events and auto-rescan.
     * Supports both Gutenberg (wp.data) and Classic Editor (form submit).
     */
    function initAutoRescan() {
        // Gutenberg: subscribe to wp.data for save completion.
        if (typeof wp !== 'undefined' && wp.data && wp.data.subscribe) {
            var wasSaving = false;

            wp.data.subscribe(function () {
                var editor = wp.data.select('core/editor');
                if (!editor) return;

                var isSaving = editor.isSavingPost();
                var isAutosaving = editor.isAutosavingPost();

                // Only trigger on actual saves, not autosaves.
                if (wasSaving && !isSaving && !isAutosaving) {
                    // Post just finished saving.
                    var didSaveSucceed = !editor.didPostSaveRequestFail();
                    if (didSaveSucceed) {
                        setTimeout(function () {
                            rescanAfterSave();
                        }, 500);
                    }
                }

                wasSaving = isSaving && !isAutosaving;
            });
        } else {
            // Classic Editor: hook into form submit.
            var postForm = document.getElementById('post');
            if (postForm) {
                postForm.addEventListener('submit', function () {
                    // Set a flag so we rescan on next load.
                    try {
                        sessionStorage.setItem('scalyn_qa_rescan_' + postId, '1');
                    } catch (e) {
                        // sessionStorage not available.
                    }
                });

                // Check if we need to rescan after page reload.
                try {
                    var rescanFlag = sessionStorage.getItem('scalyn_qa_rescan_' + postId);
                    if (rescanFlag) {
                        sessionStorage.removeItem('scalyn_qa_rescan_' + postId);
                        setTimeout(function () {
                            rescanAfterSave();
                        }, 1000);
                    }
                } catch (e) {
                    // sessionStorage not available.
                }
            }
        }
    }

    /**
     * Perform a quiet rescan after saving.
     */
    function rescanAfterSave() {
        if (!postId) return;

        fetchApi('scan/' + postId, { method: 'POST' })
            .then(function (response) {
                if (response.success && response.data) {
                    renderMetaboxContent(response.data);
                }
            })
            .catch(function (err) {
                console.error('Scalyn QA: Auto-rescan failed.', err);
            });
    }

    // -------------------------------------------------------------------------
    // Initialization
    // -------------------------------------------------------------------------

    /**
     * Initialize the metabox JS.
     */
    function init() {
        // Get the current post ID.
        postId = scalynQA.currentPostId || null;
        if (!postId) {
            // Try to extract from URL.
            var urlParams = new URLSearchParams(window.location.search);
            var urlPostId = urlParams.get('post');
            if (urlPostId) {
                postId = parseInt(urlPostId, 10);
            }
        }

        if (!postId) return;

        // Load current scan results.
        loadScanResults();

        // Bind event handlers.
        initRescan();
        initGenerateAiMeta();
        initApplyAiMeta();
        initCopyMeta();
        initToggleChecks();
        initAutoRescan();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();

/**
 * Admin Audit JS.
 *
 * Handles both the audit list page (filtering, rescanning, batch scans)
 * and the single audit page (rescan, notes, snapshots, AI meta, ignore rules,
 * quick fixes).
 *
 * @package Scalyn\QA\Assets
 * @since   1.0.0
 */

'use strict';

(function () {

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Get post_id from URL query parameter.
     * @returns {string|null}
     */
    function getPostIdFromUrl() {
        var params = new URLSearchParams(window.location.search);
        return params.get('post_id') || null;
    }

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
     * Calculate the traffic-light status for a given score.
     *
     * @param {number} score - Score (0-100).
     * @returns {string} 'green', 'yellow', or 'red'.
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
     * Convert an ISO date string to a human-readable "X minutes ago" format.
     *
     * @param {string} dateString - ISO 8601 date string.
     * @returns {string}
     */
    function formatTimeAgo(dateString) {
        if (!dateString) return 'Never';

        var now = new Date();
        var date = new Date(dateString);
        var seconds = Math.floor((now - date) / 1000);

        if (seconds < 0) return 'Just now';

        var intervals = [
            { label: 'year', seconds: 31536000 },
            { label: 'month', seconds: 2592000 },
            { label: 'week', seconds: 604800 },
            { label: 'day', seconds: 86400 },
            { label: 'hour', seconds: 3600 },
            { label: 'minute', seconds: 60 },
        ];

        for (var i = 0; i < intervals.length; i++) {
            var interval = intervals[i];
            var count = Math.floor(seconds / interval.seconds);
            if (count >= 1) {
                return count + ' ' + interval.label + (count > 1 ? 's' : '') + ' ago';
            }
        }

        return 'Just now';
    }

    /**
     * Escape HTML special characters in a string.
     *
     * @param {string} str - The string to escape.
     * @returns {string}
     */
    function escapeHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    // -------------------------------------------------------------------------
    // Render Helpers
    // -------------------------------------------------------------------------

    /**
     * Render a check item row as an HTML string.
     *
     * @param {Object} item - Check item data.
     * @returns {string} HTML string.
     */
    function renderCheckItem(item) {
        var statusIcons = {
            pass: 'dashicons-yes-alt',
            warning: 'dashicons-warning',
            fail: 'dashicons-dismiss',
        };
        var iconClass = statusIcons[item.status] || 'dashicons-marker';
        var severity = item.severity || 'info';
        var quickFix = item.quick_fix || '';
        var tooltip = item.tooltip || '';
        var postId = scalynQA.currentPostId || 0;

        var html = '<div class="scalyn-check-item scalyn-check-item--' + escapeHtml(item.status) + ' scalyn-check-item--' + escapeHtml(severity) + '"'
            + ' data-check-id="' + escapeHtml(item.id) + '"'
            + ' data-status="' + escapeHtml(item.status) + '"'
            + ' data-severity="' + escapeHtml(severity) + '">';
        html += '<span class="scalyn-check-icon" aria-hidden="true">';
        html += '<span class="dashicons ' + escapeHtml(iconClass) + '"></span>';
        html += '</span>';
        html += '<div class="scalyn-check-content">';
        html += '<strong class="scalyn-check-label">' + escapeHtml(item.label) + '</strong>';
        if (item.message) {
            html += ' <span class="scalyn-check-message">' + escapeHtml(item.message) + '</span>';
        }
        html += '</div>';
        html += '<div class="scalyn-check-actions">';

        if (quickFix) {
            html += '<button type="button" class="scalyn-btn scalyn-btn--small scalyn-btn--secondary scalyn-quick-fix"'
                + ' data-action="' + escapeHtml(quickFix) + '"'
                + ' data-post-id="' + postId + '">Fix</button>';
        }

        if (tooltip) {
            html += '<span class="scalyn-tooltip" tabindex="0" role="button" aria-label="More information">';
            html += '<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>';
            html += '<span class="scalyn-tooltip__content">' + escapeHtml(tooltip) + '</span>';
            html += '</span>';
        }

        html += '<button type="button" class="scalyn-btn scalyn-btn--small scalyn-btn--ghost scalyn-ignore-check"'
            + ' data-check-id="' + escapeHtml(item.id) + '"'
            + ' data-post-id="' + postId + '"'
            + ' title="Ignore this check">';
        html += '<span class="dashicons dashicons-hidden" aria-hidden="true"></span>';
        html += '</button>';
        html += '</div>';

        // Render expandable details list if available.
        html += renderCheckDetails(item);

        html += '</div>';

        return html;
    }

    /**
     * Extract a displayable array from a check item's details object.
     *
     * Returns the first array-valued property found in details.
     *
     * @param {Object} item - Check item data.
     * @returns {string} HTML string (empty if no details list).
     */
    function renderCheckDetails(item) {
        if (!item.details || typeof item.details !== 'object') {
            return '';
        }

        // Find the first array property in details.
        var listItems = null;
        var keys = Object.keys(item.details);
        for (var i = 0; i < keys.length; i++) {
            var val = item.details[keys[i]];
            if (Array.isArray(val) && val.length > 0) {
                listItems = val;
                break;
            }
        }

        if (!listItems || listItems.length === 0) {
            return '';
        }

        var uid = 'details-' + escapeHtml(item.id) + '-' + Math.random().toString(36).substr(2, 6);

        var html = '<div class="scalyn-check-details">';
        html += '<button type="button" class="scalyn-check-details__toggle" data-target="' + uid + '">';
        html += '<span class="dashicons dashicons-arrow-right-alt2"></span> ';
        html += 'Show ' + listItems.length + ' affected item' + (listItems.length !== 1 ? 's' : '');
        html += '</button>';
        html += '<ul class="scalyn-check-details__list" id="' + uid + '" style="display:none;">';

        for (var j = 0; j < listItems.length; j++) {
            html += '<li>' + escapeHtml(String(listItems[j])) + '</li>';
        }

        html += '</ul></div>';

        return html;
    }

    /**
     * Render AI suggestion results panel.
     *
     * @param {Object} data - AI result data with title, description, provider, model.
     * @returns {string} HTML string.
     */
    function renderAiResults(data) {
        var postId = scalynQA.currentPostId || 0;

        var html = '<div class="scalyn-ai-results">';
        html += '<h4 class="scalyn-ai-results__heading">AI Suggestions</h4>';
        html += '<p class="scalyn-ai-results__meta">Generated by ' + escapeHtml(data.provider) + ' (' + escapeHtml(data.model) + ')</p>';

        if (data.title) {
            html += '<div class="scalyn-ai-field">';
            html += '<label class="scalyn-ai-field__label">Meta Title</label>';
            html += '<div class="scalyn-ai-field__value" data-field="title">' + escapeHtml(data.title) + '</div>';
            html += '<div class="scalyn-ai-field__actions">';
            html += '<button type="button" class="scalyn-btn scalyn-btn--small scalyn-copy-meta" data-field="title">Copy</button>';
            html += '<button type="button" class="scalyn-btn scalyn-btn--small scalyn-btn--secondary scalyn-edit-ai-meta" data-field="title">Edit</button>';
            html += '</div></div>';
        }

        if (data.description) {
            html += '<div class="scalyn-ai-field">';
            html += '<label class="scalyn-ai-field__label">Meta Description</label>';
            html += '<div class="scalyn-ai-field__value" data-field="description">' + escapeHtml(data.description) + '</div>';
            html += '<div class="scalyn-ai-field__actions">';
            html += '<button type="button" class="scalyn-btn scalyn-btn--small scalyn-copy-meta" data-field="description">Copy</button>';
            html += '<button type="button" class="scalyn-btn scalyn-btn--small scalyn-btn--secondary scalyn-edit-ai-meta" data-field="description">Edit</button>';
            html += '</div></div>';
        }

        html += '<div class="scalyn-ai-actions">';
        html += '<button type="button" class="scalyn-btn scalyn-apply-ai-meta" data-post-id="' + postId + '">Apply to SEO Plugin</button>';
        html += '<button type="button" class="scalyn-btn scalyn-btn--secondary scalyn-regenerate-ai" data-post-id="' + postId + '">Regenerate</button>';
        html += '</div></div>';

        return html;
    }

    /**
     * Render a note as HTML.
     *
     * @param {Object} note  - Note data with content, author, created_at.
     * @param {number} index - Index of the note in the list.
     * @returns {string} HTML string.
     */
    function renderNote(note, index) {
        var postId = scalynQA.currentPostId || 0;

        var html = '<div class="scalyn-note" data-index="' + index + '">';
        html += '<div class="scalyn-note__content">' + escapeHtml(note.content) + '</div>';
        html += '<div class="scalyn-note__meta">';
        html += '<span class="scalyn-note__author">' + escapeHtml(note.author) + '</span>';
        html += '<span class="scalyn-note__date">' + formatTimeAgo(note.created_at) + '</span>';
        html += '</div>';
        html += '<button type="button" class="scalyn-btn scalyn-btn--small scalyn-btn--ghost scalyn-delete-note"'
            + ' data-post-id="' + postId + '"'
            + ' data-index="' + index + '"'
            + ' title="Delete note">';
        html += '<span class="dashicons dashicons-trash" aria-hidden="true"></span>';
        html += '</button>';
        html += '</div>';

        return html;
    }

    /**
     * Render a snapshot entry as HTML.
     *
     * @param {Object} snapshot - Snapshot data.
     * @returns {string} HTML string.
     */
    function renderSnapshot(snapshot) {
        var scores = snapshot.scores || {};
        var status = getStatus(scores.overall || 0);

        var html = '<div class="scalyn-snapshot">';
        html += '<div class="scalyn-snapshot__score">';
        html += '<span class="scalyn-badge scalyn-badge--' + status + '">' + (scores.overall || 0) + '</span>';
        html += '</div>';
        html += '<div class="scalyn-snapshot__details">';
        html += '<span class="scalyn-snapshot__date">' + formatTimeAgo(snapshot.created_at) + '</span>';
        html += '<span class="scalyn-snapshot__breakdown">SEO: ' + (scores.seo || 0) + ' | Content: ' + (scores.content || 0) + ' | Func: ' + (scores.functionality || 0) + '</span>';
        html += '</div></div>';

        return html;
    }

    // -------------------------------------------------------------------------
    // List Page Functionality
    // -------------------------------------------------------------------------

    /**
     * Initialize the audit list page.
     */
    function initListPage() {
        initStatusFilter();
        initTypeFilter();
        initRescanButtons();
        initScanAll();
        initSelectiveScanning();
    }

    /**
     * Initialize client-side status filtering.
     */
    function initStatusFilter() {
        var filterSelect = document.getElementById('scalyn-filter-status');
        if (!filterSelect) return;

        filterSelect.addEventListener('change', function () {
            var params = new URLSearchParams(window.location.search);

            if (this.value) {
                params.set('status', this.value);
            } else {
                params.delete('status');
            }

            params.delete('paged');
            window.location.search = params.toString();
        });
    }

    /**
     * Initialize post type filtering — navigates with query param.
     */
    function initTypeFilter() {
        var filterSelect = document.getElementById('scalyn-filter-type');
        if (!filterSelect) return;

        filterSelect.addEventListener('change', function () {
            var params = new URLSearchParams(window.location.search);

            if (this.value) {
                params.set('filter_type', this.value);
            } else {
                params.delete('filter_type');
            }

            params.delete('paged');
            window.location.search = params.toString();
        });
    }

    /**
     * Initialize individual rescan buttons on the list page.
     */
    function initRescanButtons() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.scalyn-rescan') || e.target.closest('#scalyn-rescan');
            if (!btn) return;

            var postId = btn.getAttribute('data-post-id') || getPostIdFromUrl();
            if (!postId) return;

            btn.disabled = true;
            btn.textContent = 'Scanning...';

            fetchApi('scan/' + postId, { method: 'POST' })
                .then(function (response) {
                    if (response.success) {
                        if (typeof ScalynAlert !== 'undefined') {
                            ScalynAlert.toast('Scan complete');
                        }
                        window.location.reload();
                    }
                })
                .catch(function (err) {
                    console.error('Scalyn QA: Rescan failed.', err);
                    if (typeof ScalynAlert !== 'undefined') {
                        ScalynAlert.error('Scan Failed', err.message || 'An error occurred while scanning.');
                    }
                })
                .finally(function () {
                    btn.disabled = false;
                    btn.textContent = 'Rescan';
                });
        });
    }

    /**
     * Update a table row with new scan data.
     *
     * @param {string|number} postId - The post ID.
     * @param {Object}        data   - Scan result data.
     */
    function updateTableRow(postId, data) {
        var row = document.querySelector('tr[data-post-id="' + postId + '"]');
        if (!row) return;

        var scores = data.scores || {};
        var seoScore = scores.seo || 0;
        var contentScore = scores.content || 0;
        var funcScore = scores.functionality || 0;
        var overallScore = scores.overall || 0;

        var cells = row.querySelectorAll('td');
        if (cells.length < 9) return;

        // Update SEO score (index 4, after checkbox + row number).
        cells[4].innerHTML = '<span class="scalyn-badge scalyn-badge--' + getStatus(seoScore) + '">' + seoScore + '</span>';
        // Update Content score (index 5).
        cells[5].innerHTML = '<span class="scalyn-badge scalyn-badge--' + getStatus(contentScore) + '">' + contentScore + '</span>';
        // Update Func score (index 6).
        cells[6].innerHTML = '<span class="scalyn-badge scalyn-badge--' + getStatus(funcScore) + '">' + funcScore + '</span>';
        // Update Overall score (index 7).
        cells[7].innerHTML = '<span class="scalyn-badge scalyn-badge--' + getStatus(overallScore) + '">' + overallScore + '</span>';
        // Update Last Scan (index 8).
        cells[8].innerHTML = '<span>' + formatTimeAgo(data.scanned_at) + '</span>';
    }

    /**
     * Initialize "Scan All" button on the list page.
     */
    function initScanAll() {
        var scanAllBtn = document.getElementById('scalyn-scan-all');
        if (!scanAllBtn) return;

        scanAllBtn.addEventListener('click', function () {
            if (typeof ScalynAlert === 'undefined') return;

            ScalynAlert.confirm(
                'Scan All Pages',
                'This will scan all visible pages for QA issues. This may take a moment.',
                'Start Scan'
            ).then(function (result) {
                if (!result.isConfirmed) return;
                runListBatchScan();
            });
        });
    }

    /**
     * Run batch scan for all posts visible in the table.
     */
    function runListBatchScan() {
        var rows = document.querySelectorAll('.scalyn-table tbody tr[data-post-id]');
        var postIds = [];

        rows.forEach(function (row) {
            var id = parseInt(row.getAttribute('data-post-id'), 10);
            if (id > 0) postIds.push(id);
        });

        if (postIds.length === 0) {
            if (typeof ScalynAlert !== 'undefined') {
                ScalynAlert.warning('No Pages', 'No pages found to scan.');
            }
            return;
        }

        // Show progress bar.
        var progressContainer = document.getElementById('scalyn-scan-progress');
        var progressBar = progressContainer ? progressContainer.querySelector('.scalyn-progress__bar') : null;
        var countEl = document.getElementById('scalyn-scan-count');
        var totalEl = document.getElementById('scalyn-scan-total');
        var percentEl = document.getElementById('scalyn-scan-percent');

        var completed = 0;
        var total = postIds.length;

        if (progressContainer) {
            progressContainer.style.display = '';
            if (totalEl) totalEl.textContent = total;
            if (countEl) countEl.textContent = '0';
            if (percentEl) percentEl.textContent = '0';
            if (progressBar) progressBar.style.width = '0%';
        }

        function updateProgress() {
            var percent = Math.round((completed / total) * 100);
            if (progressBar) progressBar.style.width = percent + '%';
            if (countEl) countEl.textContent = completed;
            if (percentEl) percentEl.textContent = percent;
        }

        // Scan one page at a time for visible progress.
        function scanNext(index) {
            if (index >= postIds.length) {
                // Show 100% briefly before the success alert.
                updateProgress();
                setTimeout(function () {
                    if (progressContainer) progressContainer.style.display = 'none';
                    if (typeof ScalynAlert !== 'undefined') {
                        ScalynAlert.success('Scan Complete', 'Successfully scanned ' + total + ' page(s).');
                    }
                }, 500);
                return;
            }

            fetchApi('scan/' + postIds[index], {
                method: 'POST',
            })
                .then(function (response) {
                    if (response.success && response.data) {
                        updateTableRow(postIds[index], response.data);
                    }
                    completed++;
                    updateProgress();
                    scanNext(index + 1);
                })
                .catch(function (err) {
                    console.error('Scalyn QA: Scan error for post ' + postIds[index], err);
                    completed++;
                    updateProgress();
                    scanNext(index + 1);
                });
        }

        scanNext(0);
    }

    /**
     * Initialize selective scanning — checkboxes + "Scan Selected" button.
     */
    function initSelectiveScanning() {
        var selectAll   = document.getElementById('scalyn-select-all');
        var scanSelBtn  = document.getElementById('scalyn-scan-selected');
        var countEl     = document.getElementById('scalyn-selected-count');

        if (!scanSelBtn) return;

        function getChecked() {
            return document.querySelectorAll('.scalyn-select-page:checked');
        }

        function updateSelectedCount() {
            var checked = getChecked();
            var count   = checked.length;
            if (countEl) countEl.textContent = count;
            scanSelBtn.style.display = count > 0 ? '' : 'none';
        }

        // Individual checkboxes.
        document.addEventListener('change', function (e) {
            if (!e.target.classList.contains('scalyn-select-page')) return;
            updateSelectedCount();
            // Sync "select all" state.
            if (selectAll) {
                var total   = document.querySelectorAll('.scalyn-select-page').length;
                var checked = getChecked().length;
                selectAll.checked       = checked === total;
                selectAll.indeterminate = checked > 0 && checked < total;
            }
        });

        // Select-all checkbox.
        if (selectAll) {
            selectAll.addEventListener('change', function () {
                var boxes = document.querySelectorAll('.scalyn-select-page');
                boxes.forEach(function (cb) { cb.checked = selectAll.checked; });
                updateSelectedCount();
            });
        }

        // Scan Selected button.
        scanSelBtn.addEventListener('click', function () {
            var checked = getChecked();
            var postIds = [];
            checked.forEach(function (cb) {
                var id = parseInt(cb.value, 10);
                if (id > 0) postIds.push(id);
            });

            if (postIds.length === 0) return;

            if (typeof ScalynAlert !== 'undefined') {
                ScalynAlert.confirm(
                    'Scan Selected Pages',
                    'This will scan ' + postIds.length + ' selected page(s). Continue?',
                    'Start Scan'
                ).then(function (result) {
                    if (!result.isConfirmed) return;
                    runSelectedBatchScan(postIds);
                });
            } else {
                runSelectedBatchScan(postIds);
            }
        });
    }

    /**
     * Run batch scan for a given array of post IDs (selective scan).
     */
    function runSelectedBatchScan(postIds) {
        var progressContainer = document.getElementById('scalyn-scan-progress');
        var progressBar       = progressContainer ? progressContainer.querySelector('.scalyn-progress__bar') : null;
        var countEl           = document.getElementById('scalyn-scan-count');
        var totalEl           = document.getElementById('scalyn-scan-total');
        var percentEl         = document.getElementById('scalyn-scan-percent');

        var completed = 0;
        var total     = postIds.length;

        if (progressContainer) {
            progressContainer.style.display = '';
            if (totalEl) totalEl.textContent = total;
            if (countEl) countEl.textContent = '0';
            if (percentEl) percentEl.textContent = '0';
            if (progressBar) progressBar.style.width = '0%';
        }

        function updateProgress() {
            var percent = Math.round((completed / total) * 100);
            if (progressBar) progressBar.style.width = percent + '%';
            if (countEl) countEl.textContent = completed;
            if (percentEl) percentEl.textContent = percent;
        }

        function scanNext(index) {
            if (index >= postIds.length) {
                updateProgress();
                setTimeout(function () {
                    if (progressContainer) progressContainer.style.display = 'none';
                    if (typeof ScalynAlert !== 'undefined') {
                        ScalynAlert.success('Scan Complete', 'Successfully scanned ' + total + ' page(s).');
                    }
                }, 500);
                return;
            }

            fetchApi('scan/' + postIds[index], {
                method: 'POST',
            })
                .then(function (response) {
                    if (response.success && response.data) {
                        updateTableRow(postIds[index], response.data);
                    }
                    completed++;
                    updateProgress();
                    scanNext(index + 1);
                })
                .catch(function (err) {
                    console.error('Scalyn QA: Scan error for post ' + postIds[index], err);
                    completed++;
                    updateProgress();
                    scanNext(index + 1);
                });
        }

        scanNext(0);
    }

    // -------------------------------------------------------------------------
    // Single Audit Page Functionality
    // -------------------------------------------------------------------------

    /**
     * Initialize single audit page.
     */
    /**
     * Display AI-generated keyword suggestions in the inline panel.
     */
    function displayKeywordResults(data) {
        var container = document.querySelector('.scalyn-ai-keyword-results[data-check-id="focus_keyword"]');

        // If container doesn't exist, create it after the focus_keyword check item.
        if (!container) {
            var checkItem = document.querySelector('.scalyn-check-item[data-check-id="focus_keyword"]');
            if (!checkItem) return;
            container = document.createElement('div');
            container.className = 'scalyn-ai-keyword-results';
            container.setAttribute('data-check-id', 'focus_keyword');
            container.setAttribute('data-post-id', scalynQA.currentPostId || '0');
            // Insert after the check-item (as sibling, same as meta title/description panels).
            checkItem.parentNode.insertBefore(container, checkItem.nextSibling);
        }

        var postId = container.getAttribute('data-post-id') || scalynQA.currentPostId || 0;
        var hasPro   = data.has_pro;
        var provider = data.provider || '';
        var model    = data.model || '';
        var meta     = provider ? provider + (model ? ' / ' + model : '') : '';

        var html = '<div class="scalyn-ai-inline-result">';
        html += '<div class="scalyn-ai-inline-result__content">';
        html += '<span class="scalyn-ai-inline-result__label">AI Suggestion:' + (meta ? ' ' + escapeHtml(meta) : '') + '</span>';

        if (hasPro && data.primary) {
            // Pro mode: checkboxes — primary pre-checked + secondary.
            html += '<label style="display:block;padding:6px 12px;margin:4px 0;border:1px solid var(--scalyn-success);border-radius:6px;font-size:0.8125rem;background:var(--scalyn-success-light);">';
            html += '<input type="checkbox" name="scalyn-ai-keyword[]" value="' + escapeHtml(data.primary) + '" checked style="margin-right:8px;">';
            html += escapeHtml(data.primary) + ' <span style="color:var(--scalyn-success);font-size:0.6875rem;">(primary)</span>';
            html += '</label>';

            var secondary = data.secondary || [];
            for (var i = 0; i < secondary.length; i++) {
                html += '<label style="display:block;padding:6px 12px;margin:4px 0;border:1px solid var(--scalyn-border-light);border-radius:6px;cursor:pointer;font-size:0.8125rem;">';
                html += '<input type="checkbox" name="scalyn-ai-keyword[]" value="' + escapeHtml(secondary[i]) + '" style="margin-right:8px;">';
                html += escapeHtml(secondary[i]) + ' <span style="color:var(--scalyn-text-muted);font-size:0.6875rem;">(secondary)</span>';
                html += '</label>';
            }
        } else {
            // Free mode: radio buttons — pick 1 of 3.
            var keywords = (data.keywords && data.keywords.length) ? data.keywords : [];
            if (!keywords.length && data.primary) {
                keywords = [data.primary];
            }

            for (var j = 0; j < keywords.length; j++) {
                html += '<label style="display:block;padding:6px 12px;margin:4px 0;border:1px solid var(--scalyn-border-light);border-radius:6px;cursor:pointer;font-size:0.8125rem;">';
                html += '<input type="radio" name="scalyn-ai-keyword" value="' + escapeHtml(keywords[j]) + '" ' + (j === 0 ? 'checked' : '') + ' style="margin-right:8px;">';
                html += escapeHtml(keywords[j]);
                html += '</label>';
            }
        }

        html += '<span class="scalyn-ai-inline-result__meta">' + (hasPro ? 'PRO: Check keywords to apply (primary + secondary)' : 'Select a keyword and click Apply') + '</span>';
        html += '</div>';

        // Actions — Copy first, then Apply (same order as meta title/description).
        html += '<div class="scalyn-ai-inline-result__actions">';
        html += '<button type="button" class="scalyn-btn scalyn-btn--small scalyn-keyword-copy" title="Copy">';
        html += '<span class="dashicons dashicons-clipboard" aria-hidden="true"></span> Copy</button>';
        html += '<button type="button" class="scalyn-btn scalyn-btn--small scalyn-btn--secondary scalyn-keyword-apply-selected" data-post-id="' + postId + '" data-is-pro="' + (hasPro ? '1' : '0') + '" title="Apply">';
        html += '<span class="dashicons dashicons-yes" aria-hidden="true"></span> Apply</button>';
        html += '</div></div>';

        container.innerHTML = html;
        container.style.display = '';
    }

    /**
     * Initialize keyword AI actions — Apply Selected, Apply All, Copy.
     */
    function initKeywordAiActions() {
        // Apply keywords — handles both radio (free) and checkbox (pro) modes.
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.scalyn-keyword-apply-selected');
            if (!btn) return;

            var postId = btn.getAttribute('data-post-id');
            var isPro  = btn.getAttribute('data-is-pro') === '1';
            if (!postId) return;

            var primary = '';
            var secondary = [];

            if (isPro) {
                // Checkbox mode: collect all checked values. First checked = primary, rest = secondary.
                var checked = document.querySelectorAll('input[name="scalyn-ai-keyword[]"]:checked');
                if (!checked.length) {
                    ScalynAlert && ScalynAlert.error('Error', 'Select at least one keyword.');
                    return;
                }
                checked.forEach(function (cb, i) {
                    if (i === 0) {
                        primary = cb.value;
                    } else {
                        secondary.push(cb.value);
                    }
                });
            } else {
                // Radio mode: single selected value.
                var selected = document.querySelector('input[name="scalyn-ai-keyword"]:checked');
                if (!selected) return;
                primary = selected.value;
            }

            if (!primary) return;

            btn.disabled = true;
            btn.textContent = 'Applying...';

            fetchApi('ai/apply-keyword/' + postId, {
                method: 'POST',
                body: JSON.stringify({ primary: primary, secondary: secondary }),
            }).then(function (res) {
                if (res.success) {
                    var count = 1 + secondary.length;
                    ScalynAlert && ScalynAlert.toast(count + ' keyword(s) applied — rescanning...');
                    return fetchApi('scan/' + postId, { method: 'POST' });
                }
            }).then(function () {
                window.location.reload();
            }).catch(function (err) {
                btn.disabled = false;
                btn.textContent = 'Apply';
                ScalynAlert && ScalynAlert.error('Error', err.message || 'Failed to apply keyword.');
            });
        });

        // Copy keyword(s) — handles radio (free) and checkbox (pro).
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.scalyn-keyword-copy');
            if (!btn) return;

            // Try checkboxes first (pro), then radio (free).
            var checked = document.querySelectorAll('input[name="scalyn-ai-keyword[]"]:checked');
            var text = '';
            if (checked.length) {
                var values = [];
                checked.forEach(function (cb) { values.push(cb.value); });
                text = values.join(', ');
            } else {
                var radio = document.querySelector('input[name="scalyn-ai-keyword"]:checked');
                text = radio ? radio.value : '';
            }

            if (text && navigator.clipboard) {
                navigator.clipboard.writeText(text).then(function () {
                    ScalynAlert && ScalynAlert.toast('Copied to clipboard');
                });
            }
        });

        // Standalone "Generate with AI" / "Regenerate with AI" keyword button.
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.scalyn-quick-fix[data-action="generate-ai-keyword"]');
            if (!btn) return;

            var postId = btn.getAttribute('data-post-id') || getPostIdFromUrl() || scalynQA.currentPostId;
            if (!postId) return;

            btn.disabled = true;
            var origHtml = btn.innerHTML;
            btn.innerHTML = '<span class="dashicons dashicons-update spin"></span> Generating...';

            fetchApi('ai/generate-keywords/' + postId, { method: 'POST' })
                .then(function (response) {
                    btn.disabled = false;
                    btn.innerHTML = origHtml;
                    if (response.success && response.data) {
                        displayKeywordResults(response.data);
                    } else {
                        ScalynAlert && ScalynAlert.error('AI Failed', 'No keywords generated.');
                    }
                })
                .catch(function (err) {
                    btn.disabled = false;
                    btn.innerHTML = origHtml;
                    ScalynAlert && ScalynAlert.error('Error', err.message || 'Keyword generation failed.');
                });
        });
    }

    function initSinglePage() {
        initSingleRescan();
        initAddNote();
        initDeleteNote();
        initCreateSnapshot();
        initInlineAiActions();
        initAiAltText();
        initGenerateAllAi();
        initAiContentReview();
        initIgnoreCheck();
        initRemoveIgnore();
        initQuickFixes();
        initFeaturedImageInline();
        initKeywordAiActions();
    }

    /**
     * Handle rescan button on single audit page.
     */
    function initSingleRescan() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('#scalyn-rescan, #scalyn-rescan-btn, .scalyn-rescan-single, .scalyn-rescan');
            if (!btn) return;

            var postId = btn.getAttribute('data-post-id') || getPostIdFromUrl() || scalynQA.currentPostId;
            if (!postId) return;

            btn.disabled = true;

            if (typeof ScalynAlert !== 'undefined') {
                ScalynAlert.loading('Scanning page...');
            }

            fetchApi('scan/' + postId, { method: 'POST' })
                .then(function (response) {
                    if (response.success) {
                        ScalynAlert && ScalynAlert.toast('Scan complete');
                        window.location.reload();
                    }
                })
                .catch(function (err) {
                    if (typeof ScalynAlert !== 'undefined') {
                        ScalynAlert.close();
                        ScalynAlert.error('Scan Failed', err.message || 'An error occurred.');
                    }
                })
                .finally(function () {
                    btn.disabled = false;
                });
        });
    }

    /**
     * Update the single audit page with new scan results.
     *
     * @param {Object} data - Scan result data.
     */
    function updateSinglePageResults(data) {
        // Update score display if present.
        var scoreCircle = document.querySelector('.scalyn-score-circle');
        if (scoreCircle && data.scores) {
            var score = data.scores.overall || 0;
            var status = data.scores.status || getStatus(score);
            var valueEl = scoreCircle.querySelector('.scalyn-score-circle__value');
            if (valueEl) valueEl.textContent = score;

            scoreCircle.classList.remove('scalyn-score-circle--green', 'scalyn-score-circle--yellow', 'scalyn-score-circle--red');
            scoreCircle.classList.add('scalyn-score-circle--' + status);
            scoreCircle.style.setProperty('--score-percent', score + '%');
        }

        // Update check items if the results container exists.
        var resultsContainer = document.querySelector('.scalyn-check-results, .scalyn-audit-checks');
        if (resultsContainer && data.results) {
            var html = '';
            var categories = Object.keys(data.results);
            categories.forEach(function (category) {
                var items = data.results[category];
                if (Array.isArray(items)) {
                    html += '<div class="scalyn-check-category">';
                    html += '<h3 class="scalyn-check-category__title">' + escapeHtml(category) + '</h3>';
                    items.forEach(function (item) {
                        html += renderCheckItem(item);
                    });
                    html += '</div>';
                }
            });
            resultsContainer.innerHTML = html;
        }
    }

    /**
     * Handle "Add Note" button.
     */
    function initAddNote() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.scalyn-add-note') || e.target.closest('#scalyn-add-note');
            if (!btn) return;

            var postId = btn.getAttribute('data-post-id') || getPostIdFromUrl() || scalynQA.currentPostId;
            if (!postId) return;

            if (typeof Swal === 'undefined') return;

            Swal.fire({
                title: 'Add Note',
                input: 'textarea',
                inputPlaceholder: 'Enter your note...',
                showCancelButton: true,
                confirmButtonText: 'Save Note',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#4a90d9',
                inputValidator: function (value) {
                    if (!value || !value.trim()) {
                        return 'Note content cannot be empty.';
                    }
                },
                customClass: { popup: 'scalyn-swal-popup' },
            }).then(function (result) {
                if (!result.isConfirmed || !result.value) return;

                fetchApi('notes/' + postId, {
                    method: 'POST',
                    body: JSON.stringify({ content: result.value.trim() }),
                })
                    .then(function (response) {
                        if (response.success) {
                            ScalynAlert && ScalynAlert.toast('Note added');
                            window.location.reload();
                        }
                    })
                    .catch(function (err) {
                        ScalynAlert && ScalynAlert.error('Error', err.message || 'Failed to add note.');
                    });
            });
        });
    }

    /**
     * Handle "Delete Note" button.
     */
    function initDeleteNote() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.scalyn-delete-note');
            if (!btn) return;

            var postId = btn.getAttribute('data-post-id') || scalynQA.currentPostId;
            var index = btn.getAttribute('data-index');
            if (!postId || index === null) return;

            if (typeof ScalynAlert === 'undefined') return;

            ScalynAlert.confirm(
                'Delete Note',
                'Are you sure you want to delete this note?',
                'Delete'
            ).then(function (result) {
                if (!result.isConfirmed) return;

                fetchApi('notes/' + postId + '/' + index, { method: 'DELETE' })
                    .then(function (response) {
                        if (response.success) {
                            ScalynAlert.toast('Note deleted');
                            window.location.reload();
                        }
                    })
                    .catch(function (err) {
                        ScalynAlert.error('Error', err.message || 'Failed to delete note.');
                    });
            });
        });
    }

    /**
     * Update the notes section with new notes data.
     *
     * @param {Array} notes - Array of note objects.
     */
    function updateNotesSection(notes) {
        var container = document.querySelector('.scalyn-notes-list');
        if (!container) return;

        if (!notes || notes.length === 0) {
            container.innerHTML = '<p class="scalyn-empty">No notes yet.</p>';
            return;
        }

        var html = '';
        notes.forEach(function (note, index) {
            html += renderNote(note, index);
        });
        container.innerHTML = html;
    }

    /**
     * Handle "Create Snapshot" button.
     */
    function initCreateSnapshot() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.scalyn-create-snapshot') || e.target.closest('#scalyn-create-snapshot');
            if (!btn) return;

            var postId = btn.getAttribute('data-post-id') || getPostIdFromUrl() || scalynQA.currentPostId;
            if (!postId) return;

            btn.disabled = true;

            fetchApi('snapshots/' + postId, { method: 'POST' })
                .then(function (response) {
                    if (response.success) {
                        ScalynAlert && ScalynAlert.toast('Snapshot saved');
                        window.location.reload();
                    }
                })
                .catch(function (err) {
                    ScalynAlert && ScalynAlert.error('Error', err.message || 'Failed to create snapshot.');
                    btn.disabled = false;
                });
        });
    }

    /**
     * Append a new snapshot to the snapshots list.
     *
     * @param {Object} snapshot - Snapshot data.
     */
    function appendSnapshot(snapshot) {
        var container = document.querySelector('.scalyn-snapshots-list');
        if (!container) return;

        // Remove empty message if present.
        var emptyMsg = container.querySelector('.scalyn-empty');
        if (emptyMsg) emptyMsg.remove();

        container.insertAdjacentHTML('afterbegin', renderSnapshot(snapshot));
    }

    /**
     * Handle "Generate AI Meta" button.
     */
    function initGenerateAiMeta() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.scalyn-generate-ai-meta') || e.target.closest('#scalyn-generate-ai');
            if (!btn) return;

            var postId = btn.getAttribute('data-post-id') || getPostIdFromUrl() || scalynQA.currentPostId;
            if (!postId) return;

            btn.disabled = true;
            if (typeof ScalynAlert !== 'undefined') {
                ScalynAlert.loading('Generating AI suggestions...');
            }

            fetchApi('ai/generate/' + postId, { method: 'POST' })
                .then(function (response) {
                    if (typeof ScalynAlert !== 'undefined') {
                        ScalynAlert.close();
                    }

                    if (response.success && response.data) {
                        displayAiResults(response.data);
                    }
                })
                .catch(function (err) {
                    if (typeof ScalynAlert !== 'undefined') {
                        ScalynAlert.close();
                        ScalynAlert.error('AI Generation Failed', err.message || 'An error occurred.');
                    }
                })
                .finally(function () {
                    btn.disabled = false;
                });
        });
    }

    /**
     * Display AI results inline below the relevant check items.
     *
     * @param {Object} data - AI response data with title and description.
     */
    function displayAiResults(data) {
        // Show title result inline.
        var titlePanel = document.querySelector('.scalyn-ai-inline-result[data-check-id="meta_title_exists"]');
        if (titlePanel && data.title) {
            var titleText = titlePanel.querySelector('.scalyn-ai-inline-result__text');
            var titleMeta = titlePanel.querySelector('.scalyn-ai-inline-result__meta');
            if (titleText) titleText.textContent = data.title;
            if (titleMeta) titleMeta.textContent = data.title.length + ' characters';
            titlePanel.style.display = '';
        }

        // Show description result inline.
        var descPanel = document.querySelector('.scalyn-ai-inline-result[data-check-id="meta_description_exists"]');
        if (descPanel && data.description) {
            var descText = descPanel.querySelector('.scalyn-ai-inline-result__text');
            var descMeta = descPanel.querySelector('.scalyn-ai-inline-result__meta');
            if (descText) descText.textContent = data.description;
            if (descMeta) descMeta.textContent = data.description.length + ' characters';
            descPanel.style.display = '';
        }
    }

    /**
     * Handle "Copy to Clipboard" buttons for AI meta.
     */
    function initCopyMeta() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.scalyn-copy-meta');
            if (!btn) return;

            var field = btn.getAttribute('data-field');
            var valueEl = btn.closest('.scalyn-ai-field').querySelector('.scalyn-ai-field__value');
            if (!valueEl) return;

            var text = valueEl.textContent.trim();

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function () {
                    if (typeof ScalynAlert !== 'undefined') {
                        ScalynAlert.toast('Copied to clipboard');
                    }
                }).catch(function () {
                    fallbackCopy(text);
                });
            } else {
                fallbackCopy(text);
            }
        });
    }

    /**
     * Fallback clipboard copy using textarea.
     *
     * @param {string} text - Text to copy.
     */
    function fallbackCopy(text) {
        var textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        try {
            document.execCommand('copy');
            if (typeof ScalynAlert !== 'undefined') {
                ScalynAlert.toast('Copied to clipboard');
            }
        } catch (err) {
            if (typeof ScalynAlert !== 'undefined') {
                ScalynAlert.error('Copy Failed', 'Unable to copy to clipboard.');
            }
        }
        document.body.removeChild(textarea);
    }

    /**
     * Handle "Apply to SEO Plugin" button.
     */
    function initApplyAiMeta() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.scalyn-apply-ai-meta');
            if (!btn) return;

            var postId = btn.getAttribute('data-post-id') || scalynQA.currentPostId;
            if (!postId) return;

            var resultsContainer = btn.closest('.scalyn-ai-results');
            if (!resultsContainer) return;

            var titleEl = resultsContainer.querySelector('[data-field="title"]');
            var descEl = resultsContainer.querySelector('[data-field="description"]');

            var title = titleEl ? titleEl.textContent.trim() : '';
            var description = descEl ? descEl.textContent.trim() : '';

            if (!title && !description) {
                if (typeof ScalynAlert !== 'undefined') {
                    ScalynAlert.warning('No Data', 'No AI-generated meta to apply.');
                }
                return;
            }

            btn.disabled = true;

            fetchApi('ai/apply/' + postId, {
                method: 'POST',
                body: JSON.stringify({ title: title, description: description }),
            })
                .then(function (response) {
                    if (response.success) {
                        if (typeof ScalynAlert !== 'undefined') {
                            ScalynAlert.toast('Meta applied to SEO plugin');
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
                });
        });
    }

    /**
     * Handle "Edit AI Meta" buttons.
     */
    function initEditAiMeta() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.scalyn-edit-ai-meta');
            if (!btn) return;

            var field = btn.getAttribute('data-field');
            var fieldContainer = btn.closest('.scalyn-ai-field');
            var valueEl = fieldContainer ? fieldContainer.querySelector('.scalyn-ai-field__value') : null;
            if (!valueEl) return;

            var currentValue = valueEl.textContent.trim();
            var fieldLabel = field === 'title' ? 'Meta Title' : 'Meta Description';

            if (typeof Swal === 'undefined') return;

            Swal.fire({
                title: 'Edit ' + fieldLabel,
                input: field === 'description' ? 'textarea' : 'text',
                inputValue: currentValue,
                showCancelButton: true,
                confirmButtonText: 'Save',
                confirmButtonColor: '#4a90d9',
                inputValidator: function (value) {
                    if (!value || !value.trim()) {
                        return fieldLabel + ' cannot be empty.';
                    }
                },
                customClass: { popup: 'scalyn-swal-popup' },
            }).then(function (result) {
                if (result.isConfirmed && result.value) {
                    valueEl.textContent = result.value.trim();
                    if (typeof ScalynAlert !== 'undefined') {
                        ScalynAlert.toast(fieldLabel + ' updated');
                    }
                }
            });
        });
    }

    /**
     * Handle "Regenerate" button.
     */
    function initRegenerateAi() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.scalyn-regenerate-ai');
            if (!btn) return;

            var postId = btn.getAttribute('data-post-id') || scalynQA.currentPostId;
            if (!postId) return;

            btn.disabled = true;
            if (typeof ScalynAlert !== 'undefined') {
                ScalynAlert.loading('Regenerating AI suggestions...');
            }

            fetchApi('ai/generate/' + postId, { method: 'POST' })
                .then(function (response) {
                    if (typeof ScalynAlert !== 'undefined') {
                        ScalynAlert.close();
                    }

                    if (response.success && response.data) {
                        displayAiResults(response.data);
                        if (typeof ScalynAlert !== 'undefined') {
                            ScalynAlert.toast('AI suggestions regenerated');
                        }
                    }
                })
                .catch(function (err) {
                    if (typeof ScalynAlert !== 'undefined') {
                        ScalynAlert.close();
                        ScalynAlert.error('Regeneration Failed', err.message || 'An error occurred.');
                    }
                })
                .finally(function () {
                    btn.disabled = false;
                });
        });
    }

    /**
     * Initialize the main "Generate with AI" button that runs meta generation + content review.
     */
    /**
     * Initialize AI alt text generation and apply.
     */
    function initAiAltText() {
        // Load saved alt text results from data attribute if available.
        var container = document.querySelector('.scalyn-ai-alt-results[data-check-id="image_alt_text"]');
        if (container && container.getAttribute('data-saved-results')) {
            try {
                var saved = JSON.parse(container.getAttribute('data-saved-results'));
                if (saved && saved.length > 0) {
                    displayAltTextResults(saved);
                }
            } catch (e) {}
        }

        // Apply single alt text button.
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.scalyn-alt-apply');
            if (!btn) return;

            var row = btn.closest('.scalyn-alt-row');
            var src = btn.getAttribute('data-src');
            var input = row ? row.querySelector('.scalyn-alt-input') : null;
            var altText = input ? input.value.trim() : btn.getAttribute('data-alt');
            var postId = btn.getAttribute('data-post-id') || getPostIdFromUrl();
            if (!src || !altText || !postId) return;

            btn.disabled = true;
            fetchApi('ai/apply-alt/' + postId, {
                method: 'POST',
                body: JSON.stringify({ src: src, alt_text: altText }),
            })
                .then(function (response) {
                    if (response.success && row) {
                        markAltRowApplied(row);
                        ScalynAlert.toast('Alt text applied');
                        var rescanBtn = document.querySelector('#scalyn-rescan') || document.querySelector('.scalyn-rescan');
                        if (rescanBtn) rescanBtn.click();
                    }
                })
                .catch(function (err) {
                    ScalynAlert.error('Apply Failed', err.message || 'Failed to apply alt text.');
                    btn.disabled = false;
                });
        });

        // Apply All alt texts button.
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.scalyn-alt-apply-all');
            if (!btn) return;

            var container = btn.closest('.scalyn-ai-alt-results');
            var rows = container ? container.querySelectorAll('.scalyn-alt-row:not(.scalyn-alt-row--applied)') : [];
            if (rows.length === 0) return;

            btn.disabled = true;
            ScalynAlert.loading('Applying all alt texts…');

            var postId = container.getAttribute('data-post-id') || getPostIdFromUrl();
            var promises = [];

            rows.forEach(function (row) {
                var src = row.getAttribute('data-src');
                var input = row.querySelector('.scalyn-alt-input');
                var altText = input ? input.value.trim() : '';
                if (!src || !altText) return;

                promises.push(
                    fetchApi('ai/apply-alt/' + postId, {
                        method: 'POST',
                        body: JSON.stringify({ src: src, alt_text: altText }),
                    }).then(function (response) {
                        if (response.success) markAltRowApplied(row);
                    })
                );
            });

            Promise.all(promises)
                .then(function () {
                    ScalynAlert.close();
                    ScalynAlert.toast('All alt texts applied');
                    var rescanBtn = document.querySelector('#scalyn-rescan') || document.querySelector('.scalyn-rescan');
                    if (rescanBtn) rescanBtn.click();
                })
                .catch(function (err) {
                    ScalynAlert.close();
                    ScalynAlert.error('Apply Failed', err.message || 'Some alt texts failed to apply.');
                })
                .finally(function () {
                    btn.disabled = false;
                });
        });

        // Copy alt text button.
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.scalyn-alt-copy');
            if (!btn) return;
            var row = btn.closest('.scalyn-alt-row');
            var input = row ? row.querySelector('.scalyn-alt-input') : null;
            var altText = input ? input.value.trim() : btn.getAttribute('data-alt');
            if (altText) {
                navigator.clipboard.writeText(altText).then(function () {
                    ScalynAlert.toast('Alt text copied');
                });
            }
        });
    }

    /**
     * Mark an alt text row as applied.
     */
    function markAltRowApplied(row) {
        row.classList.add('scalyn-alt-row--applied');
        var actions = row.querySelector('.scalyn-ai-inline-result__actions');
        if (actions) {
            actions.innerHTML = '<span style="color:var(--scalyn-success);font-size:0.8125rem;font-weight:600;">' +
                '<span class="dashicons dashicons-yes-alt" style="font-size:16px;width:16px;height:16px;margin-right:4px;"></span>Applied</span>';
        }
        var input = row.querySelector('.scalyn-alt-input');
        if (input) {
            input.disabled = true;
            input.style.opacity = '0.6';
        }
    }

    /**
     * Display AI-generated alt text results below the image_alt_text check.
     */
    function displayAltTextResults(results) {
        var container = document.querySelector('.scalyn-ai-alt-results[data-check-id="image_alt_text"]');
        if (!container || !results || results.length === 0) return;

        var postId = container.getAttribute('data-post-id') || getPostIdFromUrl() || '';
        var checkItem = container.closest('.scalyn-check-item');
        var isPass = checkItem && checkItem.getAttribute('data-status') === 'pass';
        var validCount = results.filter(function (r) { return !r.error && r.alt_text; }).length;

        // Header with Apply All (only when not already all applied).
        var html = '';
        var headerLabel = isPass ? 'Applied Alt Text (' + validCount + ' images)' : 'Alt Text Suggestions (' + validCount + ' images)';

        if (validCount > 1) {
            html += '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.5rem;">' +
                '<span class="scalyn-ai-inline-result__label" style="margin:0;">' + headerLabel + '</span>';
            if (!isPass) {
                html += '<button type="button" class="scalyn-btn scalyn-btn--small scalyn-btn--secondary scalyn-alt-apply-all">' +
                    '<span class="dashicons dashicons-yes" aria-hidden="true"></span> Apply All</button>';
            }
            html += '</div>';
        }

        results.forEach(function (item) {
            var filename = item.src.split('/').pop() || item.src;

            if (item.error) {
                html += '<div class="scalyn-ai-inline-result scalyn-alt-row" data-src="' + escAttr(item.src) + '" style="margin-bottom:0.5rem;">' +
                    '<div class="scalyn-alt-thumb"><img src="' + escAttr(item.src) + '" alt="" onerror="this.style.display=\'none\'"></div>' +
                    '<div class="scalyn-ai-inline-result__content">' +
                    '<span class="scalyn-ai-inline-result__label">' + escHtml(filename) + '</span>' +
                    '<p class="scalyn-ai-inline-result__text" style="color:var(--scalyn-danger);margin:0.25rem 0;">Error: ' + escHtml(item.error) + '</p>' +
                    '</div></div>';
                return;
            }

            if (isPass) {
                // Pass state: show as applied (read-only).
                html += '<div class="scalyn-ai-inline-result scalyn-alt-row scalyn-alt-row--applied" data-src="' + escAttr(item.src) + '" style="margin-bottom:0.5rem;">' +
                    '<div class="scalyn-alt-thumb"><img src="' + escAttr(item.src) + '" alt="" onerror="this.style.display=\'none\'"></div>' +
                    '<div class="scalyn-ai-inline-result__content">' +
                    '<span class="scalyn-ai-inline-result__label">' + escHtml(filename) + '</span>' +
                    '<input type="text" class="scalyn-alt-input" value="' + escAttr(item.alt_text) + '" disabled style="opacity:0.6;" />' +
                    '</div>' +
                    '<div class="scalyn-ai-inline-result__actions">' +
                    '<span style="color:var(--scalyn-success);font-size:0.8125rem;font-weight:600;">' +
                    '<span class="dashicons dashicons-yes-alt" style="font-size:16px;width:16px;height:16px;margin-right:4px;"></span>Applied</span>' +
                    '</div></div>';
            } else {
                // Non-pass: editable with Copy/Apply buttons.
                html += '<div class="scalyn-ai-inline-result scalyn-alt-row" data-src="' + escAttr(item.src) + '" style="margin-bottom:0.5rem;">' +
                    '<div class="scalyn-alt-thumb"><img src="' + escAttr(item.src) + '" alt="" onerror="this.style.display=\'none\'"></div>' +
                    '<div class="scalyn-ai-inline-result__content">' +
                    '<span class="scalyn-ai-inline-result__label">' + escHtml(filename) + '</span>' +
                    '<input type="text" class="scalyn-alt-input" value="' + escAttr(item.alt_text) + '" />' +
                    '</div>' +
                    '<div class="scalyn-ai-inline-result__actions">' +
                    '<button type="button" class="scalyn-btn scalyn-btn--small scalyn-alt-copy" title="Copy">' +
                    '<span class="dashicons dashicons-clipboard" aria-hidden="true"></span> Copy</button>' +
                    '<button type="button" class="scalyn-btn scalyn-btn--small scalyn-btn--secondary scalyn-alt-apply" data-src="' + escAttr(item.src) + '" data-post-id="' + escAttr(postId) + '" title="Apply">' +
                    '<span class="dashicons dashicons-yes" aria-hidden="true"></span> Apply</button>' +
                    '</div></div>';
            }
        });

        container.innerHTML = html;
        container.style.display = '';
    }

    function escAttr(str) {
        return String(str || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    /**
     * Switch a quick-fix button from Generate to Regenerate style.
     */
    function switchBtnToRegenerate(btn) {
        if (!btn) return;
        btn.innerHTML = '<span class="dashicons dashicons-update" aria-hidden="true"></span> Regenerate with AI';
        btn.classList.remove('scalyn-btn--ai');
        btn.classList.add('scalyn-btn--ghost');
    }

    function switchAllGenerateToRegenerate() {
        var selectors = [
            '.scalyn-quick-fix[data-action="generate-ai-meta"]',
            '.scalyn-quick-fix[data-action="generate-ai-alt"]',
            '.scalyn-quick-fix[data-action="generate-ai-keyword"]',
            '.scalyn-quick-fix[data-action="generate-ai-featured-image"]',
        ];
        selectors.forEach(function (sel) {
            document.querySelectorAll(sel).forEach(switchBtnToRegenerate);
        });
    }

    function initGenerateAllAi() {
        var btn = document.getElementById('scalyn-generate-all-ai');
        if (!btn) return;

        var hasRun = false;

        btn.addEventListener('click', function () {
            if (hasRun) {
                if (typeof ScalynAlert !== 'undefined') {
                    ScalynAlert.warning('Already Generated', 'AI generation has already been run for this page. Use individual Regenerate buttons to regenerate specific items.');
                }
                return;
            }

            var postId = btn.getAttribute('data-post-id') || getPostIdFromUrl();
            if (!postId) return;

            hasRun = true;
            btn.disabled = true;
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Generating All with AI',
                    text: 'Analyzing content, generating meta, keywords, images, and reviewing…',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: function () { Swal.showLoading(); },
                    customClass: { popup: 'scalyn-swal-popup' },
                });
            }

            // Build API calls — always run meta + review, add optional checks.
            var apiCalls = [
                fetchApi('ai/generate/' + postId, { method: 'POST' }),
                fetchApi('ai/review/' + postId, { method: 'POST' }),
            ];
            var callMap = { meta: 0, review: 1 };
            var nextIdx = 2;

            var hasAltCheck = !!document.querySelector('.scalyn-ai-alt-results[data-check-id="image_alt_text"]');
            if (hasAltCheck) {
                apiCalls.push(fetchApi('ai/generate-alt/' + postId, { method: 'POST' }));
                callMap.alt = nextIdx++;
            }

            var hasKeywordCheck = !!document.querySelector('.scalyn-ai-keyword-results[data-check-id="focus_keyword"]');
            if (hasKeywordCheck) {
                apiCalls.push(fetchApi('ai/generate-keywords/' + postId, { method: 'POST' }));
                callMap.keywords = nextIdx++;
            }

            var hasFeaturedImageCheck = !!document.querySelector('.scalyn-ai-featured-image-results[data-check-id="featured_image_exists"]');
            if (hasFeaturedImageCheck) {
                apiCalls.push(fetchApi('ai/generate-featured-image/' + postId, { method: 'POST' }));
                callMap.featuredImage = nextIdx++;
            }

            Promise.allSettled(apiCalls)
                .then(function (settled) {
                    if (typeof ScalynAlert !== 'undefined') ScalynAlert.close();

                    // Extract fulfilled results (rejected ones are skipped gracefully).
                    var responses = settled.map(function (s) { return s.status === 'fulfilled' ? s.value : null; });

                    var metaResponse = responses[callMap.meta];
                    if (metaResponse && metaResponse.success && metaResponse.data) {
                        displayAiResults(metaResponse.data);
                    }

                    var reviewResponse = responses[callMap.review];
                    if (reviewResponse && reviewResponse.success && reviewResponse.data) {
                        displayReviewResults(reviewResponse.data);
                    }

                    if (callMap.alt !== undefined) {
                        var altResponse = responses[callMap.alt];
                        if (altResponse && altResponse.success && altResponse.data && altResponse.data.results) {
                            displayAltTextResults(altResponse.data.results);
                        }
                    }

                    if (callMap.keywords !== undefined) {
                        var kwResponse = responses[callMap.keywords];
                        if (kwResponse && kwResponse.success && kwResponse.data) {
                            displayKeywordResults(kwResponse.data);
                        }
                    }

                    if (callMap.featuredImage !== undefined) {
                        var fiResponse = responses[callMap.featuredImage];
                        if (fiResponse && fiResponse.success && fiResponse.data) {
                            var fiPanel = document.querySelector('.scalyn-ai-featured-image-results[data-post-id="' + postId + '"]');
                            if (fiPanel) {
                                fiPanel.style.display = '';
                                addFeaturedImageOption(fiPanel, fiResponse.data, postId);
                            }
                        }
                    }

                    // Switch all Generate buttons to Regenerate.
                    switchAllGenerateToRegenerate();

                    // Disable the main button permanently for this session.
                    btn.disabled = true;
                    btn.innerHTML = '<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> AI Generated';
                    btn.classList.remove('scalyn-btn--ai');
                    btn.classList.add('scalyn-btn--secondary');
                    btn.style.opacity = '0.6';
                    btn.style.pointerEvents = 'none';

                    if (typeof ScalynAlert !== 'undefined') {
                        ScalynAlert.toast('AI analysis complete');
                    }
                });
        });
    }

    /**
     * Initialize inline AI actions (copy suggestion, apply to SEO plugin).
     */
    function initInlineAiActions() {
        // Load saved AI meta drafts if available.
        var savedMetaEl = document.getElementById('scalyn-saved-ai-meta');
        if (savedMetaEl) {
            try {
                var savedMeta = JSON.parse(savedMetaEl.textContent);
                if (savedMeta && (savedMeta.title || savedMeta.description)) {
                    displayAiResults(savedMeta);
                }
            } catch (e) {
                // Ignore parse errors.
            }
        }

        // Copy inline AI suggestion.
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.scalyn-ai-inline-copy');
            if (!btn) return;
            var panel = btn.closest('.scalyn-ai-inline-result');
            if (!panel) return;
            var text = panel.querySelector('.scalyn-ai-inline-result__text');
            if (text && text.textContent) {
                navigator.clipboard.writeText(text.textContent).then(function () {
                    if (typeof ScalynAlert !== 'undefined') ScalynAlert.toast('Copied to clipboard');
                });
            }
        });

        // Apply inline AI suggestion to SEO plugin.
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.scalyn-ai-inline-apply');
            if (!btn) return;
            var field = btn.getAttribute('data-field');
            var postId = btn.getAttribute('data-post-id');
            if (!postId || postId === '0') postId = getPostIdFromUrl();
            if (!postId || postId === '0') {
                var rescanBtn = document.getElementById('scalyn-rescan');
                if (rescanBtn) postId = rescanBtn.getAttribute('data-post-id');
            }
            if (!field || !postId || postId === '0') return;

            var panel = btn.closest('.scalyn-ai-inline-result');
            if (!panel) return;
            var text = panel.querySelector('.scalyn-ai-inline-result__text');
            if (!text || !text.textContent) return;

            var payload = {};
            payload[field] = text.textContent;

            btn.disabled = true;
            fetchApi('ai/apply/' + postId, {
                method: 'POST',
                body: JSON.stringify(payload),
            })
                .then(function (response) {
                    if (response.success) {
                        if (typeof ScalynAlert !== 'undefined') ScalynAlert.toast('Applied — rescanning...');
                        return fetchApi('scan/' + postId, {
                            method: 'POST',
                        });
                    }
                })
                .then(function () {
                    window.location.reload();
                })
                .catch(function (err) {
                    if (typeof ScalynAlert !== 'undefined') {
                        ScalynAlert.error('Apply Failed', err.message || 'Failed to apply suggestion.');
                    }
                    btn.disabled = false;
                });
        });
    }

    /**
     * Initialize AI Content Review button and regenerate.
     */
    function initAiContentReview() {
        function runReview(btn) {
            var postId = btn.getAttribute('data-post-id') || scalynQA.currentPostId;
            if (!postId) return;

            var resultsEl = document.getElementById('scalyn-review-results');
            var errorEl = document.getElementById('scalyn-review-error');

            btn.disabled = true;
            var origHtml = btn.innerHTML;
            btn.innerHTML = '<span class="dashicons dashicons-update spin"></span> Reviewing...';
            if (resultsEl) resultsEl.style.display = 'none';
            if (errorEl) errorEl.style.display = 'none';

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Reviewing Content',
                    text: 'Checking spelling, grammar, and readability...',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: function () { Swal.showLoading(); },
                    customClass: { popup: 'scalyn-swal-popup' },
                });
            }

            fetchApi('ai/review/' + postId, { method: 'POST' })
                .then(function (response) {
                    if (typeof Swal !== 'undefined') Swal.close();
                    if (response.success && response.data) {
                        displayReviewResults(response.data);
                        ScalynAlert && ScalynAlert.toast('Content review complete');
                    }
                })
                .catch(function (err) {
                    if (typeof Swal !== 'undefined') Swal.close();
                    if (errorEl) {
                        var errorText = document.getElementById('scalyn-review-error-text');
                        if (errorText) errorText.textContent = err.message || 'Content review failed.';
                        errorEl.style.display = '';
                    }
                    ScalynAlert && ScalynAlert.error('Review Failed', err.message || 'Content review failed.');
                })
                .finally(function () {
                    btn.disabled = false;
                    btn.innerHTML = origHtml;
                });
        }

        var reviewBtn = document.getElementById('scalyn-review-content');
        if (reviewBtn) {
            reviewBtn.addEventListener('click', function () { runReview(reviewBtn); });
        }

        var regenBtn = document.getElementById('scalyn-review-regenerate');
        if (regenBtn) {
            regenBtn.addEventListener('click', function () { runReview(regenBtn); });
        }

        // "Review Current" — recheck existing issues against current content.
        var recheckBtn = document.getElementById('scalyn-review-recheck');
        if (recheckBtn) {
            recheckBtn.addEventListener('click', function () {
                var postId = recheckBtn.getAttribute('data-post-id') || scalynQA.currentPostId;
                if (!postId) return;

                recheckBtn.disabled = true;
                var origHtml = recheckBtn.innerHTML;
                recheckBtn.innerHTML = '<span class="dashicons dashicons-update spin"></span> Checking...';

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Reviewing Current Content',
                        text: 'Checking which issues have been fixed...',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        showConfirmButton: false,
                        didOpen: function () { Swal.showLoading(); },
                        customClass: { popup: 'scalyn-swal-popup' },
                    });
                }

                fetchApi('ai/review/' + postId + '/recheck', { method: 'POST' })
                    .then(function (response) {
                        if (typeof Swal !== 'undefined') Swal.close();

                        if (response.success && response.data) {
                            displayReviewResults(response.data);

                            var resolved = response.data.resolved || 0;
                            var active = response.data.still_active || 0;

                            if (resolved > 0 && active === 0) {
                                ScalynAlert && ScalynAlert.success('All Fixed!', 'All ' + resolved + ' issues have been resolved.');
                            } else if (resolved > 0) {
                                ScalynAlert && ScalynAlert.toast(resolved + ' issue(s) auto-resolved, ' + active + ' still active.');
                            } else {
                                ScalynAlert && ScalynAlert.toast('No issues resolved yet. Fix the issues in the post editor and try again.');
                            }

                            // Update the saved data element for persistence.
                            var savedDataEl = document.getElementById('scalyn-saved-review-data');
                            if (savedDataEl) {
                                savedDataEl.textContent = JSON.stringify(response.data);
                            }
                        }
                    })
                    .catch(function (err) {
                        if (typeof Swal !== 'undefined') Swal.close();
                        ScalynAlert && ScalynAlert.error('Recheck Failed', err.message || 'Could not recheck issues.');
                    })
                    .finally(function () {
                        recheckBtn.disabled = false;
                        recheckBtn.innerHTML = origHtml;
                    });
            });
        }

        // Load saved review data if available.
        var savedDataEl = document.getElementById('scalyn-saved-review-data');
        if (savedDataEl) {
            try {
                var savedData = JSON.parse(savedDataEl.textContent);
                if (savedData && savedData.summary) {
                    displayReviewResults(savedData);
                }
            } catch (e) {
                // Ignore parse errors.
            }
        }

        // Copy suggestion to clipboard.
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.scalyn-review-copy');
            if (!btn) return;
            var row = btn.closest('tr');
            if (!row) return;
            // Get suggestion text from the 4th column.
            var cells = row.querySelectorAll('td');
            var suggestion = cells[3] ? cells[3].textContent.trim() : '';
            if (suggestion) {
                navigator.clipboard.writeText(suggestion).then(function () {
                    if (typeof ScalynAlert !== 'undefined') ScalynAlert.toast('Suggestion copied to clipboard');
                });
            }
        });

        // Mark as resolved.
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.scalyn-review-resolve');
            if (!btn) return;
            var row = btn.closest('tr');
            if (!row) return;
            var idx = parseInt(row.getAttribute('data-issue-index'), 10);
            if (!isNaN(idx)) updateReviewIssueStatus(idx, 'resolved');
        });

        // Ignore issue.
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.scalyn-review-ignore');
            if (!btn) return;
            var row = btn.closest('tr');
            if (!row) return;
            var idx = parseInt(row.getAttribute('data-issue-index'), 10);
            if (!isNaN(idx)) updateReviewIssueStatus(idx, 'ignored');
        });

        // Restore dismissed issue.
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.scalyn-review-restore');
            if (!btn) return;
            var idx = parseInt(btn.getAttribute('data-issue-index'), 10);
            if (!isNaN(idx)) updateReviewIssueStatus(idx, null);
        });
    }

    /**
     * Display AI content review results.
     */
    function displayReviewResults(data) {
        var resultsEl = document.getElementById('scalyn-review-results');
        var summaryText = document.getElementById('scalyn-review-summary-text');
        var scoreBadge = document.getElementById('scalyn-review-score-badge');
        var issuesWrap = document.getElementById('scalyn-review-issues-wrap');
        var issuesBody = document.getElementById('scalyn-review-issues-body');
        var emptyState = document.getElementById('scalyn-review-empty');
        if (emptyState) emptyState.style.display = 'none';

        if (!resultsEl) return;

        if (summaryText) summaryText.textContent = data.summary || 'No summary available.';

        if (scoreBadge) {
            var score = data.score || 0;
            var status = score >= 80 ? 'green' : (score >= 50 ? 'yellow' : 'red');
            scoreBadge.className = 'scalyn-badge scalyn-badge--' + status;
            scoreBadge.textContent = score + '/100';
        }

        if (issuesBody) {
            issuesBody.innerHTML = '';

            var issues = data.issues || [];
            var activeIssues = issues.filter(function (i) { return i.status !== 'resolved' && i.status !== 'ignored'; });
            var dismissedIssues = issues.filter(function (i) { return i.status === 'resolved' || i.status === 'ignored'; });

            if (activeIssues.length > 0 && issuesWrap) {
                issuesWrap.style.display = '';

                var severityBadge = { error: 'red', warning: 'yellow', suggestion: 'neutral' };

                activeIssues.forEach(function (issue, idx) {
                    var row = document.createElement('tr');
                    row.setAttribute('data-issue-index', issues.indexOf(issue));
                    row.innerHTML =
                        '<td><span class="scalyn-badge scalyn-badge--neutral">' + escHtml(issue.type || '') + '</span></td>' +
                        '<td><span class="scalyn-badge scalyn-badge--' + (severityBadge[issue.severity] || 'neutral') + '">' + escHtml(issue.severity || '') + '</span></td>' +
                        '<td>' +
                            '<strong>' + escHtml(issue.text || '') + '</strong>' +
                            (issue.context ? '<br><small style="color:var(--scalyn-text-muted)">' + escHtml(issue.context) + '</small>' : '') +
                        '</td>' +
                        '<td>' + escHtml(issue.suggestion || '') + '</td>' +
                        '<td style="white-space:nowrap;">' +
                            '<button type="button" class="scalyn-btn scalyn-btn--small scalyn-btn--ghost scalyn-review-copy" title="Copy suggestion">' +
                                '<span class="dashicons dashicons-clipboard" aria-hidden="true"></span>' +
                            '</button>' +
                            '<button type="button" class="scalyn-btn scalyn-btn--small scalyn-btn--ghost scalyn-review-resolve" title="Mark as resolved">' +
                                '<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>' +
                            '</button>' +
                            '<button type="button" class="scalyn-btn scalyn-btn--small scalyn-btn--ghost scalyn-review-ignore" title="Ignore">' +
                                '<span class="dashicons dashicons-hidden" aria-hidden="true"></span>' +
                            '</button>' +
                        '</td>';
                    issuesBody.appendChild(row);
                });
            } else if (issuesWrap) {
                issuesWrap.style.display = activeIssues.length > 0 ? '' : 'none';
            }

            // Show dismissed issues in a collapsed section.
            var dismissedWrap = document.getElementById('scalyn-review-dismissed-wrap');
            if (dismissedIssues.length > 0) {
                if (!dismissedWrap) {
                    dismissedWrap = document.createElement('div');
                    dismissedWrap.id = 'scalyn-review-dismissed-wrap';
                    issuesWrap.parentNode.insertBefore(dismissedWrap, issuesWrap.nextSibling);
                }
                var severityBadge2 = { error: 'red', warning: 'yellow', suggestion: 'neutral' };
                var dismissedHtml = '<details class="scalyn-ignored-section" style="margin-top:0.75rem;">' +
                    '<summary class="scalyn-ignored-section__toggle">' + dismissedIssues.length + ' resolved/ignored issue' + (dismissedIssues.length !== 1 ? 's' : '') + '</summary>' +
                    '<div class="scalyn-ignored-section__list"><table class="scalyn-table scalyn-table--compact"><tbody>';
                dismissedIssues.forEach(function (issue) {
                    var badge = issue.status === 'resolved' ? 'green' : 'neutral';
                    var label = issue.status === 'resolved' ? 'Resolved' : 'Ignored';
                    dismissedHtml +=
                        '<tr style="opacity:0.6;">' +
                        '<td><span class="scalyn-badge scalyn-badge--neutral">' + escHtml(issue.type || '') + '</span></td>' +
                        '<td><span class="scalyn-badge scalyn-badge--' + badge + '">' + label + '</span></td>' +
                        '<td><strong>' + escHtml(issue.text || '') + '</strong></td>' +
                        '<td>' + escHtml(issue.suggestion || '') + '</td>' +
                        '<td><button type="button" class="scalyn-btn scalyn-btn--small scalyn-btn--ghost scalyn-review-restore" data-issue-index="' + issues.indexOf(issue) + '" title="Restore"><span class="dashicons dashicons-visibility" aria-hidden="true"></span></button></td>' +
                        '</tr>';
                });
                dismissedHtml += '</tbody></table></div></details>';
                dismissedWrap.innerHTML = dismissedHtml;
            } else if (dismissedWrap) {
                dismissedWrap.innerHTML = '';
            }

            // Update active count in summary.
            if (activeIssues.length === 0 && dismissedIssues.length > 0 && issuesWrap) {
                issuesWrap.style.display = 'none';
            }
        }

        resultsEl.style.display = '';
    }

    /**
     * Update a review issue status and save to server.
     */
    function updateReviewIssueStatus(issueIndex, newStatus) {
        var savedDataEl = document.getElementById('scalyn-saved-review-data');
        if (!savedDataEl) return;

        var data;
        try { data = JSON.parse(savedDataEl.textContent); } catch (e) { return; }

        if (!data || !data.issues || !data.issues[issueIndex]) return;

        data.issues[issueIndex].status = newStatus;
        savedDataEl.textContent = JSON.stringify(data);

        // Get post ID from the review button or URL.
        var postId = null;
        var reviewBtn = document.getElementById('scalyn-review-content');
        if (reviewBtn) postId = reviewBtn.getAttribute('data-post-id');
        if (!postId) postId = getPostIdFromUrl();
        if (!postId && scalynQA) postId = scalynQA.currentPostId;

        if (postId) {
            fetchApi('ai/review/' + postId + '/update', {
                method: 'POST',
                body: JSON.stringify({ issues: data.issues }),
            }).catch(function (err) {
                console.error('Scalyn QA: Failed to save review status.', err);
            });
        }

        // Re-render.
        displayReviewResults(data);
    }

    function escHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(String(str || '')));
        return div.innerHTML;
    }

    /**
     * Handle "Ignore Check" buttons.
     */
    function initIgnoreCheck() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.scalyn-ignore-check');
            if (!btn) return;

            var checkId = btn.getAttribute('data-check-id');
            var postId = btn.getAttribute('data-post-id') || scalynQA.currentPostId;
            if (!checkId) return;

            if (typeof Swal === 'undefined') return;

            Swal.fire({
                title: 'Ignore This Check',
                text: 'Provide a reason for ignoring this check (optional):',
                input: 'text',
                inputPlaceholder: 'Reason (optional)',
                showCancelButton: true,
                confirmButtonText: 'Ignore',
                confirmButtonColor: '#f0ad4e',
                customClass: { popup: 'scalyn-swal-popup' },
            }).then(function (result) {
                if (!result.isConfirmed) return;

                var reason = result.value || '';

                fetchApi('ignore', {
                    method: 'POST',
                    body: JSON.stringify({
                        type: postId ? 'check' : 'global',
                        check_id: checkId,
                        post_id: postId ? parseInt(postId, 10) : null,
                        reason: reason,
                        context: 'audit',
                    }),
                })
                    .then(function (response) {
                        if (response.success) {
                            ScalynAlert && ScalynAlert.toast('Check ignored — rescanning...');
                            // Rescan to recalculate scores without ignored checks.
                            return fetchApi('scan/' + postId, { method: 'POST' });
                        }
                    })
                    .then(function () {
                        window.location.reload();
                    })
                    .catch(function (err) {
                        ScalynAlert && ScalynAlert.error('Error', err.message || 'Failed to ignore check.');
                    });
            });
        });
    }

    /**
     * Handle "Remove Ignore" buttons.
     */
    function initRemoveIgnore() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.scalyn-remove-ignore');
            if (!btn) return;

            var ruleId = btn.getAttribute('data-rule-id');
            if (!ruleId) return;

            if (typeof ScalynAlert === 'undefined') return;

            ScalynAlert.confirm(
                'Remove Ignore Rule',
                'This check will be evaluated again in future scans.',
                'Remove'
            ).then(function (result) {
                if (!result.isConfirmed) return;

                var postId = getPostIdFromUrl();
                fetchApi('ignore/' + ruleId, { method: 'DELETE' })
                    .then(function (response) {
                        if (response.success) {
                            ScalynAlert.toast('Check restored — rescanning...');
                            if (postId) {
                                return fetchApi('scan/' + postId, { method: 'POST' });
                            }
                        }
                    })
                    .then(function () {
                        window.location.reload();
                    })
                    .catch(function (err) {
                        ScalynAlert.error('Error', err.message || 'Failed to remove ignore rule.');
                    });
            });
        });
    }

    /**
     * Handle quick fix buttons.
     */
    function initQuickFixes() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.scalyn-quick-fix');
            if (!btn) return;

            var action = btn.getAttribute('data-action');
            var postId = btn.getAttribute('data-post-id') || scalynQA.currentPostId;

            switch (action) {
                case 'generate-ai-meta':
                    triggerAiGeneration(postId, btn);
                    break;

                case 'generate-ai-featured-image':
                    generateAiFeaturedImage(postId, btn);
                    break;

                case 'generate-ai-alt':
                    triggerAiAltGeneration(postId, btn);
                    break;

                case 'use-titles-as-alt':
                    triggerTitlesAsAlt(postId, btn);
                    break;

                case 'upload-featured-image':
                    openMediaLibrary(postId);
                    break;

                case 'jump-to-heading':
                    navigateToPostEditor(postId);
                    break;

                case 'edit-link':
                    navigateToPostEditor(postId);
                    break;

                default:
                    break;
            }
        });
    }

    /**
     * Trigger AI meta generation from quick fix.
     *
     * @param {string|number} postId - Post ID.
     * @param {Element} triggerBtn - The button that triggered the generation.
     */
    function triggerAiGeneration(postId, triggerBtn) {
        if (!postId) return;

        if (triggerBtn) triggerBtn.disabled = true;
        if (typeof ScalynAlert !== 'undefined') {
            ScalynAlert.loading('Generating AI suggestions...');
        }

        fetchApi('ai/generate/' + postId, { method: 'POST' })
            .then(function (response) {
                if (typeof ScalynAlert !== 'undefined') {
                    ScalynAlert.close();
                }
                if (response.success && response.data) {
                    displayAiResults(response.data);
                    document.querySelectorAll('.scalyn-quick-fix[data-action="generate-ai-meta"]').forEach(switchBtnToRegenerate);
                    if (typeof ScalynAlert !== 'undefined') {
                        ScalynAlert.toast('AI suggestions generated');
                    }
                }
            })
            .catch(function (err) {
                if (typeof ScalynAlert !== 'undefined') {
                    ScalynAlert.close();
                    ScalynAlert.error('AI Generation Failed', err.message || 'An error occurred.');
                }
            })
            .finally(function () {
                if (triggerBtn) triggerBtn.disabled = false;
            });
    }

    /**
     * Trigger AI alt text generation for all images missing alt.
     */
    function triggerAiAltGeneration(postId, triggerBtn) {
        if (!postId) return;

        if (triggerBtn) triggerBtn.disabled = true;
        Swal.fire({
            title: 'Generating alt text…',
            text: 'This may take a moment for multiple images.',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: function () { Swal.showLoading(); },
            customClass: { popup: 'scalyn-swal-popup' },
        });

        fetchApi('ai/generate-alt/' + postId, { method: 'POST' })
            .then(function (response) {
                Swal.close();
                if (response.success && response.data && response.data.results) {
                    displayAltTextResults(response.data.results);
                    document.querySelectorAll('.scalyn-quick-fix[data-action="generate-ai-alt"]').forEach(switchBtnToRegenerate);
                    ScalynAlert.toast('Alt text generated for ' + response.data.results.length + ' images');
                } else {
                    ScalynAlert.error('No Results', 'AI returned no alt text suggestions. Check your OpenAI provider settings.');
                }
            })
            .catch(function (err) {
                Swal.close();
                ScalynAlert.error('Alt Text Generation Failed', err.message || 'An error occurred.');
            })
            .finally(function () {
                if (triggerBtn) triggerBtn.disabled = false;
            });
    }

    /**
     * Apply image titles as alt text immediately, then rescan.
     */
    function triggerTitlesAsAlt(postId, triggerBtn) {
        if (!postId) return;

        if (triggerBtn) triggerBtn.disabled = true;
        ScalynAlert.loading('Applying titles as alt text…');

        fetchApi('ai/titles-as-alt/' + postId, { method: 'POST' })
            .then(function (response) {
                Swal.close();
                if (response.success && response.data && response.data.applied > 0) {
                    ScalynAlert.toast(response.data.message || 'Titles applied as alt text');
                    var rescanBtn = document.querySelector('#scalyn-rescan') || document.querySelector('.scalyn-rescan');
                    if (rescanBtn) rescanBtn.click();
                } else {
                    ScalynAlert.warning('No Titles Found', 'Could not find image titles to use. Images may not be in the Media Library.');
                }
            })
            .catch(function (err) {
                Swal.close();
                ScalynAlert.error('Failed', err.message || 'An error occurred.');
            })
            .finally(function () {
                if (triggerBtn) triggerBtn.disabled = false;
            });
    }

    /**
     * Generate a featured image using AI (DALL-E) and set it on the post.
     *
     * @param {string|number} postId - Post ID.
     * @param {Element} triggerBtn - The button that triggered the generation.
     */
    function generateAiFeaturedImage(postId, triggerBtn) {
        if (!postId) return;

        var panel = document.querySelector('.scalyn-ai-featured-image-results[data-post-id="' + postId + '"]');
        if (!panel) return;

        // Show the panel and add a loading state.
        panel.style.display = '';
        var loadingId = 'scalyn-fi-loading-' + Date.now();
        var loadingHtml = '<div id="' + loadingId + '" style="text-align:center;padding:20px;">' +
            '<span class="dashicons dashicons-update" style="animation:rotation 1s linear infinite;font-size:24px;width:24px;height:24px;"></span>' +
            '<p style="margin:8px 0 0;color:var(--scalyn-text-muted);font-size:13px;">Generating image… This may take 15–30 seconds.</p>' +
            '</div>';

        // If no grid yet, set it up matching the standard __content / __actions layout.
        if (!panel.querySelector('.scalyn-fi-grid')) {
            panel.innerHTML = '<div class="scalyn-ai-inline-result">' +
                '<div class="scalyn-ai-inline-result__content">' +
                '<span class="scalyn-ai-inline-result__label">AI Generated Images</span>' +
                '<div class="scalyn-fi-grid"></div>' +
                '<span class="scalyn-ai-inline-result__meta scalyn-fi-meta"></span>' +
                '</div>' +
                '<div class="scalyn-ai-inline-result__actions">' +
                '<button type="button" class="scalyn-btn scalyn-btn--small scalyn-btn--secondary scalyn-fi-apply" data-post-id="' + postId + '" disabled>' +
                '<span class="dashicons dashicons-yes" aria-hidden="true"></span> Apply</button>' +
                '</div></div>';
        }

        var grid = panel.querySelector('.scalyn-fi-grid');
        grid.insertAdjacentHTML('beforeend', loadingHtml);

        if (triggerBtn) triggerBtn.disabled = true;

        fetchApi('ai/generate-featured-image/' + postId, { method: 'POST' })
            .then(function (response) {
                var loader = document.getElementById(loadingId);
                if (loader) loader.remove();

                if (response.success && response.data) {
                    addFeaturedImageOption(panel, response.data, postId);
                }
            })
            .catch(function (err) {
                var loader = document.getElementById(loadingId);
                if (loader) loader.remove();
                ScalynAlert.error('Image Generation Failed', err.message || 'An error occurred.');
            })
            .finally(function () {
                if (triggerBtn) triggerBtn.disabled = false;
            });
    }

    /**
     * Add a generated image option to the inline grid.
     */
    function addFeaturedImageOption(panel, data, postId) {
        var grid = panel.querySelector('.scalyn-fi-grid');
        if (!grid) return;

        var count = grid.querySelectorAll('.scalyn-fi-option').length;
        var filename = data.url.split('/').pop() || 'ai-featured-' + (count + 1) + '.png';

        // Deselect all existing options and select the new one.
        grid.querySelectorAll('.scalyn-fi-option').forEach(function (opt) {
            opt.classList.remove('selected');
            var r = opt.querySelector('input[type="radio"]');
            if (r) r.checked = false;
        });

        var option = document.createElement('label');
        option.className = 'scalyn-fi-option selected';
        option.innerHTML = '<img src="' + data.url + '" alt="AI generated option ' + (count + 1) + '" />' +
            '<div class="scalyn-fi-option-footer">' +
            '<input type="radio" name="scalyn-fi-select-' + postId + '" value="' + data.attachment_id + '" checked>' +
            '<span>' + filename + '</span></div>';

        grid.appendChild(option);

        // Update header label to reflect multiple options.
        var label = panel.querySelector('.scalyn-ai-inline-result__label');
        if (label) label.textContent = 'Featured Image Options';

        // Update meta text.
        var meta = panel.querySelector('.scalyn-fi-meta');
        if (meta && data.provider) {
            meta.textContent = data.provider;
        }

        // Enable apply button.
        var applyBtn = panel.querySelector('.scalyn-fi-apply');
        if (applyBtn) applyBtn.disabled = false;

        // Wire up radio selection highlight.
        bindFiRadioHighlight(option, grid);
    }

    /**
     * Bind radio change to highlight the selected featured image option.
     */
    function bindFiRadioHighlight(option, grid) {
        var radio = option.querySelector('input[type="radio"]');
        if (!radio) return;
        radio.addEventListener('change', function () {
            grid.querySelectorAll('.scalyn-fi-option').forEach(function (opt) {
                opt.classList.remove('selected');
            });
            option.classList.add('selected');
        });
    }

    /**
     * Handle inline featured image Regenerate and Apply clicks.
     */
    function initFeaturedImageInline() {
        // Wire up radio highlights on server-rendered options (current featured image).
        document.querySelectorAll('.scalyn-ai-featured-image-results').forEach(function (panel) {
            var grid = panel.querySelector('.scalyn-fi-grid');
            if (!grid) return;
            grid.querySelectorAll('.scalyn-fi-option').forEach(function (option) {
                bindFiRadioHighlight(option, grid);
            });
        });

        document.addEventListener('click', function (e) {
            // Apply button.
            var applyBtn = e.target.closest('.scalyn-fi-apply');
            if (applyBtn) {
                var postId = applyBtn.getAttribute('data-post-id');
                var panel = applyBtn.closest('.scalyn-ai-featured-image-results');
                var selected = panel ? panel.querySelector('input[type="radio"]:checked') : null;

                if (!selected) {
                    ScalynAlert.warning('No Image Selected', 'Please select an image first.');
                    return;
                }

                applyBtn.disabled = true;
                ScalynAlert.loading('Applying featured image…');

                fetchApi('ai/apply-featured-image/' + postId, {
                    method: 'POST',
                    body: JSON.stringify({ attachment_id: parseInt(selected.value, 10) }),
                })
                .then(function () {
                    ScalynAlert.close();
                    ScalynAlert.toast('Featured image applied!');
                    var rescanBtn = document.querySelector('#scalyn-rescan') || document.querySelector('.scalyn-rescan');
                    if (rescanBtn) rescanBtn.click();
                })
                .catch(function (err) {
                    ScalynAlert.close();
                    ScalynAlert.error('Apply Failed', err.message || 'Failed to set featured image.');
                    applyBtn.disabled = false;
                });
            }
        });
    }

    /**
     * Open the WordPress media library for uploading a featured image.
     *
     * @param {string|number} postId - Post ID.
     */
    function openMediaLibrary(postId) {
        if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
            // Fallback: navigate to post editor.
            navigateToPostEditor(postId);
            return;
        }

        var frame = wp.media({
            title: 'Select Featured Image',
            button: { text: 'Set Featured Image' },
            multiple: false,
            library: { type: 'image' },
        });

        frame.on('select', function () {
            var attachment = frame.state().get('selection').first().toJSON();
            // Set as featured image via REST API.
            fetch(scalynQA.restUrl.replace('scalyn-qa/v1/', '') + 'wp/v2/posts/' + postId, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': scalynQA.nonce,
                },
                credentials: 'same-origin',
                body: JSON.stringify({ featured_media: attachment.id }),
            })
                .then(function () {
                    if (typeof ScalynAlert !== 'undefined') {
                        ScalynAlert.toast('Featured image set');
                    }
                })
                .catch(function (err) {
                    if (typeof ScalynAlert !== 'undefined') {
                        ScalynAlert.error('Error', 'Failed to set featured image.');
                    }
                });
        });

        frame.open();
    }

    /**
     * Navigate to the post editor in a new tab.
     *
     * @param {string|number} postId - Post ID.
     */
    function navigateToPostEditor(postId) {
        if (!postId) return;
        var editUrl = scalynQA.restUrl.split('/wp-json/')[0] + '/wp-admin/post.php?post=' + postId + '&action=edit';
        window.open(editUrl, '_blank');
    }

    // -------------------------------------------------------------------------
    // Initialization
    // -------------------------------------------------------------------------

    /**
     * Determine which page we are on and initialize accordingly.
     */
    function init() {
        // Check if we're on a single audit page (has post_id in URL).
        var urlParams = new URLSearchParams(window.location.search);
        var isInSingleView = urlParams.has('post_id') && parseInt(urlParams.get('post_id'), 10) > 0;

        if (isInSingleView) {
            initSinglePage();
        } else {
            initListPage();
        }

        // Global: toggle check details lists.
        initCheckDetailsToggle();
    }

    /**
     * Handle expand/collapse of check details lists.
     */
    function initCheckDetailsToggle() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.scalyn-check-details__toggle');
            if (!btn) return;

            var targetId = btn.getAttribute('data-target');
            var list = targetId ? document.getElementById(targetId) : null;
            if (!list) return;

            var isOpen = list.style.display !== 'none';
            list.style.display = isOpen ? 'none' : '';
            btn.classList.toggle('scalyn-check-details__toggle--open', !isOpen);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();

/**
 * Admin Dashboard JS.
 *
 * Handles the main Scalyn QA dashboard: score circle animations,
 * project score fetching, batch scanning, auto-refresh, and tooltips.
 *
 * @package Scalyn\QA\Assets
 * @since   1.0.0
 */

'use strict';

(function () {

    /** @type {number|null} Auto-refresh interval ID. */
    var refreshIntervalId = null;

    /** Auto-refresh interval in milliseconds. */
    var REFRESH_INTERVAL = 60000;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Escape HTML entities for safe rendering.
     *
     * @param {string} str - The string to escape.
     * @returns {string} Escaped string.
     */
    function escapeHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    /**
     * Wrapper for fetch() that adds the REST nonce header and base URL.
     *
     * @param {string} endpoint - Relative endpoint path (e.g. 'scores/summary').
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
     * Update a score circle element's conic-gradient and displayed value.
     *
     * @param {HTMLElement} element - The .scalyn-score-circle element.
     * @param {number}      score   - The score value (0-100).
     * @param {string}      status  - Traffic-light status: 'green', 'yellow', or 'red'.
     */
    function updateScoreCircle(element, score, status) {
        if (!element) {
            return;
        }

        var valueEl = element.querySelector('.scalyn-score-circle__value');
        if (valueEl) {
            valueEl.textContent = score;
        }

        // Remove existing status classes and add the new one.
        element.classList.remove(
            'scalyn-score-circle--green',
            'scalyn-score-circle--yellow',
            'scalyn-score-circle--red'
        );
        element.classList.add('scalyn-score-circle--' + status);

        // Animate the conic-gradient via a CSS custom property.
        animateScoreCircle(element, score);
    }

    /**
     * Animate a score circle from 0 to the target percentage.
     *
     * @param {HTMLElement} element - The .scalyn-score-circle element.
     * @param {number}      target  - The target score (0-100).
     */
    function animateScoreCircle(element, target) {
        var current = 0;
        var step = Math.max(1, Math.ceil(target / 40));
        var duration = 600;
        var stepTime = duration / (target / step || 1);

        function frame() {
            current += step;
            if (current >= target) {
                current = target;
                element.style.setProperty('--score-percent', current + '%');
                return;
            }
            element.style.setProperty('--score-percent', current + '%');
            requestAnimationFrame(function () {
                setTimeout(frame, stepTime);
            });
        }

        element.style.setProperty('--score-percent', '0%');
        if (target > 0) {
            requestAnimationFrame(function () {
                setTimeout(frame, stepTime);
            });
        }
    }

    /**
     * Convert an ISO date string to a human-readable "X minutes ago" format.
     *
     * @param {string} dateString - ISO 8601 date string.
     * @returns {string} Human-readable time ago string.
     */
    function formatTimeAgo(dateString) {
        if (!dateString) {
            return 'Never';
        }

        var now = new Date();
        var date = new Date(dateString);
        var seconds = Math.floor((now - date) / 1000);

        if (seconds < 0) {
            return 'Just now';
        }

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
     * Calculate the traffic-light status for a given score.
     *
     * @param {number} score - The score value (0-100).
     * @returns {string} 'green', 'yellow', or 'red'.
     */
    function getStatus(score) {
        var settings = scalynQA.settings || {};
        var greenThreshold = parseInt(settings.green_threshold, 10) || 80;
        var yellowThreshold = parseInt(settings.yellow_threshold, 10) || 50;

        if (score >= greenThreshold) {
            return 'green';
        }
        if (score >= yellowThreshold) {
            return 'yellow';
        }
        return 'red';
    }

    // -------------------------------------------------------------------------
    // Dashboard Functionality
    // -------------------------------------------------------------------------

    /**
     * Fetch project scores from the REST API and update the dashboard cards.
     */
    function loadProjectScores() {
        fetchApi('scores/summary')
            .then(function (response) {
                if (!response.success || !response.data) {
                    return;
                }

                var data = response.data;
                var circles = document.querySelectorAll('.scalyn-card--score');

                // The cards are rendered in order: SEO Ready, QA Ready, Launch Ready, Overall.
                var scoreKeys = [
                    { key: 'seo_ready', label: 'SEO Ready' },
                    { key: 'qa_ready', label: 'QA Ready' },
                    { key: 'launch_ready', label: 'Launch Ready' },
                    { key: 'overall', label: 'Overall' },
                ];

                circles.forEach(function (card, index) {
                    if (!scoreKeys[index]) {
                        return;
                    }

                    var key = scoreKeys[index].key;
                    var score = parseInt(data[key], 10) || 0;
                    var status = getStatus(score);
                    var circle = card.querySelector('.scalyn-score-circle');

                    if (circle) {
                        updateScoreCircle(circle, score, status);
                    }

                    // Update the badge.
                    var badge = card.querySelector('.scalyn-badge');
                    if (badge) {
                        badge.className = 'scalyn-badge scalyn-badge--' + status;
                        var labels = { green: 'Passed', yellow: 'Needs Review', red: 'Issues Found' };
                        badge.textContent = labels[status] || status;
                    }
                });
            })
            .catch(function (err) {
                console.error('Scalyn QA: Failed to load project scores.', err);
            });
    }

    /**
     * Handle "Scan All" button click.
     * Shows a confirmation dialog, then performs a batch scan with progress.
     */
    function handleScanAll() {
        var scanAllBtn = document.getElementById('scalyn-scan-all') || document.getElementById('scalyn-scan-all-pages');
        if (!scanAllBtn) {
            return;
        }

        scanAllBtn.addEventListener('click', function () {
            if (typeof ScalynAlert === 'undefined') {
                return;
            }

            ScalynAlert.confirm(
                'Scan All Pages',
                'This will scan all pages for QA issues. This may take a moment.',
                'Start Scan'
            ).then(function (result) {
                if (!result.isConfirmed) {
                    return;
                }

                runBatchScan();
            });
        });
    }

    /**
     * Run a batch scan by collecting all post IDs from the table or
     * from the pages needing attention widget and scanning in batches of 20.
     */
    function runBatchScan() {
        if (typeof ScalynAlert !== 'undefined') {
            ScalynAlert.loading('Loading pages...');
        }

        // Fetch all scannable post IDs from the server.
        fetchApi('scan/post-ids', { method: 'GET' })
            .then(function (response) {
                var postIds = (response.success && response.data && response.data.post_ids) ? response.data.post_ids : [];

                // Fallback: collect from DOM if endpoint doesn't exist.
                if (postIds.length === 0) {
                    var rows = document.querySelectorAll('[data-post-id]');
                    rows.forEach(function (row) {
                        var id = parseInt(row.getAttribute('data-post-id'), 10);
                        if (id > 0 && postIds.indexOf(id) === -1) {
                            postIds.push(id);
                        }
                    });
                }

                if (postIds.length === 0) {
                    if (typeof ScalynAlert !== 'undefined') {
                        ScalynAlert.close();
                        ScalynAlert.warning('No Pages', 'No pages found to scan.');
                    }
                    return;
                }

                startBatchScan(postIds);
            })
            .catch(function () {
                // Fallback: collect from DOM.
                var rows = document.querySelectorAll('[data-post-id]');
                var postIds = [];
                rows.forEach(function (row) {
                    var id = parseInt(row.getAttribute('data-post-id'), 10);
                    if (id > 0 && postIds.indexOf(id) === -1) {
                        postIds.push(id);
                    }
                });
                if (postIds.length > 0) {
                    startBatchScan(postIds);
                } else if (typeof ScalynAlert !== 'undefined') {
                    ScalynAlert.close();
                    ScalynAlert.warning('No Pages', 'No pages found to scan.');
                }
            });
    }

    function startBatchScan(postIds) {
        if (typeof ScalynAlert !== 'undefined') {
            ScalynAlert.loading('Scanning ' + postIds.length + ' pages...');
        }

        // Process in batches of 20 (API limit).
        var batchSize = 20;
        var batches = [];
        for (var i = 0; i < postIds.length; i += batchSize) {
            batches.push(postIds.slice(i, i + batchSize));
        }

        var completed = 0;
        var total = postIds.length;

        function processBatch(index) {
            if (index >= batches.length) {
                if (typeof ScalynAlert !== 'undefined') {
                    ScalynAlert.close();
                    ScalynAlert.success(
                        'Scan Complete',
                        'Successfully scanned ' + total + ' page(s).'
                    );
                }
                loadProjectScores();
                return;
            }

            fetchApi('scan/batch', {
                method: 'POST',
                body: JSON.stringify({ post_ids: batches[index] }),
            })
                .then(function (response) {
                    completed += batches[index].length;

                    if (typeof Swal !== 'undefined' && Swal.isVisible()) {
                        Swal.update({
                            title: 'Scanning pages... (' + completed + '/' + total + ')',
                        });
                    }

                    processBatch(index + 1);
                })
                .catch(function (err) {
                    console.error('Scalyn QA: Batch scan error.', err);
                    completed += batches[index].length;
                    processBatch(index + 1);
                });
        }

        processBatch(0);
    }

    /**
     * Handle click events on "View Audit" links.
     */
    function initAuditLinks() {
        document.addEventListener('click', function (e) {
            var link = e.target.closest('a[href*="scalyn-qa-audits"]');
            if (!link) {
                return;
            }

            // Let the browser navigate naturally; no special handling needed
            // unless we want to add a loading indicator.
        });
    }

    /**
     * Initialize tooltips: show/hide on hover via event delegation.
     */
    function initTooltips() {
        document.addEventListener('mouseenter', function (e) {
            if (!e.target || !e.target.closest) return;
            var tooltip = e.target.closest('.scalyn-tooltip');
            if (tooltip) {
                var content = tooltip.querySelector('.scalyn-tooltip__content');
                if (content) {
                    content.style.visibility = 'visible';
                    content.style.opacity = '1';
                }
            }
        }, true);

        document.addEventListener('mouseleave', function (e) {
            if (!e.target || !e.target.closest) return;
            var tooltip = e.target.closest('.scalyn-tooltip');
            if (tooltip) {
                var content = tooltip.querySelector('.scalyn-tooltip__content');
                if (content) {
                    content.style.visibility = 'hidden';
                    content.style.opacity = '0';
                }
            }
        }, true);
    }

    /**
     * Initialize score circle animations for all score cards on the page.
     */
    function animateAllScoreCircles() {
        var circles = document.querySelectorAll('.scalyn-score-circle');

        circles.forEach(function (circle) {
            var valueEl = circle.querySelector('.scalyn-score-circle__value');
            if (!valueEl) {
                return;
            }

            var score = parseInt(valueEl.textContent, 10) || 0;
            animateScoreCircle(circle, score);
        });
    }

    /**
     * Start auto-refresh of dashboard data every 60 seconds.
     * Only refreshes while the page tab is visible.
     */
    function startAutoRefresh() {
        refreshIntervalId = setInterval(function () {
            if (document.visibilityState === 'visible') {
                loadProjectScores();
            }
        }, REFRESH_INTERVAL);

        // Stop when the page is unloaded.
        window.addEventListener('beforeunload', function () {
            if (refreshIntervalId) {
                clearInterval(refreshIntervalId);
            }
        });
    }

    // -------------------------------------------------------------------------
    // Initialization
    // -------------------------------------------------------------------------

    /**
     * Initialize all dashboard functionality on DOMContentLoaded.
     */
    /**
     * Handle Launch Checklist "Run Check" button.
     */
    function initLaunchScan() {
        var btn = document.getElementById('scalyn-launch-scan');
        if (btn) btn.addEventListener('click', runLaunchScan);

        var emptyBtn = document.getElementById('scalyn-launch-scan-empty');
        if (emptyBtn) emptyBtn.addEventListener('click', runLaunchScan);
    }

    function runLaunchScan() {
        var btn = document.getElementById('scalyn-launch-scan') || document.getElementById('scalyn-launch-scan-empty');
        if (btn) btn.disabled = true;

        if (typeof ScalynAlert !== 'undefined') ScalynAlert.loading('Running launch check…');

        fetchApi('launch/scan', { method: 'POST' })
            .then(function (response) {
                if (response.success) {
                    if (typeof ScalynAlert !== 'undefined') ScalynAlert.toast('Launch check complete');
                    window.location.reload();
                }
            })
            .catch(function (err) {
                if (typeof ScalynAlert !== 'undefined') {
                    ScalynAlert.close();
                    ScalynAlert.error('Error', err.message || 'Launch check failed.');
                }
                if (btn) btn.disabled = false;
            });
    }

    /**
     * Handle "Auto Fix All" button — runs all auto-fixable checks sequentially.
     */
    function initAutoFixAll() {
        var btn = document.getElementById('scalyn-launch-auto-fix-all');
        if (!btn) return;

        btn.addEventListener('click', function () {
            var fixBtns = document.querySelectorAll('.scalyn-launch-auto-fix');
            var checkIds = [];
            fixBtns.forEach(function (b) {
                var id = b.getAttribute('data-check-id');
                if (id) checkIds.push(id);
            });

            if (checkIds.length === 0) {
                if (typeof ScalynAlert !== 'undefined') ScalynAlert.warning('Nothing to Fix', 'No auto-fixable issues found.');
                return;
            }

            if (typeof ScalynAlert !== 'undefined') {
                ScalynAlert.confirm(
                    'Auto Fix All',
                    'This will automatically fix ' + checkIds.length + ' issues. Continue?',
                    'Fix All'
                ).then(function (result) {
                    if (!result.isConfirmed) return;
                    executeAutoFixAll(btn, checkIds);
                });
            } else {
                executeAutoFixAll(btn, checkIds);
            }
        });
    }

    function executeAutoFixAll(btn, checkIds) {
        btn.disabled = true;
        var completed = 0;
        var total = checkIds.length;

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Fixing issues…',
                text: '0/' + total + ' completed',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: function () { Swal.showLoading(); },
                customClass: { popup: 'scalyn-swal-popup' },
            });
        }

        function fixNext(index) {
            if (index >= checkIds.length) {
                if (typeof Swal !== 'undefined' && Swal.isVisible()) {
                    Swal.update({ title: 'Rescanning…', text: 'Verifying fixes.' });
                }
                fetchApi('launch/scan', { method: 'POST' })
                    .then(function () {
                        if (typeof ScalynAlert !== 'undefined') {
                            ScalynAlert.close();
                            ScalynAlert.toast('Fixed ' + completed + ' issues');
                        }
                        window.location.reload();
                    });
                return;
            }

            var fixPayload = { check_id: checkIds[index] };
            if (checkIds[index] === 'timezone_set') {
                try { fixPayload.content = Intl.DateTimeFormat().resolvedOptions().timeZone; } catch (e) {}
            }

            fetchApi('launch/auto-fix', {
                method: 'POST',
                body: JSON.stringify(fixPayload),
            })
            .then(function () { ++completed; })
            .catch(function () { ++completed; })
            .finally(function () {
                if (typeof Swal !== 'undefined' && Swal.isVisible()) {
                    Swal.update({ text: completed + '/' + total + ' completed' });
                }
                fixNext(index + 1);
            });
        }

        fixNext(0);
    }

    /**
     * Handle individual Rescan buttons on dashboard and launch pages.
     */
    function initRescanButtons() {
        document.addEventListener('click', function (e) {
            if (!e.target || !e.target.closest) return;
            var btn = e.target.closest('.scalyn-rescan');
            if (!btn) return;

            var postId = btn.getAttribute('data-post-id');
            if (!postId) return;

            btn.disabled = true;
            var origText = btn.textContent;
            btn.textContent = 'Scanning...';

            fetchApi('scan/' + postId, { method: 'POST' })
                .then(function () {
                    if (typeof ScalynAlert !== 'undefined') {
                        ScalynAlert.toast('Scan complete');
                    }
                    window.location.reload();
                })
                .catch(function (err) {
                    if (typeof ScalynAlert !== 'undefined') {
                        ScalynAlert.error('Error', err.message || 'Scan failed.');
                    }
                    btn.disabled = false;
                    btn.textContent = origText;
                });
        });
    }

    /**
     * Handle "Ignore Check" buttons on launch checklist page.
     */
    function initIgnoreCheck() {
        document.addEventListener('click', function (e) {
            if (!e.target || !e.target.closest) return;
            var btn = e.target.closest('.scalyn-ignore-check');
            if (!btn) return;

            var checkId = btn.getAttribute('data-check-id');
            if (!checkId) return;

            var postId = btn.getAttribute('data-post-id') || '0';

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Ignore This Check',
                    text: 'Provide a reason (optional):',
                    input: 'text',
                    inputPlaceholder: 'Reason (optional)',
                    showCancelButton: true,
                    confirmButtonText: 'Ignore',
                    confirmButtonColor: '#F59E0B',
                }).then(function (result) {
                    if (!result.isConfirmed) return;

                    var isLaunch = !!document.getElementById('scalyn-launch-scan');
                    fetchApi('ignore', {
                        method: 'POST',
                        body: JSON.stringify({
                            type: parseInt(postId, 10) > 0 ? 'check' : 'global',
                            check_id: checkId,
                            post_id: parseInt(postId, 10) > 0 ? parseInt(postId, 10) : null,
                            reason: result.value || '',
                            context: isLaunch ? 'launch' : 'audit',
                        }),
                    }).then(function (response) {
                        if (response.success) {
                            ScalynAlert && ScalynAlert.toast('Check ignored — recalculating...');
                            // Re-run launch scan to recalculate scores.
                            return fetchApi('launch/scan', { method: 'POST' });
                        }
                    }).then(function () {
                        window.location.reload();
                    }).catch(function (err) {
                        ScalynAlert && ScalynAlert.error('Error', err.message || 'Failed to ignore check.');
                    });
                });
            }
        });
    }

    /**
     * Handle "Restore" (remove ignore) buttons.
     */
    function initRemoveIgnore() {
        document.addEventListener('click', function (e) {
            if (!e.target || !e.target.closest) return;
            var btn = e.target.closest('.scalyn-remove-ignore');
            if (!btn) return;

            var ruleId = btn.getAttribute('data-rule-id');
            if (!ruleId) return;

            if (typeof ScalynAlert !== 'undefined') {
                ScalynAlert.confirm('Restore Check', 'This check will be evaluated again.', 'Restore')
                    .then(function (result) {
                        if (!result.isConfirmed) return;
                        fetchApi('ignore/' + ruleId, { method: 'DELETE' })
                            .then(function (response) {
                                if (response.success) {
                                    ScalynAlert.toast('Check restored — recalculating...');
                                    return fetchApi('launch/scan', { method: 'POST' });
                                }
                            })
                            .then(function () {
                                window.location.reload();
                            })
                            .catch(function (err) {
                                ScalynAlert.error('Error', err.message || 'Failed to restore check.');
                            });
                    });
            }
        });
    }

    /**
     * Handle "Auto Fix" buttons on launch checklist.
     */
    function initLaunchAutoFix() {
        document.addEventListener('click', function (e) {
            if (!e.target || !e.target.closest) return;
            var btn = e.target.closest('.scalyn-launch-auto-fix');
            if (!btn) return;

            var checkId = btn.getAttribute('data-check-id');
            if (!checkId) return;

            var label = btn.getAttribute('title') || 'Apply fix';

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Auto Configure',
                    text: label + '?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Apply',
                    confirmButtonColor: '#4a90d9',
                    customClass: { popup: 'scalyn-swal-popup' },
                }).then(function (result) {
                    if (!result.isConfirmed) return;
                    performAutoFix(btn, checkId);
                });
            } else {
                if (confirm(label + '?')) {
                    performAutoFix(btn, checkId);
                }
            }
        });
    }

    /**
     * Call the auto-fix endpoint, then re-scan.
     */
    function performAutoFix(btn, checkId) {
        btn.disabled = true;
        var origHtml = btn.innerHTML;
        btn.textContent = 'Fixing...';

        var payload = { check_id: checkId };

        // For timezone fix, detect the browser timezone and send it as content.
        if (checkId === 'timezone_set') {
            try {
                payload.content = Intl.DateTimeFormat().resolvedOptions().timeZone;
            } catch (e) {
                // Intl API not available — the server will return an error.
            }
        }

        fetchApi('launch/auto-fix', {
            method: 'POST',
            body: JSON.stringify(payload),
        })
            .then(function (response) {
                if (response.success && response.data) {
                    if (typeof ScalynAlert !== 'undefined') {
                        ScalynAlert.toast(response.data.message || 'Fixed');
                    }
                    // Re-run launch scan to update results.
                    return fetchApi('launch/scan', { method: 'POST' });
                }
            })
            .then(function () {
                window.location.reload();
            })
            .catch(function (err) {
                if (typeof ScalynAlert !== 'undefined') {
                    ScalynAlert.error('Auto Fix Failed', err.message || 'Could not apply fix.');
                }
                btn.disabled = false;
                btn.innerHTML = origHtml;
            });
    }

    /**
     * Handle llms.txt Generate / Edit button — opens a textarea modal.
     */
    function initLlmsTxtEditor() {
        document.addEventListener('click', function (e) {
            if (!e.target || !e.target.closest) return;
            var btn = e.target.closest('.scalyn-llms-txt-editor');
            if (!btn) return;

            btn.disabled = true;
            var origHtml = btn.innerHTML;
            btn.textContent = 'Loading...';

            // Fetch current or default content.
            fetchApi('launch/llms_txt', { method: 'GET' })
                .then(function (response) {
                    btn.disabled = false;
                    btn.innerHTML = origHtml;

                    var content = (response.data && response.data.content) || '';
                    var exists = response.data && response.data.exists;

                    if (typeof Swal === 'undefined') {
                        alert('SweetAlert2 is required for the llms.txt editor.');
                        return;
                    }

                    Swal.fire({
                        title: exists ? 'Edit llms.txt' : 'Generate llms.txt',
                        html: '<textarea id="scalyn-llms-txt-content" style="width:100%;height:300px;font-family:monospace;font-size:13px;resize:vertical;padding:8px;border:1px solid #d1d5db;border-radius:4px;">'
                            + escapeHtml(content) + '</textarea>',
                        width: 640,
                        showCancelButton: true,
                        confirmButtonText: 'Save',
                        confirmButtonColor: '#4a90d9',
                        customClass: { popup: 'scalyn-swal-popup' },
                        preConfirm: function () {
                            var textarea = document.getElementById('scalyn-llms-txt-content');
                            var val = textarea ? textarea.value : '';
                            if (!val.trim()) {
                                Swal.showValidationMessage('Content cannot be empty.');
                                return false;
                            }
                            return val;
                        },
                    }).then(function (result) {
                        if (!result.isConfirmed || !result.value) return;

                        if (typeof ScalynAlert !== 'undefined') {
                            ScalynAlert.loading('Saving llms.txt...');
                        }

                        fetchApi('launch/auto-fix', {
                            method: 'POST',
                            body: JSON.stringify({
                                check_id: 'llms_txt',
                                content: result.value,
                            }),
                        })
                            .then(function (res) {
                                if (res.success) {
                                    if (typeof ScalynAlert !== 'undefined') {
                                        ScalynAlert.close();
                                        ScalynAlert.toast(res.data.message || 'llms.txt saved');
                                    }
                                    return fetchApi('launch/scan', { method: 'POST' });
                                }
                            })
                            .then(function () {
                                window.location.reload();
                            })
                            .catch(function (err) {
                                if (typeof ScalynAlert !== 'undefined') {
                                    ScalynAlert.close();
                                    ScalynAlert.error('Save Failed', err.message || 'Could not save llms.txt.');
                                }
                            });
                    });
                })
                .catch(function (err) {
                    btn.disabled = false;
                    btn.innerHTML = origHtml;
                    if (typeof ScalynAlert !== 'undefined') {
                        ScalynAlert.error('Error', err.message || 'Could not load llms.txt content.');
                    }
                });
        });
    }

    /**
     * Handle single "Generate with AI" button — generates all content in one call,
     * saves to option, and reloads to show inline panels.
     */
    /**
     * Handle Local Business Schema Edit button — opens a form modal.
     */
    function initLocalBizEditor() {
        document.addEventListener('click', function (e) {
            if (!e.target || !e.target.closest) return;
            var btn = e.target.closest('.scalyn-local-biz-editor');
            if (!btn) return;

            btn.disabled = true;
            var origHtml = btn.innerHTML;
            btn.textContent = 'Loading...';

            fetchApi('launch/local_business', { method: 'GET' })
                .then(function (response) {
                    btn.disabled = false;
                    btn.innerHTML = origHtml;

                    var data = (response.data && response.data.data) || {};

                    if (typeof Swal === 'undefined') return;

                    var formHtml = '<div style="text-align:left;">'
                        + '<label style="display:block;margin-bottom:12px;">'
                        + '<span style="font-weight:600;font-size:13px;">Business Type</span>'
                        + '<input id="scalyn-lb-type" class="swal2-input" value="' + escapeHtml(data.type || 'LocalBusiness') + '" placeholder="e.g. Restaurant, Dentist, LocalBusiness" style="margin-top:4px;">'
                        + '</label>'
                        + '<label style="display:block;margin-bottom:12px;">'
                        + '<span style="font-weight:600;font-size:13px;">Business Name</span>'
                        + '<input id="scalyn-lb-name" class="swal2-input" value="' + escapeHtml(data.name || '') + '" style="margin-top:4px;">'
                        + '</label>'
                        + '<label style="display:block;margin-bottom:12px;">'
                        + '<span style="font-weight:600;font-size:13px;">Description</span>'
                        + '<textarea id="scalyn-lb-desc" class="swal2-textarea" style="margin-top:4px;height:60px;">' + escapeHtml(data.description || '') + '</textarea>'
                        + '</label>'
                        + '<label style="display:block;margin-bottom:12px;">'
                        + '<span style="font-weight:600;font-size:13px;">Phone</span>'
                        + '<input id="scalyn-lb-phone" class="swal2-input" value="' + escapeHtml(data.phone || '') + '" placeholder="+1234567890" style="margin-top:4px;">'
                        + '</label>'
                        + '<label style="display:block;margin-bottom:12px;">'
                        + '<span style="font-weight:600;font-size:13px;">Email</span>'
                        + '<input id="scalyn-lb-email" class="swal2-input" value="' + escapeHtml(data.email || '') + '" style="margin-top:4px;">'
                        + '</label>'
                        + '</div>';

                    Swal.fire({
                        title: 'Local Business Schema',
                        html: formHtml,
                        width: 520,
                        showCancelButton: true,
                        confirmButtonText: 'Save',
                        confirmButtonColor: '#4a90d9',
                        customClass: { popup: 'scalyn-swal-popup' },
                        preConfirm: function () {
                            return {
                                type: document.getElementById('scalyn-lb-type').value.trim() || 'LocalBusiness',
                                name: document.getElementById('scalyn-lb-name').value.trim(),
                                description: document.getElementById('scalyn-lb-desc').value.trim(),
                                phone: document.getElementById('scalyn-lb-phone').value.trim(),
                                email: document.getElementById('scalyn-lb-email').value.trim(),
                            };
                        },
                    }).then(function (result) {
                        if (!result.isConfirmed || !result.value) return;

                        ScalynAlert && ScalynAlert.loading('Saving Local Business schema...');

                        fetchApi('launch/auto-fix', {
                            method: 'POST',
                            body: JSON.stringify({
                                check_id: 'local_business_schema',
                                content: JSON.stringify(result.value),
                            }),
                        }).then(function (res) {
                            ScalynAlert && ScalynAlert.close();
                            if (res.success) {
                                ScalynAlert && ScalynAlert.toast(res.data.message || 'Saved');
                                return fetchApi('launch/scan', { method: 'POST' });
                            }
                        }).then(function () {
                            window.location.reload();
                        }).catch(function (err) {
                            ScalynAlert && ScalynAlert.close();
                            ScalynAlert && ScalynAlert.error('Error', err.message || 'Failed to save.');
                        });
                    });
                })
                .catch(function () {
                    btn.disabled = false;
                    btn.innerHTML = origHtml;
                    ScalynAlert && ScalynAlert.error('Error', 'Could not load business data.');
                });
        });
    }

    function initLaunchAiGenerate() {
        var btn = document.getElementById('scalyn-launch-generate-ai');
        if (!btn) return;

        btn.addEventListener('click', function () {
            btn.disabled = true;
            var origHtml = btn.innerHTML;
            btn.innerHTML = '<span class="dashicons dashicons-update spin"></span> Generating...';

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Generating with AI',
                    text: 'This may take a moment...',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: function () { Swal.showLoading(); },
                    customClass: { popup: 'scalyn-swal-popup' },
                });
            }

            fetchApi('launch/ai_generate', {
                method: 'POST',
                body: JSON.stringify({}),
            })
                .then(function (response) {
                    if (typeof ScalynAlert !== 'undefined') {
                        ScalynAlert.close();
                    }

                    if (!response.success || !response.data) {
                        btn.disabled = false;
                        btn.innerHTML = origHtml;
                        ScalynAlert && ScalynAlert.error('AI Failed', 'No content generated.');
                        return;
                    }

                    ScalynAlert && ScalynAlert.toast('AI content generated — review suggestions below each check.');
                    // Reload to show persisted inline panels.
                    window.location.reload();
                })
                .catch(function (err) {
                    btn.disabled = false;
                    btn.innerHTML = origHtml;
                    if (typeof ScalynAlert !== 'undefined') {
                        ScalynAlert.close();
                    }
                    ScalynAlert && ScalynAlert.error('AI Generation Failed', err.message || 'Unknown error.');
                });
        });
    }

    /**
     * Handle inline AI panel "Apply" buttons.
     */
    function initLaunchAiApply() {
        document.addEventListener('click', function (e) {
            if (!e.target || !e.target.closest) return;
            var btn = e.target.closest('.scalyn-launch-ai-apply');
            if (!btn) return;

            var aiKey   = btn.getAttribute('data-ai-key');
            var checkId = btn.getAttribute('data-check-id');
            if (!aiKey || !checkId) return;

            // Get the content to apply.
            var content = '';
            if (aiKey === 'taglines') {
                var selected = document.querySelector('input[name="scalyn-launch-ai-tagline"]:checked');
                content = selected ? selected.value : '';
            } else if (aiKey === 'cornerstone') {
                // Checkboxes — collect checked page titles as JSON array.
                var checked = document.querySelectorAll('input[name="scalyn-launch-ai-cornerstone[]"]:checked');
                var titles = [];
                checked.forEach(function (cb) { titles.push(cb.value); });
                content = titles.length ? JSON.stringify(titles) : '';
            } else if (aiKey === 'local_business') {
                // Inline form fields.
                var panel = btn.closest('.scalyn-launch-ai-panel');
                var form = panel ? panel.querySelector('.scalyn-lb-inline-form') : null;
                if (form) {
                    content = JSON.stringify({
                        type: (form.querySelector('[name="scalyn-lb-type"]') || {}).value || 'LocalBusiness',
                        name: (form.querySelector('[name="scalyn-lb-name"]') || {}).value || '',
                        description: (form.querySelector('[name="scalyn-lb-desc"]') || {}).value || '',
                        phone: (form.querySelector('[name="scalyn-lb-phone"]') || {}).value || '',
                        email: (form.querySelector('[name="scalyn-lb-email"]') || {}).value || '',
                    });
                }
            } else {
                var panel = btn.closest('.scalyn-launch-ai-panel');
                var textarea = panel ? panel.querySelector('.scalyn-ai-inline-result__textarea') : null;
                content = textarea ? textarea.value.trim() : '';
            }

            if (!content) {
                ScalynAlert && ScalynAlert.error('Error', 'No content to apply.');
                return;
            }

            btn.disabled = true;
            var origHtml = btn.innerHTML;
            btn.textContent = 'Applying...';

            fetchApi('launch/auto-fix', {
                method: 'POST',
                body: JSON.stringify({ check_id: checkId, content: content }),
            })
                .then(function (res) {
                    if (res.success) {
                        ScalynAlert && ScalynAlert.toast(res.data.message || 'Applied');
                        return fetchApi('launch/scan', { method: 'POST' });
                    }
                })
                .then(function () {
                    window.location.reload();
                })
                .catch(function (err) {
                    btn.disabled = false;
                    btn.innerHTML = origHtml;
                    ScalynAlert && ScalynAlert.error('Apply Failed', err.message || 'Could not apply.');
                });
        });
    }

    /**
     * Handle inline AI panel "Copy" buttons.
     */
    function initLaunchAiCopy() {
        document.addEventListener('click', function (e) {
            if (!e.target || !e.target.closest) return;
            var btn = e.target.closest('.scalyn-launch-ai-copy');
            if (!btn) return;

            var aiKey = btn.getAttribute('data-ai-key');
            var panel = btn.closest('.scalyn-launch-ai-panel');
            var text  = '';

            if (aiKey === 'taglines') {
                var selected = panel ? panel.querySelector('input[name="scalyn-launch-ai-tagline"]:checked') : null;
                text = selected ? selected.value : '';
            } else if (aiKey === 'cornerstone') {
                var checked = panel ? panel.querySelectorAll('input[name="scalyn-launch-ai-cornerstone[]"]:checked') : [];
                var titles = [];
                checked.forEach(function (cb) { titles.push(cb.value); });
                text = titles.join(', ');
            } else if (aiKey === 'local_business') {
                var form = panel ? panel.querySelector('.scalyn-lb-inline-form') : null;
                if (form) {
                    text = 'Type: ' + ((form.querySelector('[name="scalyn-lb-type"]') || {}).value || '')
                        + '\nName: ' + ((form.querySelector('[name="scalyn-lb-name"]') || {}).value || '')
                        + '\nDescription: ' + ((form.querySelector('[name="scalyn-lb-desc"]') || {}).value || '')
                        + '\nPhone: ' + ((form.querySelector('[name="scalyn-lb-phone"]') || {}).value || '')
                        + '\nEmail: ' + ((form.querySelector('[name="scalyn-lb-email"]') || {}).value || '');
                }
            } else {
                var textarea = panel ? panel.querySelector('.scalyn-ai-inline-result__textarea') : null;
                text = textarea ? textarea.value.trim() : '';
            }

            if (text && navigator.clipboard) {
                navigator.clipboard.writeText(text).then(function () {
                    ScalynAlert && ScalynAlert.toast('Copied to clipboard');
                });
            }
        });
    }

    /**
     * Handle inline AI panel "Regenerate" buttons — regenerates just that one check.
     */
    function initLaunchAiRegenerate() {
        document.addEventListener('click', function (e) {
            if (!e.target || !e.target.closest) return;
            var btn = e.target.closest('.scalyn-launch-ai-regenerate');
            if (!btn) return;

            var aiKey = btn.getAttribute('data-ai-key');
            if (!aiKey) return;

            btn.disabled = true;
            var origHtml = btn.innerHTML;
            btn.innerHTML = '<span class="dashicons dashicons-update spin"></span> Regenerating...';

            fetchApi('launch/ai_generate', {
                method: 'POST',
                body: JSON.stringify({ type: aiKey }),
            })
                .then(function (response) {
                    if (response.success) {
                        ScalynAlert && ScalynAlert.toast('Regenerated — reloading...');
                        window.location.reload();
                    } else {
                        btn.disabled = false;
                        btn.innerHTML = origHtml;
                        ScalynAlert && ScalynAlert.error('Failed', 'AI regeneration failed.');
                    }
                })
                .catch(function (err) {
                    btn.disabled = false;
                    btn.innerHTML = origHtml;
                    ScalynAlert && ScalynAlert.error('Error', err.message || 'Regeneration failed.');
                });
        });
    }


    /**
     * Handle "Generate with AI" for favicon.
     */
    function initGenerateFavicon() {
        document.addEventListener('click', function (e) {
            // Generate button.
            var genBtn = e.target.closest('.scalyn-generate-favicon');
            if (genBtn) {
                handleFaviconGenerate(genBtn);
                return;
            }

            // Regenerate button.
            var regenBtn = e.target.closest('.scalyn-favicon-regenerate');
            if (regenBtn) {
                var origGenBtn = regenBtn.closest('.scalyn-check-item').querySelector('.scalyn-generate-favicon');
                if (origGenBtn) handleFaviconGenerate(origGenBtn);
                return;
            }

            // Apply button (inline after generation).
            var applyBtn = e.target.closest('.scalyn-favicon-apply');
            if (applyBtn) {
                var attachmentId = applyBtn.getAttribute('data-attachment-id');
                if (!attachmentId) return;
                applyFavicon(applyBtn, parseInt(attachmentId, 10));
                return;
            }

            // Apply selected (from radio list).
            var applySelectedBtn = e.target.closest('.scalyn-favicon-apply-selected');
            if (applySelectedBtn) {
                var selected = document.querySelector('.scalyn-favicon-radio:checked');
                if (!selected) {
                    ScalynAlert && ScalynAlert.error('Error', 'Select a favicon first.');
                    return;
                }
                var currentId = applySelectedBtn.getAttribute('data-current');
                if (selected.value === currentId) {
                    ScalynAlert && ScalynAlert.toast('This favicon is already active.');
                    return;
                }
                applyFavicon(applySelectedBtn, parseInt(selected.value, 10));
                return;
            }

            // Radio change — update selected class (same pattern as featured image).
            var radio = e.target.closest('.scalyn-favicon-radio');
            if (radio) {
                var panel = radio.closest('.scalyn-favicon-preview');
                if (!panel) return;
                panel.querySelectorAll('.scalyn-fi-option').forEach(function (opt) {
                    opt.classList.toggle('selected', opt.contains(radio));
                });
                return;
            }
        });

        function applyFavicon(btn, attachmentId) {
            btn.disabled = true;
            var origText = btn.textContent;
            btn.textContent = 'Applying...';

            fetchApi('ai/generate-favicon', {
                method: 'POST',
                body: JSON.stringify({ apply: true, attachment_id: attachmentId }),
            }).then(function () {
                ScalynAlert && ScalynAlert.toast('Favicon set! Rescanning...');
                var scanBtn = document.getElementById('scalyn-launch-scan');
                if (scanBtn) scanBtn.click();
            }).catch(function (err) {
                ScalynAlert && ScalynAlert.error('Error', err.message || 'Failed to apply favicon.');
                btn.disabled = false;
                btn.textContent = origText;
            });
        }

        function handleFaviconGenerate(btn) {
            var checkItem = btn.closest('.scalyn-check-item');
            if (!checkItem) return;

            btn.disabled = true;
            var origHtml = btn.innerHTML;
            btn.innerHTML = '<span class="dashicons dashicons-update spin" aria-hidden="true"></span> Generating...';

            if (typeof ScalynAlert !== 'undefined') {
                ScalynAlert.loading('Generating favicon with AI...');
            }

            fetchApi('ai/generate-favicon', {
                method: 'POST',
                body: JSON.stringify({ apply: false }),
            })
                .then(function (response) {
                    ScalynAlert && ScalynAlert.close();
                    var data = response.data || response;

                    if (!data.url || !data.attachment_id) {
                        ScalynAlert && ScalynAlert.error('Error', 'Failed to generate favicon.');
                        btn.disabled = false;
                        btn.innerHTML = origHtml;
                        return;
                    }

                    var existingPanel = checkItem.querySelector('.scalyn-favicon-preview');
                    var filename = data.filename || ('favicon-' + data.attachment_id + '.png');

                    if (existingPanel) {
                        // Add to existing grid.
                        var grid = existingPanel.querySelector('.scalyn-fi-grid');
                        if (grid) {
                            // Deselect all.
                            existingPanel.querySelectorAll('.scalyn-favicon-radio').forEach(function (r) { r.checked = false; });
                            existingPanel.querySelectorAll('.scalyn-fi-option').forEach(function (o) { o.classList.remove('selected'); });

                            var newLabel = document.createElement('label');
                            newLabel.className = 'scalyn-fi-option selected';
                            newLabel.innerHTML = '<img src="' + data.url + '" alt="' + filename + '" />' +
                                '<div class="scalyn-fi-option-footer">' +
                                    '<input type="radio" name="scalyn-favicon-choice" value="' + data.attachment_id + '" checked class="scalyn-favicon-radio">' +
                                    '<span>' + filename + '</span>' +
                                '</div>';
                            grid.appendChild(newLabel);
                        }
                    } else {
                        // Create new panel matching featured image design.
                        var panel = document.createElement('div');
                        panel.className = 'scalyn-ai-featured-image-results scalyn-favicon-preview';
                        panel.setAttribute('data-check-id', 'favicon_exists');

                        panel.innerHTML = '<div class="scalyn-ai-inline-result">' +
                            '<div class="scalyn-ai-inline-result__content">' +
                                '<span class="scalyn-ai-inline-result__label">AI Generated Favicons</span>' +
                                '<div class="scalyn-fi-grid">' +
                                    '<label class="scalyn-fi-option selected">' +
                                        '<img src="' + data.url + '" alt="' + filename + '" />' +
                                        '<div class="scalyn-fi-option-footer">' +
                                            '<input type="radio" name="scalyn-favicon-choice" value="' + data.attachment_id + '" checked class="scalyn-favicon-radio">' +
                                            '<span>' + filename + '</span>' +
                                        '</div>' +
                                    '</label>' +
                                '</div>' +
                                '<span class="scalyn-ai-inline-result__meta">' + (data.provider || 'OpenAI') + '</span>' +
                            '</div>' +
                            '<div class="scalyn-ai-inline-result__actions">' +
                                '<button type="button" class="scalyn-btn scalyn-btn--small scalyn-btn--secondary scalyn-favicon-apply-selected" data-current="0">' +
                                    '<span class="dashicons dashicons-yes" aria-hidden="true"></span> Apply' +
                                '</button>' +
                                '<button type="button" class="scalyn-btn scalyn-btn--small scalyn-generate-favicon" data-check-id="favicon_exists">' +
                                    '<span class="dashicons dashicons-update" aria-hidden="true"></span> Regenerate' +
                                '</button>' +
                            '</div>' +
                        '</div>';

                        checkItem.appendChild(panel);
                    }

                    btn.disabled = false;
                    btn.innerHTML = origHtml;
                })
                .catch(function (err) {
                    ScalynAlert && ScalynAlert.close();
                    ScalynAlert && ScalynAlert.error('Generation Failed', err.message || 'Failed to generate favicon.');
                    btn.disabled = false;
                    btn.innerHTML = origHtml;
                });
        }
    }

    function init() {
        // Animate existing score circles rendered server-side.
        animateAllScoreCircles();

        // Fetch and update project scores from the API.
        loadProjectScores();

        // Bind event handlers.
        handleScanAll();
        initAuditLinks();
        initRescanButtons();
        initTooltips();
        initLaunchScan();
        initAutoFixAll();
        initIgnoreCheck();
        initRemoveIgnore();
        initLaunchAutoFix();
        initLlmsTxtEditor();
        initLocalBizEditor();
        initLaunchAiGenerate();
        initLaunchAiApply();
        initLaunchAiCopy();
        initLaunchAiRegenerate();
        initGenerateFavicon();

        // Start auto-refresh.
        startAutoRefresh();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();

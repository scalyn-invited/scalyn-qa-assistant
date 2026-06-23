/**
 * Toolbar JS.
 *
 * Lightweight script for the front-end admin bar toolbar node.
 * Handles rescan, dropdown toggle, outside-click closing, and
 * score badge color updates.
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
     * Get a status icon character.
     *
     * @param {string} status - 'green', 'yellow', or 'red'.
     * @returns {string}
     */
    function getStatusIcon(status) {
        switch (status) {
            case 'green': return '\u2713';   // checkmark
            case 'yellow': return '!';
            case 'red': return '\u00D7';     // multiplication sign (x)
            default: return '?';
        }
    }

    // -------------------------------------------------------------------------
    // Rescan
    // -------------------------------------------------------------------------

    /**
     * Initialize rescan button handler.
     */
    function initRescan() {
        document.addEventListener('click', function (e) {
            // Match the rescan link in the toolbar.
            var link = e.target.closest('#wp-admin-bar-scalyn-qa-rescan a, .scalyn-qa-rescan-link');
            if (!link) return;

            e.preventDefault();
            e.stopPropagation();

            var pid = link.getAttribute('data-post-id') || postId;
            if (!pid) return;

            // Update link text to show scanning state.
            var originalText = link.textContent;
            link.textContent = 'Scanning...';
            link.style.pointerEvents = 'none';

            fetchApi('scan/' + pid, { method: 'POST' })
                .then(function (response) {
                    if (response.success && response.data) {
                        updateToolbarScore(response.data);
                        link.textContent = 'Scan Complete!';
                        setTimeout(function () {
                            link.textContent = originalText.indexOf('Scan Now') !== -1 ? 'Rescan Page' : originalText;
                        }, 2000);
                    }
                })
                .catch(function (err) {
                    console.error('Scalyn QA: Toolbar rescan failed.', err);
                    link.textContent = 'Scan Failed';
                    setTimeout(function () {
                        link.textContent = originalText;
                    }, 2000);
                })
                .finally(function () {
                    link.style.pointerEvents = '';
                });
        });
    }

    /**
     * Update toolbar score display after a rescan.
     *
     * @param {Object} data - Scan result data.
     */
    function updateToolbarScore(data) {
        var scores = data.scores || {};
        var overallScore = scores.overall || 0;
        var status = scores.status || getStatus(overallScore);

        // Update the parent toolbar node.
        var parentLink = document.querySelector('#wp-admin-bar-scalyn-qa-score > a, #wp-admin-bar-scalyn-qa-score .ab-item');
        if (parentLink) {
            var badge = parentLink.querySelector('.scalyn-qa-toolbar-badge');
            var label = parentLink.querySelector('.scalyn-qa-toolbar-label');

            if (badge) {
                badge.className = 'scalyn-qa-toolbar-badge scalyn-qa-badge--' + status;
                badge.textContent = getStatusIcon(status);
            }

            if (label) {
                label.textContent = 'QA: ' + overallScore + '%';
            }

            // Update title attribute.
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

            var parentNode = document.getElementById('wp-admin-bar-scalyn-qa-score');
            if (parentNode) {
                parentNode.setAttribute('title', 'QA Score: ' + overallScore + '% \u2014 ' + issueCount + ' issues');
                parentNode.classList.remove('scalyn-qa-unscanned');
            }
        }

        // Update the score details child node.
        var detailsLink = document.querySelector('#wp-admin-bar-scalyn-qa-score-details .ab-item');
        if (detailsLink) {
            detailsLink.textContent = 'SEO: ' + (scores.seo || 0) + '% | Content: ' + (scores.content || 0) + '% | Func: ' + (scores.functionality || 0) + '%';
        }

        // Update the issues child node.
        var issuesLink = document.querySelector('#wp-admin-bar-scalyn-qa-issues .ab-item');
        if (issuesLink) {
            var count = 0;
            if (data.results) {
                Object.keys(data.results).forEach(function (cat) {
                    var items = data.results[cat];
                    if (Array.isArray(items)) {
                        items.forEach(function (item) {
                            if (item.status === 'fail' || item.status === 'warning') {
                                count++;
                            }
                        });
                    }
                });
            }

            if (count > 0) {
                issuesLink.textContent = count + ' issue' + (count !== 1 ? 's' : '') + ' found';
            } else {
                issuesLink.textContent = 'No issues found';
            }
        }

        // Update score badge color.
        updateBadgeColor(status);
    }

    /**
     * Update the toolbar badge color based on status.
     *
     * @param {string} status - 'green', 'yellow', or 'red'.
     */
    function updateBadgeColor(status) {
        var badge = document.querySelector('.scalyn-qa-toolbar-badge');
        if (!badge) return;

        badge.classList.remove(
            'scalyn-qa-badge--green',
            'scalyn-qa-badge--yellow',
            'scalyn-qa-badge--red',
            'scalyn-qa-badge--gray'
        );
        badge.classList.add('scalyn-qa-badge--' + status);
    }

    // -------------------------------------------------------------------------
    // Dropdown Toggle
    // -------------------------------------------------------------------------

    /**
     * Initialize dropdown panel toggle behavior.
     * The WordPress admin bar has built-in hover behavior, but we add
     * click toggle for improved mobile/touch support.
     */
    function initDropdownToggle() {
        var toolbarNode = document.getElementById('wp-admin-bar-scalyn-qa-score');
        if (!toolbarNode) return;

        var parentLink = toolbarNode.querySelector('.ab-item');
        if (!parentLink) return;

        parentLink.addEventListener('click', function (e) {
            // Don't interfere if it's a real link to the audit page.
            var href = parentLink.getAttribute('href');
            if (href && href !== '#' && href.indexOf('scalyn-qa-audits') !== -1) {
                return; // Let the browser navigate.
            }

            e.preventDefault();
            toolbarNode.classList.toggle('hover');
        });
    }

    /**
     * Close dropdown when clicking outside.
     */
    function initOutsideClickClose() {
        document.addEventListener('click', function (e) {
            var toolbarNode = document.getElementById('wp-admin-bar-scalyn-qa-score');
            if (!toolbarNode) return;

            if (!toolbarNode.contains(e.target)) {
                toolbarNode.classList.remove('hover');
            }
        });
    }

    // -------------------------------------------------------------------------
    // Initialization
    // -------------------------------------------------------------------------

    /**
     * Initialize toolbar JS.
     */
    function init() {
        postId = scalynQA.currentPostId || null;

        initRescan();
        initDropdownToggle();
        initOutsideClickClose();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();

/**
 * Admin Settings JS.
 *
 * Handles settings page interactions: general settings save, AI provider
 * configuration, template management, and setup wizard actions.
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

    // -------------------------------------------------------------------------
    // General Tab
    // -------------------------------------------------------------------------

    /**
     * Initialize the general settings tab.
     */
    function initGeneralTab() {
        var form = document.getElementById('scalyn-settings-form');
        if (!form) return;

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            saveGeneralSettings(form);
        });

        // Also handle the save button directly if it exists.
        var saveBtn = document.getElementById('scalyn-save-general');
        if (saveBtn) {
            saveBtn.addEventListener('click', function (e) {
                e.preventDefault();
                saveGeneralSettings(form);
            });
        }
    }

    /**
     * Collect form values and save general settings via REST.
     *
     * @param {HTMLFormElement} form - The settings form element.
     */
    function saveGeneralSettings(form) {
        var formData = new FormData(form);

        // Collect values.
        var settings = {};

        // Auto-scan on save (checkbox).
        settings.auto_scan_on_save = formData.has('auto_scan_on_save');

        // Post types (checkboxes).
        var postTypes = formData.getAll('post_types[]');
        settings.post_types = postTypes.length > 0 ? postTypes : ['post', 'page'];

        // Score thresholds.
        var scoreGreen = parseInt(formData.get('score_green'), 10);
        var scoreYellow = parseInt(formData.get('score_yellow'), 10);

        // Validation: green must be greater than yellow.
        if (!isNaN(scoreGreen) && !isNaN(scoreYellow) && scoreGreen <= scoreYellow) {
            if (typeof ScalynAlert !== 'undefined') {
                ScalynAlert.error(
                    'Validation Error',
                    'Green threshold must be greater than yellow threshold.'
                );
            }
            return;
        }

        settings.green_threshold = isNaN(scoreGreen) ? 80 : Math.min(100, Math.max(0, scoreGreen));
        settings.yellow_threshold = isNaN(scoreYellow) ? 50 : Math.min(100, Math.max(0, scoreYellow));

        // Link check settings.
        var linkTimeout = parseInt(formData.get('link_timeout'), 10);
        settings.link_timeout = isNaN(linkTimeout) ? 10 : Math.min(30, Math.max(1, linkTimeout));

        var linkCacheHours = parseInt(formData.get('link_cache_hours'), 10);
        settings.link_cache_hours = isNaN(linkCacheHours) ? 24 : Math.min(168, Math.max(1, linkCacheHours));

        // Show loading.
        var statusEl = document.getElementById('scalyn-general-status');
        if (statusEl) statusEl.textContent = 'Saving...';

        fetchApi('settings', {
            method: 'POST',
            body: JSON.stringify(settings),
        })
            .then(function (response) {
                if (response.success) {
                    if (statusEl) statusEl.textContent = '';
                    if (typeof ScalynAlert !== 'undefined') {
                        ScalynAlert.toast('Settings saved successfully');
                    }
                }
            })
            .catch(function (err) {
                if (statusEl) statusEl.textContent = '';
                if (typeof ScalynAlert !== 'undefined') {
                    ScalynAlert.error('Save Failed', err.message || 'Failed to save settings.');
                }
            });
    }

    // -------------------------------------------------------------------------
    // AI Providers Tab
    // -------------------------------------------------------------------------

    /**
     * Initialize the AI providers tab.
     */
    function initAiProvidersTab() {
        initAiSaveButton();
        initTestApiKey();
        initTogglePasswordVisibility();
        initProviderRoleRadios();
        initAiEnableToggle();
    }

    /**
     * Handle saving AI settings.
     */
    function initAiSaveButton() {
        var saveBtn = document.getElementById('scalyn-save-ai');
        if (!saveBtn) return;

        saveBtn.addEventListener('click', function (e) {
            e.preventDefault();
            saveAiSettings();
        });

        // Also handle form submit if wrapped in a form.
        var form = document.getElementById('scalyn-ai-settings-form');
        if (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                saveAiSettings();
            });
        }
    }

    /**
     * Collect AI provider configs and save via REST.
     */
    function saveAiSettings() {
        var aiConfig = {
            enabled: false,
            primary: '',
            fallback: '',
            providers: {}
        };

        // AI enabled toggle — auto-enable if a provider is active, prevent enable if none.
        var enableToggle = document.querySelector('[name="enable_ai"]');
        var providerActive = hasActiveProvider();

        if (enableToggle && enableToggle.checked && !providerActive) {
            enableToggle.checked = false;
            showAiNotice('AI features disabled — no provider is configured with an API key and active role.', 'warning');
        } else if (enableToggle && !enableToggle.checked && providerActive) {
            enableToggle.checked = true;
            showAiNotice('AI features auto-enabled — a configured provider was detected.', 'success');
        } else {
            hideAiNotice();
        }

        aiConfig.enabled = enableToggle ? enableToggle.checked : false;

        // Provider configs — field names match template: openai_api_key, openai_model, openai_role
        var providerKeys = ['openai', 'claude', 'gemini', 'openrouter', 'custom'];

        providerKeys.forEach(function (provider) {
            var apiKeyInput = document.querySelector('[name="' + provider + '_api_key"]');
            var modelInput = document.querySelector('[name="' + provider + '_model"]');
            var roleInput = document.querySelector('input[name="' + provider + '_role"]:checked');

            var role = roleInput ? roleInput.value : 'disabled';
            var keyValue = apiKeyInput ? apiKeyInput.value.trim() : '';

            // Only send api_key if user typed a new one (not empty with existing key)
            var providerData = {
                model: modelInput ? modelInput.value.trim() : '',
                enabled: role !== 'disabled',
            };

            if (keyValue !== '') {
                providerData.api_key = keyValue;
            }
            // If empty and configured, don't include api_key — server keeps existing

            aiConfig.providers[provider] = providerData;

            if (role === 'primary') {
                aiConfig.primary = provider;
            } else if (role === 'fallback') {
                aiConfig.fallback = provider;
            }
        });

        // Custom endpoint extra fields.
        var endpointInput = document.querySelector('[name="custom_endpoint"]');
        var modelNameInput = document.querySelector('[name="custom_model_name"]');
        var headersInput = document.querySelector('[name="custom_headers"]');

        if (aiConfig.providers.custom) {
            if (endpointInput) aiConfig.providers.custom.endpoint = endpointInput.value.trim();
            if (modelNameInput) aiConfig.providers.custom.model_name = modelNameInput.value.trim();
            if (headersInput) {
                var headersVal = headersInput.value.trim();
                if (headersVal) {
                    try {
                        aiConfig.providers.custom.custom_headers = JSON.parse(headersVal);
                    } catch(e) {
                        aiConfig.providers.custom.custom_headers = {};
                    }
                }
            }
        }

        if (typeof ScalynAlert !== 'undefined') {
            ScalynAlert.loading('Saving AI settings...');
        }

        fetchApi('settings', {
            method: 'POST',
            body: JSON.stringify({ ai_config: aiConfig }),
        })
            .then(function (response) {
                if (typeof ScalynAlert !== 'undefined') {
                    ScalynAlert.close();
                    if (response.success) {
                        ScalynAlert.toast('AI settings saved successfully');
                    }
                }
            })
            .catch(function (err) {
                if (typeof ScalynAlert !== 'undefined') {
                    ScalynAlert.close();
                    ScalynAlert.error('Save Failed', err.message || 'Failed to save AI settings.');
                }
            });
    }

    /**
     * Handle "Test API Key" buttons.
     */
    function initTestApiKey() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.scalyn-test-api-key') || e.target.closest('.scalyn-test-key');
            if (!btn) return;

            var provider = btn.getAttribute('data-provider');
            if (!provider) return;

            // Get the current API key value from the input field
            var keyInput = document.querySelector('[name="' + provider + '_api_key"]');
            var apiKey = keyInput ? keyInput.value.trim() : '';
            var isConfigured = keyInput && keyInput.getAttribute('data-configured') === '1';

            if (!apiKey && !isConfigured) {
                if (typeof ScalynAlert !== 'undefined') {
                    ScalynAlert.warning('No API Key', 'Please enter an API key and save first.');
                }
                return;
            }

            btn.disabled = true;
            var originalText = btn.textContent;

            // If there's a new key, save it first then test
            if (apiKey) {
                btn.textContent = 'Saving & Testing...';
                var modelInput = document.querySelector('[name="' + provider + '_model"]');
                var quickConfig = {
                    enabled: true,
                    primary: provider,
                    fallback: '',
                    providers: {}
                };
                quickConfig.providers[provider] = {
                    api_key: apiKey,
                    model: modelInput ? modelInput.value.trim() : '',
                    enabled: true,
                };

                // Include custom endpoint fields when testing custom provider.
                if (provider === 'custom') {
                    var custEndpoint = document.querySelector('[name="custom_endpoint"]');
                    var custModelName = document.querySelector('[name="custom_model_name"]');
                    var custHeaders = document.querySelector('[name="custom_headers"]');
                    if (custEndpoint) quickConfig.providers.custom.endpoint = custEndpoint.value.trim();
                    if (custModelName) quickConfig.providers.custom.model_name = custModelName.value.trim();
                    if (custHeaders) {
                        var hVal = custHeaders.value.trim();
                        if (hVal) {
                            try { quickConfig.providers.custom.custom_headers = JSON.parse(hVal); } catch(e) { /* ignore */ }
                        }
                    }
                }

                fetchApi('settings', {
                    method: 'POST',
                    body: JSON.stringify({ ai_config: quickConfig }),
                }).then(function() {
                    btn.textContent = 'Testing...';
                    keyInput.value = '';
                    keyInput.setAttribute('data-configured', '1');
                    keyInput.placeholder = 'Key saved \u2014 leave empty to keep current';
                    return fetchApi('ai/test', {
                        method: 'POST',
                        body: JSON.stringify({ provider: provider }),
                    });
                }).then(function (response) {
                    if (response && response.success && response.data) {
                        var data = response.data;
                        if (data.success) {
                            ScalynAlert && ScalynAlert.success('Connection Successful', data.message || 'API key is valid.');
                        } else {
                            ScalynAlert && ScalynAlert.error('Connection Failed', data.message || 'API key test failed.');
                        }
                    }
                }).catch(function (err) {
                    ScalynAlert && ScalynAlert.error('Test Failed', err.message || 'Failed to test connection.');
                }).finally(function () {
                    btn.disabled = false;
                    btn.textContent = originalText;
                });
            } else {
                // Key already saved, just test directly
                btn.textContent = 'Testing...';
                fetchApi('ai/test', {
                    method: 'POST',
                    body: JSON.stringify({ provider: provider }),
                }).then(function (response) {
                    if (response && response.success && response.data) {
                        var data = response.data;
                        if (data.success) {
                            ScalynAlert && ScalynAlert.success('Connection Successful', data.message || 'API key is valid.');
                        } else {
                            ScalynAlert && ScalynAlert.error('Connection Failed', data.message || 'API key test failed.');
                        }
                    }
                }).catch(function (err) {
                    ScalynAlert && ScalynAlert.error('Test Failed', err.message || 'Failed to test connection.');
                }).finally(function () {
                    btn.disabled = false;
                    btn.textContent = originalText;
                });
            }
        });
    }

    /**
     * Toggle password visibility for API key fields.
     */
    function initTogglePasswordVisibility() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.scalyn-toggle-password') || e.target.closest('.scalyn-toggle-visibility');
            if (!btn) return;

            var targetId = btn.getAttribute('data-target');
            var input = targetId ? document.getElementById(targetId) : null;

            if (!input) {
                input = btn.closest('.scalyn-input-group')
                    ? btn.closest('.scalyn-input-group').querySelector('input')
                    : null;
            }

            if (!input) return;

            // If field is empty but key is configured, fetch masked key from server
            if ((!input.value || input.getAttribute('data-showing-mask') === '1') && input.getAttribute('data-configured') === '1') {
                if (input.getAttribute('data-showing-mask') !== '1') {
                    // Show: fetch masked key from server
                    btn.disabled = true;
                    btn.textContent = '...';
                    fetchApi('settings', { method: 'GET' })
                        .then(function (response) {
                            var provider = input.name.replace('_api_key', '');
                            var maskedKey = '';
                            if (response && response.data && response.data.ai_config &&
                                response.data.ai_config.providers && response.data.ai_config.providers[provider]) {
                                maskedKey = response.data.ai_config.providers[provider].api_key || '';
                            }
                            input.type = 'text';
                            input.value = maskedKey || 'Key configured';
                            input.setAttribute('data-showing-mask', '1');
                            btn.textContent = 'Hide';
                            btn.disabled = false;
                        })
                        .catch(function () {
                            btn.textContent = 'Show';
                            btn.disabled = false;
                        });
                } else {
                    // Hide: clear the mask and restore editable state
                    input.type = 'password';
                    input.value = '';
                    input.removeAttribute('data-showing-mask');
                    btn.textContent = 'Show';
                }
                return;
            }

            // Normal toggle for when user is typing a key
            if (input.type === 'password') {
                input.type = 'text';
                btn.textContent = 'Hide';
            } else {
                input.type = 'password';
                btn.textContent = 'Show';
            }
        });
    }

    /**
     * Handle radio button groups for primary/fallback provider roles.
     * Ensures only one primary and one fallback at a time.
     */
    function initProviderRoleRadios() {
        document.addEventListener('change', function (e) {
            var radio = e.target;

            if (radio.name === 'ai_primary_provider') {
                // Deselect from fallback if same provider selected.
                var fallbackRadios = document.querySelectorAll('[name="ai_fallback_provider"]');
                fallbackRadios.forEach(function (r) {
                    if (r.value === radio.value && r.checked) {
                        r.checked = false;
                    }
                });
            }

            if (radio.name === 'ai_fallback_provider') {
                // Deselect from primary if same provider selected.
                var primaryRadios = document.querySelectorAll('[name="ai_primary_provider"]');
                primaryRadios.forEach(function (r) {
                    if (r.value === radio.value && r.checked) {
                        r.checked = false;
                    }
                });
            }
        });
    }

    /**
     * Check if any AI provider has a configured key and an active role (primary/fallback).
     */
    function hasActiveProvider() {
        var providerKeys = ['openai', 'claude', 'gemini', 'openrouter', 'custom'];
        for (var i = 0; i < providerKeys.length; i++) {
            var provider = providerKeys[i];
            var keyInput = document.querySelector('[name="' + provider + '_api_key"]');
            var roleInput = document.querySelector('input[name="' + provider + '_role"]:checked');
            var hasKey = keyInput && (keyInput.value.trim() !== '' || keyInput.getAttribute('data-configured') === '1');
            var hasActiveRole = roleInput && roleInput.value !== 'disabled';
            if (hasKey && hasActiveRole) return true;
        }
        return false;
    }

    /**
     * Show or hide the AI toggle notice.
     */
    function showAiNotice(message, type) {
        var notice = document.getElementById('scalyn-ai-toggle-notice');
        if (!notice) return;
        notice.className = 'scalyn-notice scalyn-notice--' + type;
        notice.textContent = message;
        notice.style.display = '';
    }

    function hideAiNotice() {
        var notice = document.getElementById('scalyn-ai-toggle-notice');
        if (notice) notice.style.display = 'none';
    }

    /**
     * Handle enable/disable AI toggle.
     */
    function initAiEnableToggle() {
        var toggle = document.querySelector('[name="enable_ai"], #scalyn-enable-ai');
        if (!toggle) return;

        toggle.addEventListener('change', function () {
            hideAiNotice();

            if (toggle.checked && !hasActiveProvider()) {
                toggle.checked = false;
                showAiNotice('Cannot enable AI features. Please configure at least one AI provider with an API key and set its role to Primary or Fallback first.', 'warning');
                return;
            }

            var aiFields = document.querySelectorAll('.scalyn-ai-provider-fields');
            aiFields.forEach(function (field) {
                field.style.opacity = toggle.checked ? '1' : '0.5';
                field.style.pointerEvents = toggle.checked ? '' : 'none';
            });
        });
    }

    // -------------------------------------------------------------------------
    // Page Audits Tab
    // -------------------------------------------------------------------------

    function initPageAuditsTab() {
        var form = document.getElementById('scalyn-page-audit-settings-form');
        if (!form) return;

        form.addEventListener('submit', function (e) {
            e.preventDefault();

            var enabledChecks = [];
            form.querySelectorAll('input[name="enabled_checks[]"]:checked').forEach(function (cb) {
                enabledChecks.push(cb.value);
            });

            var payload = {
                enabled_checks: enabledChecks,
            };

            // Image optimization threshold.
            var maxSizeInput = form.querySelector('[name="max_image_file_size"]');
            if (maxSizeInput) {
                var maxSize = parseInt(maxSizeInput.value, 10);
                payload.max_image_file_size = isNaN(maxSize) ? 900 : Math.min(10000, Math.max(1, maxSize));
            }

            fetchApi('settings', {
                method: 'POST',
                body: JSON.stringify({
                    page_audit_settings: payload,
                }),
            }).then(function () {
                ScalynAlert && ScalynAlert.toast('Page audit settings saved');
            }).catch(function (err) {
                ScalynAlert && ScalynAlert.error('Error', err.message || 'Failed to save page audit settings.');
            });
        });
    }

    // -------------------------------------------------------------------------
    // Wizard Tab
    // -------------------------------------------------------------------------

    /**
     * Initialize the wizard tab.
     */
    // -------------------------------------------------------------------------
    // Launch Checklist Settings Tab
    // -------------------------------------------------------------------------

    function initLaunchSettingsTab() {
        var form = document.getElementById('scalyn-launch-settings-form');
        if (!form) return;

        form.addEventListener('submit', function (e) {
            e.preventDefault();

            var thresholds = {
                php_version: (form.querySelector('[name="php_version"]').value || '8.3.14').trim(),
                memory_limit: parseInt(form.querySelector('[name="memory_limit"]').value, 10) || 512,
                max_execution_time: parseInt(form.querySelector('[name="max_execution_time"]').value, 10) || 90,
                max_input_time: parseInt(form.querySelector('[name="max_input_time"]').value, 10) || 90,
                post_max_size: parseInt(form.querySelector('[name="post_max_size"]').value, 10) || 128,
                upload_max_size: parseInt(form.querySelector('[name="upload_max_size"]').value, 10) || 64,
            };

            var enabledChecks = [];
            form.querySelectorAll('input[name="enabled_checks[]"]:checked').forEach(function (cb) {
                enabledChecks.push(cb.value);
            });

            fetchApi('settings', {
                method: 'POST',
                body: JSON.stringify({
                    launch_settings: {
                        thresholds: thresholds,
                        enabled_checks: enabledChecks,
                    },
                }),
            }).then(function () {
                ScalynAlert && ScalynAlert.toast('Launch settings saved');
            }).catch(function (err) {
                ScalynAlert && ScalynAlert.error('Error', err.message || 'Failed to save launch settings.');
            });
        });
    }

    // -------------------------------------------------------------------------
    // Wizard Tab
    // -------------------------------------------------------------------------

    function initWizardTab() {
        initInstallSeoPlugin();
        initActivateSeoPlugin();
        initDismissWizard();
        initResetWizard();
    }

    /**
     * Handle "Install SEO Plugin" button.
     */
    function initInstallSeoPlugin() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.scalyn-install-seo-plugin');
            if (!btn) return;

            var plugin = btn.getAttribute('data-plugin');
            if (!plugin) return;

            if (typeof ScalynAlert !== 'undefined') {
                ScalynAlert.loading('Installing ' + plugin + '...');
            }

            fetchApi('wizard/install', {
                method: 'POST',
                body: JSON.stringify({ plugin: plugin }),
            })
                .then(function (response) {
                    if (typeof ScalynAlert !== 'undefined') {
                        ScalynAlert.close();
                    }

                    if (response.success) {
                        if (typeof ScalynAlert !== 'undefined') {
                            ScalynAlert.success(
                                'Plugin Installed',
                                plugin + ' has been installed and activated successfully.'
                            );
                        }
                        // Update UI to show installed state.
                        btn.textContent = 'Installed';
                        btn.disabled = true;
                        btn.classList.add('scalyn-btn--disabled');
                    }
                })
                .catch(function (err) {
                    if (typeof ScalynAlert !== 'undefined') {
                        ScalynAlert.close();
                        ScalynAlert.error('Installation Failed', err.message || 'Failed to install plugin.');
                    }
                });
        });
    }

    /**
     * Handle "Activate SEO Plugin" button (AJAX, no page redirect).
     */
    function initActivateSeoPlugin() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.scalyn-activate-seo-plugin');
            if (!btn) return;

            var plugin = btn.getAttribute('data-plugin');
            if (!plugin) return;

            btn.disabled = true;
            var originalText = btn.textContent;
            btn.textContent = 'Activating...';

            if (typeof ScalynAlert !== 'undefined') {
                ScalynAlert.loading('Activating plugin...');
            }

            fetchApi('wizard/activate', {
                method: 'POST',
                body: JSON.stringify({ plugin: plugin }),
            })
                .then(function (response) {
                    if (typeof ScalynAlert !== 'undefined') {
                        ScalynAlert.close();
                    }

                    if (response.success) {
                        if (typeof ScalynAlert !== 'undefined') {
                            ScalynAlert.success(
                                'Plugin Activated',
                                response.data.message || 'Plugin activated successfully.'
                            ).then(function () {
                                window.location.reload();
                            });
                        } else {
                            window.location.reload();
                        }
                    }
                })
                .catch(function (err) {
                    if (typeof ScalynAlert !== 'undefined') {
                        ScalynAlert.close();
                        ScalynAlert.error('Activation Failed', err.message || 'Failed to activate plugin.');
                    }
                    btn.disabled = false;
                    btn.textContent = originalText;
                });
        });
    }

    /**
     * Handle "Dismiss Wizard" button.
     */
    function initDismissWizard() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.scalyn-dismiss-wizard');
            if (!btn) return;

            fetchApi('wizard/dismiss', { method: 'POST' })
                .then(function (response) {
                    if (response.success) {
                        if (typeof ScalynAlert !== 'undefined') {
                            ScalynAlert.toast('Wizard dismissed');
                        }
                        // Hide the wizard panel.
                        var wizardPanel = btn.closest('.scalyn-wizard-panel');
                        if (wizardPanel) {
                            wizardPanel.style.display = 'none';
                        }
                    }
                })
                .catch(function (err) {
                    if (typeof ScalynAlert !== 'undefined') {
                        ScalynAlert.error('Error', err.message || 'Failed to dismiss wizard.');
                    }
                });
        });
    }

    /**
     * Handle "Reset Wizard" button.
     */
    function initResetWizard() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.scalyn-reset-wizard');
            if (!btn) return;

            if (typeof ScalynAlert === 'undefined') return;

            ScalynAlert.confirm(
                'Reset Wizard',
                'This will reset the setup wizard so it appears again. Continue?',
                'Reset'
            ).then(function (result) {
                if (!result.isConfirmed) return;

                fetchApi('wizard/dismiss', {
                    method: 'DELETE',
                })
                    .then(function (response) {
                        if (response.success) {
                            ScalynAlert.toast('Wizard reset');
                            window.location.reload();
                        }
                    })
                    .catch(function (err) {
                        ScalynAlert.error('Error', err.message || 'Failed to reset wizard.');
                    });
            });
        });
    }

    // -------------------------------------------------------------------------
    // Advanced Tab
    // -------------------------------------------------------------------------

    /**
     * Initialize the advanced settings tab.
     */
    function initAdvancedTab() {
        initAdvancedSettingsForm();
        initExportSettings();
        initImportSettings();
        initAiUsageForm();
        loadAiLog();
        initClearAiLog();
        initDebugMode();
        loadDebugLog();
        initDebugFilter();
        initClearDebugLog();
        loadBackupInfo();
        initRollbackButton();
        initGitHubUpdates();
    }

    /**
     * Handle advanced settings form (delete data on uninstall).
     */
    function initAdvancedSettingsForm() {
        var form = document.getElementById('scalyn-advanced-settings-form');
        if (!form) return;

        form.addEventListener('submit', function (e) {
            e.preventDefault();

            var formData = new FormData(form);
            var settings = {
                delete_data_on_uninstall: formData.has('delete_data_on_uninstall'),
            };

            var statusEl = document.getElementById('scalyn-advanced-status');
            if (statusEl) statusEl.textContent = 'Saving...';

            fetchApi('settings', {
                method: 'POST',
                body: JSON.stringify(settings),
            })
                .then(function (response) {
                    if (statusEl) statusEl.textContent = '';
                    if (response.success && typeof ScalynAlert !== 'undefined') {
                        ScalynAlert.toast('Advanced settings saved');
                    }
                })
                .catch(function (err) {
                    if (statusEl) statusEl.textContent = '';
                    if (typeof ScalynAlert !== 'undefined') {
                        ScalynAlert.error('Save Failed', err.message || 'Failed to save settings.');
                    }
                });
        });
    }

    /**
     * Handle AI usage form (max requests per day).
     */
    function initAiUsageForm() {
        var form = document.getElementById('scalyn-ai-usage-form');
        if (!form) return;

        form.addEventListener('submit', function (e) {
            e.preventDefault();

            var formData = new FormData(form);
            var maxRequests = parseInt(formData.get('max_ai_requests_per_day'), 10);

            var settings = {
                max_ai_requests_per_day: isNaN(maxRequests) ? 0 : maxRequests,
            };

            var statusEl = document.getElementById('scalyn-ai-usage-status');
            if (statusEl) statusEl.textContent = 'Saving...';

            fetchApi('settings', {
                method: 'POST',
                body: JSON.stringify(settings),
            })
                .then(function (response) {
                    if (statusEl) statusEl.textContent = '';
                    if (response.success && typeof ScalynAlert !== 'undefined') {
                        ScalynAlert.toast('AI usage limits saved');
                    }
                })
                .catch(function (err) {
                    if (statusEl) statusEl.textContent = '';
                    if (typeof ScalynAlert !== 'undefined') {
                        ScalynAlert.error('Save Failed', err.message || 'Failed to save AI limits.');
                    }
                });
        });
    }

    /**
     * Handle Export Settings button click.
     */
    function initExportSettings() {
        var btn = document.getElementById('scalyn-export-settings');
        if (!btn) return;

        btn.addEventListener('click', function (e) {
            e.preventDefault();

            btn.disabled = true;
            btn.textContent = 'Exporting...';

            fetchApi('settings/export', { method: 'GET' })
                .then(function (response) {
                    if (response.success && response.data) {
                        var json = JSON.stringify(response.data, null, 2);
                        var blob = new Blob([json], { type: 'application/json' });
                        var url = URL.createObjectURL(blob);
                        var today = new Date().toISOString().slice(0, 10);

                        var a = document.createElement('a');
                        a.href = url;
                        a.download = 'scalyn-qa-settings-' + today + '.json';
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        URL.revokeObjectURL(url);

                        if (typeof ScalynAlert !== 'undefined') {
                            ScalynAlert.toast('Settings exported successfully');
                        }
                    }
                })
                .catch(function (err) {
                    if (typeof ScalynAlert !== 'undefined') {
                        ScalynAlert.error('Export Failed', err.message || 'Failed to export settings.');
                    }
                })
                .finally(function () {
                    btn.disabled = false;
                    btn.textContent = 'Export Settings';
                });
        });
    }

    /**
     * Handle Import Settings button click.
     */
    function initImportSettings() {
        var btn = document.getElementById('scalyn-import-settings');
        if (!btn) return;

        btn.addEventListener('click', function (e) {
            e.preventDefault();

            var fileInput = document.getElementById('scalyn-import-file');
            if (!fileInput || !fileInput.files || !fileInput.files.length) {
                if (typeof ScalynAlert !== 'undefined') {
                    ScalynAlert.error('No File Selected', 'Please select a JSON file to import.');
                }
                return;
            }

            var file = fileInput.files[0];
            var reader = new FileReader();

            reader.onload = function (event) {
                var content;
                try {
                    content = JSON.parse(event.target.result);
                } catch (parseErr) {
                    if (typeof ScalynAlert !== 'undefined') {
                        ScalynAlert.error('Invalid File', 'The selected file is not valid JSON.');
                    }
                    return;
                }

                // Confirm import with SweetAlert2.
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Import Settings',
                        text: 'This will overwrite your current settings with the imported file. API keys will not be changed. Continue?',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Import',
                        confirmButtonColor: '#4a90d9',
                        customClass: { popup: 'scalyn-swal-popup' },
                    }).then(function (result) {
                        if (result.isConfirmed) {
                            performImport(content);
                        }
                    });
                } else {
                    // Fallback without SweetAlert2.
                    if (confirm('This will overwrite your current settings. Continue?')) {
                        performImport(content);
                    }
                }
            };

            reader.readAsText(file);
        });
    }

    /**
     * Perform the actual settings import via REST API.
     *
     * @param {Object} data - The parsed JSON data to import.
     */
    function performImport(data) {
        if (typeof ScalynAlert !== 'undefined') {
            ScalynAlert.loading('Importing settings...');
        }

        fetchApi('settings/import', {
            method: 'POST',
            body: JSON.stringify(data),
        })
            .then(function (response) {
                if (typeof ScalynAlert !== 'undefined') {
                    ScalynAlert.close();
                }
                if (response.success && response.data) {
                    var imported = response.data.imported || [];
                    var labelMap = {
                        'settings': 'General Settings',
                        'ai_config (API keys preserved)': 'AI Config (API keys preserved)',
                        'page_audit_settings': 'Page Audit Settings',
                        'global_ignores': 'Global Ignores',
                        'launch_settings': 'Launch Checklist Settings',
                        'local_business': 'Local Business Schema',
                        'launch_ai_content': 'Launch AI Content',
                    };
                    var labels = imported.map(function (key) { return labelMap[key] || key; });
                    var message = 'Imported: ' + labels.join(', ');
                    if (typeof ScalynAlert !== 'undefined') {
                        ScalynAlert.success('Import Complete', message);
                    }
                    // Reload page after short delay.
                    setTimeout(function () {
                        window.location.reload();
                    }, 1500);
                }
            })
            .catch(function (err) {
                if (typeof ScalynAlert !== 'undefined') {
                    ScalynAlert.close();
                    ScalynAlert.error('Import Failed', err.message || 'Failed to import settings.');
                }
            });
    }

    /**
     * Load and render the AI usage log table.
     */
    function loadAiLog() {
        var container = document.getElementById('scalyn-ai-log-container');
        if (!container) return;

        fetchApi('ai/log', { method: 'GET' })
            .then(function (response) {
                if (response.success && response.data) {
                    var entries = response.data.entries || [];
                    renderAiLogTable(container, entries);
                }
            })
            .catch(function (err) {
                container.innerHTML = '<p class="scalyn-field-description">Failed to load AI usage log.</p>';
            });
    }

    /**
     * Render the AI log as an HTML table.
     *
     * @param {HTMLElement} container - The container element.
     * @param {Array}       entries   - Log entries array.
     */
    function renderAiLogTable(container, entries) {
        if (!entries.length) {
            container.innerHTML = '<p class="scalyn-field-description">No AI requests logged yet.</p>';
            return;
        }

        // Show most recent first, limit to 50 entries in the table.
        var display = entries.slice().reverse().slice(0, 50);

        var html = '<table class="widefat striped" style="max-width: 100%; margin-top: 8px;">';
        html += '<thead><tr>';
        html += '<th>Date</th>';
        html += '<th>User</th>';
        html += '<th>Provider</th>';
        html += '<th>Model</th>';
        html += '<th>Post ID</th>';
        html += '<th>Status</th>';
        html += '<th>Content Length</th>';
        html += '</tr></thead><tbody>';

        display.forEach(function (entry) {
            var date = entry.date ? new Date(entry.date).toLocaleString() : '-';
            var status = entry.success ? '<span style="color:#46b450;">Success</span>' : '<span style="color:#dc3232;">Failed</span>';

            html += '<tr>';
            html += '<td>' + escapeHtml(date) + '</td>';
            html += '<td>' + escapeHtml(entry.user_name || '-') + '</td>';
            html += '<td>' + escapeHtml(entry.provider || '-') + '</td>';
            html += '<td>' + escapeHtml(entry.model || '-') + '</td>';
            html += '<td>' + (entry.post_id || '-') + '</td>';
            html += '<td>' + status + '</td>';
            html += '<td>' + (entry.content_length || 0) + '</td>';
            html += '</tr>';
        });

        html += '</tbody></table>';

        if (entries.length > 50) {
            html += '<p class="scalyn-field-description">Showing 50 most recent of ' + entries.length + ' total entries.</p>';
        }

        container.innerHTML = html;
    }

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
     * Handle the "Clear AI Log" button.
     */
    function initClearAiLog() {
        var btn = document.getElementById('scalyn-clear-ai-log');
        if (!btn) return;

        btn.addEventListener('click', function () {
            Swal.fire({
                title: 'Clear AI Usage Log',
                text: 'This will permanently delete all AI usage log entries. Continue?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Clear Log',
                confirmButtonColor: '#dc3545',
                customClass: { popup: 'scalyn-swal-popup' },
            }).then(function (result) {
                if (!result.isConfirmed) return;

                btn.disabled = true;
                fetchApi('ai/log', { method: 'DELETE' })
                    .then(function (response) {
                        if (response.success) {
                            ScalynAlert.toast('AI usage log cleared');
                            var container = document.getElementById('scalyn-ai-log-container');
                            if (container) {
                                container.innerHTML = '<p class="scalyn-field-description">No AI requests logged yet.</p>';
                            }
                        }
                    })
                    .catch(function (err) {
                        ScalynAlert.error('Failed', err.message || 'Failed to clear AI log.');
                    })
                    .finally(function () {
                        btn.disabled = false;
                    });
            });
        });
    }

    // -------------------------------------------------------------------------
    // GitHub Updates
    // -------------------------------------------------------------------------

    /**
     * Initialize GitHub update check and save settings handlers.
     */
    function initGitHubUpdates() {
        // Auto-check on page load.
        var latestEl = document.getElementById('scalyn-github-latest-version');
        if (latestEl && latestEl.textContent.trim() === 'Not checked') {
            fetchApi('updates/check', { method: 'POST' })
                .then(function (response) {
                    var data = response.data || response;
                    if (data.latest_version) {
                        latestEl.innerHTML = '<code>' + data.latest_version + '</code>';
                        if (data.status === 'update_available') {
                            latestEl.innerHTML += ' <span style="color:var(--scalyn-warning);font-weight:600;">— Update available</span>';
                            var updateBtn = document.getElementById('scalyn-update-now');
                            if (updateBtn) updateBtn.style.display = '';
                        } else {
                            latestEl.innerHTML += ' <span style="color:var(--scalyn-success);">✓ Up to date</span>';
                        }
                    } else if (data.status === 'error' || data.status === 'up_to_date') {
                        // No release found or already up to date — show current version
                        var ver = (typeof scalynQA !== 'undefined' && scalynQA.settings && scalynQA.settings.version) ? scalynQA.settings.version : '';
                        if (!ver) {
                            var installedEl = document.querySelector('.scalyn-form-table code');
                            ver = installedEl ? installedEl.textContent.trim() : '';
                        }
                        if (ver) {
                            latestEl.innerHTML = '<code>' + ver + '</code> <span style="color:var(--scalyn-success);">✓ Up to date</span>';
                        } else {
                            latestEl.innerHTML = '<span style="color:var(--scalyn-text-muted);">No releases published yet</span>';
                        }
                    }
                    var checkedEl = document.getElementById('scalyn-github-last-checked');
                    if (checkedEl) checkedEl.textContent = 'Just now';
                })
                .catch(function () {
                    // No release on GitHub — not an error, just no releases yet
                    latestEl.innerHTML = '<span style="color:var(--scalyn-text-muted);">No releases published yet</span>';
                    var checkedEl = document.getElementById('scalyn-github-last-checked');
                    if (checkedEl) checkedEl.textContent = 'Just now';
                });
        }

        // Check for Updates button.
        var checkBtn = document.getElementById('scalyn-check-updates');
        if (checkBtn) {
            checkBtn.addEventListener('click', function () {
                var statusEl = document.getElementById('scalyn-github-status');
                checkBtn.disabled = true;
                if (statusEl) {
                    statusEl.textContent = 'Checking...';
                    statusEl.style.color = '#666';
                }

                fetchApi('updates/check', { method: 'POST' })
                    .then(function (response) {
                        var data = response.data || response;

                        // Update Latest Version display.
                        var latestEl = document.getElementById('scalyn-github-latest-version');
                        if (latestEl && data.latest_version) {
                            latestEl.innerHTML = '<code>' + escapeHtml(data.latest_version) + '</code>';
                            if (data.status === 'update_available') {
                                latestEl.innerHTML += ' <span style="color: #F59E0B; font-weight: 600;">\u2014 Update available!</span>';
                            } else {
                                latestEl.innerHTML += ' <span style="color: #10B981;">\u2713 Up to date</span>';
                            }
                        }

                        // Update Last Checked.
                        var checkedEl = document.getElementById('scalyn-github-last-checked');
                        if (checkedEl) {
                            checkedEl.textContent = 'Just now';
                        }

                        if (statusEl) {
                            statusEl.textContent = data.message || 'Check complete.';
                            statusEl.style.color = data.status === 'error' ? '#EF4444' : '#10B981';
                        }

                        // Show/hide Update Now button.
                        var updateBtn = document.getElementById('scalyn-update-now');
                        if (updateBtn) {
                            updateBtn.style.display = data.status === 'update_available' ? '' : 'none';
                        }

                        if (typeof ScalynAlert !== 'undefined') {
                            if (data.status === 'update_available') {
                                ScalynAlert.success('Update Available', 'Version ' + data.latest_version + ' is available. Click "Update Now" to install.');
                            } else if (data.status === 'up_to_date') {
                                ScalynAlert.toast('You are running the latest version.', 'success');
                            } else if (data.status === 'error' && data.message && data.message.indexOf('No GitHub release') !== -1) {
                                // No release yet — not a real error
                                ScalynAlert.toast('No releases published yet. Create a release on GitHub to enable update checks.', 'info');
                                if (latestEl) {
                                    latestEl.innerHTML = '<span style="color:var(--scalyn-text-muted);">No releases published yet</span>';
                                }
                            } else {
                                ScalynAlert.error('Check Failed', data.message || 'Could not check for updates.');
                            }
                        }
                    })
                    .catch(function (err) {
                        if (statusEl) {
                            statusEl.textContent = 'Failed to check for updates.';
                            statusEl.style.color = '#EF4444';
                        }
                        if (typeof ScalynAlert !== 'undefined') {
                            ScalynAlert.error('Error', 'Failed to check for updates: ' + (err.message || 'Unknown error'));
                        }
                    })
                    .finally(function () {
                        checkBtn.disabled = false;
                    });
            });
        }

        // Save GitHub Settings button.
        var saveGithubBtn = document.getElementById('scalyn-save-github-settings');
        if (saveGithubBtn) {
            saveGithubBtn.addEventListener('click', function () {
                var ownerEl = document.getElementById('scalyn-github-owner');
                var repoEl = document.getElementById('scalyn-github-repo');
                var tokenEl = document.getElementById('scalyn-github-token');

                var owner = ownerEl ? ownerEl.value.trim() : 'scalyn-invited';
                var repo = repoEl ? repoEl.value.trim() : 'scalyn-qa-assistant';
                var token = tokenEl ? tokenEl.value.trim() : '';

                fetchApi('updates/save-token', {
                    method: 'POST',
                    body: JSON.stringify({
                        github_owner: owner || 'scalyn-invited',
                        github_repo: repo || 'scalyn-qa-assistant',
                        github_token: token,
                    }),
                })
                    .then(function () {
                        if (typeof ScalynAlert !== 'undefined') {
                            ScalynAlert.toast('GitHub settings saved.', 'success');
                        }

                        // Clear the token field after save (for security).
                        if (tokenEl && token) {
                            tokenEl.value = '';
                            tokenEl.placeholder = '\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022';
                        }
                    })
                    .catch(function () {
                        if (typeof ScalynAlert !== 'undefined') {
                            ScalynAlert.error('Error', 'Failed to save GitHub settings.');
                        }
                    });
            });
        }

        // Update Now button.
        var updateBtn = document.getElementById('scalyn-update-now');
        if (updateBtn) {
            updateBtn.addEventListener('click', function () {
                if (typeof ScalynAlert === 'undefined') return;

                ScalynAlert.confirm(
                    'Update Plugin',
                    'This will download and install the latest version. The page will reload after the update.',
                    'Update Now'
                ).then(function (result) {
                    if (!result.isConfirmed) return;

                    updateBtn.disabled = true;
                    updateBtn.textContent = 'Updating...';
                    ScalynAlert.loading('Installing update...');

                    fetchApi('updates/install', { method: 'POST' })
                        .then(function (response) {
                            ScalynAlert.close();
                            var data = response.data || response;
                            if (data.updated) {
                                ScalynAlert.success('Updated', data.message || 'Update installed successfully.').then(function () {
                                    window.location.reload();
                                });
                            }
                        })
                        .catch(function (err) {
                            ScalynAlert.close();
                            ScalynAlert.error('Update Failed', err.message || 'Failed to install update.');
                            updateBtn.disabled = false;
                            updateBtn.innerHTML = '<span class="dashicons dashicons-update" aria-hidden="true"></span> Update Now';
                        });
                });
            });
        }
    }

    // -------------------------------------------------------------------------
    // Debug Mode
    // -------------------------------------------------------------------------

    /**
     * Handle debug mode toggle — saves via POST /settings.
     */
    function initDebugMode() {
        var toggle = document.getElementById('scalyn-debug-mode');
        if (!toggle) return;

        toggle.addEventListener('change', function () {
            var settings = {
                debug_mode: toggle.checked,
            };

            fetchApi('settings', {
                method: 'POST',
                body: JSON.stringify(settings),
            })
                .then(function (response) {
                    if (response.success && typeof ScalynAlert !== 'undefined') {
                        ScalynAlert.toast(toggle.checked ? 'Debug mode enabled' : 'Debug mode disabled');
                    }
                })
                .catch(function (err) {
                    if (typeof ScalynAlert !== 'undefined') {
                        ScalynAlert.error('Save Failed', err.message || 'Failed to update debug mode.');
                    }
                    // Revert toggle on failure.
                    toggle.checked = !toggle.checked;
                });
        });
    }

    /**
     * Load and render the debug log table.
     *
     * @param {string} [category] - Optional category filter.
     */
    function loadDebugLog(category) {
        var container = document.getElementById('scalyn-debug-log-container');
        if (!container) return;

        var endpoint = 'debug/log?limit=100';
        if (category) {
            endpoint += '&category=' + encodeURIComponent(category);
        }

        container.innerHTML = '<p>Loading debug log...</p>';

        fetchApi(endpoint, { method: 'GET' })
            .then(function (response) {
                if (response.success && response.data) {
                    var entries = response.data.entries || [];
                    renderDebugLogTable(container, entries);
                }
            })
            .catch(function () {
                container.innerHTML = '<p class="scalyn-field-description">Failed to load debug log.</p>';
            });
    }

    /**
     * Render the debug log as an HTML table.
     *
     * @param {HTMLElement} container - The container element.
     * @param {Array}       entries   - Log entries array.
     */
    function renderDebugLogTable(container, entries) {
        if (!entries.length) {
            container.innerHTML = '<p class="scalyn-field-description">No debug log entries.</p>';
            return;
        }

        var html = '<table class="widefat striped" style="max-width: 100%; margin-top: 8px;">';
        html += '<thead><tr>';
        html += '<th>Date</th>';
        html += '<th>Category</th>';
        html += '<th>Message</th>';
        html += '<th>User ID</th>';
        html += '</tr></thead><tbody>';

        entries.forEach(function (entry) {
            var date = entry.date ? new Date(entry.date).toLocaleString() : '-';
            var categoryLabel = escapeHtml(entry.category || '-');

            html += '<tr>';
            html += '<td>' + escapeHtml(date) + '</td>';
            html += '<td><code>' + categoryLabel + '</code></td>';
            html += '<td>' + escapeHtml(entry.message || '-') + '</td>';
            html += '<td>' + (entry.user_id || 0) + '</td>';
            html += '</tr>';
        });

        html += '</tbody></table>';
        container.innerHTML = html;
    }

    /**
     * Handle category filter dropdown changes.
     */
    function initDebugFilter() {
        var filter = document.getElementById('scalyn-debug-filter');
        if (!filter) return;

        filter.addEventListener('change', function () {
            var category = filter.value || undefined;
            loadDebugLog(category);
        });
    }

    /**
     * Handle "Clear Log" button — calls DELETE /debug/log with confirmation.
     */
    function initClearDebugLog() {
        var btn = document.getElementById('scalyn-clear-debug-log');
        if (!btn) return;

        btn.addEventListener('click', function (e) {
            e.preventDefault();

            if (typeof ScalynAlert !== 'undefined') {
                ScalynAlert.confirm(
                    'Clear Debug Log',
                    'Are you sure you want to clear the entire debug log? This action cannot be undone.',
                    'Clear'
                ).then(function (result) {
                    if (!result.isConfirmed) return;
                    performClearDebugLog();
                });
            } else if (confirm('Are you sure you want to clear the debug log?')) {
                performClearDebugLog();
            }
        });
    }

    /**
     * Perform the debug log clear via REST API.
     */
    function performClearDebugLog() {
        fetchApi('debug/log', { method: 'DELETE' })
            .then(function (response) {
                if (response.success) {
                    if (typeof ScalynAlert !== 'undefined') {
                        ScalynAlert.toast('Debug log cleared');
                    }
                    // Re-render the empty log.
                    var container = document.getElementById('scalyn-debug-log-container');
                    if (container) {
                        container.innerHTML = '<p class="scalyn-field-description">No debug log entries.</p>';
                    }
                }
            })
            .catch(function (err) {
                if (typeof ScalynAlert !== 'undefined') {
                    ScalynAlert.error('Error', err.message || 'Failed to clear debug log.');
                }
            });
    }

    // -------------------------------------------------------------------------
    // Backup / Rollback
    // -------------------------------------------------------------------------

    /**
     * Load backup info and display the rollback panel if a backup exists.
     */
    function loadBackupInfo() {
        var panel = document.getElementById('scalyn-backup-info');
        if (!panel) return;

        // Check if backup info was already rendered server-side.
        if (panel.getAttribute('data-has-backup') === '1') {
            panel.style.display = 'block';
            return;
        }

        // No backup — keep panel hidden, no AJAX needed.
        return;

        // Legacy fetch (kept for reference but not called).
        fetch(scalynQA.restUrl + 'settings/backup', {
            method: 'GET',
            headers: { 'X-WP-Nonce': scalynQA.nonce },
            credentials: 'same-origin',
        })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (response) {
                if (!response) return;
                if (response.success && response.data) {
                    var dateEl = document.getElementById('scalyn-backup-date');
                    var byEl = document.getElementById('scalyn-backup-by');

                    if (dateEl) {
                        var dateStr = response.data.created_at
                            ? new Date(response.data.created_at).toLocaleString()
                            : 'Unknown';
                        dateEl.textContent = dateStr;
                    }

                    if (byEl) {
                        byEl.textContent = response.data.created_by || 'Unknown';
                    }

                    panel.style.display = 'block';
                }
            })
            .catch(function () {
                // No backup exists — keep panel hidden.
            });
    }

    /**
     * Handle the rollback button click with confirmation.
     */
    function initRollbackButton() {
        var btn = document.getElementById('scalyn-rollback-settings');
        if (!btn) return;

        btn.addEventListener('click', function (e) {
            e.preventDefault();

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Rollback Settings',
                    text: 'This will restore all settings to their state before the last import. This action cannot be undone. Continue?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Rollback',
                    confirmButtonColor: '#d33',
                    customClass: { popup: 'scalyn-swal-popup' },
                }).then(function (result) {
                    if (result.isConfirmed) {
                        performRollback();
                    }
                });
            } else if (confirm('This will restore all settings to their state before the last import. Continue?')) {
                performRollback();
            }
        });
    }

    /**
     * Perform the settings rollback via REST API.
     */
    function performRollback() {
        if (typeof ScalynAlert !== 'undefined') {
            ScalynAlert.loading('Rolling back settings...');
        }

        fetchApi('settings/rollback', { method: 'POST' })
            .then(function (response) {
                if (typeof ScalynAlert !== 'undefined') {
                    ScalynAlert.close();
                }

                if (response.success && response.data) {
                    var restored = response.data.restored || [];
                    var labelMap = {
                        'settings': 'General Settings',
                        'ai_config': 'AI Config',
                        'page_audit_settings': 'Page Audit Settings',
                        'global_ignores': 'Global Ignores',
                        'launch_settings': 'Launch Checklist Settings',
                        'local_business': 'Local Business Schema',
                        'launch_ai_content': 'Launch AI Content',
                    };
                    var labels = restored.map(function (key) { return labelMap[key] || key; });
                    var message = 'Restored: ' + labels.join(', ');

                    if (typeof ScalynAlert !== 'undefined') {
                        ScalynAlert.success('Rollback Complete', message);
                    }

                    // Reload page after short delay to reflect restored settings.
                    setTimeout(function () {
                        window.location.reload();
                    }, 1500);
                }
            })
            .catch(function (err) {
                if (typeof ScalynAlert !== 'undefined') {
                    ScalynAlert.close();
                    ScalynAlert.error('Rollback Failed', err.message || 'Failed to rollback settings.');
                }
            });
    }

    // -------------------------------------------------------------------------
    // Report Tab
    // -------------------------------------------------------------------------

    function initReportTab() {
        var form = document.getElementById('scalyn-report-settings-form');
        if (!form) return;

        // Save settings.
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            var reportSettings = {
                include_page_scores: !!form.querySelector('[name="include_page_scores"]').checked,
                include_top_issues: !!form.querySelector('[name="include_top_issues"]').checked,
                include_launch: !!form.querySelector('[name="include_launch"]').checked,
                max_pages: parseInt(form.querySelector('[name="max_pages"]').value, 10) || 500,
                company_logo_id: parseInt(form.querySelector('[name="company_logo_id"]').value, 10) || 0,
            };

            fetchApi('settings', {
                method: 'POST',
                body: JSON.stringify({ report_settings: reportSettings }),
            }).then(function () {
                ScalynAlert && ScalynAlert.toast('Report settings saved');
            }).catch(function (err) {
                ScalynAlert && ScalynAlert.error('Error', err.message || 'Failed to save report settings.');
            });
        });

        // Upload logo via WP media.
        var uploadBtn = document.getElementById('scalyn-upload-logo');
        if (uploadBtn) {
            uploadBtn.addEventListener('click', function () {
                var frame = wp.media({
                    title: 'Select Company Logo',
                    button: { text: 'Use This Logo' },
                    multiple: false,
                    library: { type: 'image' },
                });

                frame.on('select', function () {
                    var attachment = frame.state().get('selection').first().toJSON();
                    var input = document.getElementById('scalyn-company-logo-id');
                    var preview = document.getElementById('scalyn-logo-preview');

                    input.value = attachment.id;
                    var imgUrl = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
                    preview.innerHTML = '<img src="' + imgUrl + '" alt="" style="max-height:60px;border-radius:6px;border:1px solid var(--scalyn-border-light);">';
                    preview.style.display = '';

                    uploadBtn.innerHTML = '<span class="dashicons dashicons-upload" aria-hidden="true"></span> Change Logo';

                    // Add remove button if not present.
                    if (!document.getElementById('scalyn-remove-logo')) {
                        var removeBtn = document.createElement('button');
                        removeBtn.type = 'button';
                        removeBtn.id = 'scalyn-remove-logo';
                        removeBtn.className = 'scalyn-btn scalyn-btn--small scalyn-btn--ghost';
                        removeBtn.style.marginLeft = '0.25rem';
                        removeBtn.textContent = 'Remove';
                        uploadBtn.parentNode.insertBefore(removeBtn, uploadBtn.nextSibling);
                        bindRemoveLogo(removeBtn);
                    }
                });

                frame.open();
            });
        }

        // Remove logo.
        var removeBtn = document.getElementById('scalyn-remove-logo');
        if (removeBtn) {
            bindRemoveLogo(removeBtn);
        }

        function bindRemoveLogo(btn) {
            btn.addEventListener('click', function () {
                document.getElementById('scalyn-company-logo-id').value = '0';
                var preview = document.getElementById('scalyn-logo-preview');
                preview.innerHTML = '';
                preview.style.display = 'none';
                var upload = document.getElementById('scalyn-upload-logo');
                if (upload) upload.innerHTML = '<span class="dashicons dashicons-upload" aria-hidden="true"></span> Upload Logo';
                btn.remove();
            });
        }

        // Detect site logo button — calls REST endpoint to find logo.
        var detectBtn = document.getElementById('scalyn-detect-logo');
        if (detectBtn) {
            detectBtn.addEventListener('click', function () {
                detectBtn.disabled = true;
                var origHtml = detectBtn.innerHTML;
                detectBtn.innerHTML = '<span class="dashicons dashicons-update spin" aria-hidden="true"></span> Detecting...';

                fetch(scalynQA.restUrl + 'settings/detect-logo', {
                    method: 'GET',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': scalynQA.nonce },
                    credentials: 'same-origin',
                })
                .then(function (r) { return r.json(); })
                .then(function (response) {
                    if (response.success && response.data && response.data.found) {
                        var logoId  = response.data.logo_id;
                        var logoUrl = response.data.logo_url;

                        var input   = document.getElementById('scalyn-company-logo-id');
                        var preview = document.getElementById('scalyn-logo-preview');

                        input.value = logoId;
                        preview.innerHTML = '<img src="' + logoUrl + '" alt="" style="max-height: 60px; border-radius: 6px; border: 1px solid var(--scalyn-border-light);">';
                        preview.style.display = '';

                        var uploadLogoBtn = document.getElementById('scalyn-upload-logo');
                        if (uploadLogoBtn) uploadLogoBtn.innerHTML = '<span class="dashicons dashicons-upload" aria-hidden="true"></span> Change Logo';

                        // Add remove button if not present.
                        if (!document.getElementById('scalyn-remove-logo')) {
                            var rmBtn = document.createElement('button');
                            rmBtn.type = 'button';
                            rmBtn.id = 'scalyn-remove-logo';
                            rmBtn.className = 'scalyn-btn scalyn-btn--small scalyn-btn--ghost';
                            rmBtn.style.marginLeft = '0.25rem';
                            rmBtn.textContent = 'Remove';
                            detectBtn.parentNode.insertBefore(rmBtn, detectBtn.nextSibling);
                            bindRemoveLogo(rmBtn);
                        }

                        if (typeof ScalynAlert !== 'undefined') {
                            ScalynAlert.toast('Site logo detected! Click "Save Settings" to confirm.');
                        }
                    } else {
                        var msg = (response.data && response.data.message) ? response.data.message : 'No site logo detected.';
                        if (typeof ScalynAlert !== 'undefined') {
                            ScalynAlert.warning('No Logo Found', msg);
                        } else {
                            alert(msg);
                        }
                    }
                })
                .catch(function () {
                    if (typeof ScalynAlert !== 'undefined') {
                        ScalynAlert.error('Detection Failed', 'Could not detect site logo.');
                    }
                })
                .finally(function () {
                    detectBtn.disabled = false;
                    detectBtn.innerHTML = origHtml;
                });
            });
        }
    }

    // -------------------------------------------------------------------------
    // Initialization
    // -------------------------------------------------------------------------

    /**
     * Detect the current tab and initialize the appropriate functionality.
     */
    function init() {
        // Determine current tab from URL or active tab element.
        var urlParams = new URLSearchParams(window.location.search);
        var currentTab = urlParams.get('tab') || 'general';

        // Always init general tab if visible (it's the default).
        initGeneralTab();

        switch (currentTab) {
            case 'ai-providers':
                initAiProvidersTab();
                break;
            case 'page-audits':
                initPageAuditsTab();
                break;
            case 'launch':
                initLaunchSettingsTab();
                break;
            case 'wizard':
                initWizardTab();
                break;
            case 'report':
                initReportTab();
                break;
            case 'advanced':
                initAdvancedTab();
                break;
            default:
                // General tab is already initialized.
                break;
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();

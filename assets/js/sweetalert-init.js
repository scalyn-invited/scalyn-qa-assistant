/**
 * SweetAlert2 Initialization.
 *
 * Provides a ScalynAlert wrapper object with convenience methods
 * for common SweetAlert2 dialog patterns used throughout the plugin.
 *
 * @package Scalyn\QA\Assets
 * @since   1.0.0
 */

'use strict';

(function () {

    /**
     * ScalynAlert - SweetAlert2 wrapper for consistent alert styling.
     *
     * @namespace ScalynAlert
     */
    const ScalynAlert = {

        /**
         * Show a green success toast that auto-closes after 3 seconds.
         *
         * @param {string} title - The alert title.
         * @param {string} text  - The alert body text.
         * @returns {Promise} SweetAlert2 promise.
         */
        success(title, text) {
            return Swal.fire({
                icon: 'success',
                title: title,
                text: text,
                timer: 3000,
                timerProgressBar: true,
                showConfirmButton: false,
                customClass: {
                    popup: 'scalyn-swal-popup',
                },
            });
        },

        /**
         * Show a red error alert requiring user acknowledgement.
         *
         * @param {string} title - The alert title.
         * @param {string} text  - The alert body text.
         * @returns {Promise} SweetAlert2 promise.
         */
        error(title, text) {
            return Swal.fire({
                icon: 'error',
                title: title,
                text: text,
                confirmButtonColor: '#dc3545',
                customClass: {
                    popup: 'scalyn-swal-popup',
                },
            });
        },

        /**
         * Show a yellow warning alert.
         *
         * @param {string} title - The alert title.
         * @param {string} text  - The alert body text.
         * @returns {Promise} SweetAlert2 promise.
         */
        warning(title, text) {
            return Swal.fire({
                icon: 'warning',
                title: title,
                text: text,
                confirmButtonColor: '#f0ad4e',
                customClass: {
                    popup: 'scalyn-swal-popup',
                },
            });
        },

        /**
         * Show a confirmation dialog with Yes/Cancel buttons.
         *
         * @param {string} title       - The dialog title.
         * @param {string} text        - The dialog body text.
         * @param {string} confirmText - The confirm button label. Default: 'Yes'.
         * @returns {Promise<SweetAlertResult>} Resolves with the SweetAlert2 result.
         */
        confirm(title, text, confirmText = 'Yes') {
            return Swal.fire({
                icon: 'question',
                title: title,
                text: text,
                showCancelButton: true,
                confirmButtonText: confirmText,
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#4a90d9',
                cancelButtonColor: '#6c757d',
                reverseButtons: true,
                customClass: {
                    popup: 'scalyn-swal-popup',
                },
            });
        },

        /**
         * Show a loading spinner overlay.
         *
         * @param {string} title - The loading message. Default: 'Processing...'.
         * @returns {void}
         */
        loading(title = 'Processing...') {
            Swal.fire({
                title: title,
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: function () {
                    Swal.showLoading();
                },
                customClass: {
                    popup: 'scalyn-swal-popup',
                },
            });
        },

        /**
         * Close the currently open SweetAlert2 dialog.
         *
         * @returns {void}
         */
        close() {
            Swal.close();
        },

        /**
         * Show a small corner toast notification.
         *
         * @param {string} message - The toast message.
         * @param {string} icon    - The icon type. Default: 'success'.
         * @returns {Promise} SweetAlert2 promise.
         */
        toast(message, icon = 'success') {
            var Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                didOpen: function (toast) {
                    toast.addEventListener('mouseenter', Swal.stopTimer);
                    toast.addEventListener('mouseleave', Swal.resumeTimer);
                },
                customClass: {
                    popup: 'scalyn-swal-toast',
                },
            });

            return Toast.fire({
                icon: icon,
                title: message,
            });
        },
    };

    // Expose globally.
    window.ScalynAlert = ScalynAlert;

})();

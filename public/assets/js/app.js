/**
 * SlimRack - Core Application JavaScript
 */

(function($) {
    'use strict';

    // CSRF token helper
    window.SlimRack = window.SlimRack || {};

    SlimRack.ajax = function(options) {
        const defaults = {
            method: 'GET',
            dataType: 'json',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        };

        // Add CSRF token for state-changing requests
        if (['POST', 'PUT', 'DELETE', 'PATCH'].includes(options.method?.toUpperCase())) {
            defaults.headers['X-CSRF-Token'] = SlimRack.csrf.value;
        }

        return $.ajax($.extend(true, defaults, options));
    };

    // Toast notification helper using Bootstrap's toast
    SlimRack.toast = function(message, type) {
        type = type || 'success';
        var toastContainer = document.getElementById('toastContainer');
        if (!toastContainer) {
            toastContainer = createToastContainer();
        }

        var toast = document.createElement('div');
        toast.className = 'toast align-items-center text-white bg-' + type + ' border-0';
        toast.setAttribute('role', 'alert');

        var flexDiv = document.createElement('div');
        flexDiv.className = 'd-flex';

        var bodyDiv = document.createElement('div');
        bodyDiv.className = 'toast-body';
        bodyDiv.textContent = message;

        var closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.className = 'btn-close btn-close-white me-2 m-auto';
        closeBtn.setAttribute('data-bs-dismiss', 'toast');

        flexDiv.appendChild(bodyDiv);
        flexDiv.appendChild(closeBtn);
        toast.appendChild(flexDiv);
        toastContainer.appendChild(toast);

        var bsToast = new bootstrap.Toast(toast, { delay: 3000 });
        bsToast.show();

        toast.addEventListener('hidden.bs.toast', function() {
            toast.remove();
        });
    };

    function createToastContainer() {
        var container = document.createElement('div');
        container.id = 'toastContainer';
        container.className = 'toast-container position-fixed top-0 end-0 p-3';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
        return container;
    }

    // Initialize clipboard.js
    if (typeof ClipboardJS !== 'undefined') {
        new ClipboardJS('.btn-copy').on('success', function(e) {
            SlimRack.toast('Copied to clipboard!', 'info');
            e.clearSelection();
        });
    }

    // Initialize Bootstrap datepickers
    $(document).ready(function() {
        $('.datepicker').datepicker({
            format: 'yyyy-mm-dd',
            autoclose: true,
            todayHighlight: true
        });
    });

})(jQuery);

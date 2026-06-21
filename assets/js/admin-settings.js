(function ($) {
    'use strict';

    $(function () {
        var colorPickerInitialized = false;
        function initColorPickers() {
            if (colorPickerInitialized) return;
            if (!$.fn.wpColorPicker) return;

            $('.kitgenix-pdf-invoicing-for-woocommerce-color-field').each(function () {
                var $input = $(this);
                var defaultColor = $input.data('default-color') || $input.val() || '';
                if (!$input.val() && defaultColor) {
                    $input.val(defaultColor);
                }

                $input.wpColorPicker({
                    defaultColor: defaultColor
                });
            });

            colorPickerInitialized = true;
        }

        // If Settings panel is visible, initialize immediately; otherwise wait until the Settings tab is shown.
        var root = document.querySelector('.kitgenix-pdf-invoicing-for-woocommerce-pdf-settings');
        var settingsPanel = root ? root.querySelector('[data-kitgenix-pdf-invoicing-for-woocommerce-tab-panel="settings"]') : null;
        var settingsVisible = !settingsPanel || settingsPanel.style.display !== 'none';
        if (settingsVisible) {
            initColorPickers();
        }

        if (root) {
            root.addEventListener('kitgenix:tabchange', function (e) {
                var tab = e && e.detail && e.detail.tab ? String(e.detail.tab) : '';
                if (tab) initColorPickers();
            });
        }
    });

})(jQuery);

(function () {
    function initLogoField() {
        var container = document.querySelector('.kitgenix-pdf-invoicing-for-woocommerce-logo-field');

        if (!container || typeof wp === 'undefined' || !wp.media) {
            return;
        }

        var selectBtn = container.querySelector('.kitgenix-pdf-invoicing-for-woocommerce-logo-select');
        var removeBtn = container.querySelector('.kitgenix-pdf-invoicing-for-woocommerce-logo-remove');
        var input     = container.querySelector('.kitgenix-pdf-invoicing-for-woocommerce-logo-id-field');
        var preview   = container.querySelector('.kitgenix-pdf-invoicing-for-woocommerce-logo-preview');

        if (!selectBtn || !input) {
            return;
        }

        var frame;

        selectBtn.addEventListener('click', function (e) {
            e.preventDefault();

            if (frame) {
                frame.open();
                return;
            }

            frame = wp.media({
                title: selectBtn.getAttribute('data-frame-title') || 'Select logo',
                button: {
                    text: selectBtn.getAttribute('data-frame-button') || 'Use this logo'
                },
                multiple: false,
                library: {
                    type: 'image'
                }
            });

            frame.on('select', function () {
                var attachment = frame.state().get('selection').first().toJSON();

                if (!attachment || !attachment.id) {
                    return;
                }

                input.value = attachment.id;

                if (preview) {
                    preview.innerHTML = '<img src="' + attachment.url + '" alt="">';
                }

                if (removeBtn) {
                    removeBtn.classList.remove('kitgenix-is-hidden');
                }
            });

            frame.open();
        });

        if (removeBtn) {
            removeBtn.addEventListener('click', function (e) {
                e.preventDefault();

                input.value = '';

                if (preview) {
                    preview.innerHTML = '';
                }

                removeBtn.classList.add('kitgenix-is-hidden');
            });
        }
    }

    document.addEventListener('DOMContentLoaded', initLogoField);
})();

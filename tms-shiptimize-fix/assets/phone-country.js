(function () {
    'use strict';

    function refresh() {
        for (const address of ['shipping', 'billing']) {
            const country = document.getElementById(address + '-country');
            const phoneCountry = document.getElementById(address + '-tms-phone-country');
            if (!country || !phoneCountry) {
                continue;
            }

            const matchingCountry = Array.from(phoneCountry.options).find(
                option => option.value === country.value && option.value !== ''
            );
            // Only update the display text. WooCommerce retains the automatic
            // value or the customer's explicit choice in its checkout state.
            for (const option of phoneCountry.options) {
                if (option.value !== 'AUTO' && option.value !== '') {
                    continue;
                }
                option.dataset.tmsOriginalLabel ??= option.textContent;
                const label = matchingCountry
                    ? option.dataset.tmsOriginalLabel + ' · ' + matchingCountry.textContent
                    : option.dataset.tmsOriginalLabel;
                if (option.textContent !== label) {
                    option.textContent = label;
                }
            }
        }
    }

    const checkout = document.querySelector('.wp-block-woocommerce-checkout');
    if (!checkout) {
        return;
    }
    let scheduled = false;
    function scheduleRefresh() {
        if (scheduled) {
            return;
        }
        scheduled = true;
        window.requestAnimationFrame(() => {
            scheduled = false;
            refresh();
        });
    }

    checkout.addEventListener('change', scheduleRefresh);
    new MutationObserver(scheduleRefresh).observe(checkout, { childList: true, subtree: true });
    refresh();
}());

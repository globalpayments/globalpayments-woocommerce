/**
 * Add tooltips to saved payment cards
 */
(function() {
    'use strict';

    const { __ } = wp.i18n;
    const TOOLTIP_MESSAGE = __( 'For card-based installment payment options, please enter your card information below.', 'globalpayments-gateway-provider-for-woocommerce' );

    function addTooltipsToSavedCards() {
        const savedCardInputs = document.querySelectorAll('input[name="radio-control-wc-payment-method-saved-tokens"]');
        
        if (savedCardInputs.length === 0) {
            return false;
        }

        savedCardInputs.forEach(function(input) {
            const label = input.closest('label');
            
            if (!label || label.querySelector('.gp-saved-card-tooltip')) {
                return;
            }

            const textContainer = label.querySelector('.wc-block-components-radio-control__label-group') || 
                                 label.querySelector('.wc-block-components-radio-control__label') || 
                                 label;

            // Create tooltip wrapper
            const tooltipSpan = document.createElement('span');
            tooltipSpan.className = 'gp-saved-card-tooltip';
            tooltipSpan.style.cssText = 'margin-left: 8px; position: relative; display: inline; vertical-align: middle;';

            // Create info icon with background image
            const icon = document.createElement('span');
            icon.className = 'gp-tooltip-icon';
            icon.style.cssText = 'display: inline-block; width: 20px; height: 20px; cursor: help; background: transparent url(https://js-cert.globalpay.com/4.1.17/images/gp-fa-question-circle.svg) no-repeat center center; background-size: 18px; vertical-align: middle;';
            icon.setAttribute('aria-label', TOOLTIP_MESSAGE);

            // Create tooltip bubble
            const bubble = document.createElement('span');
            bubble.className = 'gp-tooltip-bubble';
            bubble.textContent = TOOLTIP_MESSAGE;
            bubble.style.cssText = 'visibility: hidden; width: 280px; background-color: #fff; color: #474B57; text-align: left; border: 1px solid #5a5e6d; border-radius: 4px; padding: 12px; position: absolute; z-index: 10000; bottom: 125%; left: 50%; margin-left: -140px; opacity: 0; transition: opacity 0.3s; font-family: DMSans, sans-serif; font-size: 13px; line-height: 1.5; box-shadow: 0 2px 8px rgba(0,0,0,0.15); white-space: normal;';

            // Create arrow
            const arrow = document.createElement('span');
            arrow.style.cssText = 'position: absolute; top: 100%; left: 50%; margin-left: -5px; border-width: 5px; border-style: solid; border-color: #fff transparent transparent transparent;';
            bubble.appendChild(arrow);

            // Create arrow border
            const arrowBorder = document.createElement('span');
            arrowBorder.style.cssText = 'position: absolute; top: 100%; left: 50%; margin-left: -6px; border-width: 6px; border-style: solid; border-color: #5a5e6d transparent transparent transparent;';
            bubble.appendChild(arrowBorder);

            // Add hover events
            icon.addEventListener('mouseenter', function() {
                bubble.style.visibility = 'visible';
                bubble.style.opacity = '1';
            });

            icon.addEventListener('mouseleave', function() {
                bubble.style.visibility = 'hidden';
                bubble.style.opacity = '0';
            });

            tooltipSpan.appendChild(icon);
            tooltipSpan.appendChild(bubble);
            textContainer.appendChild(tooltipSpan);
        });

        return true;
    }

    function init() {
        if (!addTooltipsToSavedCards()) {
            setTimeout(addTooltipsToSavedCards, 100);
        }

        const observer = new MutationObserver(addTooltipsToSavedCards);
        const targetNode = document.querySelector('#payment-method') || document.body;
        observer.observe(targetNode, { childList: true, subtree: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

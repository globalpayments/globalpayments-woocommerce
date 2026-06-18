/**
 * Heartland Gateway WooCommerce Blocks Integration
 *
 * Provides WooCommerce Blocks checkout support for the Heartland (Portico) payment gateway.
 */
(() => {
    "use strict";

    const { createElement, useEffect, useState, useCallback, Fragment } = window.React;
    const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
    const { decodeEntities } = window.wp.htmlEntities;
    const { getSetting } = window.wc.wcSettings;
    const { __ } = wp.i18n;

    // DOM helper functions
    const querySelector = (selector) => document.querySelector(selector);
    const addClass = (el, className) => (el?.classList.add(className), el);
    const removeClass = (el, className) => (el?.classList.remove(className), el);
    const hideElement = (el) => (el?.style && (el.style.display = "none"), el);

    // Helper params state
    let helperParams = {};

    /**
     * Initialize helper params from settings
     */
    const initHelper = (params) => {
        helperParams = params;
        return { ...helper, order: helperParams.order };
    };

    /**
     * Helper object with DOM manipulation and checkout utilities
     */
    const helper = {
        getOrderInfo: () => {
            helper.blockOnSubmit();
            fetch(helperParams.orderInfoUrl)
                .then((response) => response.json())
                .then((data) => {
                    helperParams.order = data.message;
                })
                .catch((error) => {
                    console.log(error);
                })
                .finally(() => {
                    helper.unblockOnError();
                });
        },

        getPlaceOrderButtonSelector: () => ".wc-block-components-checkout-place-order-button",
        getSubmitButtonTargetSelector: (id) => "#" + id + "-card-submit",
        getPaymentMethodRadioSelector: (id) =>
            '#payment-method input[id*="radio-control-wc-payment-method-options-' + id + '"]',
        getStoredPaymentMethodsRadioSelector: () =>
            '#payment-method input[id*="radio-control-wc-payment-method-saved-tokens-"]:checked',

        isOnlyGatewayMethodDisplayed: (id) => {
            const allMethods = [...document.querySelectorAll(
                '#payment-method input[id*="radio-control-wc-payment-method-"]'
            )];
            const thisMethod = querySelector(helper.getPaymentMethodRadioSelector(id));
            return allMethods.includes(thisMethod) && allMethods.length === 1;
        },

        isFirstPaymentMethod: (id) =>
            document.querySelector("#payment-method input")?.value === id,

        toggleSubmitButtons: () => {
            const selectedMethod = querySelector(
                '#payment-method input[id*="radio-control-wc-payment-method-options-"]:checked'
            )?.value;

            removeClass(querySelector(".globalpayments.card-submit"), "is-active");

            if (helperParams.hide?.includes(selectedMethod)) {
                helper.hidePlaceOrderButton();
                return;
            }

            if (!helperParams.toggle?.includes(selectedMethod)) {
                helper.showPlaceOrderButton();
                return;
            }

            const submitBtn = querySelector("#" + selectedMethod + "-card-submit");
            const storedMethod = querySelector(helper.getStoredPaymentMethodsRadioSelector());
            const isStoredSelected = selectedMethod === querySelector(
                helper.getStoredPaymentMethodsRadioSelector() + ":checked"
            )?.value;

            if (!storedMethod || (storedMethod && isStoredSelected)) {
                addClass(submitBtn, "is-active");
                helper.hidePlaceOrderButton();
            } else {
                hideElement(submitBtn);
                helper.showPlaceOrderButton();
            }
        },

        hidePlaceOrderButton: () => {
            hideElement(
                addClass(
                    querySelector(helper.getPlaceOrderButtonSelector()),
                    "woocommerce-globalpayments-hidden"
                )
            );
        },

        showPlaceOrderButton: () => {
            const btn = removeClass(
                querySelector(helper.getPlaceOrderButtonSelector()),
                "woocommerce-globalpayments-hidden"
            );
            if (btn?.style) btn.style.display = "";
        },

        getFormSelector: () => "form.wc-block-components-form.wc-block-checkout__form",

        createInputElement: (id, name, value) => {
            let input = document.getElementById(id + "-" + name);
            if (!input) {
                input = document.createElement("input");
                input.id = id + "-" + name;
                input.name = id + "[" + name + "]";
                input.type = "hidden";
                querySelector(helper.getFormSelector())?.appendChild(input);
            }
            input.value = value;
        },

        createSubmitButtonTarget: (id) => {
            const target = document.createElement("div");
            target.id = helper.getSubmitButtonTargetSelector(id).replace("#", "");
            target.className = "globalpayments " + id + " card-submit";
            const placeOrderBtn = querySelector(helper.getPlaceOrderButtonSelector());
            placeOrderBtn?.parentNode?.insertBefore(target, placeOrderBtn.nextSibling);
            helper.toggleSubmitButtons(id);
        },

        placeOrder: () => {
            try {
                const btn = querySelector(helper.getPlaceOrderButtonSelector());
                if (btn) {
                    btn.click();
                    return;
                }
            } catch (e) { }
            querySelector(helper.getFormSelector())?.submit();
        },

        showPaymentError: (message) => {
            const form = querySelector(helper.getFormSelector());
            querySelector(
                ".woocommerce-NoticeGroup, .woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-globalpayments-checkout-error"
            )?.remove();

            let errorHtml = message;
            if (message.indexOf("woocommerce-error") === -1) {
                errorHtml = '<ul class="woocommerce-error"><li>' + message + "</li></ul>";
            }

            form?.prepend(
                '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout woocommerce-globalpayments-checkout-error">' +
                errorHtml +
                "</div>"
            );

            helper.unblockOnError();
            document.body.dispatchEvent(new Event("checkout_error"));
        },

        blockOnSubmit: () => {
            const form = querySelector(helper.getFormSelector());
            const data = form?.data?.();
            if (data?.["blockUI.isBlocked"] !== 1) {
                form?.block?.({
                    message: null,
                    overlayCSS: { background: "#fff", opacity: 0.6 },
                });
            }
        },

        unblockOnError: () => {
            querySelector(helper.getFormSelector())?.unblock?.();
        },

        setPaymentMethodData: (data) => {
            data.block_checkout = true;

            window.wp.data
                .dispatch(window.wc.wcBlocksData.PAYMENT_STORE_KEY)
                .__internalSetPaymentMethodData(data);
        },

        dispatchError: ({ message, context = "heartland-context", cb = null }) => {
            window.wp.data.dispatch("core/notices").createErrorNotice(message, { context });
            cb?.();
        },

        hasValidationErrors: () =>
            window.wp.data
                .select(window.wc.wcBlocksData.VALIDATION_STORE_KEY)
                .hasValidationErrors(),
    };

    // ==========================================
    // Gift Card Functions
    // ==========================================

    /**
     * Apply a gift card to the order
     * @param {string} cardNumber - Gift card number
     * @param {string} cardPin - Gift card PIN
     * @param {Function} onSuccess - Callback on success
     * @param {Function} onError - Callback on error
     */
    const applyGiftCard = (cardNumber, cardPin, onSuccess, onError) => {
        if (!cardNumber || !cardPin) {
            onError(__('Please enter both gift card number and PIN.', 'globalpayments-gateway-provider-for-woocommerce'));
            return;
        }

        giftCardState.isProcessing = true;
        giftCardState.errorMessage = null;
        giftCardState.successMessage = null;

        const ajaxUrl = state.settings?.ajax_url || '/wp-admin/admin-ajax.php';
        const formData = new FormData();
        formData.append('action', 'use_gift_card');
        formData.append('gift_card_number', cardNumber);
        formData.append('gift_card_pin', cardPin);
        formData.append('block_checkout', 1);

        fetch(ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
        })
            .then((response) => response.json())
            .then((data) => {
                giftCardState.isProcessing = false;
                if (data.error) {
                    giftCardState.errorMessage = data.message;
                    onError(data.message);
                } else {
                    giftCardState.successMessage = __('Gift card applied successfully!', 'globalpayments-gateway-provider-for-woocommerce');
                    // Trigger WooCommerce checkout update to refresh totals
                    triggerCheckoutUpdate();
                    onSuccess(data);
                }
            })
            .catch((error) => {
                giftCardState.isProcessing = false;
                giftCardState.errorMessage = __('Failed to apply gift card. Please try again.', 'globalpayments-gateway-provider-for-woocommerce');
                onError(giftCardState.errorMessage);
                console.error('Gift card error:', error);
            });
    };

    /**
     * Remove a gift card from the order
     * @param {string} cardId - Gift card ID to remove
     * @param {Function} onSuccess - Callback on success
     * @param {Function} onError - Callback on error
     */
    const removeGiftCard = (cardId, onSuccess, onError) => {
        const ajaxUrl = state.settings?.ajax_url || '/wp-admin/admin-ajax.php';
        const formData = new FormData();
        formData.append('action', 'remove_gift_card');
        formData.append('securesubmit_card_id', cardId);

        fetch(ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
        })
            .then(() => {
                // Trigger WooCommerce checkout update to refresh totals
                triggerCheckoutUpdate();
                onSuccess?.();
            })
            .catch((error) => {
                onError?.(__('Failed to remove gift card. Please try again.', 'globalpayments-gateway-provider-for-woocommerce'));
                console.error('Remove gift card error:', error);
            });
    };

    /**
     * Trigger WooCommerce checkout update to refresh cart totals
     */
    const triggerCheckoutUpdate = () => {
        // Dispatch action to refresh the cart/checkout
        if (window.wp?.data?.dispatch) {
            try {
                // Invalidate cart data to trigger refresh
                window.wp.data.dispatch(window.wc.wcBlocksData.CART_STORE_KEY).invalidateResolutionForStore();
            } catch (e) {
                console.log('Cart refresh dispatch not available');
            }
        }

        // Also trigger jQuery events for classic checkout compatibility
        if (typeof jQuery !== 'undefined') {
            jQuery('body').trigger('update_checkout');
        }
    };

    /**
     * Gift Card Input Section Component
     */
    const GiftCardSection = ({ settings, onUpdate }) => {
        const [cardNumber, setCardNumber] = useState('');
        const [cardPin, setCardPin] = useState('');
        const [isProcessing, setIsProcessing] = useState(false);
        const [errorMessage, setErrorMessage] = useState('');
        const [successMessage, setSuccessMessage] = useState('');

        const handleApply = (e) => {
            e.preventDefault();
            setErrorMessage('');
            setSuccessMessage('');
            setIsProcessing(true);

            applyGiftCard(
                cardNumber,
                cardPin,
                (data) => {
                    setIsProcessing(false);
                    setSuccessMessage(
                        data.balance 
                            ? __('Gift card applied! Balance: ', 'globalpayments-gateway-provider-for-woocommerce') + data.balance
                            : __('Gift card applied successfully!', 'globalpayments-gateway-provider-for-woocommerce')
                    );
                    setCardNumber('');
                    setCardPin('');
                    onUpdate?.();
                },
                (error) => {
                    setIsProcessing(false);
                    setErrorMessage(error);
                }
            );
        };

        return createElement(
            'fieldset',
            { className: 'heartland-gift-card-section' },
            createElement(
                'div',
                { className: 'securesubmit-content gift-card-content' },
                createElement(
                    'div',
                    { className: 'form-row form-row-wide', id: 'gift-card-row' },
                    createElement(
                        'label',
                        { id: 'gift-card-label', htmlFor: 'gift-card-number' },
                        __('Use a gift card', 'globalpayments-gateway-provider-for-woocommerce')
                    ),
                    createElement(
                        'div',
                        { id: 'gift-card-input' },
                        createElement('input', {
                            type: 'tel',
                            placeholder: __('Gift card', 'globalpayments-gateway-provider-for-woocommerce'),
                            id: 'gift-card-number',
                            className: 'input-text wc-block-components-text-input',
                            value: cardNumber,
                            onChange: (e) => setCardNumber(e.target.value),
                            disabled: isProcessing,
                        }),
                        createElement('input', {
                            type: 'tel',
                            placeholder: __('PIN', 'globalpayments-gateway-provider-for-woocommerce'),
                            id: 'gift-card-pin',
                            className: 'input-text wc-block-components-text-input',
                            value: cardPin,
                            onChange: (e) => setCardPin(e.target.value),
                            disabled: isProcessing,
                        }),
                        errorMessage && createElement(
                            'p',
                            { id: 'gift-card-error', className: 'woocommerce-error', style: { color: '#a00', marginTop: '8px' } },
                            errorMessage
                        ),
                        successMessage && createElement(
                            'p',
                            { id: 'gift-card-success', className: 'woocommerce-message', style: { color: '#0a0', marginTop: '8px' } },
                            successMessage
                        )
                    ),
                    createElement(
                        'button',
                        {
                            type: 'button',
                            id: 'apply-gift-card',
                            className: 'button wc-block-components-button wp-element-button',
                            onClick: handleApply,
                            disabled: isProcessing || !cardNumber || !cardPin,
                            style: { marginTop: '10px' },
                        },
                        isProcessing 
                            ? __('Applying...', 'globalpayments-gateway-provider-for-woocommerce')
                            : __('Apply', 'globalpayments-gateway-provider-for-woocommerce')
                    )
                ),
                createElement('div', { className: 'clear' })
            )
        );
    };

    /**
     * Applied Gift Cards Display Component
     * This component is rendered but the actual applied cards are shown
     * in the order totals by the server-side hooks
     */
    const AppliedGiftCardsInfo = ({ settings, refreshKey }) => {
        const [appliedCards, setAppliedCards] = useState([]);
        const [isLoading, setIsLoading] = useState(false);
        const [removingCardId, setRemovingCardId] = useState(null);

        /**
         * Fetch applied gift cards from server
         */
        const fetchAppliedCards = useCallback(() => {
            setIsLoading(true);
            const ajaxUrl = settings?.ajax_url || '/wp-admin/admin-ajax.php';
            const formData = new FormData();
            formData.append('action', 'get_applied_gift_cards');

            fetch(ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
            })
                .then((response) => response.json())
                .then((data) => {
                    setIsLoading(false);
                    if (data.success && Array.isArray(data.cards)) {
                        setAppliedCards(data.cards);
                    }
                })
                .catch((error) => {
                    setIsLoading(false);
                    console.error('Error fetching applied gift cards:', error);
                });
        }, [settings]);

        /**
         * Handle removing a gift card
         */
        const handleRemove = (cardId) => {
            setRemovingCardId(cardId);
            removeGiftCard(
                cardId,
                () => {
                    setRemovingCardId(null);
                    // Refresh the list after removal
                    fetchAppliedCards();
                },
                (error) => {
                    setRemovingCardId(null);
                    console.error('Failed to remove gift card:', error);
                }
            );
        };

        // Fetch cards on mount and when refreshKey changes
        useEffect(() => {
            fetchAppliedCards();
        }, [fetchAppliedCards, refreshKey]);

        // Also check initial settings for applied cards
        useEffect(() => {
            if (settings?.applied_gift_cards && Array.isArray(settings.applied_gift_cards)) {
                setAppliedCards(settings.applied_gift_cards);
            }
        }, [settings]);

        if (appliedCards.length === 0) {
            return null;
        }

        const currencySymbol = settings?.currency_symbol || '$';

        return createElement(
            'div',
            { className: 'heartland-applied-gift-cards-list' },
            createElement(
                'h4',
                { className: 'heartland-applied-gift-cards-title' },
                __('Applied Gift Cards', 'globalpayments-gateway-provider-for-woocommerce')
            ),
            createElement(
                'ul',
                { className: 'heartland-applied-gift-cards-items' },
                appliedCards.map((card) =>
                    createElement(
                        'li',
                        { 
                            key: card.id, 
                            className: 'heartland-applied-gift-card-item' + (removingCardId === card.id ? ' removing' : '')
                        },
                        createElement(
                            'span',
                            { className: 'heartland-gift-card-name' },
                            card.name || __('Gift Card', 'globalpayments-gateway-provider-for-woocommerce')
                        ),
                        createElement(
                            'button',
                            {
                                type: 'button',
                                className: 'heartland-remove-gift-card-btn',
                                onClick: () => handleRemove(card.id),
                                disabled: removingCardId === card.id,
                                'aria-label': __('Remove gift card', 'globalpayments-gateway-provider-for-woocommerce'),
                            },
                            removingCardId === card.id
                                ? __('Removing...', 'globalpayments-gateway-provider-for-woocommerce')
                                : __('Remove', 'globalpayments-gateway-provider-for-woocommerce')
                        )
                    )
                )
            ),
            isLoading && createElement(
                'div',
                { className: 'heartland-gift-card-loading' },
                __('Loading...', 'globalpayments-gateway-provider-for-woocommerce')
            )
        );
    };

    // State for the payment form
    const state = {
        settings: null,
        cardForm: null,
        tokenResponse: null,
        fieldOptions: null,
        isGiftCardOnlyOrder: false,
    };

    // State for gift cards
    const giftCardState = {
        appliedCards: [],
        isProcessing: false,
        errorMessage: null,
        successMessage: null,
        remainingBalance: null,
    };

    let globalHelper = {};
    let initRetryCount = 0;
    let isFormInitialized = false;
    const MAX_RETRY_COUNT = 20;

    /**
     * Check remaining balance after gift cards and update state
     * @param {Function} onComplete - Callback when check is complete
     */
    const checkRemainingBalance = (onComplete) => {
        const ajaxUrl = state.settings?.ajax_url || '/wp-admin/admin-ajax.php';
        const formData = new FormData();
        formData.append('action', 'get_remaining_balance');

        fetch(ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
        })
            .then((response) => response.json())
            .then((data) => {
                if (data.success) {
                    giftCardState.remainingBalance = data.remaining_balance;
                    state.isGiftCardOnlyOrder = data.is_gift_card_only_order === true;
                    onComplete?.(data);
                }
            })
            .catch((error) => {
                console.error('Error checking remaining balance:', error);
                onComplete?.(null);
            });
    };

    /**
     * Field component for rendering secure payment fields
     */
    const FieldComponent = ({ id, field }) => {
        return createElement(
            "div",
            { className: `globalpayments ${id} ${field.class}` },
            createElement(
                "label",
                { htmlFor: `${id}-${field.class}` },
                field.label,
                createElement("span", { className: "required" }, "")
            ),
            createElement("div", { id: `${id}-${field.class}` })
        );
    };

    /**
     * Initialize the GlobalPayments form
     */
    const initializePaymentForm = () => {
        if (isFormInitialized && state.cardForm) {
            console.log('Heartland form is already initialized, skipping...');
            return;
        }

        if (initRetryCount >= MAX_RETRY_COUNT) {
            console.error('Maximum retry attempts reached. Heartland form initialization failed.');
            showError('Payment form could not be loaded. Please refresh the page and try again.');
            return;
        }

        const gatewayOptions = state.settings.gateway_options;

        if (gatewayOptions.error) {
            showError(gatewayOptions.message);
            return;
        }

        // Create submit button if it doesn't exist
        if (!document.querySelector(globalHelper.getSubmitButtonTargetSelector(state.settings.id))) {
            globalHelper.createSubmitButtonTarget(state.settings.id);
        }

        // Check if GlobalPayments is loaded
        if (typeof GlobalPayments === 'undefined') {
            console.warn('GlobalPayments is not loaded. Retrying in 500ms...');
            initRetryCount++;
            setTimeout(initializePaymentForm, 500);
            return;
        }

        // Check if required GlobalPayments methods exist
        if (!GlobalPayments.configure || !GlobalPayments.creditCard || !GlobalPayments.creditCard.form) {
            console.warn('GlobalPayments methods not available. Retrying in 300ms...');
            initRetryCount++;
            setTimeout(initializePaymentForm, 300);
            return;
        }

        // tony notes: cardForm

        // Check if field options are loaded
        if (
            !state.fieldOptions ||
            !state.fieldOptions["card-number-field"] ||
            !state.fieldOptions["card-expiry-field"] ||
            !state.fieldOptions["card-cvc-field"]
        ) {
            console.warn('Field options not loaded. Retrying in 200ms...');
            initRetryCount++;
            setTimeout(initializePaymentForm, 200);
            return;
        }

        // Check if the card number field container exists
        const cardNumberField = "#" + state.settings.id + "-" + state.fieldOptions["card-number-field"].class;
        const containerElement = document.querySelector(cardNumberField);

        if (!cardNumberField) {
            console.warn('Card number field container not found:', cardNumberField, 'Retrying in 200ms...');
            initRetryCount++;
            setTimeout(initializePaymentForm, 200);
            return;
        }

        // Check if form already exists in the container to prevent duplicates
        if (containerElement.children.length > 0) {
            console.log('Payment form already exists in container, marking as initialized');
            isFormInitialized = true;
            return;
        }

        // Reset retry count on successful dependency check
        initRetryCount = 0;

        try {
            GlobalPayments.configure(gatewayOptions);
            GlobalPayments.on("error", handleTokenError);

            state.cardForm = GlobalPayments.ui.form({
                fields: {
                    "card-number": {
                        placeholder: "•••• •••• •••• ••••",
                        target: "#globalpayments_heartland-card-number",
                        validationMessages: {
                            Required: 'A Card Number is required',
                            CharactersLessThan12: 'The Card Number must consist of at least 12 digits',
                            NumberIsNotValid: 'The Card Number is not valid',
                            NotAllowedCardType: 'Cannot process this card type, please use another Card'
                        },
                    },
                    "card-expiration": {
                        placeholder: "MM / YYYY",
                        target: "#globalpayments_heartland-card-expiration",
                        validationMessages: {
                            NotCompleted: 'Please enter a valid month/year',
                            YearNotValid: 'The year is not valid',
                            MonthNotValid: 'The month is not valid',
                            ExpiryDateNotValid: 'The Expiry Date is not valid',
                        }
                    },
                    "card-cvv": {
                        placeholder: "•••",
                        target: "#globalpayments_heartland-card-cvv",
                        validationMessages: {
                            CodeIsNotValid: 'The Card CVV is not valid',
                            CodeIsLessThan3Digits: 'Card CVV is too short',
                            CodeMustBe3Digits: 'Card CVV must be 3 digits',
                            AmexCodeMustBe4Digits: 'Card CVV for Amex must be 4 digits',
                        }
                    },
                    "submit": {
                        text: getButtonText(),
                        target: '#' + state.settings.id + '-card-submit'
                    }
                },
                styles: getFieldStyles()
            });

            // Mark form as initialized
            isFormInitialized = true;

            // Add event handlers only if form was successfully created
            if (state.cardForm) {
                state.cardForm.on("submit", "click", () => {
                    showSpinner();
                    disableForm();
                    globalHelper.blockOnSubmit();
                });
                state.cardForm.on("token-success", handleTokenSuccess);
                state.cardForm.on("token-error", handleTokenError);
                state.cardForm.on("error", handleTokenError);
                state.cardForm.on("card-form-validity", (isValid) => {
                    if (!isValid) {
                        globalHelper.unblockOnError();
                        hideSpinner();
                        enableForm();
                    }
                });
                state.cardForm.ready(() => {
                    globalHelper.toggleSubmitButtons();
                });
            } else {
                console.error('Failed to create GlobalPayments form');
                isFormInitialized = false;
                showError('Payment form initialization failed. Please refresh the page.');
            }
        } catch (error) {
            console.error('Error initializing GlobalPayments form:', error);
            isFormInitialized = false;
            showError('Payment system initialization failed. Please refresh the page.');
        }
    };

    /**
     * Show spinner on submit button
     */
    const showSpinner = () => {
        const btn = document.querySelector(globalHelper.getSubmitButtonTargetSelector(state.settings.id));
        if (btn) {
            btn.classList.add("wc-block-components-spinner");
            btn.classList.add("wc-block-components-checkout-step--disabled");
            btn.style.position = "relative";
        }
    };

    /**
     * Hide spinner from submit button
     */
    const hideSpinner = () => {
        const btn = document.querySelector(globalHelper.getSubmitButtonTargetSelector(state.settings.id));
        if (btn) {
            btn.classList.remove("wc-block-components-spinner");
            btn.classList.remove("wc-block-components-checkout-step--disabled");
        }
    };

    /**
     * Disable form during processing
     */
    const disableForm = () => {
        const form = document.querySelector(globalHelper.getFormSelector());
        form?.classList.add("wc-block-components-checkout-step--disabled");
        document.querySelectorAll(`${globalHelper.getFormSelector()} > *`).forEach((el) => {
            el.style.pointerEvents = "none";
        });
    };

    /**
     * Enable form after processing
     */
    const enableForm = () => {
        const form = document.querySelector(globalHelper.getFormSelector());
        form?.classList.remove("wc-block-components-checkout-step--disabled");
        document.querySelectorAll(`${globalHelper.getFormSelector()} > *`).forEach((el) => {
            el.style.pointerEvents = "";
        });
    };

    /**
     * Show error message
     */
    const showError = (message) => {
        globalHelper.dispatchError({
            message,
            cb: () => {
                hideSpinner();
                enableForm();
            },
        });
    };

    /**
     * Show field validation error
     */
    const showFieldError = (fieldKey) => {
        showError(state.settings.secure_payment_fields[fieldKey].messages.validation);
        globalHelper.unblockOnError();
    };

    /**
     * Handle token errors from GlobalPayments
     */
    const handleTokenError = (error) => {
        if (error.reasons) {
            for (let i = 0; i < error.reasons.length; i++) {
                const reason = error.reasons[i];
                if (reason.code === "NOT_AUTHENTICATED") {
                    showError("We're not able to process this payment. Please refresh the page and try again.");
                } else {
                    showError(reason.message);
                }
            }
        } else {
            showError("Something went wrong. Please contact us to get assistance.");
        }
    };

    /**
     * Validate token response
     */
    const validateTokenResponse = (tokenResponse) => {
        if (tokenResponse.details) {
            const expDate = new Date(tokenResponse.details.expiryYear, tokenResponse.details.expiryMonth - 1);
            const now = new Date();
            const currentMonth = new Date(now.getFullYear(), now.getMonth());

            if (!tokenResponse.details.expiryYear || !tokenResponse.details.expiryMonth || expDate < currentMonth) {
                showFieldError("card-expiry-field");
                return false;
            }
        }

        if (tokenResponse.details && !tokenResponse.details.cardSecurityCode) {
            showFieldError("card-cvc-field");
            return false;
        }

        return true;
    };

    /**
     * Handle successful token response
     */
    const handleTokenSuccess = (tokenResponse) => {
        if (globalHelper.hasValidationErrors()) {
            hideSpinner();
            enableForm();
            window.wp.data
                .dispatch(window.wc.wcBlocksData.VALIDATION_STORE_KEY)
                .showAllValidationErrors();
            document.querySelector(".has-error")?.scrollIntoView({
                behavior: "smooth",
                block: "center",
                inline: "start",
            });
            return;
        }

        if (!validateTokenResponse(tokenResponse)) {
            return;
        }

        state.tokenResponse = tokenResponse;
        submitPayment();
    };

    /**
     * Submit payment data
     */
    const submitPayment = () => {
        // Try to get CVV if available
        if (state.cardForm?.frames?.["card-cvv"]?.getCvv && typeof state.cardForm.frames["card-cvv"].getCvv === "function") {
            state.cardForm.frames["card-cvv"].getCvv().then((cvv) => {
                if (state.tokenResponse) {
                    state.tokenResponse.details.cardSecurityCode = cvv;
                }
                finalizePayment();
            });
        } else {
            finalizePayment();
        }
    };

    /**
     * Finalize payment by setting data and placing order
     */
    const finalizePayment = () => {
        const paymentData = { token_response: JSON.stringify(state.tokenResponse) };
        globalHelper.setPaymentMethodData(paymentData);
        globalHelper.placeOrder();
    };

    /**
     * Get button text from place order button
     */
    const getButtonText = () => {
        return document.querySelector(globalHelper.getPlaceOrderButtonSelector())?.innerText || __('Place Order', 'globalpayments-gateway-provider-for-woocommerce');
    };

    /**
     * Get styles from the WooCommerce place order button
     * Returns an object with relevant CSS properties for button styling
     */
    const getButtonStyle = () => {
        const sourceButton = document.querySelector(globalHelper.getPlaceOrderButtonSelector());

        if (!sourceButton) {
            console.error("Source button not found!");
            return getDefaultButtonStyle();
        }

        const computed = window.getComputedStyle(sourceButton);

        return {
            // Typography
            "font-family": computed.fontFamily,
            "font-size": computed.fontSize,
            "font-weight": computed.fontWeight,
            "font-style": computed.fontStyle,
            "line-height": computed.lineHeight,
            "letter-spacing": computed.letterSpacing,
            "text-transform": computed.textTransform,
            "text-align": computed.textAlign,
            "text-decoration": computed.textDecoration,

            // Colors
            "color": computed.color,
            "background": computed.background,
            "background-color": computed.backgroundColor,

            // Border
            "border": computed.border,
            "border-width": computed.borderWidth,
            "border-style": computed.borderStyle,
            "border-color": computed.borderColor,
            "border-radius": computed.borderRadius,

            // Spacing
            "padding": computed.padding,
            "padding-top": computed.paddingTop,
            "padding-right": computed.paddingRight,
            "padding-bottom": computed.paddingBottom,
            "padding-left": computed.paddingLeft,
            "margin": computed.margin,

            // Dimensions
            "width": computed.width,
            "min-width": computed.minWidth,
            "max-width": computed.maxWidth,
            "height": computed.height,
            "min-height": computed.minHeight,

            // Display & Layout
            "justify-content": computed.justifyContent,
            "align-items": computed.alignItems,

            // Effects
            "box-shadow": computed.boxShadow,
            "cursor": computed.cursor,
            "transition": computed.transition,
            "opacity": computed.opacity,
        };
    };

    /**
     * Get field styles from server settings (includes card type logos and CVV icons)
     * Falls back to basic styles if server styles are unavailable
     */
    const getFieldStyles = () => {
        if (state.settings?.field_styles) {
            try {
                return JSON.parse(state.settings.field_styles);
            } catch (e) {
                console.warn('Failed to parse field_styles, using defaults');
            }
        }
        return {
            button: getButtonStyle(),
            input: { 'min-height': '30px' }
        };
    };

    /**
     * Get default button styles as fallback
     */
    const getDefaultButtonStyle = () => {
        return {
            "font-family": "inherit",
            "font-size": "14px",
            "font-weight": "600",
            "line-height": "1.5",
            "text-transform": "none",
            "text-align": "center",
            "color": "#ffffff",
            "background-color": "#7f54b3",
            "border": "none",
            "border-radius": "4px",
            "padding": "12px 24px",
            "cursor": "pointer",
            "display": "inline-block",
        };
    };

    // Get settings for Heartland gateway
    const heartlandSettings = getSetting("globalpayments_heartland_data", {});

    // Only register if settings are available
    if (Object.keys(heartlandSettings).length > 0) {
        const heartlandTitle = decodeEntities(heartlandSettings.title);

        registerPaymentMethod({
            name: "globalpayments_heartland",
            label: createElement((props) => {
                const { PaymentMethodLabel } = props.components;
                return createElement(PaymentMethodLabel, { text: heartlandTitle });
            }, null),
            content: createElement(
                (props) => {
                    const { id, eventRegistration } = props;
                    const settings = getSetting(id + "_data", {});
                    const fields = Object.entries(settings.secure_payment_fields);
                    const { StoreNoticesContainer } = window.wc.blocksCheckout;

                    globalHelper = initHelper(settings.helper_params);
                    state.settings = settings;
                    state.fieldOptions = settings.secure_payment_fields;

                    const { onCheckoutFail } = eventRegistration;

                    useEffect(() => {
                        // Set up event listeners
                        document
                            .querySelector("#order_review, #add_payment_method")
                            ?.addEventListener("click", (e) => {
                                if (e.target.matches('#payment-method input[type="radio"]')) {
                                    globalHelper.toggleSubmitButtons();
                                }
                            });

                        document.body.addEventListener("checkout_error", () => {
                            document.querySelector("#globalpayments_heartland-token_response")?.remove();
                        });

                        // Initialize payment form
                        const initForm = () => {
                            initializePaymentForm();

                            if (!isFormInitialized) {
                                setTimeout(() => {
                                    if (!isFormInitialized) initializePaymentForm();
                                }, 500);

                                setTimeout(() => {
                                    if (!isFormInitialized) initializePaymentForm();
                                }, 1000);
                            }
                        };

                        if (
                            globalHelper.isFirstPaymentMethod(state.settings.id) ||
                            globalHelper.isOnlyGatewayMethodDisplayed(state.settings.id)
                        ) {
                            if (document.readyState === "loading") {
                                document.addEventListener("DOMContentLoaded", initForm);
                                window.addEventListener("load", initForm);
                            } else if (document.readyState === "interactive") {
                                setTimeout(initForm, 100);
                                window.addEventListener("load", initForm);
                            } else {
                                initForm();
                            }
                        }

                        // Handle payment method selection
                        const radioSelector = "#radio-control-wc-payment-method-options-globalpayments_heartland";
                        const handleChange = () => {
                            isFormInitialized = false;
                            initRetryCount = 0;
                            initializePaymentForm();
                        };

                        const radioElement = document.querySelector(radioSelector);
                        if (radioElement) {
                            radioElement.addEventListener("change", handleChange);
                        } else {
                            setTimeout(() => {
                                const delayedRadio = document.querySelector(radioSelector);
                                delayedRadio?.addEventListener("change", handleChange);
                            }, 500);
                        }

                        const unsubscribeFail = onCheckoutFail(() => {
                            enableForm();
                            hideSpinner();
                        });

                        return () => {
                            const radio = document.querySelector(
                                globalHelper.getPaymentMethodRadioSelector(state.settings.id)
                            );
                            if (!radio?.checked) {
                                document
                                    .querySelector(globalHelper.getSubmitButtonTargetSelector(state.settings.id))
                                    ?.remove();
                                globalHelper.showPlaceOrderButton();
                            }
                            unsubscribeFail();
                        };
                    }, [onCheckoutFail]);

                    // Check if gift cards are enabled
                    const allowGiftCards = settings.allow_gift_cards === true || settings.allow_gift_cards === 'yes';

                    // State for refreshing applied gift cards list and tracking gift-card-only status
                    const [giftCardRefreshKey, setGiftCardRefreshKey] = useState(0);
                    const [isGiftCardOnlyOrder, setIsGiftCardOnlyOrder] = useState(false);
                    const [initialCheckDone, setInitialCheckDone] = useState(false);

                    // Helper function to update gift card only state
                    const updateGiftCardOnlyState = useCallback((data) => {
                        if (data) {
                            const newIsGiftCardOnly = data.is_gift_card_only_order === true;
                            setIsGiftCardOnlyOrder(newIsGiftCardOnly);
                            state.isGiftCardOnlyOrder = newIsGiftCardOnly;

                            // Toggle submit buttons based on gift card only status
                            if (newIsGiftCardOnly) {
                                // Hide the custom submit button and show Place Order
                                const submitBtn = document.querySelector(globalHelper.getSubmitButtonTargetSelector(state.settings.id));
                                if (submitBtn) {
                                    submitBtn.classList.remove('is-active');
                                    submitBtn.style.display = 'none';
                                }
                                globalHelper.showPlaceOrderButton();
                            } else {
                                // Show the custom submit button for card tokenization
                                globalHelper.toggleSubmitButtons();
                            }
                        }
                    }, []);

                    // Initial check for pre-existing gift cards on component mount
                    useEffect(() => {
                        if (allowGiftCards && !initialCheckDone) {
                            setInitialCheckDone(true);
                            // Small delay to ensure cart data is ready
                            setTimeout(() => {
                                checkRemainingBalance(updateGiftCardOnlyState);
                            }, 500);
                        }
                    }, [allowGiftCards, initialCheckDone, updateGiftCardOnlyState]);

                    // Effect to check remaining balance after gift card changes
                    useEffect(() => {
                        if (allowGiftCards && giftCardRefreshKey > 0) {
                            checkRemainingBalance(updateGiftCardOnlyState);
                        }
                    }, [giftCardRefreshKey, allowGiftCards, updateGiftCardOnlyState]);

                    // Handle onPaymentSetup for gift-card-only orders
                    const { onPaymentSetup } = eventRegistration;
                    useEffect(() => {
                        if (!allowGiftCards) return;

                        const unsubscribePaymentSetup = onPaymentSetup(() => {
                            // Check current state for gift-card-only order
                            if (state.isGiftCardOnlyOrder) {
                                // For gift-card-only orders, just pass through without token
                                return {
                                    type: 'success',
                                    meta: {
                                        paymentMethodData: {
                                            gift_card_only: 'true',
                                            block_checkout: 'true',
                                        },
                                    },
                                };
                            }
                            // For normal orders with credit card, let the token flow handle it
                            return true;
                        });

                        return () => {
                            unsubscribePaymentSetup();
                        };
                    }, [onPaymentSetup, allowGiftCards]);

                    // Callback to trigger refresh of applied gift cards list
                    const handleGiftCardUpdate = useCallback(() => {
                        setGiftCardRefreshKey(prev => prev + 1);
                        triggerCheckoutUpdate();
                    }, []);

                    return createElement(
                        Fragment,
                        null,
                        createElement("div", {
                            dangerouslySetInnerHTML: {
                                __html: state.settings.environment_indicator,
                            },
                        }),
                        // Credit card fields - hide when gift cards cover entire order
                        !isGiftCardOnlyOrder && fields.map(([key, field]) =>
                            createElement(FieldComponent, { id, field, key })
                        ),
                        // Show message when gift cards cover entire order
                        isGiftCardOnlyOrder && createElement(
                            'div',
                            { className: 'heartland-gift-card-only-notice' },
                            createElement(
                                'p',
                                { style: { padding: '16px', backgroundColor: '#f0fff4', border: '1px solid #008a20', borderRadius: '4px', color: '#006617', margin: '0 0 16px 0' } },
                                __('Your gift card(s) cover the entire order total. No credit card is required.', 'globalpayments-gateway-provider-for-woocommerce')
                            )
                        ),
                        // Gift Card Section - only show if enabled
                        allowGiftCards && createElement(GiftCardSection, { 
                            settings: settings,
                            onUpdate: handleGiftCardUpdate
                        }),
                        // Applied Gift Cards List - only show if enabled
                        allowGiftCards && createElement(AppliedGiftCardsInfo, { 
                            settings: settings, 
                            refreshKey: giftCardRefreshKey 
                        }),
                        createElement(StoreNoticesContainer, { context: "heartland-context" })
                    );
                },
                { id: "globalpayments_heartland" }
            ),
            edit: createElement(
                (props) => {
                    const { id } = props;
                    const settings = getSetting(id + "_data", {});
                    const fields = Object.entries(settings.secure_payment_fields || {});

                    return createElement(
                        Fragment,
                        null,
                        createElement("div", {
                            dangerouslySetInnerHTML: {
                                __html: settings.environment_indicator || "",
                            },
                        }),
                        fields.map(([key, field]) =>
                            createElement(FieldComponent, { id, field, key })
                        )
                    );
                },
                { id: "globalpayments_heartland" }
            ),
            savedTokenComponent: null,
            canMakePayment: (cartData) => {
                const settings = getSetting("globalpayments_heartland_data", {});
                if (settings.gateway_options?.hide && settings.gateway_options?.error) {
                    console.error(settings.gateway_options.message);
                    return false;
                }
                return true;
            },
            ariaLabel: heartlandTitle,
            supports: {
                features: heartlandSettings.supports,
                showSaveOption: heartlandSettings.allow_card_saving,
            },
        });
    }
})();

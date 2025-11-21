import { useEffect, useMemo } from 'react';

const { __ } = wp.i18n;

/**
 * Hosted Payment Pages Component for WooCommerce Blocks
 * 
 * - Validates phone number for 3D Secure transactions
 * - Validates county for GB addresses
 * - Removes "(optional)" text from required fields
 * - Adds required indicators
 * - Reverts changes when payment method changes
 * 
 * Note: there is no official WooCommerce Blocks API way for making fields required,
 * so this implementation relies on DOM manipulation.
 *
 */
	const HppComponent = ({
	billing,
	shippingData,
	setValidationErrors,
	clearValidationErrors,
	onSubmit,
	activePaymentMethod,
	eventRegistration,
	emitResponse,
	enableThreeDSecure,
	hpp_nonce,
	gateway_id,
	hpp_text,
	environment_indicator,
	...otherProps
}) => {
	// Extract event registration 
	const { onPaymentSetup, onCheckoutValidation } = eventRegistration || {};
	

	const billingCountry = useMemo(
		() => billing?.billingAddress?.country,
		[billing?.billingAddress?.country]
	);
	
	const shippingCountry = useMemo(
		() => shippingData?.shippingAddress?.country,
		[shippingData?.shippingAddress?.country]
	);
	
	const isGBAddress = useMemo(
		() => billingCountry === 'GB' || shippingCountry === 'GB',
		[billingCountry, shippingCountry]
	);
	
	const validationState = useMemo(() => ({
		enableThreeDSecure,
		billingCountry,
		shippingCountry,
		isGBAddress,
		billingPhone: billing?.billingAddress?.phone,
		billingState: billing?.billingAddress?.state,
		shippingState: shippingData?.shippingAddress?.state,
		needsShipping: shippingData?.needsShipping
	}), [
		enableThreeDSecure,
		billingCountry,
		shippingCountry,
		isGBAddress,
		billing?.billingAddress?.phone,
		billing?.billingAddress?.state,
		shippingData?.shippingAddress?.state,
		shippingData?.needsShipping
	]);

	// Clear validation errors on component mount
	useEffect(() => {
		if (clearValidationErrors) {
			try {
				clearValidationErrors();
			} catch (error) {
				console.warn('GlobalPayments HPP: Failed to clear validation errors:', error);
			}
		}
	}, [clearValidationErrors]);



	useEffect(() => {
		// Store original content for restoration
		const originalContent = new Map();

		// Input field selectors
		const PHONE_SELECTOR = 'input[id*="phone"], input[name*="phone"]';
		const STATE_SELECTOR = 'input[id*="state"], select[id*="state"], input[name*="state"], select[name*="state"]';
		const PAYMENT_METHOD_SELECTOR = 'input[name="radio-control-wc-payment-method-options"]';

		// Function to store original content
		const storeOriginalContent = (element) => {
			if (!originalContent.has(element)) {
				originalContent.set(element, {
					type: 'innerHTML',
					value: element.innerHTML
				});
			}
		};

		// Helper function to remove "(optional)" text from elements
		const removeOptionalText = (element) => {
			// Remove from direct text content
			if (element.textContent.includes('(optional)')) {
				element.innerHTML = element.innerHTML.replace(/\s*\(optional\)\s*/gi, '');
			}

			// Remove text from child elements
			const optionalElements = element.querySelectorAll('*');
			optionalElements.forEach(child => {
				if (child.textContent.toLowerCase().includes('optional')) {
					// const trimmedText = child.textContent.trim();
					child.innerHTML = child.innerHTML.replace(/\s*\(optional\)\s*/gi, '');

					// if (trimmedText === '(optional)' || trimmedText === 'optional') {
					// 	child.style.display = 'none';
					// } else {
					// 	child.innerHTML = child.innerHTML.replace(/\s*\(optional\)\s*/gi, '');
					// }
				}
			});
		};

		// Adds a red required asterisk
		const addRequiredAsterisk = (label) => {
			if (!label.querySelector('.gp-required-asterisk')) {
				const asterisk = document.createElement('span');
				asterisk.className = 'gp-required-asterisk';
				asterisk.textContent = ' *';
				asterisk.style.cssText = 'color: #e2401c; font-weight: bold;';
				label.appendChild(asterisk);
			}
		};

		// Helper function to store and remove optional text
		const storeAndRemoveOptionalText = (element) => {
			if (!originalContent.has(element)) {
				storeOriginalContent(element);
			}
			removeOptionalText(element);
		};

		// Process input fields and their associated elements
		const processInputField = (input, isRequired) => {
			if (!isRequired) return;
			
			// Set HTML validation attributes
			input.setAttribute('required', 'required');
			input.setAttribute('aria-required', 'true');
			
			// Process associated labels
			const labels = document.querySelectorAll(`label[for="${input.id}"]`);
			labels.forEach(label => {
				storeAndRemoveOptionalText(label);
				addRequiredAsterisk(label);
			});
			
			// Process validation error paragraphs
			const fieldName = input.id || input.name;
			if (fieldName) {
				const validationError = document.querySelector(`#validate-error-${fieldName}`);
				if (validationError) {
					storeAndRemoveOptionalText(validationError);
				}
			}
			
			// Process parent container descriptions
			let parent = input.parentElement;
			while (parent && !parent.classList.contains('wc-block-checkout__form')) {
				const descriptions = parent.querySelectorAll('.wc-block-components-checkout-step__description');
				descriptions.forEach(desc => {
					if (desc.textContent.toLowerCase().includes('optional')) {
						storeOriginalContent(desc);
						desc.style.display = 'none';
					}
				});
				parent = parent.parentElement;
			}
		};
		
		const processFields = () => {
			// Process phone fields when 3DS is enabled
			if (enableThreeDSecure) {
				document.querySelectorAll(PHONE_SELECTOR).forEach(input => {
					processInputField(input, true);
				});
			}
			
			// Process state/county fields for GB addresses
			if (isGBAddress) {
				document.querySelectorAll(STATE_SELECTOR).forEach(input => {
					processInputField(input, true);
				});
			}
		};
		
		// Revert all field modifications
		const revertFields = () => {
			// Restore original content
			originalContent.forEach((content, element) => {
				if (element && element.parentNode) {
					if (content.type === 'innerHTML') {
						element.innerHTML = content.value;
					} else if (content.type === 'display') {
						element.style.display = content.value;
					}
				}
			});
			originalContent.clear();
			
			// Remove required attributes using combined selector
			document.querySelectorAll(`${PHONE_SELECTOR}, ${STATE_SELECTOR}`).forEach(input => {
				input.removeAttribute('required');
				input.removeAttribute('aria-required');
			});
			
			// Remove added  red asterisks
			document.querySelectorAll('.gp-required-asterisk').forEach(asterisk => {
				asterisk.remove();
			});
		};
		
		// Run immediately and once more after a delay to catch dynamically loaded content
		processFields();
		const delayedTimeout = setTimeout(processFields, 800);
		
		// Set up optimized MutationObserver to watch for DOM changes
		// like the country changing or fields being conditionally rendered
		const observer = new MutationObserver((mutations) => {
			let shouldProcessFields = false;
			
			for (const mutation of mutations) {
				if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
					for (const node of mutation.addedNodes) {
						if (node.nodeType === Node.ELEMENT_NODE && node.querySelector) {
							// Check for relevant form fields in one query
							if (node.querySelector(`${PHONE_SELECTOR}, ${STATE_SELECTOR}`)) {
								shouldProcessFields = true;
							}
							
							// Handle new payment method inputs
							node.querySelectorAll(PAYMENT_METHOD_SELECTOR).forEach(input => {
								input.addEventListener('change', paymentMethodChangeHandler);
							});
						}
					}
				}
			}
			
			if (shouldProcessFields) {
				setTimeout(processFields, 100);
			}
		});
		
		// Start observing
		observer.observe(document.body, {
			childList: true,
			subtree: true
		});
		
		// Listen for payment method changes to revert text
		const paymentMethodChangeHandler = (event) => {
			let activeMethod;
			try {
				activeMethod = window.wp?.data?.select('wc/store/payment')?.getActivePaymentMethod();
			} catch (error) {
				console.warn('GlobalPayments HPP: Payment Store access failed:', error);
			}
			
			// Fallback to DOM query if store method fails
			const fallbackMethod = document.querySelector('input[name="radio-control-wc-payment-method-options"]:checked')?.value;
			const currentMethod = activeMethod || fallbackMethod;
			
			// Check if payment method changed away from the GlobalPayments gateway
			if (currentMethod && !currentMethod.includes('globalpayments')) {
				revertFields();
			} else {
				// Re-process fields if switching back to our payment method
				setTimeout(processFields, 100);
			}
		};
		
		// Add listeners for payment method changes using event delegation
		const paymentMethodInputs = document.querySelectorAll(PAYMENT_METHOD_SELECTOR);
		paymentMethodInputs.forEach(input => {
			input.addEventListener('change', paymentMethodChangeHandler);
		});
		
		return () => {
			clearTimeout(delayedTimeout);
			observer.disconnect();
			
			// Remove payment method change listeners
			paymentMethodInputs.forEach(input => {
				input.removeEventListener('change', paymentMethodChangeHandler);
			});
			
			// Revert all changes
			revertFields();
		};
				}, [enableThreeDSecure, isGBAddress]);

	// Checkout validation for error messages
	useEffect(() => {
		if (!onCheckoutValidation) return;
		
		const validateCheckout = () => {
			const errors = [];
			
			// Validate phone number for 3DS
			if (validationState.enableThreeDSecure) {
				const phone = validationState.billingPhone || '';
				if (!phone.trim()) {
					errors.push({
						errorId: 'billing_phone_required',
						message: __(
							'Billing phone number is required for secure payments.',
							'globalpayments-gateway-provider-for-woocommerce'
						),
						validationErrorId: 'billing_phone'
					});
				}
			}

			// Validate county for GB addresses
			if (validationState.billingCountry === 'GB' && !(validationState.billingState || '').trim()) {
				errors.push({
					errorId: 'billing_state_required_gb',
					message: __(
						'County is required for UK billing addresses.',
						'globalpayments-gateway-provider-for-woocommerce'
					),
					validationErrorId: 'billing_state'
				});
			}

			if (validationState.shippingCountry === 'GB' && validationState.needsShipping && !(validationState.shippingState || '').trim()) {
				errors.push({
					errorId: 'shipping_state_required_gb',
					message: __(
						'County is required for UK shipping addresses.',
						'globalpayments-gateway-provider-for-woocommerce'
					),
					validationErrorId: 'shipping_state'
				});
			}
			
			return errors.length > 0 ? errors : true;
		};
		
		const unsubscribeValidation = onCheckoutValidation(validateCheckout);
		
		return unsubscribeValidation;
	}, [
		onCheckoutValidation,
		validationState
	]);

	// Payment validation and setup
	useEffect(() => {
		// Payment setup - return payment method data for processing
		const handlePaymentSetup = () => {
			// Return success with HPP payment data
			return {
				type: emitResponse.responseTypes.SUCCESS,
				meta: {
					paymentMethodData: {
						payment_method: activePaymentMethod || 'globalpayments_gpapi',
						payment_interface: 'hpp',
						gp_hpp_nonce: hpp_nonce || '',
						gateway_id: gateway_id || 'globalpayments_gpapi',
						checkout_validated: '1'
					}
				}
			};
		};
		
		const unsubscribe = onPaymentSetup(handlePaymentSetup);

		return unsubscribe;
	}, [
		onPaymentSetup,
		emitResponse.responseTypes,
		hpp_nonce,
		gateway_id,
		activePaymentMethod
	]);

	return (
		<div className="gp-hpp-payment-method">
			{environment_indicator && (
				<div dangerouslySetInnerHTML={{ __html: environment_indicator }} />
			)}
			<div className="gp-hpp-description">
				<p>
					{hpp_text || __(
						'Pay With Credit / Debit Card Via Globalpayments',
						'globalpayments-gateway-provider-for-woocommerce'
					)}
				</p>
			</div>
		</div>
	);
};

HppComponent.displayName = 'Globalpayments.HppComponent';

export { HppComponent };

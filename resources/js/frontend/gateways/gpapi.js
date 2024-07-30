import { BuyNowPayLater } from '../content/buyNowPayLater';
import { CreditCard } from '../content/creditCard';
import { DigitalWallets } from '../content/digitalWallets';
import { OpenBankingComponent } from '../components/openBanking/openBankingComponent';
import { PaypalComponent } from '../components/apm/paypalComponent';
import { SavedTokenComponent } from '../components/savedTokenComponent';

const { __ } = wp.i18n;

export const gpapiPaymentMethods = [ {
		id: 'globalpayments_gpapi',
		Content: <CreditCard id={ 'globalpayments_gpapi' } />,
		SavedTokenComponent: <SavedTokenComponent id={ 'globalpayments_gpapi' } />,
		canMakePayment: ( settings ) => {
			if ( settings.gateway_options.hide && settings.gateway_options.error ) {
				console.error( settings.gateway_options.message );

				return false;
			}

			return true;
		},
	}, {
		id: 'globalpayments_clicktopay',
		Content: <DigitalWallets.ClickToPayComponent />,
		SavedTokenComponent: null,
		canMakePayment: ( settings ) => {
			if ( settings.payment_method_options.error ) {
				console.error( settings.payment_method_options.message );

				return false;
			}

			return true;
		},
	}, {
		id: 'globalpayments_googlepay',
		Content: <DigitalWallets.GooglePayComponent />,
		SavedTokenComponent: null,
		canMakePayment: () => true,
	}, {
		id: 'globalpayments_applepay',
		Content: <DigitalWallets.ApplePayComponent />,
		SavedTokenComponent: null,
		canMakePayment: () => {
			if ( 'https:' !== location.protocol ) {
				console.warn( __( 'Apple Pay requires your checkout be served over HTTPS', 'globalpayments-gateway-provider-for-woocommerce' ) );
				return false;
			}

			if ( true !== ( window.ApplePaySession && ApplePaySession.canMakePayments() ) ) {
				console.warn( __( 'Apple Pay is not supported on this device/browser', 'globalpayments-gateway-provider-for-woocommerce' ) );
				return false;
			}

			return true;
		},
	}, {
		id: 'globalpayments_affirm',
		Content: <BuyNowPayLater.AffirmComponent id={ 'globalpayments_affirm' } />,
		SavedTokenComponent: null,
		canMakePayment: () => true,
	}, {
		id: 'globalpayments_klarna',
		Content: <BuyNowPayLater.KlarnaComponent id={ 'globalpayments_klarna' } />,
		SavedTokenComponent: null,
		canMakePayment: () => true,
	}, {
		id: 'globalpayments_bankpayment',
		Content: <OpenBankingComponent id={ 'globalpayments_bankpayment' } />,
		SavedTokenComponent: null,
		canMakePayment: () => true,
	}, {
		id: 'globalpayments_paypal',
		Content: <PaypalComponent id={ 'globalpayments_paypal' } />,
		SavedTokenComponent: null,
		canMakePayment: () => true,
	},
];

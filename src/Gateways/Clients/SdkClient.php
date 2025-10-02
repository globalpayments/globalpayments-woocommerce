<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Clients;

use GlobalPayments\Api\Builders\TransactionBuilder;
use GlobalPayments\Api\Entities\Address;
use GlobalPayments\Api\Entities\Enums\Secure3dStatus;
use GlobalPayments\Api\Entities\Exceptions\ApiException;
use GlobalPayments\Api\Entities\Transaction;
use GlobalPayments\Api\Entities\Enums\AddressType;
use GlobalPayments\Api\Entities\Enums\CardType;
use GlobalPayments\Api\Entities\Enums\GatewayProvider;
use GlobalPayments\Api\Entities\Enums\StoredCredentialInitiator;
use GlobalPayments\Api\Entities\GpApi\AccessTokenInfo;
use GlobalPayments\Api\Entities\StoredCredential;
use GlobalPayments\Api\Gateways\IPaymentGateway;
use GlobalPayments\Api\PaymentMethods\CreditCardData;
use GlobalPayments\Api\PaymentMethods\Interfaces\IAuthable;
use GlobalPayments\Api\PaymentMethods\Interfaces\IChargable;
use GlobalPayments\Api\ServiceConfigs\AcceptorConfig;
use GlobalPayments\Api\ServiceConfigs\Gateways\GeniusConfig;
use GlobalPayments\Api\ServiceConfigs\Gateways\GpApiConfig;
use GlobalPayments\Api\ServiceConfigs\Gateways\PorticoConfig;
use GlobalPayments\Api\ServiceConfigs\Gateways\TransactionApiConfig;
use GlobalPayments\Api\ServiceConfigs\Gateways\TransitConfig;
use GlobalPayments\Api\Services\ReportingService;
use GlobalPayments\Api\Services\Secure3dService;
use GlobalPayments\Api\ServicesContainer;
use GlobalPayments\Api\Utils\Logging\Logger;
use GlobalPayments\Api\Utils\Logging\SampleRequestLogger;
use GlobalPayments\WooCommercePaymentGatewayProvider\Data\PaymentTokenData;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\AbstractGateway;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Requests\RequestArg;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Requests\RequestInterface;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Requests\ThreeDSecure\AbstractAuthenticationsRequest;
use GlobalPayments\WooCommercePaymentGatewayProvider\Utils\Utils;
use WC_Payment_Token_CC;

defined( 'ABSPATH' ) || exit;

class SdkClient implements ClientInterface {
	/**
	 * Current request args
	 *
	 * @var array
	 */
	protected $args = array();

	/**
	 * Prepared builder args
	 *
	 * @var array
	 */
	protected $builder_args = array();

	protected $auth_transactions = array(
		AbstractGateway::TXN_TYPE_AUTHORIZE,
		AbstractGateway::TXN_TYPE_SALE,
		AbstractGateway::TXN_TYPE_VERIFY,
	);

	protected $client_transactions = array(
		AbstractGateway::TXN_TYPE_CREATE_TRANSACTION_KEY,
		AbstractGateway::TXN_TYPE_CREATE_MANIFEST,
		AbstractGateway::TXN_TYPE_GET_ACCESS_TOKEN,
	);

	protected $refund_transactions = array(
		AbstractGateway::TXN_TYPE_REFUND,
		AbstractGateway::TXN_TYPE_REVERSAL,
		AbstractGateway::TXN_TYPE_VOID,
	);

	protected $three_d_secure_auth_status = array(
		Secure3dStatus::NOT_ENROLLED,
		Secure3dStatus::SUCCESS_AUTHENTICATED,
		Secure3dStatus::SUCCESS_ATTEMPT_MADE,
	);

	/**
	 * Card data
	 *
	 * @var CreditCardData
	 */
	protected $card_data = null;

	/**
	 * Previous transaction
	 *
	 * @var Transaction
	 */
	protected $previous_transaction = null;

	public function set_request( RequestInterface $request ) {
		$this->prepare_request_args( $request );
		$this->prepare_request_objects();

		return $this;
	}

	public function execute() {
		$this->configure_sdk();
		$builder = $this->get_transaction_builder();
		if ( ! isset( $builder ) ) {
			throw new \Exception( esc_html__( 'Unable to perform request.', 'globalpayments-gateway-provider-for-woocommerce' ) );
		}
		if ( 'transactionDetail' === $this->args['TXN_TYPE'] ) {
			return $builder->execute();
		}

		if ( ! ( $builder instanceof TransactionBuilder ) ) {
			return $builder->{$this->get_arg( RequestArg::TXN_TYPE )}();
		}

		$this->prepare_builder( $builder );
		if ( $this->threedsecure_is_enabled() ) {
			$this->set_threedsecure_data();
		}
		$response = $builder->execute();
		if (
			$this->is_gateway(GatewayProvider::PORTICO)
			&& !is_null($this->card_data)
			&& $response instanceof Transaction
			&& $response->token
		) {
			$this->card_data->token = $response->token;
			$this->card_data->updateTokenExpiry();
		}

		return $response;
	}

	protected function is_gateway($gatewayProvider)
	{
		return isset($this->args['SERVICES_CONFIG'])
			&& isset($this->args['SERVICES_CONFIG']['gatewayProvider'])
			&& $this->args['SERVICES_CONFIG']['gatewayProvider'] === $gatewayProvider;
	}

	public function submit_request( RequestInterface $request ) {
		$this->prepare_request_args( $request );
		$this->configure_sdk();

		return $request->do_request();
	}

	protected function prepare_builder( TransactionBuilder $builder ) {
		foreach ( $this->builder_args as $name => $args ) {
			$method = 'with' . ucfirst( $name );

			if ( ! method_exists( $builder, $method ) ) {
				continue;
			}

			call_user_func_array( array( $builder, $method ), $args );
		}
	}

	/**
	 * Gets required builder for the transaction
	 *
	 * @return TransactionBuilder|IPaymentGateway
	 */
	protected function get_transaction_builder() {
		if ( in_array( $this->get_arg( RequestArg::TXN_TYPE ), $this->client_transactions, true ) ) {
			return ServicesContainer::instance()->getClient( 'default' ); // this value should always be safe here
		}

		if ( $this->get_arg( RequestArg::TXN_TYPE ) === 'transactionDetail' ) {
			return ReportingService::transactionDetail( $this->get_arg( 'GATEWAY_ID' ) );
		}

		if ( in_array( $this->get_arg( RequestArg::TXN_TYPE ), $this->refund_transactions, true ) ) {
			$subject = Transaction::fromId( $this->get_arg( 'GATEWAY_ID' ) );

			return $subject->{$this->get_arg( RequestArg::TXN_TYPE )}();
		}

		if ( $this->get_arg( RequestArg::TXN_TYPE ) === 'capture' ) {
			$subject = Transaction::fromId( $this->get_arg( 'GATEWAY_ID' ) );

			return $subject->{$this->get_arg( RequestArg::TXN_TYPE )}();
		}

		$subject =
			in_array( $this->get_arg( RequestArg::TXN_TYPE ), $this->auth_transactions, true )
				? $this->card_data : $this->previous_transaction;

		if ( $subject instanceof IChargable || $subject instanceof IAuthable ) {
			return $subject->{$this->get_arg( RequestArg::TXN_TYPE )}();
		}
	}

	protected function prepare_request_args( RequestInterface $request ) {
		$this->args = array_merge(
			$request->get_default_args(),
			$request->get_args()
		);

		$paymentData = $request->get_request_data( 'payment_data' );
		if ( isset( $paymentData ) ) {
			$serverTransId = Utils::get_data_from_payment_data( $paymentData, 'serverTransId' );
			if ( isset( $serverTransId ) && ! $this->has_arg( RequestArg::SERVER_TRANS_ID )) {
				$this->args[ RequestArg::SERVER_TRANS_ID ] = $serverTransId;
			}
		}
	}

	protected function prepare_request_objects() {
		if ( $this->has_arg( RequestArg::AMOUNT ) ) {
			$this->builder_args['amount'] = array( $this->get_arg( RequestArg::AMOUNT ) );
		}

		if ( $this->has_arg( RequestArg::CURRENCY ) ) {
			$this->builder_args['currency'] = array( $this->get_arg( RequestArg::CURRENCY ) );
		}

		if ( $this->has_arg( RequestArg::ORDER_ID ) ) {
			$this->builder_args['orderId'] = array( $this->get_arg( RequestArg::ORDER_ID ) );
		}

		$token = null;
		if ( $this->has_arg( RequestArg::CARD_DATA ) ) {
			/**
			 * Get the request's single- or multi-use token
			 *
			 * @var \WC_Payment_Token_Cc $token
			 */
			$token = $this->get_arg( RequestArg::CARD_DATA );
			$this->prepare_card_data( $token );

			if ( null !== $token && $this->has_arg( RequestArg::CARD_HOLDER_NAME ) ) {
				$this->card_data->cardHolderName = $this->get_arg( RequestArg::CARD_HOLDER_NAME );
			}

			if (
				null !== $token &&
				$token instanceof \WC_Payment_Token_CC &&
				$token->get_meta( PaymentTokenData::KEY_SHOULD_SAVE_TOKEN, true )
			) {
				$user_id = get_current_user_id();
				$existing_tokens = \WC_Payment_Tokens::get_customer_tokens( $user_id );
				$already_saved = false;

				foreach ( $existing_tokens as $existing_token ) {
					if (
						$existing_token instanceof \WC_Payment_Token_CC &&
							$existing_token->get_last4() === $token->get_last4() &&
							$existing_token->get_expiry_month() === $token->get_expiry_month() &&
							$existing_token->get_expiry_year() === $token->get_expiry_year()
					) {
						$already_saved = true;
						break;
					}
				}
				if ( !$already_saved ) {
					$this->builder_args['requestMultiUseToken'] = array( true );
				}
			}
		}
		// Checks if order contains a subscription and requests a muti-use token.
		if ( function_exists( "wcs_order_contains_subscription" ) ) {
			if ( isset( $this->builder_args['orderId'] ) && wcs_order_contains_subscription( wc_get_order( $this->builder_args['orderId'][0] ) ) ) {
				$this->builder_args['requestMultiUseToken'] = array( true );
			}
		}

		if ( $this->has_arg( RequestArg::DIGITAL_WALLET_TOKEN ) ) {
			$this->card_data             = new CreditCardData();
			$this->card_data->token      = $this->get_arg( RequestArg::DIGITAL_WALLET_TOKEN );
			$this->card_data->mobileType = $this->get_arg( RequestArg::MOBILE_TYPE );
		}

		if ( $this->has_arg( RequestArg::PAYMENT_METHOD_USAGE ) ) {
		    $this->builder_args['paymentMethodUsageMode'] = array( $this->get_arg( RequestArg::PAYMENT_METHOD_USAGE ) );
		}

		if ( $this->has_arg( RequestArg::TRANSACTION_MODIFIER ) ) {
			$this->builder_args['modifier'] = array( $this->get_arg( RequestArg::TRANSACTION_MODIFIER ) );
		}

		if ( $this->has_arg( RequestArg::BILLING_ADDRESS ) ) {
			$this->prepare_address( AddressType::BILLING, $this->get_arg( RequestArg::BILLING_ADDRESS ) );
		}

		if ( $this->has_arg( RequestArg::SHIPPING_ADDRESS ) ) {
			$this->prepare_address( AddressType::SHIPPING, $this->get_arg( RequestArg::SHIPPING_ADDRESS ) );
		}

		if ( $this->has_arg( RequestArg::DESCRIPTION ) ) {
			$this->builder_args['description'] = array( $this->get_arg( RequestArg::DESCRIPTION ) );
		}

		if ( $this->has_arg( RequestArg::DYNAMIC_DESCRIPTOR ) ) {
			$this->builder_args['dynamicDescriptor'] = array( $this->get_arg( RequestArg::DYNAMIC_DESCRIPTOR ) );
		}

		if ( $this->has_arg( RequestArg::AUTH_AMOUNT ) ) {
			$this->builder_args['authAmount'] = array( $this->get_arg( RequestArg::AUTH_AMOUNT ) );
		}

		if ( $token !== null ) {
			$is_first = ( $token->get_id() === 0 );

			$this->builder_args['storedCredential'] = array(
				$this->prepare_stored_credential_data( $token->get_meta( 'card_brand_txn_id' ), $is_first )
			);
		}
	}

	protected function prepare_stored_credential_data( $card_brand_txn_id, $is_first ) {
		$storedCredsDetails                         = new StoredCredential();
		$storedCredsDetails->initiator              = StoredCredentialInitiator::CARDHOLDER;
		$storedCredsDetails->cardBrandTransactionId = $card_brand_txn_id;
		$storedCredsDetails->type                   = 'UNSCHEDULED';
		$storedCredsDetails->sequence               = $is_first ? 'FIRST' : 'SUBSEQUENT';

		return $storedCredsDetails;
	}

	protected function prepare_card_data( WC_Payment_Token_CC $token = null ) {
		if ( null === $token ) {
			return;
		}

		$this->card_data           = new CreditCardData();
		$this->card_data->token    = $token->get_token();
		$this->card_data->expMonth = $token->get_expiry_month();
		$this->card_data->expYear  = $token->get_expiry_year();

		/**
		 * $token->get_card_type() will return one of:
		 * "visa"
		 * "mastercard"
		 * "american express"
		 * "discover"
		 * "diners"
		 * "jcb"
		 */

		/**
		 * Defaulting to Discover since it's currently the only card type not
		 * returned by JS library in the case of Discover CUP cards.
		 */
		$this->card_data->cardType = CardType::DISCOVER;

		// map for use with GlobalPayments SDK
		switch ( $token->get_card_type() ) {
			case "visa":
				$this->card_data->cardType = CardType::VISA;
				break;
			case "mastercard":
				$this->card_data->cardType = CardType::MASTERCARD;
				break;
			case "american express":
				$this->card_data->cardType = CardType::AMEX;
				break;
			case "discover":
				$this->card_data->cardType = CardType::DISCOVER;
				break;
			case "diners":
				$this->card_data->cardType = CardType::DINERS;
				break;
			case "jcb":
				$this->card_data->cardType = CardType::JCB;
				break;
		}

		if ( isset( PaymentTokenData::$tsepCvv ) ) {
			$this->card_data->cvn = PaymentTokenData::$tsepCvv;
		}

		if ( $this->has_arg( RequestArg::ENTRY_MODE ) ) {
			$this->card_data->entryMethod = $this->get_arg( RequestArg::ENTRY_MODE );
		}
	}

	protected function threedsecure_is_enabled() {
		return $this->has_arg( RequestArg::SERVER_TRANS_ID );
	}

	protected function set_threedsecure_data() {
		try {
			$threeDSecureData = Secure3dService::getAuthenticationData()
			                                   ->withServerTransactionId( $this->get_arg( RequestArg::SERVER_TRANS_ID ) )
			                                   ->execute();
		} catch ( \Exception $e ) {
			throw new ApiException( esc_html__( '3DS Authentication failed. Please try again.', 'globalpayments-gateway-provider-for-woocommerce' ) );
		}
		if ( AbstractAuthenticationsRequest::YES !== $threeDSecureData->liabilityShift
		     || ! in_array( $threeDSecureData->status, $this->three_d_secure_auth_status ) ) {
			throw new ApiException( esc_html__( '3DS Authentication failed. Please try again.', 'globalpayments-gateway-provider-for-woocommerce' ) );
		}
		$this->card_data->threeDSecure = $threeDSecureData;
	}

	protected function prepare_address( $address_type, array $data ) {
		$address       = new Address();
		$address->type = $address_type;
		$address       = $this->set_object_data( $address, $data );

		$this->builder_args['address'] = array( $address, $address_type );
	}

	protected function has_arg( $arg_type ) {
		return isset( $this->args[ $arg_type ] );
	}

	protected function get_arg( $arg_type ) {
		return $this->args[ $arg_type ];
	}

	protected function configure_sdk() {
		switch ( $this->args['SERVICES_CONFIG']['gatewayProvider'] ) {
			case GatewayProvider::PORTICO:
				$gatewayConfig = new PorticoConfig();
				break;
			case GatewayProvider::TRANSIT:
				$gatewayConfig                 = new TransitConfig();
				$gatewayConfig->acceptorConfig = new AcceptorConfig(); // defaults should work here
				if ( $this->get_arg( RequestArg::TXN_TYPE ) === AbstractGateway::TXN_TYPE_CREATE_MANIFEST ) {
					$gatewayConfig->deviceId = $this->get_arg( RequestArg::SERVICES_CONFIG )['tsepDeviceId'];
				}
				break;
			case GatewayProvider::GENIUS:
				$gatewayConfig = new GeniusConfig();
				break;
			case GatewayProvider::GP_API:
				$gatewayConfig = new GpApiConfig();
				if ( $this->has_arg( RequestArg::PERMISSIONS ) ) {
					$gatewayConfig->permissions = $this->get_arg( RequestArg::PERMISSIONS );
				}
				if ( $this->has_arg( RequestArg::SECONDS_TO_EXPIRE ) ) {
					$gatewayConfig->secondsToExpire = $this->get_arg( RequestArg::SECONDS_TO_EXPIRE );
				}
				$account_name = $this->get_arg( RequestArg::SERVICES_CONFIG )['accountName'] ?? null;
				if ( ! empty( $account_name ) ) {
					$access_token_info = new AccessTokenInfo();
					$access_token_info->transactionProcessingAccountName = $account_name;
					$gatewayConfig->accessTokenInfo = $access_token_info;
				}
				break;
			case GatewayProvider::TRANSACTION_API:
				$gatewayConfig = new TransactionApiConfig();
				break;
		}
		$config = $this->set_object_data(
			$gatewayConfig,
			$this->args[ RequestArg::SERVICES_CONFIG ]
		);
		if ( $this->get_arg( RequestArg::SERVICES_CONFIG )['debug'] ) {
			$gatewayConfig->requestLogger = new SampleRequestLogger( new Logger(WC_LOG_DIR ) );
		}

		ServicesContainer::configureService( $config );
	}

	protected function set_object_data( $obj, array $data ) {
		foreach ( $data as $key => $value ) {
			if ( property_exists( $obj, $key ) ) {
				if (
					$key === 'deviceId' &&
					$this->get_arg( RequestArg::TXN_TYPE ) === AbstractGateway::TXN_TYPE_CREATE_MANIFEST
				) {
					continue;
				}
				$obj->{$key} = $value;
			}
		}

		return $obj;
	}
}

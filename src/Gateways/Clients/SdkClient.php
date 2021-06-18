<?php

namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Clients;

use GlobalPayments\Api\Builders\TransactionBuilder;
use GlobalPayments\Api\Entities\Address;
use GlobalPayments\Api\Entities\Exceptions\ApiException;
use GlobalPayments\Api\Entities\Transaction;
use GlobalPayments\Api\Entities\Enums\AddressType;
use GlobalPayments\Api\Entities\Enums\CardType;
use GlobalPayments\Api\Entities\Enums\GatewayProvider;
use GlobalPayments\Api\Entities\Enums\StoredCredentialInitiator;
use GlobalPayments\Api\Entities\StoredCredential;
use GlobalPayments\Api\Gateways\IPaymentGateway;
use GlobalPayments\Api\PaymentMethods\CreditCardData;
use GlobalPayments\Api\ServiceConfigs\AcceptorConfig;
use GlobalPayments\Api\ServiceConfigs\Gateways\GeniusConfig;
use GlobalPayments\Api\ServiceConfigs\Gateways\GpApiConfig;
use GlobalPayments\Api\ServiceConfigs\Gateways\PorticoConfig;
use GlobalPayments\Api\ServiceConfigs\Gateways\TransitConfig;
use GlobalPayments\Api\Services\ReportingService;
use GlobalPayments\Api\Services\Secure3dService;
use GlobalPayments\Api\ServicesContainer;
use GlobalPayments\WooCommercePaymentGatewayProvider\Data\PaymentTokenData;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\AbstractGateway;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Requests\RequestArg;
use GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Requests\RequestInterface;

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
		if ( ! is_null( $this->card_data ) && $response instanceof Transaction && $response->token ) {
			$this->card_data->token = $response->token;
			$this->card_data->updateTokenExpiry();
		}

		return $response;
	}

	public function submit_request( RequestInterface $request ) {
		$this->prepare_request_args( $request );
		$this->configure_sdk();

		$request->do_request();
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
		return $subject->{$this->get_arg( RequestArg::TXN_TYPE )}();
	}

	protected function prepare_request_args( RequestInterface $request ) {
		$this->args = array_merge(
			$request->get_default_args(),
			$request->get_args()
		);
	}

	protected function prepare_request_objects() {
		if ( $this->has_arg( RequestArg::AMOUNT ) ) {
			$this->builder_args['amount'] = array( $this->get_arg( RequestArg::AMOUNT ) );
		}

		if ( $this->has_arg( RequestArg::CURRENCY ) ) {
			$this->builder_args['currency'] = array( $this->get_arg( RequestArg::CURRENCY ) );
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

			if ( null !== $token && $token->get_meta( PaymentTokenData::KEY_SHOULD_SAVE_TOKEN, true ) ) {
				$this->builder_args['requestMultiUseToken'] = array( true );
			}
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

		if ( $this->has_arg( RequestArg::AUTH_AMOUNT ) ) {
			$this->builder_args['authAmount'] = array( $this->get_arg( RequestArg::AUTH_AMOUNT ) );
		}

		if ( $token !== null && !empty( $token->get_meta( 'card_brand_txn_id' ) ) ) {
			$this->prepare_stored_credential_data( $token->get_meta( 'card_brand_txn_id' ) );
		}
	}

	protected function prepare_stored_credential_data( $card_brand_txn_id ) {
		$storedCredsDetails = new StoredCredential();
		$storedCredsDetails->initiator = StoredCredentialInitiator::CARDHOLDER;
		$storedCredsDetails->cardBrandTransactionId = $card_brand_txn_id;
		
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
		switch( $token->get_card_type() ) {
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
	}

	protected function threedsecure_is_enabled() {
		return $this->has_arg( RequestArg::SERVER_TRANS_ID );
	}

	protected function set_threedsecure_data() {
		$threeDSecureData = Secure3dService::getAuthenticationData()
			->withServerTransactionId( $this->get_arg( RequestArg::SERVER_TRANS_ID ) )
			->withPayerAuthenticationResponse( $this->get_arg( RequestArg::PARES ) )
			->execute();

		if ( ! in_array( $threeDSecureData->eci, ["01", "02", "05", "06"] ) ) {
			throw new ApiException( __( '3DS authentication failed' ) );
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
				$gatewayConfig = new TransitConfig();
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
				break;
		}
		$config = $this->set_object_data(
			$gatewayConfig,
			$this->args[ RequestArg::SERVICES_CONFIG ]
		);

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

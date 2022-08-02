/*global jQuery */
( function( $ ) {
	'use strict';

	/**
	 * WooCommerce Backbone Global Payments Modal plugin
	 *
	 * @param {object} options
	 */
	$.fn.WCGlobalPaymentsPayOrderBackboneModal = function( options ) {
		return this.each( function() {
			( new $.WCGlobalPaymentsPayOrderBackboneModal( $( this ), options ) );
		} );
	};

	/**
	 * Initialize the Backbone Modal
	 *
	 * @param {object} element [description]
	 * @param {object} options [description]
	 */
	$.WCGlobalPaymentsPayOrderBackboneModal = function( element, options ) {
		var settings = $.extend( {}, $.WCBackboneModal.defaultOptions, options );

		if ( settings.template ) {
			new $.WCGlobalPaymentsPayOrderBackboneModal.View( {
				target: settings.template,
				string: settings.variable
			} );
		}
	};

	$.WCGlobalPaymentsPayOrderBackboneModal.View = $.WCBackboneModal.View.extend( {
		events: _.extend( $.WCBackboneModal.View.prototype.events, {
			'click #place_order': 'payOrder'
		} ),
		payOrder: function( e ) {
			e.preventDefault();
			this.block();
			$.ajax( {
				url: globalpayments_admin_params.payorder_url,
				method: 'POST',
				data: this.getFormData(),
			} ).done( function ( response ) {
				if ( response.error ) {
					this.unblock();
					$( document.body ).trigger( 'globalpayments_pay_order_modal_error', [ response.message ] );
				} else {
					window.location.href = window.location.href;
				}
			}.bind( this ) ).fail( function ( xhr, textStatus, errorThrown ) {
				this.unblock();
				$( document.body ).trigger( 'globalpayments_pay_order_modal_error', [ errorThrown ] );
			}.bind( this ) )
				$( document.body ).trigger( 'globalpayments_pay_order_modal_response', [ this ] );
		},
		block: function() {
			this.$el.find( '.wc-backbone-modal-content' ).block( {
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			} );
		},
		unblock: function() {
			this.$el.find( '.wc-backbone-modal-content' ).unblock();
		},
	} );
} )( jQuery );

<?php
namespace GlobalPayments\WooCommercePaymentGatewayProvider\Gateways\Traits;

trait MulticheckboxTrait {
	/**
	 * Generate multiselectcheckbox HTML.
	 *
	 * @param string $key Field key.
	 * @param array  $data Field data.
	 * @since  1.0.0
	 * @return string
	 */
	public function generate_multiselectcheckbox_html( $key, $data ) {
		$field_key = $this->get_field_key( $key );
		$defaults  = array(
			'title'             => '',
			'disabled'          => false,
			'class'             => '',
			'css'               => '',
			'placeholder'       => '',
			'type'              => 'text',
			'desc_tip'          => false,
			'description'       => '',
			'custom_attributes' => array(),
			'select_buttons'    => false,
			'options'           => array(),
		);

		$data  = wp_parse_args( $data, $defaults );
		$value = (array) $this->get_option( $key, array() );
		ob_start();

		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?> <?php echo esc_html($this->get_tooltip_html( $data )); // WPCS: XSS ok. ?></label>
			</th>
			<td class="forminp">
				<fieldset>
					<legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
					<ul class="ul-multicheckbox">
						<?php foreach ( (array) $data['options'] as $option_key => $option_value ) : ?>
							<li>
								<input type="checkbox" id="<?php echo esc_attr( $field_key );echo esc_attr( $option_key ); ?>" name="<?php echo esc_attr( $field_key ); ?>[]" value="<?php echo esc_attr( $option_key ); ?>"
									class="<?php echo esc_html($data['class']) ?>"
                                    <?php checked( in_array( (string) $option_key, $value, true ), true  ); ?> />
								<label for="<?php echo esc_attr( $field_key );echo esc_attr( $option_key ); ?>"><?php echo esc_html( $option_value ); ?></label>
							</li>
						<?php endforeach; ?>
					</ul>
					<?php echo wp_kses_post( $this->get_description_html( $data ) ); // WPCS: XSS ok. ?>
					<?php if ( $data['select_buttons'] ) : ?>
						<br/><a class="select_all button" href="#"><?php esc_html_e( 'Select all', 'globalpayments-gateway-provider-for-woocommerce' ); ?></a> <a class="select_none button" href="#"><?php esc_html_e( 'Select none', 'globalpayments-gateway-provider-for-woocommerce' ); ?></a>
					<?php endif; ?>
				</fieldset>
			</td>
		</tr>
		<?php

		return ob_get_clean();
	}

	/**
	 * Validate multiselectcheckbox Field.
	 *
	 * @param  string $key Field key.
	 * @param  string $value Posted Value.
	 * @return string|array
	 */
	public function validate_multiselectcheckbox_field( $key, $value ) {
		return is_array( $value ) ? array_map( 'wc_clean', array_map( 'stripslashes', $value ) ) : '';
	}
}


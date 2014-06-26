<?php

class Affiliate_WP_Settings {

	private $options;

	/**
	 * Get things started
	 *
	 * @since 1.0
	 * @return void
	*/
	public function __construct() {

		$this->options = get_option( 'affwp_settings', array() );

		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_init', array( $this, 'activate_license' ) );
		add_action( 'admin_init', array( $this, 'deactivate_license' ) );
		add_action( 'admin_init', array( $this, 'check_license' ) );

	}

	/**
	 * Get the value of a specific setting
	 *
	 * @since 1.0
	 * @return mixed
	*/
	public function get( $key, $default = false ) {
		$value = ! empty( $this->options[ $key ] ) ? $this->options[ $key ] : $default;
		return $value;
	}

	/**
	 * Get all settings
	 *
	 * @since 1.0
	 * @return array
	*/
	public function get_all() {
		return $this->options;
	}

	/**
	 * Add all settings sections and fields
	 *
	 * @since 1.0
	 * @return void
	*/
	function register_settings() {

		if ( false == get_option( 'affwp_settings' ) ) {
			add_option( 'affwp_settings' );
		}

		foreach( $this->get_registered_settings() as $tab => $settings ) {

			add_settings_section(
				'affwp_settings_' . $tab,
				__return_null(),
				'__return_false',
				'affwp_settings_' . $tab
			);

			foreach ( $settings as $key => $option ) {

				$name = isset( $option['name'] ) ? $option['name'] : '';

				add_settings_field(
					'affwp_settings[' . $key . ']',
					$name,
					is_callable( array( $this, $option[ 'type' ] . '_callback' ) ) ? array( $this, $option[ 'type' ] . '_callback' ) : array( $this, 'missing_callback' ),
					'affwp_settings_' . $tab,
					'affwp_settings_' . $tab,
					array(
						'id'      => $key,
						'desc'    => ! empty( $option['desc'] ) ? $option['desc'] : '',
						'name'    => isset( $option['name'] ) ? $option['name'] : null,
						'section' => $tab,
						'size'    => isset( $option['size'] ) ? $option['size'] : null,
						'options' => isset( $option['options'] ) ? $option['options'] : '',
						'std'     => isset( $option['std'] ) ? $option['std'] : ''
					)
				);
			}

		}

		// Creates our settings in the options table
		register_setting( 'affwp_settings', 'affwp_settings', array( $this, 'sanitize_settings' ) );

	}

	/**
	 * Retrieve the array of plugin settings
	 *
	 * @since 1.0
	 * @return array
	*/
	function sanitize_settings( $input = array() ) {

		if ( empty( $_POST['_wp_http_referer'] ) ) {
			return $input;
		}

		parse_str( $_POST['_wp_http_referer'], $referrer );

		$saved    = get_option( 'affwp_settings', array() );
		if( ! is_array( $saved ) ) {
			$saved = array();
		}
		$settings = $this->get_registered_settings();
		$tab      = isset( $referrer['tab'] ) ? $referrer['tab'] : 'general';

		$input = $input ? $input : array();
		$input = apply_filters( 'affwp_settings_' . $tab . '_sanitize', $input );

		// Ensure a value is always passed for every checkbox
		if( ! empty( $settings[ $tab ] ) ) {
			foreach ( $settings[ $tab ] as $key => $setting ) {

				// Single checkbox
				if ( isset( $settings[ $tab ][ $key ][ 'type' ] ) && 'checkbox' == $settings[ $tab ][ $key ][ 'type' ] ) {
					$input[ $key ] = ! empty( $input[ $key ] );
				}

				// Multicheck list
				if ( isset( $settings[ $tab ][ $key ][ 'type' ] ) && 'multicheck' == $settings[ $tab ][ $key ][ 'type' ] ) {
					if( empty( $input[ $key ] ) ) {
						$input[ $key ] = array();
					}
				}
			}
		}
		
		// Loop through each setting being saved and pass it through a sanitization filter
		foreach ( $input as $key => $value ) {

			// Get the setting type (checkbox, select, etc)
			$type = isset( $settings[ $tab ][ $key ][ 'type' ] ) ? $settings[ $tab ][ $key ][ 'type' ] : false;

			if ( $type ) {
				// Field type specific filter
				$input[$key] = apply_filters( 'affwp_settings_sanitize_' . $type, $value, $key );
			}

			// General filter
			$input[ $key ] = apply_filters( 'affwp_settings_sanitize', $value, $key );
		}

		add_settings_error( 'affwp-notices', '', __( 'Settings updated.', 'affiliate-wp' ), 'updated' );

		return array_merge( $saved, $input );

	}

	/**
	 * Retrieve the array of plugin settings
	 *
	 * @since 1.0
	 * @return array
	*/
	function get_registered_settings() {

		$settings = array(
			/** General Settings */
			'general' => apply_filters( 'affwp_settings_general',
				array(
					'license' => array(
						'name' => '<strong>' . __( 'License Settings', 'affiliate-wp' ) . '</strong>',
						'desc' => '',
						'type' => 'header'
					),
					'license_key' => array(
						'name' => __( 'License Key', 'affiliate-wp' ),
						'desc' => sprintf( __( 'Please enter your license key. An active license key is needed for automatic plugin updates and <a href="%s" target="_blank">support</a>.', 'affiliate-wp' ), 'http://affiliatewp.com/support/' ),
						'type' => 'license'
					),

					'pages' => array(
						'name' => '<strong>' . __( 'Pages', 'affiliate-wp' ) . '</strong>',
						'desc' => '',
						'type' => 'header'
					),
					'affiliates_page' => array(
						'name' => __( 'Affiliate Area', 'affiliate-wp' ),
						'desc' => __( 'This is the page where affiliates will manage their affiliate account.', 'affiliate-wp' ),
						'type' => 'select',
						'options' => affwp_get_pages()
					),
					'terms_of_use' => array(
						'name' => __( 'Terms of Use', 'affiliate-wp' ),
						'desc' => __( 'Select the page that shows the terms of use for Affiliate Registration', 'affiliate-wp' ),
						'type' => 'select',
						'options' => affwp_get_pages()
					),
					'referrals' => array(
						'name' => '<strong>' . __( 'Referral Settings', 'affiliate-wp' ) . '</strong>',
						'desc' => '',
						'type' => 'header'
					),
					'referral_var' => array(
						'name' => __( 'Referral Variable', 'affiliate-wp' ),
						'desc' => sprintf( __( 'The URL variable for referral URLs. For example: <strong>%s</strong>.', 'affiliate-wp' ), add_query_arg( affiliate_wp()->tracking->get_referral_var(), '1', home_url( '/' ) ) ),
						'type' => 'text',
						'std' => 'ref'
					),
					'referral_rate_type' => array(
						'name' => __( 'Referral Rate Type', 'affiliate-wp' ),
						'desc' => __( 'Should referrals be based on a percentage or flat rate amounts?', 'affiliate-wp' ),
						'type' => 'select',
						'options' => affwp_get_affiliate_rate_types()
					),
					'referral_rate' => array(
						'name' => __( 'Referral Rate', 'affiliate-wp' ),
						'desc' => __( 'Default referral rate. A percentage if Referral Rate Type is Percentage, a flat amount otherwise. Rates can be set for each affiliate individually as well.', 'affiliate-wp' ),
						'type' => 'number',
						'size' => 'small',
						'std' => '20'
					),
					'cookie_exp' => array(
						'name' => __( 'Cookie Expiration', 'affiliate-wp' ),
						'desc' => __( 'How many days should the referral tracking cookie be valid for?', 'affiliate-wp' ),
						'type' => 'number',
						'size' => 'small',
						'std' => '1'
					),
					'currency_settings' => array(
						'name' => '<strong>' . __( 'Currency Settings', 'affiliate-wp' ) . '</strong>',
						'desc' => __( 'Configure the currency options', 'affiliate-wp' ),
						'type' => 'header'
					),
					'currency' => array(
						'name' => __( 'Currency', 'affiliate-wp' ),
						'desc' => __( 'Choose your currency. Note that some payment gateways have currency restrictions.', 'affiliate-wp' ),
						'type' => 'select',
						'options' => affwp_get_currencies()
					),
					'currency_position' => array(
						'name' => __( 'Currency Position', 'affiliate-wp' ),
						'desc' => __( 'Choose the location of the currency sign.', 'affiliate-wp' ),
						'type' => 'select',
						'options' => array(
							'before' => __( 'Before - $10', 'affiliate-wp' ),
							'after' => __( 'After - 10$', 'affiliate-wp' )
						)
					),
					'thousands_separator' => array(
						'name' => __( 'Thousands Separator', 'affiliate-wp' ),
						'desc' => __( 'The symbol (usually , or .) to separate thousands', 'affiliate-wp' ),
						'type' => 'text',
						'size' => 'small',
						'std' => ','
					),
					'decimal_separator' => array(
						'name' => __( 'Decimal Separator', 'affiliate-wp' ),
						'desc' => __( 'The symbol (usually , or .) to separate decimal points', 'affiliate-wp' ),
						'type' => 'text',
						'size' => 'small',
						'std' => '.'
					)
				)
			),
			/** Integration Settings */
			'integrations' => apply_filters( 'affwp_settings_integrations',
				array(
					'integrations' => array(
						'name' => __( 'Integrations', 'affiliate-wp' ),
						'desc' => sprintf( __( 'Choose the integrations to enable. If you are not using any of these, you may use the <strong>[affiliate_conversion_script]</strong> short code to track and create referrals. Refer to the <a href="%s" target="_blank">documentation</a> for help using this.', 'affiliate-wp' ), 'http://affiliatewp.com/docs/custom-referral-tracking/' ),
						'type' => 'multicheck',
						'options' => affiliate_wp()->integrations->get_integrations()
					)
				)
			),
			/** Misc Settings */
			'misc' => apply_filters( 'affwp_settings_misc',
				array(
					'allow_affiliate_registration' => array(
						'name' => __( 'Allow affiliate registration', 'affiliate-wp' ),
						'desc' => __( 'Should affiliates be able to register accounts for themselves?', 'affiliate-wp' ),
						'type' => 'checkbox'
					),
					'registration_notifications' => array(
						'name' => __( 'Notify Admins', 'affiliate-wp' ),
						'desc' => __( 'Notify site admins of new affiliate registrations?', 'affiliate-wp' ),
						'type' => 'checkbox'
					),
					'require_approval' => array(
						'name' => __( 'Require approval', 'affiliate-wp' ),
						'desc' => __( 'Require that site admins approve affiliates before they can begin earning referrals?', 'affiliate-wp' ),
						'type' => 'checkbox'
					),
					'auto_register' => array(
						'name' => __( 'Auto Register New Users', 'affiliate-wp' ),
						'desc' => __( 'Automatically register new users as affiliates?', 'affiliate-wp' ),
						'type' => 'checkbox'
					),
					'revoke_on_refund' => array(
						'name' => __( 'Reject Unpaid Referrals on Refund?', 'affiliate-wp' ),
						'desc' => __( 'Should unpaid referrals get automatically rejected when the originating purchase is refunded or revoked?', 'affiliate-wp' ),
						'type' => 'checkbox'
					),
					'tracking_fallback' => array(
						'name' => __( 'Use Fallback Referral Tracking Method?', 'affiliate-wp' ),
						'desc' => __( 'The method used to track referral links can fail on sites that have jQuery errors. Check this if referrals are not getting tracked properly.', 'affiliate-wp' ),
						'type' => 'checkbox'
					),
					'ignore_zero_referrals' => array(
						'name' => __( 'Ignore Zero Referrals?', 'affiliate-wp' ),
						'desc' => __( 'Check this box if you would like AffiliateWP to completely ignore referrals for a zero total amount. This can be useful for multi-price products that start at zero, or if a discount was used, which resulted in a zero amount. Please note: if this setting is enabled and a visit results in a zero referral, then the visit would be considered not converted.', 'affiliate-wp' ),
						'type' => 'checkbox'
					),
					'uninstall_on_delete' => array(
						'name' => __( 'Remove Data on Uninstall?', 'affiliate-wp' ),
						'desc' => __( 'Check this box if you would like AffiliateWP to completely remove all of its data when the plugin is deleted.', 'affiliate-wp' ),
						'type' => 'checkbox'
					)
				)
			)
		);

		return $settings;
	}


	/**
	 * Header Callback
	 *
	 * Renders the header.
	 *
	 * @since 1.0
	 * @param array $args Arguments passed by the setting
	 * @return void
	 */
	function header_callback( $args ) {
		echo '<hr/>';
	}

	/**
	 * Checkbox Callback
	 *
	 * Renders checkboxes.
	 *
	 * @since 1.0
	 * @param array $args Arguments passed by the setting
	 * @global $this->options Array of all the AffiliateWP Options
	 * @return void
	 */
	function checkbox_callback( $args ) {

		$checked = isset($this->options[$args['id']]) ? checked(1, $this->options[$args['id']], false) : '';
		$html = '<input type="checkbox" id="affwp_settings[' . $args['id'] . ']" name="affwp_settings[' . $args['id'] . ']" value="1" ' . $checked . '/>';
		$html .= '<label for="affwp_settings[' . $args['id'] . ']"> '  . $args['desc'] . '</label>';

		echo $html;
	}

	/**
	 * Multicheck Callback
	 *
	 * Renders multiple checkboxes.
	 *
	 * @since 1.0
	 * @param array $args Arguments passed by the setting
	 * @global $this->options Array of all the AffiliateWP Options
	 * @return void
	 */
	function multicheck_callback( $args ) {

		if ( ! empty( $args['options'] ) ) {
			foreach( $args['options'] as $key => $option ) {
				if( isset( $this->options[$args['id']][$key] ) ) { $enabled = $option; } else { $enabled = NULL; }
				echo '<input name="affwp_settings[' . $args['id'] . '][' . $key . ']" id="affwp_settings[' . $args['id'] . '][' . $key . ']" type="checkbox" value="' . $option . '" ' . checked($option, $enabled, false) . '/>&nbsp;';
				echo '<label for="affwp_settings[' . $args['id'] . '][' . $key . ']">' . $option . '</label><br/>';
			}
			echo '<p class="description">' . $args['desc'] . '</p>';
		}
	}

	/**
	 * Radio Callback
	 *
	 * Renders radio boxes.
	 *
	 * @since 1.0
	 * @param array $args Arguments passed by the setting
	 * @global $this->options Array of all the AffiliateWP Options
	 * @return void
	 */
	function radio_callback( $args ) {

		foreach ( $args['options'] as $key => $option ) :
			$checked = false;

			if ( isset( $this->options[ $args['id'] ] ) && $this->options[ $args['id'] ] == $key )
				$checked = true;
			elseif( isset( $args['std'] ) && $args['std'] == $key && ! isset( $this->options[ $args['id'] ] ) )
				$checked = true;

			echo '<input name="affwp_settings[' . $args['id'] . ']"" id="affwp_settings[' . $args['id'] . '][' . $key . ']" type="radio" value="' . $key . '" ' . checked(true, $checked, false) . '/>&nbsp;';
			echo '<label for="affwp_settings[' . $args['id'] . '][' . $key . ']">' . $option . '</label><br/>';
		endforeach;

		echo '<p class="description">' . $args['desc'] . '</p>';
	}

	/**
	 * Text Callback
	 *
	 * Renders text fields.
	 *
	 * @since 1.0
	 * @param array $args Arguments passed by the setting
	 * @global $this->options Array of all the AffiliateWP Options
	 * @return void
	 */
	function text_callback( $args ) {

		if ( isset( $this->options[ $args['id'] ] ) )
			$value = $this->options[ $args['id'] ];
		else
			$value = isset( $args['std'] ) ? $args['std'] : '';

		$size = ( isset( $args['size'] ) && ! is_null( $args['size'] ) ) ? $args['size'] : 'regular';
		$html = '<input type="text" class="' . $size . '-text" id="affwp_settings[' . $args['id'] . ']" name="affwp_settings[' . $args['id'] . ']" value="' . esc_attr( stripslashes( $value ) ) . '"/>';
		$html .= '<label for="affwp_settings[' . $args['id'] . ']"> '  . $args['desc'] . '</label>';

		echo $html;
	}

	/**
	 * License Callback
	 *
	 * Renders license key fields.
	 *
	 * @since 1.0
	 * @param array $args Arguments passed by the setting
	 * @global $this->options Array of all the AffiliateWP Options
	 * @return void
	 */
	function license_callback( $args ) {

		if ( isset( $this->options[ $args['id'] ] ) )
			$value = $this->options[ $args['id'] ];
		else
			$value = isset( $args['std'] ) ? $args['std'] : '';

		$size = ( isset( $args['size'] ) && ! is_null( $args['size'] ) ) ? $args['size'] : 'regular';
		$html = '<input type="text" class="' . $size . '-text" id="affwp_settings[' . $args['id'] . ']" name="affwp_settings[' . $args['id'] . ']" value="' . esc_attr( stripslashes( $value ) ) . '"/>';
		$license_status = $this->get( 'license_status' );
		$license_key = ! empty( $value ) ? $value : false;

		if( 'valid' === $license_status && ! empty( $license_key ) ) {
			$html .= '<input type="submit" class="button" name="affwp_deactivate_license" value="' . esc_attr__( 'Deactivate License', 'affiliate-wp' ) . '"/>';
			$html .= '<span style="color:green;">&nbsp;' . __( 'Your license is valid!', 'affiliate-wp' ) . '</span>';
		} elseif( 'expired' === $license_status && ! empty( $license_key ) ) {
			$renewal_url = add_query_arg( array( 'edd_license_key' => $license_key, 'download_id' => 17 ), 'https://affiliatewp.com/checkout' );
			$html .= '<a href="' . esc_url( $renewal_url ) . '" class="button-primary">' . __( 'Renew Your License', 'affiliate-wp' ) . '</a>';
			$html .= '<br/><span style="color:red;">&nbsp;' . __( 'Your license has expired, renew today to continue getting updates and support!', 'affiliate-wp' ) . '</span>';
		} else {
			$html .= '<input type="submit" class="button" name="affwp_activate_license" value="' . esc_attr__( 'Activate License', 'affiliate-wp' ) . '"/>';
		}

		$html .= '<br/><label for="affwp_settings[' . $args['id'] . ']"> '  . $args['desc'] . '</label>';

		echo $html;
	}

	/**
	 * Number Callback
	 *
	 * Renders number fields.
	 *
	 * @since 1.9
	 * @param array $args Arguments passed by the setting
	 * @global $this->options Array of all the AffiliateWP Options
	 * @return void
	 */
	function number_callback( $args ) {

		if ( isset( $this->options[ $args['id'] ] ) )
			$value = $this->options[ $args['id'] ];
		else
			$value = isset( $args['std'] ) ? $args['std'] : '';

		$max  = isset( $args['max'] ) ? $args['max'] : 999999;
		$min  = isset( $args['min'] ) ? $args['min'] : 0;
		$step = isset( $args['step'] ) ? $args['step'] : 1;

		$size = ( isset( $args['size'] ) && ! is_null( $args['size'] ) ) ? $args['size'] : 'regular';
		$html = '<input type="number" step="' . esc_attr( $step ) . '" max="' . esc_attr( $max ) . '" min="' . esc_attr( $min ) . '" class="' . $size . '-text" id="affwp_settings[' . $args['id'] . ']" name="affwp_settings[' . $args['id'] . ']" value="' . esc_attr( stripslashes( $value ) ) . '"/>';
		$html .= '<label for="affwp_settings[' . $args['id'] . ']"> '  . $args['desc'] . '</label>';

		echo $html;
	}

	/**
	 * Textarea Callback
	 *
	 * Renders textarea fields.
	 *
	 * @since 1.0
	 * @param array $args Arguments passed by the setting
	 * @global $this->options Array of all the AffiliateWP Options
	 * @return void
	 */
	function textarea_callback( $args ) {

		if ( isset( $this->options[ $args['id'] ] ) )
			$value = $this->options[ $args['id'] ];
		else
			$value = isset( $args['std'] ) ? $args['std'] : '';

		$size = ( isset( $args['size'] ) && ! is_null( $args['size'] ) ) ? $args['size'] : 'regular';
		$html = '<textarea class="large-text" cols="50" rows="5" id="affwp_settings[' . $args['id'] . ']" name="affwp_settings[' . $args['id'] . ']">' . esc_textarea( stripslashes( $value ) ) . '</textarea>';
		$html .= '<label for="affwp_settings[' . $args['id'] . ']"> '  . $args['desc'] . '</label>';

		echo $html;
	}

	/**
	 * Password Callback
	 *
	 * Renders password fields.
	 *
	 * @since 1.3
	 * @param array $args Arguments passed by the setting
	 * @global $this->options Array of all the AffiliateWP Options
	 * @return void
	 */
	function password_callback( $args ) {

		if ( isset( $this->options[ $args['id'] ] ) )
			$value = $this->options[ $args['id'] ];
		else
			$value = isset( $args['std'] ) ? $args['std'] : '';

		$size = ( isset( $args['size'] ) && ! is_null( $args['size'] ) ) ? $args['size'] : 'regular';
		$html = '<input type="password" class="' . $size . '-text" id="affwp_settings[' . $args['id'] . ']" name="affwp_settings[' . $args['id'] . ']" value="' . esc_attr( $value ) . '"/>';
		$html .= '<label for="affwp_settings[' . $args['id'] . ']"> '  . $args['desc'] . '</label>';

		echo $html;
	}

	/**
	 * Missing Callback
	 *
	 * If a function is missing for settings callbacks alert the user.
	 *
	 * @since 1.3.1
	 * @param array $args Arguments passed by the setting
	 * @return void
	 */
	function missing_callback($args) {
		printf( __( 'The callback function used for the <strong>%s</strong> setting is missing.', 'affiliate-wp' ), $args['id'] );
	}

	/**
	 * Select Callback
	 *
	 * Renders select fields.
	 *
	 * @since 1.0
	 * @param array $args Arguments passed by the setting
	 * @global $this->options Array of all the AffiliateWP Options
	 * @return void
	 */
	function select_callback($args) {

		if ( isset( $this->options[ $args['id'] ] ) )
			$value = $this->options[ $args['id'] ];
		else
			$value = isset( $args['std'] ) ? $args['std'] : '';

		$html = '<select id="affwp_settings[' . $args['id'] . ']" name="affwp_settings[' . $args['id'] . ']"/>';

		foreach ( $args['options'] as $option => $name ) :
			$selected = selected( $option, $value, false );
			$html .= '<option value="' . $option . '" ' . $selected . '>' . $name . '</option>';
		endforeach;

		$html .= '</select>';
		$html .= '<label for="affwp_settings[' . $args['id'] . ']"> '  . $args['desc'] . '</label>';

		echo $html;
	}

	/**
	 * Rich Editor Callback
	 *
	 * Renders rich editor fields.
	 *
	 * @since 1.0
	 * @param array $args Arguments passed by the setting
	 * @global $this->options Array of all the AffiliateWP Options
	 * @global $wp_version WordPress Version
	 */
	function rich_editor_callback( $args ) {

		if ( isset( $this->options[ $args['id'] ] ) )
			$value = $this->options[ $args['id'] ];
		else
			$value = isset( $args['std'] ) ? $args['std'] : '';

		ob_start();
		wp_editor( stripslashes( $value ), 'affwp_settings[' . $args['id'] . ']', array( 'textarea_name' => 'affwp_settings[' . $args['id'] . ']' ) );
		$html = ob_get_clean();

		$html .= '<br/><label for="affwp_settings[' . $args['id'] . ']"> '  . $args['desc'] . '</label>';

		echo $html;
	}


	public function activate_license() {

		if( ! isset( $_POST['affwp_settings'] ) )
			return;

		if( ! isset( $_POST['affwp_activate_license'] ) )
			return;

		if( ! isset( $_POST['affwp_settings']['license_key'] ) )
			return;

		// retrieve the license from the database
		$status  = $this->get( 'license_status' );
		$license = trim( $_POST['affwp_settings']['license_key'] );

		if( 'valid' == $status )
			return; // license already activated and valid

		// data to send in our API request
		$api_params = array(
			'edd_action'=> 'activate_license',
			'license' 	=> $license,
			'item_name' => 'AffiliateWP',
			'url'       => home_url()
		);

		// Call the custom API.
		$response = wp_remote_post( 'http://affiliatewp.com', array( 'timeout' => 35, 'sslverify' => false, 'body' => $api_params ) );

		// make sure the response came back okay
		if ( is_wp_error( $response ) )
			return false;

		// decode the license data
		$license_data = json_decode( wp_remote_retrieve_body( $response ) );

		$options = $this->get_all();

		$options['license_status'] = $license_data->license;

		update_option( 'affwp_settings', $options );

		delete_transient( 'affwp_license_check' );

	}

	public function deactivate_license() {

		if( ! isset( $_POST['affwp_settings'] ) )
			return;

		if( ! isset( $_POST['affwp_deactivate_license'] ) )
			return;

		if( ! isset( $_POST['affwp_settings']['license_key'] ) )
			return;

		// retrieve the license from the database
		$license = trim( $_POST['affwp_settings']['license_key'] );

		// data to send in our API request
		$api_params = array(
			'edd_action'=> 'deactivate_license',
			'license' 	=> $license,
			'item_name' => 'AffiliateWP',
			'url'       => home_url()
		);

		// Call the custom API.
		$response = wp_remote_post( 'http://affiliatewp.com', array( 'timeout' => 35, 'sslverify' => false, 'body' => $api_params ) );

		// make sure the response came back okay
		if ( is_wp_error( $response ) )
			return false;

		$options = $this->get_all();

		$options['license_status'] = 0;

		update_option( 'affwp_settings', $options );

		delete_transient( 'affwp_license_check' );

	}

	public function check_license() {

		if( ! empty( $_POST['affwp_settings'] ) ) {
			return; // Don't fire when saving settings
		}

		$status = get_transient( 'affwp_license_check' );

		// Run the license check a maximum of once per day
		if( false === $status ) {

			// data to send in our API request
			$api_params = array(
				'edd_action'=> 'check_license',
				'license' 	=> $this->get( 'license_key' ),
				'item_name' => 'AffiliateWP',
				'url'       => home_url()
			);

			// Call the custom API.
			$response = wp_remote_post( 'http://affiliatewp.com', array( 'timeout' => 35, 'sslverify' => false, 'body' => $api_params ) );

			// make sure the response came back okay
			if ( is_wp_error( $response ) )
				return false;

			$license_data = json_decode( wp_remote_retrieve_body( $response ) );

			$options = $this->get_all();

			$options['license_status'] = $license_data->license;

			update_option( 'affwp_settings', $options );

			set_transient( 'affwp_license_check', $license_data->license, DAY_IN_SECONDS );

			$status = $license_data->license;

		}

		return $status;

	}

	public function is_license_valid() {
		return $this->check_license() == 'valid';
	}

}

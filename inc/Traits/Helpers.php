<?php

namespace MeuMouse\Flexify_Checkout\Inter_Bank\Traits;

defined('ABSPATH') || exit;

/**
 * Helpers functions
 * 
 * @since 1.0.0
 * @version 1.2.5
 * @package MeuMouse.com
 */
trait Helpers {

	/**
	 * Get order document
	 *
	 * @since 1.0.0
	 * @param string|int $order | Order ID
	 * @return string|int
	 */
	protected function get_order_document( $order ) {
    	$person_type = intval( $order->get_meta( '_billing_persontype' ) );
		$document = null;

		if ( 1 === $person_type ) {
			$document = $order->get_meta('_billing_cpf');
		} elseif ( 2 === $person_type ) {
			$document = $order->get_meta('_billing_cnpj');
		} else {
			$document = $order->get_meta('_billing_cpf');

			if ( empty ( $document ) ) {
				$document = $order->get_meta('_billing_cnpj');
			}
		}

    	return $this->only_numbers( $document );
	}


	/**
	 * Only numbers
	 *
	 * @since 1.0.0
	 * @param string|int $string | String to convert
	 * @return string|int
	 */
	protected function only_numbers( $string ) {
		return preg_replace( '([^0-9])', '', $string );
	}


	/**
	 * Extract DDD from number string
	 * 
	 * @since 1.1.0
	 * @param string $phone | Phone number
	 * @return string
	 */
	protected function extract_ddd( $phone ) {
		if ( preg_match( '/\((\d+)\)/', $phone, $matches ) ) {
			return $matches[1];
		}

		return '';
	}


	/**
	 * Extract only phone number excluding DDD
	 * 
	 * @since 1.1.0
	 * @param string $phone | Phone number
	 * @return string
	 */
	protected function extract_phone_number( $phone ) {
        // Removes all non-numeric characters
        $phone_number = preg_replace( '/\D/', '', preg_replace( '/\(\d+\)\s?/', '', $phone ) );

        // Ensures the number has a maximum of 9 digits
        return substr( $phone_number, 0, 9 );
    }


	/**
	 * Generate hash
	 * 
	 * @since 1.2.5
	 * @param int $lenght | Lenght hash
	 * @return string
	 */
	protected function generate_hash( $length ) {
		$result = '';
		$characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
		$charactersLength = strlen( $characters );

		for ( $i = 0; $i < $length; $i++ ) {
			$result .= $characters[wp_rand(0, $charactersLength - 1)];
		}

		return $result;
	}
}
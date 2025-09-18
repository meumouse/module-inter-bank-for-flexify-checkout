<?php

namespace MeuMouse\Flexify_Checkout\Inter_Bank\Admin;

defined('ABSPATH') || exit;

/**
 * Manage Pix Automático product settings.
 *
 * Adds custom fields to WooCommerce products so store owners can
 * define automatic Pix charge parameters per product and variation.
 *
 * @since 1.4.0
 */
class Product_Automattic_Pix {

    /**
     * Constructor
     * 
     * @since 1.4.0
     * @return void
     */
    public function __construct() {
        add_action( 'woocommerce_product_options_general_product_data', [ $this, 'render_general_fields' ] );
        add_action( 'woocommerce_admin_process_product_object', [ $this, 'save_product_fields' ] );

        add_action( 'woocommerce_product_after_variable_attributes', [ $this, 'render_variation_fields' ], 10, 3 );
        add_action( 'woocommerce_save_product_variation', [ $this, 'save_variation_fields' ], 10, 2 );
    }


    /**
     * Output Pix Automático fields in the product edit screen
     * 
     * @since 1.4.0
     * @return void
     */
    public function render_general_fields() {
        global $post;

        if ( empty( $post ) ) {
            return;
        }

        $product = wc_get_product( $post->ID );

        if ( ! $product ) {
            return;
        }

        echo '<div class="options_group">';

        woocommerce_wp_text_input(
            [
                'id'                => '_inter_pix_auto_amount',
                'label'             => __( 'Valor da cobrança Pix Automático', 'module-inter-bank-for-flexify-checkout' ),
                'type'              => 'number',
                'value'             => $product->get_meta( '_inter_pix_auto_amount', true ),
                'custom_attributes' => [
                    'step' => '0.01',
                    'min'  => '0',
                ],
                'description'       => __( 'Defina um valor específico para a cobrança automática deste produto. Deixe em branco para usar o total do pedido.', 'module-inter-bank-for-flexify-checkout' ),
                'desc_tip'          => true,
            ]
        );

        woocommerce_wp_text_input(
            [
                'id'          => '_inter_pix_auto_due_days',
                'label'       => __( 'Prazo em dias', 'module-inter-bank-for-flexify-checkout' ),
                'type'        => 'number',
                'value'       => $product->get_meta( '_inter_pix_auto_due_days', true ),
                'description' => __( 'Informe em quantos dias a cobrança deve expirar. Deixe em branco para usar a configuração padrão do Banco Inter.', 'module-inter-bank-for-flexify-checkout' ),
                'desc_tip'    => true,
                'custom_attributes' => [
                    'min' => '0',
                ],
            ]
        );

        echo '</div>';
    }


    /**
     * Save Pix Automático fields for products
     *
     * @since 1.4.0
     * @param \WC_Product $product | Product instance
     * @return void
     */
    public function save_product_fields( $product ) {
        if ( ! $product ) {
            return;
        }

        if ( isset( $_POST['_inter_pix_auto_amount'] ) ) {
            $raw_amount = wp_unslash( $_POST['_inter_pix_auto_amount'] );
            $raw_amount = is_array( $raw_amount ) ? '' : $raw_amount;

            if ( '' === trim( $raw_amount ) ) {
                $product->delete_meta_data( '_inter_pix_auto_amount' );
            } else {
                $product->update_meta_data( '_inter_pix_auto_amount', wc_format_decimal( $raw_amount ) );
            }
        }

        if ( isset( $_POST['_inter_pix_auto_due_days'] ) ) {
            $raw_due_days = wp_unslash( $_POST['_inter_pix_auto_due_days'] );
            $raw_due_days = is_array( $raw_due_days ) ? '' : $raw_due_days;

            if ( '' === trim( $raw_due_days ) ) {
                $product->delete_meta_data( '_inter_pix_auto_due_days' );
            } else {
                $product->update_meta_data( '_inter_pix_auto_due_days', absint( $raw_due_days ) );
            }
        }
    }


    /**
     * Render Pix Automático fields for variations
     *
     * @since 1.4.0
     * @param int $loop | Loop index
     * @param array $variation_data | Variation data
     * @param \WP_Post $variation | Variation object
     * @return void
     */
    public function render_variation_fields( $loop, $variation_data, $variation ) {
        if ( empty( $variation ) || empty( $variation->ID ) ) {
            return;
        }

        $variation_id = $variation->ID;

        woocommerce_wp_text_input(
            [
                'id'                => "_inter_pix_auto_amount_{$variation_id}",
                'name'              => "_inter_pix_auto_amount[{$variation_id}]",
                'wrapper_class'     => 'form-row form-row-full',
                'label'             => __( 'Valor da cobrança Pix Automático', 'module-inter-bank-for-flexify-checkout' ),
                'type'              => 'number',
                'value'             => get_post_meta( $variation_id, '_inter_pix_auto_amount', true ),
                'custom_attributes' => [
                    'step' => '0.01',
                    'min'  => '0',
                ],
                'description'       => __( 'Sobrescreve o valor configurado no produto pai.', 'module-inter-bank-for-flexify-checkout' ),
                'desc_tip'          => true,
            ]
        );

        woocommerce_wp_text_input(
            [
                'id'                => "_inter_pix_auto_due_days_{$variation_id}",
                'name'              => "_inter_pix_auto_due_days[{$variation_id}]",
                'wrapper_class'     => 'form-row form-row-full',
                'label'             => __( 'Prazo em dias', 'module-inter-bank-for-flexify-checkout' ),
                'type'              => 'number',
                'value'             => get_post_meta( $variation_id, '_inter_pix_auto_due_days', true ),
                'custom_attributes' => [
                    'min' => '0',
                ],
                'description'       => __( 'Sobrescreve o prazo configurado no produto pai.', 'module-inter-bank-for-flexify-checkout' ),
                'desc_tip'          => true,
            ]
        );
    }


    /**
     * Save Pix Automático fields for variations
     *
     * @since 1.4.0
     * @param int $variation_id | Variation ID
     * @param int $index | Loop index
     * @return void
     */
    public function save_variation_fields( $variation_id, $index ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
        if ( ! $variation_id ) {
            return;
        }

        if ( isset( $_POST['_inter_pix_auto_amount'][ $variation_id ] ) ) {
            $raw_amount = wp_unslash( $_POST['_inter_pix_auto_amount'][ $variation_id ] );

            if ( '' === trim( (string) $raw_amount ) ) {
                delete_post_meta( $variation_id, '_inter_pix_auto_amount' );
            } else {
                update_post_meta( $variation_id, '_inter_pix_auto_amount', wc_format_decimal( $raw_amount ) );
            }
        }

        if ( isset( $_POST['_inter_pix_auto_due_days'][ $variation_id ] ) ) {
            $raw_due_days = wp_unslash( $_POST['_inter_pix_auto_due_days'][ $variation_id ] );

            if ( '' === trim( (string) $raw_due_days ) ) {
                delete_post_meta( $variation_id, '_inter_pix_auto_due_days' );
            } else {
                update_post_meta( $variation_id, '_inter_pix_auto_due_days', absint( $raw_due_days ) );
            }
        }
    }
}
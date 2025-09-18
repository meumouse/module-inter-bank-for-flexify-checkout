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
        // automattic Pix settings for simple product
        add_action( 'woocommerce_product_options_general_product_data', array( $this, 'render_general_fields' ) );
        add_action( 'woocommerce_admin_process_product_object', array( $this, 'save_product_fields' ) );

        // automattic Pix settings for variable product
        add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'render_variation_fields' ), 10, 3 );
        add_action( 'woocommerce_save_product_variation', array( $this, 'save_variation_fields' ), 10, 2 );
    }


    /**
     * Output Pix Automático fields in the product edit screen
     * 
     * @since 1.4.0
     * @return void
     */
    public function render_general_fields() {
        global $post;

        $product = wc_get_product( $post->ID );

        if ( ! $product ) {
            return;
        }

        echo '<div class="options_group">';
            woocommerce_wp_select([
                'id'      => '_inter_pix_auto_interval_period',
                'label'   => __( 'Periodicidade das cobranças', 'module-inter-bank-for-flexify-checkout' ),
                'value'   => $product->get_meta( '_inter_pix_auto_interval_period', true ),
                'options' => array(
                    ''          => __( 'Selecione', 'module-inter-bank-for-flexify-checkout' ),
                    'week'      => __( 'Semanal', 'module-inter-bank-for-flexify-checkout' ),
                    'month'     => __( 'Mensal', 'module-inter-bank-for-flexify-checkout' ),
                    'quarter'   => __( 'Trimestral', 'module-inter-bank-for-flexify-checkout' ),
                    'semester'  => __( 'Semestral', 'module-inter-bank-for-flexify-checkout' ),
                    'year'      => __( 'Anual', 'module-inter-bank-for-flexify-checkout' ),
                ),
                'description' => __( 'Período base da recorrência para o Pix Automático.', 'module-inter-bank-for-flexify-checkout' ),
                'desc_tip'    => true,
            ]);
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

        // save interval unit
        if ( isset( $_POST['_inter_pix_auto_interval_period'] ) ) {
            $unit = sanitize_text_field( wp_unslash( $_POST['_inter_pix_auto_interval_period'] ) );
           
            if ( $unit ) {
                $product->update_meta_data( '_inter_pix_auto_interval_period', $unit );
            } else {
                $product->delete_meta_data( '_inter_pix_auto_interval_period' );
            }
        }
    }


    /**
     * Render Pix Automático fields for variations
     *
     * @since 1.4.0
     * @param int $loop | Loop index.
     * @param array $variation_data | Variation data.
     * @param WP_Post $variation | Variation object.
     * @return void
     */
    public function render_variation_fields( $loop, $variation_data, $variation ) {
        if ( empty( $variation ) || empty( $variation->ID ) ) {
            return;
        }

        $variation_id = $variation->ID;

        echo '<div class="options_group form-row form-row-full">';
            woocommerce_wp_select([
                'id'          => "_inter_pix_auto_interval_unit_{$variation_id}",
                'name'        => "_inter_pix_auto_interval_period[{$variation_id}]",
                'label'       => __( 'Periodicidade das cobranças', 'module-inter-bank-for-flexify-checkout' ),
                'value'       => get_post_meta( $variation_id, '_inter_pix_auto_interval_period', true ),
                'options' => array(
                    ''          => __( 'Herdar do produto pai', 'module-inter-bank-for-flexify-checkout' ),
                    'week'      => __( 'Semanal', 'module-inter-bank-for-flexify-checkout' ),
                    'month'     => __( 'Mensal', 'module-inter-bank-for-flexify-checkout' ),
                    'quarter'   => __( 'Trimestral', 'module-inter-bank-for-flexify-checkout' ),
                    'semester'  => __( 'Semestral', 'module-inter-bank-for-flexify-checkout' ),
                    'year'      => __( 'Anual', 'module-inter-bank-for-flexify-checkout' ),
                ),
                'description' => __( 'Período base da recorrência (deixe vazio para herdar do produto pai).', 'module-inter-bank-for-flexify-checkout' ),
                'desc_tip'    => true,
            ]);
        echo '</div>';
    }

    /**
     * Save Pix Automático fields for variations
     *
     * @since 1.4.0
     * @param int $variation_id | Variation ID.
     * @param int $index | Loop index.
     * @return void
     */
    public function save_variation_fields( $variation_id, $index ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
        if ( ! $variation_id ) {
            return;
        }

        // interval period
        if ( isset( $_POST['_inter_pix_auto_interval_period'][ $variation_id ] ) ) {
            $raw = wp_unslash( $_POST['_inter_pix_auto_interval_period'][ $variation_id ] );
            $unit = sanitize_text_field( $raw );

            if ( '' === $unit ) {
                delete_post_meta( $variation_id, '_inter_pix_auto_interval_period' ); // herda do pai
            } else {
                update_post_meta( $variation_id, '_inter_pix_auto_interval_period', $unit );
            }
        }
    }


    /**
     * Helper: get effective interval settings for a product/variation
     *
     * @since 1.4.0
     * @param WC_Product $product | Product object
     * @return array{count:int|null, unit:string|null}
     */
/*    public static function get_effective_interval_settings( $product ) {
        if ( ! $product instanceof \WC_Product ) {
            return [ 'count' => null, 'unit' => null ];
        }

        $count = null;
        $unit = null;

        if ( $product->is_type('variation') ) {
            $variation_id = $product->get_id();
            $parent_id = $product->get_parent_id();
            $count_meta = get_post_meta( $variation_id, '_inter_pix_auto_interval_count', true );
            $unit_meta = get_post_meta( $variation_id, '_inter_pix_auto_interval_period', true );

            if ( '' !== $count_meta ) {
                $count = absint( $count_meta );
            }

            if ( '' !== $unit_meta ) {
                $unit = sanitize_text_field( $unit_meta );
            }

            // inherit from parent if empty
            if ( null === $count ) {
                $count_parent = get_post_meta( $parent_id, '_inter_pix_auto_interval_count', true );
                $count = ( '' !== $count_parent ) ? absint( $count_parent ) : null;
            }

            if ( null === $unit ) {
                $unit_parent = get_post_meta( $parent_id, '_inter_pix_auto_interval_period', true );
                $unit = ( '' !== $unit_parent ) ? sanitize_text_field( $unit_parent ) : null;
            }
        } else {
            // simple product
            $count_meta = $product->get_meta( '_inter_pix_auto_interval_count', true );
            $unit_meta  = $product->get_meta( '_inter_pix_auto_interval_period', true );
            $count = ( '' !== $count_meta ) ? absint( $count_meta ) : null;
            $unit = ( '' !== $unit_meta ) ? sanitize_text_field( $unit_meta ) : null;
        }

        return array(
            'count' => $count,
            'unit' => $unit,
        );
    }*/
}
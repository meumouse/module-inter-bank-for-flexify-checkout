<?php

defined('ABSPATH') || exit;

$decimals = wc_get_price_decimals();
$amount_meta = isset( $order ) ? $order->get_meta( 'inter_pix_automatico_amount' ) : '';
$amount_value = ( '' !== $amount_meta && null !== $amount_meta ) ? wc_format_decimal( $amount_meta, $decimals ) : wc_format_decimal( $order->get_total(), $decimals );
$amount_display = wc_price( $amount_value );

$due_days_meta = isset( $order ) ? $order->get_meta( 'inter_pix_automatico_due_days' ) : '';
$due_days = ( '' !== $due_days_meta && null !== $due_days_meta ) ? absint( $due_days_meta ) : null;
$due_days_display = '';

if ( null !== $due_days ) {
    $due_days_display = sprintf(
        _n( '%s dia para autorização', '%s dias para autorização', $due_days, 'module-inter-bank-for-flexify-checkout' ),
        number_format_i18n( $due_days )
    );
}
?>

<div id="interpix-automatico-details" class="interpix-automatico-details">
    <?php if ( ! empty( $instructions ) ) : ?>
        <div class="pix-automatico-instructions">
            <?php echo wp_kses_post( wpautop( $instructions ) ); ?>
        </div>
    <?php endif; ?>

    <div class="pix-automatico-summary">
        <p class="pix-automatico-summary__amount">
            <strong><?php esc_html_e( 'Valor da cobrança Pix Automático:', 'module-inter-bank-for-flexify-checkout' ); ?></strong>
            <span><?php echo wp_kses_post( $amount_display ); ?></span>
        </p>

        <?php if ( '' !== $due_days_display ) : ?>
            <p class="pix-automatico-summary__due-days">
                <strong><?php esc_html_e( 'Prazo', 'module-inter-bank-for-flexify-checkout' ); ?>:</strong>
                <span><?php echo esc_html( $due_days_display ); ?></span>
            </p>
        <?php endif; ?>
    </div>

    <?php if ( ! empty( $pix_image ) ) : ?>
        <div class="pix-automatico-qrcode">
            <img src="<?php echo esc_url( $pix_image ); ?>" alt="<?php esc_attr_e( 'QR Code Pix Automático', 'module-inter-bank-for-flexify-checkout' ); ?>" />
        </div>
    <?php endif; ?>

    <?php if ( ! empty( $payload ) ) : ?>
        <div class="pix-automatico-copy">
            <label for="interpix-automatico-payload">
                <?php esc_html_e( 'Código Pix para copiar e colar', 'module-inter-bank-for-flexify-checkout' ); ?>
            </label>
            <textarea id="interpix-automatico-payload" readonly><?php echo esc_textarea( $payload ); ?></textarea>
        </div>
    <?php endif; ?>
</div>
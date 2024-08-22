<?php

defined('ABSPATH') || exit; ?>

<style>
  .banco-inter-payment-created {
    display: block;
    margin-bottom: 20px;
    text-align: center;
    border: 1px solid #ccc;
    padding: 15px;
  }

  .banco-inter-payment-created .banco-inter-status {
    display: block;
  }

  .wc-print-banking-ticket-button {
    font-size: 1.25rem;
    height: 48px;
    line-height: 24px;
    text-align: center;
    display: block;
    margin: 20px 0;
    width: 100%;
    color: #fff !important;
  }

  .wc-linecode-title {
    display: block;
    text-align: center;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    font-size: 11px;
    margin-bottom: 5px;
  }

  #wc-linecode-field,
  #wc-pix-copia-cola-field {
    width: 100%;
    text-align: center;
    border-radius: 0.5rem;
    border: 1px solid #E9ECEF;
    color: #343A40;
    letter-spacing: 2px;
    resize: none;
    padding: 1rem;
  }

  .wc-copy-linecode-button,
  .wc-copy-pix-copia-cola-button {
    display: block;
    width: 100%;
    margin-top: 1rem;
    margin-bottom: 3rem;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 1px;
    border-radius: 0.5rem;
  }

  @media only screen and (min-width: 600px) {
    #wc-linecode-field {
      height: 5rem;
      line-height: 2.5;
    }
  }

  #interboleto-thankyou {
    padding: 3rem 1.75rem;
    background-color: #F8F9FA;
    border-radius: 0.5rem;
  }

  .woocommerce-order-received #interboleto-thankyou {
    margin: 3.225rem 2.115rem 6rem 2.115rem;
  }
</style>

<script src="https://cdn.jsdelivr.net/npm/clipboard@2.0.10/dist/clipboard.min.js"></script>

<script class="flexify-checkout-clipboard-js skip-lazy" data-no-defer="1">
  jQuery( function($) {
    var linecode_button = $('.wc-copy-linecode-button');
    var pix_button = $('.wc-copy-pix-copia-cola-button');
    var linecode = new ClipboardJS('.wc-copy-linecode-button');

    linecode.on('success', function(e) {
      linecode_button.html('Copiado!');

      window.setTimeout( function() {
        linecode_button.html(linecode_button.data('text'));
      }, 1500);
    });

    var pix = new ClipboardJS('.wc-copy-pix-copia-cola-button');

    pix.on('success', function(e) {
      pix_button.html('Copiado!');

      window.setTimeout(function() {
        pix_button.html(pix_button.data('text'));
      }, 1500);
    });
  });
</script>

<section id="<?php echo esc_attr( $id ); ?>-thankyou">
  <div class="flexify-checkout-bank-slip-archive">
    <a class="button alt wc-print-banking-ticket-button" href="<?php echo esc_url( $url ) ?>" target="_blank"><?php echo __( 'Ver boleto (PDF)', 'module-inter-bank-for-flexify-checkout' ); ?></a>
  </div>

  <div class="flexify-checkout-bank-slip-linecode">
    <span class="wc-linecode-title"><?php echo __( 'Linha digitável do boleto', 'module-inter-bank-for-flexify-checkout' ); ?></span>
    <textarea id="wc-linecode-field" rows="2" readonly><?php echo $payment_line; ?></textarea>
    <button class="wc-copy-linecode-button" data-clipboard-target="#wc-linecode-field" data-text="<?php echo __( 'Copiar linha digitável do boleto', 'module-inter-bank-for-flexify-checkout' ); ?>"><?php echo __( 'Copiar linha digitável do boleto', 'module-inter-bank-for-flexify-checkout' ); ?></button>
  </div>

  <div class="flexify-checkout-pix-copia-cola">
    <textarea id="wc-pix-copia-cola-field" rows="2" readonly><?php echo $pix_copia_cola; ?></textarea>
    <button class="wc-copy-pix-copia-cola-button" data-clipboard-target="#wc-pix-copia-cola-field" data-text="<?php echo __( 'Copiar Pix Copia e Cola', 'module-inter-bank-for-flexify-checkout' ); ?>"><?php echo __( 'Copiar Pix Copia e Cola', 'module-inter-bank-for-flexify-checkout' ); ?></button>
  </div>
</section>
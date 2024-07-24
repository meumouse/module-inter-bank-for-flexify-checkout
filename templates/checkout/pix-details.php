<?php

defined('ABSPATH') || exit;

$base = get_option( 'woocommerce_email_base_color' );
$base_text = wc_light_or_dark( $base, '#202020', '#ffffff' );

// Recupere a hora do pedido
$order_date = $order->get_date_created();

// Recupere o tempo restante do Pix
$pix_expires_in = $order->get_meta('inter_pix_expires_in');

// Calcule a hora de expiração do Pix
$pix_expiration_time = strtotime( $order_date ) + $pix_expires_in;

// Calcule o tempo restante do Pix
$pix_time_remaining = $pix_expiration_time - time(); ?>

<style type="text/css">
#interpix-thankyou {
  display: flex;
  flex-direction: column;
  align-items: center;
  width: 100%;
}

.flexify-checkout-interpix-open-browser-container {
  max-width: 300px;
  margin: 20px auto;
  text-align: center;
}

.flexify-checkout-interpix-open-browser {
  display: inline-block;
  text-decoration: none;
  font-size: 13px;
  padding-bottom: 15px;
  padding-left: 10px;
  padding-right: 10px;
  font-weight: bold;
  border-radius: 0.5rem;
  vertical-align: middle;
}

.flexify-checkout-interpix-copy-paste {
  padding: 1.5rem;
  box-sizing: border-box;
  margin-top: 2rem;
  width: 95%;
}

.flexify-checkout-interpix-copy-paste .flexify-checkout-interpix-copy-title {
  margin: 0 0 15px 0;
  padding: 0;
  font-size: 18px;
  font-weight: 600;
  display: flex;
  align-items: center;
}

.flexify-checkout-interpix-copy-paste .flexify-checkout-interpix-copy-title span {
  font-size: 13px;
  background: #1e262c;
  color: #fff;
  font-weight: normal;
  padding: 3px 6px;
  display: flex;
  margin-left: 15px;
  border-radius: 4px;
  cursor: pointer;
  transition: 0.2s;
}

.flexify-checkout-interpix-copy-paste .flexify-checkout-interpix-copy-title span:hover {
  background: #343A40;
}

.flexify-checkout-interpix-copy-paste .flexify-checkout-interpix-url {
  background: #efefef;
  text-align: center;
  padding: 15px 20px;
  font-size: 13px;
  cursor: pointer;
  resize: none;
  width: 100%;
  box-sizing: border-box;
  height: 75px !important;
}

@media only screen and (max-width: 650px) {
  .flexify-checkout-interpix-copy-paste .flexify-checkout-interpix-url {
    height: 125px !important;
  }
}

.flexify-checkout-interpix-title {
  font-size: 1.35rem;
  margin-bottom: 1rem;
  color: #6C757D;
}

.flexify-checkout-interpix-container {
  display: flex;
  align-items: center;
  width: 95%;
}

@media only screen and (min-width: 992px) {
  .flexify-checkout-interpix-container,
  .flexify-checkout-interpix-copy-paste {
    width: 75%;
  }
}

.flexify-checkout-interpix-container .qr-code,
.flexify-checkout-interpix-container img {
  width: 20rem;
}

.flexify-checkout-interpix-container img {
  margin: 0 auto;
  display: inline-block;
  background-color: #fff;
  border-radius: 0.5rem;
}

.flexify-checkout-interpix-container .flexify-checkout-interpix-instructions {
  flex: 1;
}

.flexify-checkout-interpix-container .flexify-checkout-interpix-instructions ul {
  padding: 0;
  margin: 0 0 0 10px;
  list-style: none;
}

.flexify-checkout-interpix-container .flexify-checkout-interpix-instructions ul li {
  margin: 15px 0 20px;
  display: flex;
  align-items: center;
  justify-content: flex-start;
}

.flexify-checkout-interpix-container .flexify-checkout-interpix-instructions svg {
  min-width: 45px;
  width: 45px;
  height: 45px;
  margin-right: 20px;
}

.flexify-checkout-interpix-container .flexify-checkout-interpix-instructions .flexify-checkout-interpix-mobile-only {
  display: none;
}

@media only screen and (max-width: 650px){
  .flexify-checkout-interpix-container {
    flex-direction: column-reverse;
  }

  .flexify-checkout-interpix-container .qr-code {
    width: 100%;
    text-align: center;
  }

  .flexify-checkout-interpix-container .flexify-checkout-interpix-instructions .flexify-checkout-interpix-desktop-only {
    display: none;
  }

  .flexify-checkout-interpix-container .flexify-checkout-interpix-instructions .flexify-checkout-interpix-mobile-only {
    display: inline;
  }
}

.inter-pix-step-instructions {
  padding: 1rem;
  font-size: 1.225rem;
  background-color: #343A40;
  color: #fff;
  width: 2rem;
  height: 2rem;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 100%;
  margin-right: 1rem;
}

#interpix-payload {
  width: 100%;
  height: 6rem;
  padding: 0.825rem 1rem;
  font-size: 1rem;
  font-weight: 400;
  line-height: 1.4;
  color: #576071;
  background-color: #fff;
  background-clip: padding-box;
  border: 1px solid #d7dde2;
  -webkit-appearance: none;
  -moz-appearance: none;
  appearance: none;
  border-radius: 0.5rem;
  transition: border-color .15s ease-in-out;
}

#interpix-payload:focus {
  border-color: #212529;
}

.flexify-checkout-countdown-pix {
  display: flex;
  align-items: center;
  background-color: #ffba08;
  color: #fff;
  padding: 0 1rem;
  border-radius: 0.5rem;
}

.flexify-checkout-countdown-pix .countdown-icon {
  margin-right: 1rem;
  width: 2rem;
  height: auto;
  fill: #fff;
}

#countdown-pix {
  font-size: 1.75rem;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/clipboard@2.0.10/dist/clipboard.min.js"></script>

<script type="application/javascript">
  // enable copy button
  var clipboard = new ClipboardJS( '.pix-copy' );

  clipboard.on( 'success', function() {
    const button = jQuery('.pix-copy-button');
    const buttonText = button.text();

    button.text('Copiado!');

    setTimeout(() => {
      button.text( buttonText );
    }, 1000);
  });
</script>

<script>
  var pix_time_remaining = <?php echo $pix_time_remaining; ?>;
  // construct countdown timer
  var x = setInterval( function() {

    // Calcule os dias, horas, minutos e segundos restantes
    var days = Math.floor(pix_time_remaining / (60 * 60 * 24));
    var hours = Math.floor((pix_time_remaining % (60 * 60 * 24)) / (60 * 60));
    var minutes = Math.floor((pix_time_remaining % (60 * 60)) / 60);
    var seconds = Math.floor(pix_time_remaining % 60);

    // Construa a string da contagem regressiva
    var countdownString = '';

    // Se houver dias restantes, adicione à string
    if (days > 0) {
      countdownString += days + "d ";
    }

    // Se houver horas restantes, adicione à string
    if (hours > 0) {
      countdownString += hours + "h ";
    }

    // Se houver minutos restantes, adicione à string
    if (minutes > 0) {
      countdownString += minutes + "m ";
    }

    // Se houver segundos restantes, adicione à string
    if (seconds > 0) {
      countdownString += seconds + "s ";
    }

    // Exiba a contagem regressiva
    document.getElementById("countdown-pix").innerHTML = countdownString;

    // Reduza o tempo restante em 1 segundo
    pix_time_remaining--;

    // Se o tempo restante for menor ou igual a zero, pare a contagem regressiva
    if (pix_time_remaining <= 0) {
      clearInterval(x);
      document.getElementById("countdown-pix").innerHTML = "Prazo para pagamento expirado.";
      document.querySelector(".flexify-checkout-interpix-title").remove();
      document.querySelector(".flexify-checkout-interpix-container").remove();
      document.querySelector(".flexify-checkout-interpix-copy-paste").remove();
    }
  }, 1000);
</script>


<section id="<?php echo esc_attr( $id ); ?>-thankyou">
  <div class="flexify-checkout-countdown-pix">
    <svg class="countdown-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path d="m20.145 8.27 1.563-1.563-1.414-1.414L18.586 7c-1.05-.63-2.274-1-3.586-1-3.859 0-7 3.14-7 7s3.141 7 7 7 7-3.14 7-7a6.966 6.966 0 0 0-1.855-4.73zM15 18c-2.757 0-5-2.243-5-5s2.243-5 5-5 5 2.243 5 5-2.243 5-5 5z"></path><path d="M14 10h2v4h-2zm-1-7h4v2h-4zM3 8h4v2H3zm0 8h4v2H3zm-1-4h3.99v2H2z"></path></svg>
    <div id="countdown-pix"></div>
  </div>

	<h3 class="flexify-checkout-interpix-title"><?php echo __( 'Aguardando sua transferência via Pix', 'module-inter-bank-for-flexify-checkout' ); ?></h3>
	<?php if ( $instructions ) { ?>
		<div class="instruction"><?php echo wpautop( wptexturize( $instructions ) ); ?></div>
	<?php } ?>

  <?php if ( isset( $order ) && $is_email ) { ?>
    <div class="flexify-checkout-interpix-open-browser-container">
      <a href="<?php echo esc_url( $pix_details_page ); ?>" class="flexify-checkout-interpix-open-browser">
        <?php echo __( 'Ver QR Code no navegador', 'module-inter-bank-for-flexify-checkout' ); ?>
      </a>
    </div>
  <?php } ?>

	<div class="flexify-checkout-interpix-container">
    <?php if ( ! $is_email && $pix_image ) { ?>
      <div class="qr-code">
        <img src="<?php echo esc_attr( $pix_image ); // base64 image ?>"
          alt="<?php echo __( 'QR Code para pagamento', 'module-inter-bank-for-flexify-checkout' ); ?>"
        />
      </div>
    <?php } ?>

		<div class="flexify-checkout-interpix-instructions">
			<ul>
				<li>
				<span class="inter-pix-step-instructions"><?php echo esc_html( '1', 'module-inter-bank-for-flexify-checkout' ); ?></span>

				<div><?php echo __( 'Abra o app do seu banco ou instituição financeira e <strong>entre no ambiente Pix</strong>.', 'module-inter-bank-for-flexify-checkout' ); ?></div></li>

				<li>
          <span class="inter-pix-step-instructions"><?php echo esc_html( '2', 'module-inter-bank-for-flexify-checkout' ); ?></span>

					<div>
            <?php if ( $is_email || ! $pix_image ) {
              echo __( 'Escolha a opção <strong>Pix Copia e Cola</strong> e insira o texto acima<strong></strong>.', 'module-inter-bank-for-flexify-checkout' );
            } else {
              echo __( 'Escolha a opção <strong> Pagar com QR Code</strong> e escaneie o código <span class="flexify-checkout-interpix-mobile-only"> abaixo</span><span class="flexify-checkout-interpix-desktop-only"> ao lado</span> ou utilize o recurso <strong>Pix Copia e Cola</strong>.', 'module-inter-bank-for-flexify-checkout' );
            } ?>
          </div>
				</li>

				<li>
          <span class="inter-pix-step-instructions"><?php echo esc_html( '3', 'module-inter-bank-for-flexify-checkout' ); ?></span>
					<div>
            <?php echo __( 'Confirme as informações e <strong>finalize o pagamento</strong>. Pode demorar alguns minutos até que o pagamento seja confirmado. Iremos avisar você!', 'module-inter-bank-for-flexify-checkout' ); ?>
          </div>
				</li>
			</ul>
		</div>
	</div>

  <div class="flexify-checkout-interpix-copy-paste">
		<h3 class="flexify-checkout-interpix-copy-title"><?php echo __( 'Pix Copia e cola', 'module-inter-bank-for-flexify-checkout' ); ?>
      <?php if ( ! $is_email ) {
        echo '<span class="pix-copy pix-copy-button" data-clipboard-target="#interpix-payload">' . __( 'Clique para copiar', 'module-inter-bank-for-flexify-checkout' ) . '</span>';
      } ?>
    </h3>
		<textarea id="interpix-payload" readonly data-clipboard-target="#interpix-payload" class="pix-copy interpix-url"><?php echo esc_html( $payload ); ?></textarea>
	</div>
</section>

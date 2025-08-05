<?php

defined('ABSPATH') || exit;

$base = get_option('woocommerce_email_base_color');
$base_text = wc_light_or_dark( $base, '#202020', '#ffffff' );

// Recupere a hora do pedido
$order_date = $order->get_date_created();

// Recupere o tempo restante do Pix
$pix_expires_in = $order->get_meta('inter_pix_expires_in');

// Calcule a hora de expiração do Pix
$pix_expiration_time = strtotime( $order_date ) + (int) $pix_expires_in;

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

.base-timer {
  position: relative;
  width: 8rem;
  height: 8rem;
  margin: auto;
}

.base-timer__svg {
  transform: rotate(90deg);
}

.base-timer__circle {
  fill: none;
  stroke: none;
}

.base-timer__path-elapsed {
  stroke-width: 5px;
  stroke: transparent;
}

.base-timer__path-remaining {
  stroke-width: 5px;
  stroke-linecap: round;
  transition: 1s linear all;
  stroke: #343A40;
}

.base-timer__label {
  position: absolute;
  width: 8rem;
  height: 8rem;
  top: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  color: #343A40;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/clipboard@2.0.10/dist/clipboard.min.js"></script>

<script type="application/javascript" class="flexify-checkout-clipboard-js skip-lazy" data-no-defer="1">
  // Enable copy button
  var clipboard = new ClipboardJS('.pix-copy');

  clipboard.on( 'success', function() {
    const button = jQuery('.pix-copy-button');
    const buttonText = button.text();

    button.text('Copiado!');

    setTimeout(() => {
      button.text( buttonText );
    }, 1000);
  });

  var pix_time_remaining = <?php echo $pix_time_remaining; ?>;
  var timePassed = 0;
  var timeLeft = pix_time_remaining;
  var timerInterval = null;
  var FULL_DASH_ARRAY = 283;
  
  function formatTime(time) {
    const minutes = Math.floor(time / 60);
    let seconds = time % 60;

    if (seconds < 10) {
      seconds = `0${seconds}`;
    }

    return `${minutes}:${seconds}`;
  }

  function startTimer() {
    timerInterval = setInterval(() => {
      timePassed += 1;
      timeLeft = pix_time_remaining - timePassed;

      document.getElementById("base-timer-label").innerHTML = formatTime(timeLeft);
      setCircleDasharray();

      if (timeLeft <= 0) {
        clearInterval(timerInterval);
        document.getElementById("countdown-pix").innerHTML = "Prazo para pagamento expirado.";
        document.querySelector(".flexify-checkout-interpix-title").remove();
        document.querySelector(".flexify-checkout-interpix-container").remove();
        document.querySelector(".flexify-checkout-interpix-copy-paste").remove();
      }
    }, 1000);
  }

  function setCircleDasharray() {
    const circleDasharray = `${(
      calculateTimeFraction() * FULL_DASH_ARRAY
    ).toFixed(0)} 283`;
    document
      .getElementById("base-timer-path-remaining")
      .setAttribute("stroke-dasharray", circleDasharray);
  }

  function calculateTimeFraction() {
    const rawTimeFraction = timeLeft / pix_time_remaining;
    return rawTimeFraction - (1 / pix_time_remaining) * (1 - rawTimeFraction);
  }

  document.addEventListener("DOMContentLoaded", function() {
    document.getElementById("base-timer-label").innerHTML = formatTime(timeLeft);
    startTimer();
  });
</script>

<section id="<?php echo esc_attr( $id ); ?>-thankyou">
  <div id="countdown-pix" class="flexify-checkout-countdown-pix">
    <div class="base-timer">
      <svg class="base-timer__svg" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
        <g class="base-timer__circle">
          <circle class="base-timer__path-elapsed" cx="50" cy="50" r="45"></circle>
          <path id="base-timer-path-remaining" stroke-dasharray="283" class="base-timer__path-remaining"
                d="M 50, 50 m -45, 0 a 45,45 0 1,0 90,0 a 45,45 0 1,0 -90,0"></path>
        </g>
      </svg>
      <span id="base-timer-label" class="base-timer__label"></span>
    </div>
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
        <img class="flexify-checkout-pix qr-code" src="<?php echo esc_attr( $pix_image ); ?>"
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

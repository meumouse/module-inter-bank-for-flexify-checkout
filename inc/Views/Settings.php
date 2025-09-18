<?php

namespace MeuMouse\Flexify_Checkout\Inter_Bank\Views;

use MeuMouse\Flexify_Checkout\Admin\Admin_Options;
use MeuMouse\Flexify_Checkout\API\License;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Add plugin settings to Inter plugin
 * 
 * @since 1.4.0
 * @package MeuMouse.com
 */
class Settings {

    /**
     * Constructor
     * 
     * @since 1.4.0
     * @return void
     */
    public function __construct() {
        // add settings panel to Flexify Checkout
		add_action( 'flexify_checkout_inter_module', array( $this, 'add_admin_settings' ) );
    }


    /**
	 * Add Inter bank settings modal on Flexify Checkout integration tab
	 * 
	 * @since 1.2.0
	 * @version 1.4.0
	 * @return void
	 */
	public function add_admin_settings() {
		?>
		<div id="require_inter_bank_module_container" class="popup-container">
			<div class="popup-content popup-lg">
				<div class="popup-header">
				<h5 class="popup-title"><?php echo esc_html__( 'Configure as formas de pagamento disponíveis:', 'module-inter-bank-for-flexify-checkout' ); ?></h5>
				<button id="require_inter_bank_module_close" class="btn-close fs-lg" aria-label="<?php esc_attr( 'Fechar', 'module-inter-bank-for-flexify-checkout' ); ?>"></button>
				</div>

				<div class="popup-body">
				<table class="form-table">
					<tbody>
						<tr>
							<th>
								<?php echo esc_html( 'Ativar modo depuração', 'module-inter-bank-for-flexify-checkout' ); ?>
								<span class="flexify-checkout-description"><?php echo esc_html__( 'Ative o modo depuração para salvar o registro de requisições da API.', 'module-inter-bank-for-flexify-checkout' ) ?></span>
							</th>
							<td>
								<div class="form-check form-switch">
									<input type="checkbox" class="toggle-switch" id="inter_bank_debug_mode" name="inter_bank_debug_mode" value="yes" <?php checked( Admin_Options::get_setting('inter_bank_debug_mode') === 'yes' ); ?>/>
								</div>
							</td>
						</tr>

						<tr>
							<th>
								<?php echo esc_html( 'Ambiente da API', 'module-inter-bank-for-flexify-checkout' ); ?>
								<span class="flexify-checkout-description"><?php echo esc_html__( 'Permite definir a integração como modo Produção (Operacional) ou Sandbox (Testes).', 'module-inter-bank-for-flexify-checkout' ) ?></span>
							</th>

							<td>
								<select name="inter_bank_env_mode" class="form-select">
									<option value="production" <?php selected( Admin_Options::get_setting('inter_bank_env_mode'), 'production' ) ?>><?php echo esc_html__( 'Produção (Operacional)', 'module-inter-bank-for-flexify-checkout' ) ?></option>
									<option value="sandbox" <?php selected( Admin_Options::get_setting('inter_bank_env_mode'), 'sandbox' ) ?>><?php echo esc_html__( 'Sandbox (Testes)', 'module-inter-bank-for-flexify-checkout' ) ?></option>
								</select>
							</td>
						</tr>

						<tr>
							<th>
								<?php echo esc_html( 'ClientID', 'module-inter-bank-for-flexify-checkout' ); ?>
								<span class="flexify-checkout-description"><?php echo esc_html__( 'Chave aleatória ClientID da API do banco Inter.', 'module-inter-bank-for-flexify-checkout' ) ?></span>
							</th>
							<td>
								<input type="text" class="form-control input-control-wd-20" name="inter_bank_client_id" value="<?php echo Admin_Options::get_setting('inter_bank_client_id' ) ?>"/>
							</td>
						</tr>

						<tr>
							<th>
								<?php echo esc_html( 'ClientSecret', 'module-inter-bank-for-flexify-checkout' ); ?>
								<span class="flexify-checkout-description"><?php echo esc_html__( 'Chave aleatória ClientSecret da API do banco Inter.', 'module-inter-bank-for-flexify-checkout' ) ?></span>
							</th>
							<td>
								<input type="text" class="form-control input-control-wd-20" name="inter_bank_client_secret" value="<?php echo Admin_Options::get_setting('inter_bank_client_secret' ) ?>"/>
							</td>
						</tr>

						<tr>
							<th>
								<?php echo esc_html( 'Data de expiração das credenciais', 'module-inter-bank-for-flexify-checkout' ); ?>
								<span class="flexify-checkout-description"><?php echo esc_html__( 'Informe a data de quando irá expirar as credenciais da aplicação, assim poderemos te avisar 7 dias antes das credenciais serem revogadas.', 'module-inter-bank-for-flexify-checkout' ) ?></span>
							</th>
							<td>
								<input type="text" class="form-control input-control-wd-10 dateselect" name="inter_bank_expire_date" value="<?php echo Admin_Options::get_setting('inter_bank_expire_date' ) ?>"/>
							</td>
						</tr>

						<tr>
							<th>
								<?php echo esc_html( 'Envie sua chave e certificado', 'module-inter-bank-for-flexify-checkout' ); ?>
								<span class="flexify-checkout-description"><?php echo esc_html__( 'Envie sua chave e certificado que você recebeu do banco Inter ao criar a aplicação.', 'module-inter-bank-for-flexify-checkout' ) ?></span>
							</th>
						</tr>
					</tbody>
				</table>

				<div class="drop-file-inter-bank mb-2">
					<?php
					$crt_file = get_option('flexify_checkout_inter_bank_crt_file');
					$key_file = get_option('flexify_checkout_inter_bank_key_file');

					if ( empty( $crt_file ) ) : ?>
						<div class="dropzone py-3 me-2" id="dropzone-crt">
							<div class="drag-text">
							<svg class="drag-and-drop-file-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path d="M19.937 8.68c-.011-.032-.02-.063-.033-.094a.997.997 0 0 0-.196-.293l-6-6a.997.997 0 0 0-.293-.196c-.03-.014-.062-.022-.094-.033a.991.991 0 0 0-.259-.051C13.04 2.011 13.021 2 13 2H6c-1.103 0-2 .897-2 2v16c0 1.103.897 2 2 2h12c1.103 0 2-.897 2-2V9c0-.021-.011-.04-.013-.062a.99.99 0 0 0-.05-.258zM16.586 8H14V5.414L16.586 8zM6 20V4h6v5a1 1 0 0 0 1 1h5l.002 10H6z"></path></svg>
							<?php echo esc_html( 'Arraste e solte o arquivo .crt aqui', 'module-inter-bank-for-flexify-checkout' ); ?>
							</div>

							<div class="file-list"></div>

							<div class="drag-and-drop-file">
							<div class="custom-file">
								<input type="file" class="custom-file-input" id="upload-file-crt" name="crt_file" hidden>
								<label class="custom-file-label" for="upload-file-crt"><?php echo esc_html( 'Ou clique para procurar seu arquivo', 'module-inter-bank-for-flexify-checkout' ); ?></label>
							</div>
							</div>
						</div>
					<?php endif;

					if ( empty( $key_file ) ) : ?>
						<div class="dropzone py-3 ms-2" id="dropzone-key">
							<div class="drag-text">
							<svg class="drag-and-drop-file-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path d="M19.937 8.68c-.011-.032-.02-.063-.033-.094a.997.997 0 0 0-.196-.293l-6-6a.997.997 0 0 0-.293-.196c-.03-.014-.062-.022-.094-.033a.991.991 0 0 0-.259-.051C13.04 2.011 13.021 2 13 2H6c-1.103 0-2 .897-2 2v16c0 1.103.897 2 2 2h12c1.103 0 2-.897 2-2V9c0-.021-.011-.04-.013-.062a.99.99 0 0 0-.05-.258zM16.586 8H14V5.414L16.586 8zM6 20V4h6v5a1 1 0 0 0 1 1h5l.002 10H6z"></path></svg>
							<?php echo esc_html( 'Arraste e solte o arquivo .key aqui', 'module-inter-bank-for-flexify-checkout' ); ?>
							</div>

							<div class="file-list"></div>

							<div class="drag-and-drop-file">
							<div class="custom-file">
								<input type="file" class="custom-file-input" id="upload-file-key" name="key_file" hidden>
								<label class="custom-file-label" for="upload-file-key"><?php echo esc_html( 'Ou clique para procurar seu arquivo', 'module-inter-bank-for-flexify-checkout' ); ?></label>
							</div>
							</div>
						</div>
					<?php endif; ?>
				</div>

				<?php if ( ! empty( $key_file ) && ! empty( $crt_file ) ) : ?>
					<div class="d-grid w-100 justify-content-center">
						<div class="file-uploaded-info mt-3 mb-5 w-100">
							<div class="d-flex flex-column align-items-start me-4">
							<span class="fs-lg mb-2"><?php echo esc_html( 'Sua chave e certificado já foram enviados.', 'module-inter-bank-for-flexify-checkout' ); ?></span>
							<span class="text-muted"><?php echo esc_html( 'Os gateways de pagamento já estão disponíveis para uso.', 'module-inter-bank-for-flexify-checkout' ); ?></span>
							</div>

							<button class="btn btn-icon btn-outline-danger" id="exclude_inter_bank_crt_key_files">
							<svg class="delete-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path d="M15 2H9c-1.103 0-2 .897-2 2v2H3v2h2v12c0 1.103.897 2 2 2h10c1.103 0 2-.897 2-2V8h2V6h-4V4c0-1.103-.897-2-2-2zM9 4h6v2H9V4zm8 16H7V8h10v12z"></path></svg>
							</button>
						</div>

						<?php if ( get_option('flexify_checkout_inter_bank_webhook') === 'enabled' ) : ?>
							<div class="webhook-state mb-5 d-flex align-items-center justify-content-center">
							<div class="ping me-3">
								<svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24" style="fill: #fff"><path d="m10 15.586-3.293-3.293-1.414 1.414L10 18.414l9.707-9.707-1.414-1.414z"></path></svg>
							</div>
							<div class="d-flex flex-column text-left">
								<span class="fs-normal"><?php echo esc_html( 'Ouvindo Webhook do banco Inter', 'module-inter-bank-for-flexify-checkout' ); ?></span>
								<span class="text-muted"><?php echo esc_html( 'Aprovação automática de pedidos ativada', 'module-inter-bank-for-flexify-checkout' ); ?></span>
							</div>
							</div>
						<?php endif; ?>
					</div>
				<?php endif; ?>
				</div>

				<div class="popup-footer">
				<table class="form-table">
					<tbody>
						<!-- START INTER PIX -->
						<tr>
							<th>
							<?php echo esc_html__( 'Ativar recebimento de pagamentos com Pix', 'module-inter-bank-for-flexify-checkout' ); ?>
							<span class="flexify-checkout-description"><?php echo esc_html__( 'Ative esta opção para configurar recibimentos via Pix com aprovação automática gratuitamente (Disponível apenas no Brasil).', 'module-inter-bank-for-flexify-checkout' ) ?></span>
							</th>
							<td class="d-flex align-items-center">
							<div class="form-check form-switch <?php echo ( ! License::is_valid() ) ? 'require-pro' : ''; ?>">
								<input type="checkbox" class="toggle-switch <?php echo ( ! License::is_valid() ) ? 'pro-version' : ''; ?>" id="enable_inter_bank_pix_api" name="enable_inter_bank_pix_api" value="yes" <?php checked( Admin_Options::get_setting('enable_inter_bank_pix_api') === 'yes' && class_exists('Module_Inter_Bank') && License::is_valid() ); ?>/>
							</div>
							</td>
						</tr>

						<tr class="require-enabled-inter-pix">
							<th>
							<?php echo esc_html( 'Título da forma de pagamento Pix', 'module-inter-bank-for-flexify-checkout' ); ?>
							<span class="flexify-checkout-description"><?php echo esc_html__( 'Título que o usuário verá na finalização de compra (Disponível apenas no Brasil).', 'module-inter-bank-for-flexify-checkout' ) ?></span>
							</th>
							<td>
							<input type="text" class="form-control input-control-wd-20" name="pix_gateway_title" value="<?php echo Admin_Options::get_setting('pix_gateway_title' ) ?>"/>
							</td>
						</tr>
						
						<tr class="require-enabled-inter-pix">
							<th>
								<?php echo esc_html( 'Descrição da forma de pagamento Pix', 'module-inter-bank-for-flexify-checkout' ); ?>
								<span class="flexify-checkout-description"><?php echo esc_html__( 'Descrição da forma de pagamento que o usuário verá na finalização de compra.', 'module-inter-bank-for-flexify-checkout' ) ?></span>
							</th>
							<td>
								<input type="text" class="form-control input-control-wd-20" name="pix_gateway_description" value="<?php echo Admin_Options::get_setting('pix_gateway_description' ) ?>"/>
							</td>
						</tr>

						<tr class="require-enabled-inter-pix">
							<th>
								<?php echo esc_html( 'Instruções por e-mail da forma de pagamento Pix', 'module-inter-bank-for-flexify-checkout' ); ?>
								<span class="flexify-checkout-description"><?php echo esc_html__( 'Texto exibido no e-mail junto do botão de copiar código Copia e Cola do Pix.', 'module-inter-bank-for-flexify-checkout' ) ?></span>
							</th>
							<td>
								<input type="text" class="form-control input-control-wd-20" name="pix_gateway_email_instructions" value="<?php echo Admin_Options::get_setting('pix_gateway_email_instructions' ) ?>"/>
							</td>
						</tr>

						<tr class="require-enabled-inter-pix">
							<th>
								<?php echo esc_html( 'Chave Pix', 'module-inter-bank-for-flexify-checkout' ); ?>
								<span class="flexify-checkout-description"><?php echo esc_html__( 'Chave Pix associada ao banco Inter que receberá o pagamento. Para chaves do tipo celular ou CNPJ, utilize apenas números.', 'module-inter-bank-for-flexify-checkout' ) ?></span>
							</th>
							<td>
								<input type="text" class="form-control input-control-wd-20" name="pix_gateway_receipt_key" value="<?php echo Admin_Options::get_setting('pix_gateway_receipt_key' ) ?>"/>
							</td>
						</tr>

						<tr class="require-enabled-inter-pix">
							<th>
								<?php echo esc_html( 'Validade do Pix', 'module-inter-bank-for-flexify-checkout' ); ?>
								<span class="flexify-checkout-description"><?php echo esc_html__( 'Prazo máximo para pagamento do Pix em minutos.', 'module-inter-bank-for-flexify-checkout' ) ?></span>
							</th>
							<td>
								<input type="number" class="form-control input-control-wd-5" name="pix_gateway_expires" value="<?php echo Admin_Options::get_setting('pix_gateway_expires' ) ?>"/>
							</td>
						</tr>

						<tr class="container-separator"></tr>

						<!-- START INTER PIX AUTOMATICO -->
						<tr>
							<th>
								<?php echo esc_html__( 'Ativar recebimento de pagamentos com Pix Automático', 'module-inter-bank-for-flexify-checkout' ); ?>
								<span class="flexify-checkout-description"><?php echo esc_html__( 'Ative esta opção para configurar recebimentos via Pix Automático com aprovação automática.', 'module-inter-bank-for-flexify-checkout' ) ?></span>
							</th>
							<td class="d-flex align-items-center">
								<div class="form-check form-switch">
									<input type="checkbox" class="toggle-switch" id="enable_inter_bank_pix_automatico_api" name="enable_inter_bank_pix_automatico_api" value="yes" <?php checked( Admin_Options::get_setting('enable_inter_bank_pix_automatico_api') === 'yes' ); ?> />
								</div>
							</td>
						</tr>

						<tr class="require-enabled-inter-pix-automatico">
							<th>
								<?php echo esc_html( 'Título da forma de pagamento Pix Automático', 'module-inter-bank-for-flexify-checkout' ); ?>
								<span class="flexify-checkout-description"><?php echo esc_html__( 'Título que o usuário verá na finalização de compra para o Pix Automático.', 'module-inter-bank-for-flexify-checkout' ) ?></span>
							</th>
							<td>
								<input type="text" class="form-control input-control-wd-20" name="pix_automatico_gateway_title" value="<?php echo Admin_Options::get_setting('pix_automatico_gateway_title' ) ?>"/>
							</td>
						</tr>

						<tr class="require-enabled-inter-pix-automatico">
							<th>
								<?php echo esc_html( 'Descrição da forma de pagamento Pix Automático', 'module-inter-bank-for-flexify-checkout' ); ?>
								<span class="flexify-checkout-description"><?php echo esc_html__( 'Descrição exibida durante a finalização da compra.', 'module-inter-bank-for-flexify-checkout' ) ?></span>
							</th>
							<td>
								<input type="text" class="form-control input-control-wd-20" name="pix_automatico_gateway_description" value="<?php echo Admin_Options::get_setting('pix_automatico_gateway_description' ) ?>"/>
							</td>
						</tr>

						<tr class="require-enabled-inter-pix-automatico">
							<th>
								<?php echo esc_html( 'Chave Pix de recebimento', 'module-inter-bank-for-flexify-checkout' ); ?>
								<span class="flexify-checkout-description"><?php echo esc_html__( 'Chave Pix que receberá os pagamentos automáticos.', 'module-inter-bank-for-flexify-checkout' ) ?></span>
							</th>
							<td>
								<input type="text" class="form-control input-control-wd-20" name="pix_automatico_gateway_receipt_key" value="<?php echo Admin_Options::get_setting('pix_automatico_gateway_receipt_key' ) ?>"/>
							</td>
						</tr>

						<tr class="require-enabled-inter-pix-automatico">
							<th>
								<?php echo esc_html( 'Permitir retentativas de cobrança', 'module-inter-bank-for-flexify-checkout' ); ?>
								<span class="flexify-checkout-description"><?php echo esc_html__( 'Permite até 3 retentativas em dias diferentes no intervalo de até 7 dias corridos contados a partir da data de liquidação prevista na instrução de pagamento original.', 'module-inter-bank-for-flexify-checkout' ) ?></span>
							</th>
							<td class="d-flex align-items-center">
								<div class="form-check form-switch">
									<input type="checkbox" class="toggle-switch" id="enable_inter_retry_billing_policy" name="enable_inter_retry_billing_policy" value="yes" <?php checked( Admin_Options::get_setting('enable_inter_retry_billing_policy') === 'yes' && class_exists('Module_Inter_Bank') ); ?>/>
								</div>
							</td>
						</tr>

						<tr class="container-separator"></tr>

						<!-- START INTER BANK SLIP -->
						<tr>
							<th>
								<?php echo esc_html__( 'Ativar recebimento de pagamentos com boleto bancário + Pix', 'module-inter-bank-for-flexify-checkout' );
								
								if ( ! License::is_valid() ) : ?>
									<span class="badge pro bg-primary rounded-pill ms-2">
										<svg class="icon-pro" viewBox="0 0 24.00 24.00" xmlns="http://www.w3.org/2000/svg"><g stroke-width="0"></g><g stroke-linecap="round" stroke-linejoin="round" stroke="#CCCCCC" stroke-width="0.336"></g><g><path fill-rule="evenodd" clip-rule="evenodd" d="M12.0001 3C12.3334 3 12.6449 3.16613 12.8306 3.443L16.6106 9.07917L21.2523 3.85213C21.5515 3.51525 22.039 3.42002 22.4429 3.61953C22.8469 3.81904 23.0675 4.26404 22.9818 4.70634L20.2956 18.5706C20.0223 19.9812 18.7872 21 17.3504 21H6.64977C5.21293 21 3.97784 19.9812 3.70454 18.5706L1.01833 4.70634C0.932635 4.26404 1.15329 3.81904 1.55723 3.61953C1.96117 3.42002 2.44865 3.51525 2.74781 3.85213L7.38953 9.07917L11.1696 3.443C11.3553 3.16613 11.6667 3 12.0001 3ZM12.0001 5.79533L8.33059 11.2667C8.1582 11.5237 7.8765 11.6865 7.56772 11.7074C7.25893 11.7283 6.95785 11.6051 6.75234 11.3737L3.67615 7.90958L5.66802 18.1902C5.75913 18.6604 6.17082 19 6.64977 19H17.3504C17.8293 19 18.241 18.6604 18.3321 18.1902L20.324 7.90958L17.2478 11.3737C17.0423 11.6051 16.7412 11.7283 16.4324 11.7074C16.1236 11.6865 15.842 11.5237 15.6696 11.2667L12.0001 5.79533Z"></path> </g></svg>
										<?php echo esc_html__( 'Pro', 'module-inter-bank-for-flexify-checkout' ) ?>
									</span>
								<?php endif; ?>
								
								<span class="flexify-checkout-description"><?php echo esc_html__( 'Ative esta opção para configurar recibimentos via boleto bancário com QR code Pix no boleto e aprovação automática.', 'module-inter-bank-for-flexify-checkout' ) ?></span>
							</th>

							<td class="d-flex align-items-center">
								<div class="form-check form-switch <?php echo ( ! License::is_valid() ) ? 'require-pro' : ''; ?>">
									<input type="checkbox" class="toggle-switch <?php echo ( ! License::is_valid() ) ? 'pro-version' : ''; ?>" id="enable_inter_bank_ticket_api" name="enable_inter_bank_ticket_api" value="yes" <?php checked( Admin_Options::get_setting('enable_inter_bank_ticket_api') === 'yes' && class_exists('Module_Inter_Bank') && License::is_valid() ); ?>/>
								</div>
							</td>
						</tr>

						<tr class="require-enabled-inter-slip-bank">
							<th>
								<?php echo esc_html( 'Título da forma de pagamento Boleto', 'module-inter-bank-for-flexify-checkout' ); ?>
								<span class="flexify-checkout-description"><?php echo esc_html__( 'Título que o usuário verá na finalização de compra.', 'module-inter-bank-for-flexify-checkout' ) ?></span>
							</th>
							<td>
								<input type="text" class="form-control input-control-wd-20" name="bank_slip_gateway_title" value="<?php echo Admin_Options::get_setting('bank_slip_gateway_title' ) ?>"/>
							</td>
						</tr>

						<tr class="require-enabled-inter-slip-bank">
							<th>
								<?php echo esc_html( 'Descrição da forma de pagamento Boleto', 'module-inter-bank-for-flexify-checkout' ); ?>
								<span class="flexify-checkout-description"><?php echo esc_html__( 'Descrição da forma de pagamento que o usuário verá na finalização de compra.', 'module-inter-bank-for-flexify-checkout' ) ?></span>
							</th>
							<td>
								<input type="text" class="form-control input-control-wd-20" name="bank_slip_gateway_description" value="<?php echo Admin_Options::get_setting('bank_slip_gateway_description' ) ?>"/>
							</td>
						</tr>

						<tr class="require-enabled-inter-slip-bank">
							<th>
								<?php echo esc_html( 'Instruções por e-mail da forma de pagamento Boleto', 'module-inter-bank-for-flexify-checkout' ); ?>
								<span class="flexify-checkout-description"><?php echo esc_html__( 'Texto exibido no e-mail junto do botão de copiar código Copia e Cola do Pix.', 'module-inter-bank-for-flexify-checkout' ) ?></span>
							</th>
							<td>
								<input type="text" class="form-control input-control-wd-20" name="bank_slip_gateway_email_instructions" value="<?php echo Admin_Options::get_setting('bank_slip_gateway_email_instructions' ) ?>"/>
							</td>
						</tr>

						<tr class="require-enabled-inter-slip-bank">
							<th>
								<?php echo esc_html( 'Mensagem do rodapé', 'module-inter-bank-for-flexify-checkout' ); ?>
								<span class="flexify-checkout-description"><?php echo esc_html__( 'Mensagem do rodapé do boleto bancário. Use a variável {order_id} para inserir o número do pedido.', 'module-inter-bank-for-flexify-checkout' ) ?></span>
							</th>
							<td>
								<input type="text" class="form-control input-control-wd-20" name="bank_slip_gateway_footer_message" value="<?php echo Admin_Options::get_setting('bank_slip_gateway_footer_message' ) ?>"/>
							</td>
						</tr>
						
						<tr class="require-enabled-inter-slip-bank">
							<th>
								<?php echo esc_html( 'Validade do boleto', 'module-inter-bank-for-flexify-checkout' ); ?>
								<span class="flexify-checkout-description"><?php echo esc_html__( 'Prazo máximo para pagamento do boleto em dias.', 'module-inter-bank-for-flexify-checkout' ) ?></span>
							</th>
							<td>
								<input type="number" class="form-control input-control-wd-5" name="bank_slip_gateway_expires" value="<?php echo Admin_Options::get_setting('bank_slip_gateway_expires' ) ?>"/>
							</td>
						</tr>
					</tbody>
				</table>
				</div>
			</div>
		</div>
		<?php
	}
}
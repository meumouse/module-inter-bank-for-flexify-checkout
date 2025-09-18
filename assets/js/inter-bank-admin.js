(function ($) {
	"use strict";

    /**
     * Get Flexify Checkout Admin API
     * 
     * @since 1.4.0
     * @return {object}
     */
    const FCA = window.Flexify_Checkout_Admin;

    if ( FCA && ( ! FCA.setupVisibilityControllers || !FCA.setupVisibilityControllers.attach ) ) {
        FCA.setupVisibilityControllers = FCA.setupVisibilityControllers || {};

        FCA.setupVisibilityControllers.attach = function(triggerSel, target, containerSel = null) {
            const $trigger = $(triggerSel);

            const apply = () => {
                const val = $trigger.val();

                if ( $trigger.is(':checkbox') ) {
                    const on = $trigger.is(':checked');
                    if (typeof target === 'string') $(target).toggleClass('d-none', !on);

                    return;
                }

                if (typeof target === 'object' && !Array.isArray(target)) {
                    $.each(target, function(option, container) {
                        $(container).toggleClass('d-none', val !== option);
                    });

                    return;
                }

                if (Array.isArray(target) && containerSel) {
                    $(containerSel).toggleClass('d-none', !target.includes(val));
                    return;
                }

                if (typeof target === 'string') {
                    const on = (val !== 'no' && val !== 'false' && val !== '');
                    $(target).toggleClass('d-none', !on);
                }
            };

            $(document).off('change', triggerSel).on('change', triggerSel, apply);
            apply();
        };
    }

	/**
	 * Inter Bank Certificates (upload/remove)
	 *
	 * - Drag & drop + input change para enviar .crt/.key
	 * - Remoção via botão com spinner e toasts
	 *
	 * @since 1.2.0
	 * @package MeuMouse.com
	 */
	const Inter_Bank_Module = {
		// Fallbacks: permite usar a mesma base de AJAX do Flexify ou do módulo Inter Bank
		params: window.flexify_checkout_params || {},
		ib: window.inter_bank_params || {},

		/**
		 * Get AJAX URL (prefere Flexify; fallback Inter Bank)
		 * @returns {string}
		 */
		ajaxUrl() {
			return this.params.ajax_url || this.ib.ajax_url || '';
		},

		/**
		 * Attach dragover/dragleave/drop + input change
         * 
		 * @since 1.2.0
		 */
		bindUploadUI() {
			const dzSel = '.dropzone';
			const fileSel = '#upload-file-crt, #upload-file-key';

			// drag over/leave
			$(document).on('dragover dragleave', dzSel, (e) => {
				e.preventDefault();
				$(e.currentTarget).toggleClass('drag-over', e.type === 'dragover');
			});

			// drop
			$(document).on('drop', dzSel, (e) => {
				e.preventDefault();
				const $dz = $(e.currentTarget);
				const file = e.originalEvent.dataTransfer?.files?.[0];

				if ( ! file || $dz.hasClass('file-uploaded') ) {
                    return;
                }

				this.handleFileUpload(file, $dz);
			});

			// input file
			$(document).on('change', fileSel, (e) => {
				e.preventDefault();
				const file = e.target.files?.[0];
				const $dz = $(e.currentTarget).closest('.dropzone');

				if ( file && $dz.length ) {
					this.handleFileUpload(file, $dz);
				}
			});
		},

		/**
		 * Upload handler (UI + AJAX)
		 * @since 1.2.0
		 * @param {File} file
		 * @param {jQuery} $dropzone
		 */
		handleFileUpload(file, $dropzone) {
			const filename = file.name;

			// UI state
			$dropzone.children('.file-list').removeClass('d-none').text(filename);
			$dropzone.addClass('file-processing');
			$dropzone.append('<div class="spinner-border"></div>');
			$dropzone.children('.drag-text, .drag-and-drop-file, .form-inter-bank-files').addClass('d-none');

			// Build payload
			const fd = new FormData();
			fd.append('action', 'upload_file');
			fd.append('file', file);
			fd.append('type', $dropzone.attr('id'));

			$.ajax({
				url: this.ajaxUrl(),
				type: 'POST',
				data: fd,
				processData: false,
				contentType: false,
			})
				.done((response) => {
					try {
						if (response?.status === 'success') {
							$dropzone.addClass('file-uploaded').removeClass('file-processing');
							$dropzone.children('.spinner-border').remove();

							$dropzone.append(
								'<div class="upload-notice d-flex flex-column align-items-center">' +
									'<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"><path fill="#22c55e" d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zm0 18c-4.411 0-8-3.589-8-8s3.589-8 8-8 8 3.589 8 8-3.589 8-8 8z"></path><path fill="#22c55e" d="M9.999 13.587 7.7 11.292l-1.412 1.416 3.713 3.705 6.706-6.706-1.414-1.414z"></path></svg>' +
									`<span>${response.message || ''}</span>` +
								'</div>'
							);

							$dropzone.children('.file-list').addClass('d-none');
						} else if (response?.status === 'invalid_file') {
							$('.drop-file-inter-bank').after(`<div class="text-danger mt-2"><p>${response.message || ''}</p></div>`);
							this.resetDropzoneUI($dropzone);
						} else {
							// Fallback: erro genérico
							this.resetDropzoneUI($dropzone);
							if (window.Flexify_Checkout_Admin?.displayToast) {
								Flexify_Checkout_Admin.displayToast('danger', 'Erro', 'Falha ao enviar o arquivo.');
							}
						}
					} catch (err) {
						console.error(err);
						this.resetDropzoneUI($dropzone);
					}
				})
				.fail((xhr, status, error) => {
					console.error('Upload error:', status, error, xhr?.responseText);
					this.resetDropzoneUI($dropzone);
				});
		},

		/**
		 * Reset dropzone UI to initial state after failure/invalid
         * 
		 * @since 1.2.0
		 * @param {jQuery} $dropzone
		 */
		resetDropzoneUI($dropzone) {
			$dropzone.addClass('invalid-file').removeClass('file-processing');
			$dropzone.children('.spinner-border').remove();
			$dropzone.children('.drag-text, .drag-and-drop-file, .form-inter-bank-files').removeClass('d-none');
			$dropzone.children('.file-list').addClass('d-none');
		},

		/**
		 * Bind removal button for certificates
         * 
		 * @since 1.2.0
		 */
		bindRemove() {
			const btnSel = '#exclude_inter_bank_crt_key_files';

			$(document).on('click', btnSel, (e) => {
				e.preventDefault();

				const confirmMsg =
					this.params.confirm_remove_certificates ||
					this.ib.confirm_remove_certificates ||
					'Remover certificados?';

				if ( ! window.confirm(confirmMsg) ) {
                    return;
                }

				const $btn = $(e.currentTarget);
				const state = window.Flexify_Checkout_Admin?.keepButtonState
						? Flexify_Checkout_Admin.keepButtonState($btn)
						: { width: $btn.width(), height: $btn.height(), html: $btn.html() };

				$btn.width(state.width).height(state.height).html('<span class="spinner-border spinner-border-sm"></span>');

				$.ajax({
					url: this.ajaxUrl(),
					type: 'POST',
					dataType: 'json',
					data: {
						action: 'remove_certificates',
						security: this.ib.nonce || '',
					},
				})
					.done((response) => {
						if (response?.status === 'success') {
							$btn.removeClass('btn-outline-danger').addClass('btn-success').html('<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" style="fill:#ffffff"><path d="m10 15.586-3.293-3.293-1.414 1.414L10 18.414l9.707-9.707-1.414-1.414z"></path></svg>');

							// close modal
							$('#require_inter_bank_module_close').trigger('click');

							if ( window.Flexify_Checkout_Admin?.displayToast ) {
								Flexify_Checkout_Admin.displayToast( 'success', response.toast_header_title || '', response.toast_body_title || '' );
							}

							setTimeout(() => location.reload(), 1000);
						} else {
							$btn.html(state.html);

							if ( window.Flexify_Checkout_Admin?.displayToast ) {
								Flexify_Checkout_Admin.displayToast( 'danger',
									response?.toast_header_title || 'Erro',
									response?.toast_body_title || 'Não foi possível remover os certificados.'
								);
							}

							$('.toast-certificates').fadeOut('fast', function () {
								$(this).remove();
							});
						}
					})
					.fail((xhr, status, error) => {
						console.error(error);
						$btn.html(state.html);

						if (window.Flexify_Checkout_Admin?.displayToast) {
							Flexify_Checkout_Admin.displayToast('danger', 'Erro', 'Falha na requisição para remover certificados.');
						}
					});
			});
		},

        /**
         * Handle with toggle visibility controllers
         * 
         * @since 1.4.0
         */
        toggleVisibility: function() {
            const attach = FCA?.setupVisibilityControllers?.attach;

            if ( typeof attach === 'function' ) {
                attach('#enable_inter_bank_pix_automatico_api', '.require-enabled-inter-pix-automatico');
            }
        },

		/**
		 * Init module
         * 
		 * @since 1.4.0
		 */
		init() {
			this.bindUploadUI();
			this.bindRemove();
            this.toggleVisibility();
		},
	};

	/**
	 * Initialize when admin is ready
     * 
     * @since 1.4.0
	 */
	$(document).on('ready', function () {
        if (window.Flexify_Checkout_Admin) {
            window.Flexify_Checkout_Admin.Inter_Bank_Module = Inter_Bank_Module;
        }

        Inter_Bank_Module.init();
	});
})(jQuery);
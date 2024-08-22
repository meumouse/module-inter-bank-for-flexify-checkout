/**
 * Process upload key and crt files
 * 
 * @since 1.2.0
 * @package MeuMouse.com
 */
jQuery(document).ready( function($) {
    // Add event handlers for dragover and dragleave
    $('.dropzone').on('dragover dragleave', function(e) {
        e.preventDefault();
        $(this).toggleClass('drag-over', e.type === 'dragover');
    });

    // Add event handlers for drop
    $('.dropzone').on('drop', function(e) {
        e.preventDefault();

        var file = e.originalEvent.dataTransfer.files[0];

        if ( ! $(this).hasClass('file-uploaded') ) {
            handle_certificates(file, $(this));
        }
    });

    // Adds a change event handler to the input file
    $('#upload-file-crt, #upload-file-key').on('change', function(e) {
        e.preventDefault();

        var file = e.target.files[0];

        handle_certificates(file, $(this).parents('.dropzone'));
    });

    function handle_certificates(file, dropzone) {
        if (file) {
            var filename = file.name;

            dropzone.children('.file-list').removeClass('d-none').text(filename);
            dropzone.addClass('file-processing');
            dropzone.append('<div class="spinner-border"></div>');
            dropzone.children('.drag-text').addClass('d-none');
            dropzone.children('.drag-and-drop-file').addClass('d-none');
            dropzone.children('.form-inter-bank-files').addClass('d-none');

            // Create a FormData object to send the file via AJAX
            var form_data = new FormData();
            form_data.append('action', 'upload_file');
            form_data.append('file', file);
            form_data.append('type', dropzone.attr('id'));

            $.ajax({
                url: inter_bank_params.ajax_url,
                type: 'POST',
                data: form_data,
                processData: false,
                contentType: false,
                success: function(response) {
                    try {
                        if (response.status === 'success') {
                            dropzone.addClass('file-uploaded').removeClass('file-processing');
                            dropzone.children('.spinner-border').remove();
                            dropzone.append('<div class="upload-notice d-flex flex-column align-items-center"><svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"><path fill="#22c55e" d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zm0 18c-4.411 0-8-3.589-8-8s3.589-8 8-8 8 3.589 8 8-3.589 8-8 8z"></path><path fill="#22c55e" d="M9.999 13.587 7.7 11.292l-1.412 1.416 3.713 3.705 6.706-6.706-1.414-1.414z"></path></svg><span>'+ response.message +'</span></div>');
                            dropzone.children('.file-list').addClass('d-none');
                        } else if (response.status === 'invalid_file') {
                            $('.drop-file-inter-bank').after('<div class="text-danger mt-2"><p>'+ response.message +'</p></div>');
                            dropzone.addClass('invalid-file').removeClass('file-processing');
                            dropzone.children('.spinner-border').remove();
                            dropzone.children('.drag-text').removeClass('d-none');
                            dropzone.children('.drag-and-drop-file').removeClass('d-none');
                            dropzone.children('.form-inter-bank-files').removeClass('d-none');
                            dropzone.children('.file-list').addClass('d-none');
                        }
                    } catch (error) {
                        console.log(error);
                    }
                },
                error: function(xhr, status, error) {
                    dropzone.addClass('fail-upload').removeClass('file-processing');
                    console.log('Erro ao enviar o arquivo');
                    console.log(xhr.responseText);
                }
            });
        }
    }
});

/**
 * Exclude integration certificates process
 * 
 * @since 1.2.0
 * @package MeuMouse.com
 */
jQuery(document).ready( function($) {
    $(document).on('click', '#exclude_inter_bank_crt_key_files', function(e) {
        e.preventDefault();

        var confirm_deactivate_license = confirm(inter_bank_params.confirm_remove_certificates);

        if ( ! confirm_deactivate_license ) {
            return;
        }

        var btn = $(this);
        var btn_width = btn.width();
        var btn_html = btn.html();
        var btn_height = btn.height();

        // keep original width and height
        btn.width(btn_width);
        btn.height(btn_height);

        // Add spinner inside button
        btn.html('<span class="spinner-border spinner-border-sm"></span>');

        jQuery.ajax({
            url: inter_bank_params.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'remove_certificates',
                security: inter_bank_params.nonce,
            },
            success: function(response) {
                if (response.status === 'success') {
                    btn.removeClass('btn-outline-danger').addClass('btn-success').html('<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" style="fill: #ffffff"><path d="m10 15.586-3.293-3.293-1.414 1.414L10 18.414l9.707-9.707-1.414-1.414z"></path></svg>');
                    $('#require_inter_bank_module_close').click();

                    $('.flexify-checkout-wrapper').before(`<div class="toast toast-success show">
                        <div class="toast-header bg-success text-white">
                            <svg class="icon icon-white me-2" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="#ffffff"><g stroke-width="0"/><g stroke-linecap="round" stroke-linejoin="round"/><g><path d="M10.5 15.25C10.307 15.2353 10.1276 15.1455 9.99998 15L6.99998 12C6.93314 11.8601 6.91133 11.7029 6.93756 11.55C6.96379 11.3971 7.03676 11.2562 7.14643 11.1465C7.2561 11.0368 7.39707 10.9638 7.54993 10.9376C7.70279 10.9114 7.86003 10.9332 7.99998 11L10.47 13.47L19 5.00004C19.1399 4.9332 19.2972 4.91139 19.45 4.93762C19.6029 4.96385 19.7439 5.03682 19.8535 5.14649C19.9632 5.25616 20.0362 5.39713 20.0624 5.54999C20.0886 5.70286 20.0668 5.86009 20 6.00004L11 15C10.8724 15.1455 10.6929 15.2353 10.5 15.25Z" fill="#ffffff"/> <path d="M12 21C10.3915 20.9974 8.813 20.5638 7.42891 19.7443C6.04481 18.9247 4.90566 17.7492 4.12999 16.34C3.54037 15.29 3.17596 14.1287 3.05999 12.93C2.87697 11.1721 3.2156 9.39921 4.03363 7.83249C4.85167 6.26578 6.1129 4.9746 7.65999 4.12003C8.71001 3.53041 9.87134 3.166 11.07 3.05003C12.2641 2.92157 13.4719 3.03725 14.62 3.39003C14.7224 3.4105 14.8195 3.45215 14.9049 3.51232C14.9903 3.57248 15.0622 3.64983 15.116 3.73941C15.1698 3.82898 15.2043 3.92881 15.2173 4.03249C15.2302 4.13616 15.2214 4.2414 15.1913 4.34146C15.1612 4.44152 15.1105 4.53419 15.0425 4.61352C14.9745 4.69286 14.8907 4.75712 14.7965 4.80217C14.7022 4.84723 14.5995 4.87209 14.4951 4.87516C14.3907 4.87824 14.2867 4.85946 14.19 4.82003C13.2186 4.52795 12.1987 4.43275 11.19 4.54003C10.193 4.64212 9.22694 4.94485 8.34999 5.43003C7.50512 5.89613 6.75813 6.52088 6.14999 7.27003C5.52385 8.03319 5.05628 8.91361 4.77467 9.85974C4.49307 10.8059 4.40308 11.7987 4.50999 12.78C4.61208 13.777 4.91482 14.7431 5.39999 15.62C5.86609 16.4649 6.49084 17.2119 7.23999 17.82C8.00315 18.4462 8.88357 18.9137 9.8297 19.1953C10.7758 19.4769 11.7686 19.5669 12.75 19.46C13.747 19.3579 14.713 19.0552 15.59 18.57C16.4349 18.1039 17.1818 17.4792 17.79 16.73C18.4161 15.9669 18.8837 15.0864 19.1653 14.1403C19.4469 13.1942 19.5369 12.2014 19.43 11.22C19.4201 11.1169 19.4307 11.0129 19.461 10.9139C19.4914 10.8149 19.5409 10.7228 19.6069 10.643C19.6728 10.5631 19.7538 10.497 19.8453 10.4485C19.9368 10.3999 20.0369 10.3699 20.14 10.36C20.2431 10.3502 20.3471 10.3607 20.4461 10.3911C20.5451 10.4214 20.6372 10.471 20.717 10.5369C20.7969 10.6028 20.863 10.6839 20.9115 10.7753C20.9601 10.8668 20.9901 10.9669 21 11.07C21.1821 12.829 20.842 14.6026 20.0221 16.1695C19.2022 17.7363 17.9389 19.0269 16.39 19.88C15.3288 20.4938 14.1495 20.8755 12.93 21C12.62 21 12.3 21 12 21Z" fill="#ffffff"/></g></svg>
                            <span class="me-auto">${response.toast_header_title}</span>
                            <button class="btn-close btn-close-white ms-2 hide-toast" type="button" data-bs-dismiss="toast" aria-label="Close"></button>
                        </div>
                        <div class="toast-body">${response.toast_body_title}</div>
                    </div>`);

                    setTimeout( function() {
                        location.reload();
                    }, 1000);
                } else {
                    btn.html(btn_html);

                    $('.flexify-checkout-wrapper').before(`<div class="toast toast-certificates toast-danger show">
                        <div class="toast-header bg-danger text-white">
                            <svg class="icon icon-white me-2" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" stroke="#ffffff"><g stroke-width="0"/><g stroke-linecap="round" stroke-linejoin="round"/><g><path d="M10.5 15.25C10.307 15.2353 10.1276 15.1455 9.99998 15L6.99998 12C6.93314 11.8601 6.91133 11.7029 6.93756 11.55C6.96379 11.3971 7.03676 11.2562 7.14643 11.1465C7.2561 11.0368 7.39707 10.9638 7.54993 10.9376C7.70279 10.9114 7.86003 10.9332 7.99998 11L10.47 13.47L19 5.00004C19.1399 4.9332 19.2972 4.91139 19.45 4.93762C19.6029 4.96385 19.7439 5.03682 19.8535 5.14649C19.9632 5.25616 20.0362 5.39713 20.0624 5.54999C20.0886 5.70286 20.0668 5.86009 20 6.00004L11 15C10.8724 15.1455 10.6929 15.2353 10.5 15.25Z" fill="#ffffff"/> <path d="M12 21C10.3915 20.9974 8.813 20.5638 7.42891 19.7443C6.04481 18.9247 4.90566 17.7492 4.12999 16.34C3.54037 15.29 3.17596 14.1287 3.05999 12.93C2.87697 11.1721 3.2156 9.39921 4.03363 7.83249C4.85167 6.26578 6.1129 4.9746 7.65999 4.12003C8.71001 3.53041 9.87134 3.166 11.07 3.05003C12.2641 2.92157 13.4719 3.03725 14.62 3.39003C14.7224 3.4105 14.8195 3.45215 14.9049 3.51232C14.9903 3.57248 15.0622 3.64983 15.116 3.73941C15.1698 3.82898 15.2043 3.92881 15.2173 4.03249C15.2302 4.13616 15.2214 4.2414 15.1913 4.34146C15.1612 4.44152 15.1105 4.53419 15.0425 4.61352C14.9745 4.69286 14.8907 4.75712 14.7965 4.80217C14.7022 4.84723 14.5995 4.87209 14.4951 4.87516C14.3907 4.87824 14.2867 4.85946 14.19 4.82003C13.2186 4.52795 12.1987 4.43275 11.19 4.54003C10.193 4.64212 9.22694 4.94485 8.34999 5.43003C7.50512 5.89613 6.75813 6.52088 6.14999 7.27003C5.52385 8.03319 5.05628 8.91361 4.77467 9.85974C4.49307 10.8059 4.40308 11.7987 4.50999 12.78C4.61208 13.777 4.91482 14.7431 5.39999 15.62C5.86609 16.4649 6.49084 17.2119 7.23999 17.82C8.00315 18.4462 8.88357 18.9137 9.8297 19.1953C10.7758 19.4769 11.7686 19.5669 12.75 19.46C13.747 19.3579 14.713 19.0552 15.59 18.57C16.4349 18.1039 17.1818 17.4792 17.79 16.73C18.4161 15.9669 18.8837 15.0864 19.1653 14.1403C19.4469 13.1942 19.5369 12.2014 19.43 11.22C19.4201 11.1169 19.4307 11.0129 19.461 10.9139C19.4914 10.8149 19.5409 10.7228 19.6069 10.643C19.6728 10.5631 19.7538 10.497 19.8453 10.4485C19.9368 10.3999 20.0369 10.3699 20.14 10.36C20.2431 10.3502 20.3471 10.3607 20.4461 10.3911C20.5451 10.4214 20.6372 10.471 20.717 10.5369C20.7969 10.6028 20.863 10.6839 20.9115 10.7753C20.9601 10.8668 20.9901 10.9669 21 11.07C21.1821 12.829 20.842 14.6026 20.0221 16.1695C19.2022 17.7363 17.9389 19.0269 16.39 19.88C15.3288 20.4938 14.1495 20.8755 12.93 21C12.62 21 12.3 21 12 21Z" fill="#ffffff"/></g></svg>
                            <span class="me-auto">${response.toast_header_title}</span>
                            <button class="btn-close btn-close-white ms-2 hide-toast" type="button" aria-label="Fechar"></button>
                        </div>
                        <div class="toast-body">${response.toast_body_title}</div>
                    </div>`);

                    setTimeout( function() {
                        $('.toast-certificates').fadeOut('fast');
                    }, 3000);

                    setTimeout( function() {
                        $('.toast-certificates').remove();
                    }, 3500);
                }
            },
            error: function(xhr, status, error) {
                console.log(error);
            }
        });
    });
});
jQuery(document).ready(function($) {
    // Mostrar/esconder campos quando o checkbox principal é alterado
    $('.cpt-enabled').on('change', function() {
        const $fields = $(this).closest('.cpt-item').find('.cpt-fields');
        if ($(this).is(':checked')) {
            $fields.slideDown();
        } else {
            $fields.slideUp();
        }
    });

    // Mostrar campos para CPTs já habilitados
    $('.cpt-enabled:checked').each(function() {
        $(this).closest('.cpt-item').find('.cpt-fields').show();
    });

    // Salvar configurações
    $('#save-settings').on('click', function() {
        const settings = {
            enabled_cpts: {}
        };

        // Coletar configurações de cada CPT
        $('.cpt-item').each(function() {
            const postType = $(this).data('type');
            const enabled = $(this).find('.cpt-enabled').is(':checked');
            
            if (enabled) {
                settings.enabled_cpts[postType] = {
                    enabled: true,
                    meta_fields: {},
                    taxonomies: {}
                };

                // Coletar meta fields selecionados
                $(this).find('.meta-fields input:checked').each(function() {
                    settings.enabled_cpts[postType].meta_fields[$(this).val()] = true;
                });

                // Coletar taxonomias selecionadas
                $(this).find('.taxonomies input:checked').each(function() {
                    settings.enabled_cpts[postType].taxonomies[$(this).val()] = true;
                });
            }
        });

        // Salvar via AJAX
        $.ajax({
            url: pdfGeneratorAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'save_cpt_settings',
                settings: JSON.stringify(settings),
                nonce: pdfGeneratorAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('Configurações salvas com sucesso!');
                    location.reload();
                } else {
                    alert('Erro ao salvar configurações: ' + response.data);
                }
            },
            error: function() {
                alert('Erro ao processar a requisição');
            }
        });
    });
});

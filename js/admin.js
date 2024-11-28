jQuery(document).ready(function($) {
    // Inicializar sortable para os campos
    $('.sortable-fields').each(function() {
        $(this).sortable({
            handle: '.handle',
            items: '.field-item.selected',
            placeholder: 'field-item-placeholder',
            opacity: 0.7,
            update: function(event, ui) {
                updateFieldOrder($(this));
            }
        });
    });

    // Toggle de campos selecionados
    $('.field-item input[type="checkbox"]').on('change', function() {
        var $item = $(this).closest('.field-item');
        var $sortable = $item.closest('.sortable-fields');
        
        if (this.checked) {
            $item.addClass('selected').removeClass('disabled');
        } else {
            $item.addClass('disabled').removeClass('selected');
        }
        
        $sortable.sortable('refresh');
        updateFieldOrder($sortable);
    });

    // Toggle de CPT
    $('.cpt-enabled').on('change', function() {
        var $fields = $(this).closest('.cpt-item').find('.cpt-fields');
        if (this.checked) {
            $fields.slideDown();
        } else {
            $fields.slideUp();
        }
    });

    // Atualizar ordem dos campos
    function updateFieldOrder($sortable) {
        var $selectedItems = $sortable.find('.field-item.selected');
        $selectedItems.each(function(index) {
            $(this).attr('data-order', index);
        });
    }

    // Salvar configurações
    $('#save-settings').on('click', function() {
        var settings = {};
        var $button = $(this);

        // Coletar configurações de cada CPT
        $('.cpt-item').each(function() {
            var $cpt = $(this);
            var type = $cpt.data('type');
            var enabled = $cpt.find('.cpt-enabled').is(':checked');

            settings[type] = {
                enabled: enabled,
                meta_fields: {},
                taxonomies: {},
                field_order: {}
            };

            if (enabled) {
                // Coletar campos meta selecionados com ordem
                $cpt.find('.meta-fields .field-item.selected').each(function(index) {
                    var $field = $(this);
                    var fieldName = $field.find('input[type="checkbox"]').val();
                    settings[type].meta_fields[fieldName] = true;
                    settings[type].field_order[fieldName] = index;
                });

                // Coletar taxonomias selecionadas com ordem
                var metaFieldsCount = Object.keys(settings[type].meta_fields).length;
                $cpt.find('.taxonomies .field-item.selected').each(function(index) {
                    var $field = $(this);
                    var fieldName = $field.find('input[type="checkbox"]').val();
                    settings[type].taxonomies[fieldName] = true;
                    settings[type].field_order[fieldName] = metaFieldsCount + index;
                });
            }
        });

        // Debug
        console.log('Configurações a serem salvas:', settings);

        // Enviar configurações via AJAX
        $button.prop('disabled', true).text('Salvando...');

        $.ajax({
            url: pdfGeneratorAdmin.ajaxurl,
            method: 'POST',
            data: {
                action: 'save_cpt_settings',
                nonce: pdfGeneratorAdmin.nonce,
                settings: JSON.stringify(settings)
            },
            success: function(response) {
                console.log('Resposta do servidor:', response);
                if (response.success) {
                    $button.text('Configurações Salvas!');
                    setTimeout(function() {
                        $button.prop('disabled', false).text('Salvar Configurações');
                    }, 2000);
                } else {
                    var errorMsg = response.data || 'Erro desconhecido';
                    alert('Erro ao salvar configurações: ' + errorMsg);
                    $button.prop('disabled', false).text('Salvar Configurações');
                    console.error('Erro ao salvar:', response);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('Erro AJAX:', textStatus, errorThrown);
                alert('Erro ao salvar configurações. Tente novamente.');
                $button.prop('disabled', false).text('Salvar Configurações');
            }
        });
    });

    // Inicializar estado dos campos
    $('.cpt-enabled').each(function() {
        if (!this.checked) {
            $(this).closest('.cpt-item').find('.cpt-fields').hide();
        }
    });

    // Inicializar ordem dos campos
    $('.sortable-fields').each(function() {
        updateFieldOrder($(this));
    });
});

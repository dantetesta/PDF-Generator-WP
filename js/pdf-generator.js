jQuery(document).ready(function($) {
    $('.generate-pdf-btn').on('click', function(e) {
        e.preventDefault();
        const $button = $(this);
        const postId = $button.data('id');
        const postType = $button.data('type');
        
        // Salvar o texto original do botão
        const originalText = $button.text();
        
        // Adicionar classe de loading e mudar texto
        $button.addClass('loading').text('Gerando PDF...');
        
        $.ajax({
            url: pdfGeneratorAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'generate_cpt_pdf',
                post_id: postId,
                post_type: postType,
                nonce: pdfGeneratorAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    try {
                        // Certifique-se que o jsPDF está disponível
                        if (typeof window.jspdf === 'undefined') {
                            throw new Error('jsPDF não está carregado');
                        }

                        // Criar nova instância do jsPDF
                        const doc = new window.jspdf.jsPDF();

                        // Configuração do cabeçalho
                        doc.setFontSize(18);
                        doc.text('Dados do ' + response.data.title, 105, 15, { align: 'center' });
                        
                        // Preparar dados para a tabela
                        const tableData = [['Campo', 'Valor']];
                        
                        // Adicionar campos
                        Object.entries(response.data.fields).forEach(([key, value]) => {
                            tableData.push([key, value || '']);
                        });

                        // Gerar tabela
                        doc.autoTable({
                            startY: 25,
                            head: [tableData[0]],
                            body: tableData.slice(1),
                            theme: 'grid',
                            headStyles: {
                                fillColor: [41, 128, 185],
                                textColor: 255,
                                fontSize: 12,
                                halign: 'center'
                            },
                            styles: {
                                fontSize: 10,
                                cellPadding: 5
                            },
                            columnStyles: {
                                0: { fontStyle: 'bold', width: 40 },
                                1: { width: 150 }
                            }
                        });

                        // Gerar o PDF e forçar o download
                        const fileName = 'dados-' + response.data.title.replace(/[^a-z0-9]/gi, '-').toLowerCase() + '.pdf';
                        doc.save(fileName);

                    } catch (error) {
                        console.error('Erro ao gerar PDF:', error);
                        alert('Erro ao gerar o PDF: ' + error.message);
                    }
                } else {
                    alert('Erro ao gerar PDF: ' + response.data);
                }
            },
            error: function() {
                alert('Erro ao processar a requisição');
            },
            complete: function() {
                // Restaurar o botão ao estado original
                $button.removeClass('loading').text(originalText);
            }
        });
    });
});

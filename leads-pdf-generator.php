<?php
/*
Plugin Name: PDF Generator for CPTs
Description: Generate PDF for any Custom Post Type with configurable fields
Version: 1.3
Author: Dante Testa
*/

if (!defined('ABSPATH')) exit;

class PDFGeneratorForCPTs {
    private $plugin_options = 'pdf_generator_cpts_options';

    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'init_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // AJAX hooks
        add_action('wp_ajax_save_cpt_settings', array($this, 'save_cpt_settings'));
        add_action('wp_ajax_generate_cpt_pdf', array($this, 'generate_cpt_pdf'));
        
        // Adicionar colunas dinamicamente para CPTs configurados
        add_action('admin_init', array($this, 'setup_cpt_columns'));
    }

    public function add_admin_menu() {
        add_menu_page(
            'PDF Generator Settings',
            'PDF Generator',
            'manage_options',
            'pdf-generator-settings',
            array($this, 'render_settings_page'),
            'dashicons-pdf',
            30
        );
    }

    public function init_settings() {
        register_setting($this->plugin_options, $this->plugin_options);
    }

    public function setup_cpt_columns() {
        $options = get_option($this->plugin_options, array());
        if (!empty($options['enabled_cpts'])) {
            foreach ($options['enabled_cpts'] as $post_type => $settings) {
                if (!empty($settings['enabled'])) {
                    add_filter("manage_{$post_type}_posts_columns", array($this, 'add_pdf_column'));
                    add_action("manage_{$post_type}_posts_custom_column", array($this, 'display_pdf_button'), 10, 2);
                }
            }
        }
    }

    public function add_pdf_column($columns) {
        $columns['pdf_generator'] = 'Gerar PDF';
        return $columns;
    }

    public function display_pdf_button($column, $post_id) {
        if ($column === 'pdf_generator') {
            $post_type = get_post_type($post_id);
            echo '<button class="button generate-pdf-btn" data-id="' . esc_attr($post_id) . '" data-type="' . esc_attr($post_type) . '">Gerar PDF</button>';
        }
    }

    public function enqueue_scripts($hook) {
        // Carregar scripts nas páginas de listagem de posts
        if ('edit.php' === $hook) {
            $current_screen = get_current_screen();
            $options = get_option($this->plugin_options, array());
            
            if (!empty($options['enabled_cpts'][$current_screen->post_type]['enabled'])) {
                // Carregar CSS para o loader
                wp_enqueue_style('pdf-generator-admin', plugins_url('css/admin.css', __FILE__));
                
                // Carregar scripts
                wp_enqueue_script('jquery');
                wp_enqueue_script('jspdf', 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js', array('jquery'), '2.5.1', true);
                wp_enqueue_script('jspdf-autotable', 'https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js', array('jspdf'), '3.5.28', true);
                wp_enqueue_script('pdf-generator', plugins_url('js/pdf-generator.js', __FILE__), array('jquery', 'jspdf', 'jspdf-autotable'), '1.0', true);
                wp_localize_script('pdf-generator', 'pdfGeneratorAjax', array(
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('generate_cpt_pdf_nonce')
                ));
            }
        }
    }

    public function enqueue_admin_scripts($hook) {
        if ('toplevel_page_pdf-generator-settings' !== $hook) {
            return;
        }

        // Estilos do WordPress necessários
        wp_enqueue_style('wp-admin');
        wp_enqueue_style('dashicons');

        // jQuery UI
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-core');
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_style('wp-jquery-ui-dialog');

        // Nossos scripts e estilos
        wp_enqueue_style(
            'pdf-generator-admin',
            plugins_url('css/admin.css', __FILE__),
            array(),
            filemtime(plugin_dir_path(__FILE__) . 'css/admin.css')
        );

        wp_enqueue_script(
            'pdf-generator-admin',
            plugins_url('js/admin.js', __FILE__),
            array('jquery', 'jquery-ui-sortable'),
            filemtime(plugin_dir_path(__FILE__) . 'js/admin.js'),
            true
        );

        wp_localize_script('pdf-generator-admin', 'pdfGeneratorAdmin', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pdf_generator_admin_nonce')
        ));
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $options = get_option($this->plugin_options, array());
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="pdf-generator-settings">
                <?php
                $post_types = get_post_types(array('public' => true), 'objects');
                foreach ($post_types as $post_type) {
                    $type = $post_type->name;
                    $enabled = isset($options['enabled_cpts'][$type]['enabled']) ? $options['enabled_cpts'][$type]['enabled'] : false;
                    $meta_fields = isset($options['enabled_cpts'][$type]['meta_fields']) ? $options['enabled_cpts'][$type]['meta_fields'] : array();
                    $taxonomies = isset($options['enabled_cpts'][$type]['taxonomies']) ? $options['enabled_cpts'][$type]['taxonomies'] : array();
                    $field_order = isset($options['enabled_cpts'][$type]['field_order']) ? $options['enabled_cpts'][$type]['field_order'] : array();
                    
                    // Obter todos os campos meta disponíveis
                    $available_meta_fields = $this->get_meta_keys($type);
                    $meta_field_items = array();
                    foreach ($available_meta_fields as $field) {
                        $is_selected = isset($meta_fields[$field]);
                        $order = isset($field_order[$field]) ? $field_order[$field] : 999;
                        $meta_field_items[] = array(
                            'field' => $field,
                            'selected' => $is_selected,
                            'order' => $order
                        );
                    }

                    // Obter todas as taxonomias disponíveis
                    $available_taxonomies = get_object_taxonomies($type, 'objects');
                    $taxonomy_items = array();
                    foreach ($available_taxonomies as $tax) {
                        if (!$tax->public) continue;
                        $is_selected = isset($taxonomies[$tax->name]);
                        $order = isset($field_order[$tax->name]) ? $field_order[$tax->name] : 999;
                        $taxonomy_items[] = array(
                            'field' => $tax->name,
                            'label' => $tax->label,
                            'selected' => $is_selected,
                            'order' => $order
                        );
                    }

                    // Ordenar os campos meta
                    usort($meta_field_items, function($a, $b) {
                        if ($a['selected'] && !$b['selected']) return -1;
                        if (!$a['selected'] && $b['selected']) return 1;
                        if ($a['selected'] && $b['selected']) {
                            return $a['order'] - $b['order'];
                        }
                        return strcmp($a['field'], $b['field']);
                    });

                    // Ordenar as taxonomias
                    usort($taxonomy_items, function($a, $b) {
                        if ($a['selected'] && !$b['selected']) return -1;
                        if (!$a['selected'] && $b['selected']) return 1;
                        if ($a['selected'] && $b['selected']) {
                            return $a['order'] - $b['order'];
                        }
                        return strcmp($a['label'], $b['label']);
                    });
                    ?>
                    <div class="cpt-item" data-type="<?php echo esc_attr($type); ?>">
                        <div class="cpt-header">
                            <label>
                                <input type="checkbox" class="cpt-enabled" <?php checked($enabled); ?>>
                                <?php echo esc_html($post_type->labels->name); ?>
                            </label>
                        </div>
                        
                        <div class="cpt-fields" style="display: <?php echo $enabled ? 'block' : 'none'; ?>">
                            <!-- Campos Meta -->
                            <div class="field-section meta-fields">
                                <h3>Campos Meta</h3>
                                <div class="sortable-fields">
                                    <?php foreach ($meta_field_items as $item): ?>
                                        <div class="field-item <?php echo $item['selected'] ? 'selected' : 'disabled'; ?>" 
                                             data-field="<?php echo esc_attr($item['field']); ?>"
                                             data-order="<?php echo esc_attr($item['order']); ?>">
                                            <span class="handle dashicons dashicons-menu"></span>
                                            <label>
                                                <input type="checkbox" value="<?php echo esc_attr($item['field']); ?>" 
                                                       <?php checked($item['selected']); ?>>
                                                <?php echo esc_html($item['field']); ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <!-- Taxonomias -->
                            <div class="field-section taxonomies">
                                <h3>Taxonomias</h3>
                                <div class="sortable-fields">
                                    <?php foreach ($taxonomy_items as $item): ?>
                                        <div class="field-item <?php echo $item['selected'] ? 'selected' : 'disabled'; ?>"
                                             data-field="<?php echo esc_attr($item['field']); ?>"
                                             data-order="<?php echo esc_attr($item['order']); ?>">
                                            <span class="handle dashicons dashicons-menu"></span>
                                            <label>
                                                <input type="checkbox" value="<?php echo esc_attr($item['field']); ?>" 
                                                       <?php checked($item['selected']); ?>>
                                                <?php echo esc_html($item['label']); ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php
                }
                ?>
                
                <div class="submit-wrapper">
                    <button type="button" id="save-settings" class="button button-primary">Salvar Configurações</button>
                </div>
            </div>
        </div>
        <?php
    }

    private function get_meta_keys($post_type) {
        global $wpdb;
        $query = "
            SELECT DISTINCT meta_key
            FROM $wpdb->postmeta pm
            JOIN $wpdb->posts p ON p.ID = pm.post_id
            WHERE p.post_type = %s
            AND meta_key NOT LIKE '\_%'
            ORDER BY meta_key";
        
        return $wpdb->get_col($wpdb->prepare($query, $post_type));
    }

    public function save_cpt_settings() {
        // Verificar nonce e permissões
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'pdf_generator_admin_nonce')) {
            wp_send_json_error('Nonce inválido');
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permissão negada');
            return;
        }

        // Obter e sanitizar dados
        $settings = isset($_POST['settings']) ? json_decode(stripslashes($_POST['settings']), true) : array();
        if (empty($settings)) {
            wp_send_json_error('Dados inválidos');
            return;
        }

        $sanitized_settings = array(
            'enabled_cpts' => array()
        );

        // Sanitizar configurações
        foreach ($settings as $post_type => $config) {
            if (!empty($config['enabled'])) {
                $sanitized_settings['enabled_cpts'][$post_type] = array(
                    'enabled' => true,
                    'meta_fields' => array(),
                    'taxonomies' => array(),
                    'field_order' => array()
                );

                // Sanitizar campos meta
                if (!empty($config['meta_fields'])) {
                    foreach ($config['meta_fields'] as $field => $enabled) {
                        $sanitized_settings['enabled_cpts'][$post_type]['meta_fields'][sanitize_text_field($field)] = true;
                    }
                }

                // Sanitizar taxonomias
                if (!empty($config['taxonomies'])) {
                    foreach ($config['taxonomies'] as $tax => $enabled) {
                        $sanitized_settings['enabled_cpts'][$post_type]['taxonomies'][sanitize_text_field($tax)] = true;
                    }
                }

                // Sanitizar ordem dos campos
                if (!empty($config['field_order'])) {
                    foreach ($config['field_order'] as $field => $order) {
                        $sanitized_settings['enabled_cpts'][$post_type]['field_order'][sanitize_text_field($field)] = absint($order);
                    }
                }
            }
        }

        // Salvar configurações
        $updated = update_option($this->plugin_options, $sanitized_settings);

        if ($updated) {
            wp_send_json_success('Configurações salvas com sucesso');
        } else {
            wp_send_json_error('Erro ao salvar configurações no banco de dados');
        }
    }

    public function generate_cpt_pdf() {
        check_ajax_referer('generate_cpt_pdf_nonce', 'nonce');

        $post_id = intval($_POST['post_id']);
        $post_type = $_POST['post_type'];
        $post = get_post($post_id);

        if (!$post || $post->post_type !== $post_type) {
            wp_send_json_error('Post não encontrado');
        }

        $options = get_option($this->plugin_options);
        if (empty($options['enabled_cpts'][$post_type]['enabled'])) {
            wp_send_json_error('PDF não habilitado para este tipo de post');
        }

        $data = array(
            'title' => $post->post_title,
            'fields' => array()
        );

        // Adicionar meta fields selecionados
        if (!empty($options['enabled_cpts'][$post_type]['meta_fields'])) {
            foreach ($options['enabled_cpts'][$post_type]['meta_fields'] as $meta_key => $enabled) {
                if ($enabled) {
                    $data['fields'][$meta_key] = get_post_meta($post_id, $meta_key, true);
                }
            }
        }

        // Adicionar taxonomias selecionadas
        if (!empty($options['enabled_cpts'][$post_type]['taxonomies'])) {
            foreach ($options['enabled_cpts'][$post_type]['taxonomies'] as $tax_name => $enabled) {
                if ($enabled) {
                    $terms = wp_get_post_terms($post_id, $tax_name, array('fields' => 'names'));
                    $data['fields'][$tax_name] = is_wp_error($terms) ? '' : implode(', ', $terms);
                }
            }
        }

        wp_send_json_success($data);
    }
}

new PDFGeneratorForCPTs();

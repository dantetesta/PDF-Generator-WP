<?php
/*
Plugin Name: PDF Generator for CPTs
Description: Generate PDF for any Custom Post Type with configurable fields
Version: 1.0
Author: Your Name
*/

if (!defined('ABSPATH')) exit;

class PDFGeneratorForCPTs {
    private $plugin_options = 'pdf_generator_cpts_options';

    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'init_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_generate_cpt_pdf', array($this, 'generate_cpt_pdf'));
        add_action('wp_ajax_save_cpt_settings', array($this, 'save_cpt_settings'));
        
        // Adicionar colunas dinamicamente para CPTs configurados
        add_action('admin_init', array($this, 'setup_cpt_columns'));
    }

    public function add_admin_menu() {
        add_menu_page(
            'PDF Generator Settings',
            'PDF Generator',
            'manage_options',
            'pdf-generator-settings',
            array($this, 'settings_page'),
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
        // Carregar scripts na página de configurações
        if ('toplevel_page_pdf-generator-settings' === $hook) {
            wp_enqueue_style('pdf-generator-admin', plugins_url('css/admin.css', __FILE__));
            wp_enqueue_script('pdf-generator-admin', plugins_url('js/admin.js', __FILE__), array('jquery'), '1.0', true);
            wp_localize_script('pdf-generator-admin', 'pdfGeneratorAdmin', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('pdf_generator_admin_nonce')
            ));
        }

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

    public function settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $post_types = get_post_types(array('public' => true), 'objects');
        $options = get_option($this->plugin_options, array());
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <div id="pdf-generator-settings">
                <div class="cpt-list">
                    <?php foreach ($post_types as $post_type): ?>
                        <div class="cpt-item" data-type="<?php echo esc_attr($post_type->name); ?>">
                            <h2><?php echo esc_html($post_type->labels->name); ?></h2>
                            <label>
                                <input type="checkbox" class="cpt-enabled" 
                                    <?php checked(!empty($options['enabled_cpts'][$post_type->name]['enabled'])); ?>>
                                Habilitar geração de PDF
                            </label>
                            <div class="cpt-fields" style="display: none;">
                                <h3>Campos Disponíveis</h3>
                                <div class="field-group meta-fields">
                                    <h4>Meta Fields</h4>
                                    <?php
                                    $meta_keys = $this->get_post_type_meta_keys($post_type->name);
                                    foreach ($meta_keys as $meta_key) {
                                        $checked = !empty($options['enabled_cpts'][$post_type->name]['meta_fields'][$meta_key]);
                                        ?>
                                        <label>
                                            <input type="checkbox" name="meta_fields[]" value="<?php echo esc_attr($meta_key); ?>"
                                                <?php checked($checked); ?>>
                                            <?php echo esc_html($meta_key); ?>
                                        </label>
                                    <?php } ?>
                                </div>
                                <div class="field-group taxonomies">
                                    <h4>Taxonomias</h4>
                                    <?php
                                    $taxonomies = get_object_taxonomies($post_type->name, 'objects');
                                    foreach ($taxonomies as $tax) {
                                        $checked = !empty($options['enabled_cpts'][$post_type->name]['taxonomies'][$tax->name]);
                                        ?>
                                        <label>
                                            <input type="checkbox" name="taxonomies[]" value="<?php echo esc_attr($tax->name); ?>"
                                                <?php checked($checked); ?>>
                                            <?php echo esc_html($tax->label); ?>
                                        </label>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="submit-wrapper">
                    <button type="button" class="button button-primary" id="save-settings">Salvar Configurações</button>
                </div>
            </div>
        </div>
        <?php
    }

    private function get_post_type_meta_keys($post_type) {
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
        check_ajax_referer('pdf_generator_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permissão negada');
        }

        $settings = json_decode(stripslashes($_POST['settings']), true);
        update_option($this->plugin_options, $settings);
        
        wp_send_json_success('Configurações salvas com sucesso');
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

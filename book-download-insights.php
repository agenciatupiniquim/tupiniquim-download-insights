<?php

/**
 * Plugin Name: Tupiniquim Book Downloads Insights
 * Plugin URI:  https://tupiniquim.example/book-download-insights
 * Description: Dashboard de analytics para downloads de livros gratuitos vendidos via WooCommerce. Registra quem baixou, qual livro, autor mais baixado e mais.
 * Version:     1.0.0
 * Author:      Agência Tupiniquim
 * Text Domain: tupiniquim-book-downloads-insights
 * Requires Plugins: woocommerce
 */

if (!defined('ABSPATH')) {
	exit; // Bloqueia acesso direto.
}

define('TUPBDI_VERSION', '1.0.0');
define('TUPBDI_PLUGIN_FILE', __FILE__);
define('TUPBDI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TUPBDI_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Verifica se o WooCommerce está ativo antes de carregar qualquer coisa.
 *
 * @return void
 */
function tupbdi_woocommerce_missing_notice(): void {
?>
	<div class="notice notice-error">
		<p><?php esc_html_e('Tupiniquim Book Downloads Insights precisa do WooCommerce ativo para funcionar.', 'tupiniquim-book-downloads-insights'); ?></p>
	</div>
<?php
}

/**
 * Inicializa o plugin após verificar dependências.
 *
 * @return void
 */
function tupbdi_init_plugin(): void {
	if (!class_exists('WooCommerce')) {
		add_action('admin_notices', 'tupbdi_woocommerce_missing_notice');
		return;
	}

	require_once TUPBDI_PLUGIN_DIR . 'includes/class-tupbdi-db.php';
	require_once TUPBDI_PLUGIN_DIR . 'includes/class-tupbdi-manual-download.php';
	require_once TUPBDI_PLUGIN_DIR . 'includes/class-tupbdi-author.php';
	require_once TUPBDI_PLUGIN_DIR . 'includes/class-tupbdi-admin.php';
	require_once TUPBDI_PLUGIN_DIR . 'includes/class-tupbdi-export.php';

	Tupiniquim\BookDownloadsInsights\ManualDownload::init();
	Tupiniquim\BookDownloadsInsights\Admin::init();
	Tupiniquim\BookDownloadsInsights\Export::init();
}
add_action('plugins_loaded', 'tupbdi_init_plugin');

/**
 * Cria a tabela customizada na ativação do plugin.
 *
 * @return void
 */
function tupbdi_activate_plugin(): void {
	require_once TUPBDI_PLUGIN_DIR . 'includes/class-tupbdi-db.php';
	Tupiniquim\BookDownloadsInsights\DB::create_table();
}
register_activation_hook(__FILE__, 'tupbdi_activate_plugin');

<?php
/**
 * Plugin Name: Book Download Insights
 * Plugin URI:  https://tupiniquim.example/book-download-insights
 * Description: Dashboard de analytics para downloads de livros gratuitos vendidos via WooCommerce. Registra quem baixou, qual livro, autor mais baixado e mais.
 * Version:     1.0.0
 * Author:      Agência Tupiniquim
 * Text Domain: book-download-insights
 * Requires Plugins: woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Bloqueia acesso direto.
}

define( 'BDI_VERSION', '1.0.0' );
define( 'BDI_PLUGIN_FILE', __FILE__ );
define( 'BDI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BDI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Verifica se o WooCommerce está ativo antes de carregar qualquer coisa.
 */
function bdi_woocommerce_missing_notice() {
	?>
	<div class="notice notice-error">
		<p><?php esc_html_e( 'Book Download Insights precisa do WooCommerce ativo para funcionar.', 'book-download-insights' ); ?></p>
	</div>
	<?php
}

function bdi_init_plugin() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'bdi_woocommerce_missing_notice' );
		return;
	}

	require_once BDI_PLUGIN_DIR . 'includes/class-bdi-db.php';
	require_once BDI_PLUGIN_DIR . 'includes/class-bdi-tracker.php';
	require_once BDI_PLUGIN_DIR . 'includes/class-bdi-author.php';
	require_once BDI_PLUGIN_DIR . 'includes/class-bdi-admin.php';
	require_once BDI_PLUGIN_DIR . 'includes/class-bdi-export.php';

	BDI_Tracker::init();
	BDI_Admin::init();
	BDI_Export::init();
}
add_action( 'plugins_loaded', 'bdi_init_plugin' );

/**
 * Cria a tabela customizada na ativação do plugin.
 */
function bdi_activate_plugin() {
	require_once BDI_PLUGIN_DIR . 'includes/class-bdi-db.php';
	BDI_DB::create_table();
}
register_activation_hook( __FILE__, 'bdi_activate_plugin' );

<?php
if (! defined('ABSPATH')) {
	exit;
}

/**
 * Substitui o rastreamento via hook 'woocommerce_download_product' (que só
 * dispara dentro do fluxo de pedido/permissão do WooCommerce) por um
 * disparo manual: o front-end chama esse endpoint via AJAX quando o
 * visitante clica em um botão de download na página do produto, sem
 * depender de checkout, pedido ou permissão de download do WooCommerce.
 *
 * O produto ainda precisa ser um "downloadable product" do WooCommerce
 * (é de onde tiramos a URL do arquivo), mas o log e a liberação do
 * download acontecem 100% fora do fluxo de compra.
 */
class BDI_Manual_Download {

	const NONCE_ACTION = 'bdi_manual_download';

	public static function init() {
		add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));
		add_action('wp_ajax_bdi_log_download', array(__CLASS__, 'handle_ajax'));
		add_action('wp_ajax_nopriv_bdi_log_download', array(__CLASS__, 'handle_ajax'));
		add_shortcode('bdi_download_button', array(__CLASS__, 'render_button_shortcode'));
	}

	public static function enqueue_assets() {
		if (! function_exists('is_product') || ! is_product()) {
			return;
		}

		wp_enqueue_script('bdi-frontend-download', BDI_PLUGIN_URL . 'assets/js/frontend-download.js', array(), BDI_VERSION, true);

		wp_localize_script(
			'bdi-frontend-download',
			'BDI_Download',
			array(
				'ajax_url' => admin_url('admin-ajax.php'),
				'nonce'    => wp_create_nonce(self::NONCE_ACTION),
			)
		);
	}

	/**
	 * Botão pronto via shortcode [bdi_download_button text="Baixar livro"],
	 * caso não queira montar o botão personalizado na mão. O atributo
	 * data-product-id / data-download-key também funciona em QUALQUER botão
	 * seu que tenha a classe "bdi-download-button" — não precisa usar o
	 * shortcode se já tem seu próprio HTML.
	 */
	public static function render_button_shortcode($atts) {
		global $product;

		if (! $product instanceof WC_Product) {
			$product = wc_get_product(get_the_ID());
		}

		if (! $product) {
			return '';
		}

		$atts = shortcode_atts(
			array(
				'text' => __('Baixar livro', 'book-download-insights'),
			),
			$atts
		);

		return sprintf(
			'<button type="button" class="bdi-download-button button" data-product-id="%d">%s</button>',
			esc_attr($product->get_id()),
			esc_html($atts['text'])
		);
	}

	public static function handle_ajax() {
		check_ajax_referer(self::NONCE_ACTION, 'nonce');

		$product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;

		if (! $product_id) {
			wp_send_json_error(array('message' => __('Livro inválido.', 'book-download-insights')), 400);
		}

		$product = wc_get_product($product_id);

		if (! $product || ! $product->is_downloadable()) {
			wp_send_json_error(array('message' => __('Este produto não tem arquivo para download.', 'book-download-insights')), 404);
		}

		$downloads = $product->get_downloads();

		if (! $downloads) {
			wp_send_json_error(array('message' => __('Nenhum arquivo configurado para este livro.', 'book-download-insights')), 404);
		}

		// Se o produto tem mais de um arquivo, o botão pode informar qual via data-download-key.
		$download_key = isset($_POST['download_key']) ? sanitize_text_field(wp_unslash($_POST['download_key'])) : '';
		$file         = ($download_key && isset($downloads[$download_key])) ? $downloads[$download_key] : reset($downloads);

		$user_id    = get_current_user_id();
		$user_name  = '';
		$user_email = '';

		if ($user_id) {
			$user       = get_userdata($user_id);
			$user_name  = $user ? $user->display_name : '';
			$user_email = $user ? $user->user_email : '';
		} else {
			// Visitante não logado: usa nome/e-mail se o seu botão/formulário enviar esses campos.
			$user_name  = isset($_POST['guest_name']) ? sanitize_text_field(wp_unslash($_POST['guest_name'])) : __('Visitante', 'book-download-insights');
			$user_email = isset($_POST['guest_email']) ? sanitize_email(wp_unslash($_POST['guest_email'])) : '';
		}

		BDI_DB::insert_log(
			array(
				'user_id'       => $user_id,
				'user_email'    => $user_email,
				'user_name'     => $user_name,
				'product_id'    => $product_id,
				'order_id'      => 0,
				'download_id'   => $file->get_id(),
				'ip_address'    => self::get_client_ip(),
				'downloaded_at' => current_time('mysql'),
			)
		);

		wp_send_json_success(
			array(
				'download_url' => $file->get_file(),
				'file_name'    => $file->get_name(),
			)
		);
	}

	protected static function get_client_ip() {
		$keys = array('HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');

		foreach ($keys as $key) {
			if (! empty($_SERVER[$key])) {
				$ip = sanitize_text_field(wp_unslash($_SERVER[$key]));
				$ip = trim(explode(',', $ip)[0]);
				return $ip;
			}
		}

		return '';
	}
}

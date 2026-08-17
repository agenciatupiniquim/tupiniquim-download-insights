<?php

namespace Tupiniquim\BookDownloadsInsights;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Gerencia o rastreamento manual de downloads via AJAX.
 *
 * Substitui o rastreamento via hook 'woocommerce_download_product' (que só
 * dispara dentro do fluxo de pedido/permissão do WooCommerce) por um
 * disparo manual: o front-end chama esse endpoint via AJAX quando o
 * visitante clica em um botão de download na página do produto, sem
 * depender de checkout, pedido ou permissão de download do WooCommerce.
 *
 * O produto ainda precisa ser um "downloadable product" do WooCommerce
 * (é de onde tiramos a URL do arquivo), mas o log e a liberação do
 * download acontecem 100% fora do fluxo de compra.
 *
 * @package Tupiniquim\BookDownloadsInsights
 */
class ManualDownload {

	/**
	 * Action nonce para validação AJAX.
	 *
	 * @const string
	 */
	const NONCE_ACTION = 'tupbdi_manual_download';

	/**
	 * Inicializa os hooks de frontend e AJAX.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action('wp_enqueue_scripts', [self::class, 'enqueue_assets']);
		add_action('wp_ajax_tupbdi_log_download', [self::class, 'handle_ajax']);
		add_action('wp_ajax_nopriv_tupbdi_log_download', [self::class, 'handle_ajax']);
		add_shortcode('tupbdi_download_button', [self::class, 'render_button_shortcode']);
	}

	/**
	 * Enfileira assets do frontend (JS) apenas em páginas de produto.
	 *
	 * @return void
	 */
	public static function enqueue_assets(): void {
		if (!function_exists('is_product') || !is_product()) {
			return;
		}

		wp_enqueue_style('tupbdi-frontend-download-css', TUPBDI_PLUGIN_URL . 'assets/css/frontend.css', [], TUPBDI_VERSION);
		wp_enqueue_script('tupbdi-frontend-download', TUPBDI_PLUGIN_URL . 'assets/js/frontend-download.js', [], TUPBDI_VERSION, true);

		wp_localize_script(
			'tupbdi-frontend-download',
			'TUPBDI_Download',
			[
				'ajax_url' => admin_url('admin-ajax.php'),
				'nonce'    => wp_create_nonce(self::NONCE_ACTION),
			]
		);
	}

	/**
	 * Renderiza botão de download via shortcode.
	 *
	 * Uso: [tupbdi_download_button text="Baixar livro"]
	 * Caso não queira usar o shortcode, o atributo data-product-id / data-download-key
	 * também funciona em QUALQUER botão que tenha a classe "tupbdi-download-button".
	 *
	 * @param array<string, string> $atts Atributos do shortcode.
	 *
	 * @return string HTML do botão.
	 */
	public static function render_button_shortcode(array $atts): string {
		global $product;

		if (!$product instanceof \WC_Product) {
			$product = wc_get_product(get_the_ID());
		}

		if (!$product) {
			return '';
		}

		$downloads = $product->get_downloads();
		if (!$downloads) {
			return '';
		}

		$atts = shortcode_atts(
			[
				'text' => __('Baixar livro', 'tupiniquim-book-downloads-insights'),
			],
			$atts
		);

		$buttons = [];
		foreach ($downloads as $download_key => $download_file) {
			$label = self::get_download_button_label($download_file, $atts['text']);
			$buttons[] = sprintf(
				'<button type="button" class="tupbdi-download-button button" data-product-id="%d" data-download-key="%s">%s</button>',
				esc_attr($product->get_id()),
				esc_attr($download_key),
				esc_html($label)
			);
		}

		if (!$buttons) {
			return '';
		}

		return '<div class="tupbdi-download-buttons">' . implode('', $buttons) . '</div>';
	}

	/**
	 * Retorna o texto do botão para cada arquivo de download.
	 *
	 * @param \WC_Product_Download $download_file Arquivo de download do WooCommerce.
	 * @param string               $fallback_text Texto padrão do shortcode.
	 *
	 * @return string
	 */
	protected static function get_download_button_label($download_file, string $fallback_text): string {
		$name = strtolower((string) $download_file->get_name());
		$name = preg_replace('/[^a-z0-9]+/i', ' ', $name);
		$name = trim($name);

		switch (true) {
			case $name === '':
				return $fallback_text;
			case strpos($name, 'pdf') !== false:
				return __('Download do PDF', 'tupiniquim-book-downloads-insights');
			case strpos($name, 'epub') !== false || strpos($name, 'e pub') !== false:
				return __('Download do EPUB', 'tupiniquim-book-downloads-insights');
			case strpos($name, 'comprar') !== false:
				return __('Comprar', 'tupiniquim-book-downloads-insights');
			case strpos($name, 'urulimpressao') !== false:
				return __('Impressão sob demanda', 'tupiniquim-book-downloads-insights');
			default:
				return sprintf(__('Download do %s', 'tupiniquim-book-downloads-insights'), ucfirst($name));
		}
	}

	/**
	 * Processa a requisição AJAX de download.
	 *
	 * @return void Envia resposta JSON e encerra.
	 */
	public static function handle_ajax(): void {
		check_ajax_referer(self::NONCE_ACTION, 'nonce');

		if (!is_user_logged_in()) {
			wp_send_json_error(['message' => __('Você precisa estar autenticado para acessar o livro.', 'tupiniquim-book-downloads-insights')], 400);
		}

		$product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;

		if (!$product_id) {
			wp_send_json_error(['message' => __('Livro inválido.', 'tupiniquim-book-downloads-insights')], 400);
		}

		$product = wc_get_product($product_id);

		if (!$product || !$product->is_downloadable()) {
			wp_send_json_error(['message' => __('Este produto não tem arquivo para download.', 'tupiniquim-book-downloads-insights')], 404);
		}

		$downloads = $product->get_downloads();

		if (!$downloads) {
			wp_send_json_error(['message' => __('Nenhum arquivo configurado para este livro.', 'tupiniquim-book-downloads-insights')], 404);
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
			$user_name  = isset($_POST['guest_name']) ? sanitize_text_field(wp_unslash($_POST['guest_name'])) : __('Visitante', 'tupiniquim-book-downloads-insights');
			$user_email = isset($_POST['guest_email']) ? sanitize_email(wp_unslash($_POST['guest_email'])) : '';
		}

		DB::insert_log(
			[
				'user_id'       => $user_id,
				'user_email'    => $user_email,
				'user_name'     => $user_name,
				'product_id'    => $product_id,
				'order_id'      => 0,
				'download_id'   => $file->get_id(),
				'file_name'     => $file->get_name() ?: (string) $download_key,
				'ip_address'    => self::get_client_ip(),
				'downloaded_at' => current_time('mysql'),
			]
		);

		wp_send_json_success(
			[
				'download_url' => $file->get_file(),
				'file_name'    => $file->get_name(),
			]
		);
	}

	/**
	 * Obtém o IP do cliente respeitando proxies e CDNs.
	 *
	 * @return string IP do cliente.
	 */
	protected static function get_client_ip(): string {
		$keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];

		foreach ($keys as $key) {
			if (!empty($_SERVER[$key])) {
				$ip = sanitize_text_field(wp_unslash($_SERVER[$key]));
				$ip = trim(explode(',', $ip)[0]);
				return $ip;
			}
		}

		return '';
	}
}

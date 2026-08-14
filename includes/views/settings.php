<?php
if (!defined('ABSPATH')) {
	exit;
}
/**
 * @var array $settings
 * @var array $taxonomies
 */
?>
<div class="wrap tupbdi-wrap">
	<h1><?php esc_html_e('Configurações — Downloads de Livros', 'tupiniquim-book-downloads-insights'); ?></h1>

	<form method="post">
		<?php wp_nonce_field('tupbdi_save_settings', 'tupbdi_settings_nonce'); ?>

		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e('Como o Autor está cadastrado?', 'tupiniquim-book-downloads-insights'); ?></th>
				<td>
					<label>
						<input type="radio" name="tupbdi_author_type" value="taxonomy" <?php checked('taxonomy', $settings['type']); ?> />
						<?php esc_html_e('Taxonomia (ex: categoria/tag customizada de Autor)', 'tupiniquim-book-downloads-insights'); ?>
					</label>
					<br />
					<label>
						<input type="radio" name="tupbdi_author_type" value="meta" <?php checked('meta', $settings['type']); ?> />
						<?php esc_html_e('Campo customizado (ACF ou meta field)', 'tupiniquim-book-downloads-insights'); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e('Nome da taxonomia ou do campo', 'tupiniquim-book-downloads-insights'); ?></th>
				<td>
					<input type="text" name="tupbdi_author_key" value="<?php echo esc_attr($settings['key']); ?>" class="regular-text" />
					<p class="description">
						<?php esc_html_e('Se for taxonomia, é o "slug" dela (ex: autor). Se for campo customizado, é o nome do meta field (ex: autor_livro).', 'tupiniquim-book-downloads-insights'); ?>
					</p>
					<?php if ($taxonomies) : ?>
						<p class="description">
							<?php esc_html_e('Taxonomias encontradas nos produtos:', 'tupiniquim-book-downloads-insights'); ?>
							<?php echo esc_html(implode(', ', array_keys($taxonomies))); ?>
						</p>
					<?php endif; ?>
				</td>
			</tr>
		</table>

		<?php submit_button(__('Salvar configurações', 'tupiniquim-book-downloads-insights')); ?>
	</form>
</div>

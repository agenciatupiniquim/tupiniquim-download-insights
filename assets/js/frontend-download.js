(function () {
	'use strict';

	/**
	 * Chama o back-end para registrar o download e, se der certo, redireciona
	 * pro arquivo. Exposta como window.bdiDownload pra você poder chamar
	 * direto de um botão totalmente seu, sem precisar do shortcode:
	 *
	 *   <button onclick="bdiDownload(123)">Baixar</button>
	 *
	 * Se o produto tiver mais de um arquivo, passe a chave do arquivo:
	 *
	 *   <button onclick="bdiDownload(123, 'abc123hash')">Baixar</button>
	 */
	function bdiDownload(productId, downloadKey) {
		if (typeof BDI_Download === 'undefined') {
			console.error('Book Download Insights: script não carregado nesta página.');
			return;
		}

		var formData = new FormData();
		formData.append('action', 'bdi_log_download');
		formData.append('nonce', BDI_Download.nonce);
		formData.append('product_id', productId);

		if (downloadKey) {
			formData.append('download_key', downloadKey);
		}

		fetch(BDI_Download.ajax_url, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData,
		})
			.then(function (response) {
				return response.json();
			})
			.then(function (result) {
				if (result.success && result.data && result.data.download_url) {
					window.location.href = result.data.download_url;
				} else {
					var message = result.data && result.data.message ? result.data.message : 'Erro ao registrar o download.';
					console.error('Book Download Insights:', message);
					window.alert(message);
				}
			})
			.catch(function () {
				window.alert('Não foi possível iniciar o download. Tente novamente.');
			});
	}

	window.bdiDownload = bdiDownload;

	// Qualquer botão com a classe "bdi-download-button" funciona automaticamente,
	// seja o do shortcode [bdi_download_button] ou um botão seu com essa classe.
	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('.bdi-download-button').forEach(function (button) {
			button.addEventListener('click', function () {
				var productId = button.getAttribute('data-product-id');
				var downloadKey = button.getAttribute('data-download-key') || '';
				bdiDownload(productId, downloadKey);
			});
		});
	});
})();

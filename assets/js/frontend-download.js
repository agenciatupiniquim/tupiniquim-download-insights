(function () {
	'use strict';

	/**
	 * Chama o back-end para registrar o download e, se der certo, redireciona
	 * pro arquivo. Exposta como window.tupbdiDownload pra você poder chamar
	 * direto de um botão totalmente seu, sem precisar do shortcode:
	 *
	 *   <button onclick="tupbdiDownload(123)">Baixar</button>
	 *
	 * Se o produto tiver mais de um arquivo, passe a chave do arquivo:
	 *
	 *   <button onclick="tupbdiDownload(123, 'abc123hash')">Baixar</button>
	 */
	function tupbdiDownload(productId, downloadKey) {
		if (typeof TUPBDI_Download === 'undefined') {
			console.error('Tupiniquim Book Downloads Insights: script não carregado nesta página.');
			return;
		}

		var formData = new FormData();
		formData.append('action', 'tupbdi_log_download');
		formData.append('nonce', TUPBDI_Download.nonce);
		formData.append('product_id', productId);

		if (downloadKey) {
			formData.append('download_key', downloadKey);
		}

		fetch(TUPBDI_Download.ajax_url, {
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
					console.error('Tupiniquim Book Downloads Insights:', message);
					window.alert(message);
				}
			})
			.catch(function () {
				window.alert('Não foi possível iniciar o download. Tente novamente.');
			});
	}

	window.tupbdiDownload = tupbdiDownload;

	// Qualquer botão com a classe "tupbdi-download-button" funciona automaticamente,
	// seja o do shortcode [tupbdi_download_button] ou um botão seu com essa classe.
	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('.tupbdi-download-button').forEach(function (button) {
			button.addEventListener('click', function () {
				var productId = button.getAttribute('data-product-id');
				var downloadKey = button.getAttribute('data-download-key') || '';
				tupbdiDownload(productId, downloadKey);
			});
		});
	});
})();

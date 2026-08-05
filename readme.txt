=== Book Download Insights ===
Contributors: agenciatupiniquim
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 1.0.0
License: GPLv2 or later

Dashboard de analytics para downloads de livros vendidos como produtos gratuitos/digitais no WooCommerce.

== Description ==

Registra o download quando o visitante clica em um botão na página do
produto — via AJAX, sem depender de checkout, pedido ou permissão de
download do WooCommerce — e mostra em um dashboard: total de downloads,
usuários únicos, autor mais baixado, top 10 livros, top autores, gráfico de
downloads por dia e uma tabela com o histórico completo (usuário, e-mail,
livro, autor). Permite filtrar por período/livro e exportar tudo em CSV.

== Installation ==

1. Envie a pasta `book-download-insights` para `/wp-content/plugins/`.
2. Ative o plugin no painel do WordPress.
3. Vá em "Downloads de Livros > Configurações" e informe se o Autor do livro
   é uma taxonomia ou um campo customizado, e qual o nome/slug dele.
4. O produto precisa continuar cadastrado como "downloadable" no WooCommerce
   (é de onde o plugin pega a URL do arquivo), mas o download em si não
   depende mais de pedido/checkout.
5. No botão de download da página do produto, use a classe
   `bdi-download-button` com `data-product-id="ID_DO_PRODUTO"`, ou chame
   `bdiDownload(ID_DO_PRODUTO)` diretamente no onclick do seu botão. Também
   existe o shortcode `[bdi_download_button]` pra um botão pronto.
6. O log começa a ser preenchido a partir do primeiro clique feito depois
   da ativação (downloads anteriores não são retroativos).

== Changelog ==

= 1.0.0 =
* Versão inicial.

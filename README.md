# TMS — Shiptimize Address Fix

Complemento para WooCommerce Checkout Blocks e Shiptimize 4.1.4.

- Indicativo automático pelo país da morada, com opção de escolher outro país.
- Validação de números fixos e móveis com libphonenumber, incluída no plugin.
- Guarda os números das novas encomendas em formato internacional E.164.
- Compacta códigos postais britânicos apenas quando lidos pelo adaptador Shiptimize.
- Procura versões estáveis neste repositório para atualizar pelo painel WordPress.

Requer PHP 8.1+, WordPress 6.5+ e WooCommerce 8.9+.
Ver `tms-shiptimize-fix/LEIA-ME.txt` para instalação, comportamento e limites.

## Instalação e atualizações

Descarregar **tms-shiptimize-fix.zip** na secção Releases e carregar em
WordPress → Plugins → Adicionar plugin. Usar o ficheiro anexado à release,
e não os arquivos automáticos “Source code” do GitHub.

Para instalações anteriores à versão 1.2.0, esta primeira atualização é manual.
Depois, o WordPress verifica as releases estáveis, com cache até seis horas.
As futuras versões surgem no painel de atualizações. A instalação continua a ser
controlada pelo administrador; o complemento não ativa atualizações automáticas.

A consulta envia pedidos normais de rede ao GitHub, que recebe o IP do servidor
e os cabeçalhos HTTP habituais. Não envia telefones, moradas nem encomendas.
O repositório e os downloads de releases têm de permanecer públicos.

## Verificações e publicação

Os testes PHP usam simulações do WooCommerce; não substituem testes numa loja.
Cada alteração recebe verificação de sintaxe e testes em PHP 8.1 e 8.3 no Actions.

Para publicar uma versão:

1. Alterar a versão no cabeçalho do plugin e atualizar as instruções.
2. Executar os testes e verificar a compra numa loja de testes.
3. Criar e enviar uma etiqueta `vX.Y.Z` que corresponda ao cabeçalho.
4. O Actions testa, cria o ZIP instalável e publica uma release estável.

Publicar a etiqueta disponibiliza a versão às lojas que instalaram o atualizador.
As alterações apenas em `main` não são distribuídas pelo atualizador.

## Limites

Esta versão não valida a existência da morada, cidade ou código postal numa base
postal externa. A validação de telefones verifica planos de numeração e não a
titularidade ou a possibilidade de receber chamadas/SMS. O seletor aparece como
campo separado na secção das moradas; o WooCommerce controla a sua posição.

O plugin ainda não foi validado numa instalação WordPress real. Testar primeiro
com o tema e os meios de pagamento usados pela loja.

Bibliotecas, versões e licenças: `tms-shiptimize-fix/THIRD-PARTY.txt`.
As bibliotecas são reunidas durante os testes e a publicação por `tools/vendor.py`,
a partir de commits fixos, com verificação SHA-256. O ZIP final inclui-as e funciona
sem Composer, sem transferências de bibliotecas na loja e sem outros plugins.

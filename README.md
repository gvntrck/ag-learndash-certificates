# LearnDash Certificates

Plugin WordPress para gerar certificados em PDF fora do fluxo nativo do LearnDash, liberando a emissão com base em um percentual configurável de aulas concluídas.

## Instalação

1. Copie a pasta `ag-learndash-certificates` para `wp-content/plugins/`.
2. Ative o plugin no painel do WordPress.
3. Acesse `Certificados LD` no menu administrativo.
4. Configure:
   - percentual mínimo para liberação;
   - imagem do certificado em JPG ou PNG;
   - fonte, tamanho, cor e posição do nome do aluno.

## Shortcode

Use o shortcode abaixo em qualquer página:

```text
[agld_certificados]
```

## Como funciona

- O plugin lista os cursos do usuário no LearnDash.
- Calcula quantas aulas do curso estão marcadas como concluídas.
- Quando o percentual configurado é atingido, exibe um botão para abrir o PDF do certificado em nova aba.

## Requisitos

- WordPress
- LearnDash ativo
- Extensão GD do PHP habilitada

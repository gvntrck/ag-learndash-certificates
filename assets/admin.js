(function ($) {
	'use strict';

	function setImageState(url, id) {
		var $preview = $('#agldc-image-preview');
		var $placeholder = $('#agldc-image-placeholder');
		var $field = $('#agldc-certificate-image-id');

		$field.val(id || '');

		if (url) {
			$preview.attr('src', url).show();
			$placeholder.hide();
			return;
		}

		$preview.attr('src', '').hide();
		$placeholder.show();
	}

	$(function () {
		var frame;

		$('.agldc-color-field').wpColorPicker();

		$('#agldc-upload-image').on('click', function (event) {
			event.preventDefault();

			if (frame) {
				frame.open();
				return;
			}

			frame = wp.media({
				title: 'Selecionar imagem do certificado',
				button: {
					text: 'Usar imagem'
				},
				library: {
					type: ['image/jpeg', 'image/png']
				},
				multiple: false
			});

			frame.on('select', function () {
				var attachment = frame.state().get('selection').first().toJSON();
				setImageState(attachment.url, attachment.id);
			});

			frame.open();
		});

		$('#agldc-remove-image').on('click', function (event) {
			event.preventDefault();
			setImageState('', '');
		});
	});
}(jQuery));

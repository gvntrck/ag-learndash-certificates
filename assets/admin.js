(function ($) {
	'use strict';

	function setCourseImageState(courseId, url, id) {
		var $card = $('[data-course-id="' + courseId + '"]').closest('.agldc-course-card');
		var $preview = $card.find('.agldc-course-preview img');
		var $placeholder = $card.find('.agldc-course-preview .agldc-image-placeholder');
		var $field = $('#agldc_course_' + courseId + '_image_id');
		var $badge = $card.find('.agldc-badge-global, .agldc-badge-custom');

		$field.val(id || '');

		if (url) {
			if ($preview.length === 0) {
				$preview = $('<img alt="">');
				$placeholder.replaceWith($preview);
			} else {
				$placeholder.hide();
			}
			$preview.attr('src', url).show();
			$badge.removeClass('agldc-badge-global').addClass('agldc-badge-custom').text('Personalizado');
		} else {
			$preview.remove();
			$placeholder = $('<div class="agldc-image-placeholder">Nenhuma imagem configurada para este curso.</div>');
			$card.find('.agldc-course-preview').append($placeholder);
			$badge.removeClass('agldc-badge-custom').addClass('agldc-badge-global').text('Não configurado');
		}
	}

	$(function () {
		$('.agldc-color-field').wpColorPicker();

		// Course-specific certificate image upload
		$(document).on('click', '.agldc-upload-course-image', function (event) {
			event.preventDefault();

			var courseId = $(this).data('course-id');

			var courseFrame = wp.media({
				title: 'Selecionar imagem do certificado',
				button: {
					text: 'Usar imagem'
				},
				library: {
					type: ['image/jpeg', 'image/png']
				},
				multiple: false
			});

			courseFrame.on('select', function () {
				var attachment = courseFrame.state().get('selection').first().toJSON();
				setCourseImageState(courseId, attachment.url, attachment.id);
			});

			courseFrame.open();
		});

		$(document).on('click', '.agldc-remove-course-image', function (event) {
			event.preventDefault();
			var courseId = $(this).data('course-id');
			setCourseImageState(courseId, '', '');
		});

		// Group-specific certificate image upload
		function setGroupImageState(groupId, courseId, url, id) {
			var fieldId = 'agldc_group_' + groupId + '_course_' + courseId + '_image_id';
			var $field = $('#' + fieldId);
			var $card = $field.closest('.agldc-group-card');
			var $preview = $card.find('.agldc-group-preview img');
			var $placeholder = $card.find('.agldc-group-preview .agldc-image-placeholder');
			var $badge = $card.find('.agldc-badge-global, .agldc-badge-custom');
			var fallbackLabel = $field.data('fallback-label') || 'Usando curso';

			$field.val(id || '');

			if (url) {
				if ($preview.length === 0) {
					$preview = $('<img alt="">');
					$placeholder.replaceWith($preview);
				} else {
					$placeholder.hide();
				}
				$preview.attr('src', url).show();
				$badge.removeClass('agldc-badge-global').addClass('agldc-badge-custom').text('Personalizado');
			} else {
				$preview.remove();
				$placeholder = $('<div class="agldc-image-placeholder"></div>').text(fallbackLabel);
				$card.find('.agldc-group-preview').append($placeholder);
				$badge.removeClass('agldc-badge-custom').addClass('agldc-badge-global').text(fallbackLabel);
			}
		}

		$(document).on('click', '.agldc-upload-group-image', function (event) {
			event.preventDefault();

			var groupId = $(this).data('group-id');
			var courseId = $(this).data('course-id');

			var groupFrame = wp.media({
				title: 'Selecionar imagem do certificado',
				button: {
					text: 'Usar imagem'
				},
				library: {
					type: ['image/jpeg', 'image/png']
				},
				multiple: false
			});

			groupFrame.on('select', function () {
				var attachment = groupFrame.state().get('selection').first().toJSON();
				setGroupImageState(groupId, courseId, attachment.url, attachment.id);
			});

			groupFrame.open();
		});

		$(document).on('click', '.agldc-remove-group-image', function (event) {
			event.preventDefault();
			var groupId = $(this).data('group-id');
			var courseId = $(this).data('course-id');
			setGroupImageState(groupId, courseId, '', '');
		});
	});
}(jQuery));

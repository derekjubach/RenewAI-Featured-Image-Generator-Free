jQuery(document).ready(function ($) {
	console.log('RenewAI Featured Image Generator script loaded');

	function getPostId() {
		let postId;

		console.log('Attempting to get post ID');

		// Try to get post ID from URL first
		const urlParams = new URLSearchParams(window.location.search);
		postId = urlParams.get('post');
		console.log('Post ID from URL:', postId);

		// If not found in URL, try to get from classic editor
		if (!postId && $('#post_ID').length) {
			postId = $('#post_ID').val();
			console.log('Post ID from classic editor:', postId);
		}

		// If still not found, try to get from block editor
		if (!postId && wp.data && wp.data.select('core/editor')) {
			postId = wp.data.select('core/editor').getCurrentPostId();
			console.log('Post ID from block editor:', postId);
		}

		console.log('Final post ID:', postId);
		return postId;
	}

	function showSpinner($button) {
		$button.find('.spinner').css('visibility', 'visible');
		$button.prop('disabled', true);
	}

	function hideSpinner($button) {
		$button.find('.spinner').css('visibility', 'hidden');
		$button.prop('disabled', false);
	}

	function initializeMetaBox() {
		console.log('Initializing meta box');
		const $metaBox = $('#renewai-ig1-metabox');
		if (!$metaBox.length) {
			console.log('Meta box not found, exiting initialization');
			return;
		}

		// Log the current state
		console.log('Current generator:', $metaBox.data('generator'));
		console.log(
			'Current size options:',
			$('#renewai-ig1-size option')
				.map(function () {
					return { value: $(this).val(), text: $(this).text() };
				})
				.get()
		);

		const $promptTextarea = $('#renewai-ig1-prompt');
		const $generatePromptBtn = $('#renewai-ig1-generate-prompt');
		const $generateImageBtn = $('#renewai-ig1-generate-image');
		const $charCount = $('#renewai-ig1-char-count');
		const $statusMessage = $('#renewai-ig1-status-message');

		console.log('Prompt textarea found:', $promptTextarea.length > 0);
		console.log('Generate prompt button found:', $generatePromptBtn.length > 0);
		console.log('Generate image button found:', $generateImageBtn.length > 0);

		function updateCharCount() {
			const count = $promptTextarea.val().length;
			$charCount.text(`${count} / 1000`);
		}

		function toggleGenerateImageButton() {
			const hasPrompt = $promptTextarea.val().trim().length > 0;
			$generateImageBtn.toggle(hasPrompt);
			$('#renewai-ig1-image-options').toggle(hasPrompt);
		}

		$promptTextarea.on('input', function () {
			updateCharCount();
			toggleGenerateImageButton();
		});

		$generatePromptBtn.on('click', function () {
			console.log('Generate prompt button clicked');
			const postId = getPostId();
			console.log('Post ID for prompt generation:', postId);

			if (!postId) {
				console.error('Unable to determine the post ID');
				alert('Error: Unable to determine the post ID.');
				return;
			}

			showSpinner($generatePromptBtn);
			$generateImageBtn.prop('disabled', true);

			$.ajax({
				url: renewai_ig1_ajax.ajax_url,
				type: 'POST',
				data: {
					action: 'renewai_ig1_generate_prompt',
					nonce: renewai_ig1_ajax.nonce,
					post_id: postId,
					request_id: 'req_' + Date.now(),
				},
				success: function (response) {
					console.log('Prompt generation response:', response);
					if (response.success) {
						$promptTextarea.val(response.data.prompt);
						updateCharCount();
						toggleGenerateImageButton();
					} else {
						console.error('Error generating prompt:', response.data);
						alert('Error: ' + response.data);
					}
				},
				error: function (jqXHR, textStatus, errorThrown) {
					console.error('AJAX error:', textStatus, errorThrown);
					alert('An error occurred. Please try again.');
				},
				complete: function () {
					hideSpinner($generatePromptBtn);
					$generateImageBtn.prop('disabled', false);
				},
			});
		});

		$generateImageBtn.on('click', function () {
			console.log('Generate image button clicked');
			const postId = getPostId();
			console.log('Post ID for image generation:', postId);

			if (!postId) {
				console.error('Unable to determine the post ID');
				alert('Error: Unable to determine the post ID.');
				return;
			}

			showSpinner($generateImageBtn);
			$generatePromptBtn.prop('disabled', true);

			$.ajax({
				url: renewai_ig1_ajax.ajax_url,
				type: 'POST',
				data: {
					action: 'renewai_ig1_generate_image',
					nonce: renewai_ig1_ajax.nonce,
					post_id: postId,
					size: $('#renewai-ig1-size').val(),
					prompt: $promptTextarea.val(),
					request_id: 'req_' + Date.now(),
				},
				success: function (response) {
					console.log('Image generation response:', response);
					if (response.success) {
						localStorage.setItem('renewai_ig1_image_generated', 'true');
						window.location.reload();
					} else {
						console.error('Error generating image:', response.data);
						alert('Error: ' + response.data);
					}
				},
				error: function (jqXHR, textStatus, errorThrown) {
					console.error('AJAX error:', textStatus, errorThrown);
					alert('An error occurred. Please try again.');
				},
				complete: function () {
					hideSpinner($generateImageBtn);
					$generatePromptBtn.prop('disabled', false);
				},
			});
		});

		// Check if image was generated after page reload
		if (localStorage.getItem('renewai_ig1_image_generated') === 'true') {
			$statusMessage.text('Featured image generated successfully!').show();
			localStorage.removeItem('renewai_ig1_image_generated');
		}

		// Initial setup
		updateCharCount();
		toggleGenerateImageButton();
	}

	// Determine editor type and initialize
	if ($('body').hasClass('block-editor-page')) {
		console.log('Block editor detected');
		if (wp.data && wp.data.subscribe) {
			console.log('Setting up block editor initialization');
			const unsubscribe = wp.data.subscribe(() => {
				if (wp.data.select('core/editor').getCurrentPostId()) {
					console.log('Post ID available in block editor, initializing meta box');
					unsubscribe();
					initializeMetaBox();
				}
			});
		} else {
			console.error('wp.data.subscribe not available for block editor');
		}
	} else {
		console.log('Classic editor detected, initializing meta box immediately');
		initializeMetaBox();
	}

	// Plugin settings page functionality
	// Delete log file
	$('#renewai-ig1-delete-log').on('click', function (e) {
		e.preventDefault();
		if (
			confirm(
				'Are you sure you want to delete the log file? This will also disable debug mode.'
			)
		) {
			$.ajax({
				url: renewai_ig1_ajax.ajax_url,
				type: 'POST',
				data: {
					action: 'renewai_ig1_delete_log',
					nonce: renewai_ig1_ajax.nonce,
				},
				success: function (response) {
					if (response.success) {
						alert(response.data);
						// Update the UI to reflect debug mode being disabled
						$('#renewai_ig1_debug_mode').prop('checked', false);
						$('.renewai-ig1-log-actions').html(
							'<p>' + renewai_ig1_ajax.no_log_file_text + '</p>'
						);
						// Remove the warning notice
						$('.notice-warning').remove();
					} else {
						alert('Error: ' + (response.data || 'Unknown error occurred'));
					}
				},
				error: function (jqXHR, textStatus, errorThrown) {
					console.error('AJAX error:', textStatus, errorThrown);
					alert('An error occurred. Please check the console for more details.');
				},
			});
		}
	});

	// Common functionality (if any)
	function logToServer(message, level = 'info') {
		$.ajax({
			url: renewai_ig1_ajax.ajax_url,
			type: 'POST',
			data: {
				action: 'renewai_ig1_log',
				nonce: renewai_ig1_ajax.nonce,
				message: message,
				level: level,
			},
		});
	}

	// Function to toggle API key field
	window.toggleApiKeyField = function (provider) {
		console.log('toggleApiKeyField called with provider:', provider);
		var fieldId = 'renewai_ig1_' + provider + '_api_key';
		var field = document.getElementById(fieldId);
		if (!field) {
			console.error('Field not found:', fieldId);
			return;
		}
		var button = field.nextElementSibling;

		if (field.type === 'password') {
			field.value = '';
			field.type = 'text';
			button.textContent = renewai_ig1_ajax.cancel_text;
			field.value = '';
		} else {
			field.value = '••••••••••••••••••••••••••••••••';
			field.type = 'password';
			button.textContent = renewai_ig1_ajax.change_api_key_text;
		}
	};

	// Initialize API key fields
	function initApiKeyFields() {
		['openai', 'fal'].forEach(function (provider) {
			var fieldId = 'renewai_ig1_' + provider + '_api_key';
			var field = document.getElementById(fieldId);
			if (field && field.value) {
				field.value = '••••••••••••••••••••••••••••••••';
			}
		});
	}

	// Call initApiKeyFields when the document is ready
	initApiKeyFields();

	

	function updateModelInfo() {
		const $openaiModel = $('#renewai_ig1_openai_model');
		const $openaiModelNote = $('#renewai_ig1_openai_model_note');

		$openaiModel.on('change', function () {
			const selectedModel = $(this).val();
			const modelNotes = {
				'gpt-4o':
					'Most advanced GPT model. Multimodal, with GPT-4 Turbo intelligence but 2x faster and 50% cheaper.',
				'gpt-4o-mini':
					'Advanced small model. Multimodal, higher intelligence than GPT-3.5 Turbo with same speed. Most cost-effective option.',
				'gpt-4-turbo':
					'Large multimodal model with advanced reasoning and broad knowledge for accurate problem-solving.',
				'gpt-4':
					'Large multimodal model with broad knowledge and advanced reasoning capabilities.',
				'gpt-3.5-turbo':
					'Optimized for chat but works well for non-chat tasks. Good balance of performance and cost.',
			};
			$openaiModelNote.text(modelNotes[selectedModel]);
		});

		// Trigger change event to set initial note
		$openaiModel.trigger('change');
	}
	//
	function toggleModelFields() {
		var selectedGenerator = $('#renewai_ig1_image_generator').val();

		
	}

	$('#renewai_ig1_image_generator').on('change', toggleModelFields);

	

	//Update the model info
	updateModelInfo();
	//Toggle model fields
	toggleModelFields();
});

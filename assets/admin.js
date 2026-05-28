(function () {
	var forms = document.querySelectorAll('.yy-dmm-run-form');
	forms.forEach(function (form) {
		form.addEventListener('submit', function (event) {
			if (!window.confirm('DMM/FANZA APIを取得して投稿を実行します。よろしいですか？')) {
				event.preventDefault();
				return;
			}

			if (window.yyDmmAutoPostAdmin && window.yyDmmAutoPostAdmin.ajaxUrl) {
				event.preventDefault();
				runManualImport(form);
			}
		});
	});

	function postAjax(data) {
		return window.fetch(window.yyDmmAutoPostAdmin.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
			},
			body: new URLSearchParams(data).toString()
		}).then(function (response) {
			return response.json();
		});
	}

	function getManualNonce(form) {
		var nonceInput = form.querySelector('input[name="_wpnonce"]');
		return nonceInput ? nonceInput.value : '';
	}

	function runManualImport(form) {
		var nonce = getManualNonce(form);
		var submit = form.querySelector('[type="submit"]');
		if (!nonce) {
			return;
		}

		if (submit) {
			submit.disabled = true;
		}
		resetManualProgressPanel();
		renderManualProgress({
			status_label: '実行中',
			result: {},
			lines: [
				{ time: '', level: 'info', message: '手動実行を開始しています。' }
			]
		});

		function finishButton() {
			if (submit) {
				submit.disabled = false;
			}
		}

		function runNextStep() {
			postAjax({
				action: 'yy_dmm_auto_post_manual_step',
				nonce: nonce
			}).then(function (response) {
				if (response && response.success) {
					renderManualProgress(response.data.progress || {});
					if (response.data && response.data.done) {
						finishButton();
						return;
					}

					window.setTimeout(runNextStep, 500);
					return;
				}

				appendManualProgressLine('error', (response && response.data && response.data.message) ? response.data.message : '手動実行に失敗しました。');
				finishButton();
			}).catch(function () {
				appendManualProgressLine('error', '通信エラーにより手動実行を続行できませんでした。');
				finishButton();
			});
		}

		postAjax({
			action: 'yy_dmm_auto_post_run_manual',
			nonce: nonce
		}).then(function (response) {
			if (response && response.success) {
				renderManualProgress(response.data.progress || {});
				if (response.data && response.data.done) {
					finishButton();
					return;
				}

				window.setTimeout(runNextStep, 500);
				return;
			}

			appendManualProgressLine('error', (response && response.data && response.data.message) ? response.data.message : '手動実行に失敗しました。');
			finishButton();
		}).catch(function () {
			appendManualProgressLine('error', '通信エラーにより手動実行を開始できませんでした。');
			finishButton();
		});
	}

	function getManualProgressPanel() {
		return document.querySelector('[data-yy-dmm-manual-progress]');
	}

	function resetManualProgressPanel() {
		var panel = getManualProgressPanel();
		var lines = panel ? panel.querySelector('[data-yy-dmm-progress-lines]') : null;
		if (!panel) {
			return;
		}

		panel.hidden = false;
		if (lines) {
			lines.innerHTML = '';
		}
	}

	function renderManualProgress(progress) {
		var panel = getManualProgressPanel();
		if (!panel) {
			return;
		}

		panel.hidden = false;
		setProgressText(panel, 'status', progress.status_label || progress.status || '実行中');
		var result = progress.result || {};
		setProgressText(panel, 'fetched', result.fetched || 0);
		setProgressText(panel, 'posted', result.posted || 0);
		setProgressText(panel, 'created', result.created || 0);
		setProgressText(panel, 'updated', result.updated || 0);
		setProgressText(panel, 'skipped', result.skipped || 0);
		setProgressText(panel, 'errors', Array.isArray(result.errors) ? result.errors.length : 0);

		var lines = panel.querySelector('[data-yy-dmm-progress-lines]');
		if (!lines || !Array.isArray(progress.lines)) {
			return;
		}

		lines.innerHTML = '';
		progress.lines.forEach(function (line) {
			var item = document.createElement('li');
			item.className = 'yy-dmm-progress-line yy-dmm-progress-line-' + (line.level || 'info');
			var time = line.time ? '[' + line.time + '] ' : '';
			item.textContent = time + (line.message || '');
			lines.appendChild(item);
		});
		lines.scrollTop = lines.scrollHeight;
	}

	function appendManualProgressLine(level, message) {
		var panel = getManualProgressPanel();
		var lines = panel ? panel.querySelector('[data-yy-dmm-progress-lines]') : null;
		if (!panel || !lines) {
			return;
		}

		panel.hidden = false;
		var item = document.createElement('li');
		item.className = 'yy-dmm-progress-line yy-dmm-progress-line-' + level;
		item.textContent = message;
		lines.appendChild(item);
		lines.scrollTop = lines.scrollHeight;
	}

	function setProgressText(panel, key, value) {
		var element = panel.querySelector('[data-yy-dmm-progress-' + key + ']');
		if (element) {
			element.textContent = value;
		}
	}

	var maxPostsInput = document.getElementById('yy-dmm-max-posts');
	var maxPostsAllCheckbox = document.getElementById('yy-dmm-max-posts-all');
	if (maxPostsInput && maxPostsAllCheckbox) {
		function toggleMaxPostsInput() {
			maxPostsInput.disabled = maxPostsAllCheckbox.checked;
		}

		toggleMaxPostsInput();
		maxPostsAllCheckbox.addEventListener('change', toggleMaxPostsInput);
	}

	function fallbackCopy(text) {
		var textarea = document.createElement('textarea');
		textarea.value = text;
		textarea.setAttribute('readonly', 'readonly');
		textarea.style.position = 'absolute';
		textarea.style.left = '-9999px';
		document.body.appendChild(textarea);
		textarea.select();

		try {
			document.execCommand('copy');
		} finally {
			document.body.removeChild(textarea);
		}
	}

	function copyText(text) {
		if (navigator.clipboard && navigator.clipboard.writeText) {
			return navigator.clipboard.writeText(text);
		}

		fallbackCopy(text);
		return Promise.resolve();
	}

	var copyButtons = document.querySelectorAll('.yy-dmm-copy-token');
	copyButtons.forEach(function (button) {
		button.addEventListener('click', function () {
			var text = button.getAttribute('data-copy-text') || '';
			var originalText = button.textContent;
			if (!text) {
				return;
			}

			copyText(text).then(function () {
				button.textContent = 'コピー済み';
				window.setTimeout(function () {
					button.textContent = originalText;
				}, 1200);
			}).catch(function () {
				button.textContent = '失敗';
				window.setTimeout(function () {
					button.textContent = originalText;
				}, 1200);
			});
		});
	});
})();

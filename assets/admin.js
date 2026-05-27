(function () {
	var forms = document.querySelectorAll('.yy-dmm-run-form');
	forms.forEach(function (form) {
		form.addEventListener('submit', function (event) {
			if (!window.confirm('DMM/FANZA APIを取得して投稿を実行します。よろしいですか？')) {
				event.preventDefault();
			}
		});
	});

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

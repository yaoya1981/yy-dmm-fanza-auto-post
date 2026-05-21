(function () {
	var forms = document.querySelectorAll('.yy-dmm-run-form');
	forms.forEach(function (form) {
		form.addEventListener('submit', function (event) {
			if (!window.confirm('DMM/FANZA APIを取得して投稿を実行します。よろしいですか？')) {
				event.preventDefault();
			}
		});
	});
})();

(function () {
	var $ = jQuery;

	$(document.body)
			.on('change', '.wishlist-type-input', toggle_fieldsets)
			.on('change', '.nmgr-select-page', maybe_show_select_page_text_input);

	function toggle_fieldsets() {
		if (true === this.checked) {
			$('.nmgr-container').prop('disabled', false);
		} else {
			$('.nmgr-container').prop('disabled', true);
		}
	}

	function maybe_show_select_page_text_input() {
		if ('create' === this.value) {
			$('.nmgr_set_page_title').slideDown(200).prop('disabled', false);
		} else {
			$('.nmgr_set_page_title').hide().prop('disabled', true);
		}
	}

	$('.wishlist-type-input').trigger('change');
	$('.nmgr-select-page').trigger('change');

})();
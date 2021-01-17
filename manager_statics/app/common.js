$(function () {
	$(".check-all").click(function () {
		$(".ids").prop("checked", this.checked);
	});
	$(".ids").click(function () {
		var option = $(".ids");
		option.each(function (i) {
			if (!this.checked) {
				$(".check-all").prop("checked", false);
				return false;
			} else {
				$(".check-all").prop("checked", true);
			}
		});
	});

	$(".phone4js").keyup(function () {
		$(this).val($(this).val().replace(/[^0-9]/g, ''));
	}).blur(function(){
		$(this).val($(this).val().replace(/[^0-9]/g, ''));
	}).bind("paste", function () {  //CTR+V事件处理
		$(this).val($(this).val().replace(/[^0-9]/g, ''));
	}).css("ime-mode", "disabled"); //CSS设置输入法不可用

	$(".car_no5").keyup(function () {
		$(this).val($(this).val().replace(/[^0-9A-Za-z]/g, ''));
		$(this).val($(this).val().toUpperCase());
		if($(this).val().length > 5){
			$(this).val($(this).val().substr(0, 5));
		}
	}).blur(function(){
		$(this).val($(this).val().replace(/[^0-9A-Za-z]/g, ''));
		$(this).val($(this).val().toUpperCase());
		if($(this).val().length > 5){
			$(this).val($(this).val().substr(0, 5));
		}
	}).bind("paste", function () {  //CTR+V事件处理
		$(this).val($(this).val().replace(/[^0-9A-Za-z]/g, ''));
		$(this).val($(this).val().toUpperCase());
		if($(this).val().length > 5){
			$(this).val($(this).val().substr(0, 5));
		}
	}).css("ime-mode", "disabled"); //CSS设置输入法不可用

	$('#page_div').find('a').click(function () {
		var action = $(this).attr('href');
		$('#search_form').attr('action',action);
		$('#search_form').submit();
		return false;
	})
});

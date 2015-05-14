$(function(){
	$('#unlink').on('click',function(){
		var title = 'Unlink account & sign out';
		$.Dialog.confirm(title,'Are you sure you want to unlink your account?',function(sure){
			if (!sure) return;

			$.Dialog.wait(title,'Removing account link');

			$.ajax({
				method: "POST",
				url: '/signout?unlink',
				success: function(data){
					if (typeof data !== 'object') return console.log(data) && $w.trigger('ajaxerror');

					if (data.status){
						$.Dialog.success(title,data.message);
						setTimeout(function(){
							window.location.reload();
						},1000);
					}
					else $.Dialog.fail(title,data.message);
				}
			});
		});
	});
});

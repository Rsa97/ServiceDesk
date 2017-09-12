try {
	if ((time = localStorage.getItem('token_expireAt')) !== null && time > Date.now()/1000) {
		location.replace('desktop_new.html');
	}
	var login;
	if ((login = localStorage.getItem('loginName')) !== null) {
		$("#username").val(login);
		$("#password").focus();
	} else {
		$("#username").val('').focus();
	}
	localStorage.clear();
	localStorage.setItem('loginName', login);
} catch (e) {
	alert('Слишком старый браузер.');
}

$(function() {
	$('.head').show();
	
	$('#changePasswd').change(function(){
  		if ($(this).prop('checked'))
			$('#newpass').show();
		else
			$('#newpass').hide();
	});

	$('#login').click(function() {
		var change = 0;
		var newpass = '';
		if ($('#changePasswd').prop('checked')) {
			newpass = $('#newpasswd').val().trim();
			if (newpass.length < 6) {
				alert('Новый пароль должен быть не короче 6 символов');
	  			return;
			}
			if (newpass != $('#checkpasswd').val().trim()) {
				alert('Введённые новые пароли не совпадают');
				return;
			}
			change = 1;
		}

		$('input').prop('disabled', 'disabled');
		var loginName = $("#username").val().trim();
		var password = $("#password").val().trim();

		try {
			localStorage.setItem('loginName', loginName);
		} catch (e) {}
		
		$.ajax({
			  url: 		'/api/v2/auth/name/'+loginName+'/pass/'+password,
			  method: 	'POST',
			  cache: 	false,
			  dataType: 'json',
		}).done(function(data) {
			if (null !== data && 'undefined' !== typeof data.result) {
				if ('error' == data.result) {
					alert(data.error);
					$("#password").val('');
					return;
				}
				localStorage.setItem('token', JSON.stringify(data.token));
				localStorage.setItem('token_expireAt', Date.now()/1000 + data.expireTime);
				location.replace('desktop_new.html');
				if (1 == change) {
					$.ajax({
						url:		'/api/v2/newPass/token/'+data.token+'/pass/'+newpass,
						method: 	'POST',
						cache:		false,
						dataType:	'json',
					});
				}
			} else {
				alert('Ошибка соединения с сервером. Попробуйте перезагрузить страницу через некоторое время.');
			}
		}).fail(function(data) {
			alert('Ошибка соединения с сервером. Попробуйте перезагрузить страницу через некоторое время.');
		}).always(function(data) {
			$('input').removeProp('disabled');
			$("#password").focus();
		});
      		
	});

	$('input').keypress(function(event) {
		if (event.which == 0xD) {
			switch ($(this).attr('id')) {
				case 'username':
					$('#password').focus();
					break;
				case 'password':
				case 'login':
					$('#login').trigger('click');
					break;
			}
			event.preventDefault();
		}
	});
});


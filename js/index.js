$(function() {
	$('#newpass').hide();
	$('#key').data('try', 0);
	
	try {
		if ((l = localStorage.getItem('loginName')) !== null) {
			$("#username").val(l);
			$("#password").focus();
		} else {
			$("#username").val('').focus();
		}
	} catch (e) {
		$("#username").val('').focus();
	}

  
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
		try {
			localStorage.setItem('loginName', $("#username").val());
		} catch (e) {}
		$.post('/ajax/user/key')
			.done(function(data){
				if (data === null || typeof data.key === 'undefined') {
			        alert('Ошибка соединения с сервером. Попробуйте перезагрузить страницу через некоторое время.');
			        return;
				}
				var newpass = '';
				var change = 0;
  				var crypt = new JSEncrypt();
  				crypt.setPublicKey(data.key);
				var encrypted = crypt.encrypt($('#password').val());
  				if (change)
					newpass = crypt.encrypt(newpass);
			  	$.post('/ajax/user/login', {name: $('#username').val(), pass: encrypted, changePass: change, newPass: newpass})
					.done(function(data){
						if (data !== null) {
							if (typeof data.redirect !== 'undefined')
								location.replace(data.redirect);
	          				else if (typeof data.error !== 'undefined') {
								alert(data.error);
								$("#password").val('');
							}
						} else
							alert('Ошибка соединения с сервером. Попробуйте перезагрузить страницу через некоторое время.');
					})
					.error(function(data) {
        				alert('Ошибка соединения с сервером. Попробуйте перезагрузить страницу через некоторое время.');
      				})
					.always(function(data) {
        				$('input').removeProp('disabled');
        				$("#password").focus();
      				});
      		})
			.error(function(data) {
				alert('Ошибка соединения с сервером. Попробуйте перезагрузить страницу через некоторое время.');
      		})
			.always(function(data) {
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


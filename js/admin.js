function myPostJson(url, param, onReady, onError, onAlways) {
  $.post(url, param, 'json')
    .done(function(data) {
      if (data !== null) {
        if (typeof data.error !== 'undefined') {
          alert(data.error);
        } else {
          for (var key in data)
            if(data.hasOwnProperty(key)) {
              if (key == 'message')
              	alert(data[key]);
              else {
              	if (key.substr(0, 1) == '_')
                  $('#'+key.substr(1)).val(data[key]);
              	else
                  $('#'+key).html(data[key]);
              }
            }
          if (typeof onReady === 'function')
            onReady(data);
        }
        if (typeof data.redirect != 'undefined')
          location.replace(data.redirect);
      }
    })
    .fail(function() {
      if (typeof onError === 'function')
        onError();
      else
        alert('Ошибка связи с сервером');
    })
    .always(function() {
      if (typeof onAlways === 'function')
        onAlways();
    });
}

$(function() {
	$.datepicker.setDefaults({ monthNames: ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'],
			       monthNamesShort: ['Янв', 'Фев', 'Мар', 'Апр', 'Май', 'Июн', 'Июл', 'Авг', 'Сен', 'Окт', 'Ноя', 'Дек'],
			       dayNamesMin: ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'],
			       firstDay: 1});	
	
	$('button').button();
	$('button').each(function() {
	if ($(this).data('icon') != '')
		$(this).button('option', 'icons', {primary: $(this).data('icon')});
	});
	$('#refresh').button('option', 'text', false);
	$("#desktop").click(function() {
		location.replace('desktop.html');
	});

	$('#moveSubTypeDlg').dialog({autoOpen: false,
                     			 resizable: false,
                     			 dialogClass: 'no-close',
                     			 width: 'auto',
                     			 modal: true,
                     			 draggable: false,
                     			 closeOnEscape: true,
                     			 title: 'Перемещение подтипа',
                     			 buttons: [{text:"Ok", click:function() {
                     			 	myPostJson('ajax/eqtypes.php', {"call":"moveSubType","subType":$('#moveSubType').data('id'),
                     			 									"toType":$('#moveToType').val()}, 
                     			 									function() {
																		$('li.collapsed ul').hide();
																	});
									$(this).dialog("close");
                     			 }}, {text:"Отменить", click:function() { $(this).dialog("close"); }}] 
              				   });

	myPostJson('ajax/userName.php', {});
  
	$('.menuLink').click(function() {
		$('.menuLink').removeClass('menuActive');
		$(this).addClass('menuActive');
		myPostJson('ajax/'+$(this).data('page')+'.php', {"call":"init"}, function() {
			$('li.collapsed ul').hide();
		});
  	});

	$('#content').on('click', 'span.collapse', function() {
		if ($(this).parent().hasClass('collapsed')) {
			$(this).parent().removeClass('collapsed');
			$(this).siblings('ul').first().show();
			$(this).removeClass('ui-icon-folder-collapsed').addClass('ui-icon-folder-open');
		} else {
			$(this).parent().addClass('collapsed');
			$(this).siblings('ul').first().hide();
			$(this).removeClass('ui-icon-folder-open').addClass('ui-icon-folder-collapsed');
		}
	});
	
	$('#content').on('click', 'span.ui-icon-plusthick', function() {
		var name, row;
		if ($(this).parent('li').hasClass('eqType')) {
			if ((name = prompt('Название типа:')) === null || (name = name.trim()) == '')
				return;
			myPostJson('ajax/eqtypes.php', {"call":"addType","name":name}, function() {
				$('li.collapsed ul').hide();
			});
		} else if ($(this).parent('li').hasClass('eqSubType')) {
			if ((name = prompt('Название подтипа:')) === null || (name = name.trim()) == '')
				return;
			myPostJson('ajax/eqtypes.php', {"call":"addSubType","name":name,"toType":$(this).parents('li.eqType').data('id')}, function() {
				$('li.collapsed ul').hide();
			});
		} else if ($(this).parent().attr('id') == 'mdSelectors') {
			if ((name = prompt('Производитель:')) === null || (name = name.trim()) == '')
				return;
			myPostJson('ajax/eqmodels.php', {"call":"addMfg","name":name,"subType":$('#mdSubType').val()});
		} else if ($(this).parent('li').hasClass('model')) {
			if ((name = prompt('Модель: '+$('#mdMfg :selected').text().trim()+' ')) === null || (name = name.trim()) == '')
				return;
			myPostJson('ajax/eqmodels.php', {"call":"addModel","name":name,"subType":$('#mdSubType').val(),"mfg":$('#mdMfg').val()});
		} else if ($(this).parents('table').hasClass('slaTbl') || $(this).parents('table').hasClass('servTbl')) {
			$(this).parents('tr').find('td').each(function(i) {
				if (i == 0)
					$(this).html("<span class='ui-icon ui-icon-check'> </span> <span class='ui-icon ui-icon-closethick'> </span>");
				else {
					$(this).html("<input type='text'>");
				}
			});
		} else if ($(this).parents('table').hasClass('slaLvlTbl')) {
			row = $(this).parents('tr');
			name = '<select>';
			$('#letters').val().split('').forEach(function(ltr) {
				name += "<option value='"+ltr+"'>"+ltr;
			});
			name += '</select>';
			row.find('.tdId').html(name);
			row.find('.tdName').html("<input type='text' maxlength='46'>");
			row.find('.tdDesc').html("<textarea rows='5'></textarea>");
			row.find('.tdBtns').html("<span class='ui-icon ui-icon-check'> </span> <span class='ui-icon ui-icon-closethick'> </span>");
		} else if ($(this).parents('table').hasClass('partnerTbl') || $(this).parents('table').hasClass('caTbl')) {
			$(this).parents('tr').find('td:eq(1)').html("<input type='text'>");
			$(this).parents('tr').find('td:eq(0)').html("<span class='ui-icon ui-icon-check'> </span> <span class='ui-icon ui-icon-closethick'> </span>");
		} else if ($(this).parents('table').hasClass('usersTbl')) {
			$(this).parents('tr').find('td').each(function(i) {
				if (i == 0)
					$(this).html("<span class='ui-icon ui-icon-check'> </span><span class='ui-icon ui-icon-closethick'> </span>");
				else if (i == 5) 
					$(this).html($('#rlist').val());
				else if (i == 9) {
					$('#oldOrg').val('');
					$(this).html('<select id="org"></select>');
				} else
					$(this).html("<input type='text'>");
			});
		} else if ($(this).parents('table').hasClass('contractsTbl')) {
			$(this).parents('tr').find('td').each(function(i) {
				if (i == 0)
					$(this).html("<span class='ui-icon ui-icon-check'> </span><span class='ui-icon ui-icon-closethick'> </span>");
				else if (i == 2)
					$(this).html("<select id='contragent'></select>");
				else if (i == 5 || i == 6)
					$(this).html("<textarea rows='3'>");
				else if (i == 9)
					$(this).html("<div id='users' class='checklist'></div>");
				else
					$(this).html("<input type='text'>");
				if (i == 7 || i == 8) {
					$(this).find('input').datepicker({
						changeMonth: true,
						numberOfMonths: 1,
						dateFormat: 'yy-mm-dd',
						constrainInput: false,
    				});
				}
			});
			myPostJson('ajax/contracts.php', {"call":"fillSelects","id":"0"});
		} else if ($(this).parents('table').hasClass('divsTbl')) {
			$(this).parents('tr').find('td').each(function(i) {
				if (i == 0)
					$(this).html("<span class='ui-icon ui-icon-check'> </span><span class='ui-icon ui-icon-closethick'> </span>");
				else if (i == 2)
					$(this).html("<select id='contragent'></select>");
				else if (i == 5 || i == 6)
					$(this).html("<textarea rows='3'>");
				else if (i == 7)
					$(this).html("<div id='users' class='checklist'></div>");
				else if (i == 8)
					$(this).html("<select id='type'></select>");
				else if (i != 9)
					$(this).html("<input type='text'>");
			});
			myPostJson('ajax/divisions.php', {"call":"fillSelects","id":"0"});
		} else if ($(this).parents('table').hasClass('contSrvTbl')) {
			$(this).parents('tr').find('td:eq(1)').html($("#addList").val());
			$(this).parents('tr').find('td:eq(0)').html("<span class='ui-icon ui-icon-check'> </span><span class='ui-icon ui-icon-closethick'> </span>");
		} else if ($(this).parents('table').hasClass('divSlaTbl')) {
			$(this).parents('tr').find('td:eq(2)').html("<select id='sla'></select>");
			$(this).parents('tr').find('td:eq(1)').html("<select id='level'></select>");
			$(this).parents('tr').find('td:eq(0)').html("<span class='ui-icon ui-icon-check'> </span><span class='ui-icon ui-icon-closethick'> </span>");
			myPostJson('ajax/divslas.php', {"call":"fillSelects","levelId":"0","divisionId":$('#slaDivision').val(),"slaId":"0"});
		} else if ($(this).parents('table').hasClass('divPartnerTbl')) {
			$(this).parents('tr').find('td:eq(1)').html("<select id='partner'></select>");
			$(this).parents('tr').find('td:eq(0)').html("<span class='ui-icon ui-icon-check'> </span><span class='ui-icon ui-icon-closethick'> </span>");
			myPostJson('ajax/divpartners.php', {"call":"fillSelects","divisionId":$('#partnerDivision').val(),"partnerId":"0"});
		} else if ($(this).parents('table').hasClass('divEqTbl')) {
			$(this).parents('tr').find('td:eq(1)').html("<input id='servNumber'>");
			$(this).parents('tr').find('td:eq(2)').html("<input id='serial'>");
			$(this).parents('tr').find('td:eq(3)').html("<input id='mfg'><input id='model'>");
			$(this).parents('tr').find('td:eq(4)').html("<input id='warrEnd'>");
			$(this).parents('tr').find('td:eq(5)').html("<input id='remark'>");
			$('#mfg').autocomplete({
				minLength:0,
				position: {collision: 'flip', within: 'body'}, 
				source: function(request, response) {
					$.post('ajax/autocomplete.php', {"call":"mfg","term":request.term}, 'json')
					.done(function(data) {
						if (typeof data.select !== 'undefined')
							response(data.select);
						else
							response([]);
					})
					.fail(function() {
						response([]);							
					});
				}
			}).focus(function() {
				console.log('focus');
				if ($(this).autocomplete("widget").is(":visible"))
					return;
				$(this).autocomplete("search", $(this).val());
			});
			$('#mfg').on("autocompleteselect", function(){
				$('#model').val('');
			});
			$('#model').autocomplete({
				minLength:0, 
				position: {collision: 'flip', within: 'body'}, 
				source: function(request, response) {
					$.post('ajax/autocomplete.php', {"call":"model","term":request.term,"mfg":$('#mfg').val()}, 'json')
					.done(function(data) {
						if (typeof data.select !== 'undefined')
							response(data.select);
						else
							response([]);
					})
					.fail(function() {
						response([]);							
					});
				},
				select: function(event, ui) {
					if ($('#mfg').val() == '')
						myPostJson('ajax/autocomplete.php', {"call":"modelMfg","term":ui['item']['value']});
				}
			}).focus(function() {
				console.log('focus');
				if ($(this).autocomplete("widget").is(":visible"))
					return;
				$(this).autocomplete("search", $(this).val());
			});
			$('#model').on('change', function(){
				
			});
			$('#warrEnd').datepicker({
						changeMonth: true,
						numberOfMonths: 1,
						dateFormat: 'yy-mm-dd',
						constrainInput: false,
    			});
			$(this).parents('tr').find('td:eq(0)').html("<span class='ui-icon ui-icon-check'> </span><span class='ui-icon ui-icon-closethick'> </span>");
		} else if ($(this).parents('table').hasClass('divTypesTbl')) {
			row = $(this).parents('tr').find('td');
			row.eq(2).html("<textarea id='typeComment'></textarea>");
			row.eq(1).html("<input id='typeName'>");
			row.eq(0).html("<span class='ui-icon ui-icon-check'> </span><span class='ui-icon ui-icon-closethick'> </span>");
		} else if ($(this).parents('table').hasClass('contSlaTbl')) {
			$(this).parents('tr').find('td').each(function(i) {
				if (i == 0)
					$(this).html("<span class='ui-icon ui-icon-check'> </span><span class='ui-icon ui-icon-closethick'> </span>");
				else if (i == 1)
					$(this).html("<select id='service'></select>");
				else if (i == 2)
					$(this).html("<select id='divType'></select>");
				else if (i == 3)
					$(this).html("<select id='slaLevel'></select>");
				else if (i == 10 || i == 11 || i == 12)
					$(this).html("<input type='checkbox'>");
				else if (i != 13)
					$(this).html("<input type='text'>");
			});
			myPostJson('ajax/contsla.php', {"call":"fillSelects","srvId":0,"typeId":0,"slaLevel":""});
		}
	});

	$('#content').on('click', 'span.ui-icon-trash', function() {
		var row;
		if ($(this).parent('li').hasClass('eqType')) {
			if (!confirm('Удалить тип "'+$(this).parent('li').children('.desc').text()+'"?'))
				return;
			myPostJson('ajax/eqtypes.php', {"call":"delType","type":$(this).parent('li').data('id')}, function() {
				$('li.collapsed ul').hide();
			});
		} else if ($(this).parent('li').hasClass('eqSubType')) {
			if (!confirm('Удалить подтип "'+$(this).parent('li').children('.desc').text()+'"?'))
				return;
			myPostJson('ajax/eqtypes.php', {"call":"delSubType","type":$(this).parents('li.eqType').data('id'),"subType":$(this).parent('li').data('id')}, function() {
				$('li.collapsed ul').hide();
			});
		} else if ($(this).parent().attr('id') == 'mdSelectors') {
			if (!confirm('Удалить производителя "'+$('#mdMfg :selected').text().trim()+'"?'))
				return;
			myPostJson('ajax/eqmodels.php', {"call":"delMfg","subType":$('#mdSubType').val(),"mfg":$('#mdMfg').val()});
		} else if ($(this).parent('li').hasClass('model')) {
			if (!confirm('Удалить модель "'+$('#mdMfg :selected').text().trim()+' '+$(this).siblings('.desc').text()+'"?'))
				return;
			myPostJson('ajax/eqmodels.php', {"call":"delModel","model":$(this).parent().data('id'),"subType":$('#mdSubType').val(),"mfg":$('#mdMfg').val()});
		} else if ($(this).parents('table').hasClass('slaTbl')) {
			if (!confirm('Удалить SLA "'+$(this).parents('tr').find('td')[1].textContent+'"?'))
				return;
			myPostJson('ajax/slas.php', {"call":"delSLA","id":$(this).parents('tr').data('id')});
		} else if ($(this).parents('table').hasClass('slaLvlTbl')) {
			if (!confirm('Удалить уровень критичности "'+$(this).parents('tr').data('id')+' - '+$(this).parents('tr').find('.tdName').text()+'"?'))
				return;
			myPostJson('ajax/levels.php', {"call":"delLevel","id":$(this).parents('tr').data('id')});
		} else if ($(this).parents('table').hasClass('servTbl')) {
			if (!confirm('Удалить услугу "'+$(this).parents('tr').find('td')[1].textContent+'"?'))
				return;
			myPostJson('ajax/services.php', {"call":"delService","id":$(this).parents('tr').data('id')});
		} else if ($(this).parents('table').hasClass('partnerTbl')) {
			if (!confirm('Удалить партнёра "'+$(this).parents('tr').find('td')[1].textContent+'"?'))
				return;
			myPostJson('ajax/partners.php', {"call":"delPartner","id":$(this).parents('tr').data('id')});
		} else if ($(this).parents('table').hasClass('caTbl')) {
			if (!confirm('Удалить контрагента "'+$(this).parents('tr').find('td')[1].textContent+'"?'))
				return;
			myPostJson('ajax/contragents.php', {"call":"delCa","id":$(this).parents('tr').data('id')});
		} else if ($(this).parents('table').hasClass('usersTbl')) {
			if (!confirm('Удалить пользователя "'+$(this).parents('tr').find('td')[1].textContent+' '+
														$(this).parents('tr').find('td')[2].textContent+' '+
														$(this).parents('tr').find('td')[3].textContent+'"?'))
				return;
			myPostJson('ajax/users.php', {"call":"delUser","id":$(this).parents('tr').data('id')});
		} else if ($(this).parents('table').hasClass('contractsTbl')) {
			if (!confirm('Удалить договор "'+$(this).parents('tr').find('td')[1].textContent+' - '+
														$(this).parents('tr').find('td')[2].textContent+'"?'))
				return;
			myPostJson('ajax/contracts.php', {"call":"delContract","id":$(this).parents('tr').data('id')});
		} else if ($(this).parents('table').hasClass('divsTbl')) {
			if (!confirm('Удалить подразделение "'+$(this).parents('tr').find('td')[1].textContent+'"?'))
				return;
			myPostJson('ajax/divisions.php', {"call":"delDivision","id":$(this).parents('tr').data('id'),"contractId":$('#divContract').val()});
		} else if ($(this).parents('table').hasClass('contSrvTbl')) {
			if (!confirm('Удалить из договора сервис "'+$(this).parents('tr').find('td')[1].textContent+'"?'))
				return;
			myPostJson('ajax/contsrvs.php', {"call":"delService","id":$(this).parents('tr').data('id'),"contractId":$('#srvContract').val()});
		} else if ($(this).parents('table').hasClass('divSlaTbl')) {
			if (!confirm('Удалить для филиала уровень критичности "'+$(this).parents('tr').find('td')[1].textContent+'"?'))
				return;
			myPostJson('ajax/divslas.php', {"call":"delDivSla","levelId":$(this).parents('tr').data('id'),"divisionId":$('#slaDivision').val()});
		} else if ($(this).parents('table').hasClass('divPartnerTbl')) {
			if (!confirm('Удалить для филиала партнёра "'+$(this).parents('tr').find('td')[1].textContent+'"?'))
				return;
			myPostJson('ajax/divpartners.php', {"call":"delDivPartner","partnerId":$('#partnerDivision').val(),"divisionId":$('#partnerDivision').val()});
		} else if ($(this).parents('table').hasClass('divEqTbl')) {
			if (!confirm('Удалить "'+$(this).parents('tr').find('td')[3].textContent+'" сервисный номер '+
									$(this).parents('tr').find('td')[1].textContent+'?'))
				return;
			myPostJson('ajax/divequip.php', {"call":"delEquip","divisionId":$('#eqDivision').val(),"servNum":$(this).parents('tr').data('id')});
		} else if ($(this).parents('table').hasClass('divTypesTbl')) {
			if (!confirm('Удалить тип филиала "'+$(this).parents('tr').find('td').eq(1).text()+'"?'))
				return;
			myPostJson('ajax/divisionTypes.php', {"call":"delType","id":$(this).parents('tr').data('id')});
		} else if ($(this).parents('table').hasClass('contSlaTbl')) {
			row = $(this).parents('tr').find('td');
			if (!confirm('Удалить SLA уровня "'+row.eq(3).text()+'" для услуги "'+row.eq(1).text()+'" в филиалах типа "'+row.eq(2).text()+'"?'))
				return;
			myPostJson('ajax/contsla.php', {"call":"delSla","contractId":$('#divSlaContract').val(),"srvId":row.eq(1).data('id'),
											"typeId":row.eq(2).data('id'),"slaLevel":row.eq(3).data('id'),
											"workDays":(row.eq(10).find('input').prop('checked') ? 1 : 0),
											"weekends":(row.eq(11).find('input').prop('checked') ? 1 : 0),
											"isDefault":(row.eq(12).find('input').prop('checked') ? 1 : 0)});
		}
	});

	$('#content').on('click', 'span.ui-icon-pencil', function() {
		var name, row, id, v1, v2, v3;
		if ($(this).parent('li').hasClass('eqType')) {
			if ((name = prompt('Новое название типа:', $(this).parent('li').children('.desc').text())) === null || (name = name.trim()) == '')
				return;
			myPostJson('ajax/eqtypes.php', {"call":"renType","type":$(this).parent('li').data('id'),"newName":name}, function() {
				$('li.collapsed ul').hide();
			});
		} else if ($(this).parent('li').hasClass('eqSubType')) {
			if ((name = prompt('Новое название подтипа:', $(this).parent('li').children('.desc').text())) === null || (name = name.trim()) == '')
				return;
			myPostJson('ajax/eqtypes.php', {"call":"renSubType","type":$(this).parents('li.eqType').data('id'),
											"subType":$(this).parent('li').data('id'),"newName":name}, function() {
				$('li.collapsed ul').hide();
			});
		} else if ($(this).parent().attr('id') == 'mdSelectors') {
			if ((name = prompt('Новое название производителя:', $('#mdMfg :selected').text().trim())) === null || (name = name.trim()) == '')
				return;
			myPostJson('ajax/eqmodels.php', {"call":"renMfg","name":name,"subType":$('#mdSubType').val(),"mfg":$('#mdMfg').val()});
		} else if ($(this).parent('li').hasClass('model')) {
			if ((name = prompt('Новое название: '+$('#mdMfg :selected').text().trim()+' ', $(this).siblings('.desc').text())) === null || (name = name.trim()) == '')
				return;
			myPostJson('ajax/eqmodels.php', {"call":"renModel","model":$(this).parent().data('id'),"name":name,"subType":$('#mdSubType').val(),"mfg":$('#mdMfg').val()});
		} else if ($(this).parents('table').hasClass('slaTbl') || $(this).parents('table').hasClass('servTbl')) {
			$(this).parents('tr').find('td').each(function(i){
				if (i == 0)
					$(this).html("<span class='ui-icon ui-icon-check'> </span> <span class='ui-icon ui-icon-closethick'> </span>");
				else {
					name = $(this).text();
					$(this).html("<input type='text'>");
					$(this).children('input').val(name);
				}
			});
		} else if ($(this).parents('table').hasClass('slaLvlTbl')) {
			row = $(this).parents('tr');
			name = '<select>';
			id = row.find('.tdId').text();
			$('#letters').val().split('').concat(id).sort().forEach(function(ltr) {
				name += "<option value='"+ltr+(ltr == id ? "' selected>" : "'>")+ltr;
			});
			name += '</select>';
			row.find('.tdId').html(name);
			row.find('.tdName').html("<input type='text' maxlength='46' value='"+row.find('.tdName').text()+"'>");
			row.find('.tdDesc').html("<textarea rows='5'>"+row.find('.tdDesc').text()+"</textarea>");
			row.find('.tdBtns').html("<span class='ui-icon ui-icon-check'> </span> <span class='ui-icon ui-icon-closethick'> </span>");
		} else if ($(this).parents('table').hasClass('partnerTbl') || $(this).parents('table').hasClass('caTbl')) {
			name = $(this).parents('tr').find('td:eq(1)').text();
			$(this).parents('tr').find('td:eq(1)').html("<input type='text' >");
			$(this).parents('tr').find('input').val(name);
			$(this).parents('tr').find('td:eq(0)').html("<span class='ui-icon ui-icon-check'> </span> <span class='ui-icon ui-icon-closethick'> </span>");
		} else if ($(this).parents('table').hasClass('usersTbl')) {
			$(this).parents('tr').find('td').each(function(i) {
				name = $(this).text();
				if (i == 0)
					$(this).html("<span class='ui-icon ui-icon-check'> </span><span class='ui-icon ui-icon-closethick'> </span>");
				else if (i == 5) { 
					$(this).html($('#rlist').val());
					$('#rights option').each(function() {
						if ($(this).selected)
							$(this).removeAttr('selected');
						if ($(this).text() == name)
							$(this).attr('selected', 'selected');
					});
				} else if (i == 9) {
					$('#oldOrg').val(name);
					$(this).html('<select id="org"></select>');
				} else {
					$(this).html("<input type='text'>");
					$(this).find('input').val(name);
				}
			});
			if ($('#rights').val() == 'partner')
				$('#rights').trigger('change');
		} else if ($(this).parents('table').hasClass('contractsTbl')) {
			id = $(this).parents('tr').data('id');
			$(this).parents('tr').find('td').each(function(i) {
				name = $(this).text();
				if (i == 0)
					$(this).html("<span class='ui-icon ui-icon-check'> </span><span class='ui-icon ui-icon-closethick'> </span>");
				else if (i == 2)
					$(this).html("<select id='contragent'></select>");
				else if (i == 5 || i == 6) {
					$(this).html("<textarea rows='3'>");
					$(this).find('textarea').val(name);
				}
				else if (i == 9)
					$(this).html("<div id='users' class='checklist'></div>");
				else {
					$(this).html("<input type='text'>");
					$(this).find('input').val(name);
				}
				if (i == 7 || i == 8) {
					$(this).find('input').datepicker({
						changeMonth: true,
						numberOfMonths: 1,
						dateFormat: 'yy-mm-dd',
						constrainInput: false,
    				});
				}
			});
			myPostJson('ajax/contracts.php', {"call":"fillSelects","id":id});
		} else if ($(this).parents('table').hasClass('divsTbl')) {
			id = $(this).parents('tr').data('id');
			$(this).parents('tr').find('td').each(function(i) {
				name = $(this).text();
				if (i == 0)
					$(this).html("<span class='ui-icon ui-icon-check'> </span><span class='ui-icon ui-icon-closethick'> </span>");
				else if (i == 2)
					$(this).html("<select id='contragent'></select>");
				else if (i == 5 || i == 6) {
					$(this).html("<textarea rows='3'>");
					$(this).find('textarea').val(name);
				}
				else if (i == 7)
					$(this).html("<div id='users' class='checklist'></div>");
				else if (i == 8)
					$(this).html("<select id='type'></select>");
				else if (i != 9) {
					$(this).html("<input type='text'>");
					$(this).find('input').val(name);
				}
			});
			myPostJson('ajax/divisions.php', {"call":"fillSelects","id":id});
		} else if ($(this).parents('table').hasClass('divSlaTbl')) {
			id = $(this).parents('tr').data('id');
			name = $(this).parents('tr').find('td:eq(2)').data('id');
			$(this).parents('tr').find('td:eq(2)').html("<select id='sla'></select>");
			$(this).parents('tr').find('td:gt(2)').html("");
			$(this).parents('tr').find('td:eq(0)').html("<span class='ui-icon ui-icon-check'> </span><span class='ui-icon ui-icon-closethick'> </span>");
			myPostJson('ajax/divslas.php', {"call":"fillSelects","levelId":id,"divisionId":$('#slaDivision').val(),"slaId":name});
		} else if ($(this).parents('table').hasClass('divPartnerTbl')) {
			id = $(this).parents('tr').data('id');
			$(this).parents('tr').find('td:eq(1)').html("<select id='partner'></select>");
			$(this).parents('tr').find('td:eq(0)').html("<span class='ui-icon ui-icon-check'> </span><span class='ui-icon ui-icon-closethick'> </span>");
			myPostJson('ajax/divpartners.php', {"call":"fillSelects","divisionId":$('#partnerDivision').val(),"partnerId":id});
		} else if ($(this).parents('table').hasClass('divEqTbl')) {
			name = $(this).parents('tr').find('td:eq(2)').text();
			$(this).parents('tr').find('td:eq(2)').html("<input id='serial'>");
			$('#serial').val(name);
			$(this).parents('tr').find('td:eq(3)').html("<input id='mfg'><input id='model'>");
			$('#mfg').val($(this).parents('tr').data('mfg'));
			$('#model').val($(this).parents('tr').data('model'));
			name = $(this).parents('tr').find('td:eq(4)').text();
			$(this).parents('tr').find('td:eq(4)').html("<input id='warrEnd'>");
			$('#warrEnd').val(name);
			name = $(this).parents('tr').find('td:eq(5)').text();
			$(this).parents('tr').find('td:eq(5)').html("<input id='remark'>");
			$('#mfg').autocomplete({
				minLength:0,
				position: {collision: 'flip', within: 'body'}, 
				source: function(request, response) {
					$.post('ajax/autocomplete.php', {"call":"mfg","term":request.term}, 'json')
					.done(function(data) {
						if (typeof data.select !== 'undefined')
							response(data.select);
						else
							response([]);
					})
					.fail(function() {
						response([]);							
					});
				}
			}).focus(function() {
				console.log('focus');
				if ($(this).autocomplete("widget").is(":visible"))
					return;
				$(this).autocomplete("search", $(this).val());
			});
			$('#mfg').on("autocompleteselect", function(){
				$('#model').val('');
			});
			$('#model').autocomplete({
				minLength:0, 
				position: {collision: 'flip', within: 'body'}, 
				source: function(request, response) {
					$.post('ajax/autocomplete.php', {"call":"model","term":request.term,"mfg":$('#mfg').val()}, 'json')
					.done(function(data) {
						if (typeof data.select !== 'undefined')
							response(data.select);
						else
							response([]);
					})
					.fail(function() {
						response([]);							
					});
				}
			}).focus(function() {
				console.log('focus');
				if ($(this).autocomplete("widget").is(":visible"))
					return;
				$(this).autocomplete("search", $(this).val());
			});
			$('#warrEnd').datepicker({
						changeMonth: true,
						numberOfMonths: 1,
						dateFormat: 'yy-mm-dd',
						constrainInput: false,
    			});
			$(this).parents('tr').find('td:eq(0)').html("<span class='ui-icon ui-icon-check'> </span><span class='ui-icon ui-icon-closethick'> </span>");
		} else if ($(this).parents('table').hasClass('divTypesTbl')) {
			row = $(this).parents('tr').find('td');
			name = row.eq(2).text();
			row.eq(2).html("<textarea id='typeComment'></textarea>");
			$('#typeComment').val(name);
			name = row.eq(1).text();
			row.eq(1).html("<input id='typeName'>");
			$('#typeName').val(name);
			row.eq(0).html("<span class='ui-icon ui-icon-check'> </span><span class='ui-icon ui-icon-closethick'> </span>");
		} else if ($(this).parents('table').hasClass('contSlaTbl')) {
			$(this).parents('tr').find('td').each(function(i) {
				if (i == 0)
					$(this).html("<span class='ui-icon ui-icon-check'> </span><span class='ui-icon ui-icon-closethick'> </span>");
				else if (i == 1) {
					v1 = $(this).data('id'); 
					$(this).html("<select id='service'></select>");
				} else if (i == 2) {
					v2 = $(this).data('id'); 
					$(this).html("<select id='divType'></select>");
				} else if (i == 3) {
					v3 = $(this).data('id'); 
					$(this).html("<select id='slaLevel'></select>");
				} else if (i == 10 || i == 11 || i == 12)
					$(this).find('input').removeAttr('disabled');
				else if (i != 13) {
					val = $(this).text(); 
					$(this).html("<input type='text'>");
					$(this).find('input').val(val);
				}
			});
			myPostJson('ajax/contsla.php', {"call":"fillSelects","srvId":v1,"typeId":v2,"slaLevel":v3});
		}
	});

	$('#content').on('click', 'span.ui-icon-transferthick-e-w', function() {
		var s = '';
		if ($(this).parent('li').hasClass('eqSubType')) {
			$('#moveSubType').data('id', $(this).parent('li').data('id'));
			$('#moveSubType').text($(this).parent('li').children('.desc').text());
			$('#moveFromType').data('id', $(this).parents('li.eqType').data('id'));
			$('#moveFromType').text($(this).parents('li.eqType').children('.desc').text());
			$('.eqType').each(function() {
				s += '<option value="'+$(this).data('id')+'">'+$(this).children('.desc').text();
			});
			$('#moveToType').html(s);
			$('#moveSubTypeDlg').dialog('open');
		}
	});
	
	
	$('#content').on('click', 'span.ui-icon-closethick', function() {
		if ($(this).parents('table').hasClass('slaTbl')) {
			myPostJson('ajax/slas.php', {"call":"init"});
		} else if ($(this).parents('table').hasClass('slaLvlTbl')) {
			myPostJson('ajax/levels.php', {"call":"init"});
		} else if ($(this).parents('table').hasClass('servTbl')) {
			myPostJson('ajax/services.php', {"call":"init"});
		} else if ($(this).parents('table').hasClass('partnerTbl')) {
			myPostJson('ajax/partners.php', {"call":"init"});
		} else if ($(this).parents('table').hasClass('caTbl')) {
			myPostJson('ajax/contragents.php', {"call":"init"});
		} else if ($(this).parents('table').hasClass('usersTbl')) {
			myPostJson('ajax/users.php', {"call":"init"});
		} else if ($(this).parents('table').hasClass('contractsTbl')) {
			myPostJson('ajax/contracts.php', {"call":"init"});
		} else if ($(this).parents('table').hasClass('divsTbl')) {
			myPostJson('ajax/divisions.php', {"call":"selectContract","contractId":$('#divContract').val()});
		} else if ($(this).parents('table').hasClass('contSrvTbl')) {
			myPostJson('ajax/contsrvs.php', {"call":"selectContract","contractId":$('#srvContract').val()});
		} else if ($(this).parents('table').hasClass('divSlaTbl')) {
			myPostJson('ajax/divslas.php', {"call":"selectDivision","divisionId":$('#slaDivision').val()});
		} else if ($(this).parents('table').hasClass('divPartnerTbl')) {
			myPostJson('ajax/divpartners.php', {"call":"selectDivision","divisionId":$('#partnerDivision').val()});
		} else if ($(this).parents('table').hasClass('divEqTbl')) {
			myPostJson('ajax/divequip.php', {"call":"selectDivision","divisionId":$('#eqDivision').val()});
		} else if ($(this).parents('table').hasClass('divTypesTbl')) {
			myPostJson('ajax/divisionTypes.php', {"call":"init"});
		} else if ($(this).parents('table').hasClass('contSlaTbl')) {
			myPostJson('ajax/contsla.php', {"call":"selectContract","contractId":$('#divSlaContract').val()});
		}
	});

	$('#content').on('click', 'span.ui-icon-check', function() {
		var inp, name, v1, v2, v3, v4, v5, v6, v7, v8, v9, id;
		if ($(this).parents('table').hasClass('slaTbl')) {
			id = $(this).parents('tr').data('id');
			inp = $(this).parents('tr').find('input');
			if ((name = inp[0].value.trim()) == '' || (v1 = inp[1].value.trim()) == '' || (v2 = inp[2].value.trim()) == '' ||
				(v3 = inp[3].value.trim()) == '' || (v4 = inp[4].value.trim()) == '')
				return;
			if (id == 0) 
				myPostJson('ajax/slas.php', 
							{"call":"addSLA","name":name,"toReact":v1,"toFix":v2,"toRestore":v3,"quality":v4});
			else
				myPostJson('ajax/slas.php', 
							{"call":"updateSLA","id":id,"name":name,"toReact":v1,"toFix":v2,"toRestore":v3,"quality":v4});
		} else if ($(this).parents('table').hasClass('slaLvlTbl')) {
			id = $(this).parents('tr').data('id');
			inp = $(this).parents('tr');
			if ((name = inp.find('.tdName').children('input').val().trim()) == '')
				return;
			v1 = inp.find('.tdDesc').children('textarea').val().trim();
			if (id == '.')
				myPostJson('ajax/levels.php', 
							{"call":"addLevel","id":inp.find('.tdId').children('select').val(),"name":name,"desc":v1});
			else
				myPostJson('ajax/levels.php', 
							{"call":"updateLevel","id":id,"newId":inp.find('.tdId').children('select').val(),"name":name,"desc":v1});
		} else if ($(this).parents('table').hasClass('servTbl')) {
			id = $(this).parents('tr').data('id');
			inp = $(this).parents('tr').find('input');
			if ((name = inp[0].value.trim()) == '' || (v1 = inp[1].value.trim()) == '')
				return;
			if (id == 0) 
				myPostJson('ajax/services.php', 
							{"call":"addService","name":name,"shortName":v1});
			else
				myPostJson('ajax/services.php', 
							{"call":"updateService","id":id,"name":name,"shortName":v1});
		} else if ($(this).parents('table').hasClass('partnerTbl')) {
			id = $(this).parents('tr').data('id');
			if ((name = $(this).parents('tr').find('input').val().trim()) == '')
				return;
			if (id == 0) 
				myPostJson('ajax/partners.php', 
							{"call":"addPartner","name":name});
			else
				myPostJson('ajax/partners.php', 
							{"call":"updatePartner","id":id,"name":name});
		} else if ($(this).parents('table').hasClass('caTbl')) {
			id = $(this).parents('tr').data('id');
			if ((name = $(this).parents('tr').find('input').val().trim()) == '')
				return;
			if (id == 0) 
				myPostJson('ajax/contragents.php', 
							{"call":"addCa","name":name});
			else
				myPostJson('ajax/contragents.php', 
							{"call":"updateCa","id":id,"name":name});
		} else if ($(this).parents('table').hasClass('usersTbl')) {
			id = $(this).parents('tr').data('id');
			inp = $(this).parents('tr').find('input');
			if ((v1 = inp[0].value.trim()) == '' || (v2 = inp[1].value.trim()) == '' || (v3 = inp[2].value.trim()) == '' ||
				(v4 = inp[3].value.trim()) == '' || (v5 = inp[4].value.trim()) == '' || (v6 = inp[5].value.trim()) == '' ||
				(v7 = inp[6].value.trim()) == '')
				return;
			if (id == 0) 
				myPostJson('ajax/users.php', {"call":"addUser","familyName":v1,"givenName":v2,"middleName":v3,"login":v4,
							"rights":$('#rights').val(),"email":v5,"phone":v6,"address":v7,"partner":$('#org').val()});
			else
				myPostJson('ajax/users.php', {"call":"updateUser","id":id,"familyName":v1,"givenName":v2,
							"middleName":v3,"login":v4,"rights":$('#rights').val(),"email":v5,"phone":v6,"address":v7,"partner":$('#org').val()});
		} else if ($(this).parents('table').hasClass('contractsTbl')) {
			id = $(this).parents('tr').data('id');
			inp = $(this).parents('tr').find('input');
			name = $(this).parents('tr').find('textarea');
			if ((v1 = inp[0].value.trim()) == '' || (v7 = inp[3].value.trim()) == '' || (v8 = inp[4].value.trim()) == '' || 
				(v2 = $('#contragent').val()) == '')
				return;
			v3 = inp[1].value.trim();
			v4 = inp[2].value.trim();
			v5 = name[0].value.trim();
			v6 = name[1].value.trim();
			v9 = '';
			name = '';
			$('input[name="userId"]:checked').each(function() {
				v9 += name+$(this).val();
				name = '|';
			});
			if (id == 0) 
				myPostJson('ajax/contracts.php', {"call":"addContract","number":v1,"ca":v2,"email":v3,"phone":v4,
							"address":v5,"yurAddress":v6,"start":v7,"end":v8,"users":v9});
			else
				myPostJson('ajax/contracts.php', {"call":"updateContract","id":id,"number":v1,"ca":v2,"email":v3,"phone":v4,
							"address":v5,"yurAddress":v6,"start":v7,"end":v8,"users":v9});
		} else if ($(this).parents('table').hasClass('divsTbl')) {
			id = $(this).parents('tr').data('id');
			inp = $(this).parents('tr').find('input');
			name = $(this).parents('tr').find('textarea');
			if ((v1 = inp[0].value.trim()) == '' || (v2 = $('#contragent').val()) == '')
				return;
			v3 = inp[1].value.trim();
			v4 = inp[2].value.trim();
			v5 = name[0].value.trim();
			v6 = name[1].value.trim();
			v9 = '';
			name = '';
			$('input[name="userId"]:checked').each(function() {
				v9 += name+$(this).val();
				name = '|';
			});
			if (id == 0) 
				myPostJson('ajax/divisions.php', {"call":"addDivision","name":v1,"ca":v2,"email":v3,"phone":v4,
							"address":v5,"yurAddress":v6,"users":v9,"contractId":$('#divContract').val(),"type":$('#type').val()});
			else
				myPostJson('ajax/divisions.php', {"call":"updateDivision","id":id,"name":v1,"ca":v2,"email":v3,"phone":v4,
							"address":v5,"yurAddress":v6,"users":v9,"contractId":$('#divContract').val(),"type":$('#type').val()});
		} else if ($(this).parents('table').hasClass('contSrvTbl')) {
			myPostJson('ajax/contsrvs.php', {"call":"addService","id":$('#addServ').val(),"contractId":$('#srvContract').val()});
		} else if ($(this).parents('table').hasClass('divSlaTbl')) {
			id = $(this).parents('tr').data('id');
			if (id == 0) 
				myPostJson('ajax/divslas.php', {"call":"addDivSla","levelId":$('#level').val(),"slaId":$('#sla').val(),"divisionId":$('#slaDivision').val()});
			else
				myPostJson('ajax/divslas.php', {"call":"updateDivSla","levelId":id,"slaId":$('#sla').val(),"divisionId":$('#slaDivision').val()});
		} else if ($(this).parents('table').hasClass('divPartnerTbl')) {
			myPostJson('ajax/divpartners.php', {"call":"addDivPartner","partnerId":$('#partnerDivision').val(),"divisionId":$('#partnerDivision').val()});
		} else if ($(this).parents('table').hasClass('divEqTbl')) {
			id = $(this).parents('tr').data('id');
			if ((v2 = $('#serial').val().trim()) == '' || (v3 = $('#mfg').val().trim()) == '' || (v4 = $('#model').val().trim()) == '')
				return;
			if (id == 0) {
				if ((id = $('#servNumber').val().trim()) == '')
					return;
				myPostJson('ajax/divequip.php', {"call":"addEquip","divisionId":$('#eqDivision').val(),"servNum":id,"serial":v2,"mfg":v3,"model":v4,"warrEnd":$('#warrEnd').val().trim(),"remark":$('#remark').val().trim()});
			} else
				myPostJson('ajax/divequip.php', {"call":"updateEquip","divisionId":$('#eqDivision').val(),"servNum":id,"serial":v2,"mfg":v3,"model":v4,"warrEnd":$('#warrEnd').val().trim(),"remark":$('#remark').val().trim()});
		} else if ($(this).parents('table').hasClass('divTypesTbl')) {
			id = $(this).parents('tr').data('id');
			if ((v2 = $('#typeName').val().trim()) == '')
				return;
			if (id == 0)
				myPostJson('ajax/divisionTypes.php', {"call":"addType","name":v2,"comment":$('#typeComment').val().trim()});
			else
				myPostJson('ajax/divisionTypes.php', {"call":"updateType","id":id,"name":v2,"comment":$('#typeComment').val().trim()});
		} else if ($(this).parents('table').hasClass('contSlaTbl')) {
			inp = $(this).parents('tr').find('input');
			if ((v2 = inp.eq(0).val().trim()) == '' || (v3 = inp.eq(1).val().trim()) == '' || (v4 = inp.eq(2).val().trim()) == '' ||
				(v5 = inp.eq(3).val().trim()) == '' || (v6 = inp.eq(4).val().trim()) == '' || (v7 = inp.eq(5).val().trim()) == '')
				return;
			if (!inp.eq(6).prop('checked') && !inp.eq(7).prop('checked'))
				return;
			console.log(inp.eq(6)+' : '+inp.eq(6).prop('checked')+inp.eq(6).checked);
			myPostJson('ajax/contsla.php', {"call":"changeSla","contractId":$('#divSlaContract').val(),"srvId":$('#service').val(),
						"typeId":$('#divType').val(),"slaLevel":$('#slaLevel').val(),"toReact":v2,"toFix":v3,"toRepair":v4,"quality":v5,
						"startDay":v6,"endDay":v7,"workDays":(inp.eq(6).prop('checked') ? 1 : 0),
						"weekends":(inp.eq(7).prop('checked') ? 1 : 0),"isDefault":(inp.eq(8).prop('checked') ? 1 : 0)});
		}
	});

	$('#content').on('click', 'span.ui-icon-clipboard', function() {
		var v1, v2, v3;
		var row = $(this).parents('table').find('tr').last().find('td');
		if ($(this).parents('table').hasClass('contSlaTbl')) {
			$(this).parents('tr').find('td').each(function(i) {
				if (i == 0)
					row.eq(i).html("<span class='ui-icon ui-icon-check'> </span><span class='ui-icon ui-icon-closethick'> </span>");
				else if (i == 1) {
					v1 = $(this).data('id'); 
					row.eq(i).html("<select id='service'></select>");
				} else if (i == 2) {
					v2 = $(this).data('id'); 
					row.eq(i).html("<select id='divType'></select>");
				} else if (i == 3) {
					v3 = $(this).data('id'); 
					row.eq(i).html("<select id='slaLevel'></select>");
				} else if (i == 10 || i == 11 || i == 12) {
					row.eq(i).html("<input type='checkbox'>");
					row.eq(i).find('input').prop('checked', $(this).find('input').prop('checked'));
				}
				else if (i != 13) {
					row.eq(i).html("<input type='text'>");
					row.eq(i).find('input').val($(this).text());
				}
			});
			myPostJson('ajax/contsla.php', {"call":"fillSelects","srvId":v1,"typeId":v2,"slaLevel":v3});
		}
	});
	
	$('#content').on('click', 'span.ui-icon-locked, span.ui-icon-unlocked', function() {
		if ($(this).parents('table').hasClass('usersTbl')) {
			myPostJson('ajax/users.php', 
				{"call":"switchUserLock","id":$(this).parents('tr').data('id')});
		}
	});

	$('#content').on('click', 'span.ui-icon-key', function() {
		if ($(this).parents('table').hasClass('usersTbl')) {
			if (!confirm('Сменить пароль пользователя "'+$(this).parents('tr').find('td')[1].textContent+' '+
														$(this).parents('tr').find('td')[2].textContent+' '+
														$(this).parents('tr').find('td')[3].textContent+'"?'))
				return;
			myPostJson('ajax/users.php', 
				{"call":"changePwd","id":$(this).parents('tr').data('id'),
				 "login":$(this).parents('tr').find('td')[4].textContent.trim()});
		}
	});
	
	$('#content').on('click', 'span.ui-icon-gear', function() {
		if ($(this).parents('table').hasClass('divEqTbl'))
			myPostJson('ajax/divequip.php', {"call":"onoffEquip","servNum":$(this).parents('tr').data('id'),"divisionId":$('#eqDivision').val(),"state":1});
	});
	
	$('#content').on('click', 'span.ui-icon-cancel', function() {
		if ($(this).parents('table').hasClass('divEqTbl'))
			myPostJson('ajax/divequip.php', {"call":"onoffEquip","servNum":$(this).parents('tr').data('id'),"divisionId":$('#eqDivision').val(),"state":0});
	});
	
	$('#content').on('change', '#mdSubType, #mdMfg', function() {
		myPostJson('ajax/eqmodels.php', {"call":"change","subType":$('#mdSubType').val(),"mfg":$('#mdMfg').val()});
	});
	
	$('#content').on('change', '#rights', function() {
		if ($(this).val() == 'partner')
			myPostJson('ajax/users.php', {"call":"partnersList"}, function() {
				var old = $('#oldOrg').val();
				$('#org option').each(function(){
					if ($(this).checked)
						$(this).removeAttr('checked');
					if ($(this).text() == old)
						$(this).attr('checked', 'checked');
				});
			});
		else
			$('#org').html('');
	});
	
	$('#content').on('change', '.contEqSlaCheck', function() {
		myPostJson('ajax/conteqsla.php', {"call":"onoffScl","contractId":$('#eqSlaContract').val(),"code":$(this).parent().attr('id'),"value":($(this).prop('checked') ? 1 : 0)});
	});
	
	$('#content').on('change', '#divContract', function() {
		myPostJson('ajax/divisions.php', {"call":"selectContract","contractId":$('#divContract').val()});
	});

	$('#content').on('change', '#divSlaContract', function() {
		myPostJson('ajax/contsla.php', {"call":"selectContract","contractId":$('#divSlaContract').val()});
	});

	$('#content').on('change', '#srvContract', function() {
		myPostJson('ajax/contsrvs.php', {"call":"selectContract","contractId":$('#srvContract').val()});
	});

	$('#content').on('change', '#slaDivision', function() {
		$('#contract').text($('#slaDivision option:selected').parent().attr('label'));
		myPostJson('ajax/divslas.php', {"call":"selectDivision","divisionId":$('#slaDivision').val()});
	});

	$('#content').on('change', '#partnerDivision', function() {
		$('#contract').text($('#partnerDivision option:selected').parent().attr('label'));
		myPostJson('ajax/divpartners.php', {"call":"selectDivision","divisionId":$('#partnerDivision').val()});
	});

	$('#content').on('change', '#eqDivision', function() {
		$('#contract').text($('#eqDivision option:selected').parent().attr('label'));
		myPostJson('ajax/divequip.php', {"call":"selectDivision","divisionId":$('#eqDivision').val()});
	});

	$('#content').on('change', '#eqSlaContract', function() {
		myPostJson('ajax/conteqsla.php', {"call":"selectContract","contractId":$('#eqSlaContract').val()});
	});
	
	$('#content').on('click', '.shiftYear', function() {
		myPostJson('ajax/calendar.php', {"call":"setYear","year":$(this).data("year")});
	});
	
	$('#content').on('click', '.monthTbl td', function() {
		var self = this;
		myPostJson('ajax/calendar.php', {"call":"change","year":$("#calendar").data("year"),
				   "month":$(this).parents(".calMonth").data("id"),"day":$(this).text()}, function(data) {
				   		if (typeof data.ok !== 'undefined') {
				   			$(self).removeClass('work').removeClass('weekend').addClass(data.ok);
				   		}
				   });
	});
});
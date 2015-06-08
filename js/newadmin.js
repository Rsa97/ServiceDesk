var iconClass = {	expand:		'ui-icon-folder-collapsed',
					collapse:	'ui-icon-folder-open',
					edit:		'ui-icon-pencil',
					del:		'ui-icon-trash',
					add:		'ui-icon-plusthick',
					apply:		'ui-icon-check',
					cancel:		'ui-icon-closethick',
					moveRight:	'ui-icon-seek-next',
					moveLeft:	'ui-icon-seek-prev',
					doc:		'ui-icon-document',
					copy:		'ui-icon-clipboard',
					serviceOn:	'ui-icon-gear',
					serviceOff:	'ui-icon-cancel'
};
var icon = {};
for (var i in iconClass) {
	icon[i] = '<span class="ui-icon '+iconClass[i]+'"></span>';
}

var tableDefs = {
	services: {
		hasIcons: ['a','d','e'],
		fields: [
			{name: 'name', header: 'Название', type: 'text', test: /\S+/, val: /^\s*(\S.*\S)\s*$/, placeholder: 'Название услуги', width: '60%'},
			{name: 'shortName', header: 'Сокращение', type: 'text', test: /\S+/, val: /^\s*(\S.*\S)\s*$/, width: '25%'},
			{name: 'utility', header: 'Служебная', type: 'check', width: '10%'}
		],
		ajax: 'ajax/adm_services.php',
		pageSize: 20
	},
	partners: {
		hasIcons: ['a','d','e'],
		fields: [
			{name: 'name', header: 'Партнёр', type: 'text', test: /\S+/, val: /^\s*(\S.*\S)\s*$/, width: '20%'},
			{name: 'address', header: 'Адрес', type: 'multitext', width: '20%', val: /^\s*(\S.*\S)\s*$/},
			{name: 'users', header: 'Работники', type: 'multilist', width: '15%'},
			{name: 'contracts', header: 'Обслуживаемые договоры', type: 'multilist', width: '40%'}
		],
		ajax: 'ajax/adm_partners.php',
		pageSize: 20
	},
	contragents: {
		hasIcons: ['a','d','e'],
		fields: [
			{name: 'name', header: 'Контрагент', type: 'text', test: /\S+/, val: /^\s*(\S.*\S)\s*$/, width: '95%'}
		],
		ajax: 'ajax/adm_contragents.php',
		pageSize: 20
	},
	divisionTypes: {
		hasIcons: ['a','d','e'],
		fields: [
			{name: 'name', header: 'Название', type: 'text', test: /\S+/, val: /^\s*(\S.*\S)\s*$/, width: '30%'},
			{name: 'comment', header: 'Примечание', type: 'text', val: /^\s*(\S.*\S)\s*$/, width: '65%'}
		],
		ajax: 'ajax/adm_divisionTypes.php',
		pageSize: 20
	},
	users: {
		hasIcons: ['a','d','e'],
		customIcons: [
			{name: 'state', value: 'unlocked', icon: 'ui-icon-unlocked', call: 'lock'},
			{name: 'state', value: 'locked', icon: 'ui-icon-locked', call: 'unlock'},
			{name: 'changePass', value: 1, icon: 'ui-icon-key', call: 'changePass', confirm: "Сменить пароль пользователя?"},
		],
		fields: [
			{name: 'fn', header: 'Фамилия', type: 'text', test: /\S+/, val: /^\s*(\S.*\S)\s*$/, width: '6%'},
			{name: 'gn', header: 'Имя', type: 'text', test: /\S+/, val: /^\s*(\S.*\S)\s*$/, width: '6%'},
			{name: 'mn', header: 'Отчество', type: 'text', val: /^\s*(\S.*\S)\s*$/, width: '6%'},
			{name: 'login', header: 'Логин', type: 'text', test: /\S+/, val: /^\s*(\S.*\S)\s*$/, width: '6%'},
			{name: 'rights', header: 'Права', type: 'fixedlist', width: '9%', options: [
				{value: 'client', text: 'Клиент'},
				{value: 'admin', text: 'Администратор'},
				{value: 'engeneer', text: 'Инженер'},
				{value: 'operator', text: 'Оператор'},
				{value: 'partner', text: 'Партнёр'}
			]},
			{name: 'email', header: 'E-mail', type: 'text', test: /\S+[@]\S+\.\S+/, val: /^\s*(\S.*\S)\s*$/, width: '12%'},
			{name: 'phone', header: 'Телефон', type: 'text', val: /^\s*(\S.*\S)\s*$/, width: '13%'},
			{name: 'address', header: 'Адрес', type: 'multitext', rows: 3, val: /^\s*(\S.*\S)\s*$/, width: '17%'},
			{name: 'partner', header: 'Партнёр', type: 'list', width: '18%'}
		],
		ajax: 'ajax/adm_users.php',
		pageSize: 20
	},
	contractSLA: {
		hasIcons: ['a','d','e','c'],
		fields: [
			{name: 'service', header: 'Сервис', type: 'list', width: '9%'},
			{name: 'divType', header: 'Тип филиала', type: 'list', width: '9%'},
			{name: 'SLA', header: 'Уровень SLA', type: 'fixedlist', width: '9%', options: [
				{value: 'critical', text: 'Критический'},
				{value: 'high', text: 'Высокий'},
				{value: 'medium', text: 'Стандартный'},
				{value: 'low', text: 'Низкий'}
			]},
			{name: 'toReact', header: 'Время реакции', type: 'text', test: /(\d+\s+d)?\s*(\d+\s+h)?\s*(\d+\s+m)?/, val: /^\s*(\S.*\S)\s*$/, width: '8%'},
			{name: 'toFix', header: 'Время до восстановления', type: 'text', test: /(\d+\s+d)?\s*(\d+\s+h)?\s*(\d+\s+m)?/, val: /^\s*(\S.*\S)\s*$/, width: '8%'},
			{name: 'toRepair', header: 'Время до завершения', type: 'text', test: /(\d+\s+d)?\s*(\d+\s+h)?\s*(\d+\s+m)?/, val: /^\s*(\S.*\S)\s*$/, width: '8%'},
			{name: 'quality', header: 'Качество', type: 'text', val: /^\s*(\S.*\S)\s*$/, width: '8%'},
			{name: 'dayStart', header: 'Начало дня', type: 'text', test: /\d{1,2}:\d{1,2}/, val: /^\s*(\S.*\S)\s*$/, width: '7%'},
			{name: 'dayEnd', header: 'Конец дня', type: 'text', test: /\d{1,2}:\d{1,2}/, val: /^\s*(\S.*\S)\s*$/, width: '7%'},
			{name: 'workdays', header: 'Рабочие дни', type: 'check', width: '7%', canChange: function(self) {
				if ($(self).parents('tbody').find('.'+iconClass.cancel).length > 0) {
					$(self).removeProp('checked');
					return false;
				}
				var row = $(self).parents('tr');
				var id = row.data('id');
				if (id == 0) {
					$(self).removeProp('checked');
					return false;
				}
				if (!$(self).prop('checked'))
					return true;
				var serv = row.find('td:eq(1)').text();
				var divType = row.find('td:eq(2)').text();
				var sla = row.find('td:eq(3)').data('value');
				var data = $(self).parents('table').data('data');
				for (var i = 0; i < data.length; i++) {
					if (data[i].id != id && data[i].fields[0] == serv && data[i].fields[1] == divType && 
						data[i].fields[2] == sla && data[i].fields[9] == 1) {
						$(self).removeProp('checked');
					 	return false;	
					}
				}
				return true;
			}},
			{name: 'weekdays', header: 'Выходные', type: 'check', width: '7%', canChange: function(self){
				if ($(self).parents('tbody').find('.'+iconClass.cancel).length > 0) {
					$(self).removeProp('checked');
					return false;
				}
				var row = $(self).parents('tr');
				var id = row.data('id');
				if (id == 0) {
					$(self).removeProp('checked');
					return false;
				}
				if (!$(self).prop('checked'))
					return true;
				var serv = row.find('td:eq(1)').text();
				var divType = row.find('td:eq(2)').text();
				var sla = row.find('td:eq(3)').data('value');
				var data = $(self).parents('table').data('data');
				for (var i = 0; i < data.length; i++) {
					if (data[i].id != id && data[i].fields[0] == serv && data[i].fields[1] == divType && 
						data[i].fields[2] == sla && data[i].fields[10] == 1) {
						$(self).removeProp('checked');
					 	return false;	
					}
				}
				return true;
			}},
			{name: 'default', header: 'По умолчанию', type: 'check', width: '7%', canChange: function(self) {
				if ($(self).parents('tbody').find('.'+iconClass.cancel).length > 0) {
					$(self).removeProp('checked');
					return false;
				}
				var row = $(self).parents('tr');
				var id = row.data('id');
				if (id == 0) {
					$(self).removeProp('checked');
					return false;
				}
				if (!$(self).prop('checked'))
					return true;
				var serv = row.find('td:eq(1)').text();
				var divType = row.find('td:eq(2)').text();
				var data = $(self).parents('table').data('data');
				for (var i = 0; i < data.length; i++) {
					if (data[i].id != id && data[i].fields[0] == serv && data[i].fields[1] == divType && 
						data[i].fields[11] == 1) {
						$(self).removeProp('checked');
					 	return false;	
					}
				}
				return true;
			}},
		],
		ajax: 'ajax/adm_divSLA.php',
		pageSize: 20
	},
	divisionEquipment: {
		hasIcons: ['a','d','e'],
		customIcons: [
			{name: 'onService', value: 1, icon: iconClass.serviceOff, call: 'serviceOff'},
			{name: 'onService', value: 0, icon: iconClass.serviceOn, call: 'serviceOn'}
		],
		fields: [
			{name: 'serviceNum', header: 'Сервисный номер', type: 'text', test: /\S+/, val: /^\s*(\S.*\S)\s*$/, width: '10%'},
			{name: 'serial', header: 'Серийный номер', type: 'text', width: '15%'},
			{name: 'model', header: 'Модель', type: 'autocomplete', ajax: 'ajax/ac_model', width: '30%', limitToList: true},
			{name: 'warrEnd', header: 'Окончание гарантии', type: 'date', width: '10%'},
			{name: 'comment', header: 'Комментарий', type: 'text', width: '30%'}
		],
		ajax: 'ajax/adm_divisionEq.php',
		pageSize: 20,
		onUpdate: function(data) {
			if (typeof data.count !== 'undefined') {
				var opt = $('#selDivision option:selected');
				opt.text(opt.data('text')+' '+data.count);
			}
		}
	},
	divisionPlanned: {
		hasIcons: ['a','d','e'],
		fields: [
			{name: 'service', header: 'Услуга', type: 'list', width: '20%', onChange: function() {
				inp = $(this).parent('td').next().children('select');
				var val = inp.data('val');
				inp.html('');
				myJson($(this).parents('table').data('def').ajax, {call: 'getlists', id: $(this).parents('tr').data('id'), 
						field: 'sla', service: $(this).val()}, function(data) {
					for (var i = 0; i < data.options.length; i++) {
						inp.append('<option value="'+data.options[i].id+'"'+
							(data.options[i].name == val ? ' selected' : '')+
							(typeof data.options[i].mark !== 'undefined' ? ' class="'+data.options[i].mark+'"' : '')+
							'>'+data.options[i].name);
					}
					inp.trigger('change');
				});
			}},
			{name: 'sla', header: 'Уровень', type: 'list', width: '15%', externalFill: true},
			{name: 'problem', header: 'Задача', type: 'multitext', width: '25%'},
			{name: 'nextDate', header: 'Следующий выезд', type: 'date', width: '10%'},
			{name: 'interval', header: 'Интервал', type: 'text', test: /(\d+\s+y)?\s*(\d+\s+m)?\s*(\d+\s+w)?\s*(\d+\s+d)?/, val: /^\s*(\S.*\S)\s*$/, width: '10%', placeholder: '## y ## m ## w ## d'},
			{name: 'preStart', header: 'Ранний выезд, дней', type: 'text', test: /\d+/, val: /(\d+)/, width: '10%'}				
		],
		ajax: 'ajax/adm_divisionPlanned.php',
		pageSize: 20
	}
};

function myJson(url, param, onReady, onError, onAlways) {
	$.post(url, param, 'json')
		.done(function(data) {
			if (data !== null) {
				if (typeof data.error !== 'undefined') {
					alert(data.error);
	    	  		if (typeof onError === 'function')
    	    			onError();
				} else { 
					if (typeof data.message !== 'undefined')
						alert(data.message);
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

function makeRowEditable(row, from) {
	var def = row.parents('table').data('def');
	var src = from.children('td');
	row.children('td').each(function(i) {
		if (i == 0)
			$(this).html(icon.apply+icon.cancel);
		else {
			var val = (from.data('id') == 0 ? '' : $(src[i]).text());
			switch(def.fields[i-1].type) {
				case 'autocomplete':
					$(this).html('<input type="text" name="'+def.fields[i-1].name+'" class="dynTableInput">');
					var inp = $(this).children('input');
					inp.autocomplete({
						minLength: 0,
						position: {collision: 'flip', within: 'body'},
						source: function(request, response) {
							$.post(def.fields[i-1].ajax, {term: request.term}, 'json')
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
					});
					inp.val(val);
					break;
				case 'date':
					$(this).html('<input type="text" name="'+def.fields[i-1].name+'" class="dynTableInput">');
					var inp = $(this).children('input');
					inp.datepicker({
						changeMonth: true,
						numberOfMonths: 1,
						dateFormat: 'd MM yy',
						constrainInput: false
					});
					inp.val(val);
					break;
				case 'fixedlist':
					var select = '<select class="dynTableInput">';
					val = $(src[i]).data('value');
					for (var j = 0; j < def.fields[i-1].options.length; j++) {
						select += '<option value="'+def.fields[i-1].options[j].value+'"'+
							(def.fields[i-1].options[j].value == val ? ' selected' : '')+'>'+def.fields[i-1].options[j].text;
					}
					select += '</select>';
					$(this).html(select);
					$(this).children('select').width($(this).width-4);
					break;
				case 'list':
					$(this).html('<select class="dynTableInput"></select>');
					var inp = $(this).children('select');
					if (typeof def.fields[i-1].onChange === 'function') {
						inp.change(def.fields[i-1].onChange);
					}
					inp.width($(this).width-4);
					inp.data('val', val);
					inp.html('');
					if (typeof def.fields[i-1].externalFill === 'undefined' || !def.fields[i-1].externalFill)
						myJson(def.ajax, {call: 'getlists', id: $(this).parents('tr').data('id'), field: def.fields[i-1].name}, function(data) {
							for (var i = 0; i < data.options.length; i++) {
								inp.append('<option value="'+data.options[i].id+'"'+
									(data.options[i].name == val ? ' selected' : '')+
									(typeof data.options[i].mark !== 'undefined' ? ' class="'+data.options[i].mark+'"' : '')+
									'>'+data.options[i].name);
							}
							inp.trigger('change');
						});
					break;
				case 'multitext':
					$(this).html('<textarea class="dynTableInput" rows="'+def.fields[i-1].rows+'">');
					var inp = $(this).children('textarea');
					inp.val(val);
					inp.width($(this).width-4);
					break;
				case 'text':
					$(this).html('<input type="text" name="'+def.fields[i-1].name+'" class="dynTableInput">');
					var inp = $(this).children('input');
					inp.val(val);
					if (typeof def.fields[i-1].placeholder !== 'undefined')
						inp.attr('placeholder', def.fields[i-1].placeholder);
					inp.width($(this).width-4);
					break;
			}
		}
	});
}

function updateRow(row, parentSelector) {
	var def = row.parents('table').data('def');
	var data = {call: 'update', id: row.data('id')};
	var ok = true;
	var err = '';
	row.children('td').each(function(i) {
		if (i == 0)
			return;
		var field = $(this).children('.dynTableInput').val();
		if (typeof def.fields[i-1].val !== 'undefined') {
			var filtered = def.fields[i-1].val.exec(field); 
			field = (filtered == null ? '' : filtered[1]);  
		}
		if (typeof def.fields[i-1].test !== 'undefined') {
			var re = def.fields[i-1].test;
			if (!re.test(field)) {
				ok = false;
				err = def.fields[i-1].header;
			}
		}
		switch(def.fields[i-1].type) {
			case 'date':
				data[def.fields[i-1].name] = $.datepicker.formatDate('yy-mm-dd', $.datepicker.parseDate('d MM yy', field)); 
				break;
			default:
				data[def.fields[i-1].name] = field; 
				break;
		}
	});
	if (!ok) {
		alert('Неверное значение "'+err+'"');
		return;
	}
	myJson(def.ajax, data, function(data) {
		var pageSize = (typeof def.pageSize !== 'undefined' ? def.pageSize : 100);
		var page = (typeof data.last === 'undefined' ? 0 : Math.floor(data.last/pageSize));
		if (typeof def.onUpdate == 'function')
			def.onUpdate(data);
		drawTable(data.table, def, parentSelector, page);
	});
}

function listsMove(from, to) {
	to.children(':selected').removeAttr('selected');
	if (to.children('optgroup').length == 0 && from.children('optgroup').length == 0) {
		from.children(':selected').each(function() {
			var id = $(this).val();
			var name = $(this).text();
			var _class = $(this).attr('class');
			if (_class != '')
				_class = ' class="'+_class+'"';
			var ins = 0;
			to.children('option').each(function() {
				if ($(this).text() > name) {
					$(this).before('<option value="'+id+'" selected'+_class+'>'+name);
					ins = 1;
					return false;
				}
			});
			if (ins == 0)
				to.append('<option value="'+id+'" selected'+_class+'>'+name);
			$(this).remove();
		});
	} else {
		from.find(':selected').each(function() {
			var groupName = $(this).parents('optgroup').attr('label').substr(2);
			var id = $(this).val();
			var name = $(this).text();
			var ins = 0;
			var _class = $(this).attr('class');
			if (_class != '')
				_class = ' class="'+_class+'"';
			var group;
			to.children('optgroup').each(function() {
				var label = $(this).attr('label').substr(2);
				if (label < groupName)
					return;
				if (label == groupName) {
					group = $(this);
					group.children('option').show();
				} else {
					$(this).before('<optgroup>');
					group = $(this).prev('optgroup');
				}
				ins = 1;
				return false;
			});
			if (ins == 0) {
				to.append('<optgroup>');
				group = to.children('optgroup').last();
			}
			group.attr('label', '− '+groupName);
			group.removeClass('collapsed').addClass('expanded');
			ins = 0;
			group.children('option').each(function() {
				if ($(this).text() > name) {
					$(this).before('<option value="'+id+'" selected'+_class+'>'+name);
					ins = 1;
					return false;
				}
			});
			if (ins == 0)
				group.append('<option value="'+id+'" selected'+_class+'>'+name);
			$(this).remove();
		});
		from.children('optgroup').each(function() {
			if ($(this).children('option').length == 0)
				$(this).remove();
		});
	}			
}

function editMultiList(ajax, field, rowId, confirm) {
	if ($('#listEdit').length == 0) {
		$('body').append('<div id="listEdit" style="display:none"><table class="invis"><tr>'+
						 '<td>Общий список<select class="total" multiple></select></td>'+
						 '<td>'+icon.moveRight+'<br><br>'+icon.moveLeft+'</td>'+
						 '<td>Выбраны<select class="selected" multiple></select></td>'+
						 '</tr></table></div>');
		$('#listEdit').dialog({autoOpen: false,
                     resizable: false,
                     dialogClass: 'no-close',
                     width: 660,
                     modal: true,
                     draggable: false
    	});
    	$('#listEdit .'+iconClass.moveRight).click(function() {
    		listsMove($('#listEdit .total'), $('#listEdit .selected'));
    	});
    	
    	$('#listEdit .'+iconClass.moveLeft).click(function() {
    		console.log('left');
    		listsMove($('#listEdit .selected'), $('#listEdit .total'));
    	});
   		$('#listEdit').on('click', 'option', function(event) {
   			event.stopPropagation();
   		});
   		$('#listEdit').on('click', 'optgroup', function() {
   			if ($(this).hasClass('collapsed')) {
   				$(this).attr('label', '−'+$(this).attr('label').substr(1)).removeClass('collapsed').addClass('expanded');
   				$(this).children('option').show();
   			} else {
   				$(this).attr('label', '+'+$(this).attr('label').substr(1)).removeClass('expanded').addClass('collapsed');
   				$(this).children('option').hide();
   			}
   		});
	}
	$('#listEdit').dialog('option', 'buttons', [
		{text: 'Принять',
		 click: function() { 
			var list = [];
			$('#listEdit .selected option').each(function() {
            	list.push($(this).val());
			});
			confirm(list);
           	$(this).dialog("close");
		}},
        {text: 'Отменить', 
			click: function() { 
            	$(this).dialog("close");
		}}]);
	if ($('#listEdit').dialog('isOpen'))
		return;
	$('#listEdit .total').html('');
    $('#listEdit .selected').html('');
    $('.total').width($('.total').parents('td').width-2);
    $('.selected').width($('.selected').parents('td').width-2);
    $('#listEdit').dialog('open');
    $('#listEdit button').prop('disabled');
    myJson(ajax, {call: 'getlists', id: rowId, field: field}, function(data) {
    	if (typeof data.mode === 'undefined' || data.mode == 'flat') {
	    	var list = '';
    		for (var i = 0; i < data.total.length; i++)
    			list += '<option value="'+data.total[i].id+'"'+
    					(typeof data.total[i].mark === 'undefined' ? '' : ' class="'+data.total[i].mark+'"')+
    					'>'+data.total[i].name;
    		$('#listEdit .total').html(list);
    		list = '';
    		for (var i = 0; i < data.selected.length; i++)
    			list += '<option value="'+data.selected[i].id+'"'+
    					(typeof data.selected[i].mark === 'undefined' ? '' : ' class="'+data.selected[i].mark+'"')+
    					'>'+data.selected[i].name;
    		$('#listEdit .selected').html(list);
    	} else {
	    	var list = '';
    		for (var i = 0; i < data.total.length; i++) {
    			list += '<optgroup label="+&nbsp;'+data.total[i].group+'">';
    			for (var j = 0; j < data.total[i].items.length; j++)
    				list += '<option value="'+data.total[i].items[j].id+'"'+
    					(typeof data.total[i].items[j].mark === 'undefined' ? '' : ' class="'+data.total[i].items[j].mark+'"')+
    					'>'+data.total[i].items[j].name;
    		}
    		$('#listEdit .total').html(list);
    		$('#listEdit .total option').hide();
    		$('#listEdit .total optgroup').addClass('collapsed');
	    	var list = '';
    		for (var i = 0; i < data.selected.length; i++) {
    			list += '<optgroup label="− '+data.selected[i].group+'">';
    			for (var j = 0; j < data.selected[i].items.length; j++)
    				list += '<option value="'+data.selected[i].items[j].id+'"'+
    					(typeof data.selected[i].items[j].mark === 'undefined' ? '' : ' class="'+data.selected[i].items[j].mark+'"')+
    					'>'+data.selected[i].items[j].name;
    		}
    		$('#listEdit .selected').html(list);
    		$('#listEdit .selected optgroup').addClass('expanded');
    	}
		$('#listEdit button').removeProp('disabled');
    }, function() {
    	$('#listEdit').dialog('close');
    });
    
}

function drawTable(data, def, parentSelector, page) {
	var icons = '';
	var iconEdit = '';
	var iconDel = '';
	var iconCopy = '';
	var pageSize = (typeof def.pageSize !== 'undefined' ? def.pageSize : 100);
	var from = page*pageSize;
	var maxPage = 0;
	var pager = '';
	maxPage = Math.floor(data.length/pageSize);
	if (page > maxPage)
		page = maxPage;
	pager = '<div class="pager">';
	if (page > 3)
		pager += '<span class="page">1</span>...';
	for (var i = page-3; i < page+3; i++) {
		if (i == page)
			pager += '<span class="curPage">'+(1+i)+'</span>';
		else if(i >= 0 && i <= maxPage)
			pager += '<span class="page">'+(1+i)+'</span>';
	}
	if (page < maxPage-3)
		pager += '...<span class="page">'+(1+maxPage)+'</span>';
	pager += '</div>';
	var tbl = '<table><thead><tr>';
	if (typeof def.hasIcons !== 'undefined') {
		tbl += '<th>';
		icons = '<td style="text-align:center;">';
		if (def.hasIcons.indexOf('e') != -1)
			iconEdit = icon.edit;
		if (def.hasIcons.indexOf('d') != -1)
			iconDel = icon.del;
		if (def.hasIcons.indexOf('c') != -1)
			iconCopy = icon.copy;
	}
	for (var fieldNum in def.fields) {
		var field = def.fields[fieldNum];
		tbl += '<th'+(typeof field.width !== 'undefined' ? ' style="width:'+field.width+'"' : '')+'>'; 
		if (typeof field.header !== 'undefined')
			tbl += field.header;
	}
	tbl += '<tbody>';
	var maxRow = (page+1)*pageSize;
	if (maxRow > data.length)
		maxRow = data.length;
	for (var rowNum = page*pageSize; rowNum < maxRow; rowNum++) {
		var _class = (typeof data[rowNum].last !== 'undefined' ? 'last' : '');
		_class += (typeof data[rowNum].mark !== 'undefined' ? (_class == '' ? '' : ' ')+data[rowNum].mark : '');
		tbl += '<tr data-id="'+data[rowNum].id+'"'+(_class != '' ? ' class="'+_class+'"' : '')+'>'+
				icons+(typeof data[rowNum].notEdit === 'undefined' ? iconEdit : '')+(typeof data[rowNum].notDel === 'undefined' ? iconDel : '')+iconCopy;
		if (typeof def.customIcons !== 'undefined') {
			for (var i = 0; i < def.customIcons.length; i++) {
				if (typeof def.customIcons[i].name === 'undefined' || typeof def.customIcons[i].value === 'undefined' ||
					(typeof data[rowNum][def.customIcons[i].name] !== 'undefined' && 
					data[rowNum][def.customIcons[i].name] == def.customIcons[i].value))
					tbl += '<span class="ui-icon '+def.customIcons[i].icon+'" data-call="'+def.customIcons[i].call+'" data-confirm="'+
							(typeof def.customIcons[i].confirm !== 'undefined' ? def.customIcons[i].confirm : '')+'"></span>';
			}
		}
		for (var fieldNum = 0; fieldNum < data[rowNum].fields.length; fieldNum++) {
			switch(def.fields[fieldNum].type) {
				case 'date':
					tbl += '<td>';
					if (data[rowNum].fields[fieldNum] != '' && data[rowNum].fields[fieldNum] != '0000-00-00')
						tbl += $.datepicker.formatDate('d MM yy', $.datepicker.parseDate('yy-mm-dd', data[rowNum].fields[fieldNum]));
					break; 
				case 'multilist':
					tbl += '<td><table class="invis"><tr><td class="listIcon">'+
						   '<span class="ui-icon '+iconClass.doc+'" data-field="'+def.fields[fieldNum].name+'"></span><td>'+
							data[rowNum].fields[fieldNum]+'</table>';
							
					break;
				case 'fixedlist':
					tbl += '<td data-value="'+data[rowNum].fields[fieldNum]+'">';
					for (var i = 0; i < def.fields[fieldNum].options.length; i++) {
						if (def.fields[fieldNum].options[i].value == data[rowNum].fields[fieldNum])
							tbl += def.fields[fieldNum].options[i].text;
					}
					break;
				case 'check':
					tbl += '<td><input type="checkbox" value="1" class="'+def.fields[fieldNum].name+'Check" data-name="'+
							def.fields[fieldNum].name+'"'+(data[rowNum].fields[fieldNum] == 1 ? ' checked' : '')+'>';
					break;
				case 'autocomplete':
				case 'multitext':
				case 'text':
				case 'custom':
				default:
					tbl += '<td>'+data[rowNum].fields[fieldNum];
					break;
			}
		}
	}
	if (typeof def.hasIcons !== 'undefined' && def.hasIcons.indexOf('a') != -1)
		tbl += '<tr data-id="0"><td style="text-align:center;">'+icon.add+'<td>Добавить';
	for (var i = 1; i < def.fields.length; i++)
		tbl += '<td>';
	tbl += '</table>';
	$(parentSelector).html(pager+tbl+pager);
	tbl = $(parentSelector+' table');
	tbl.data('data', data);
	tbl.data('def', def);

	for (var fieldNum in def.fields) {
		if (def.fields[fieldNum].type == 'check') {
			var canChange = '';
			if (typeof def.fields[fieldNum].canChange == 'function')
				canChange = def.fields[fieldNum].canChange;
			$(parentSelector+' .'+def.fields[fieldNum].name+'Check').change(function() {
				if (typeof canChange !== 'function' || canChange(this)) {
					var check = !$(this).prop('checked');
					var self = this;
					myJson(def.ajax, {call: 'setCheck', field: $(this).data('name'), id: $(this).parents('tr').data('id'), 
							value: ($(this).prop('checked') ? 1 : 0)}, function(data) {
								console.log(data + (typeof data.ok) + check);
								if (typeof data.ok === 'undefined' || data.ok != 1) {
									if (check)
										$(self).prop('checked', true);
									else
										$(self).removeProp('checked');
								}
							}, function() {
								console.log(check, $(self));
								if (check)
									$(self).prop('checked', true);
								else
									$(self).removeProp('checked');
							});
				}
			});
		}
	}

	if (typeof def.customIcons !== 'undefined') {
		for (var i = 0; i < def.customIcons.length; i++) {
			tbl.on('click', '.'+def.customIcons[i].icon, function() {
				if ($(this).data('confirm') == '' || confirm($(this).data('confirm'))) {
					var page = $('.curPage').first().text()-1;
					var tbl = $(parentSelector+' table');
					myJson(def.ajax, {call: $(this).data('call'), id: $(this).parents('tr').data('id')}, function(data) {
						drawTable(data.table, def, parentSelector, page);
					});
				}
			});
		}
	}
	
	tbl.on('click', '.'+iconClass.add+', .'+iconClass.edit, function() {
		if ($(parentSelector+' .'+iconClass.cancel).length == 0)
			makeRowEditable($(this).parents('tr'), $(this).parents('tr'));
	});

	tbl.on('click', '.'+iconClass.copy, function() {
		if ($(parentSelector+' .'+iconClass.cancel).length > 0)
			return;
		makeRowEditable($(this).parents('tr').siblings().last(), $(this).parents('tr'));
	});

	tbl.on('click', '.'+iconClass.apply, function() {
		updateRow($(this).parents('tr'), parentSelector);
	});

	tbl.on('click', '.'+iconClass.cancel, function() {
		var tbl = $(parentSelector+' table');
		drawTable(tbl.data('data'), tbl.data('def'), parentSelector, $('.curPage').first().text()-1);
	});

	tbl.on('click', '.'+iconClass.del, function() {
		if (!confirm("Удалить?"))
			return;
		var page = $('.curPage').first().text()-1;
		var tbl = $(parentSelector+' table');
		myJson(def.ajax, {call: 'del', id: $(this).parents('tr').data('id')}, function(data) {
			if (typeof def.onUpdate == 'function')
				def.onUpdate(data);
			drawTable(data.table, def, parentSelector, page);
		});
	});
	
	tbl.on('click', '.'+iconClass.doc, function() {
		if ($(parentSelector+' .'+iconClass.cancel).length != 0)
			return;
		var field = $(this).data('field');
		var rowId = $(this).parents('table').parents('tr').data('id'); 
		editMultiList(def.ajax, field, rowId, function(list) {
			myJson(def.ajax, {call: 'updatelists', id: rowId, 
							  field: field, list: list}, function(data) {
            	var pageSize = (typeof def.pageSize !== 'undefined' ? def.pageSize : 100);
				var page = (typeof data.last === 'undefined' ? 0 : Math.floor(data.last/pageSize));
				drawTable(data.table, def, parentSelector, page);
			});
		});
	});

  	$('.pager').on('click', '.page', function() {
		var tbl = $(parentSelector+' table');
  		drawTable(tbl.data('data'), tbl.data('def'), parentSelector, $(this).text()-1);
  	});
}

function initTable(def, parentSelector) {
	myJson(def.ajax, {call: 'init'}, function(data) {
		drawTable(data.table, def, parentSelector, 0);
	});
}

function setEqTreeDragDrop(parentSelector) {
	$(parentSelector+' .eqTypes li.drag').draggable({
		axis: 'y',
		opacity: 0.35,
		revert: 'invalid',
		revertDuration: 100,
		delay: 100
	});
	
	$(parentSelector+' .eqTypes li.eqType.collapsed').droppable({
		accept: function(what) {
			return ($(this).data('id') != 0 && what.hasClass('eqSubType') && what.parents('li').first().data('id') != $(this).data('id'));
		},
		drop: function(e, ui) {
			if(!confirm('Переместить подтип?')) {
				$(ui.helper).css('top', 0);
				return;
			}
			myJson('ajax/adm_eqmodels.php', {call: 'moveSubtype', type: $(e.target).data('id'), sub: $(ui.draggable).data('id')},
				function(data) {
					if (typeof data.ok !== 'undefined' && data.ok == 1) {
						var name = $(ui.helper).children('.subTypeName').text();
						var tgt = $(e.target);
						var par = $(ui.helper).parent('ul');
						tgt.children('ul').children('li').each(function() {
							if ($(this).data('id') == 0 || name < $(this).children('.subTypeName').text()) {
								$(this).before($(ui.helper));
								$(ui.helper).css('top', 0);
								$('.eqTypes').find('.last').removeClass('last');
								$(ui.helper).addClass('last');
								if (par.children('li').length == 1)
									par.parent().children('.icons3').append(icon.del);
								tgt.find('span .'+iconClass.del).remove();
								if (tgt.hasClass('collapsed')) {
									tgt.find('.'+iconClass.expand).removeClass(iconClass.expand).addClass(iconClass.collapse);
									tgt.removeClass('collapsed').addClass('expanded');
									tgt.children('ul').show();
								}
								return false;
							}
						});
					} else {
						$(ui.helper).css('top', 0);
					}
				}, function() {
					$(ui.helper).css('top', 0);
				});
		}
	});

	$(parentSelector+' .eqTypes li.eqSubType.collapsed').droppable({
		accept: function(what) {
			return ($(this).data('id') != 0 && what.hasClass('eqMfgModel') && what.parents('li').first().data('id') != $(this).data('id'));
		},
		drop: function(e, ui) {
			if(!confirm('Переместить модель?')) {
				$(ui.helper).css('top', 0);
				return;
			}
			myJson('ajax/adm_eqmodels.php', {call: 'moveModel', sub: $(e.target).data('id'), model: $(ui.draggable).data('id')},
				function(data) {
					if (typeof data.ok !== 'undefined' && data.ok == 1) {
						var name = $(ui.helper).children('.eqMfg').text()+' '+$(ui.helper).children('.eqModel').text();
						var tgt = $(e.target);
						var par = $(ui.helper).parent('ul');
						tgt.children('ul').children('li').each(function() {
							if ($(this).data('id') == 0 || name < $(this).children('.eqMfg').text()+' '+$(this).children('.eqModel').text()) {
								$(this).before($(ui.helper));
								$(ui.helper).css('top', 0);
								$('.eqTypes').find('.last').removeClass('last');
								$(ui.helper).addClass('last');
								if (par.children('li').length == 1)
									par.parent().children('.icons3').append(icon.del);
								tgt.find('span .'+iconClass.del).remove();
								if (tgt.hasClass('collapsed')) {
									tgt.find('.'+iconClass.expand).removeClass(iconClass.expand).addClass(iconClass.collapse);
									tgt.removeClass('collapsed').addClass('expanded');
									tgt.children('ul').show();
								}
								return false;
							}
						});
					} else {
						$(ui.helper).css('top', 0);
					}
				}, function() {
					$(ui.helper).css('top', 0);
				});
		}

	});
} 

function initEqTree(data, parentSelector) {
	$(parentSelector).html('<ul class="eqTypes"></ul>');
	var tree = $(parentSelector).children('ul');
	var subtypeNum, modelNum, type, subtype;
	for(var i = 0; i < data.types.length; i++) {
		subtypeNum = (typeof data.types[i].subtypes !== 'undefined' ? data.types[i].subtypes.length : 0);
		tree.append('<li class="eqType collapsed" data-id="'+data.types[i].id+'"><span class="icons3">'+icon.expand+icon.edit+
					(subtypeNum == 0 ? icon.del : '')+'</span> <span class="typeName">'+data.types[i].name+'</span><ul class="eqSubTypes"></ul>');
		type = tree.children('li').last().children('ul').first();
		for (var j = 0; j < subtypeNum; j++) {
			modelNum = (typeof data.types[i].subtypes[j].models !== 'undefined' ? data.types[i].subtypes[j].models.length : 0);
			type.append('<li class="eqSubType collapsed drag" data-id="'+data.types[i].subtypes[j].id+'"><span class="icons3">'+icon.expand+icon.edit+
						(modelNum == 0 ? icon.del : '')+'</span> <span class="subTypeName">'+data.types[i].subtypes[j].name+'</span><ul class="eqModels"></ul>');
			subtype = type.children('li').last().children('ul');
			for (var k = 0; k < modelNum; k++) {
				subtype.append('<li class="eqMfgModel drag" data-id="'+data.types[i].subtypes[j].models[k].id+'"><span class="icons3">'+icon.edit+
								(typeof data.types[i].subtypes[j].models[k].notDel == 'undefined' ? icon.del : '')+
								'</span> <span class="eqMfg">'+data.types[i].subtypes[j].models[k].mfg+'</span> '+
								'<span class="eqModel">'+data.types[i].subtypes[j].models[k].model+"</span>");
			}
			subtype.append('<li class="eqMfgModel" data-id="0"><span class="icons3">'+icon.add+'</span> <span class="eqMfg">Добавить</span> <span class="eqModel"></span>');
		}
		type.append('<li class="eqSubType" data-id="0"><span class="icons3">'+icon.add+'</span> <span class="subTypeName">Добавить</span>');
	}
	tree.append('<li class="eqType" data-id="0"><span class="icons3">'+icon.add+'</span> <span class="typeName">Добавить</span>');
	tree.find('ul').hide();
	
	$(parentSelector).on('click', '.eqTypes li .'+iconClass.expand, function(event) {
		event.stopPropagation();
		$(this).removeClass(iconClass.expand).addClass(iconClass.collapse);
		$(this).parents('li').first().removeClass('collapsed').addClass('expanded');
		$(this).parent().siblings('ul').show();
	});

	$(parentSelector).on('click', '.eqTypes li .'+iconClass.collapse, function(event) {
		event.stopPropagation();
		if ($(this).parent().siblings('ul').find('.'+iconClass.cancel).length > 0)
			return;
		$(this).removeClass(iconClass.collapse).addClass(iconClass.expand);
		$(this).parents('li').first().removeClass('expanded').addClass('collapsed');
		$(this).parent().siblings('ul').hide();
	});
	
	setEqTreeDragDrop(parentSelector);
	
	$(parentSelector).on('click', '.eqTypes li .'+iconClass.edit, function(event) {
		event.stopPropagation();
		if ($(parentSelector+' .'+iconClass.cancel).length > 0)
			return false;
		$(parentSelector+' .eqTypes li.drag').draggable('destroy');
		var li = $(this).parent().parent();
		var name = $(this).parent().next();
		var icons = name.siblings('.icons3');
		icons.data('old', icons.html());
		icons.html(icon.apply+icon.cancel); 
		if (li.hasClass('eqType') || li.hasClass('eqSubType')) {
			var val = name.text();
			name.html('<input class="listInput">');
			name.data('old', val);
			name.children('input').val(val);
		} else if (li.hasClass('eqMfgModel')) {
			var mfg = name.text();
			var name2 = name.siblings('.eqModel');
			var model = name2.text();
			name.html('<input class="listInput" placeholder="Производитель">');
			name.data('old', mfg);
			name.children('input').val(mfg).autocomplete({
				minLength: 0,
				position: {collision: 'flip', within: 'body'},
				source: function(request, response) {
							$.post('ajax/ac_mfg.php', {term: request.term}, 'json')
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
			});
			name2.html('<input class="listInput" placeholder="Модель">');
			name2.children('input').val(model);
			name2.data('old', model);
		}
		return false;
	});

	$(parentSelector).on('click', '.eqTypes li .'+iconClass.add, function(event) {
		event.stopPropagation();
		if ($(parentSelector+' .'+iconClass.cancel).length > 0)
			return false;
		$(parentSelector+' .eqTypes li.drag').draggable('destroy');
		var li = $(this).parent().parent();
		var name = $(this).parent().next();
		var icons = name.siblings('.icons3');
		icons.html(icon.apply+icon.cancel);
		if (li.hasClass('eqType') || li.hasClass('eqSubType')) {
			icons.data('old', icon.expand+icon.edit+icon.del);
			name.html('<input class="listInput">');
		} else if (li.hasClass('eqMfgModel')) {
			icons.data('old', icon.edit+icon.del);
			name.html('<input class="listInput" placeholder="Производитель">');
			name.children('input').autocomplete({
				minLength: 0,
				position: {collision: 'flip', within: 'body'},
				source: function(request, response) {
							$.post('ajax/ac_mfg.php', {term: request.term}, 'json')
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
			});
			name.siblings('.eqModel').html('<input class="listInput" placeholder="Модель">');
		}
		return false;
	});
	
	$(parentSelector).on('click', '.eqTypes li .'+iconClass.cancel, function(event) {
		event.stopPropagation();
		var li = $(this).parent().parent();
		var name = $(this).parent().next();
		var icons = name.siblings('.icons3');
		if (li.data('id') == 0) {
			icons.html(icon.add);
			name.html('Добавить');
			if (li.hasClass('eqMfgModel'))
				name.siblings('.eqModel').html('');
		} else {
			icons.html(icons.data('old'));
			name.text(name.data('old'));
			if (li.hasClass('eqMfgModel')) {
				var name2 = name.siblings('.eqModel');
				name2.text(name2.data('old'));
			}
		}
		setEqTreeDragDrop(parentSelector);
		return false;
	});

	$(parentSelector).on('click', '.eqTypes li .'+iconClass.apply, function(event) {
		event.stopPropagation();
		var li = $(this).parent().parent();
		var inp = $(this).parent().next().children('input');
		var name = inp.val().trim();
		var icons = $(this).parent().siblings('.icons3');
		if (name == '') {
			alert('Не указан '+(li.hasClass('eqType') ? 'тип' : (li.hasClass('eqSubType') ? 'подтип' : 'производитель')));
			return;
		}
		var name2 = '';
		if (li.hasClass('eqMfgModel')) {
			var inp2 = $(this).parent().siblings('.eqModel').find('input');
			var name2 = inp2.val().trim();
			if (name2 == '') {
				alert('Не указана модель');
				return;
			}
		}
		var req = {call: 'update', id: li.data('id')};
		if (li.hasClass('eqType')) {
			req.type = 'type';
			req.name = name;
		} else if (li.hasClass('eqSubType')) {
			req.type = 'subType';
			req.parent = li.parents('li.eqType').data('id');
			req.name = name;
		} else if (li.hasClass('eqMfgModel')) {
			req.type = 'model';
			req.parent = li.parents('li.eqSubType').data('id');
			req.mfg = name;
			req.model = name2;
		}
		myJson('ajax/adm_eqmodels.php', req, function(data) {
			if (typeof data.id !== 'undefined' && data.id != 0) {
				var ul = li.parent();
				li.detach();
				li.children('.icons3').html(li.children('.icons3').data('old'));
				if (li.data('id') == 0) {
					li.addClass('collapsed');
					if (li.hasClass('eqType'))
						ul.append('<li class="eqType" data-id="0"><span class="icons3">'+icon.add+'</span> <span class="typeName">Добавить</span>');
					else if (li.hasClass('eqSubType'))
						ul.append('<li class="eqSubType" data-id="0"><span class="icons3">'+icon.add+'</span> <span class="subTypeName">Добавить</span>');
					else if (li.hasClass('eqMfgModel'))
						ul.append('<li class="eqMfgModel" data-id="0"><span class="icons3">'+icon.add+'</span> <span class="eqMfg">Добавить</span> <span class="eqModel"></span>');
				}
				if (li.hasClass('eqType')) {
					li.children('.typeName').text(name);
					if (li.children('ul').length == 0) {
						li.append('<ul class="eqSubTypes"><li class="eqSubType" data-id="0"><span class="icons3">'+icon.add+
									'</span> <span class="subTypeName">Добавить</span></ul>');
						li.children('ul').hide();
					}
				}
				else if (li.hasClass('eqSubType')) {
					li.children('.subTypeName').text(name);
					if (li.children('ul').length == 0) {
						li.append('<ul class="eqModels"><li class="eqMfgModel" data-id="0"><span class="icons3">'+icon.add+
									'</span> <span class="eqMfg">Добавить</span> <span class="eqModel"></span></ul>');
						li.children('ul').hide();
					}
				}
				else if (li.hasClass('eqMfgModel')) {
					li.children('.eqMfg').text(name);
					li.children('.eqModel').text(name2);
					name += ' '+name2;
				}
				ul.children('li').each(function() {
					var t = ($(this).hasClass('eqType') ? $(this).find('.typeName').text() : 
								($(this).hasClass('eqSubType') ? $(this).find('.subTypeName').text() : 
					 				$(this).find('.eqMfg').text()+' '+$(this).find('.eqModel').text()));
					if ($(this).data('id') == 0 || name < t) {
						$(this).before(li);
						return false;
					}
				});
				li.data('id', data.id);
				$(parentSelector+' .last').removeClass('last');
				li.addClass('last');
				setEqTreeDragDrop(parentSelector);
			}
		});
	});
	
	$(parentSelector).on('click', '.eqTypes li .'+iconClass.del, function(event) {
		event.stopPropagation();
		if ($(parentSelector+' .'+iconClass.cancel).length > 0)
			return false;
		if (!confirm('Удалить?'))
			return false;
		var li = $(this).parent().parent();
		var req = {call: 'del', id: li.data('id')};
		if (li.hasClass('eqType'))
			req.type = 'type';
		else if (li.hasClass('eqSubType'))
			req.type = 'subType';
		else if (li.hasClass('eqMfgModel'))
			req.type = 'model';
		myJson('ajax/adm_eqmodels.php', req, function(data) {
			if (typeof data.ok !== 'undefined' && data.ok == 1)
				li.remove();
		});
	});
}

function initContracts() {
	
	$('.contractIcons').html(icon.add+icon.edit+icon.del);
	$('#editCUsers').addClass(iconClass.doc);
	$('.divisionIcons').html(icon.add+icon.edit+icon.del);
	$('#editDUsers').addClass(iconClass.doc);
	$('#editDPartners').addClass(iconClass.doc);
						
	myJson('ajax/adm_contracts', {call: 'getlists', field: 'contragents', id: 1}, function(data) {
		if (typeof data.list !== 'undefined') {
			var opt = '';
			for (var i = 0; i < data.list.length; i++) {
				opt += '<option value="'+data.list[i].id+'"'+(i == 0 ? ' selected' : '')+'>'+data.list[i].name;
			}
			$('#selContragent').html(opt).trigger('change');
		}
	});
}	

$(function() {
	$('.hideonstart').hide();
	
	$.datepicker.setDefaults({ monthNames: ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'],
			       monthNamesShort: ['Янв', 'Фев', 'Мар', 'Апр', 'Май', 'Июн', 'Июл', 'Авг', 'Сен', 'Окт', 'Ноя', 'Дек'],
			       dayNamesMin: ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'],
			       firstDay: 1});	
	$('#contract').tabs({active: 0});			       
	$('#division').tabs({active: 0});

	$('button').button();
	$('button').each(function() {
	if ($(this).data('icon') != '')
		$(this).button('option', 'icons', {primary: $(this).data('icon')});
	});
	$('#refresh').button('option', 'text', false);

	$("#desktop").click(function() {
		location.replace('desktop.html');
	});
	
	$('#logout').click(function() {
    	myJson('/ajax/login.php', {Op: 'out'});
  	});

	
	$('#cStartIn').datepicker({
			changeMonth: true,
			numberOfMonths: 1,
			dateFormat: 'd MM yy',
			altField: '#cStartInt',
			altFormat: 'yy-mm-dd',
			constrainInput: false,
			onClose: function(selectedDate) {
			    if ($('#cStartIn').val() == '')
					$("#cStartInt").val('');;
	    		$('#cEndIn').datepicker('option', 'minDate', selectedDate);
	    		if ($('#cEndIn').val() == '')
					$('#cEndIn').datepicker('setDate', $('#cStartIn').datepicker('getDate'));
			}
	});

	$('#cEndIn').datepicker({
			changeMonth: true,
			numberOfMonths: 1,
			dateFormat: 'd MM yy',
			altField: '#cEndInt',
			altFormat: 'yy-mm-dd',
			constrainInput: false,
			onClose: function(selectedDate) {
			    if ($('#cEndIn').val() == '')
					$("#cEndInt").val('');;
	    		$('#cStartIn').datepicker('option', 'maxDate', selectedDate);
	    		if ($('#cStartIn').val() == '')
					$('#cStartIn').datepicker('setDate', $('#cEndIn').datepicker('getDate'));
			}
	});

	myJson('ajax/userName.php', {});

	$('#content').on('click', '.shiftYear', function() {
		myJson('ajax/adm_calendar.php', {call: 'setYear', year: $(this).data('year')}, function(data) {
			if (typeof data.content !== 'undefined')
				$('#content').html(data.content);
		});
	});

	$('#content').on('click', '.monthTbl td', function() {
		var self = this;
		myJson('ajax/adm_calendar.php', {call: 'change', year: $("#calendar").data('year'),
				month: $(this).parents('.calMonth').data('id'), day: $(this).text()}, function(data) {
			if (typeof data.ok !== 'undefined')
				$(self).removeClass('work').removeClass('weekend').addClass(data.ok);
		});
	});
		
	$('.menuLink').click(function() {
		if ($('#contracts .'+iconClass.cancel).length > 0 || $('#content .'+iconClass.cancel).length > 0)
			return;
		$('.menuLink').removeClass('menuActive');
		$(this).addClass('menuActive');
		$('#content').hide();
		$('#contracts').hide();
		switch($(this).data('page')) {
			case 'calendar':
				$('#content').show();
				myJson('ajax/adm_calendar.php', {"call":"init"}, function(data) {
					if (typeof data.content !== 'undefined')
						$('#content').html(data.content);
				});
				break;
			case 'eqmodels':
				$('#content').show();
				myJson('ajax/adm_eqmodels.php', {"call":"init"}, function(data) {
					initEqTree(data, '#content');
				});
				break;
			case 'services':
			case 'partners':
			case 'contragents':
			case 'divisionTypes':
			case 'users':
				$('#content').show();
				initTable(tableDefs[$(this).data('page')], '#content');
				break;
			case 'contracts':
				$('#contracts').show();
				initContracts();
				break;
		}
  	});

	$('#selContragent').change(function() {
		myJson('ajax/adm_contracts', {call: 'getlists', field: 'contracts', id: $(this).val()}, function(data) {
			if (typeof data.list !== 'undefined') {
				var opt = '';
				for (var i = 0; i < data.list.length; i++)
					opt += '<option value="'+data.list[i].id+'"'+(i == 0 ? ' selected' : '')+
							(typeof data.list[i].mark !== 'undefined' ? ' class="'+data.list[i].mark+'"' : '')+'>'+data.list[i].name;
				$('#selContract').html(opt).trigger('change');
			}
		});
	});
	
	$('#selContract').change(function() {
		$('#contract').tabs('option', 'active', 0);
		if ($(this).children('option').length == 0) {
			$('.contractIcons').html(icon.add);
			$('#contMain span.edit').text('');
			$('#contract').tabs('option', 'disabled', [1, 2]);
			return;
		}
		$('#contract').tabs('option', 'disabled', false);
		myJson('ajax/adm_contracts', {call: 'getlists', field: 'contract', id: $(this).val()}, function(data) {
			if (typeof data.notDel !== 'undefined' && data.notDel == 1)
				$('.contractIcons').html(icon.add+icon.edit);
			else 
				$('.contractIcons').html(icon.add+icon.edit+icon.del);
			for (var key in data.main)
            	if(data.main.hasOwnProperty(key)) {
            		if ($('#'+key).hasClass('date')) {
            			$('#'+key).text($.datepicker.formatDate('d MM yy', $.datepicker.parseDate('yy-mm-dd', data.main[key])));
            		} else 
            			$('#'+key).text(data.main[key]);
            	}
			if (typeof data.early !== 'undefined' && data.early == 1)
				$('#cStart').addClass('red');
			else 
				$('#cStart').removeClass('red');
			if (typeof data.late !== 'undefined' && data.late == 1)
				$('#cEnd').addClass('red');
			else 
				$('#cEnd').removeClass('red');
			var opt = '';
            if (typeof data.list !== 'undefined') {
				for (var i = 0; i < data.list.length; i++)
					opt += '<option value="'+data.list[i].id+'"'+(i == 0 ? ' selected' : '')+
							(typeof data.list[i].mark !== 'undefined' ? ' class="'+data.list[i].mark+'"' : '')+
							' data-text="'+data.list[i].name+'">'+data.list[i].name+' '+data.list[i].count;
				$('#selDivision').html(opt).trigger('change');
			}
		});
		tableDefs['contractSLA'].ajax = 'ajax/adm_contractSLA.php?contId='+$(this).val();
		initTable(tableDefs['contractSLA'], '#contSLA');
	}); 
	
	$('#editCUsers').click(function() {
		if ($('#contracts .'+iconClass.cancel).length > 0)
			return;
		editMultiList('ajax/adm_contracts', 'users', $('#selContract').val(), function(list) {
			myJson('ajax/adm_contracts', {call: 'updatelists', field: 'users', id: $('#selContract').val(), list: list}, function(data) {
				$('#cUsers').html(typeof data.cUsers !== 'undefined' ? data.cUsers : '');
			});
		});
	});
	
	$('.contractIcons').on('click', '.'+iconClass.edit+',.'+iconClass.add, function() {
		if ($('#contracts .'+iconClass.cancel).length > 0)
			return;
		$('#contract').tabs('option', 'active', 0);
		$('#contract').tabs('option', 'disabled', [1, 2]);
		$('#selContragent').prop('disabled', 'disabled');
		var add = $(this).hasClass(iconClass.add);
		var val = (add ? '' : $('#selContract option:selected').text());
		$('#selContractIn').data('add', add);
		$('#selContract').hide();
		if (add)
			$('#cUsers').hide();
		$('#selContractIn').val(val).show();
		$('#contMain span.edit').each(function() {
			val = (add ? '' : $(this).text());
			var inp = $(this).siblings('input');
			$(this).hide();
			inp.show().val(val);
		});
		$('.contractIcons').data('old', $('.contractIcons').html()).html(icon.apply+icon.cancel);
	});

	$('.contractIcons').on('click', '.'+iconClass.cancel, function() {
		$('#contract').tabs('option', 'disabled', false);
		$('#selContragent').removeProp('disabled');
		$('#selContractIn').hide();
		$('#selContract').show();
		$('#contMain span.edit').siblings('input').hide();
		$('#contMain span.edit').show();
		$('#cUsers').show();
		$('.contractIcons').html($('.contractIcons').data('old'));
	});

	$('.contractIcons').on('click', '.'+iconClass.apply, function() {
		var num;
		if ((num = $('#selContractIn').val().trim()) == '') {
			alert('Не указан номер договора');
			return;
		}
		var re = /\d\d\d\d-\d\d-\d\d/; 
		if (!re.test($('#cStartInt').val()) || !re.test($('#cEndInt').val())) {
			alert('Не указан срок действия договора');
			return;
		}
		var data = {call: 'update', id: ($('#selContractIn').data('add') ? 0 : $('#selContract').val()), selContractIn: num, 
					caId: $('#selContragent').val()};
		$('#contMain span.edit').siblings('input').each(function() {
			if ($(this).hasClass('date'))
				data[$(this).attr('id')] = $($(this).datepicker('option', 'altField')).val(); 
			else
			 	data[$(this).attr('id')] = $(this).val().trim();
		});
		myJson('ajax/adm_contracts', data, function(data) {
			if (typeof data.list !== 'undefined')	{
				$('#selContragent').removeProp('disabled');
				$('#selContractIn').hide();
				$('#selContract').show();
				$('#cUsers').show();
				$('#contMain span.edit').siblings('input').hide();
				$('#contMain span.edit').val('').show();
				var opt = '';
				for (var i = 0; i < data.list.length; i++)
					opt += '<option value="'+data.list[i].id+'"'+(data.list[i].id == data.last ? ' selected' : '')+
							(typeof data.list[i].mark !== 'undefined' ? ' class="'+data.list[i].mark+'"' : '')+'>'+data.list[i].name;
				$('#contract').tabs('option', 'disabled', false);
				$('#selContract').html(opt).trigger('change');
			}
		});
	});

	$('.contractIcons').on('click', '.'+iconClass.del, function() {
		if (!confirm('Удалить договор?'))
			return;
		myJson('ajax/adm_contracts', {call: 'del', id: $('#selContract').val()}, function(data) {
			if (typeof data.ok !== 'undefined' && data.ok == 1) {
				$('#selContract option:selected').remove();
				$('#selContract').trigger('change');
			}
		});
	});
	
	$('#selDivision').change(function() {
		$('#division').tabs('option', 'active', 0);
		if ($(this).children('option').length == 0) {
			$('.divisionIcons').html(icon.add);
			$('#divMain span.edit').text('');
			$('#division').tabs('option', 'disabled', [1]);
			return;
		}
		$('#division').tabs('option', 'disabled', false);
		myJson('/ajax/adm_divisions', {call: 'init', id: $(this).val()}, function(data) {
			if (typeof data.notDel !== 'undefined' && data.notDel == 1)
				$('.divisionIcons').html(icon.add+icon.edit);
			else 
				$('.divisionIcons').html(icon.add+icon.edit+icon.del);
			for (var key in data.main)
            	if(data.main.hasOwnProperty(key)) {
           			$('#'+key).text(data.main[key]);
            	}
		});
		tableDefs['divisionEquipment'].ajax = 'ajax/adm_divisionEq.php?divId='+$(this).val();
		initTable(tableDefs['divisionEquipment'], '#divEquip');
		tableDefs['divisionPlanned'].ajax = 'ajax/adm_divisionPlanned.php?divId='+$(this).val();
		initTable(tableDefs['divisionPlanned'], '#divPlanned');
	});
	
	$('.divisionIcons').on('click', '.'+iconClass.edit+',.'+iconClass.add, function() {
		$('#contract').tabs('option', 'disabled', true);
		$('#division').tabs('option', 'active', 0);
		$('#division').tabs('option', 'disabled', [1]);
		$('#selContragent').prop('disabled', 'disabled');
		$('#selContract').prop('disabled', 'disabled');
		var add = $(this).hasClass(iconClass.add);
		var val = (add ? '' : $('#selDivision option:selected').data('text'));
		if (add) {
			$('#dUsers').hide();
			$('#dPartners').hide();
		}
		$('#selDivisionIn').data('add', add);
		$('#selDivision').hide();
		$('#selDivisionIn').val(val).show();
		$('#divMain span.edit').each(function() {
			val = (add ? '' : $(this).text());
			var inp;
			if ($(this).hasClass('list')) {
				inp = $(this).siblings('select');
				myJson('ajax/adm_divisions', {call: 'getlists', id: $('#selDivision').val(), field: $(this).attr('id')}, function(data) {
					var opt = '';
					for (var i = 0; i < data.list.length; i++)
						opt += '<option value="'+data.list[i].id+'"'+(typeof data.list[i].cur !== 'undefined' ? ' selected' : '')+
								(typeof data.list[i].mark !== 'undefined' ? ' class="'+data.list[i].mark+'"' : '')+'>'+data.list[i].name;
					inp.html(opt);
				});
			} else {
				inp = $(this).siblings('input');
			}
			$(this).hide();
			inp.show().val(val);
		});
		$('.divisionIcons').data('old', $('.divisionIcons').html()).html(icon.apply+icon.cancel);
	});

	$('.divisionIcons').on('click', '.'+iconClass.cancel, function() {
		$('#contract').tabs('option', 'disabled', false);
		$('#division').tabs('option', 'disabled', false);
		$('#selContragent').removeProp('disabled');
		$('#selContract').removeProp('disabled');
		$('#dUsers').show();
		$('#dPartners').show();
		$('#selDivision').show();
		$('#selDivisionIn').hide();
		$('#divMain span.edit').siblings('input, select').hide();
		$('#divMain span.edit').show();
		$('.divisionIcons').html($('.divisionIcons').data('old'));
	});

	$('.divisionIcons').on('click', '.'+iconClass.apply, function() {
		var num;
		if ((num = $('#selDivisionIn').val().trim()) == '') {
			alert('Не указано название филиала');
			return;
		}
		var data = {call: 'update', id: ($('#selDivisionIn').data('add') ? 0 : $('#selDivision').val()), selDivisionIn: num,
					cId: $('#selContract').val()};
		$('#divMain span.edit').siblings('input, select').each(function() {
		 	data[$(this).attr('id')] = $(this).val().trim();
		});
		myJson('ajax/adm_divisions', data, function(data) {
			if (typeof data.list !== 'undefined')	{
				$('#contract').tabs('option', 'disabled', false);
				$('#division').tabs('option', 'disabled', false);
				$('#selContragent').removeProp('disabled');
				$('#selContract').removeProp('disabled');
				$('#dUsers').show();
				$('#dPartners').show();
				$('#selDivision').show();
				$('#selDivisionIn').hide();
				$('#divMain span.edit').siblings('input, select').hide();
				$('#divMain span.edit').show();
				var opt = '';
				for (var i = 0; i < data.list.length; i++)
					opt += '<option value="'+data.list[i].id+'"'+(data.list[i].id == data.last ? ' selected' : '')+
							(typeof data.list[i].mark !== 'undefined' ? ' class="'+data.list[i].mark+'"' : '')+
							' data-text="'+data.list[i].name+'">'+data.list[i].name+' '+data.list[i].count;
				$('#selDivision').html(opt).trigger('change');
			}
		});
	});

	$('.divisionIcons').on('click', '.'+iconClass.del, function() {
		if (!confirm('Удалить филиал?'))
			return;
		myJson('ajax/adm_contracts', {call: 'del', id: $('#selDivision').val()}, function(data) {
			if (typeof data.ok !== 'undefined' && data.ok == 1) {
				$('#selDivision option:selected').remove();
				$('#selDivision').trigger('change');
			}
		});
	});
	
	$('#editDUsers').click(function() {
		if ($('#contracts .'+iconClass.cancel).length > 0)
			return;
		editMultiList('ajax/adm_divisions', 'users', $('#selDivision').val(), function(list) {
			myJson('ajax/adm_divisions', {call: 'updatelists', field: 'users', id: $('#selDivision').val(), list: list}, function(data) {
				$('#dUsers').html(typeof data.dUsers !== 'undefined' ? data.dUsers : '');
			});
		});
	});
	
	$('#editDPartners').click(function() {
		if ($('#contracts .'+iconClass.cancel).length > 0)
			return;
		editMultiList('ajax/adm_divisions', 'partners', $('#selDivision').val(), function(list) {
			myJson('ajax/adm_divisions', {call: 'updatelists', field: 'partners', id: $('#selDivision').val(), list: list}, function(data) {
				$('#dPartners').html(typeof data.dPartners !== 'undefined' ? data.dPartners : '');
			});
		});
	});
	
});

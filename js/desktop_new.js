var token = fromStorage('token', false);
if (null !== token) {
    token = token.value;
}
var firstLogin = true;

var stateToPage = {
    received:   'received',
    accepted:   'accepted',
    fixed:      'accepted',
    repaired:   'toClose',
    closed:     'closed',
    canceled:   'canceled',
    planned:    'planned'
};

var pageToState = {
    received:   'received',
    accepted:   'accepted,fixed',
    toClose:    'repaired',
    closed:     'closed',
    canceled:   'canceled',
    planned:    'planned'
};

var slaNames = {low: 'Низкий', medium: 'Средний', high: 'Высокий', critical: 'Критический'};

var pageOrders = {received: 1, accepted: 1, toClose: -1, closed: -1, canceled: -1, planned: 0};

var statusIcons = {received: {name: 'Получена', icon: 'ui-icon-mail-closed'},
    accepted:   {name: 'Принята к исполнению', icon: 'ui-icon-mail-open'},
    fixed:      {name: 'Работоспособность восстановлена', icon: 'ui-icon-wrench'},
    repaired:   {name: 'Работа завершена', icon: 'ui-icon-help'},
    closed:     {name: 'Закрыта', icon: 'ui-icon-check'},
    canceled:   {name: 'Отменена', icon: 'ui-icon-cancel'},
    planned:    {name: 'Плановая', icon: 'ui-icon-calendar'},
    onWait:     {name: 'Ожидание комплектующих', icon: 'ui-icon-clock'},
    notSync:    {name: 'Нет синхронизации с 1С', icon: 'ui-icon-alert'},
    toPartner:  {name: 'Передано партнёру', icon: 'ui-icon-arrowthick-1-e'}
};

var buttonDefs = {
    New:        {name: 'Создать', icon: 'ui-icon-document', order: 0},
    Accept:     {name: 'Принять', icon: 'ui-icon-plus', order: 1},
    Cancel:     {name: 'Отменить', icon: 'ui-icon-cancel', order: 2},
    Fixed:      {name: 'Восстановлено', icon: 'ui-icon-wrench', order: 3},
    Repaired:   {name: 'Завершено', icon: 'ui-icon-check', order: 4},
    UnClose:    {name: 'Отказать', icon: 'ui-icon-alert', order: 5},
    Close:      {name: 'Закрыть', icon: 'ui-icon-closethick', order: 6},
    UnCancel:   {name: 'Открыть повторно', icon: 'ui-icon-notice', order: 7},
    Wait:       {name: 'Ожидание', icon: 'ui-icon-clock', order: 8},
    DoNow:      {name: 'Выполнить сейчас', icon: 'ui-icon-extlink', order: 9},
    AddProblem: {name: 'Добавить примечание', icon: 'ui-icon-info', order: 10}
};

// Служебные функции
/**
 * Объединение массивов без дубликатов
 * 
 * @param array array2 Добавляемый массив
 * 
 * @result array union Объединённый массив
 */
Array.prototype.union = function(array2) {
    return array2.reduce(function (acc, cur) {
        if (acc.indexOf(cur) == -1) {
            return acc.concat([cur])
        } else {
            return acc
        }
    }, this);
}

if (!Object.keys) {
    Object.keys = function(o) {
        if (o !== Object(o)) {
            throw new TypeError('Object.prototype.keys called on a non-object');
        }
        var k=[], p;
        for (p in o) {
            if (Object.prototype.hasOwnProperty.call(o, p)) {
                k.push(p);
            }
        }
        return k;
    }
}

if (!Object.count) {
    Object.count = function(o) {
        if (o !== Object(o)) {
            throw new TypeError('Object.prototype.count called on a non-object');
        }
        var c = 0, p;
        for (p in o) {
            if (Object.prototype.hasOwnProperty.call(o, p)) {
                c++;
            }
        }
        return c;
    }
}

/**
 * Модальное сообщение об ошибке
 * 
 * @param string text Текст сообщения
 */
function myAlert (text) {
    $.blockUI({
        message:         "<div style='padding: 1em'>" + text + "<br><input type='button' id='myAlertOk' value='Ок'></div>",
        backgroundColor: '#000',
        opacity:         0.2,
        css:             {border: '3px solid #a00'}
    });
}

// Кнопки фильтра заявок
var filterBtn = [
    {
        text:   'Принять',
        click:  function () {
                    $(this).dialog('close');
                    storeFilter();
                }
    }, {
        text:   'Сбросить',
        click:  function () {
                    resetFilter();
                }
    }, {
        text:   'Отменить',
        click:  function () {
                    restoreFilter();
                    $(this).dialog('close');
                }
    }
];

// Кнопки карточки заявки в режиме создания
var cardBtnNew = [
    {
        text:   'Отменить',
        click:  function () {
                    $(this).dialog('close')
                }
    }, {
        text:   'Создать',
        click:  function () {
                    if ($('#division').val() == '*') {
                        myAlert('Не выбран филиал');
                        return;
                    }
                    if ($('#problem').val().trim() == '') {
                        myAlert('Не указана проблема');                            return;
                    }
                    if ($('#contact').val() == '*') {
                        myAlert('Не выбран ответственный');
                        return;
                    }
                    if ($('#service').val() == '*') {
                        myAlert('Не выбрана услуга');
                        return;
                    }  
                    myPostJson(
                        '/ajax/request/new/' + $('#division').val() + '/' + $('#service').val() + '/' + $('#level').val() + '/' + $('#contact').val(),
                        {equipment: $('#servNum').data('id'), problem: $('#problem').val().trim()},
                        function () {
                            $('#workflow').tabs('option', 'active', 0);
                            setFilter();
                            $('#card').dialog('close');
                        }
                    );
                }
    }
];

// Кнопки карточки заявки в режиме изменения
var cardBtnChange = [
    {
        text:   'Отменить',
        click:  function () {
                    $(this).dialog('close');
                }
    }, {
        text:   'Изменить',
        click:  function () {
                    serviceSet();
                }
    }
];

// Кнопки карточки заявки в режиме просмотра
var cardBtnLook = [
    {
        text:   'Сервисный лист',
        click:  function () {
                    var cell1 = $('tr#' + openCard + ' .cell1').find('.ui-icon');
                    if (cell1.hasClass('ui-icon-help') || cell1.hasClass('ui-icon-check') 
                        || cell1.hasClass('ui-icon-mail-open') || cell1.hasClass('ui-icon-wrench')) {
                        window.open('/ajax/serviceList/get/' + openCard, 'Сервисный лист');
                    }
                }
    }, {
        text:   'Закрыть',
        click:  function () {
                    $(this).dialog('close');
                }
    }
];

// Кэширование в sessionStorage
/**
 * Получить запись из локального хранилища
 * 
 * @param string name    Имя записи
 * @param bool   session true - сессионное хранилище, false - локальное
 * 
 * @return object {value: Значение, cacheInfo: Информация о кэшировании} или null
 */
function fromStorage(name, session = false) {
    var value = null;
    var data = null;
    try {
        if (session) {
            if ((data = sessionStorage.getItem(name)) !== null) {
                value = {
                    value: JSON.parse(data), 
                    cacheInfo: JSON.parse(sessionStorage.getItem(name+'_cacheInfo'))
                };
            }
        } else {
            if ((data = localStorage.getItem(name)) !== null) {
                value = {
                    value: JSON.parse(data), 
                    cacheInfo: JSON.parse(localStorage.getItem(name+'_cacheInfo'))
                };
            }
        }
     } catch (e) {
        console.log(e)
    }
//    console.log('fromStorage(', name, ')= ', value);
    return value;
}

/**
 * Записывает значение в локальное хранилище
 * 
 * @param string  name      Имя записи
 * @param variant value     Значение
 * @param string  cacheInfo Иформация о кэшировании
 * @param bool    session   true - сессионное хранилище, false - локальное
 */
function toStorage(name, value, cacheInfo, session = false) {
//    console.log('toStorage(', name, ', ', value, ', ', cacheInfo, ', ', session, ')');
    try {
        if (session) {
            sessionStorage.setItem(name, JSON.stringify(value));
            sessionStorage.setItem(name + '_cacheInfo', JSON.stringify(cacheInfo));
        } else {
            localStorage.setItem(name, JSON.stringify(value));
            localStorage.setItem(name + '_cacheInfo', JSON.stringify(cacheInfo));
        }
    } catch (e) {
        console.log(e);
    }
}

/**
 * Удаляет запись из хранилища
 * 
 * @param string name    Имя записи
 * @param bool   session true - сессионное хранилище, false - локальное
 */
function removeStorage(name, session = false) {
//    console.log('invalidateStorage(', name, ', ', session, ')');
    try {
        if (session) {
            sessionStorage.removeItem(name);
            sessionStorage.removeItem(name + '_lastModified');
    } else {
            localStorage.removeItem(name);
            localStorage.removeItem(name + '_lastModified');
    }
    } catch (e) {
        console.log(e);
    }
}

/**
 * Обновляет информацию о кэшировании записи в хранилище
 * 
 * @param string name    Имя записи
 * @param bool   session true - сессионное хранилище, false - локальное
 */
function renewStorage(name, cacheInfo, session = false) {
//    console.log('renewStorage(', name, ', ', cacheInfo, ', ', session, ')');
    try {
        if (session) {
            sessionStorage.setItem(name + '_cacheInfo', JSON.stringify(cacheInfo));
        } else {
            localStorage.setItem(name + '_cacheInfo', JSON.stringify(cacheInfo));
        }
    } catch (e) {
        console.log(e);
    }
}

// Блокировка экрана на время запроса данных с сервера
var blockNum = 0; // Счётчик блокировок
/**
 * Заблокировать экран
 */
function block() {
    if (0 == blockNum) {
        $.blockUI({
            message: '<img src="img/busy.gif"> Подождите...',
            overlayCSS: {
                backgroundColor: '#000',
                opacity: 0.2,
                cursor: 'wait'
            }
        });
    }
    blockNum++;
}
/**
 * Разблокировать экран
 */
function unblock() {
    if (0 < blockNum) {
        blockNum--;
    }
    if (0 == blockNum) {
        $.unblockUI();
    }
}

function authorization(callback) {
    unblock();
    if (!firstLogin) {
        $('#userLoginName'.prop('readonly', true));
    }
    $('#userLogin').dialog('option', 'buttons', [{
        text: 'Войти',
        click: function () {
            block();
            $.ajax({
                url: '/api/v2/auth',
                method: 'POST',
                cache: false,
                data: {name: $('#userLoginName').val().trim(),
                pass: $('#userPassword').val().trim()},
                dataType: 'json'
            }).done(function (data, status, jXHR) {
                if (200 == jXHR.status) {
                    unblock();
                    token = data;
                    toStorage('token', token, {}, false);
                    $('#userLogin').dialog('close');
                    callback();
                } else {
                    if (typeof data !== 'undefined' && data.hasOwnProperty('error')) {
                        alert(data.error);
                        $('#password').val('');
                        return;
                    } else {
                        alert('Ошибка соединения с сервером. Попробуйте перезагрузить страницу через некоторое время.');
                    }
                }
            }).fail(function (data) {
                unblock();
                alert('Ошибка соединения с сервером. Попробуйте перезагрузить страницу через некоторое время.');
            });
        }
    }]);
    $('#userLogin').dialog('open');
}

function request(method, req, headers = {}, vars = {}, onChange, onDone) {
    console.log('resuest(', method, ', ', req, ', ', headers, ', ', vars, ', ', onChange, ', ', onDone, ')');
    block();
    headers.APIKey = token;
    $.ajax({url: '/api/v2/' + req,
            method: method,
            cache: false,
            data: vars,
            dataType: 'json',
            headers: headers,
            statusCode: {
                401: function() {
                        authorization(function() { request(method, req, headers, vars, onChange, onDone); });
                    }
            }
    }).done(function(res, status, jXHR) {
        unblock();
        if (res !== null) {
            var result;
            if ('error' == res.result) {
                myAlert(res.error);
                return;
            }
            if (null !== onRequestDone) {
                result = onRequestDone(res);
            } else {
                result = res.value;
            }
            if (null !== onDone) {
                onDone(result);
            }
        }
    }).fail(function () {
        unblock();
        myAlert('Ошибка связи с сервером');
    });
}

/**
 * Получить данные с кэшированием
 * 
 * @param string   req      Запрос
 * @param object   vars     Переменные
 * @param callback onChange Вызывается, если пришли изменённые данные,
 *                          должна вернуть обновлённый объект
 * @param callback onDone   Вызывается с окончательно сформированными данными
 * @param bool     session true - сессионное хранилище, false - локальное
 */
function getCached(req, vars, onChange, onDone, session = false) {
    var query = '';
    for (var variable in vars) {
        query += ('' == query ? '?' : '&')+variable+'='+encodeURIComponent(vars[variable]);
    }
    req += query;
    var result = fromStorage(req, session);
    var headers = {};
    if (null !== result) {
        if (result.cacheInfo.hasOwnProperty('ETag')) {
            headers['If-None-Match'] = result.cacheInfo.ETag;
        } else if (result.cacheInfo.hasOwnProperty('LastModified')) {
            headers['If-Modified-Since'] = result.cacheInfo.LastModified;
        }
        result = result.value;
    }
    headers.APIKey = token;
    $.ajax({url:        '/api/v2/' + req,
            method:     'GET',
            cache:      false,
            data:       vars,
            dataType:   'json',
            headers:    headers,
            statusCode: {
                401: function() {
                        authorization(function() { getCached(req, vars, onChange, onDone, session); });
                     }
            }
    }).done(function(res, status, jXHR) {
        unblock();
        switch (jXHR.status) {
        case 200:
            result = res;
            var cacheInfo = {};
            var t = jXHR.getResponseHeader('Last-Modified');
            if (null != t) {
                cacheInfo.LastModified = t;
            } else {
                t = jXHR.getResponseHeader('ETag');
                if (null != t) {
                    cacheInfo.ETag = t;
                }                    
            }
            if (null != t) {
                toStorage(req, result, cacheInfo, session);
            }
            break;
        case 304:
            break;
        default:
            if (isset(res) && res.hasOwnProperty('error')) {
                myAlert(res.error);
                return;
            }
        }
        if (typeof(onDone) == 'function') {
            onDone(result);
        }
    }).fail(function () {
        unblock();
        myAlert('Ошибка связи с сервером');
    });
}

function put (req, vars, onDone) {
  request('PUT', req, vars, function (data) {
    var res = data.value
    res.expireTime = data.expireTime
    return res
  }, onDone)
}

function putCached (name, request, vars, value, onDone) {
  put(request, vars, function (data) {
    toStorage(name, value, data.expireTime)
    if (null !== onDone) {
      onDone(data)
    }
  })
}

// Работа с фильтром заявок
/**
 * Сохраняет фильтр в сессионном кэшеУдаляет запись из хранилища
 */
function storeFilter () {
    var filter = {
        contract: ('contract' == $('#filterDivision :selected').data('type') ? $('#filterDivision').val() : null),
        division: ('division' == $('#filterDivision :selected').data('type') ? $('#filterDivision').val() : null),
        service: ('*' == $('#filterService').val() ? null : $('#filterService').val()),
        partner: ('*' == $('#filterPartner').val() ? null : $('#filterPartner').val()),
        engineer: ('*' == $('#filterEngineer').val() ? null : $('#filterEngineer').val()),
        onlyMy: $('#ticketsMy').prop('checked'),
        text: $('#fltByText').val().trim(),
        from: ($('#useFromDate').prop('checked') ? $.datepicker.formatDate('yy-mm-dd', $('#fltByFromDate').datepicker('getDate')) : null),
        to: ($('#useToDate').prop('checked') ? $.datepicker.formatDate('yy-mm-dd', $('#fltByToDate').datepicker('getDate')) : null)
    }
    toStorage('filter', filter, {}, true);
}

/**
 * Сбрасывает фильтр на значения по умолчанию
 */
function resetFilter () {
  $('#filterDivision :selected').removeProp('selected');
  $('#filterService :selected').removeProp('selected');
  $('#partnerService :selected').removeProp('selected');
  $('#engineerService :selected').removeProp('selected');
  $('#chkMyTickets :checked').removeProp('checked');
  $('#fltByText').val('');
  $('#useFromDate').prop('checked', false);
  $('#fltByFromDate').prop('disabled', true).datepicker('setDate', '-3m');
  $('#useToDate').prop('checked', false);
  $('#fltByToDate').prop('disabled', true).datepicker('setDate', '0');
  storeFilter();
}

/**
 * Восстанавливает выбранные значения в окне фильтра
 */
function restoreFilter() {
    var filter = fromStorage('filter', true);
    if (null == filter) {
        resetFilter();
    } else {
        filter = filter.value;
        $('#filterDivision :selected').removeProp('selected');
        if (filter.hasOwnProperty('contract') && null !== filter.contract) {
            $('#filterDivision').val(filter.contract);
        }
        if (filter.hasOwnProperty('division') && null !== filter.division) {
            $('#filterDivision').val(filter.division);
        }
        $('#filterService :selected').removeProp('selected');
        if (filter.hasOwnProperty('division') && null !== filter.service) {
            $('#filterService').val(filter.service);
        }
        $('#partnerService :selected').removeProp('selected');
        if (filter.hasOwnProperty('partner') && null !== filter.partner) {
            $('#filterService').val(filter.partner);
        }
        $('#engineerService :selected').removeProp('selected');
        if (filter.hasOwnProperty('engineer') && null !== filter.engineer) {
            $('#filterService').val(data.engineer);
        }
        if (filter.hasOwnProperty('text') && null !== filter.engineer) {
            $('#fltByText').val(filter.text);
        } else {
            $('#fltByText').val('');
        }
        $('#chkMyTickets :checked').removeProp('checked');
        if (filter.hasOwnProperty('from') && null != filter.from) {
            $('#useFromDate').prop('checked', true);
            $('#fltByFromDate').prop('disabled', false).datepicker('setDate', $.datepicker.parseDate('yy-mm-dd', filter.from));
        } else {
            $('#useFromDate').prop('checked', false);
            $('#fltByFromDate').prop('disabled', true).datepicker('setDate', '-3m');
        }
        if (filter.hasOwnProperty('to') && null != filter.to) {
            $('#useToDate').prop('checked', true);
            $('#fltByToDate').prop('disabled', false).datepicker('setDate', $.datepicker.parseDate('yy-mm-dd', filter.to));
        } else {
            $('#useToDate').prop('checked', false);
            $('#fltByToDate').prop('disabled', true).datepicker('setDate', '0');
        }
        if (filter.hasOwnProperty('onlyMy') && filter.onlyMy) {
            $('#ticketsMy').prop('checked', true)
        } else {
            $('#ticketsAll').prop('checked', true)
        }
        $('#chkMyTickets').buttonset('refresh');
    }
}

/**
 * Заполняет списки фильтра
 *
 * @param object data Списки разрешенных значений фильтра
 */
function buildFilter (data) {
//    console.log(data);
    var group;
    var select = $('<select>', {class: 'ui-widget ui-corner-all ui-widget-content', id: 'filterDivision'});
    select.append($('<option>', {value: '*', text: '    --- Все ---', selected: true}));
    data.divisions.forEach(function(contragent) {;
        group = $('<optgroup>', {label: contragent.name});
        select.append(group);
        contragent.contracts.forEach(function(contract) {
            group.append($('<option>', {'data-type': 'contract', value: contract.guid, text: 'Договор ' + contract.name}));
            contract.divisions.forEach(function(division) {
                group.append($('<option>', {'data-type': 'division', value: division.guid, text: '      ' + division.name}));
            });
        });
    });
    $('#filterDivision').replaceWith(select);
    select = $('<select>', {class: 'ui-widget ui-corner-all ui-widget-content', id: 'filterService'});
    select.append($('<option>', {value: '*', text: '    --- Все ---', selected: true}));
    data.services.forEach(function(service) {
        select.append($('<option>', {value: service.guid, text: service.name}));
    });
    $('#filterService').replaceWith(select);
    select = $('<select>', {class: 'ui-widget ui-corner-all ui-widget-content', id: 'filterPartner'});
    select.append($('<option>', {value: '*', text: '    --- Все ---', selected: true}));
    if (data.hasOwnProperty('partners') && null != data.partners) {
        data.partners.forEach(function (partner) {
            select.append($('<option>', {value: partner.guid, text: partner.name}));
        });
    }
    $('#filterPartner').replaceWith(select)
    select = $('<select>', {class: 'ui-widget ui-corner-all ui-widget-content', id: 'filterEngineer'})
    select.append($('<option>', {value: '*', text: '    --- Все ---', selected: true}))
    if (data.hasOwnProperty('engineers') && null != data.engineers) {
        data.engineers.forEach(function (engineer) {
            select.append($('<option>', {value: engineer.guid, text: engineer.name}));
        });
    }
    $('#filterEngineer').replaceWith(select);
}

/**
 * Возвращает текущие значения фильтра как объект
 */
function getFilter() {
    var result = {};
    var temp = '';
    if ($('#filterDivision').val() != '*') {
        result[$('#filterDivision :selected').data('type')] = $('#filterDivision').val();
    }
    if ($('#filterService').val() != '*') {
        result.service = $('#filterService').val();
    }
    if ($('#filterPartner').val() != '*') {
        result.partner = $('#filterPartner').val();
    }
    if ($('#filterEngineer').val() != '*') {
        result.engineer = $('#filterEngineer').val();
    }
    if ($('#ticketsMy').prop('checked')) {
        result.onlyMy = 1;
    }
    if ((temp = $('#fltByText').val().trim()) != '') {
        result.text = temp;
    }
    if ($('#useFromDate').prop('checked') && $('#fltByFromDate').val().trim() != '') {
        result.from = $.datepicker.formatDate('yy-mm-dd', $('#fltByFromDate').datepicker('getDate'));
    }
    if ($('#useToDate').prop('checked') && $('#fltByToDate').val().trim() != '') {
        result.to = $.datepicker.formatDate('yy-mm-dd', $('#fltByToDate').datepicker('getDate'));
    }
// TODO: Временная строка, удалить
result.from = '2017-05-01';
//
//    console.log('filter = ', result);
    return result;
}

/*

function serviceNumSet() {
  if ($('#servNum').data('serv') != $('#servNum').val())
    myPostJson('/ajax/request/changeEq/'+openCard, {equipment: $('#servNum').data('id')}, null,
	  function() {
	    myAlert('Ошибка связи с сервером')
	  },
	  function() {
		$('#card').dialog('close')
	  })
  else
    $('#card').dialog('close')
}

function contactSet() {
  if ($('#contact').data('id') != $('#service').val())
    myPostJson('/ajax/request/contact/set/'+openCard+'/'+$('#contact').val(), null,
      function() {
        serviceNumSet()
	  },
	  function() {
	    myAlert('Ошибка связи с сервером')
	  },
	  function() {
		$('#card').dialog('close')
	  })
  else
    serviceNumSet()
}

function serviceSet() {
  if ($('#service').data('id') != $('#service').val() || $('#level').data('id') != $('#level').val()) {
  	if (0 != $('#service option:selected').data('autoonly')) {
  	  myAlert('Услуга "'+$('#service option:selected').text()+'" служебная. Измените её.')
  	  return
  	}
    myPostJson('/ajax/request/sla/set/'+openCard+'/'+$('#service').val()+'/'+$('#level').val(), null, 
	  function() {
	    contactSet()
	  },
	  function() {
	    myAlert('Ошибка связи с сервером')
	  },
	  function() {
	    $('#card').dialog('close')
	  })
  } else
	contactSet()
}

var userSetupBtn = [{text: 'Отменить',
				     click: function() {
					   $(this).dialog("close")
				     }},
				     {text: 'Изменить', 
                      click: function() {
                      	var cellPhone = $('#cellPhone').val().trim()
                      	if ('' != cellPhone && !cellPhone.match(/^\+?[78]9\d{9}$/)) {
                      		myAlert("Неверный номер сотового телефона")
                      		return
                      	}
                      	cellPhone = cellPhone.substr(-10)
                      	var jid = $('#jabberUID').val().trim()
                      	if ('' != jid && !jid.match(/^\S+@\S+/)) {
                      		myAlert("Неверный адрес Jabber")
                      		return
                      	} 
                      	var data = ''
                  	    $('#sendMethods tbody tr').each(function() {
                  	    	var evt = $(this).data('id')
                  	    	$(this).find('td').each(function() {
                  	    		if ($(this).children('input').prop('checked'))
                  	    			data += evt+','+$(this).data('id')+'|'
                  	    	})
                  	    })
                  	   	myPostJson('/ajax/user/messageConfig/set/', {cellPhone:cellPhone, jid:jid, data:data},
                  	   				null, null,
                  	   				function() {
                  	   					$('#userSetup').dialog('close')
                  	   				}
                  	   			  )
                     }}
                   ]

// Кнопки карточки заявки в режиме обмена с базой
var cardBtnWait = [{text: 'Идёт запрос'}]

// Кнопки списка оборудования
var selectEqBtn = [{text: 'Отменить', 
                   click: function() { 
                            $(this).dialog("close")
                          }}
                 ]

// Кнопки списка партнёров
var selectPartnerBtn = [{text: 'Отменить', 
        	       	     click: function() { 
                        	       $(this).dialog("close")
	                             }}
    	               ]
                 
// Кнопки добавления задач в плановые
var addProblemBtn = [{text: 'Отменить', 
                      click: function() { 
                            $(this).dialog("close")
                          }},
	                 {text: 'Сохранить',function getFilter() {
	var result = {}
	var temp = ''
	if ($('#filterDivision').val() != '*') {
		result[$('#filterDivision :selected').data('type')] = $('#filterDivision').val()
	}
	if ($('#filterService').val() != '*') {
		result['service'] = $('#filterService').val()
	}
	if ($('#ticketsMy').prop('checked')) {
		result['onlyMy'] = 1
	}
	if ((temp = $('#fltByText').val().trim()) != '') {
		result['text'] = temp
	}
	if ($('#fltByFromDate').val().trim() != '') {
		result['from'] = $.datepicker.formatDate('yy-mm-dd', $('#fltByFromDate').datepicker('getDate'))
	}
	if ($('#fltByToDate').val().trim() != '') {
		result['to'] = $.datepicker.formatDate('yy-mm-dd', $('#fltByToDate').datepicker('getDate'))
	}
	return result
}

	                  click: function() {
	                  	myPostJson('/ajax/problem/set/'+$('#apContract').val()+'/'+$('#apDivision').val(),
	                  				{problem: $('#apProblem').val().trim()}, null, null, 
	                  				function() { 
									  $('#addProblem').dialog('close')
									})
	                  }} 
                    ]
       

// Кнопки решения
var solutionBtn = [{text: 'Отменить', 
                   click: function() { 
                            $(this).dialog("close")
                          }},
                   {text: 'Принять', 
                   click: function() {
                   			var Problem = $('#solProblem').val().trim()
                   			var Solution = $('#solSolution').val().trim()
                   			var Recomend = $('#solRecomendation').val().trim()
                   			if ('' == Recomend)
                   				Recomend = 'Без рекомендаций'
                   			if (Problem.length < 10 || Solution.length < 10) {
                   				myAlert('Минимальная длина текста - 10 символов!')
                   				return
                   			}
                   			if (Problem == Solution || Problem == Recomend || Solution == Recomend) {
                   				myAlert('Тексты в полях не должны совпадать!')
                   				return
                   			}
    						myPostJson('/ajax/request/Repaired/'+$('#solution').data('id'),
    							{solProblem: Problem, sol: Solution, solRecomend: Recomend},
               					null,
               					null,
               					function() {
									$('#solution').dialog("close")
                 					setFilter()
               					})
               				}}
                 ]

function checkChanges() {
	if ('new' == cardMode)
		return
	if ($('#service').data('id') != $('#service').val() ||
		$('#contact').data('id') != $('#contact').val() || 
		$('#servNum').data('serv') != $('#servNum').val() || 
		$('#level').data('id') != $('#level').val()) 
		$('#card').dialog('option', 'buttons', cardBtnChange)
	else
		$('#card').dialog('option', 'buttons', cardBtnLook)
}

function myPostJson(url, param, onReady, onError, onAlways, nonStandard) {
  $.blockUI({message: '<img src="img/busy.gif"> Подождите...', 
  			 overlayCSS:  { 
        	  backgroundColor: '#000', 
        	  opacity:         0.2, 
        	  cursor:          'wait' 
    		 } 
    	})
  $.post(url, param, 'json')
    .done(function(data) {
      $.unblockUI()
      if (data !== null) {
        if (typeof data.error !== 'undefined') {
          myAlert(data.error)
	      if (typeof onError === 'function')
    	   	onError()
        } else {
          if (typeof nonStandard === 'function')
          	nonStandard(data)
          else
            for (var key in data)
              if(data.hasOwnProperty(key)) {
                if (key.substr(0, 1) == '_')
                  $('#'+key.substr(1)).val(data[key])
                else if (key.substr(0, 1) == '!') {
              	  if (data[key] == 1)
              	    $('#'+key.substr(1)).show()
              	  else
              	    $('#'+key.substr(1)).hide()
              	  $('#'+key.substr(1)).val(data[key])
                } else
                  $('#'+key).html(data[key])
              }
          if (typeof onReady === 'function')
            onReady(data)
        }
        if (typeof data.redirect != 'undefined')
          location.replace(data.redirect)
      }
    })
    .fail(function() {
      $.unblockUI()
      if (typeof onError === 'function')
        onError()
      else
        myAlert('Ошибка связи с сервером')
    })
    .always(function() {
      if (typeof onAlways === 'function')
        onAlways()
    })
}

// Установка фильтра на сервере
function setFilter() {
  myPostJson('/ajax/filter/set', {byDiv: $('#selectDivision :selected').val(),
                                  bySrv: $('#filterService :selected').val(),
                                  byText: $('#fltByText').val(),
                                  byFrom: $('#fltFromDateSQL').val(),
                                  byTo: $('#fltToDateSQL').val(),
                                  onlyMy: $('#chkMyTickets :checked').val()}, 
             null, null, 
             function() { 
               $('.list tr:nth-child(2n+1)').addClass('odd')
               $('.list td:nth-child(1), .list th:nth-child(1)').addClass('cell1')
               $('.list td:nth-child(2)').addClass('cell2')
               $('.list td:nth-child(9)').addClass('cell9')
             })
}

function servNumLookup() {
	myPostJson('/ajax/dir/equipment/'+$('#division').val(), {servNum: $('#selectServNum').val().trim()}, 
	function() {
	  $('#selectEqList ul ul').hide()
	  $('#selectEqList ul ul.single').show()
	},
	function() {
	  myAlert('Ошибка связи с сервером')
	  $('#selectEq').dialog('close')
	});       
  timeoutSet = 0
}

*/

function drawList(page, requests, childs) {
    console.log('drawList(', page, ',', requests, ',', childs, ')');
/*    var tbody = $('<tbody>');
    if ('planned' == page) {
        reqList.forEach(function(req, i) {
            tbody.append($('<tr>', {id: req.id})
                .append($('<td>')
                    .append($('<input>', {type: 'checkbox', class: 'checkOne'})
                        .css('display', (0 == req.status.canPreStart ? 'none' : 'inline-block'))
                        .prop('disabled', 0 == req.status.canPreStart)
                        .prop('checked', req.id == selected)))
                .append($('<td>', {text: slaNames[req.slaLevel]}))
                .append($('<td>', {text: data.services[req.service].name}))
                .append($('<td>', {text: req.nextDate}))
                .append($('<td>', {text: (req.division == req.contragent ? '' : req.division + ', ') + req.contragent}))
                .append($('<td>', {text: req.problem + (req.addProblem == '' ? '' : '\n' + req.addProblem)}))
            );
        });
    } else {
        reqList.forEach(function(req, i) {
            var tr = $('<tr>', {id: req.id, class: (req.time.toRepair.percent >= 100 ? 'timeIsOut' : 'ok'), 'data-autoonly': data.services[req.service].only_auto});
            var td = $('<td>', {class: 'cell1'});
            td.append($('<input>', {class: 'checkOne', type: 'checkbox'}))
                .prop('checked', req.id == selected);
            td.append($('<abbr>', {title: statusIcons[req.status.type].name})
                .append($('<span>', {class: 'ui-icon ' + statusIcons[req.status.type].icon}))
            );
            if (0 != req.status.onWait) {
                td.append($('<abbr>', {title: statusIcons.onWait.name})
                    .append($('<span>', {class: 'ui-icon ' + statusIcons.onWait.icon}))
                );
            }
            if (0 == req.status.sync1C) {
                td.append($('<abbr>', {title: statusIcons.sync1C.name})
                    .append($('<span>', {class: 'ui-icon ' + statusIcons.sync1C.icon}))
                );
            }
            if (0 != req.status.toPartner) {
                td.append($('<abbr>', {title: req.partner})
                    .append($('<span>', {class: 'ui-icon ' + statusIcons.sync1C.icon}))
                );
            }
            tr.append(td);
            tr.append($('<td>', {text: ('000000' + req.id).slice(-7)}));
            tr.append($('<td>', {text: slaNames[req.slaLevel]}));
            tr.append($('<td>')
                .append($('<abbr>', {title: data.services[req.service].name + '\n' + req.problem, text: data.services[req.service].shortname}))
            );
            tr.append($('<td>', {text: req.receiveTime}));
            tr.append($('<td>', {text: req.repairBefore}));
            tr.append($('<td>')
                .append($('<abbr>', {title: 'Договор ' + req.contract, text: req.contragent}))
            );
            var title = '';
            if (null !== req.contact) {
                text = 'Контактное лицо: ' + data.users[req.contact].name
                     + '\nE-mail: ' + data.users[req.contact].email
                     + '\nТелефон: ' + data.users[req.contact].phone + '\n';
            }
            tr.append($('<td>')
                .append($('<abbr>', {title: title, text: req.division}))
            );
            td = $('<td>');
            if (null !== req.engineer) {
                title = data.users[req.engineer].name 
                      + '\nE-mail: ' + data.users[req.engineer].email
                      + '\nТелефон: ' + data.users[req.engineer].phone;
                td.append($('<abbr>', {title: title, text: data.users[req.engineer].shortname}));
            }
            tr.append(td);
            title = req.time.toReact.text + '\n' + req.time.toFix.text + '\n' + req.time.toRepair.text;
            var toReactStyle = 'background-color: ' + req.time.toReact.color + '; width: ' 
                             + req.time.toReact.color + '%';
            var toFixStyle = 'background-color: ' + req.time.toFix.color
                           + '; width: ' + req.time.toFix.color + '%';
            var toRepairStyle = 'background-color: ' + req.time.toRepair.color
                              + '; width: ' + req.time.toRepair.color + '%';
            tr.append($('<td>')
                .append($('<abbr>', {title: title}))
                .append($('<div>', {class: 'timeSlider', style: 'border: 1px solid ' + req.slider.color + ';'})
                    .append($('<div>', {class: 'scale', style: toReactStyle}))
                    .append($('<div>', {class: 'scale', style: toFixStyle}))
                    .append($('<div>', {class: 'scale', style: toRepairStyle}))
                )
            );
            tbody.append(tr);
        });
    }
    $('#' + page + 'List table tbody').replaceWith(tbody);
    console.log(data)
});
}); */
}

function getCachedByList(req, list, vars, onChange, onDone, session = false) {
    console.log('getCachedByList(', req, ',', list, ',', vars, ',', onChange, ',', onDone, ',', session, ')');
    if (0 == list.length) {
        if (typeof onDone === 'function') {
            onDone({});
        }
        return;
    }
    var listStr = list.join(',');
    var query = '';
    for (var variable in vars) {
        query += ('' == query ? '?' : '&')+variable+'='+encodeURIComponent(vars[variable]);
    }
    var reqFull = req + '/list/' + list + query;
    var timestamp = null, ts = null, lastModified = null;
    list.forEach(function(el) {
        var val = fromStorage(req + '/' + el + query, session);
        if (null !== val && val.cacheInfo.hasOwnProperty('LastModified')) {
            if (null == timestamp || (ts = Date.parse(val.cacheInfo.LastModified) < timestamp)) {
                timestamp = ts;
                lastModified = val.cacheInfo.LastModified;
            }
        }
    });
    var headers = {};
    if (null !== lastModified) {
        headers['If-Modified-Since'] = lastModified;
    }
    headers.APIKey = token;
    block();
    $.ajax({url:        '/api/v2/' + reqFull,
            method:     'GET',
            cache:      false,
            dataType:   'json',
            headers:    headers,
            statusCode: {
                            401: function() {
                                authorization(function() { getCachedByList(req, list, vars, onChange, onDone, session); });
                            }
                        }
    }).done(function(res, status, jXHR) {
        unblock();
        switch (jXHR.status) {
        case 200:
//            console.log(res);
            var cacheInfo = {};
            var lm = jXHR.getResponseHeader('Last-Modified');
            if (null != lm) {
                cacheInfo.LastModified = lm;
                list.forEach(function(el) {
                    renewStorage(req + '/' + el + query, cacheInfo, session);
                });
            }
            for (el in res) {
                if (null == res[el]) {
                    removeStorage(req + '/' + el + query, session);
                } else {
                    toStorage(req + '/' + el + query, res[el], cacheInfo, session);
                }
            }
            break;
        case 304:
            break;
        default:
            if (isset(res) && res.hasOwnProperty('error')) {
                myAlert(res.error);
                return;
            }
        }
        var result = {};
        list.forEach(function(el) {
            res = fromStorage(req + '/' + el + query, session);
            if (null !== res) {
                result[el] = res.value;
            }
        });
        if (typeof onDone === 'function') {
            onDone(result);
        }
    }).fail(function () {
        unblock();
        myAlert('Ошибка связи с сервером');
    });
}

function getCachedByTree(tree, result = {}, onDone, level = 0) {
    console.log('getCachedByTree(', tree, ',', result, ',', onDone, ',', level, ')');
    var count = Object.count(tree[level]);
    var el;
    Object.keys(tree[level]).forEach(function(el) {
        var def = tree[level][el];
        var opts = def.hasOwnProperty('options') ? def.options : {};
        var session = def.hasOwnProperty('session') ? def.session : false;
        getCachedByList(el, result[el], opts, null, function(data) {
            result[el] = data;
            if (def.hasOwnProperty('childs')) {
                var one, child;
                var temp = {};
                for (child in def.childs) {
                    if (!temp.hasOwnProperty(def.childs[child])) {
                        temp[def.childs[child]] = {};
                    }
                    for (one in data) {
                        if (data[one].hasOwnProperty(child) && null !== data[one][child]) {
                            temp[def.childs[child]][data[one][child]] = 1;
                        }
                    }
                    if (!result.hasOwnProperty(def.childs[child])) {
                        result[def.childs[child]] = [];
                    }
                    if (temp.hasOwnProperty(def.childs[child])) {
                        result[def.childs[child]] = result[def.childs[child]].union(Object.keys(temp[def.childs[child]]));
                    }
                }
            }
            if (0 != --count) {
                return;
            }
            if (++level == tree.length) {
                if (typeof onDone == 'function') {
                    onDone(result);
                }
            } else {
                getCachedByTree(tree, result, onDone, level);
            }
        }, session);
    });
}

function refreshList() {
    $requestsTree = [
        {
            requests: {
                childs: {division: 'divisions', contact: 'users', engineer: 'users', partner: 'partners', 
                          equipment: 'equipments', service: 'services'},
                options: {withRates: 1},
                session: true
            }
        },{
            divisions: {childs: {contract: 'contracts', contragent: 'contragents', engineer: 'users'}},
            partners: {},
            equipments: {childs: {workplace: 'workplaces'}},
            services: {}
        },{
            contracts: {childs: {contragent: 'contragents'}},
            workplaces: {}
        },{
            contragents: {},
            users: {}
        }
    ];
    
    var page = $('ul.navi li.ui-state-active a').data('type');
    if (null != fromStorage(page + 'List')) {
        return;
    }
    getCached('users/me/allowedFilters', {}, null, function(data) {
        buildFilter(data);
        restoreFilter();
        var filter = getFilter();
        var states = pageToState[page];
        var selected = $('#' + page + 'List table').data('selected');
        getCached('requests/' + states, filter, null, function(reqNums) {
            console.log('got ', reqNums);
            getCachedByTree($requestsTree, {requests: reqNums}, function(result) {
                console.log(result);
//                drawList(page, requests, childs);
            });
        }, true);
    }, true);
}

function refreshCounts() {
    getCached('users/me/allowedFilters', {}, null, function(data) {
        buildFilter(data);
        restoreFilter();
        var filter = getFilter();
        console.log(filter);
        getCached('requests/counts', filter, null, function(data) {
            var totalCounts = {};
            for (var state in data.total) {
                if (typeof totalCounts[stateToPage[state]] === 'undefined') {
                    totalCounts[stateToPage[state]] = data.total[state];
                } else {
                    totalCounts[stateToPage[state]] += data.total[state];
                }
            }
            var filteredCounts = {};
            for (var state in data.filtered) {
                if (typeof filteredCounts[stateToPage[state]] === 'undefined') {
                    filteredCounts[stateToPage[state]] = data.filtered[state];
                } else {
                    filteredCounts[stateToPage[state]] += data.filtered[state];
                }
            }
            $('ul.navi li a').each(function() {
                var countName = $(this).data('type');
                var countText = (typeof totalCounts[countName] !== 'undefined') ? totalCounts[countName] : 0;
                countText += (typeof filteredCounts[countName] !== 'undefined') 
                                ? ('' !== countText ? ' / ' : '') + filteredCounts[countName] 
                                : 0;
                if ('' != countText) {
                    countText = '(' + countText + ')'
                }
                $('#' + countName + 'Num').text(countText)
            });
            refreshList();
        }, true);
    }, true);
}

function fillCard (data) {
  console.log(data)
  if (null != data.equipment.guid) {
    $('#servNum').val(data.equipment.service_number)
    $('#SN').val(data.equipment.serial_number)
    $('#eqType').val(data.equipment.type +
      ('' == data.equipment.type || '' == data.equipment.subtype ? '' : ' / ') +
      data.equipment.subtype)
    $('#manufacturer').val(data.equipment.manufacturer)
    $('#model').val(data.equipment.model)
  }
  $('#problem').val(data.problem)
  $('#createdAt').val(data.createdAt)
  $('#repairBefore').val(data.repairBefore)
  $('#cardSolProblem').val(data.solution.problem)
  $('#cardSolSolution').val(data.solution.solution)
  $('#cardSolRecomendation').val(data.solution.recomendation)
  $('#card').val('guid', data.guid)
  if (1 == data.can_change_equipment) {
    $('#lookServNum').show()
  } else {
    $('#lookServNum').hide()
  }
  if (1 == data.can_change_partner) {
    $('#lookPartner').show()
  } else {
    $('#lookPartner').hide()
  }
  $('#contragent').html("<option value='" + data.contragent.guid + "'>" + data.contragent.name)
  var opts = ''
  data.contacts.forEach(function (opt, i) {
    opts += "<option value='" + data.persons[opt].guid + "' data-email='" + data.persons[opt].email +
    "' data-phone='" + data.persons[opt].phone + "' data-address='" + data.persons[opt].address + "'" +
    (opt == data.contact ? ' selected' : '') + '>' + data.persons[opt].name
  })
  $('#contact').html(opts)
  $('#email').val(data.persons[data.contact].email)
  $('#phone').val(data.persons[data.contact].phone)
  $('#address').val(data.persons[data.contact].address)
  opts = ''
  data.services.forEach(function (opt, i) {
    opts += "<option value='" + opt.guid + "' data-autoonly='" + opt.autocreate_only + "'" +
    (i == data.service ? ' selected' : '') + '>' + opt.name
  })
  $('#service').html(opts)
  opts = ''
  data.slas.forEach(function (opt, i) {
    opts += "<option value='" + opt.level + "'" + (i == data.sla ? ' selected' : '') + '>' + opt.name
  })
  $('#level').html(opts)
  $('#contract').html("<option value='" + data.contract.guid + "' selected>" + data.contract.name)
  $('#division').html("<option value='" + data.division.guid + "' selected>" + data.division.name)
  opts = ''
  var files = ''
  data.log.forEach(function (opt, i) {
    opts += "<p class='" + ('comment' == opt.event.code ? 'logDateComm' : 'logDate') + "'>" + opt.event.time +
      ": <abbr title='" + data.persons[opt.author].name + '\nE-mail: ' + data.persons[opt.author].email +
      '\nТелефон: ' + data.persons[opt.author].phone + "'>" + data.persons[opt.author].shortname + '</abbr>'
    if (null !== opt.event.text) {
      opts += "<p class='logMain'>" + opt.event.text
    }
    if (null !== opt.event.comment) {
      opts += "<p class='logComment'>" + opt.event.comment
    }
    if (null !== opt.event.doc) {
      if (null !== opt.event.doc.href) {
        opts += " <a href='" + opt.event.doc.href + "'>" + opt.event.doc.name + '</a>'
        files += '<tr><td><td>' + opt.event.time + '<td>' + opt.event.doc.name + '<td>' + opt.event.doc.size +
          "<td><a href='" + opt.event.doc.href + "'>Скачать</a>"
      } else {
        opts += ' ' + opt.event.doc.name + ' (потерян)'
      }
    }
  })
  $('#comments').html(opts)
  $('#cardDocTbl tbody').html(files)
  $('#cardDocTbl tr:nth-child(2n+1)').addClass('odd')
  $('#cardDocTbl td:nth-child(1)').addClass('cell1')
  $('#cardDocTbl td:nth-child(4)').addClass('cell4')
  $('#card select').select2()
  $('#service').data('id', $('#service').val())
  $('#contact').data('id', $('#contact').val())
  $('#servNum').data('serv', $('#servNum').val()).data('id', data.equipment_guid)
  $('#level').data('id', $('#level').val())
}

$(function () {

    // Обход ошибки с селектом в модальных окнах
    $.ui.dialog.prototype._allowInteraction = function (e) {
        return !!$(e.target).closest('.ui-dialog, .ui-datepicker, .select2-dropdown').length;
    }

    // Стартовая инициализация интерфейса
    $('#chkMyTickets').buttonset();
    $('#workflow').tabs({
        active: 0,
        activate: function (event, ui) {
            refreshCounts();
        }
    });

    $('#userLogin').dialog({
        autoOpen:    false,
        width:       '320px',
        resizable:   false,
        dialogClass: 'no-close',
        draggable:   false,
        title:       'Вход',
        modal:       true
    });

    $('#cardTabs').tabs({active: 0});

    $('button').button();
    $('button').each(function () {
        if ($(this).data('icon') != '') {
            $(this).button('option', 'icons', {primary: $(this).data('icon')});
        }
    });
    $('#refresh').button('option', 'text', false);

    $.datepicker.setDefaults({
        monthNames:      ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 
                          'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'],
        monthNamesShort: ['Янв', 'Фев', 'Мар', 'Апр', 'Май', 'Июн', 'Июл', 'Авг', 'Сен', 'Окт', 'Ноя', 'Дек'],
        dayNamesMin:     ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'],
        firstDay:        1
    });

    // Инициализируем диалоги
    $('#filter').dialog({
        autoOpen:    false,
        position:    {my: 'right top', at: 'right bottom', of: '#showFilter'},
        resizable:   false,
        dialogClass: 'no-close',
        draggable:   false,
        title:       'Отбор заявок',
        modal:       true,
        buttons:     filterBtn
    });

    $('#fltByFromDate').datepicker({
        changeMonth:    true,
        numberOfMonths: 1,
        dateFormat:     'd MM yy',
        constrainInput: false,
        onClose:        function (selectedDate) {
                            $('#fltByToDate').datepicker('option', 'minDate', selectedDate);
                        }
    });

    $('#fltByToDate').datepicker({
        changeMonth:    true,
        numberOfMonths: 1,
        dateFormat:     'd MM yy',
        constrainInput: false,
        onClose:        function (selectedDate) {
                            $('#fltByFromDate').datepicker('option', 'maxDate', selectedDate);
                        }
    });

    $('#card').dialog({
        autoOpen:    false,
        resizable:   false,
        dialogClass: 'no-close',
        width:       660,
        modal:       true,
        draggable:   false,
        buttons:     cardBtnNew,
        close:       function () {
                        $(this).find('select').each(function () {
                            $(this).select2('close');
                        });
                     }
    });

    getCached('users/me/info', {}, null, function (data) {
        $('#name').html(data.fullName)
        if ('admin' == data.rights) {
            $('#user').append("&nbsp;<button id='admin'>Администрирование</button>");
            $('#fltByPartnerPlace').show();
            $('#fltByEngineerPlace').show();
            $('#admin').button();
        } else {
            $('#fltByPartnerPlace').hide();
            $('#fltByEngineerPlace').hide();
        }
        getCached('users/me/allowedOps', {}, null, function(data) {
            var buttons = {}
            for (var state in data) {
                if (typeof buttons[stateToPage[state]] === 'undefined') {
                    buttons[stateToPage[state]] = data[state];
                } else {
                    buttons[stateToPage[state]] = buttons[stateToPage[state]].union(data[state]);
                }
            }
            for (var page in buttons) {
                buttons[page] = buttons[page].sort(function (a, b) { return buttonDefs[a].order - buttonDefs[b].order; });
                var btns = $('<div>', {id: page + 'Opers', class: 'oper'});
                buttons[page].forEach(function(op) {
                    btns.append(
                        $('<button>', {class: 'btn' + op, 'data-icon': buttonDefs[op].icon,
                        'data-cmd': op, text: buttonDefs[op].name})
                    );
                });
                $('#' + page + 'Opers').replaceWith(btns);
            }
            $('button').button();
            $('button').each(function() {
                $(this).button('option', 'icons', {primary: $(this).data('icon')})
            });
            refreshCounts();
        }, true);
    }, true);
/*
  // Описываем нажатия/выборы

  $(document).on('click', '#myAlertOk', function () {
    $.unblockUI()
    return false
  })

  $('#showFilter').click(function () {
    $(this).blur()
    if ($('#filter').dialog('isOpen')) {
      restoreFilter()
      $('#filter').dialog('close')
    } else {
      getCached('filterData', 'users/me/allowedFilters', null, function (data) {
        buildFilter(data)
        restoreFilter()
        $('#filter').dialog('open')
      })
    }
  })

  $('.useNextField').change(function () {
    $(this).next().prop('disabled', !$(this).prop('checked'))
  })

  $('#refresh').click(function () {
    invalidateStorage('counts')
    $('ul.navi li a').each(function () {
      invalidateStorage($(this).data('type') + 'List')
    })
    refreshCounts()
  })

  $('.list').on('click', 'input.checkOne', function (event) {
    if ($(this).prop('checked')) {
      $(this).parents('table').data('selected', $(this).parents('tr').attr('id'))
      $(this).parents('tbody').find('input.checkOne').prop('checked', false)
      $(this).prop('checked', true)
    } else {
      $(this).parents('table').data('selected', null)
    }
  })

  $('.list').on('click', 'tbody tr', function (event) {
    if ('checkbox' == event.target.type || $(this).parents('table').hasClass('planned'))
      return
    openCard = ($(this).attr('id'))
    $('#card').dialog('option', 'title', 'Заявка ' + $(this).find('td:nth-child(2)').text())
    $('#cardTabs').tabs('option', 'active', 0)
    $('#card .ro').prop('readonly', 'readonly')
    $('#card input, #card select, #card textarea').val('')
    $('#card input, #card select, #card textarea').each(function () {
      $(this).parent().prev().removeClass('active')
    })
    $('#card select').html('').select2({width: '100%'})
    $('#card').dialog('option', 'buttons', cardBtnLook)
    $('#card').dialog('open')
    cardMode = 'look'
    get('requests/' + openCard, null, null, fillCard)
  })'userData', 

/*
  	$('#selectEq').dialog({
  		autoOpen: 		false,
    	title: 			'Выберите оборудование',
		resizable: 		false,
        dialogClass: 	'no-close',
        width: 			660,
        modal: 			true,
        draggable: 		false,
        buttons: 		selectEqBtn
    })
	$('#selectPartner').dialog({
		autoOpen: 		false,
    	title: 			'Выберите партнёра',
        resizable: 		false,
        dialogClass: 	'no-close',
        width: 			660,
        modal: 			true,
        draggable: 		false,
        buttons: 		selectPartnerBtn
	})
  	$('#solution').dialog({
  		autoOpen: 		false,
        resizable: 		false,
        dialogClass: 	'no-close',
        width: 			660,
        modal: 			true,
        draggable: 		false,
        buttons: 		solutionBtn
	})
  	$('#addProblem').dialog({
  		autoOpen: 		false,
		title: 			'Добавить задание в плановый выезд',
        resizable: 		false,
        dialogClass: 	'no-close',
        width: 			660,
        modal: 			true,
        draggable: 		false,
        buttons: 		addProblemBtn
    })
  	$('#userSetup').dialog({
  		autoOpen:		false,
		title: 			'Способы отправки сообщений',
        resizable: 		false,
        dialogClass: 	'no-close',
        width: 			660,
        modal: 			true,
        draggable: 		false,
        buttons: 		userSetupBtn
	})
  
 	
	$('#logout').click(function() {
		var login = localStorage.getItem('loginName')
		localStorage.clear()
		if (null !== login) {
			localStorage.setItem('loginName', login)
		}
		location.replace('index_new.html')
  	})

  myPostJson('/ajax/filter/build', {}, function() {
    $('.oper button').each(function() {
      $(this).button({icons:{primary:$(this).data('icon')}})
    })
    setFilter()
  }, null, null)

  $('#user').on('click', '#admin', function() {
  	location.replace('newadmin.html')
  })

  $('#lookServNum').click(function() {
  	if ($('#division').val() == '*')
  	  return
    $('#selectEq select').html('')
    $('#selectEq').dialog('open')
    //$('#selectServNum').val($('#servNum').val())
    $('#selectServNum').val('')
	myPostJson('/ajax/dir/equipment/'+$('#division').val(), {servNum: $('#selectServNum').val().trim()},
	  function() {
		$('#selectEqList ul ul').hide()
		$('#selectEqList ul ul.single').show()
	  },
	  function() {
		myAlert('Ошибка связи с сервером')
		$('#selectEq').dialog('close')
	  });       
  })

  $('#lookPartner').click(function() {
    $('#selectPartnerList').html('')
    $('#selectPartner').dialog('open')
	myPostJson('/ajax/dir/partners/'+openCard, null, null,
	  function() {
		myAlert('Ошибка связи с сервером')
		$('#selectPartner').dialog('close')
	  });       
  })
  
  $('#selectPartnerList').on('click', 'li', function() {
	  $('#selectPartner').dialog('close')
	  if ('0' == $(this).data('id'))location.replace('index_new.html')
	  	$('#partner').val('')
	  else
	  	$('#partner').val($(this).text())
	  myPostJson('/ajax/request/partner/set/'+openCard+'/'+$(this).data('id'), null, null, 
	  function() {
		myAlert('Ошибка связи с сервером')
		$('#card').dialog('close')
	  })
  })

  $('#workflow').on('click', '.btnNew', function() {
	$('#card').dialog('open')
	$('#card').dialog('option', 'title', 'Новая заявка')
    $('#card .ro').removeProp('readonly')
	$('#cardTabs').tabs('option', 'active', 0)
  	$('#card').dialog('option', 'buttons', cardBtnNew)
	$('#servNum').val('').data('serv', null).data('id', null)
	$('#card input').val('')
	$('#card select').html('').select2()
	$('#service').data('id', null)
	$('#contact').data('id', null)
	$('#card textarea').val('')
	$('#card .active').removeClass('active')
	$('#lookServNum').hide()
	$('#lookPartner').hide()
	myPostJson('/ajax/dir/contragents', null,
  	  function() {
		$('#contragent').select2()
		if ($('#contragent').val() != 0)
		  $('#contragent').trigger('change')
  	  },
  	  function() {
		myAlert('Ошибка связи с сервером')
		$('#card').dialog('close')
 	  })
    cardMode = 'new'
  })

  $('#card').on('change', '#contragent', function() {
  	if (cardMode != 'new')
  		return
	if ($('#contragent').val() == '*') {
      $('#card .ro').removeProp('readonly')
	  $('#servNum').val('').data('id', null)
	  $('#card input').val('')
	  $('#contract').html('').select2()
	  $('#division').html('').select2()
	  $('#service').html('').select2()
	  $('#level').html('').select2()
	  $('#contact').html('').select2()
	  $('#card textarea').val('')
	  $('#card .active').removeClass('active')
	  $('#lookServNum').hide()
  	  return
    }
	myPostJson('/ajax/dir/contracts/'+$('#contragent').val(), null,
	  function() {
		$('#contract').select2()
		if ($('#contract').val() != 0)
	  		$('#contract').trigger('change')
	  },
	  function() {
		myAlert('Ошибка связи с сервером')
		$('#card').dialog('close')
	  })
  })
  
  $('#card').on('change', '#contract', function() {
  	if (cardMode != 'new')
  		return
	if ($('#contract').val() == '*') {
      $('#card .ro').removeProp('readonly')
	  $('#servNum').val('').data('id', null)
	  $('#card input').val('')
	  $('#division').html('').select2()
	  $('#service').html('').select2()
	  $('#level').html('').select2()
	  $('#contact').html('').select2()
	  $('#card textarea').val('')
	  $('#card .active').removeClass('active')
	  $('#lookServNum').hide()
  	  return
    }
	myPostJson('/ajax/dir/divisions/'+$('#contract').val(), null,
	  function() {
		$('#division').select2()
		if ($('#division').val() != 0)
	  		$('#division').trigger('change')
	  },
	  function() {
		myAlert('Ошибка связи с сервером')
		$('#card').dialog('close')
	  })
  })
  
  $('#card').on('change', '#division', function() {
  	if (cardMode != 'new')
  		return
	if ($('#division').val() == '*') {
      $('#card .ro').removeProp('readonly')
	  $('#card input').val('')
	  $('#card textarea').val('')
	  $('#card .active').removeClass('active')
	  $('#lookServNum').hide()
	  $('#lookPartner').hide()
    }
    $('#service').html('').select2()
  	$('#servNum').val('').data('id', null)
	$('#contact').html('').select2()
    $('#level').html('')
  	$('#SN').val('')
  	$('#eqType').val('')
  	$('#manufacturer').val('')
  	$('#model').val('')
   	myPostJson('ajax/time', null,
   	  function(data) {
   	  	if (typeof data !== 'undefined') {
   	  	  if (typeof data.time !== 'undefined')
   	        $('#createdAt').val(data.time)
   	  	  if (typeof data.timeEn !== 'undefined')
   	        $('#createTime').val(data.timeEn)
	      $('#lookServNum').show()
	      $('#division').parent().prev().removeClass('active')
	      $('#problem').parent().prev().addClass('active')
	      $('#level').parent().prev().addClass('active')
	      $('#contact').parent().prev().addClass('active')
          if ($('#division').val() != '*')
	        myPostJson('/ajax/dir/services/'+$('#division').val(), null,
	          function() {
	            $('#service').parent().prev().addClass('active')
  	            $('#service').trigger('change')
         	    myPostJson('/ajax/dir/contacts/'+$('#division').val(), null,
          	      function(data) {
          	        $('#contact').trigger('change')
          	  	  },
	  			  function() {
				    myAlert('Ошибка связи с сервером')
				    $('#card').dialog('close')
	  			  })
	          },
              function() {
	            myAlert('Ошибка связи с сервером')
	            $('#card').dialog('close')
              })
   	    }
   	  },
	  function() {
		myAlert('Ошибка связи с сервером')
		$('#card').dialog('close')
	  })
  })
  
  $('#card').on('change', '#service', function() {
  	if ($(this).val() == '*') {
  		$('#level').html('').select2()
  		return
  	}
	myPostJson('/ajax/dir/slas/'+$('#division').val()+'/'+$('#service').val(), null, 
	  function() {
		$('#level').trigger('change')
	  },
	  function() {
		myAlert('Ошибка связи с сервером')
		$('#card').dialog('close')
	  })
  })
  
  $('#card').on('change', '#contact', function() {
  	console.log($(this))
  	if ($(this).val() == '*') {
  	  $('#email').val('')
  	  $('#phone').val('')
  	  $('#address').val('')
  	} else {
  	  var sel = $(this).find('option:selected')
  	  $('#email').val(sel.data('email'))
  	  $('#phone').val(sel.data('phone'))
  	  $('#address').val(sel.data('address'))
  	  checkChanges()
  	}
  })
  
  $('#card').on('change', '#level', function() {
    var cardNum = ('new' == cardMode ? null : {id: openCard})
	myPostJson('/ajax/request/calcTime/'+$('#division').val()+'/'+$('#service').val()+'/'+$('#level').val(), cardNum, 
	  function() {
	  	if ('new' != cardMode && $('#service').val() != '*')
       		checkChanges()
	  },
	  function() {
		myAlert('Ошибка связи с сервером');  
		$('#card').dialog('close')
	  })
  })

  $('#selectServNum').keyup(function() {
  	if (timeoutSet == 1)
  		clearTimeout(servNumLookupInterval)
    servNumLookupInterval = setTimeout(servNumLookup, 1000)
    timeoutSet = 1;      
  })

  $('#selectServNum').change(function() {
  	if (timeoutSet == 1)
  		clearTimeout(servNumLookupInterval)
    servNumLookupInterval = setTimeout(servNumLookup, 1000)
    timeoutSet = 1;      
  })

  $('#workflow').on('click', '.btnAccept', function() {
    var cmd = $(this).data('cmd')
    var list = ''
    var errList = ''
    var errs = 0
    $('.tab'+$('#workflow').tabs('option', 'active')+' :checked').each(function() {
      if (0 != $(this).parents('tr').data('autoonly')) {
        errs++
      	errList += ('' == errList ? '' : ', ')+$(this).parents('tr').attr('id')
      } else
        list += $(this).parents('tr').attr('id')+','
    })
    if (errs > 0)
      myAlert('Невозможно принять заявк'+(errs > 1 ? 'и' : 'у')+' '+errList+'. '+(errs > 1 ? 'Указанные услуги являются служебными.' : 'Указанная услуга является служебной.'))
    if (list == '')
      return
    myPostJson('/ajax/request/'+cmd+'/'+list, null, null, null,
               function() {
                 setFilter()
               })
  })

  $('#workflow').on('click', '.btnFixed, .btnClose, .btnDoNow', function() {
    var cmd = $(this).data('cmd')
    var list = ''
    $('.tab'+$('#workflow').tabs('option', 'active')+' :checked').each(function() {
      list += $(this).parents('tr').attr('id')+','
    })
    if (list == '')
      return
    myPostJson('/ajax/request/'+cmd+'/'+list, null, null, null,
               function() {
                 setFilter()
               })
  })
  
  $('#workflow').on('click', '.btnRepaired', function() {
    var rows = $('.tab'+$('#workflow').tabs('option', 'active')+' :checked')
    if (rows.length > 1)
      myAlert('Выберите только одну заявку')
    if (rows.length != 1)
      return
  	var id = rows.first().parents('tr').attr('id')
  	$('#solution').data('id', id)
    $('#solution').dialog('open')
	$('#solution').dialog('option', 'title', 'Решение заявки '+('0000000'+id).substr(-7))
    myPostJson('/ajax/request/getSolution/'+id, null, null,
	  function() {
		myAlert('Ошибка связи с сервером')        

		$('#solution').dialog('close')
	  });       
	
  });  

  $('#workflow').on('click', '.btnCancel, .btnWait, .btnUnClose, .btnUnCancel', function() {
    var cmd = $(this).data('cmd')
    var rows = $('.tab'+$('#workflow').tabs('option', 'active')+' :checked')
    if (rows.length > 1)
      myAlert('Выберите только одну заявку')
    if (rows.length != 1)
      return
    var list = rows.first().parents('tr').attr('id')
    var cause
    if ((cause = prompt('Причина:', '')) == null || cause == '')
	    return
    myPostJson('/ajax/request/'+cmd+'/'+list, {cause: cause}, null, null,
               function() {
                 setFilter()
               })
  })

  $('#addComm').click(function() {
    if (cardMode == 'look' && $('#addComment').val() != '') {
      myPostJson('/ajax/request/addComment/'+openCard, {comment: $('#addComment').val()},
                 function() {
                     $('#addComment').val('')
		             myPostJson('/ajax/request/view/'+openCard, null,
                         function() {
                         	$('#cardDocTbl tr:nth-child(2n+1)').addClass('odd')
                         	$('#cardDocTbl td:nth-child(1)').addClass('cell1')
                         	$('#cardDocTbl td:nth-child(3)').addClass('cell3')
                       	 })
                 })
    }
  })

  $('#addFile').click(function() {
    if (cardMode == 'look' && typeof ($('#file')[0].files[0]) !== 'undefined') {
      var fd = new FormData()
      fd.append('file', $('#file')[0].files[0])
      $('#card').dialog('option', 'buttons', cardBtnWait)
      $.ajax({type: 'POST',
              url: '/ajax/request/addFile/'+openCard,
              data: fd,
              processData: false,
              contentType: false,
              dataType: 'json'})
        .done(function(data) {
          if (data === null || typeof data.error !== 'undefined') {
            myAlert((typeof data.error !== 'undefined') ? data.error : 'Ошибка передачи файла')
          } else {
            $('#file').val('')
            myPostJson('/ajax/request/view/'+openCard, null,
                       function() {
                         $('#cardDocTbl tr:nth-child(2n+1)').addClass('odd')
                         $('#cardDocTbl td:nth-child(1)').addClass('cell1')
                         $('#cardDocTbl td:nth-child(3)').addClass('cell3')
                       })
          }
        })
        .fail(function() {
            myAlert('Ошибка передачи файла')
        })
    }
  })

	$('body').on('mouseleave', 'button:focus', function() {
    $(this).blur()
  })
  
  $('body').on('change', '.checkAll', function() {  
   	if ($(this).prop('checked'))
      $(this).parents('table').first().find('.checkOne').not(':disabled').prop('checked', 'checked')
    else
      $(this).parents('table').first().find('.checkOne').not(':disabled').removeProp('checked')
  });	
	
  $('#selectEqList').on('click', '.open>ul>li', function (evt) {
	evt.stopPropagation()
	$('#selectEq').dialog('close')
	$('#servNum').data('id', $(this).data('id'))
	$('#servNum').val($(this).data('servnum'))
	$('#SN').val($(this).data('sn'))
	$('#eqType').val($(this).data('eqtype'))
	$('#manufacturer').val($(this).data('mfg'))
	$('#model').val($(this).data('model'))
	checkChanges(); 
  })
	
  $('#selectEqList').on('click', '.collapsed', function () {
	$(this).children('span').removeClass('ui-icon-folder-collapsed').addClass('ui-icon-folder-open')
	$(this).children('ul').show()
	$(this).removeClass('collapsed').addClass('open')
  })

  $('#selectEqList').on('click', '.open', function () {
	$(this).children('span').removeClass('ui-icon-folder-open').addClass('ui-icon-folder-collapsed')
	$(this).children('ul').hide()
	$(this).removeClass('open').addClass('collapsed')
  })
	
  $('#workflow').on('click', '.btnAddProblem', function() {
	$('#addProblem').dialog('open')
	myPostJson('/ajax/dir/contragents', null, null, null, null,
	  function(data) {
	  	if (typeof data.contragent !== 'undefined')
	  	  $('#apContragent').html(data.contragent).trigger('change')
	  })
  })

  $('#apContragent').change(function() {
  	if ('*' == $(this).val()) {
  	  $('#apContract').html('')
  	  $('#apDivision').html('')
  	  $('#apProblem').val('')
  	} else
	  myPostJson('/ajax/dir/contracts/'+$('#apContragent').val(), null, null, null, null, 
	    function(data) {
	  	  if (typeof data.contract !== 'undefined')
		    $('#apContract').html(data.contract).trigger('change')
	    })
  })

  $('#apContract').change(function() {
  	if ('*' == $(this).val()) {
  	  $('#apDivision').html('')
  	  $('#apProblem').val('')
  	} else
	  myPostJson('/ajax/dir/divisions/'+$('#apContract').val(), null, null, null, null, 
	    function(data) {
	  	  if (typeof data.division !== 'undefined') {
		    $('#apDivision').html(data.division)
		    $('#apDivision option[value="*"]').remove()
		    $('#apDivision').prepend('<option value="*" selected>Все').trigger('change')
		  }
	    })
  })

  $('#apDivision').change(function() {
  	if ('*' == $(this).val())
  	  $('#apProblem').val('')
  	else
	  myPostJson('/ajax/problem/get/'+$('#apDivision').val())
  })
  
  $('#setup').click(function() {
  	$('#userSetup').dialog('open')
  	myPostJson('/ajax/user/messageConfig/get/', null, null,
  	  function() {
  		$('#userSetup').dialog('close')
  	  })
  })
*/
})

<?php

$statusGroup = array('received' => 'received',
					 'preReceived' => 'received',
					 'accepted' => 'accepted',
					 'fixed' => 'accepted',
					 'repaired' => 'toClose',
					 'closed' => 'closed',
					 'canceled' => 'canceled');

$statusNames = array('received' => 'Получена',
					 'preReceived' => 'Получена',
					 'accepted' => 'Принята к исполнению',
					 'fixed' => 'Работоспособность восстановлена',
					 'repaired' => 'Работа завершена',
					 'closed' => 'Закрыта',
					 'canceled' => 'Отменена',
					 'planned' => 'Плановая',
					 'onWait' => 'Ожидание комплектующих',
					 'notSync' => 'Нет синхронизации с 1С');

$statusIcons = array('received' => 'ui-icon-mail-closed',
					 'preReceived' => 'ui-icon-mail-closed',
					 'accepted' => 'ui-icon-mail-open',
					 'fixed' => 'ui-icon-wrench',
					 'repaired' => 'ui-icon-help',
					 'closed' => 'ui-icon-check',
					 'canceled' => 'ui-icon-cancel',
					 'planned' => 'ui-icon-calendar',
					 'onWait' => 'ui-icon-clock',
					 'notSync' => 'ui-icon-alert',
					 'toPartner' => 'ui-icon-arrowthick-1-e');

$inGroupSortOrder = array('received' => 'ASC',
					 'accepted' => 'ASC',
					 'repaired' => 'DESC',
					 'closed' => 'DESC',
					 'canceled' => 'DESC',
					 'toClose' => 'DESC');
					 
$groupStatus = array('received' => "'received','preReceived'",
				 	'accepted' => "'accepted'",
				 	'fixed'	=> "'fixed'",
				 	'repaired' => "'repaired'",
				 	'closed' => "'closed'",
				 	'canceled' => "'canceled'");
					 

$slaLevels = array('critical' => 'Критический', 'high' => 'Высокий', 'medium' => 'Средний', 'low' => 'Низкий');

?>
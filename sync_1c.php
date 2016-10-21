<?php
	//include 'config/db.php';
	include 'config/files.php';
	$dbHost = '127.0.0.1';
    $dbUser = 'root';
	$dbPort = 3306;
    $dbPass = 'Rthtyrb$';
    $dbName = 'sd-dev-1c';
    $mainFirmID = 2;
	
	$npps = array();
	$svcs = array();
	$dayTypes = array('Праздник' => 'weekend', 'Воскресенье' => 'weekend', 'Суббота' => 'weekend', 'Рабочий' => 'work', 
					  'Предпраздничный' => 'work');
	$levels = array('Низкий' => 'low', 'Стандартный' => 'medium', 'Высокий' => 'high', 'Критический' => 'high');
	$states = array('_1Принята' => 'received', '_2ПринятаКИсполнению' => 'accepted', '_30РаботоспобностьВосстановлена' => 'fixed',
					'_3РаботыВыполнены' => 'repaired', '_4Завершена' => 'closed', '_5Отменена' => 'canceled');
	$events = array('Изменен статус заявки' => 'changeState', 'Добавлен комментарий' => 'comment', 'Изменено оборудование' => 'eqChange');
//					'changeDate', 'addDocument','unClose','unCancel', 'open', 'onWait', 'offWait'
	$lastStates = array();
	$prevStates = array();
	$lastEquipment = array();
	
	function formatGuid($hex) {
		if (null === $hex)
			return null;
		if (!preg_match('/^[0-9a-z]{32}$/i', $hex)) {
			$hex = unpack('H*', $hex);
			$hex = $hex[1];
		}
		if (preg_match('/^[0-9a-z]{32}$/i', $hex))
			return preg_replace('/([0-9a-z]{8})([0-9a-z]{4})([0-9a-z]{4})([0-9a-z]{4})([0-9a-z]{12})/', '$1-$2-$3-$4-$5', strtolower($hex));
		return null;
	}
	
	function parseXML($xmlList, $fields) {
		global $npps;
		$result = array();
		foreach ($fields as $name)
			$result[] = NULL;
		foreach ($xmlList as $prop) {
			$name = "{$prop->attributes()->{'Имя'}}";
			$pos = array_search($name, $fields);
			$posObj = array_search('!'.$name, $fields);
			$posNpp = array_search('?'.$name, $fields);
//			print "{$name} => '{$pos}', '{$posObj}' -> '{$prop->{'Значение'}}', '{$posNpp}' -> '{$prop->{'Нпп'}}'\n";
			if ($pos !== false)
				$result[$pos] = "{$prop->{'Значение'}}";
			else if ($posObj !== false)
				$result[$posObj] = $prop;
			else if ($posNpp !== false) {
				$npp = $prop->{'Нпп'};
				if (!$npp && $prop->{'Ссылка'})
					$npp = $prop->{'Ссылка'}->attributes()->{'Нпп'};
				$npp = trim($npp);
				if (isset($npps[$npp]))
					$result[$posNpp] = $npps[$npp];
			}
		}
		return $result;
	}
	
	function parseCalendar($xml, $db) {
		global $dayTypes;
		print "Календарь:\n";
		$req = $db->prepare("INSERT INTO `workCalendar` (`date`, `type`) VALUES (:date, :type) ".
								"ON DUPLICATE KEY UPDATE `type` = VALUES(`type`)");
		foreach ($xml->{'НаборЗаписейРегистра'} as $obj) {
			if ('РегистрСведенийНаборЗаписей.РегламентированныйПроизводственныйКалендарь' == $obj->attributes()->{'Тип'}) {
				foreach($obj->{'СтрокиНабораЗаписей'}->{'Объект'} as $day) {
					list($date, $type) = parseXML($day->{'Свойство'}, array('ДатаКалендаря', 'ВидДня'));
					$type = $dayTypes[$type];
					$date = preg_replace('/T.*$/', '', $date);
					try {
						$req->execute(array('date' => $date, 'type' => $type));
					} catch(PDOException $e) {
						print $e->getMessage()."\n";  
					} 
//					print "{$date}\t{$type}\t".$req->rowCount()."\n";
				}
			}
		}
		print "-------------------------------\n\n";
	}

	function parseEqTypes($xml, $db) {
		global $npps;
		print "Типы оборудования:\n";
		$req = $db->prepare("INSERT INTO `equipmentTypes` (`guid`, `name`) VALUES (UNHEX(REPLACE(:guid, '-', '')), :name) ".
								"ON DUPLICATE KEY UPDATE `name` = VALUES(`name`)");
		foreach ($xml->{'Объект'} as $obj) {
			if ('СправочникСсылка.СоД_КатегорииОборудования' == $obj->attributes()->{'Тип'}) {
				list($guid, $name) = parseXML($obj->{'Ссылка'}->{'Свойство'}, array('{УникальныйИдентификатор}', 'Наименование'));
				$npps[trim($obj->attributes()->{'Нпп'})] = $guid;
				try {
					$req->execute(array('guid' => $guid, 'name' => $name));
				} catch(PDOException $e) {
					print $e->getMessage()."\n";  
				} 
//				print "{$guid}\t{$name}\t".$req->rowCount()."\n";
			}
		}
		print "-------------------------------\n\n";
	}

	function parseEqSubTypes($xml, $db) {
		global $npps;
		print "Подтипы оборудования:\n";
		$req = $db->prepare("INSERT INTO `equipmentSubTypes` (`guid`, `name`, `equipmentType_guid`) ".
									"VALUES (UNHEX(REPLACE(:guid, '-', '')), :name, UNHEX(REPLACE(:parentGuid, '-', ''))) ".
								"ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `equipmentType_guid` = VALUES(`equipmentType_guid`)");
		foreach ($xml->{'Объект'} as $obj) {
			if ('СправочникСсылка.СоД_ТипыОборудования' == $obj->attributes()->{'Тип'}) {
				list($guid, $name) = parseXML($obj->{'Ссылка'}->{'Свойство'}, array('{УникальныйИдентификатор}', 'Наименование'));
				$npps[trim($obj->attributes()->{'Нпп'})] = $guid;
				list($parentGuid) = parseXML($obj->{'Свойство'}, array('?КатегорияОборудования'));
				if ($parentGuid != NULL) {
					try {
						$req->execute(array('guid' => $guid, 'name' => $name, 'parentGuid' => $parentGuid));
					} catch(PDOException $e) {
						print $e->getMessage()."\n";  
					} 
//					print "{$guid}\t{$parentGuid}\t{$name}\t".$req->rowCount()."\n";
				} else {
					print "{$guid}\t{$name} => Неизвестный Нпп\n";
				} 
			}
		}
		print "-------------------------------\n\n";
	}
	
	function parseManufacturers($xml, $db) {
		global $npps;
		print "Производители:\n";
		$req = $db->prepare("INSERT INTO `equipmentManufacturers` (`guid`, `name`) VALUES (UNHEX(REPLACE(:guid, '-', '')), :name) ".
								"ON DUPLICATE KEY UPDATE `name` = VALUES(`name`)");
		foreach ($xml->{'Объект'} as $obj) {
			if ('СправочникСсылка.СоД_ПроизводителиНоменклатуры' == $obj->attributes()->{'Тип'}) {
				list($guid, $name) = parseXML($obj->{'Ссылка'}->{'Свойство'}, array('{УникальныйИдентификатор}', 'Наименование')); 
				$npps[trim($obj->attributes()->{'Нпп'})] = $guid;
				try {
					$req->execute(array('guid' => $guid, 'name' => $name));
				} catch(PDOException $e) {
					print $e->getMessage()."\n";  
				} 
//				print "{$guid}\t{$name}\t".$req->rowCount()."\n";
			}
		}
		print "-------------------------------\n\n";
	}
	
	function parseEqModels($xml, $db) {
		global $npps;
		print "Модели:\n";
		$req = $db->prepare("INSERT INTO `equipmentModels` (`guid`, `name`, `equipmentSubType_guid`, `equipmentManufacturer_guid`) ".
									"VALUES (UNHEX(REPLACE(:guid, '-', '')), :name, UNHEX(REPLACE(:subTypeGuid, '-', '')), 
											 UNHEX(REPLACE(:manufacturerGuid, '-', ''))) ".
								"ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `equipmentSubType_guid` = VALUES(`equipmentSubType_guid`), ".
														"`equipmentManufacturer_guid` = VALUES(`equipmentManufacturer_guid`)");
		foreach ($xml->{'Объект'} as $obj) {
			if ('СправочникСсылка.СоД_МоделиОборудования' == $obj->attributes()->{'Тип'}) {
				list($guid, $name) = parseXML($obj->{'Ссылка'}->{'Свойство'}, array('{УникальныйИдентификатор}', 'Наименование'));
				$npps[trim($obj->attributes()->{'Нпп'})] = $guid;
				list($subTypeGuid, $mfgGuid) = parseXML($obj->{'Свойство'}, array('?ТипОборудования', '?Производитель'));
				if ($subTypeGuid != NULL && $mfgGuid != NULL) {
					try {
						$req->execute(array('guid' => $guid, 'name' => $name, 'subTypeGuid' => $subTypeGuid, 
											'manufacturerGuid' => $mfgGuid));
					} catch(PDOException $e) {
						print $e->getMessage()."\n";  
					} 
//					print "{$guid}\t{$subTypeGuid}\t{$mfgGuid}\t{$name}\t".$req->rowCount()."\n";
				} else {
					print "{$guid}\t{$name} => Неизвестный Нпп\n";
				}
			}
		}
		print "-------------------------------\n\n";
	}

	function parseContragents($xml, $db) {
		global $npps;
		print "Контрагенты:\n";
		$req = $db->prepare("INSERT INTO `contragents` (`guid`, `name`, `fullName`, `INN`, `KPP`, `parent`) ".
									"VALUES (UNHEX(REPLACE(:guid, '-', '')), :name, :fullName, :inn, :kpp, 
											 UNHEX(REPLACE(:parentGuid, '-', ''))) ".
								"ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `fullName` = VALUES(`fullName`), ".
														"`INN` = VALUES(`INN`), `KPP` = VALUES(`KPP`), `parent` = VALUES(`parent`)");
		foreach ($xml->{'Объект'} as $obj) {
			if ('СправочникСсылка.Контрагенты' == $obj->attributes()->{'Тип'}) {
				list($guid) = parseXML($obj->{'Ссылка'}->{'Свойство'}, array('{УникальныйИдентификатор}'));
				$npps[trim($obj->attributes()->{'Нпп'})] = $guid;
				list($name, $fullName, $inn, $kpp, $parentGuid) = 
					parseXML($obj->{'Свойство'}, array('Наименование', 'НаименованиеПолное', 'ИНН', 'КПП', '?ГоловнойКонтрагент'));
				try {
					$req->execute(array('guid' => $guid, 'name' => $name, 'fullName' => $fullName, 'inn' => $inn, 'kpp' => $kpp,
										 'parentGuid' => $parentGuid));
				} catch(PDOException $e) {
					print $e->getMessage()."\n";  
				}
//				print "{$guid}\t{$parentGuid}\t{$name}\t{$inn}\t{$kpp}\t".$req->rowCount()."\n";
			}
		}
		print "-------------------------------\n\n";
	}

	function parseServices($xml, $db) {
		global $npps;
		print "Сервисы:\n";
		$req = $db->prepare("INSERT INTO `services` (`guid`, `name`, `shortName`, `utility`) ".
									"VALUES (UNHEX(REPLACE(:guid, '-', '')), :name, :shortName, :utility) ".
								"ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `shortName` = VALUES(`shortName`), ".
														 "`utility` = VALUES(`utility`)");
		foreach ($xml->{'Объект'} as $obj) {
			if ('СправочникСсылка.СоД_Сервисы' == $obj->attributes()->{'Тип'}) {
				list($guid, $shortName) = parseXML($obj->{'Ссылка'}->{'Свойство'}, array('{УникальныйИдентификатор}', 'Наименование'));
				$npps[trim($obj->attributes()->{'Нпп'})] = $guid;
				list($name, $utility) = parseXML($obj->{'Свойство'}, array('Описание', 'Служебная'));
				$utility = ('true' == $utility ? 1 : 0); 
				try {
					$req->execute(array('guid' => $guid, 'name' => $name, 'shortName' => $shortName, 'utility' => $utility));
				} catch(PDOException $e) {
					print $e->getMessage()."\n";  
				}
//				print "{$guid}\t{$utility}\t{$shortName}\t{$name}\t".$req->rowCount()."\n";
			}
		}
		print "-------------------------------\n\n";
	}

	function parseDivisionTypes($xml, $db) {
		global $npps;
		print "Типы филиалов:\n";
		$req = $db->prepare("INSERT INTO `divisionTypes` (`guid`, `name`, `comment`) ".
									"VALUES (UNHEX(REPLACE(:guid, '-', '')), :name, :comment) ".
								"ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `comment` = VALUES(`comment`)");
		foreach ($xml->{'Объект'} as $obj) {
			if ('СправочникСсылка.СоД_ТипыФилиаловКонтрагентов' == $obj->attributes()->{'Тип'}) {
				list($guid, $name) = parseXML($obj->{'Ссылка'}->{'Свойство'}, array('{УникальныйИдентификатор}', 'Наименование'));
				$npps[trim($obj->attributes()->{'Нпп'})] = $guid;
				list($comment) = parseXML($obj->{'Свойство'}, array('Комментарий'));
				try {
					$req->execute(array('guid' => $guid, 'name' => $name, 'comment' => $comment));
				} catch(PDOException $e) {
					print $e->getMessage()."\n";  
				}
//				print "{$guid}\t{$name}\t{$comment}\t".$req->rowCount()."\n";
			}
		}
		print "-------------------------------\n\n";
	}

	function parsePartners($xml, $db) {
		global $npps;
		print "Партнёры:\n";
		$req = $db->prepare("INSERT INTO `partners` (`guid`, `name`, `address`) ".
									"VALUES (UNHEX(REPLACE(:guid, '-', '')), :name, :address) ".
								"ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `address` = VALUES(`address`)");
		foreach ($xml->{'Объект'} as $obj) {
			if ('СправочникСсылка.СоД_ПартнёрыServiceDesk' == $obj->attributes()->{'Тип'}) {
				list($guid) = parseXML($obj->{'Ссылка'}->{'Свойство'}, array('{УникальныйИдентификатор}'));
				$npps[trim($obj->attributes()->{'Нпп'})] = $guid;
				list($name, $address) = parseXML($obj->{'Свойство'}, array('Наименование', 'Адрес'));
				try {
					$req->execute(array('guid' => $guid, 'name' => $name, 'address' => $address));
				} catch(PDOException $e) {
					print $e->getMessage()."\n";  
				}
//				print "{$guid}\t{$name}\t{$address}\t".$req->rowCount()."\n";
			}
		}
		print "-------------------------------\n\n";
	}

	function parseUsers($xml, $db) {
		global $npps;
		print "Пользователи:\n";
		$req = $db->prepare("INSERT INTO `users` (`guid`, `lastName`, `firstName`, `middleName`, `login`, `email`, `phone`, `address`, ".
												 "`partner_guid`, `isDisabled`) ".
									"VALUES (UNHEX(REPLACE(:guid, '-', '')), :lName, :fName, :mName, :login, :email, :phone, :address, ".
											"UNHEX(REPLACE(:partnerGuid, '-', '')), :isDisabled) ".
								"ON DUPLICATE KEY UPDATE `lastName` = VALUES(`lastName`), `firstName` = VALUES(`firstName`), ".
														"`middleName` = VALUES(`middleName`), `login` = VALUES(`login`), ".
														"`email` = VALUES(`email`), `phone` = VALUES(`phone`), ".
														"`address` = VALUES(`address`), `partner_guid` = VALUES(`partner_guid`), ".
														"`isDisabled` = VALUES(`isDisabled`)");
		$req2 = $db->prepare("UPDATE `users` SET `rights` = :rights WHERE `guid` = UNHEX(REPLACE(:guid, '-', ''))");
		foreach ($xml->{'Объект'} as $obj) {
			if ('СправочникСсылка.СоД_ПользователиServiceDesk' == $obj->attributes()->{'Тип'}) {
				list($guid, $login) = parseXML($obj->{'Ссылка'}->{'Свойство'}, array('{УникальныйИдентификатор}', 'Код'));
				$npps[trim($obj->attributes()->{'Нпп'})] = $guid;
				list($lName, $fName, $mName, $email, $address, $isDisabled, $phone, $partnerGuid) = 
					parseXML($obj->{'Свойство'}, array('Фамилия', 'Имя', 'Отчество', 'email', 'Адрес', 'Запрещен', 'Телефон', '?Партнёр'));
				$isDisabled = ('true' == $isDisabled ? 1 : 0);
				try {
					$req->execute(array('guid' => $guid, 'lName' => $lName, 'fName' => $fName, 'mName' => $mName,
										 'login' => $login, 'isDisabled' => $isDisabled, 'phone' => $phone, 'email' => $email,
										 'address' => $address, 'partnerGuid' => $partnerGuid));
				} catch(PDOException $e) {
					print $e->getMessage()."\n";  
				}
//				print "{$guid}\t{$lName}\t${fName}\t{$mName}\t{$login}\n\t{$email}\t{$address}\t{$isDisabled}\t{$phone}\t{$partnerGuid}\t".$req->rowCount()."\n";
			}
		}
		foreach ($xml->{'НаборЗаписейРегистра'} as $obj) {
			if ('РегистрСведенийНаборЗаписей.СоД_ПраваПользователейServiceDesk' == $obj->attributes()->{'Тип'}) {
				list($guid, $rights) = 
					parseXML($obj->{'СтрокиНабораЗаписей'}->{'Объект'}->{'Свойство'}, array('?ПользовательServiceDesk', 'ВидПраваПользователяServiceDesk'));
				if ('engeneer' == $rights)
					$rights = 'engineer';
				try {
					$req2->execute(array('guid' => $guid, 'rights' => $rights));
				} catch(PDOException $e) {
					print $e->getMessage()."\n";  
				}
//				print "{$guid}\t{$rights}\t".$req2->rowCount()."\n";
			}
		}
		print "-------------------------------\n\n";
	}

	function parseContracts($xml, $db) {
		global $npps;
		print "Договоры:\n";
		$now = strtotime('now');
		$db->query("UPDATE `contracts` SET `update` = 0");
		$req = $db->prepare("INSERT INTO `contracts` (`guid`, `number`, `email`, `phone`, `address`, `yurAddress`, `isStopped`, ".
													 "`contractStart`, `contractEnd`, `isActive`, `contragent_guid`, `update`) ".
									"VALUES (UNHEX(REPLACE(:guid, '-', '')), :number, :email, :phone, :address, :yurAddress, ".
											":isStopped, :contractStart, :contractEnd, :isActive, ".
											"UNHEX(REPLACE(:contragentGuid, '-', '')), 1) ".
								"ON DUPLICATE KEY UPDATE `number` = VALUES(`number`), `email` = VALUES(`email`), `phone` = VALUES(`phone`), ".
														 "`address` = VALUES(`address`), `yurAddress` = VALUES(`yurAddress`), ".
														 "`isStopped` = VALUES(`isStopped`), `contractStart` = VALUES(`contractStart`), ".
														 "`contractEnd` = VALUES(`contractEnd`), `isActive` = VALUES(`isActive`), ".
														 "`contragent_guid` = VALUES(`contragent_guid`), `update` = 1"); 
								
		foreach ($xml->{'Объект'} as $obj) {
			if ('ДокументСсылка.СоД_СервисныйДоговор' == $obj->attributes()->{'Тип'}) {
				list($number, $start) = 
					parseXML($obj->{'Ссылка'}->{'Свойство'}, array('НомерДоговора', 'ДатаНачала'));
				list($guid, $address, $yurAddress, $isActive) = 
					parseXML($obj->{'ЗначениеПараметра'}, array('УИДОсновногоСервисногоДоговора', 'ПочтовыйАдресКонтрагента', 
																'ЮридическийАдресКонтрагента', 'АктивныйСервисныйДоговор')); 
				$npps[trim($obj->attributes()->{'Нпп'})] = $guid;
				list($end, $email, $phone, $contragentGuid) = 
					parseXML($obj->{'Свойство'}, array('ДатаОкончания', 'EMail', 'Телефон', '?Заказчик'));
				$isActive = ('true' == $isActive ? 1 : 0);
				$end = preg_replace('/T.*/', 'T23:59:59', $end); 
				list($stops) = parseXML($obj->{'ТабличнаяЧасть'}, array('!ПриостановкаДействия'));
				$isStopped = 0;
				foreach($stops->{'Запись'} as $div) {
					list($stopBegin, $stopEnd) = 
						parseXML($obj->{'Свойство'}, array('ДатаНачалаПриостановки', 'ДатаОкончанияПриостановки'));
					if (strtotime($stopBegin) < $now && $now < strtotime($stopEnd))
						$isStopped = 1;
				}
				try {
					$req->execute(array('guid' => $guid, 'number' => $number, 'email' => $email, 'phone' => $phone, 'address' => $address,
										 'yurAddress' => $yurAddress, 'contractStart' => $start, 'contractEnd' => $end, 
										 'isActive' => $isActive, 'isStopped' => $isStopped, 'contragentGuid' => $contragentGuid));
				} catch(PDOException $e) {
					print $e->getMessage()."\n";  
				}
//				print "{$guid}\t{$number}\t{$start}\t{$end}\t{$isActive}\n{$email}\t{$phone}\t{$contragentGuid}\n{$address}\n{$yurAddress}\t".$req->rowCount()."\n";
			}
		}
		$db->query("UPDATE `contracts` SET `isActive = 0 WHERE `update` = 0");
		print "-------------------------------\n\n";
	}
	
	function parseContractServices($xml, $db) {
		global $npps, $svcs;
		print "Сервисы по договорам:\n";
		$db->query("UPDATE `contractServices` SET `update` = 0");
		$req = $db->prepare("INSERT INTO `contractServices` (`contract_guid`, `service_guid`) ".
									"VALUES (UNHEX(REPLACE(:contractGuid, '-', '')), UNHEX(REPLACE(:serviceGuid, '-', ''))) ".
								"ON DUPLICATE KEY UPDATE `update` = 1");
		foreach ($xml->{'Объект'} as $obj) {
			if ('ДокументСсылка.СоД_СервисныйДоговор' == $obj->attributes()->{'Тип'}) {
				list($contractGuid) = parseXML($obj->{'ЗначениеПараметра'}, array('УИДОсновногоСервисногоДоговора'));
				list($services) = parseXML($obj->{'ТабличнаяЧасть'}, array('!Сервисы'));
				foreach($services->{'Запись'} as $srv) {
					list($uuid, $serviceGuid) = parseXML($srv->{'Свойство'}, array('UUID', '?Сервис'));
					$svcs[$uuid] = $serviceGuid;
					try {
						$req->execute(array('contractGuid' => $contractGuid, 'serviceGuid' => $serviceGuid));
					} catch(PDOException $e) {
						print $e->getMessage()."\n";  
					}
//					print "{$contractGuid}\t{$serviceGuid}".$req->rowCount()."\n";
				}
			}
		}
		$db->query("DELETE FROM `contractServices` WHERE `update` = 0");
		print "-------------------------------\n\n";
	}
	
	function parseDivisions($xml, $db) {
		global $npps;
		print "Филиалы по договорам:\n";
		$db->query("UPDATE `contractDivisions` SET `update` = 0");
		$req = $db->prepare("INSERT INTO `contractDivisions` (`guid`, `name`, `email`, `phone`, `address`, `yurAddress`, ".
											   "`contract_guid`, `contragent_guid`, `type_guid`, `isDisabled`, `update`) ". 
									"VALUES (UNHEX(REPLACE(:guid, '-', '')), :name, :email, :phone, :address, :yurAddress, ".
											"UNHEX(REPLACE(:contractGuid, '-', '')), UNHEX(REPLACE(:contragentGuid, '-', '')), ".
											"UNHEX(REPLACE(:typeGuid, '-', '')), 0, 1) ".
								"ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `email` = VALUES(`email`), `phone` = VALUES(`phone`), ".
								 						"`address` = VALUES(`address`), `yurAddress` = VALUES(`yurAddress`), ".
											   			"`contract_guid` = VALUES(`contract_guid`), ".
											   			"`contragent_guid` = VALUES(`contragent_guid`), `type_guid` = VALUES(`type_guid`), ".
											   			"`isDisabled` = 0, `update` = 1"); 
		foreach ($xml->{'Объект'} as $obj) {
			if ('ДокументСсылка.СоД_СервисныйДоговор' == $obj->attributes()->{'Тип'}) {
				list($contractGuid) = parseXML($obj->{'Ссылка'}->{'Свойство'}, array('{УникальныйИдентификатор}'));
				list($divisions) = parseXML($obj->{'ТабличнаяЧасть'}, array('!Филиалы'));
				foreach($divisions->{'Запись'} as $div) {
					list($guid, $name, $email, $phone, $address, $contragentGuid, $typeGuid) = 
						parseXML($div->{'ЗначениеПараметра'}, 
								 array('УИДФилиала', 'НаименованиеФилиала', 'EmailФилиала', 'ТелефонФилиала', 'ПочтовыйАдресФилиала',
								 	   '?Контрагент', '?ТипФилиала'));
					list($yurAddress) = parseXML($div->{'Свойство'}, array('АдресФилиала'));
					try {
						$req->execute(array('guid' => $guid, 'name' => $name, 'email' => $email, 'phone' => $phone, 
											 'address' => $address, 'yurAddress' => $yurAddress, 'contractGuid' => $contractGuid,  
											 'contragentGuid' => $contragentGuid, 'typeGuid' => $typeGuid));
					} catch(PDOException $e) {
						print $e->getMessage()."\n";  
					}
//					print "{$guid}\t{$name}\t{$email}\t{$phone}\n\t{$address}\t{$yurAddress}\n\t{$contractGuid}\t{$contragentGuid}\n{$typeGuid}\t".$req->rowCount()."\n";
				}
			}
		}
		$db->query("UPDATE `contractDivisions` SET `isDisabled` = 1 WHERE `update` = 0");
		print "-------------------------------\n\n";
	}

	function parseContractUsers($xml, $db) {
		print "Ответственные по договорам:\n";
		$db->query("UPDATE `userContracts` SET `update` = 0");
		$req = $db->prepare("INSERT INTO `userContracts` (`user_guid`, `contract_guid`) ".
									"VALUES (UNHEX(REPLACE(:userGuid, '-', '')), UNHEX(REPLACE(:contractGuid, '-', ''))) ".
								"ON DUPLICATE KEY UPDATE `update` = 1");
		foreach ($xml->{'НаборЗаписейРегистра'} as $obj) {
			if ('РегистрСведенийНаборЗаписей.СоД_ОтветственныеПользователиПоСервиснымДоговорам' == $obj->attributes()->{'Тип'}) {
				foreach($obj->{'СтрокиНабораЗаписей'}->{'Объект'} as $rec) {
					list($userGuid) = parseXML($rec->{'Свойство'}, array('?ПользовательServiceDesk'));
					list($contractGuid) = parseXML($rec->{'ЗначениеПараметра'}, array('УИДОсновногоСервисногоДоговора'));
					try {
						$req->execute(array('userGuid' => $userGuid, 'contractGuid' => $contractGuid));
					} catch(PDOException $e) {
						print $e->getMessage()."\n";  
					}
//					print "{$userGuid}\t{$contractGuid}\t".$req->rowCount()."\n";
				}
			}
		}
		$db->query("DELETE FROM `userContracts` WHERE `update` = 0");
		print "-------------------------------\n\n";
	}

	function parseDivisionUsers($xml, $db) {
		print "Ответственные по филиалам:\n";
		$db->query("UPDATE `userContractDivisions` SET `update` = 0");
		$req = $db->prepare("INSERT INTO `userContractDivisions` (`user_guid`, `contractDivision_guid`) ".
									"VALUES (UNHEX(REPLACE(:userGuid, '-', '')), UNHEX(REPLACE(:divisionGuid, '-', ''))) ".
								"ON DUPLICATE KEY UPDATE `update` = 1");
		foreach ($xml->{'НаборЗаписейРегистра'} as $obj) {
			if ('РегистрСведенийНаборЗаписей.СоД_ОтветственныеПользователиПоФилиаламСервисныхДоговоров' == $obj->attributes()->{'Тип'}) {
				foreach($obj->{'СтрокиНабораЗаписей'}->{'Объект'} as $rec) {
					list($userGuid) = parseXML($rec->{'Свойство'}, array('?ПользовательServiceDesk'));
					list($divisionGuid) = parseXML($rec->{'ЗначениеПараметра'}, array('УИДФилиала'));
					try {
						$req->execute(array('userGuid' => $userGuid, 'divisionGuid' => $divisionGuid));
					} catch(PDOException $e) {
						print $e->getMessage()."\n";  
					}
//					print "{$userGuid}\t{$divisionGuid}\t".$req->rowCount()."\n";
				}
			}
		}
		$db->query("DELETE FROM `userContractDivisions` WHERE `update` = 0");
		print "-------------------------------\n\n";
	}
	
	function parseDivisionPartners($xml, $db) {
		print "Партнёры по филиалам:\n";
		$db->query("UPDATE `partnerDivisions` SET `update` = 0");
		$req = $db->prepare("INSERT INTO `partnerDivisions` (`partner_guid`, `contractDivision_guid`) ".
									"VALUES (UNHEX(REPLACE(:partnerGuid, '-', '')), UNHEX(REPLACE(:divisionGuid, '-', ''))) ".
								"ON DUPLICATE KEY UPDATE `update` = 1");
		foreach ($xml->{'НаборЗаписейРегистра'} as $obj) {
			if ('РегистрСведенийНаборЗаписей.СоД_ПартнёрыПоФилиаламСервисныхДоговоров' == $obj->attributes()->{'Тип'}) {
				foreach($obj->{'СтрокиНабораЗаписей'}->{'Объект'} as $rec) {
					list($partnerGuid) = parseXML($rec->{'Свойство'}, array('?ПартнёрServiceDesk'));
					list($divisionGuid) = parseXML($rec->{'ЗначениеПараметра'}, array('УИДФилиала'));
					try {
						$req->execute(array('partnerGuid' => $userGuid, 'divisionGuid' => $divisionGuid));
					} catch(PDOException $e) {
						print $e->getMessage()."\n";  
					}
					print "{$partnerGuid}\t{$divisionGuid}\t".$req->rowCount()."\n";
				}
			}
		}
		$db->query("DELETE FROM `partnerDivisions` WHERE `update` = 0");
		print "-------------------------------\n\n";
	}
	
	function parseWorkplaces($xml, $db) {
		global $npps;
		print "Рабочие места:\n";
		$db->query("UPDATE `divisionWorkplaces` SET `update` = 0");
		$req = $db->prepare("INSERT INTO `divisionWorkplaces` (`guid`, `name`, `description`, `division_guid`) ".
									"VALUES (UNHEX(REPLACE(:guid, '-', '')), :name, :description, UNHEX(REPLACE(:divisionGuid, '-', ''))) ".
								"ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`), ".
														"`division_guid` = VALUES(`division_guid`)");
		foreach ($xml->{'Объект'} as $obj) {
			if ('СправочникСсылка.СоД_РабочиеМестаФилиаловКонтрагентов' == $obj->attributes()->{'Тип'}) {
				list($guid, $name) = parseXML($obj->{'Ссылка'}->{'Свойство'}, array('{УникальныйИдентификатор}', 'Наименование'));
				$npps[trim($obj->attributes()->{'Нпп'})] = $guid;
				list($description) = parseXML($obj->{'Свойство'}, array('Описание'));
				list($divisionGuid) = parseXML($obj->{'ЗначениеПараметра'}, array('УИДФилиала'));
				try {
					$req->execute(array('guid' => $guid, 'name' => $name, 'description' => $description, 'divisionGuid' => $divisionGuid));
				} catch(PDOException $e) {
					print $e->getMessage()."\n";  
				} 
//				print "{$guid}\t{$name}\t{$description}\t{$divisionGuid}\t".$req->rowCount()."\n";
			}
		}
		print "-------------------------------\n\n";
	}

	function parseContractSLAs($xml, $db) {
		global $npps, $svcs, $levels;
		print "SLA по договорам:\n";
		$db->query("UPDATE `divServicesSLA` SET `update` = 0");
		$req = $db->prepare("INSERT INTO `divServicesSLA` (`guid`, `contract_guid`, `service_guid`, `divType_guid`, `slaLevel`, ".
														  "`dayType`, `toReact`, `toFix`, `toRepair`, `quality`, `startDayTime`, ".
														  "`endDayTime`, `isDefault`, `update`) ".
									"VALUES (UNHEX(REPLACE(UUID(), '-', '')), UNHEX(REPLACE(:contractGuid, '-', '')), ".
											"UNHEX(REPLACE(:serviceGuid, '-', '')), UNHEX(REPLACE(:divTypeGuid, '-', '')), ".
											":slaLevel, :dayType, :toReact, :toFix, :toRepair, :quality, :startDayTime, :endDayTime, ".
											":isDefault, 1) ".
								"ON DUPLICATE KEY UPDATE `toReact` = VALUES(`toReact`), `toFix` = VALUES(`toFix`), ".
														"`toRepair` = VALUES(`toRepair`), `quality` = VALUES(`quality`), ".
														"`startDayTime` = VALUES(`startDayTime`), `endDayTime` = VALUES(`endDayTime`), ".
														"`isDefault` = VALUES(`isDefault`), `update` = 1");
		foreach ($xml->{'Объект'} as $obj) {
			if ('ДокументСсылка.СоД_СервисныйДоговор' == $obj->attributes()->{'Тип'}) {
				list($contractGuid) = parseXML($obj->{'ЗначениеПараметра'}, array('УИДОсновногоСервисногоДоговора'));
				list($slas) = parseXML($obj->{'ТабличнаяЧасть'}, array('!СоставСервиса'));
				foreach($slas->{'Запись'} as $sla) {
					list($uuid, $toFix, $toRepair, $toReact, $atWeekends, $atWorkdays, $quality, $start, $end, $isDefault, $divTypeGuid,
						 $level) = 
						parseXML($sla->{'Свойство'}, array('UUID', 'ВремяДоВосстановления', 'ВремяДоЗавершения', 'ВремяРеакции',
														   'ВыходныеДни', 'РабочиеДни', 'Качество', 'НачалоДня', 'КонецДня', 
														   'ПоУмолчанию', '?ТипФилиалаКонтрагента', 'УровеньSLA'));
					$serviceGuid = $svcs[$uuid];
					$dayType = array();
					if ('true' == $atWorkdays)
						$dayType[] = 'work';
					if ('true' == $atWeekends)
						$dayType[] = 'weekend';
					$dayType = implode(',', $dayType);
					$level = $levels[$level];
					$start = preg_replace('/.*?T/', '', $start);
					$end = preg_replace('/.*?T/', '', $end);
					$isDefault = ('true' == $isDefault ? 1 : 0);
					try {
						$req->execute(array('contractGuid' => $contractGuid, 'serviceGuid' => $serviceGuid, 'divTypeGuid' => $divTypeGuid,
											'slaLevel' => $level, 'dayType' => $dayType, 'toReact' => $toReact, 'toFix' => $toFix, 
											'toRepair' => $toRepair, 'quality' => $quality, 'startDayTime' => $start, 'endDayTime' => $end,
											'isDefault' => $isDefault));
					} catch(PDOException $e) {
						print $e->getMessage()."\n";  
					}
//					print "{$contractGuid}\t{$serviceGuid}\t{$divTypeGuid}\t{$level}\t{$dayType}\n\t{$toReact}\t{$toFix}\t{$toRepair}\t{$quality}\t{$start}\t{$end}\t{$isDefault}\t".$req->rowCount()."\n";
				}
			}
		}
		$db->query("DELETE FROM `divServicesSLA` WHERE `update` = 0");
		print "-------------------------------\n\n";
	}

	function parseEquipment($xml, $db) {
		global $npps;
		print "Оборудование:\n";
		$req = $db->prepare("INSERT INTO `equipment` (`guid`, `serviceNumber`, `serialNumber`, `warrantyEnd`, `rem`, `equipmentModel_guid`) ".
									"VALUES (UNHEX(REPLACE(:guid, '-', '')), :serviceNumber, :serialNumber, :warrantyEnd, :rem, ".
											"UNHEX(REPLACE(:equipmentModelGuid, '-', ''))) ".
								"ON DUPLICATE KEY UPDATE `serviceNumber` = VALUES(`serviceNumber`), `serialNumber` = VALUES(`serialNumber`), ".
														"`warrantyEnd` = VALUES(`warrantyEnd`), `rem` = VALUES(`rem`), ".
														"`equipmentModel_guid` = VALUES(`equipmentModel_guid`)");
		$req1 = $db->prepare("UPDATE `equipment` SET `onService` = :onService, ".
													"`contractDivision_guid` = UNHEX(REPLACE(:contractDivisionGuid, '-', '')), ".
													"`workplace_guid` = UNHEX(REPLACE(:workplaceGuid, '-', '')), ".
													"`contract_guid` = UNHEX(REPLACE(:contractGuid, '-', '')) ".
								"WHERE `guid` = UNHEX(REPLACE(:guid, '-', ''))");
		foreach ($xml->{'Объект'} as $obj) {
			if ('СправочникСсылка.СоД_ОборудованиеФилиаловКонтрагентов' == $obj->attributes()->{'Тип'}) {
				list($guid, $serviceNumber) = parseXML($obj->{'Ссылка'}->{'Свойство'}, array('{УникальныйИдентификатор}', 'Код'));
				$npps[trim($obj->attributes()->{'Нпп'})] = $guid;
				list($rem, $modelGuid, $warrantyEnd, $serialNumber) = 
					parseXML($obj->{'Свойство'}, array('Комментарий', '?МодельОборудования', 'ОкончаниеГарантии', 'СерийныйНомер'));
				$warrantyEnd = preg_replace('/T.*/', 'T23:59:59', $warrantyEnd);
				try {
					$req->execute(array('guid' => $guid, 'serviceNumber' => $serviceNumber, 'serialNumber' => $serialNumber, 
										'warrantyEnd' => $warrantyEnd, 'rem' => $rem, 'equipmentModelGuid' => $modelGuid));
				} catch(PDOException $e) {
					print $e->getMessage()."\n";  
				} 
//				print "{$guid}\t{$serviceNumber}\t{$serialNumber}\t{$warrantyEnd}\t{$rem}\n\t{$modelGuid}\t".$req->rowCount()."\n";
			}
		}
		foreach ($xml->{'НаборЗаписейРегистра'} as $obj) {
			if ('РегистрСведенийНаборЗаписей.СоД_ОборудованиеСервисныхДоговоров' == $obj->attributes()->{'Тип'}) {
				foreach($obj->{'СтрокиНабораЗаписей'}->{'Объект'} as $eq) {
					list($guid, $onService, $workplaceGuid) = 
						parseXML($eq->{'Свойство'}, array('?Оборудование', 'НаходитсяНаОбслуживании', '?РабочееМесто'));
					list($divisionGuid, $contractGuid) = 
						parseXML($eq->{'ЗначениеПараметра'}, array('УИДФилиала', 'УИДОсновногоСервисногоДоговора'));
					$onService = ('true' == $onService ? 1 : 0);  
					try {
						$req1->execute(array('guid' => $guid, 'onService' => $onService, 'contractDivisionGuid' => $divisionGuid, 
											'workplaceGuid' => $workplaceGuid, 'contractGuid' => $contractGuid));
					} catch(PDOException $e) {
						print $e->getMessage()."\n";  
					} 
//					print "{$guid}\t{$onService}\t{$divisionGuid}\t{$workplaceGuid}\t".$req1->rowCount()."\n";
				}
			}
		}
		print "-------------------------------\n\n";
	}

	function parseEquipmentLog($xml, $db) {
		global $npps;
		print "Журналы оборудования:\n";
		$req = $db->prepare("INSERT INTO `equipmentOnServiceLog` (`timestamp`, `equipment_guid`, `newState`, `contractDivision_guid`) ".
								"SELECT `n`.`time`, `n`.`equipment`, `n`.`state`, IFNULL(`n`.`division`, `od`.`contractDivision_guid`) ".
  									"FROM ( ".
    									"SELECT MAX(`timestamp`) AS `ts` ".
      										"FROM `equipmentOnServiceLog` ". 
      										"WHERE `equipment_guid` = UNHEX(REPLACE(:equipmentGuid, '-', '')) ".
									") AS `t` ".
  									"JOIN `equipmentOnServiceLog` AS `v` ". 
    									"ON `v`.`equipment_guid` = UNHEX(REPLACE(:equipmentGuid, '-', '')) AND `v`.`timestamp` = `t`.`ts` ".
  									"RIGHT JOIN ( ".
    									"SELECT :state AS `state`, :time AS `time`, UNHEX(REPLACE(:divisionGuid, '-', '')) AS `division`, ".
    										   "UNHEX(REPLACE(:equipmentGuid, '-', '')) AS `equipment` ".
  									") AS `n` ON `n`.`state` = `v`.`newState` ".
									"LEFT JOIN `equipmentOnServiceLog` AS `od` ".
										"ON `od`.`equipment_guid` = UNHEX(REPLACE(:equipmentGuid, '-', '')) AND `od`.`timestamp` = `t`.`ts` ".
									"WHERE `v`.`timestamp` IS NULL");
		$req1 = $db->prepare("INSERT INTO `equipmentWorkplaceLog` (`timestamp`, `equipment_guid`, `workplace_guid`) ".
								"SELECT `n`.`time`, `n`.`equipment`, `n`.`workplace` ".
  									"FROM ( ".
    									"SELECT MAX(`timestamp`) AS `ts` ".
      										"FROM `equipmentWorkplaceLog` ". 
      										"WHERE `equipment_guid` = UNHEX(REPLACE(:equipmentGuid, '-', '')) ".
									") AS `t` ".
  									"JOIN `equipmentWorkplaceLog` AS `v` ". 
    									"ON `v`.`equipment_guid` = UNHEX(REPLACE(:equipmentGuid, '-', '')) AND `v`.`timestamp` = `t`.`ts` ".
  									"RIGHT JOIN ( ".
    									"SELECT :time AS `time`, UNHEX(REPLACE(:workplaceGuid, '-', '')) AS `workplace`, ".
    										   "UNHEX(REPLACE(:equipmentGuid, '-', '')) AS `equipment` ".
  									") AS `n` ON `n`.`workplace` = `v`.`workplace_guid` ".
									"WHERE `v`.`timestamp` IS NULL");
		foreach ($xml->{'НаборЗаписейРегистра'} as $obj) {
			if ('РегистрСведенийНаборЗаписей.СоД_ИсторияОборудованияПоРабочимМестам' == $obj->attributes()->{'Тип'}) {
				foreach($obj->{'СтрокиНабораЗаписей'}->{'Объект'} as $log) {
					list($equipmentGuid, $time, $workplaceGuid, $state) = 
						parseXML($log->{'Свойство'}, array('?Оборудование', 'Период', '?РабочееМесто', 'НаходитсяНаОбслуживании'));
					list($divisionGuid) = parseXML($log->{'ЗначениеПараметра'}, array('УИДФилиала'));
					$state = ('true' == $state ? 1 : 0);
					try {
						$req->execute(array('equipmentGuid' => $equipmentGuid, 'time' => $time, 'divisionGuid' => $divisionGuid, 
											'state' => $state));
						$req1->execute(array('equipmentGuid' => $equipmentGuid, 'time' => $time, 'workplaceGuid' => $workplaceGuid));
					} catch(PDOException $e) {
						print $e->getMessage()."\n";  
					} 
//					print "{$equipmentGuid}\t{$time}\t{$divisionGuid}\t{$state}\t{$workplaceGuid}\t".$req->rowCount()."/".$req1->rowCount()."\n";
				}
			}
		}
		print "-------------------------------\n\n";
	}

	function buildLastStates($xml, $db) {
		global $states, $lastStates, $prevStates, $lastEquipment;
		print "Текущие состояния заявок:\n";
		$req = $db->prepare("SELECT `guid`, `currentState`, `onWait`, `stateChangedAt`, HEX(`equipment_guid`) AS `eq` FROM `requests`");
		$req->execute();
		while ($row = $req->fetch(PDO::FETCH_ASSOC)) {
			$prevStates[$row['guid']] = array($row['currentState'], $row['onWait'], $row['stateChangedAt']);
			$lastStates[$row['guid']] = $prevStates[$row['guid']]; 
			$lastEquipment[$row['guid']] = formatGuid($row['eq']);
//			print formatGuid($row['guid'])."\t{$lastEquipment[$row['guid']]}\n";
		}
		foreach ($xml->{'Объект'} as $obj) {
			if ('ДокументСсылка.СоД_ЗаявкаОтКлиента' == $obj->attributes()->{'Тип'}) {
				list($guid) = parseXML($obj->{'Ссылка'}->{'Свойство'}, array('{УникальныйИдентификатор}'));
//				print "{$guid}\n";
				list($events) = parseXML($obj->{'ТабличнаяЧасть'}, array('!Коментарии'));
				$evList = array();
				foreach($events->{'Запись'} as $event) {
					list($evName, $evTime, $evState) = 
						parseXML($event->{'ЗначениеПараметра'}, array('Event', 'TimeStamp', 'СтатусЗаявки'));
					$evList[$evTime] = array($evName, $evState);
				}
				ksort($evList);
				foreach($evList as $evTime => $evt) {
					list($evName, $evState) = $evt;
					if ('Изменен статус заявки' == $evName) {
						if ('_6Перенесена' == $evState) {
							$onWait = 1;
							$currentState = $lastStates[$guid][0];
						} else {
							$onWait = 0;
							$currentState = $states[$evState];
						}
						$evTime = preg_replace('/T/', ' ', $evTime);
						if (!isset($lastStates[$guid]) || $evTime > $lastStates[$guid][2]) {
							$lastStates[$guid] = array($currentState, $onWait, $evTime);
//							print "\t{$evTime}\t{$currentState}\t{$onWait}\n";
						}
					}
				}
			}
		}
		print "-------------------------------\n\n";
	}

	function parseRequests($xml, $db) {
		global $npps, $states, $levels, $lastStates, $prevStates;
		print "Заявки:\n";
		$req = $db->prepare("INSERT INTO `requests` (`guid`, `problem`, `createdAt`, `reactBefore`, `reactedAt`, `fixBefore`, `fixedAt`, ".
													 "`repairBefore`, `repairedAt`, `currentState`, `stateChangedAt`, ".
													 "`contactPerson_guid`, `contractDivision_guid`, `slaLevel`, `engineer_guid`, ".
													 "`equipment_guid`, `service_guid`, `onWait`, `solutionProblem`, `solution`, ".
													 "`solutionRecomendation`, `toReact`, `toFix`, `toRepair`, `num1c`) ".
									"VALUES (UNHEX(REPLACE(:guid, '-', '')), :problem, :createdAt, :reactBefore, :reactedAt, :fixBefore, ".
											":fixedAt, :repairBefore, :repairedAt, :currentState, :stateChangedAt, ".
											"UNHEX(REPLACE(:contactPersonGuid, '-', '')), UNHEX(REPLACE(:contractDivisionGuid, '-', '')), ".
											":slaLevel, UNHEX(REPLACE(:engineerGuid, '-', '')), UNHEX(REPLACE(:equipmentGuid, '-', '')), ".
											"UNHEX(REPLACE(:serviceGuid, '-', '')), :onWait, :solutionProblem, :solution, :solutionRecomendation, ".
											":toReact, :toFix, :toRepair, :num1c) ".
								"ON DUPLICATE KEY UPDATE `problem` = VALUES(`problem`), `createdAt` = VALUES(`createdAt`), `reactBefore` = VALUES(`reactBefore`), ".
													 "`reactedAt` = VALUES(`reactedAt`), `fixBefore` = VALUES(`fixBefore`), `fixedAt` = VALUES(`fixedAt`), ".
													 "`repairBefore` = VALUES(`repairBefore`), `repairedAt` = VALUES(`repairedAt`), `currentState` = VALUES(`currentState`), ".
													 "`stateChangedAt` = VALUES(`stateChangedAt`), `contactPerson_guid` = VALUES(`contactPerson_guid`), ".
													 "`contractDivision_guid` = VALUES(`contractDivision_guid`), `slaLevel` = VALUES(`slaLevel`), ".
													 "`engineer_guid` = VALUES(`engineer_guid`), `equipment_guid` = VALUES(`equipment_guid`), ".
													 "`service_guid` = VALUES(`service_guid`), `onWait` = VALUES(`onWait`), `solutionProblem` = VALUES(`solutionProblem`), ".
													 "`solution` = VALUES(`solution`), `solutionRecomendation` = VALUES(`solutionRecomendation`), ".
													 "`toReact` = VALUES(`toReact`), `toFix` = VALUES(`toFix`), `toRepair` = VALUES(`toRepair`), ".
													 "`num1c` = VALUES(`num1c`)");
		$req1 = $db->prepare("INSERT INTO `requestEvents` (`timestamp`, `event`, `newState`, `request_guid`, `user_guid`) ".
									"VALUES(:createdAt, 'open', 'received', UNHEX(REPLACE(:guid, '-', '')), ".
											"UNHEX(REPLACE(:contactPersonGuid, '-', '')))");
		foreach ($xml->{'Объект'} as $obj) {
			if ('ДокументСсылка.СоД_ЗаявкаОтКлиента' == $obj->attributes()->{'Тип'}) {
				list($guid, $num1c, $createdAt) = 
					parseXML($obj->{'Ссылка'}->{'Свойство'}, array('{УникальныйИдентификатор}', 'Номер', 'Дата'));
				$npps[trim($obj->attributes()->{'Нпп'})] = $guid;
				list($reactBefore, $reactedAt, $fixBefore, $fixedAt, $repairBefore, $repairedAt, 
					 $contactPersonGuid, $contractDivisionGuid, $slaLevel, $engineerGuid, $equipmentGuid, $serviceGuid, 
					 $solutionProblem, $solution, $solutionRecomendation, $toReact, $toFix, $toRepair, $dayType_work, $dayType_weekend,
					 $startDayTime, $endDayTime, $number) = 
					parseXML($obj->{'ЗначениеПараметра'}, array('reactBefore', 'reactedAt', 'fixBefore', 'fixedAt', 'repairBefore', 
													   'repairedAt', '?contactPerson', 'contractDivision', 'slaLevel', '?engeneer', 
													   '?equipment', '?service', 'solutionProblem', 'solution', 'solutionRecomendation', 
													   'toReact', 'toFix', 'toRepair', 'dayType_work', 'dayType_weekend', 'startDayTime',
													   'endDayTime', 'НомерSD'));
				list($problem) = 
					parseXML($obj->{'Свойство'}, array('Неисправность'));
				$currentState = $lastStates[$guid][0];
				$onWait = $lastStates[$guid][1];
				$stateChangedAt = $lastStates[$guid][2];
				$slaLevel = (isset($levels[$slaLevel]) ? $levels[$slaLevel] : 'medium');
				try {
					$req->execute(array('guid' => $guid, 'problem' => $problem, 'createdAt' => $createdAt, 'reactBefore' => $reactBefore, 
										'reactedAt' => $reactedAt, 'fixBefore' => $fixBefore, 'fixedAt' => $fixedAt,
										'repairBefore' => $repairBefore, 'repairedAt' => $repairedAt, 'currentState' => $currentState, 
										'stateChangedAt' => $stateChangedAt, 'contactPersonGuid' => $contactPersonGuid, 
										'contractDivisionGuid' => $contractDivisionGuid, 'slaLevel' => $slaLevel, 
										'engineerGuid' => $engineerGuid, 'equipmentGuid' => $equipmentGuid, 'serviceGuid' => $serviceGuid,
										'onWait' => $onWait, 'solutionProblem' => $solutionProblem, 'solution' => $solution, 
										'solutionRecomendation' => $solutionRecomendation, 'toReact' => $toReact, 'toFix' => $toFix, 
										'toRepair' => $toRepair, 'num1c' => $num1c));
					if (!isset($prevStates[$guid])) {
						$req1->execute(array('guid' => $guid, 'createdAt' => $createdAt, 'contactPersonGuid' => $contactPersonGuid));
						$prevStates[$guid] = array('open', 0, $createdAt);
					}
				} catch(PDOException $e) {
					print $e->getMessage()."\n";  
				}
//				print "{$guid}\t{$number}\t{$num1c}\t{$createdAt}\n{$problem}\n{$reactBefore}\t{$reactedAt}\t{$fixBefore}\t{$fixedAt}\t{$repairBefore}\t".
//					  "{$repairedAt}\n{$contactPersonGuid}\t{$contractDivisionGuid}\t{$engineerGuid}\t{$equipmentGuid}\t{$serviceGuid}\n".
//					  "{$slaLevel}\t{$currentState}\t{$onWait}\t{$stateChangedAt}\t{$toReact}\t{$toFix}\t{$toRepair}\n".
//					  "{$solutionProblem}\n{$solution}\n{$solutionRecomendation}\n".$req->rowCount()."\n";
			}
		}
		print "-------------------------------\n\n";
	}

	function parseRequestsHistory($xml, $db) {
		global $states, $prevStates, $events, $lastEquipment;
		print "История заявок:\n";
		$req = $db->prepare("INSERT IGNORE INTO `requestEvents` (`timestamp`, `event`, `text`, `newState`, `request_guid`, `user_guid`) ".
									"VALUES (:time, :event, :comment, :newState, UNHEX(REPLACE(:guid, '-', '')), ".
											"UNHEX(REPLACE(:userGuid, '-', '')))");
		$req1 = $db->prepare("SELECT `eq`.`serviceNumber` AS `servNum`, `mfg`.`name` AS `mfg`, `mdl`.`name` AS `model`, ". 
									"`eq`.`serialNumber` AS `sn` ".
							 	"FROM `equipment` AS `eq` ".
								"LEFT JOIN `equipmentModels` AS `mdl` ON `mdl`.`guid` = `eq`.`equipmentModel_guid` ".
								"LEFT JOIN `equipmentManufacturers` AS `mfg` ON `mfg`.`guid` = `mdl`.`equipmentManufacturer_guid` ".
								"WHERE `eq`.`guid` = UNHEX(REPLACE(:guid, '-', ''))");
		foreach ($xml->{'Объект'} as $obj) {
			if ('ДокументСсылка.СоД_ЗаявкаОтКлиента' == $obj->attributes()->{'Тип'}) {
				list($guid) = parseXML($obj->{'Ссылка'}->{'Свойство'}, array('{УникальныйИдентификатор}'));
				print "{$guid}\n";
				list($events) = parseXML($obj->{'ТабличнаяЧасть'}, array('!Коментарии'));
				$evList = array();
				foreach($events->{'Запись'} as $event) {
					list($evName, $evTime, $evState, $evUser, $evComment, $equipmentGuid) = 
						parseXML($event->{'ЗначениеПараметра'}, array('Event', 'TimeStamp', 'СтатусЗаявки', '?userSD', 'Комментарий', 
										 							  '?Оборудование'));
					$evList[$evTime] = array($evName, $evState, $evUser, $evComment);
				}
				ksort($evList);
				foreach ($evList as $evTime => $evt) {
					list($evName, $evState, $evUser, $evComment) = $evt;
					print "\t{$evTime}\t{$evName}\t{$evState}\t{$evUser}\t{$evComment}\n\t\t";
					switch($evName) {
						case 'Изменен статус заявки':
							if ('_5Отменена' == $prevStates[$guid]) {
								try {
									$req->execute(array('time' => $evTime, 'event' => 'unCancel', 'comment' => $evComment, 
														'newState' => $evState, 'guid' => $guid, 'userGuid' => $evUser));
								} catch(PDOException $e) {
									print $e->getMessage()."\n";  
								}
								$evComment = '';
							} else if ('_4Завершена' == $prevStates[$guid]) {
								try {
									$req->execute(array('time' => $evTime, 'event' => 'unClose', 'comment' => $evComment, 
														'newState' => $evState, 'guid' => $guid, 'userGuid' => $evUser));
								} catch(PDOException $e) {
									print $e->getMessage()."\n";  
								}
								$evComment = '';
							}
							if ('_6Перенесена' == $evState) {
								try {
									$req->execute(array('time' => $evTime, 'event' => 'onWait', 'comment' => $evComment, 
														'newState' => $prevStates[$guid][0], 'guid' => $guid, 'userGuid' => $evUser));
								} catch(PDOException $e) {
									print $e->getMessage()."\n";  
								}
								$prevStates[$guid] = array($prevStates[$guid][0], 1, $evTime);
							} else {
								$evState = $states[$evState];
								if (isset($prevStates[$guid])) {
									try {
										if (1 == $prevStates[$guid][1])
											$req->execute(array('time' => $evTime, 'event' => 'offWait', 'comment' => $evComment, 
																'newState' => $prevStates[$guid][0], 'guid' => $guid, 'userGuid' => $evUser));
										if ($evState != $prevStates[$guid])
											$req->execute(array('time' => $evTime, 'event' => 'changeState', 'comment' => null, 
																'newState' => $evState, 'guid' => $guid, 'userGuid' => $evUser));
									} catch(PDOException $e) {
										print $e->getMessage()."\n";  
									}
								}
								$prevStates[$guid] = array($evState, 0, $evTime);
							}
							break;
						case 'Добавлен комментарий':
							try {
								$req->execute(array('time' => $evTime, 'event' => 'comment', 'comment' => $evComment, 
													'newState' => $prevStates[$guid][0], 'guid' => $guid, 'userGuid' => $evUser));
							} catch(PDOException $e) {
								print $e->getMessage()."\n";  
							}
							break;
						case 'Изменено оборудование':
							if (!isset($lastEquipment[$guid]))
								$lastEquipment[$guid] = null;
							if ($equipmentGuid != $lastEquipment[$guid]) {
								try {
									$req1->execute(array('guid' => $lastEquipment[$guid]));
									$evComment = "Старое оборудование: ";
									if ($row = $req1->fetch(PDO::FETCH_ASSOC))
										$evComment .= "{$row['servNum']} - {$row['mfg']} {$row['model']} ({$row['sn']})\n";
									else 
										$evComment .= "не указано\n";
									$req1->execute(array('guid' => $equipmentGuid));
									$evComment .= "Новое оборудование: ";
									if ($row = $req1->fetch(PDO::FETCH_ASSOC))
										$evComment .= "{$row['servNum']} - {$row['mfg']} {$row['model']} ({$row['sn']})";
									else 
										$evComment .= "не указано";
									$req->execute(array('time' => $evTime, 'event' => 'eqChange', 'comment' => $evComment, 
														'newState' => $prevStates[$guid][0], 'guid' => $guid, 'userGuid' => $evUser));
								} catch(PDOException $e) {
									print $e->getMessage()."\n";  
								}
								print "Equipment: {$equipmentGuid}\n{$evComment}\n";
								$lastEquipment[$guid] = $equipmentGuid;
							}
							break;
					}
//					print "\t{$evTime}\t{$evName}\t{$evState}\t{$evComment}\t".$req->rowCount()."\n"; 
				}
			}
		}
		print "-------------------------------\n\n";
	}

	function parseDocuments($xml, $db) {
		global $npps, $fileStorage;
		print "Документы:\n";
		$req = $db->prepare("INSERT IGNORE INTO `requestEvents` (`timestamp`, `event`, `text`, `newState`, `request_guid`, `user_guid`) ".
									"VALUES (:time, 'addDocument', NULL, NULL, UNHEX(REPLACE(:requestGuid, '-', '')), ".
											"UNHEX(REPLACE(:userGuid, '-', '')))");
		$req1 = $db->prepare("INSERT IGNORE INTO `documents` (`guid`, `name`, `uniqueName`, `requestEvent_id`) ".
									"VALUES (UNHEX(REPLACE(:guid, '-', '')), :name, :uniqueName, :eventId)");
		$req2 = $db->prepare("SELECT `id` FROM `requests` WHERE `guid` = UNHEX(REPLACE(:requestGuid, '-', ''))"); 
		foreach ($xml->{'Объект'} as $obj) {
			if ('СправочникСсылка.ХранилищеДополнительнойИнформации' == $obj->attributes()->{'Тип'}) {
				list($guid) = parseXML($obj->{'Ссылка'}->{'Свойство'}, array('{УникальныйИдентификатор}'));
				$npps[trim($obj->attributes()->{'Нпп'})] = $guid;
				list($name, $b64) = parseXML($obj->{'Свойство'}, array('ИмяФайла', 'Хранилище'));
				list($requestGuid, $userGuid, $time) = 
					parseXML($obj->{'ЗначениеПараметра'}, array('УИДЗаявкиОтКлиента', '?userSD', 'TimeStamp'));
				try {
					$req2->execute(array('requestGuid' => $requestGuid));
				} catch(PDOException $e) {
					print $e->getMessage()."\n";  
				}
				$requestNum = $req2->fetchColumn();
				print "{$requestGuid} => {$requestNum}\n";
				if (!($requestNum))
					continue;
				if (!file_exists("{$fileStorage}/{$requestNum}"))
					mkdir("{$fileStorage}/{$requestNum}", 0755);
				$ext = '';
				if (preg_match('/(\.[^\.]+)$/', $name, $match))
					$ext = $match[1];
				$tempName = "{$guid}{$ext}";
				$handle = fopen("{$fileStorage}/{$requestNum}/{$tempName}", 'w');
				fwrite($handle, base64_decode($b64));
				fclose($handle);
				if (!file_exists("{$fileStorage}/{$requestNum}/{$tempName}"))
					continue;
				try {
					$req->execute(array('time' => $time, 'requestGuid' => $requestGuid, 'userGuid' => $userGuid));
					$eventId = $db->lastInsertId();
					$req1->execute(array('guid' => $guid, 'name' => $name, 'uniqueName' => $tempName, 'eventId' => $eventId));
				} catch(PDOException $e) {
					print $e->getMessage()."\n";  
				} 
//				print "{$guid}\t{$name}\t{$tempName}\t".$req->rowCount()."\n";
			}
		}
		print "-------------------------------\n\n";
	}
	
//*******************************************************************************************************************//
// Основная программа                                                                                                //
//*******************************************************************************************************************//

	try {
		$pdo = new PDO("mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=UTF8;", $dbUser, $dbPass);
	} catch(PDOException $e) {
		print $e->getMessage();  
	} 
	$pdo->query("SET NAMES utf8");
	
	$syncInMsg = '';
	$syncOutMsg = '';
	$req = $pdo->prepare("SELECT `v1`.`value`, `v2`.`value` ".
							"FROM `variables` AS `v1` ".
							"JOIN `variables` AS `v2` ON `v1`.`name` = 'syncInMsg' AND `v2`.`name` = 'syncOutMsg'");
	$req->execute();
	list($syncInMsg, $syncOutMsg) = $req->fetch(PDO::FETCH_NUM);
	
//	`/sbin/mount.cifs //term1c.sod.local/exchange_maltsev_v/SDTest /mnt -o credentials=/root/admin.cred`;
//	if (file_exists('/mnt/Message_Main_SDTest.xml')) {
	if (file_exists('Message.xml')) {
		$xml = simplexml_load_file('Message.xml');
		if ('2.0' != $xml->attributes()->{'ВерсияФормата'}) {
			print "Некорректная версия выгрузки (".$xml->attributes()->{'ВерсияФормата'}.")\n";
		} else if ('' != $syncInMsg && $xml->{'ДанныеПоОбмену'}->attributes()->{'НомерИсходящегоСообщения'} <= $syncInMsg) {
			print "Нет новых сообщений\n";
		} else {
			$syncInMsg = $xml->{'ДанныеПоОбмену'}->attributes()->{'НомерИсходящегоСообщения'};
			if ('' == $syncOutMsg) {
				$syncOutMsg = $xml->{'ДанныеПоОбмену'}->attributes()->{'НомерВходящегоСообщения'};
			}
			$syncOutMsg++;
			print 'Номер исходящего сообщения: '.$xml->{'ДанныеПоОбмену'}->attributes()->{'НомерИсходящегоСообщения'}."\n";
			print 'Номер входящего сообщения: '.$xml->{'ДанныеПоОбмену'}->attributes()->{'НомерВходящегоСообщения'}."\n";

//  РегистрСведенийНаборЗаписей.РегламентированныйПроизводственныйКалендарь
			parseCalendar($xml, $pdo);
//	СправочникСсылка.СоД_КатегорииОборудования
			parseEqTypes($xml, $pdo);
//	СправочникСсылка.СоД_ТипыОборудования
			parseEqSubTypes($xml, $pdo);
//	СправочникСсылка.СоД_ПроизводителиНоменклатуры
			parseManufacturers($xml, $pdo);
//	СправочникСсылка.СоД_МоделиОборудования
			parseEqModels($xml, $pdo);
//	СправочникСсылка.Контрагенты
			parseContragents($xml, $pdo);
//	СправочникСсылка.СоД_Сервисы
			parseServices($xml, $pdo);
//	СправочникСсылка.СоД_ТипыФилиаловКонтрагентов
			parseDivisionTypes($xml, $pdo);
//	СправочникСсылка.СоД_ПартнёрыServiceDesk
			parsePartners($xml, $pdo);
//	СправочникСсылка.СоД_ПользователиServiceDesk
			parseUsers($xml, $pdo);
//	ДокументСсылка.СоД_СервисныйДоговор
			parseContracts($xml, $pdo);
//	ДокументСсылка.СоД_СервисныйДоговор -> Сервисы
			parseContractServices($xml, $pdo);
//	ДокументСсылка.СоД_СервисныйДоговор -> Филиалы
			parseDivisions($xml, $pdo);
//  РегистрСведенийНаборЗаписей.СоД_ОтветственныеПользователиПоСервиснымДоговорам
			parseContractUsers($xml, $pdo);
//  РегистрСведенийНаборЗаписей.СоД_ОтветственныеПользователиПоФилиаламСервисныхДоговоров
			parseDivisionUsers($xml, $pdo);
//  РегистрСведенийНаборЗаписей.СоД_ПартнёрыПоФилиаламСервисныхДоговоров
			parseDivisionPartners($xml, $pdo);
//  СправочникСсылка.СоД_РабочиеМестаФилиаловКонтрагентов	
			parseWorkplaces($xml, $pdo);
//	ДокументСсылка.СоД_СервисныйДоговор -> СоставСервиса
			parseContractSLAs($xml, $pdo);
//	СправочникСсылка.СоД_ОборудованиеФилиаловКонтрагентов
//  РегистрСведенийНаборЗаписей.СоД_ОборудованиеСервисныхДоговоров		
			parseEquipment($xml, $pdo);
//  РегистрСведенийНаборЗаписей.СоД_ИсторияОборудованияПоРабочимМестам			
			parseEquipmentLog($xml, $pdo);
//  Расчёт текущих состояний заявок
			buildLastStates($xml, $pdo);
//  ДокументСсылка.СоД_ЗаявкаОтКлиента			
			parseRequests($xml, $pdo);
//  ДокументСсылка.СоД_ЗаявкаОтКлиента -> Коментарии
			parseRequestsHistory($xml, $pdo);
//  СправочникСсылка.ХранилищеДополнительнойИнформации
			parseDocuments($xml, $pdo);

 /*			$xml_ret = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><ФайлОбмена />');
			$xml_ret->addAttribute('Комментарий', '');
			$xml_ret->addAttribute('ИдПравилКонвертации', $xml->attributes()->{'ИдПравилКонвертации'});
			$xml_ret->addAttribute('ИмяКонфигурацииПриемника', $xml->attributes()->{'ИмяКонфигурацииПриемника'});
			$xml_ret->addAttribute('ДатаВыгрузки', $xml->attributes()->{'ДатаВыгрузки'});
			$xml_ret->addAttribute('ВерсияФормата', $xml->attributes()->{'ВерсияФормата'});
			$rules = $xml_ret->addChild('ПравилаОбмена');
			$rules->addChild('ВерсияФормата', $xml->{'ПравилаОбмена'}->{'ВерсияФормата'});
			$rules->addChild('Ид', $xml->{'ПравилаОбмена'}->{'Ид'});
			$rules->addChild('Наименование', $xml->{'ПравилаОбмена'}->{'Наименование'});
			$rules->addChild('ДатаВремяСоздания', $xml->{'ПравилаОбмена'}->{'ДатаВремяСоздания'});
			$rules->addChild('Источник', $xml->{'ПравилаОбмена'}->{'Источник'});
			$rules->addChild('Приемник', $xml->{'ПравилаОбмена'}->{'Приемник'});
			$rules->addChild('Параметры');
			$rules->addChild('Обработки');
			$rules->addChild('ПравилаКонвертацииОбъектов');
			$rules->addChild('ПравилаОчисткиДанных');
			$rules->addChild('Алгоритмы');
			$rules->addChild('Запросы');
			$xml_ret->addChild('ИнформацияОТипахДанных');
			$sync = $xml_ret->addChild('ДанныеПоОбмену');
			$sync->addAttribute('НомерВходящегоСообщения', $syncInMsg);
			$sync->addAttribute('НомерИсходящегоСообщения', $syncOutMsg);
			$sync->addAttribute('ОтКого', $xml->{'ДанныеПоОбмену'}->attributes()->{'Кому'});
			$sync->addAttribute('Кому', $xml->{'ДанныеПоОбмену'}->attributes()->{'ОтКого'});
			$sync->addAttribute('ПланОбмена', $xml->{'ДанныеПоОбмену'}->attributes()->{'ПланОбмена'});
			$sync = $xml_ret->addChild('ДанныеПоФоновомуОбмену');
			$sync->addAttribute('ОтКого', '0');
			$sync->addAttribute('Кому', '0');
			$sync->addAttribute('ПланОбмена', '');
			$sync->addAttribute('ПереданоОбъектовФоновогоОбмена', '0');
			$sync->addAttribute('КоличествоОбъектовДляФоновогоОбмена', '0');
			$sync->addAttribute('ДобавлениеОбъектовИзФоновогоОбмена', '0');
			$xml_ret->asXML('/mnt/Message_SDTest_Main.xml');
			$req = $mysqli->prepare("INSERT INTO `variables` (`name`, `value`) VALUES ('syncInMsg', ?), ('syncOutMsg', ?) ".
						    			"ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
			$req->bind_param('ii', $syncInMsg, $syncOutMsg);
			$req->execute();
			$req->close(); */
		}
	} else {
		print "Файл не найден\n";
	}
	
/*	$types = array();
	foreach ($xml->children() as $obj) {
		if ('Объект' == $obj->getName()) {
			$type = ''.($obj->attributes()->{'Тип'});
			if(!in_array($type, $types)) {
				print "$type\n";
				$types[] = $type;
			}
		}
	} */
//	`/bin/umount /mnt`;
?>
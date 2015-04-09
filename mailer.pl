#!/usr/bin/perl

use utf8;
use Encode;
use DBI;

%sendto = ('open'                   => 'engeneers,partners',
           'changeState'.'accepted' => 'contact',
           'changeState'.'fixed'    => '',
           'changeState'.'repaired' => 'contact',
           'changeState'.'closed'   => '',
           'changeState'.'canceled' => '',
           'unClose'                => '',
           'unCalnel'               => '',
           'onWait'                 => '',
           'offWait'                => '',
           'changeDate'             => 'contact',
           'comment'                => 'contact,engeneer',
           'addDocument'            => 'contact,engeneer',
           'time50'                 => 'engeneer,operators',
           'time20'                 => 'engeneer,operators',
           'time00'                 => 'engeneer,operators,admins',
           'autoclose'              => 'contact'
          );
          
%slaLevels = ('critical' => 'критический', 'high' => 'высокий', 'medium' => 'средний', 'low' => 'низкий');

open(IN, '< /var/www/config/db.php');
while (<IN>) {
  if ($_ =~ /\$(\S+)\s*=\s*\S(.+?)\S\s*;\s*\n/mi) {
    $db{$1} = $2;
  }
}
close(IN);

$mysql = DBI->connect("DBI:mysql:database=".$db{'dbName'}.";host=".$db{'dbHost'}, $db{'dbUser'}, $db{'dbPass'}, {'RaiseError' => 1, 'mysql_enable_utf8' => 1});
if ($DBI::err != 0) {
	die "DBI eror ".$DBI::err."\n";
}

# Получаем список операторов, инженеров и партнёров
($req = $mysql->prepare("SELECT `firstName`, `secondName`, `middleName`, `email`, `partner_id`, `rights` FROM `users` WHERE `rights` IN ('operator', 'engeneer', 'admin')"))->execute();
while ((($givenName, $lastName, $middleName, $email, $partnerId, $rights) = $req->fetchrow_array()) && defined($givenName)) {
  if ($email ne '') {
    $addr = $lastName.($givenName ne '' ? " $givenName" : "").($middleName ne '' ? " $middleName" : "")." <$email>";
    if ($rights eq 'operator') {
      push(@operators, $addr);
    } elsif ($rights eq 'engeneer') {
      push(@engeneers, $addr);
    } elsif ($rights eq 'admin') {
      push(@admins, $addr);
    }
  }
}
$req->finish();

# Обрабатываем события
$i = 0;
$req = $mysql->prepare("SELECT `re`.`timestamp`, `re`.`event`, `re`.`text`, `re`.`newState`, `r`.`id`, `r`.`problem`, ".
                              "`cont`.`firstName`, `cont`.`secondName`, `cont`.`middleName`, `cont`.`email`, `div`.`id`, `div`.`name`, `cntr`.`name`, ".
                              "`r`.`slaLevel`, `r`.`equipment_id`, `em`.`name`, `emfg`.`name`, ".
                              "`author`.`firstName`, `author`.`secondName`, `author`.`middleName`, `doc`.`name`, ".
                              "`eng`.`firstName`, `eng`.`secondName`, `eng`.`middleName`, `eng`.`email` ".
                         "FROM `requestEvents` AS `re` ".
                           "LEFT JOIN `request` AS `r` ON `r`.`id` = `re`.`request_id` ".
                           "LEFT JOIN `users` AS `cont` ON `cont`.`id` = `r`.`contactPersons_id` ".
                           "LEFT JOIN `contractDivisions` AS `div` ON `div`.`id` = `r`.`contractDivisions_id` ".
                           "LEFT JOIN `contragents` AS `cntr` ON `cntr`.`id` = `div`.`contragents_id` ".
                           "LEFT JOIN `equipment` AS `eq` ON `eq`.`serviceNumber` = `r`.`equipment_id` ".
                           "LEFT JOIN `equipmentModels` AS `em` ON `em`.`id` = `eq`.`equipmentModels_id` ".
                           "LEFT JOIN `equipmentManufacturers` AS `emfg` ON `emfg`.`id` = `em`.`equipmentManufacturers_id` ".
                           "LEFT JOIN `users` AS `author` ON `author`.`id` = `re`.`users_id` ".
                           "LEFT JOIN `documents` AS `doc` ON `doc`.`requestEvents_id` = `re`.`id` ".
                           "LEFT JOIN `users` AS `eng` ON `eng`.`id` = `r`.`engeneer_id` ".
                         "WHERE `re`.`id` = \@id ".
                         "ORDER BY `request_id`, `timestamp`");
while ($mysql->do("UPDATE `requestEvents` SET `mailed` = 1 WHERE `mailed` = 0 AND \@id := `id` LIMIT 1") == 1) {
  $req->execute();
  ($timestamp, $event, $text, $newState, $reqId, $problem, $contGN, $contLG, $contMN, $contMail, $divId, $div, $contragent, $slaLevel, $servNum, 
   $eqModel, $eqMfg, $authorGN, $authorLN, $authorMN, $document, $engGN, $engLN, $engMN, $engMail) = $req->fetchrow_array();
  if (!defined($timestamp)) {
    last;
  }
  $authorName = $authorLN.($authorGN ne '' ? " $authorGN" : "").($authorMN ne '' ? " $authorMN" : "");
  $contName = $contLN.($contGN ne '' ? " $contGN" : "").($contMN ne '' ? " $contMN" : "");
  $contact = ($contName ne '' ? "$contName <$contMail>" : "");
  $engName = $engLN.($engGN ne '' ? " $engGN" : "").($engMN ne '' ? " $engMN" : "");
  $engeneer = ($engMail ne '' ? "$engName <$engMail>" : "");
  if ($event eq 'changeState') {
    $event .= $newState;
  }
  $body = '';
  if ($event eq 'open') {
  	if ($servNum == 0 || $servNum eq '') {
	    $body = sprintf("Появилась новая заявка №%08d, уровень критичности %s\n%s (%s, %s)\nОборудование не указано\n%s",
    	                $reqId, $slaLevels{$slaLevel}, $authorName, $div, $contragent, $problem);
   	} else {
    	$body = sprintf("Появилась новая заявка №%08d, уровень критичности %s\n%s (%s, %s)\n%s %s, #%d\n%s",
						$reqId, $slaLevels{$slaLevel}, $authorName, $div, $contragent, $eqMfg, $eqModel, $servNum, $problem);
	}
  } elsif ($event eq 'changeState'.'accepted') {
    $body = sprintf("Заявка №%08d принята к исполнению\nОтветственный: %s", $reqId, $engName);
  } elsif ($event eq 'changeState'.'accepted') {
    $body = sprintf("Поступил запрос на закрытие заявки №%08d\nЕсли в течение 3 дней Вы не отклоните запрос, то заявка будет закрыта автоматически", $reqId);
  } elsif ($event eq 'changeDate') {
    $body = sprintf("Контрольный срок завершения работ по заявке %08d был перенесён на %s", $reqId, $text);
  } elsif ($event eq 'comment') {
    $body = sprintf("К заявке %08d добавлен комментарий:\n%s\n%s", $reqId, $authorName, $text);
  } elsif ($event eq 'addDocument') {
    $body = sprintf("К заявке %08d добавлен файл %s\n%s", $reqId, $document, $authorName);
  } elsif ($event eq 'eqChange') {
    $body = sprintf("Изменено оборудование по заявке %08d\n%s\n%s", $reqId, $authorName, $text);
  }
  
  if ($body ne '') {
    $msgList{$i}{'event'} = $event;
    $msgList{$i}{'body'} = $body;
    $msgList{$i}{'contact'} = $contact;
    $msgList{$i}{'engeneer'} = $engeneer;
    $msgList{$i++}{'divId'} = $divId;
  }
}
$req->finish();

# Обрабатываем остаток времени по заявкам
$req = $mysql->prepare("SELECT \@id");
while ($mysql->do("UPDATE `request` ".
                    "SET `alarm` = 3 ".
                    "WHERE `alarm` < 3 AND `currentState` IN ('accepted', 'received', 'fixed') ".
                      "AND CAST(SUBSTRING_INDEX(calcTime(`id`), ',', -1) AS UNSIGNED)/60./`toRepair` >= 1 ".
                      "AND \@id := `id` ".
                    "LIMIT 1") == 1) {
  $req->execute();
  ($id) = $req->fetchrow_array();
  if (defined($id)) {
    $times{$id} = 'time00';
  }
}
while ($mysql->do("UPDATE `request` ".
                    "SET `alarm` = 2 ".
                    "WHERE `alarm` < 2 AND `currentState` IN ('accepted', 'received', 'fixed') ".
                      "AND CAST(SUBSTRING_INDEX(calcTime(`id`), ',', -1) AS UNSIGNED)/60./`toRepair` >= 0.8 ".
                      "AND \@id := `id` ".
                    "LIMIT 1") == 1) {
  $req->execute();
  ($id) = $req->fetchrow_array();
  if (defined($id)) {
    $times{$id} = 'time20';
  }
}
while ($mysql->do("UPDATE `request` ".
                    "SET `alarm` = 1 ".
                    "WHERE `alarm` < 1 AND `currentState` IN ('accepted', 'received', 'fixed') ".
                      "AND CAST(SUBSTRING_INDEX(calcTime(`id`), ',', -1) AS UNSIGNED)/60./`toRepair` >= 0.5 ".
                      "AND \@id := `id` ".
                    "LIMIT 1") == 1) {
  $req->execute();
  ($id) = $req->fetchrow_array();
  if (defined($id)) {
    $times{$id} = 'time50';
  }
}
# Автоматически закрываем заявки
$req = $mysql->prepare("SELECT \@id");
while ($mysql->do("UPDATE `request` ".
                    "SET `currentState` = 'closed' ".
                    "WHERE `currentState` = 'repaired' ".
                      "AND NOW() > DATE_ADD(`repairedAt`, INTERVAL 3 DAY) ".
                      "AND \@id := `id` ".
                    "LIMIT 1") == 1) {
  $req->execute();
  ($id) = $req->fetchrow_array();
  if (defined($id)) {
    $times{$id} = 'autoclose';
    $mysql->do("INSERT INTO `requestEvents` (`timestamp`, `event`, `newState`, `request_id`, `users_id`, `mailed`) VALUES (NOW(), 'changeState', 'closed', $id, NULL, 1)");
  }
}
$req->finish();

# Выбираем заявки с предупреждениями по времени
$req = $mysql->prepare("SELECT `r`.`id`, `eng`.`firstName`, `eng`.`secondName`, `eng`.`middleName`, `eng`.`email`, `r`.`contractDivisions_id`, ".
                              "`cont`.`firstName`, `cont`.`secondName`, `cont`.`middleName`, `cont`.`email` ".
                         "FROM `request` AS `r`".
                           "LEFT JOIN `users` AS `cont` ON `cont`.`id` = `r`.`contactPersons_id` ".
                           "LEFT JOIN `users` AS `eng` ON `eng`.`id` = `r`.`engeneer_id` ".
                         "WHERE `r`.`id` = ?");
foreach (keys %times) {
  $id = $_;
  $req->execute($id);
  if ($reqId == NULL) {
    last;
  }
  ($reqId, $engGN, $engLN, $engMN, $engMail, $divId, $contGN, $contLG, $contMN, $contMail) = $req->fetchrow_array();
  $engName = $engLN.($engGN ne '' ? " $engGN" : "").($engMN ne '' ? " $engMN" : "");
  $engeneer = ($engtMail ne '' ? "$engName <$engMail>" : "");
  $contName = $contLN.($contGN ne '' ? " $contGN" : "").($contMN ne '' ? " $contMN" : "");
  $contact = ($contName ne '' ? "$contName <$contMail>" : "");
  if ($times{$id} eq 'time00') {
    $body = sprintf("Просрочена заявка №%08d\nОтветственный: %s", $id, $engName);
  }  elsif ($times{$id} eq 'time20') {
    $body = sprintf("По заявке №%08d осталось меньше 20% времени до контрольного срока завершения работ\nОтветственный: %s", $id, $engName);
  }  elsif ($times{$id} eq 'time00') {
    $body = sprintf("По заявке №%08d осталось меньше 50% времени до контрольного срока завершения работ\nОтветственный: %s", $id, $engName);
  }  elsif ($times{$id} eq 'autoclose') {
    $body = sprintf("Заявке №%08d была закрыта автоматически по истечении контрольного срока", $id);
  }
  if ($body ne '') {
    $msgList{$i}{'event'} = $times{$id};
    $msgList{$i}{'body'} = $body;
    $msgList{$i}{'contact'} = $contact;
    $msgList{$i}{'engeneer'} = $engeneer;
    $msgList{$i++}{'divId'} = $divId;
  }
}
$req->finish();


# Формируем список рассылки
$req = $mysql->prepare("SELECT `u`.`firstName`, `u`.`secondName`, `u`.`middleName`, `u`.`email` ".
                         "FROM `allowedContracts` AS `a` ".
                           "LEFT JOIN `users` AS `u` ON `u`.`partner_id` =  `a`.`partner_id` ".
                         "WHERE `a`.`contractDivisions_id` = ?");
foreach $i (keys %msgList) {
#  print "\n$msgList{$i}{'event'}\n$msgList{$i}{'body'}\n$msgList{$i}{'contact'}\n$msgList{$i}{'engeneer'}\n$msgList{$i}{'divId'}\n";
  foreach $to (split(',', $sendto{$msgList{$i}{'event'}})) {
    if ($to eq 'contact') {
      $mail{$msgList{$i}{'contact'}} .= $msgList{$i}{'body'}."\n==========================================================================================\n";
    } elsif ($to eq 'engeneer') {
      $mail{$msgList{$i}{'engeneer'}} .= $msgList{$i}{'body'}."\n==========================================================================================\n";
    } elsif ($to eq 'engeneers') {
      foreach (@engeneers) {
        $mail{$_} .= $msgList{$i}{'body'}."\n==========================================================================================\n";
      }
    } elsif ($to eq 'partners') {
      $req->execute($msgList{$i}{'divId'});
      while (1) {
        ($gn, $ln, $mn, $email) = $req->fetchrow_array();
        if (!defined($gn)) {
          last;
        }
        $name = $ln.($gn ne '' ? " $gn" : "").($mn ne '' ? " $mn" : "");
        $partner = ($email ne '' ? "$name <$email>" : "");
        $mail{$partner} .= $msgList{$i}{'body'}."\n==========================================================================================\n";
      }
    } elsif ($to eq 'operators') {
      foreach (@operators) {
        $mail{$_} .= $msgList{$i}{'body'}."\n==========================================================================================\n";
      }
    } elsif ($to eq 'admins') {
      foreach (@admins) {
        $mail{$_} .= $msgList{$i}{'body'}."\n==========================================================================================\n";
      }
    }
  }
}
$req->finish();

$from = Encode::encode('MIME-B', 'Сервисдеск Со-Действие <servicedesk@sodrk.ru>');
$subj = Encode::encode('MIME-B', 'События по заявкам');
foreach $addr (keys %mail) {
  if ($addr ne '') {
    $to = Encode::encode('MIME-B', $addr);
    open (SENDMAIL, "| /usr/sbin/sendmail -t") or die("Failed to open pipe to sendmail: $!");
    binmode(SENDMAIL, ":utf8");
    print SENDMAIL "From: ".$from."\nTo: ".$to."\nSubject: ".$subj."\nContent-Transfer-Encoding: 8bit\nContent-type: text/plain; charset=UTF-8\n\n".$mail{$addr};
    close(SENDMAIL);
    print "From: ".$from."\nTo: ".$to."\nSubject: ".$subj."\nContent-Transfer-Encoding: 8bit\nContent-type: text/plain; charset=UTF-8\n\n".$mail{$addr};
  }
}

#$mysql->do("UPDATE `requestEvents` SET `mailed` = 0");
#$mysql->do("UPDATE `request` SET `alarm` = 0");


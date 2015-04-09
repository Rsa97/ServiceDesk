<?php
require_once('../config/db.php');

function isDivisionAllowed($mysqli, $userId, $divisionId, $contractId, $rights) {
  global $dbHost, $dbUser, $dbPass, $dbName;
  $byPartner = 0;
  $byUser = 0;
  switch ($rights) {
    case 'admin':
    case 'operator':
    case 'engeneer':
      return true;
    case 'partner':
      $byPartner = 1;
      break;
    case 'client':
      $byUser = 1;
      break;
  }
  $byDiv = ($divisionId == null ? 0 : 1);
  $byContr = ($contractId == null ? 0 : 1);
  if ($mysqli == null) {
    $ownSql = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
    if ($ownSql->connect_error)
      return false;
  } else
    $ownSql = $mysqli;
  $req = $ownSql->query("SELECT `c`.`id` 
          FROM `contracts` AS `c`
            LEFT JOIN `contractDivisions` AS `cd` ON `cd`.`contracts_id` = `c`.`id`
            LEFT JOIN `users` AS `u` ON `u`.`contractDivisions_id` = `cd`.`id`
            LEFT JOIN `allowedContracts` AS `a` ON `a`.`contractDivisions_id` = `cd`.`id`
          WHERE (NOW() BETWEEN `c`.`contractStart` AND `c`.`contractEnd`)
            AND ({$byDiv} = 0 OR `cd`.`id` = {$divisionId})
            AND ({$byContr} = 0 OR `c`.`id` = {$contractId})
            AND ({$byUser} = 0 OR `c`.`mainUser_id` = {$userId} OR `u`.`id` = {$userId})
            AND ({$byPartner} = 0 OR (`a`.`partner_id` = `u`.`partner_id` AND `u`.`id` = {$userId}))
  
}

function isServiceAllowed($mysqli, $userId, $serviceId, $rights) {
}

?>

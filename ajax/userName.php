<?php
  header('Content-Type: application/json; charset=UTF-8');
  
  session_start();
  if (!isset($_SESSION['user'])) {
    echo json_encode(array('error' => 'Время сессии истекло. Войдите в систему снова.', 'redirect' => '/index.html'));
    exit;
  }
  
  $userName = "{$_SESSION['user']['lastName']} {$_SESSION['user']['firstName']} {$_SESSION['user']['middleName']}";
  echo json_encode(Array('name' => $userName));
?>
<?php
header('Content-Type: text/html; charset=UTF-8');
ini_set('display_errors',1);
error_reporting(E_ALL);

  mb_language('uni');
  mb_internal_encoding('UTF-8');
  $to = mb_encode_mimeheader('Rakhmatulin Sergey <rsa@sodrk.ru>');
  $subj = 'Тестовое письмо';
  $body = 'Тест отправки из системы servicedesk';
  $from = 'From: '.mb_encode_mimeheader('Со-действие').' <sd@sodrk.ru>';
  echo "<pre>",htmlspecialchars("{$from}\nTo: {$to}\nSubject: {$subj}\n{$body}\n");
  echo (mb_send_mail($to, $subj, $body, $from) ? 'true' : 'false');
?>

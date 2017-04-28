<?php

	date_default_timezone_set('Europe/Moscow');

    $config['smtp_frommail'] = 'servicedesk@sodrk.ru';
	$config['smtp_fromname'] = 'Сервисдеск Со-Действие';
	$config['smtp_port']     = '25';
    $config['smtp_host']     = '10.149.0.200';
    $config['smtp_charset']  = 'utf-8';
	$config['smtp_myhost']   = 'servicedesk.sod.local';

function server_parse($socket, $response) {
    while (@substr($server_response, 3, 1) != ' ') {
        if (!($server_response = fgets($socket, 256)))
            return FALSE;
		if (!(substr($server_response, 0, 3) == $response))
	        return FALSE;
	}
    return TRUE;
}

function compose_mail($text, $html) {
	$res = array();
	$boundary = md5(microtime());
	$res['header'] = array('Content-Type' => "multipart/alternative; boundary={$boundary}");
	$res['body'] = "--{$boundary}\r\n".
			"Content-Type: text/plain; charset=UTF-8\r\n".
			"Content-Transfer-Encoding: base64\r\n".
			"\r\n".
			chunk_split(base64_encode($text)).
			"--{$boundary}\r\n".
			"Content-Type: text/html; charset=UTF-8\r\n".
			"Content-Transfer-Encoding: base64\r\n".
			"\r\n".
			chunk_split(base64_encode($html)).
			"\r\n".	
			"--{$boundary}--";
	return $res;
}

function smtpmail($to_mail, $to_name, $subject, $message, $headers=array()) {
    global $config;
	$from = "=?{$config['smtp_charset']}?B?".base64_encode($config['smtp_fromname'])."?= <{$config['smtp_frommail']}>";
	$to = "=?{$config['smtp_charset']}?B?".base64_encode($to_name)."?= <{$to_mail}>";
    $SEND = "Date: ".date("D, d M Y H:i:s O")."\r\n";
    $SEND .= "Subject: =?{$config['smtp_charset']}?B?".base64_encode($subject)."?=\r\n";
	$hdrs = array('Reply-To' => $from,
				  'MIME-Version' => '1.0',
				  'Content-Type' => "text/plain; charset=\"{$config['smtp_charset']}\"",
				  'Content-Transfer-Encoding' => '8bit',
				  'From' => $from,
				  'To' => $to); 
	foreach ($headers as $hdr => $val) {
		$hdrs[$hdr] = $val;
	}
	foreach ($hdrs as $hdr => $val) {
		$SEND .= "{$hdr}: {$val}\r\n";
	}
	$SEND .= "\r\n";
    $SEND .=  $message."\r\n";
	if(!$socket = fsockopen($config['smtp_host'], $config['smtp_port'], $errno, $errstr, 30))
        return FALSE;
 
    if (!server_parse($socket, "220"))
    	return FALSE;
 
    fputs($socket, "HELO {$config['smtp_myhost']}\r\n");
    if (!server_parse($socket, "250")) {
        fclose($socket);
        return FALSE;
    }

    fputs($socket, "MAIL FROM: {$config['smtp_frommail']}\r\n");
    if (!server_parse($socket, "250")) {
        fclose($socket);
        return FALSE;
    }

    fputs($socket, "RCPT TO: {$to_mail}\r\n");
    if (!server_parse($socket, "250")) {
        fclose($socket);
        return FALSE;
    }
    
    fputs($socket, "DATA\r\n");
	if (!server_parse($socket, "354")) {
        fclose($socket);
        return FALSE;
    }

    fputs($socket, $SEND."\r\n.\r\n");
    if (!server_parse($socket, "250")) {
        fclose($socket);
        return FALSE;
    }

    fputs($socket, "QUIT\r\n");
    fclose($socket);
    return TRUE;
}
?>
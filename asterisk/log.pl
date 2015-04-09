#!/usr/bin/perl

use utf8;
use DBI;
use Switch;

$mysql = DBI->connect('DBI:mysql:database=asterisk;host=127.0.0.1', 'root', 'Rthtyrb$', {'RaiseError' => 1, 'mysql_enable_utf8' => 1});
#$mysql = DBI->connect('DBI:mysql:database=asterisk;host=10.149.0.204', 'root', 'Rthtyrb$', {'RaiseError' => 1, 'mysql_enable_utf8' => 1});
if ($DBI::err != 0) {
  die ("DBI error ".$DBI::err." while connecting to base");
}

$mysql->do("DELETE FROM `cel_temp`");
$mysql->do("INSERT INTO `cel_temp` (SELECT * FROM `cel` WHERE `eventtype` NOT IN ('HANGUP') AND `eventtime` > CURDATE())");

while (1) {
  ($req = $mysql->prepare("SELECT `uniqueid`, `linkedid`, `channame`, `eventtime` FROM `cel_temp` WHERE `eventtype` = 'CHAN_START' ORDER BY `eventtime` LIMIT 1"))->execute();
  ($uid, $lid, $chanName, $eventTime) = $req->fetchrow_array();
  $req->finish();
  if ($uid == NULL) {
    last;
  }
  undef %list;
  $list{$uid} = 1;
  $list{$lid} = 1;
  $done = 0;
  while ($done == 0) {
    ($req = $mysql->prepare("SELECT `uniqueid`, `linkedid`, `channame` ".
                              "FROM `cel_temp` ".
                              "WHERE `uniqueid` IN (".join(',', keys %list).") ".
                                "OR `linkedid` IN (".join(',', keys %list).")"))->execute();
    $done = 1;
    while (($uid, $lid, $chanName) = $req->fetchrow_array()) {
      if (!defined($list{$uid})) {
        $done = 0;
        $list{$uid} = 1;
      }
      if (!defined($list{$lid})) {
        $done = 0;
        $list{$lid} = 1;
      }
    }
    $req->finish();
  }
  ($req = $mysql->prepare("SELECT `eventtype`, TIMESTAMPDIFF(SECOND,'$eventTime', `eventtime`), `cid_name`, `cid_num`, `exten`, `channame`, `uniqueid`, `linkedid`, `peer`, `extra` ".
                            "FROM `cel_temp` ".
                            "WHERE `uniqueid` IN (".join(',', keys %list).") ".
                              "OR `linkedid` IN (".join(',', keys %list).") ".
                            "ORDER BY `eventtime`, `id`"))->execute();
  $mainChan = '';
  undef %start;
  undef %num;
  undef %id;
  undef @chans;
  while (($type, $time, $cidName, $cidNum, $exten, $channel, $uid, $lid, $peer, $extra) = $req->fetchrow_array()) {
    if ($type eq 'CHAN_START') {
      if ($mainChan eq '') {
        $mainChan = $channel;
        $to = $exten;
        push(@chans, $channel);
      }
      $start{$channel} = $time;
      $num{$channel} = $cidNum;
      $id{$channel} = $uid;
    } elsif ($type eq 'CHAN_END') {
      $end{$channel} = $time;
    } elsif ($type eq 'BRIDGE_START') {
      if ($channel eq $mainChan) {
        push(@chans, $peer);
        $answer{$peer} = $time;
      }
    } elsif ($type eq 'ANSWER') {
      if (!defined($num{$channel}) || $num{$channel} eq '') {
        $num{$channel} = $cidNum;
      }
    }
  }
  $answerTime = '-1';
  $str = '';
  $strTo = "|$to|";
  foreach (@chans) {
    if ($str eq '') {
      $str = $num{$_};
      $totalTime = $end{$_};
      $in = (($_ =~ /^DAHDI/i) ? 'in' : 'out');
    } else {
      $strTo .= "|$num{$_}|";
      $str .= " => $num{$_} (".($answer{$_}-$start{$_})."/".($end{$_}-$answer{$_}).")";
      if ($answerTime == -1) {
        $answerTime = $answer{$_};
      }
    }
    print "$_ ($num{$_}, $id{$_}): $start{$_} - $answer{$_} - $end{$_}\n";
  }
  if ($answerTime == -1) {
    $str .= " => $to (N/A)";
  }
  print "$eventTime: $str\n\tFrom: $num{$mainChan}\n\tTo: $strTo\n\tDirection: $in\n\ttimeToAnswer: $answerTime\n\tbillTime: ".($totalTime-$answerTime)."\n";
  print "==========================================================================================================================================================\n\n";
  push(@result, "'$eventTime', '$num{$mainChan}', '$strTo', '$str', '', '".join(',', keys %list)."', $answerTime, ".($totalTime-$answerTime));
  $mysql->do("DELETE FROM `cel_temp` WHERE `uniqueid` IN (".join(',', keys %list).") OR `linkedid` IN (".join(',', keys %list).")");
}
$mysql->do("START TRANSACTION");
$mysql->do("DELETE FROM `cdr_result` WHERE `calldate` > CURDATE()");
foreach (@result) {
  $mysql->do("INSERT INTO `cdr_result` (`calldate`, `src`, `dst`, `dst_text`, `mp3`, `uids`, `answer`, `duration`) VALUES ($_)");
}
$mysql->do("COMMIT");


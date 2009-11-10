<?php

include("common.inc.php");

  $rrd_options .= " -l 0 -E -b 1024 -u 100 -r";

  $count = mysql_result(mysql_query("SELECT COUNT(*) FROM `cempMemPool` WHERE `device_id` = '$device_id'"),0);

if($count > '0') {
  $iter = "1";
  $rrd_options .= " COMMENT:'                       Currently Used    Max\\n'";
  $sql = mysql_query("SELECT * FROM `cempMemPool` where `device_id` = '$device_id'");
  while($mempool = mysql_fetch_array($sql)) {
    $entPhysicalName = mysql_result(mysql_query("SELECT entPhysicalName from entPhysical WHERE device_id = '".$device_id."'
                                                 AND entPhysicalIndex = '".$mempool['entPhysicalIndex']."'"),0);
    if($iter=="1") {$colour="CC0000";} elseif($iter=="2") {$colour="008C00";} elseif($iter=="3") {$colour="4096EE";
    } elseif($iter=="4") {$colour="73880A";} elseif($iter=="5") {$colour="D01F3C";} elseif($iter=="6") {$colour="36393D";
    } elseif($iter=="7") {$colour="FF0084"; unset($iter); }
    $mempool['descr_fixed'] = $entPhysicalName . " " . $mempool['cempMemPoolName'];
    $mempool['descr_fixed'] = str_replace("Routing Processor", "RP", $mempool['descr_fixed']);
    $mempool['descr_fixed'] = str_replace("Switching Processor", "SP", $mempool['descr_fixed']);
    $mempool['descr_fixed'] = str_replace("Processor", "Proc", $mempool['descr_fixed']);
    $mempool['descr_fixed'] = str_pad($mempool['descr_fixed'], 20);
    $mempool['descr_fixed'] = substr($mempool['descr_fixed'],0,20);
    $oid = $mempool['entPhysicalIndex'] . "." . $mempool['Index'];
    $rrd  = $config['rrd_dir'] . "/$hostname/cempMemPool-$oid.rrd";
    $rrd_options .= " DEF:mempool" . $iter . "free=$rrd:free:AVERAGE";
    $rrd_options .= " DEF:mempool" . $iter . "used=$rrd:used:AVERAGE";
    $rrd_options .= " DEF:mempool" . $iter . "free_m=$rrd:free:MAX";
    $rrd_options .= " DEF:mempool" . $iter . "used_m=$rrd:used:MAX";
    $rrd_options .= " CDEF:mempool" . $iter . "total=mempool" . $iter . "used,mempool" . $iter . "used,mempool" . $iter . "free,+,/,100,* ";
    
    $rrd_options .= " LINE1:mempool" . $iter . "total#" . $colour . ":'" . $mempool['descr_fixed'] . "' ";
    $rrd_options .= " GPRINT:mempool" . $iter . "used:LAST:%6.2lf%s";
    $rrd_options .= " GPRINT:mempool" . $iter . "total:LAST:%3.0lf%%";
    $rrd_options .= " GPRINT:mempool" . $iter . "total:MAX:%3.0lf%%\\\\n";
    $iter++;
  }
} else {
  $database = $config['rrd_dir'] . "/" . $hostname . "/ios-mem.rrd";
  $rrd_options .= " DEF:MEMTOTAL=$database:MEMTOTAL:AVERAGE";
  $rrd_options .= " DEF:IOFREE=$database:IOFREE:AVERAGE";
  $rrd_options .= " DEF:IOUSED=$database:IOUSED:AVERAGE";
  $rrd_options .= " DEF:PROCFREE=$database:PROCFREE:AVERAGE";
  $rrd_options .= " DEF:PROCUSED=$database:PROCUSED:AVERAGE";
  $rrd_options .= " CDEF:FREE=IOFREE,PROCFREE,+";
  $rrd_options .= " CDEF:USED=IOUSED,PROCUSED,+";
  $rrd_options .= " COMMENT:Bytes\ \ \ \ Current\ \ Minimum\ \ Maximum\ \ Average\\\\n";
  $rrd_options .= " AREA:USED#ff6060:";
  $rrd_options .= " LINE2:USED#cc0000:Used";
  $rrd_options .= " GPRINT:USED:LAST:%6.2lf%s";
  $rrd_options .= " GPRINT:USED:MIN:%6.2lf%s";
  $rrd_options .= " GPRINT:USED:MAX:%6.2lf%s";
  $rrd_options .= " GPRINT:USED:AVERAGE:%6.2lf%s\\\\l";
  $rrd_options .= " AREA:FREE#e5e5e5:Free:STACK";
  $rrd_options .= " GPRINT:FREE:LAST:%6.2lf%s";
  $rrd_options .= " GPRINT:FREE:MIN:%6.2lf%s";
  $rrd_options .= " GPRINT:FREE:MAX:%6.2lf%s";
  $rrd_options .= " GPRINT:FREE:AVERAGE:%6.2lf%s\\\\l";
  $rrd_options .= " LINE1:MEMTOTAL#000000:";
}

?>

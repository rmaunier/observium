<?php

/**
 * Observium
 *
 *   This file is part of Observium.
 *
 * @package    observium
 * @subpackage rrdtool
 * @author     Adam Armstrong <adama@memetic.org>
 * @copyright  (C) 2006 - 2012 Adam Armstrong
 *
 */

/**
 * Opens up a pipe to RRDTool using handles provided
 *
 * @return boolean
 * @global config
 * @global debug
 * @param &rrd_process
 * @param &rrd_pipes
 */

function rrdtool_pipe_open(&$rrd_process, &$rrd_pipes)
{
  global $config, $debug;

  $command = $config['rrdtool'] . " -";

  $descriptorspec = array(
     0 => array("pipe", "r"),  // stdin
     1 => array("pipe", "w"),  // stdout
     2 => array("pipe", "w")   // stderr
  );

  $cwd = $config['rrd_dir'];
  $env = array();

  $rrd_process = proc_open($command, $descriptorspec, $rrd_pipes, $cwd, $env);

  stream_set_blocking($rrd_pipes[1], 0);
  stream_set_blocking($rrd_pipes[2], 0);

  if (is_resource($rrd_process))
  {
    // $pipes now looks like this:
    // 0 => writeable handle connected to child stdin
    // 1 => readable handle connected to child stdout
    // 2 => readable handle connected to child stderr
    return TRUE;
  }
}

/**
 * Closes the pipe to RRDTool
 *
 * @return integer
 * @param resource rrd_process
 * @param array rrd_pipes
 */

function rrdtool_pipe_close(&$rrd_process, &$rrd_pipes)
{
  global $debug;

  if ($debug)
  {
    echo stream_get_contents($rrd_pipes[1]);
    echo stream_get_contents($rrd_pipes[2]);
  }

  fclose($rrd_pipes[0]);
  fclose($rrd_pipes[1]);
  fclose($rrd_pipes[2]);

  // It is important that you close any pipes before calling
  // proc_close in order to avoid a deadlock

  $return_value = proc_close($rrd_process);

  return $return_value;

}

/**
 * Generates a graph file at $graph_file using $options
 * Opens its own rrdtool pipe.
 *
 * @return integer
 * @param string graph_file
 * @param string options
 */

function rrdtool_graph($graph_file, $options)
{
  global $config, $debug;

  rrdtool_pipe_open($rrd_process, $rrd_pipes);

  if (is_resource($rrd_process))
  {
    // $pipes now looks like this:
    // 0 => writeable handle connected to child stdin
    // 1 => readable handle connected to child stdout
    // Any error output will be appended to /tmp/error-output.txt

    if ($config['rrdcached'])
    {
      fwrite($rrd_pipes[0], "graph --daemon " . $config['rrdcached'] . " $graph_file $options");
    } else {
      fwrite($rrd_pipes[0], "graph $graph_file $options");
    }

    fclose($rrd_pipes[0]);

    $iter = 0;
    while (strlen($line) < 1 && $iter < 1000) {
      // wait for 10 milliseconds to loosen loop
      usleep(10000);
      $line = fgets($rrd_pipes[1],1024);
      $data .= $line;
      $iter++;
    }
    unset($iter);

    $return_value = rrdtool_pipe_close($rrd_process, $rrd_pipes);

    if ($debug)
    {
        echo("<p>");
        if ($debug) { echo("graph $graph_file $options"); }
        echo("</p><p>");
        echo "command returned $return_value ($data)\n";
        echo("</p>");
    }
    return $data;
  } else {
    return 0;
  }
}

/**
 * Generates and pipes a command to rrdtool
 *
 * @param string command
 * @param string filename
 * @param string options
 * @global config
 * @global debug
 * @global rrd_pipes
 */

function rrdtool($command, $filename, $options)
{
  global $config, $debug, $rrd_pipes;

  $cmd = "$command $filename $options";
  if ($command != "create" && $config['rrdcached'])
  {
    $cmd .= " --daemon " . $config['rrdcached'];
  }

  if ($config['norrd'])
  {
    print_message("[%rRRD Disabled - $cmd%n]", 'color');
  } else {
    fwrite($rrd_pipes[0], $cmd."\n");
    usleep(1000);
  }

  $std_out = trim(stream_get_contents($rrd_pipes[1]));
  $std_err = trim(stream_get_contents($rrd_pipes[2]));

  // Check rrdtool's output for the command.
  if ( strstr($std_out, "ERROR") ) {
#    log_event($std_out , '', 'rrdtool');
  }

  if ($debug)
  {
    print_message('RRD[cmd[%g'.$cmd.'%n] ', 'color');
    print_message('stdout[%g'.$std_out.'%n] ', 'color');
    print_message('stderr[%g'.$std_err.'%n]]', 'color');
  }

}

/**
 * Generates an rrd database at $filename using $options
 *
 * @param string filename
 * @param string options
 */

function rrdtool_create($filename, $options)
{
  global $config, $debug;

  if ($config['norrd'])
  {
    print_message("[%gRRD Disabled%n] ");
  } else {
    $command = $config['rrdtool'] . " create $filename $options";
  }
  if ($debug) { print_message("RRD[%g".$command."%n] ", 'color'); }

  return shell_exec($command);
}

/**
 * Updates an rrd database at $filename using $options
 * Where $options is an array, each entry which is not a number is replaced with "U"
 *
 * @param string filename
 * @param array options
 */

function rrdtool_update($filename, $options)
{
  // Do some sanitisation on the data if passed as an array.
  if (is_array($options))
  {
    $values[] = "N";
    foreach ($options as $value)
    {
      if (!is_numeric($value)) { $value = U; }
      $values[] = $value;
    }
    $options = implode(':', $values);
  }

  return rrdtool("update", $filename, $options);
}

// FIXME needs phpdoc
function rrdtool_fetch($filename, $options)
{
  return rrdtool("fetch", $filename, $options);
}

// FIXME needs phpdoc
function rrdtool_last($filename, $options)
{
  return rrdtool("last", $filename, $options);
}

// FIXME needs phpdoc
function rrdtool_lastupdate($filename, $options)
{
  return rrdtool("lastupdate", $filename, $options);
}

/**
 * Escapes strings for RRDtool,
 *
 * @return string
 *
 * @param string string to escape
 * @param integer if passed, string will be padded and trimmed to exactly this length (after rrdtool unescapes it)
 */

function rrdtool_escape($string, $maxlength = NULL)
{
  $result = str_replace(':','\:',$string);
  $result = str_replace('%','%%',$result);

  // FIXME: should maybe also probably escape these? # \ ? [ ^ ] ( $ ) '

  if ($maxlength != NULL)
  {
    return substr(str_pad($result, $maxlength),0,$maxlength+(strlen($result)-strlen($string)));
  }
  else
  {
    return $result;
  }
}

/**
 * Determine useful information about RRD file
 *
 * Copyright (C) 2009  Bruno Prémont <bonbons AT linux-vserver.org>
 *
 * @file Name of RRD file to analyse
 * @return Array describing the RRD file
 *
 */

function rrdtool_info($file) {
        $info = array('filename'=>$file);

        $rrd = popen(RRDTOOL.' info '.escapeshellarg($file), 'r');
        if ($rrd) {
                while (($s = fgets($rrd)) !== false) {
                        $p = strpos($s, '=');
                        if ($p === false)
                                continue;
                        $key = trim(substr($s, 0, $p));
                        $value = trim(substr($s, $p+1));
                        if (strncmp($key,'ds[', 3) == 0) {
                                /* DS definition */
                                $p = strpos($key, ']');
                                $ds = substr($key, 3, $p-3);
                                if (!isset($info['DS']))
                                        $info['DS'] = array();
                                $ds_key = substr($key, $p+2);

                                if (strpos($ds_key, '[') === false) {
                                        if (!isset($info['DS']["$ds"]))
                                                $info['DS']["$ds"] = array();
                                        $info['DS']["$ds"]["$ds_key"] = rrd_strip_quotes($value);
                                }
                        } else if (strncmp($key, 'rra[', 4) == 0) {
                                /* RRD definition */
                                $p = strpos($key, ']');
                                $rra = substr($key, 4, $p-4);
                                if (!isset($info['RRA']))
                                        $info['RRA'] = array();
                                $rra_key = substr($key, $p+2);

                                if (strpos($rra_key, '[') === false) {
                                        if (!isset($info['RRA']["$rra"]))
                                                $info['RRA']["$rra"] = array();
                                        $info['RRA']["$rra"]["$rra_key"] = rrd_strip_quotes($value);
                                }
                        } else if (strpos($key, '[') === false) {
                                $info[$key] = rrd_strip_quotes($value);
                        }
                }
                pclose($rrd);
        }
        return $info;
}


?>

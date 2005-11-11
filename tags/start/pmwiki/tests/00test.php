<?php

$testdir = dirname(__FILE__);

$dp = opendir($testdir);
while (($file=readdir($dp))!==false) {
  if (!preg_match('/\\.php$/',$file)) continue;
  include_once("$testdir/$file");
}
TestResult(__FILE__,1);
0;

function TestNote($x,$level=1) {
  global $TestLevel;
  while ($TestLevel<$level) { echo '<dl>'; $TestLevel++; }
  while ($TestLevel>$level) { echo '</dl>'; $TestLevel--; }
  if ($level>0 && $x>'') echo "<dd>$x</dd>";
  else echo $x;
}

function TestResult($f,$passes,$tests=1) {
  TestNote('',0);
  $f = basename($f);
  $result = ($passes>=$tests) ? 'Pass' : 'Fail';
  echo "$f: $result  ($passes/$tests)<br />";
}

?>

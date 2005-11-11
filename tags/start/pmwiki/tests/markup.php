<?php

$mdp = opendir("$testdir/markup");
$tests=0; $passes=0;
TestNote(basename(__FILE__),0);
while (($mfile=readdir($mdp))!==false) {
  if (substr($mfile,0,1)=='.') continue;
  $markup = implode('',file("$testdir/markup/$mfile"));
  preg_match_all("/=test\\s+(\\S+)\\s+\\[=\n(.*?)\n=\\]\\s*=result\\s+\\[=\n(.*?)=\\]/s",$markup,$match);
  for($i=0;$i<count($match[1]);$i++) {
    $out = MarkupToHTML("Test.Markup",$match[2][$i]);
    $p = ($out==$match[3][$i]);
    $passes += $p; $tests++;
    TestNote("$mfile/{$match[1][$i]}: ".(($p) ? 'Pass' : 'Fail'));
    if (!$p) {
      TestNote("<pre>out:\n".htmlspecialchars($out)."\nkey:\n".
        htmlspecialchars($match[3][$i]),2);
    }
  }
}
closedir($mdp);
TestResult(__FILE__,$passes,$tests);

0;

<?php if (!defined('PmWiki')) exit();
/*  Copyright 2004 Patrick R. Michaud (pmichaud@pobox.com)
    This file is part of PmWiki; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published
    by the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.  See pmwiki.php for full details.

    This script defines routines for displaying page revisions.  It
    is included by default from the stdconfig.php script.
*/

function LinkSuppress($pagename,$imap,$path,$title,$txt,$fmt=NULL) 
  { return $txt; }

SDV($DiffShow['minor'],(@$_REQUEST['minor']!='n')?'y':'n');
SDV($DiffShow['source'],(@$_REQUEST['source']=='y')?'y':'n');
SDV($DiffMinorFmt, ($DiffShow['minor']=='y') ?
  "<a href='\$PageUrl?action=diff&amp;source=".$DiffShow['source']."&amp;minor=n'>$[Hide minor edits]</a>" :
  "<a href='\$PageUrl?action=diff&amp;source=".$DiffShow['source']."&amp;minor=y'>$[Show minor edits]</a>" );
SDV($DiffSourceFmt, ($DiffShow['source']=='y') ?
  "<a href='\$PageUrl?action=diff&amp;source=n&amp;minor=".$DiffShow['minor']."'>$[Show changes to output]</a>" :
  "<a href='\$PageUrl?action=diff&amp;source=y&amp;minor=".$DiffShow['minor']."'>$[Show changes to markup]</a>");
SDV($PageDiffFmt,"<h1 class='wikiaction'>$[\$FullName History]</h1>
  <p>$DiffMinorFmt - $DiffSourceFmt</p>
  ");
SDV($DiffStartFmt,"
      <div class='diffbox'><div class='difftime'>\$DiffTime 
        \$[by] <span class='diffauthor' title='\$DiffHost'>\$DiffAuthor</span></div>");
SDV($DiffDelFmt['a'],"
        <div class='difftype'>\$[Deleted line \$DiffLines:]</div>
        <div class='diffdel'>");
SDV($DiffDelFmt['c'],"
        <div class='difftype'>\$[Changed line \$DiffLines from:]</div>
        <div class='diffdel'>");
SDV($DiffAddFmt['d'],"
        <div class='difftype'>\$[Added line \$DiffLines:]</div>
        <div class='diffadd'>");
SDV($DiffAddFmt['c'],"</div>
        <div class='difftype'>$[to:]</div>
        <div class='diffadd'>");
SDV($DiffEndDelAddFmt,"</div>");
SDV($DiffEndFmt,"</div>");
SDV($DiffRestoreFmt,"
      <div class='diffrestore'><a href='\$PageUrl?action=edit&amp;restore=\$DiffId&amp;preview=y'>$[Restore]</a></div>");
SDV($DiffAuthorPageExistsFmt,"<a class='authorlink'
   href='\$ScriptUrl/\$DiffAuthorPage'>\$DiffAuthor</a>");
SDV($DiffAuthorPageMissingFmt,"\$DiffAuthor");

SDV($HandleActions['diff'],'HandleDiff');
SDV($ActionTitleFmt['diff'],'| $[History]');
$HTMLStylesFmt[] = "
  .diffbox { border:1px #999999 solid; margin-top:1.33em; }
  .diffauthor { font-weight:bold; }
  .diffchangesum { font-weight:bold; }
  .difftime { font-family:verdana,sans-serif; font-size:66%; 
    background-color:#dddddd; }
  .difftype { clear:both; font-family:verdana,sans-serif; 
    font-size:66%; font-weight:bold; }
  .diffadd { border-left:5px #99ff99 solid; padding-left:5px; }
  .diffdel { border-left:5px #ffff99 solid; padding-left:5px; }
  .diffrestore { clear:both; font-family:verdana,sans-serif; 
    font-size:66%; margin:1.5em 0px; }";

function PrintDiff($pagename) {
  global $DiffShow,$DiffStartFmt,$TimeFmt,$DiffDelFmt,$DiffAddFmt,
    $DiffEndDelAddFmt,$DiffEndFmt,$DiffRestoreFmt,$FmtV, $LinkFunctions;
  $page = ReadPage($pagename);
  if (!$page) return;
  Lock(0); 
  krsort($page); reset($page);
  $lf = $LinkFunctions;
  $LinkFunctions['http:'] = 'LinkSuppress';
  $LinkFunctions['https:'] = 'LinkSuppress';
  foreach($page as $k=>$v) {
    if (!preg_match("/^diff:(\d+):(\d+):?([^:]*)/",$k,$match)) continue;
    $diffclass = $match[3];
    if ($diffclass=='minor' && $DiffShow['minor']!='y') continue;
    $diffgmt = $match[1]; $FmtV['$DiffTime'] = strftime($TimeFmt,$diffgmt); 
    $diffauthor = @$page["author:$diffgmt"]; 
    if (!$diffauthor) @$diffauthor=$page["host:$diffgmt"];
    if (!$diffauthor) $diffauthor="unknown";
    $FmtV['$DiffChangeSum'] = @$page["csum:$diffgmt"];
    $FmtV['$DiffHost'] = @$page["host:$diffgmt"];
    $FmtV['$DiffAuthor'] = $diffauthor;
    $FmtV['$DiffId'] = $k; 
    echo FmtPageName($DiffStartFmt,$pagename);
    $difflines = explode("\n",$v."\n");
    $in=array(); $out=array(); $dtype='';
    foreach($difflines as $d) {
      if ($d>'') {
        if ($d[0]=='-' || $d[0]=='\\') continue;
        if ($d[0]=='<') { $out[]=substr($d,2); continue; }
        if ($d[0]=='>') { $in[]=substr($d,2); continue; }
      }
      if (preg_match("/^(\\d+)(,(\\d+))?([adc])(\\d+)(,(\\d+))?/",
          $dtype,$match)) {
        if (@$match[7]>'') {
          $lines='lines';
          $count=$match[1].'-'.($match[1]+$match[7]-$match[5]);
        } elseif ($match[3]>'') {
          $lines='lines'; $count=$match[1].'-'.$match[3];
        } else { $lines='line'; $count=$match[1]; }
        if ($match[4]=='a' || $match[4]=='c') {
          $txt = str_replace('line',$lines,$DiffDelFmt[$match[4]]);
          $FmtV['$DiffLines'] = $count;
          echo FmtPageName($txt,$pagename);
          if ($DiffShow['source']=='y') 
            echo "<code>",
              str_replace("\n","<br />",htmlspecialchars(join("\n",$in))),
              "</code>";
          else echo MarkupToHTML($pagename,
            preg_replace('/\\(:(.*?):\\)/','[@$1@]',join("\n",$in)));
        }
        if ($match[4]=='d' || $match[4]=='c') {
          $txt = str_replace('line',$lines,$DiffAddFmt[$match[4]]);
          $FmtV['$DiffLines'] = $count;
          echo FmtPageName($txt,$pagename);
          if ($DiffShow['source']=='y') 
            echo "<code>",
              str_replace("\n","<br />",htmlspecialchars(join("\n",$out))),
              "</code>";
          else echo MarkupToHTML($pagename,
            preg_replace('/(\\(:.*?:\\))/','[@$1@]',join("\n",$out)));
        }
        echo FmtPageName($DiffEndDelAddFmt,$pagename);
      }
      $in=array(); $out=array(); $dtype=$d;
    }
    echo FmtPageName($DiffEndFmt,$pagename);
    echo FmtPageName($DiffRestoreFmt,$pagename);
  }
  $LinkFunctions = $lf;
}

function HandleDiff($pagename) {
  global $HandleDiffFmt, $PageStartFmt, $PageDiffFmt, $PageEndFmt;
  Lock(1);
  $page = RetrieveAuthPage($pagename, 'read');
  if (!$page) { Abort("?cannot diff $pagename"); }
  PCache($pagename, $page);
  SDV($HandleDiffFmt,array(&$PageStartFmt,
    &$PageDiffFmt,"<div id='wikidiff'>", 'function:PrintDiff', '</div>',
    &$PageEndFmt));
  PrintFmt($pagename,$HandleDiffFmt);
}

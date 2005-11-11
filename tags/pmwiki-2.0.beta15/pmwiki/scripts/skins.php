<?php if (!defined('PmWiki')) exit();
/*  Copyright 2004 Patrick R. Michaud (pmichaud@pobox.com)
    This file is part of PmWiki; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published
    by the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.  See pmwiki.php for full details.

    This file implements the skin selection code for PmWiki.  Skin 
    selection is controlled by the $Skin variable, which can also
    be an array (in which case the first skin found is loaded).

    In addition, $ActionSkin[$action] specifies other skins to be
    searched based on the current action.

*/

SDV($Skin, 'pmwiki');
SDV($ActionSkin['print'], 'print');
SDV($FarmPubDirUrl, $PubDirUrl);
SDV($PageLogoUrl, "$FarmPubDirUrl/skins/pmwiki/pmwiki-32.gif");

if (isset($PageTemplateFmt)) LoadPageTemplate($pagename,$PageTemplateFmt);
else {
  $Skin = array_merge((array)@$ActionSkin[$action], (array)$Skin);
  SetSkin($pagename, $Skin);
}

SDV($PageCSSListFmt,array(
  'pub/css/local.css' => '$PubDirUrl/css/local.css',
  'pub/css/$Group.css' => '$PubDirUrl/css/$Group.css',
  'pub/css/$FullName.css' => '$PubDirUrl/css/$FullName.css'));

foreach((array)$PageCSSListFmt as $k=>$v) 
  if (file_exists(FmtPageName($k,$pagename))) 
    $HTMLHeaderFmt[] = "<link rel='stylesheet' type='text/css' href='$v' />\n";

function SetSkin($pagename, $skin) {
  global $Skin, $SkinDir, $SkinDirUrl, $IsTemplateLoaded, $PubDirUrl,
    $FarmPubDirUrl, $FarmD;
  unset($Skin);
  foreach((array)$skin as $s) {
    $sd = FmtPageName("pub/skins/$s", $pagename);
    if (is_dir($sd)) 
      { $Skin=$s; $SkinDirUrl="$PubDirUrl/skins/$Skin"; break; }
    $sd = FmtPageName("$FarmD/pub/skins/$s", $pagename);
    if (is_dir($sd)) 
      { $Skin=$s; $SkinDirUrl="$FarmPubDirUrl/skins/$Skin"; break; }
  }
  if (!is_dir($sd)) 
    Abort("?unable to find skin from list ".implode(' ',(array)$skin));
  $SkinDir = $sd;
  $IsTemplateLoaded = 0;
  if (file_exists("$SkinDir/$Skin.php"))
    include_once("$SkinDir/$Skin.php");
  else if (file_exists("$SkinDir/skin.php"))
    include_once("$SkinDir/skin.php");
  if ($IsTemplateLoaded) return;
  if (file_exists("$SkinDir/$Skin.tmpl")) 
    LoadPageTemplate($pagename, "$SkinDir/$Skin.tmpl");
  else if (file_exists("$SkinDir/skin.tmpl"))
    LoadPageTemplate($pagename, "$SkinDir/skin.tmpl");
  else if (($dh = opendir($SkinDir))) {
    while (($fname = readdir($dh)) !== false) {
      if (substr($fname, -5) != '.tmpl') continue;
      if ($IsTemplateLoaded) 
        Abort("?unable to find unique template in $SkinDir");
      LoadPageTemplate($pagename, "$SkinDir/$fname");
    }
    closedir($dh);
  }
  if (!$IsTemplateLoaded) Abort("Unable to load $Skin template");
}


function LoadPageTemplate($pagename,$tfilefmt) {
  global $PageStartFmt,$PageEndFmt,$BasicLayoutVars,$HTMLHeaderFmt,
    $IsTemplateLoaded;
  SDV($BasicLayoutVars,array('HeaderText','PageHeaderFmt','PageLeftFmt',
    'PageTitleFmt','PageText','PageRightFmt','PageFooterFmt'));

  $k = implode('',file(FmtPageName($tfilefmt,$pagename)));
  $sect = preg_split('#[[<]!--(/?Page[A-Za-z]+Fmt|PageText|HeaderText)--[]>]#',
    $k,0,PREG_SPLIT_DELIM_CAPTURE);
  $PageStartFmt = array_merge(array('headers:'),
    preg_split('/[[<]!--((?:wiki|file|function|markup):.*?)--[]>]/s',
      array_shift($sect),0,PREG_SPLIT_DELIM_CAPTURE));
  $PageEndFmt = array();
  $ps = 'PageStartFmt';
  while (count($sect)>0) {
    $k = array_shift($sect);
    $v = preg_split('/[[<]!--((?:wiki|file|function|markup):.*?)--[]>]/',
      array_shift($sect),0,PREG_SPLIT_DELIM_CAPTURE);
    if (substr($k,0,1)=='/') {
      $GLOBALS[$ps][] = "<!--$k-->";
      $GLOBALS[$ps][] = (count($v)>1) ? $v : $v[0];
      continue;
    } 
    $GLOBALS[$k] = (count($v)>1) ? $v : $v[0];
    if (in_array($k,$BasicLayoutVars)) {
      $GLOBALS[$ps][] = "<!--$k-->";
      if ($k=='PageText') $ps = 'PageEndFmt'; 
      if ($k=='HeaderText') $GLOBALS[$ps][] = &$HTMLHeaderFmt;
      $GLOBALS[$ps][] =& $GLOBALS[$k];
    }
  }
  array_push($PageStartFmt,"\n<div id='wikitext'>\n");
  array_unshift($PageEndFmt,'</div>');
  $IsTemplateLoaded = 1;
}

?>

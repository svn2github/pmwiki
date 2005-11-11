<?php if (!defined('PmWiki')) exit();
/*  Copyright 2004 Patrick R. Michaud (pmichaud@pobox.com)
    This file is part of PmWiki; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published
    by the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.  See pmwiki.php for full details.

    This module sets the page layout based on reading a "layout
    template file" as specified by $PageSkinFmt.  The module first 
    looks for a file as given by $PageSkinFmt exactly, otherwise it 
    looks through "pub/skins" and "$FarmD/pub/skins" for the template.  
    $PageSkinFmt can also name a "template directory", in which case 
    this module looks for a "screen.tmpl" file within the directory.

    When the template is found, the variable $SkinDirUrl is set to 
    PmWiki's best guess of the URL location of the template's directory.
    This variable can then be used by templates to refer to images 
    and external CSS files stored in the same directory as the
    template itself.

    The contents of the template are loaded into the variables
    $PageStartFmt and $PageEndFmt.  The general mechanism for page 
    output is that a function such as HandleBrowse or HandleEdit 
    will output the value of $PageStartFmt, followed by the 
    action-specific content, followed by $PageEndFmt.  The HTML 
    comment <!--PageText--> in the template file denotes the
    location where $PageStart ends and $PageEnd begins.  The
    HTML comment <!--HeaderText--> denotes where the contents of
    $HTMLHeaderFmt should be generated.

    The template file may also contain HTML comments of the form
    <!--PageHeaderFmt-->, <!--PageFooterFmt-->, etc.  These
    have the side effect of putting the strings that follow into
    the variables $PageHeaderFmt, $PageFooterFmt, etc. and then
    placing references to those variables into $PageStartFmt or 
    $PageEndFmt.  This allows HandleBrowse and other functions to
    selectively disable headers and footers by setting the corresponding
    variable to the empty string.

*/


SDV($FarmPubDirUrl,$PubDirUrl);
# $PageTemplateFmt is deprecated.  Use $PageSkinFmt instead.
SDV($PageSkinFmt, (@$PageTemplateFmt) ? $PageTemplateFmt : 'pmwiki');
SDV($PageLogoUrl,"$FarmPubDirUrl/skins/pmwiki/pmwiki-32.gif");
SDV($PageLogoFmt,"<div id='wikilogo'><a href='$ScriptUrl'><img 
  src='$PageLogoUrl' alt='$WikiTitle' border='0' /></a></div>");
SDV($SkinTmplFmt,'screen.tmpl');

# $SkinPathFmt determines where to look for skin files, and the 
# corresponding value to use for $SkinDirUrl if the template file is found.
$SkinPathFmt = array(
  $PageSkinFmt => '',
  "pub/skins/$PageSkinFmt/$SkinTmplFmt" => 
    "$PubDirUrl/skins/$PageSkinFmt/$SkinTmplFmt",
  "pub/skins/$PageSkinFmt" => 
    "$PubDirUrl/skins/$PageSkinFmt",
  "$FarmD/pub/skins/$PageSkinFmt/$SkinTmplFmt" =>
    "$FarmPubDirUrl/skins/$PageSkinFmt/$SkinTmplFmt",
  "$FarmD/pub/skins/$PageSkinFmt" =>
    "$FarmPubDirUrl/skins/$PageSkinFmt");

$f = 0;
foreach($SkinPathFmt as $k=>$v) {
  $t = FmtPageName($k,$pagename);
  if (file_exists($t) && !is_dir($t)) {
    $SkinDirUrl = dirname(FmtPageName($v,$pagename));
    LoadPageTemplate($pagename,$t);
    $f = 1;
    break;
  }
}

if ($PageSkinFmt && !@$f) 
  Abort("?unable to find skin: $PageSkinFmt");

SDV($PageCSSListFmt,array(
  'pub/css/local.css' => '$PubDirUrl/css/local.css',
  'pub/css/$Group.css' => '$PubDirUrl/css/$Group.css',
  'pub/css/$FullName.css' => '$PubDirUrl/css/$FullName.css'));

foreach((array)$PageCSSListFmt as $k=>$v) 
  if (file_exists(FmtPageName($k,$pagename))) 
    $HTMLHeaderFmt[] = "<link rel='stylesheet' type='text/css' href='$v' />\n";

function LoadPageTemplate($pagename,$tfilefmt) {
  global $PageStartFmt,$PageEndFmt,$BasicLayoutVars,$HTMLHeaderFmt;
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
}

?>

<?php if (!defined('PmWiki')) exit();
/*  Copyright 2004 Patrick R. Michaud (pmichaud@pobox.com)
    This file is part of PmWiki; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published
    by the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.  See pmwiki.php for full details.

*/

SDV($SearchResultsFmt,"<div class='wikisearch'>\$[SearchFor]
  $HTMLVSpace\$MatchList
  $HTMLVSpace\$[SearchFound]$HTMLVSpace</div>");
SDVA($SearchPatterns['all'],array());
$SearchPatterns['normal'][] = '!\.(All)?Recent(Changes|Uploads)$!';
$SearchPatterns['normal'][] = '!\.Group(Print)?(Header|Footer|Attributes)$!';

XLSDV('en',array(
  'SearchFor' => 'Results of search for <em>$Needle</em>:',
  'SearchFound' =>
    '$MatchCount pages found out of $MatchSearched pages searched.'));

Markup('searchbox', '>links',
  '/\\(:searchbox:\\)/i',
  FmtPageName("<form class='wikisearch' action='\$ScriptUrl' 
    method='get'><input type='hidden' name='n' 
    value='$[Main/SearchWiki]' /><input class='wikisearchbox' 
    type='text' name='q' value='' size='40' /><input 
    class='wikisearchbutton' type='submit' value='$[Search]' /></form>",
    $pagename));
Markup('searchresults', 'directives',
  '/\\(:searchresults\\s*(.*?):\\)/ei',
  "'<div>'.Keep(FmtPageList(\$GLOBALS['SearchResultsFmt'], \$pagename,
    array('o' => PSS('$1'), 'req' => 1))).'</div>'");
Markup('pagelist', 'directives',
  '/\\(:pagelist\\s*(.*):\\)/ei',
  "'<div>'.Keep(FmtPageList('\$MatchList', \$pagename,
    array('o' => PSS('$1 ')))).'</div>'");

SDVA($FPLFunctions,array('bygroup'=>'FPLByGroup','simple'=>'FPLSimple',
  'group'=>'FPLGroup'));

function FmtPageList($fmt,$pagename,$opt) {
  global $GroupPattern, $SearchPatterns, $FmtV, $FPLFunctions,
    $EnablePageListProtect;
  if (isset($_REQUEST['q']) && $_REQUEST['q']=='') $_REQUEST['q']="''";
  $opt = array_merge($opt,@$_REQUEST);
  $rq = htmlspecialchars(stripmagic(@$_REQUEST['q']), ENT_NOQUOTES);
  if (preg_match("!^($GroupPattern(\\|$GroupPattern)*)?/!i",$rq,$match)) 
  { 
    $opt['group'] = @$match[1]; 
    $rq = str_replace(@$match[1].'/','',$rq);
  }
  $needle = $opt['o'] . ' ' . $rq;
  $opt = array_merge($opt, ParseArgs($needle));
  $excl = (array)@$opt['-'];
  $incl = array_merge((array)@$opt[''], (array)@$opt['+']);
  if (@$opt['req'] && !$incl && !$excl && !isset($_REQUEST['q'])) return;
  $show = (isset($opt['list'])) ? $opt['list'] : 'default';
  $pats = (array)@$SearchPatterns[$show];
  if (@$opt['group']) array_unshift($pats,"/^({$opt['group']})\./i");
  if (@$opt['trail']) {
    $t = ReadTrail($pagename,$opt['trail']);
    foreach($t as $pagefile) $pagelist[] = $pagefile['pagename'];
  } else $pagelist = ListPages($pats);
  $matches = array();
  $searchterms = count($excl)+count($incl);
  $plprotect = IsEnabled($EnablePageListProtect, 0);
  foreach($pagelist as $pagefile) {
    if ($plprotect) $page = RetrieveAuthPage($pagefile, 'read', false);
    else $page = ReadPage($pagefile);
    Lock(0);  if (!$page) continue;
    if ($searchterms) {
      $text = $pagefile."\n".@$page['text']."\n".@$page['targets'];
      foreach($excl as $t) if (stristr($text,$t)) continue 2;
      foreach($incl as $t) if (!stristr($text,$t)) continue 2;
    }
    $matches[] = array(
      'pagename' => $pagefile,
      'size' => strlen(@$page['text']),
      'author' => @$page['author'],
      'time' => $page['time']);
  }
  sort($matches);
  $FmtV['$MatchCount'] = count($matches);
  $FmtV['$MatchSearched'] = count($pagelist);
  $FmtV['$Needle'] = $needle;
  $GLOBALS['SearchIncl'] = $incl;
  $GLOBALS['SearchExcl'] = $excl;
  $GLOBALS['SearchGroup'] = @$opt['group'];
  $fmtfn = @$FPLFunctions[$opt['fmt']];
  if (!function_exists($fmtfn)) $fmtfn='FPLByGroup';
  $FmtV['$MatchList'] = $fmtfn($pagename,$matches,$opt);
  return FmtPageName($fmt,$pagename);
}

## FPLByGroup provides a simple listing of pages organized by group
function FPLByGroup($pagename,&$pagelist,$opt) {
  global $FPLByGroupStartFmt,$FPLByGroupEndFmt,$FPLByGroupGFmt,$FPLByGroupIFmt;
  SDV($FPLByGroupStartFmt,"<dl class='fplbygroup'>");
  SDV($FPLByGroupEndFmt,'</dl>');
  SDV($FPLByGroupGFmt,"<dt><a href='\$ScriptUrl/\$Group'>\$Group</a> /</dt>");
  SDV($FPLByGroupIFmt,"<dd><a href='\$PageUrl'>\$Name</a></dd>");
  $out = array();
  foreach($pagelist as $item) {
    $pgroup = FmtPageName($FPLByGroupGFmt,$item['pagename']);
    if ($pgroup!=@$lgroup) { $out[] = $pgroup; $lgroup=$pgroup; }
    $out[] = FmtPageName($FPLByGroupIFmt,$item['pagename']);
  }
  return FmtPageName($FPLByGroupStartFmt,$pagename).implode('',$out).
    FmtPageName($FPLByGroupEndFmt,$pagename);
}

## FPLSimple provides a simple bullet list of pages
function FPLSimple($pagename,&$pagelist,$opt) {
  global $FPLSimpleStartFmt,$FPLSimpleIFmt,$FPLSimpleEndFmt;
  SDV($FPLSimpleStartFmt,"<ul class='fplsimple'>");
  SDV($FPLSimpleEndFmt,"</ul>");
  SDV($FPLSimpleIFmt,"<li><a href='\$PageUrl'>\$FullName</a></li>");
  $out = array();
  foreach($pagelist as $item) 
    $out[] = FmtPageName($FPLSimpleIFmt,$item['pagename']);
  return FmtPageName($FPLSimpleStartFmt,$pagename).implode('',$out).
    FmtPageName($FPLSimpleEndFmt,$pagename);
}

## FPLGroup provides a simple bullet list of groups
function FPLGroup($pagename,&$pagelist,$opt) {
  global $FPLGroupStartFmt,$FPLGroupIFmt,$FPLGroupEndFmt;
  SDV($FPLGroupStartFmt,"<ul class='fplgroup'>");
  SDV($FPLGroupEndFmt,"</ul>");
  SDV($FPLGroupIFmt,"<li><a href='\$ScriptUrl/\$Group'>\$Group</a></li>");
  $out = array();
  foreach($pagelist as $item) {
    $pgroup = FmtPageName($FPLGroupIFmt,$item['pagename']);
    if (@!$seen[$pgroup]++) $out[] = $pgroup;
  }
  return FmtPageName($FPLGroupStartFmt,$pagename).implode('',$out).
    FmtPageName($FPLGroupEndFmt,$pagename);
}

?>

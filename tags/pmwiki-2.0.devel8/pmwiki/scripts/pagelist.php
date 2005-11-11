<?php if (!defined('PmWiki')) exit();
/*  Copyright 2004 Patrick R. Michaud (pmichaud@pobox.com)
    This file is part of PmWiki; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published
    by the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.  See pmwiki.php for full details.

*/

SDV($SearchResultsFmt,"\$[SearchFor]
  $HTMLVSpace\$MatchList
  $HTMLVSpace\$[SearchFound]$HTMLVSpace");
XLSDV('en',array(
  'SearchFor' => 'Results of search for <em>$Needle</em>:',
  'SearchFound' =>
    '$MatchCount pages found out of $MatchSearched pages searched.'));

Markup('searchbox','>links','/\\[:searchbox:\\]/',
  FmtPageName("<form class='wikisearch' action='\$ScriptUrl' 
    method='get'><input type='hidden' name='pagename' 
    value='$[Main/SearchWiki]' /><input class='wikisearchbox' 
    type='text' name='q' value='' size='40' /><input 
    class='wikisearchbutton' type='submit' value='$[Search]' /></form>",
    $pagename));
Markup('searchresults','directives','/\\[:searchresults\\s*(.*?):\\]/e',
  "Keep(FmtPageList(\$GLOBALS['SearchResultsFmt'],\$pagename,
    array('q'=>PSS('$1'))))");
Markup('pagelist','directives','/\\[:pagelist\\s*(.*):\\]/e',
  "Keep(FmtPageList('\$MatchList',\$pagename,array('q'=>PSS('$1 '))))");

SDVA($FPLFunctions,array('bygroup'=>'FPLByGroup','simple'=>'FPLSimple'));

function FmtPageList($fmt,$pagename,$opt) {
  global $GroupPattern,$SearchPatterns,$FmtV,$FPLFunctions;
  $opt = array_merge(@$_REQUEST,$opt);
  if (!$opt['q']) $opt['q']=stripmagic(@$_REQUEST['q']);
  if (!$opt['q']) return;
  $terms = preg_split('/((?<!\\S)[-+]?[\'"].*?[\'"](?!\\S)|\\S+)/',
    $opt['q'],-1,PREG_SPLIT_DELIM_CAPTURE|PREG_SPLIT_NO_EMPTY);
  if (preg_match("!^($GroupPattern(\\|$GroupPattern)*)?/!i",@$terms[0],$match)) 
  { 
    $opt['group'] = @$match[1]; 
    $terms[0]=str_replace(@$match[1].'/','',$terms[0]);
  }
  $excl = array(); $incl = array();
  foreach($terms as $t) {
    if (trim($t)=='') continue;
    if (preg_match('/^([^\'":=]*)[:=]([\'"]?)(.*?)\\2$/',$t,$match)) 
      { $opt[$match[1]] = $match[3]; continue; }
    preg_match('/^([-+]?)([\'"]?)(.+?)\\2$/',$t,$match);
    if ($match[1]=='-') $excl[] = $match[3];
    else $incl[] = $match[3];
  }
  $pats = (array)@$SearchPatterns;
  if (@$opt['group']) array_unshift($pats,"/^({$opt['group']})\./i");
  $pagelist = ListPages($pats);
  $matches = array();
  $searchterms = count($excl)+count($incl);
  foreach($pagelist as $pagefile) {
    $page = ReadPage($pagefile);  Lock(0);  if (!$page) continue;
    if ($searchterms) {
      $text = $pagefile."\n".$page['text'];
      foreach($excl as $t) if (stristr($text,$t)) continue 2;
      foreach($incl as $t) if (!stristr($text,$t)) continue 2;
    }
    $matches[] = array(
      'pagename' => $pagefile,
      'size' => strlen($page['text']),
      'author' => @$page['author'],
      'time' => $page['time']);
  }
  sort($matches);
  $FmtV['$MatchCount'] = count($matches);
  $FmtV['$MatchSearched'] = count($pagelist);
  $FmtV['$Needle'] = $opt['q'];
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
  SDV($FPLSimpleIFmt,"<li><a href='\$PageUrl'>\$PageName</a></li>");
  $out = array();
  foreach($pagelist as $item) 
    $out[] = FmtPageName($FPLSimpleIFmt,$item['pagename']);
  return FmtPageName($FPLSimpleStartFmt,$pagename).implode('',$out).
    FmtPageName($FPLSimpleEndFmt,$pagename);
}
     

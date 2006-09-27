<?php if (!defined('PmWiki')) exit();
/*  Copyright 2004-2006 Patrick R. Michaud (pmichaud@pobox.com)
    This file is part of PmWiki; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published
    by the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.  See pmwiki.php for full details.

    This script implements (:pagelist:) and friends -- it's one
    of the nastiest scripts you'll ever encounter.  Part of the reason
    for this is that page listings are so powerful and flexible, so
    that adds complexity.  They're also expensive, so we have to
    optimize them wherever we can.

    The core function is FmtPageList(), which will generate a 
    listing according to a wide variety of options.  FmtPageList takes 
    care of initial option processing, and then calls a "FPL"
    (format page list) function to obtain the formatted output.
    The FPL function is chosen by the 'fmt=' option to (:pagelist:).

    Each FPL function calls MakePageList() to obtain the list
    of pages, formats the list somehow, and returns the results
    to FmtPageList.  FmtPageList then returns the output to
    the caller, and calls Keep() (preserves HTML) or PRR() (re-evaluate
    as markup) as appropriate for the output being returned.
*/

## $PageIndexFile is the index file for term searches and link= option
if (IsEnabled($EnablePageIndex, 1)) {
  SDV($PageIndexFile, "$WorkDir/.pageindex");
  $EditFunctions[] = 'PostPageIndex';
}

## $SearchPatterns holds patterns for list= option
SDVA($SearchPatterns['all'], array());
$SearchPatterns['normal'][] = '!\.(All)?Recent(Changes|Uploads)$!';
$SearchPatterns['normal'][] = '!\.Group(Print)?(Header|Footer|Attributes)$!';
$SearchPatterns['normal'][] = str_replace('.', '\\.', "!^$pagename$!");

## $FPLFormatOpt is a list of options associated with fmt=
## values.  'default' is used for any undefined values of fmt=.
SDVA($FPLFormatOpt, array(
  'default' => array('fn' => 'FPLTemplate', 'fmt' => '#default', 
                     'class' => 'fpltemplate'),
  'bygroup' => array('fn' => 'FPLTemplate', 'template' => '#bygroup',
                     'class' => 'fplbygroup'),
  'simple'  => array('fn' => 'FPLTemplate', 'template' => '#simple',
                     'class' => 'fplsimple'),
  'group'   => array('fn' => 'FPLTemplate', 'template' => '#group',
                     'class' => 'fplgroup'),
  'title'   => array('fn' => 'FPLTemplate', 'template' => '#title',
                     'class' => 'fpltitle', 'order' => 'title'),
  ));

SDV($SearchResultsFmt, "<div class='wikisearch'>\$[SearchFor]
  $HTMLVSpace\$MatchList
  $HTMLVSpace\$[SearchFound]$HTMLVSpace</div>");
SDV($SearchQuery, str_replace('$', '&#036;', 
  htmlspecialchars(stripmagic(@$_REQUEST['q']), ENT_NOQUOTES)));
XLSDV('en', array(
  'SearchFor' => 'Results of search for <em>$Needle</em>:',
  'SearchFound' => 
    '$MatchCount pages found out of $MatchSearched pages searched.'));

Markup('pagelist', 'directives',
  '/\\(:pagelist(\\s+.*?)?:\\)/ei',
  "FmtPageList('\$MatchList', \$pagename, array('o' => PSS('$1 ')))");
Markup('searchbox', 'directives',
  '/\\(:searchbox(\\s.*?)?:\\)/e',
  "SearchBox(\$pagename, ParseArgs(PSS('$1')))");
Markup('searchresults', 'directives',
  '/\\(:searchresults(\\s+.*?)?:\\)/ei',
  "FmtPageList(\$GLOBALS['SearchResultsFmt'], \$pagename, 
       array('req' => 1, 'o' => PSS('$1')))");

SDV($SaveAttrPatterns['/\\(:(searchresults|pagelist)(\\s+.*?)?:\\)/i'], ' ');

SDV($HandleActions['search'], 'HandleSearchA');
SDV($HandleAuth['search'], 'read');
SDV($ActionTitleFmt['search'], '| $[Search Results]');

SDVA($PageListFilters, array(
  'PageListSources' => 100,
  'PageListTermsTargets' => 110,
  'PageListSort' => 900,
));

foreach(array('random', 'size', 'time', 'ctime') as $o) 
  SDV($PageListSortCmp[$o], "@(\$PCache[\$x]['$o']-\$PCache[\$y]['$o'])");

#define PAGELIST_PRE       1
#define PAGELIST_ITEM      2
#define PAGELIST_POST      4

## SearchBox generates the output of the (:searchbox:) markup.
## If $SearchBoxFmt is defined, that is used, otherwise a searchbox
## is generated.  Options include group=, size=, label=.
function SearchBox($pagename, $opt) {
  global $SearchBoxFmt, $SearchBoxOpt, $SearchQuery, $EnablePathInfo;
  if (isset($SearchBoxFmt)) return Keep(FmtPageName($SearchBoxFmt, $pagename));
  SDVA($SearchBoxOpt, array('size' => '40', 
    'label' => FmtPageName('$[Search]', $pagename),
    'value' => str_replace("'", "&#039;", $SearchQuery)));
  $opt = array_merge((array)$SearchBoxOpt, @$_GET, (array)$opt);
  $opt['action'] = 'search';
  $target = ($opt['target']) 
            ? MakePageName($pagename, $opt['target']) : $pagename;
  $out = FmtPageName(" class='wikisearch' action='\$PageUrl' method='get'>",
                     $target);
  $opt['n'] = IsEnabled($EnablePathInfo, 0) ? '' : $target;
  $out .= "<input type='text' name='q' value='{$opt['value']}' 
    class='inputbox searchbox' size='{$opt['size']}' /><input type='submit' 
    class='inputbutton searchbutton' value='{$opt['label']}' />";
  foreach($opt as $k => $v) {
    if ($v == '') continue;
    if ($k == 'q' || $k == 'label' || $k == 'value' || $k == 'size') continue;
    $k = str_replace("'", "&#039;", $k);
    $v = str_replace("'", "&#039;", $v);
    $out .= "<input type='hidden' name='$k' value='$v' />";
  }
  return '<form '.Keep($out).'</form>';
}


## FmtPageList combines options from markup, request form, and url,
## calls the appropriate formatting function, and returns the string.
function FmtPageList($outfmt, $pagename, $opt) {
  global $GroupPattern, $FmtV, $FPLFormatOpt, $FPLFunctions;
  # get any form or url-submitted request
  $rq = htmlspecialchars(stripmagic(@$_REQUEST['q']), ENT_NOQUOTES);
  # build the search string
  $FmtV['$Needle'] = $opt['o'] . ' ' . $rq;
  # Handle "group/" at the beginning of the form-submitted request
  if (preg_match("!^($GroupPattern(\\|$GroupPattern)*)?/!i", $rq, $match)) {
    $opt['group'] = @$match[1];
    $rq = substr($rq, strlen(@$match[1])+1);
  }
  # merge markup options with form and url
  $opt = array_merge($opt, ParseArgs($opt['o'] . ' ' . $rq), @$_REQUEST);
  # non-posted blank search requests return nothing
  if (@($opt['req'] && !$opt['-'] && !$opt[''] && !$opt['+'] && !$opt['q']))
    return '';
  # terms and group to be included and excluded
  $GLOBALS['SearchIncl'] = array_merge((array)@$opt[''], (array)@$opt['+']);
  $GLOBALS['SearchExcl'] = (array)@$opt['-'];
  $GLOBALS['SearchGroup'] = @$opt['group'];
  $fmt = @$opt['fmt']; if (!$fmt) $fmt = 'default';
  $fmtopt = @$FPLFormatOpt[$fmt];
  if (!is_array($fmtopt)) {
    if ($fmtopt) $fmtopt = array('fn' => $fmtopt);
    elseif (@$FPLFunctions[$fmt]) 
      $fmtopt = array('fn' => $FPLFunctions[$fmt]);
    else $fmtopt = $FPLFormatOpt['default'];
  }
  $fmtfn = @$fmtopt['fn'];
  if (!is_callable($fmtfn)) $fmtfn = $FPLFormatOpt['default']['fn'];
  $matches = array();
  $opt = array_merge($fmtopt, $opt);
  $out = $fmtfn($pagename, $matches, $opt);
  $FmtV['$MatchCount'] = count($matches);
  if ($outfmt != '$MatchList') 
    { $FmtV['$MatchList'] = $out; $out = FmtPageName($outfmt, $pagename); }
  $out = preg_replace('/^(<[^>]+>)(.*)/esm', "PSS('$1').Keep(PSS('$2'))", $out);
  return PRR($out);
}


## MakePageList generates a list of pages using the specifications given
## by $opt.
function MakePageList($pagename, $opt, $retpages = 1) {
  global $MakePageListOpt, $PageListFilters, $EnablePageListProtect;

  StopWatch('MakePageList pre');
  SDV($EnablePageListProtect, 1);
  SDVA($MakePageListOpt, array('list' => 'default'));
  $opt = array_merge((array)$MakePageListOpt, (array)$opt);

  if (IsEnabled($EnablePageListProtect, 1)) $opt['readf'] = 1000;
  else @$opt['readf'] += 0;

  asort($PageListFilters);
  $opt['=phase'] = PAGELIST_PRE; $list=array(); $pn=NULL; $page=NULL;
  foreach($PageListFilters as $fn => $v) {
    $ret = $fn($list, $opt, $pn, $page);
    if ($ret & PAGELIST_ITEM) $itemfilters[] = $fn;
    if ($ret & PAGELIST_POST) $postfilters[] = $fn;
  }

  StopWatch('MakePageList items');
  $opt['=phase'] = PAGELIST_ITEM;
  StopWatch("MakePageList scanning ".count($list).", readf={$opt['readf']}");
  $matches = array();
  foreach((array)$list as $pn) {
    if ($opt['readf'] >= 1000) 
      $page = RetrieveAuthPage($pn, 'read', false, READPAGE_CURRENT);
    else if ($opt['readf']) $page = ReadPage($pn, READPAGE_CURRENT);
    else $page = array();
    foreach((array)$itemfilters as $fn) 
      if (!$fn($list, $opt, $pn, $page)) continue 2;
    $page['pagename'] = $page['name'] = $pn;
    # StopWatch("MakePageList itemfilter count=".count($page));  
    PCache($pn, $page);
    $matches[] = $pn;
  }
  $list = $matches;

  StopWatch('MakePageList post');
  $opt['=phase'] = PAGELIST_POST; $pn=NULL; $page=NULL;
  foreach((array)$postfilters as $fn) 
    $fn($list, $opt, $pn, $page);
  
  if ($retpages) 
    for($i=0; $i<count($list); $i++)
      $list[$i] = &$PCache[$list[$i]];
  StopWatch('MakePageList end');
  return $list;
}


function PageListSources(&$list, &$opt, $pagename, &$page) {
  global $SearchPatterns;

  StopWatch('PageListSources begin');
  ## add the list= option to our list of pagename filter patterns
  $opt['=pnfilter'] = array_merge((array)@$opt['=pnfilter'], 
                                  (array)$SearchPatterns[$opt['list']]);

  if (@$opt['group']) $opt['=pnfilter'][] = FixGlob($opt['group'], '$1$2.*');
  if (@$opt['name']) $opt['=pnfilter'][] = FixGlob($opt['name'], '$1*.$2');

  if (@$opt['trail']) {
    $trail = ReadTrail($pagename, $opt['trail']);
    $list = array();
    foreach($trail as $tstop) {
      $pn = $tstop['pagename'];
      $list[] = $pn;
      $tstop['parentnames'] = array();
      PCache($pn, $tstop);
    }
    foreach($trail as $tstop) 
      $PCache[$tstop['pagename']]['parentnames'][] = 
        @$trail[$tstop['parent']]['pagename'];
  } else $list = ListPages($opt['=pnfilter']);

  StopWatch('PageListSources end');
  return 0;
}


function PageListTermsTargets(&$list, &$opt, $pn, &$page) {
  global $FmtV;

  switch ($opt['=phase']) {
    case PAGELIST_PRE:
      $FmtV['$MatchSearched'] = count($list);
      $incl = array(); $inclp = array();
      foreach((array)@$opt[''] as $i) { $incl[] = $i; }
      foreach((array)@$opt['+'] as $i) { $incl[] = $i; }
      foreach((array)@$opt['-'] as $i) { $excl[] = $i; }

      $indexterms = PageIndexTerms($incl);
      foreach($incl as $i) {
        $delim = (!preg_match('/[^\\w\\x80-\\xff]/', $i)) ? '$' : '/';
        $opt['=inclp'][] = $delim . preg_quote($i,$delim) . $delim . 'i';
      }
      if ($excl) 
        $opt['=exclp'][] = '$'.implode('|', array_map('preg_quote',$excl)).'$i';

      if (@$opt['link']) {
        $link = MakePageName($pagename, $opt['link']);
        $opt['=linkp'] = "/(^|,)$link(,|$)/i";
        $indexterms[] = " $link ";
      }

      if ($indexterms) {
        $xlist = PageIndexGrep($indexterms, true);
        $a = count($list);
        $list = array_diff($list, $xlist);
        $a -= count($list);
        StopWatch("PageListTermsTargets filtered $a pages");
      }

      if (@$opt['=inclp'] || @$opt['=exclp'] || @$opt['=linkp']) 
        { $opt['readf']++; return PAGELIST_ITEM|PAGELIST_POST; }
      return 0;

    case PAGELIST_ITEM:
      if (!$page) $page = ReadPage($pn, READPAGE_CURRENT);
      if (!$page) return 0;
      if (@$opt['=linkp'] && !preg_match($opt['=linkp'], @$page['targets'])) 
        { $opt['=reindex'][] = $pn; return 0; }
      if (@$opt['=inclp'] || @$opt['=exclp']) {
        $text = $pn."\n".@$page['targets']."\n".@$page['text'];
        foreach((array)@$opt['=exclp'] as $i) 
          if (preg_match($i, $text)) return 0;
        foreach((array)@$opt['=inclp'] as $i) 
          if (!preg_match($i, $text)) { 
            if ($i{0} == '$') $opt['=reindex'][] = $pn; 
            return 0; 
          }
      }
      return 1;

    case PAGELIST_POST:
      if (@$opt['=reindex']) {
        register_shutdown_function('flush');
        register_shutdown_function('PageIndexUpdate',$opt['=reindex'],getcwd());
      }
      return 0;
  }
}


function PageListSort(&$list, &$opt, $pn, &$page) {
  global $PageListSortCmp, $PCache;

  $order = @$opt['order'];
  switch ($opt['=phase']) {
    case PAGELIST_PRE:
      if (!preg_match('/^([\\s,|]*-?(name|group|random))*$/', $order))
        $opt['readf']++;
      if (preg_match('/random|group|title/', $order)) 
        return PAGELIST_ITEM | PAGELIST_POST;
      return PAGELIST_POST;

    case PAGELIST_ITEM:
      if (!isset($page['title']) && strpos($order, 'title')!==false) 
         $page['title'] = PageVar($pn, '$Title');
      if (strpos($order, 'group')!==false) 
         $page['group'] = PageVar($pn, '$Group');
      if (strpos($order, 'random')!==false) 
         $page['random'] = rand();
      if (preg_match_all('/\\$:?\\w+/', $order, $match, PREG_PATTERN_ORDER)) 
        foreach($match[0] as $m) $PCache[$pn][$m] = PageVar($pn, $m);
      return 1;
  }

  ## case PAGELIST_POST
  StopWatch('PageListSort begin');
  foreach(preg_split('/[\\s,|]+/', $order, -1, PREG_SPLIT_NO_EMPTY) as $o) {
    if ($o{0} == '-') { $r = '-'; $o = substr($o, 1); }
    else $r = '';
    if (@$PageListSortCmp[$o]) 
      $code .= "\$c = {$PageListSortCmp[$o]}; "; 
    else 
      $code .= "\$c = @strcasecmp(\$PCache[\$x]['$o'],\$PCache[\$y]['$o']); ";
    $code .= "if (\$c) return $r\$c;\n";
  }
  if ($code) 
    uasort($list,
           create_function('$x,$y', "global \$PCache; $code return 0;"));
  StopWatch('PageListSort end');
}


## HandleSearchA performs ?action=search.  It's basically the same
## as ?action=browse, except it takes its contents from Site.Search.
function HandleSearchA($pagename, $level = 'read') {
  global $PageSearchForm, $FmtV, $HandleSearchFmt, 
    $PageStartFmt, $PageEndFmt;
  SDV($HandleSearchFmt,array(&$PageStartFmt, '$PageText', &$PageEndFmt));
  SDV($PageSearchForm, '$[{$SiteGroup}/Search]');
  $form = RetrieveAuthPage($pagename, $level, true, READPAGE_CURRENT);
  if (!$form) Abort("?unable to read $pagename");
  PCache($pagename, $form);
  $text = preg_replace('/\\[([=@])(.*?)\\1\\]/s', ' ', $form['text']);
  if (!preg_match('/\\(:searchresults(\\s.*?)?:\\)/', $text))
    foreach((array)$PageSearchForm as $formfmt) {
      $form = ReadPage(FmtPageName($formfmt, $pagename), READPAGE_CURRENT);
      if ($form['text']) break;
    }
  $text = @$form['text'];
  if (!$text) $text = '(:searchresults:)';
  $FmtV['$PageText'] = MarkupToHTML($pagename,$text);
  PrintFmt($pagename, $HandleSearchFmt);
}


########################################################################
## The functions below provide different formatting options for
## the output list, controlled by the fmt= parameter and the
## $FPLFormatOpt hash.
########################################################################

function FPLTemplate($pagename, &$matches, $opt) {
  global $Cursor, $FPLFormatOpt, $FPLTemplatePageFmt;
  SDV($FPLTemplatePageFmt, array('{$FullName}',
    '{$SiteGroup}.LocalTemplates','{$SiteGroup}.PageListTemplates'));

  $template = @$opt['template'];
  if (!$template) $template = @$opt['fmt'];

  list($tname, $qf) = explode('#', $template, 2);
  if ($tname) $tname = array(MakePageName($pagename, $tname));
  else $tname = (array)$FPLTemplatePageFmt;
  foreach ($tname as $t) {
    $t = FmtPageName($t, $pagename);
    if (!PageExists($t)) continue;
    if ($qf) $t .= "#$qf";
    $ttext = IncludeText($pagename, $t, true);
    if (!$qf || strpos($ttext, "[[#$qf]]") !== false) break;
  }

  ##   remove any anchor markups to avoid duplications
  $ttext = preg_replace('/\\[\\[#[A-Za-z][-.:\\w]*\\]\\]/', '', $ttext);

  if (!@$opt['order'] && !@$opt['trail']) $opt['order'] = 'name';
  $matches = array_values(MakePageList($pagename, $opt, 0));
  if (@$opt['count']) array_splice($matches, $opt['count']);

  $savecursor = $Cursor;
  $pagecount = 0; $groupcount = 0; $grouppagecount = 0;
  $vk = array('{$PageCount}', '{$GroupCount}', '{$GroupPageCount}');
  $vv = array(&$pagecount, &$groupcount, &$grouppagecount);

  $lgroup = ''; $out = '';
  foreach($matches as $i => $pn) {
    $prev = (string)@$matches[$i-1];
    $next = (string)@$matches[$i+1];
    $Cursor['<'] = $Cursor['&lt;'] = $prev;
    $Cursor['='] = $pn;
    $Cursor['>'] = $Cursor['&gt;'] = $next;
    $group = PageVar($pn, '$Group');
    if ($group != $lgroup) { $groupcount++; $grouppagecount = 0; }
    $grouppagecount++; $pagecount++;

    $item = str_replace($vk, $vv, MarkupEscape($ttext));
    $item = preg_replace('/\\{(=|&[lg]t;)(\\$:?\\w+)\\}/e',
                "PageVar(\$pn, '$2', '$1')", $item);
    $out .= MarkupRestore($item);
    $lgroup = $group;
  }
  $class = preg_replace('/[^-a-zA-Z0-9\\x80-\\xff]/', ' ', @$opt['class']);
  $div = ($class) ? "<div class='$class'>" : '<div>';
  return $div.MarkupToHTML($pagename, $out, array('escape' => 0)).'</div>';
}


########################################################################
## The functions below optimize searches by maintaining a file of
## words and link cross references (the "page index").
########################################################################

## PageIndexTerms($terms) takes an array of strings and returns a
## normalized list of associated search terms.  This reduces the
## size of the index and speeds up searches.
function PageIndexTerms($terms) {
  $w = array();
  foreach((array)$terms as $t) {
    $w = array_merge($w, preg_split('/[^\\w\\x80-\\xff]+/', 
                                    strtolower($t), -1, PREG_SPLIT_NO_EMPTY));
  }
 return $w;
}

## The PageIndexUpdate($pagelist) function updates the page index
## file with terms and target links for the pages in $pagelist.
## The optional $dir parameter allows this function to be called
## via register_shutdown_function (which sometimes changes directories
## on us).
function PageIndexUpdate($pagelist, $dir = '') {
  global $PageIndexFile, $PageIndexTime, $Now;
  $abort = ignore_user_abort(true);
  if ($dir) chdir($dir);
  SDV($PageIndexTime, 10);
  if (!$pagelist || !$PageIndexFile) return;
  $c = count($pagelist);
  StopWatch("PageIndexUpdate begin ($c pages to update)");
  $pagelist = (array)$pagelist;
  $timeout = time() + $PageIndexTime;
  $cmpfn = create_function('$a,$b', 'return strlen($b)-strlen($a);');
  Lock(2);
  $ofp = fopen("$PageIndexFile,new", 'w');
  foreach($pagelist as $pn) {
    if (time() > $timeout) break;
    $page = ReadPage($pn, READPAGE_CURRENT);
    if ($page) {
      $targets = str_replace(',', ' ', @$page['targets']);
      $terms = PageIndexTerms(array(@$page['text'], $targets, $pn));
      usort($terms, $cmpfn);
      $x = '';
      foreach($terms as $t) { if (strpos($x, $t) === false) $x .= " $t"; }
      fputs($ofp, "$pn:$Now: $targets :$x\n");
    }
    $updated[$pn]++;
  }
  $ifp = @fopen($PageIndexFile, 'r');
  if ($ifp) {
    while (!feof($ifp)) {
      $line = fgets($ifp, 4096);
      while (substr($line, -1, 1) != "\n" && !feof($ifp)) 
        $line .= fgets($ifp, 4096);
      $i = strpos($line, ':');
      if ($i === false) continue;
      $n = substr($line, 0, $i);
      if (@$updated[$n]) continue;
      fputs($ofp, $line);
    }
    fclose($ifp);
  }
  fclose($ofp);
  if (file_exists($PageIndexFile)) unlink($PageIndexFile); 
  rename("$PageIndexFile,new", $PageIndexFile);
  fixperms($PageIndexFile);
  $c = count($updated);
  StopWatch("PageIndexUpdate end ($c updated)");
  ignore_user_abort($abort);
}

## PageIndexGrep returns a list of pages that match the strings
## provided.  Note that some search terms may need to be normalized
## in order to get the desired results (see PageIndexTerms above).
## Also note that this just works for the index; if the index is
## incomplete, then so are the results returned by this list.
## (MakePageList above already knows how to deal with this.)
function PageIndexGrep($terms, $invert = false) {
  global $PageIndexFile;
  if (!$PageIndexFile) return array();
  StopWatch('PageIndexGrep begin');
  $pagelist = array();
  $fp = @fopen($PageIndexFile, 'r');
  if ($fp) {
    $terms = (array)$terms;
    while (!feof($fp)) {
      $line = fgets($fp, 4096);
      while (substr($line, -1, 1) != "\n" && !feof($fp))
        $line .= fgets($fp, 4096);
      $i = strpos($line, ':');
      if (!$i) continue;
      $add = true;
      foreach($terms as $t) 
        if (strpos($line, $t) === false) { $add = false; break; }
      if ($add xor $invert) $pagelist[] = substr($line, 0, $i);
    }
    fclose($fp);
  }
  StopWatch('PageIndexGrep end');
  return $pagelist;
}
  
## PostPageIndex is inserted into $EditFunctions to update
## the linkindex whenever a page is saved.
function PostPageIndex($pagename, &$page, &$new) {
  global $IsPagePosted;
  if ($IsPagePosted) {
    register_shutdown_function('flush');
    register_shutdown_function('PageIndexUpdate',$pagename,getcwd());
  }
}

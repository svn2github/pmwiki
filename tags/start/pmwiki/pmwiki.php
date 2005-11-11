<?php
/*
    PmWiki
    Copyright 2001-2004 Patrick R. Michaud
    pmichaud@pobox.com
    http://www.pmichaud.com/

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/
error_reporting(E_ALL);
#if (ini_get('register_globals')) {
#  foreach($_REQUEST as $k=>$v) { unset(${$k}); }
$UnsafeGlobals = array_keys($GLOBALS);
define('PmWiki',1);
@include_once('scripts/version.php');
$GroupPattern = '[[:upper:]][\\w]*(?:-\\w+)*';
$NamePattern = '[[:upper:]\\d][\\w]*(?:-\\w+)*';
$WikiDir = new PageStore('wiki.d/$Group.$Name');
$WikiLibDirs = array($WikiDir,new PageStore('wikilib.d/$Group.$Name'));
$KeepToken = "\377\377";  
$K0=array('='=>'','@'=>'<code>');  $K1=array('='=>'','@'=>'</code>');
$Now=time();
$Newline="\262";
$PageEditFmt = "<form method='post' action='\$PageUrl?action=edit'>
  <input type='hidden' name='pagename' value='\$PageName' />
  <input type='hidden' name='action' value='edit' />
  <textarea name='text' cols='70' rows='24'>\$EditText</textarea><br />
  <input type='submit' name='post' value=' Save ' />
  <input type='submit' name='preview' value=' Preview ' />
  </form>";
$EditFields = array('text');
$EditFunctions = array('PostPage');
$DefaultPageTextFmt = 'Describe [[$PageName]] here.';
$ScriptUrl = '/beta/pmwiki.php';
$RedirectDelay = 0;
$DiffFunction = 'Diff';
$SysDiffCmd = '/usr/bin/diff';
$DiffKeepDays = 0;
$HTMLVSpace = "<p class='vspace'></p>";
umask(0);

## PSS is a helper function to strip the slashes inserted by /e in preg_replace.
function stripmagic($x) 
  { return get_magic_quotes_gpc() ? stripslashes($x) : $x; }
function PSS($x) 
  { return str_replace('\\\"','\"',$x); }

## Lock is used to make sure only one instance of PmWiki is running when
## files are being written.
function Lock($t) { return; }

## mkgiddir creates a directory, ensuring appropriate permissions
function mkgiddir($dir) { if (!file_exists($dir)) mkdir($dir); }

function FmtPageName($fmt,$pagename) {
  # Perform $-substitutions on $fmt relative to page given by $pagename
  global $GroupPattern,$NamePattern,$GCount,$UnsafeGlobals;
  if (strpos($fmt,'$')===false) return $fmt;                  
  if (!is_null($pagename) && !preg_match("/^($GroupPattern)[\\/.]($NamePattern)\$/",$pagename,$match)) return '';
  $fmt = preg_replace('/\\$([A-Z]\\w*Fmt)\\b/e','$GLOBALS[\'$1\']',$fmt);
  $fmt = preg_replace('/\\$\\[(.+?)\\]/e',"XL(PSS('$1'))",$fmt);
  static $qk = array('$PageUrl','$ScriptUrl','$Group','$Name');
  $qv = array('$ScriptUrl/$Group/$Name',$GLOBALS['ScriptUrl'],$match[1],
    $match[2]);
  $fmt = str_replace($qk,$qv,$fmt);
  if (strpos($fmt,'$')===false) return $fmt;
  if (count($GLOBALS)!=$GCount) {
    foreach($GLOBALS as $n=>$v) {
      if (is_array($v) || is_object($v)) { continue; }
      if (in_array($n,$UnsafeGlobals)) continue;
      $g["\$$n"] = $v;
    }
    $GCount = count($GLOBALS);
    krsort($g); reset($g);
  }
  $fmt = str_replace(array_keys($g),array_values($g),$fmt);
  return $fmt;
}


class PageStore {
  var $dirfmt;
  function PageStore($d='wiki.d') { $this->dirfmt=$d; }
  function read($pagename) {
    $newline = "\262";
    $pagefile = FmtPageName($this->dirfmt,$pagename);
    if ($pagefile && $fp=@fopen($pagefile,"r")) {
      while (!feof($fp)) {
        $line = fgets($fp,4096);
        while (substr($line,-1,1)!="\n" && !feof($fp)) 
          { $line .= fgets($fp,4096); }
        @list($k,$v) = explode('=',rtrim($line),2);
        if ($k=='newline') { $newline=$v; continue; }
        $page[$k] = str_replace($newline,"\n",$v);
      }
      fclose($fp);
    }
    return @$page;
  }
  function write($pagename,$page) {
    global $Now,$Version,$Newline;
    $page['name'] = $pagename;
    $page['time'] = $Now;
    $page['host'] = $_SERVER['REMOTE_ADDR'];
    $page['agent'] = $_SERVER['HTTP_USER_AGENT'];
    $page['rev'] = @$page['rev']+1;
    $s = false;
    $pagefile = FmtPageName($this->dirfmt,$pagename);
    mkgiddir(dirname($pagefile));
    if ($pagefile && ($fp=fopen("$pagefile,new","w"))) {
      $s = true && fputs($fp,"version=$Version\nnewline=$Newline\n");
      foreach($page as $k=>$v) 
        if ($k>'') $s = $s&&fputs($fp,str_replace("\n",$Newline,"$k=$v")."\n");
      $s = fclose($fp) && $s;
      if (file_exists($pagefile)) $s = $s && unlink($pagefile);
      $s = $s && rename("$pagefile,new",$pagefile);
    }
    if (!$s)
      Abort("Cannot write page to $pagename ($pagefile)...changes not saved");
  }
  function exists($pagename) {
    $pagefile = FmtPageName($this->dirfmt,$pagename);
    return ($pagefile && file_exists($pagefile));
  }
}

function ReadPage($pagename,$defaulttext=NULL) {
  # read a page from the appropriate directories given by $WikiReadDirsFmt.
  global $WikiLibDirs,$DefaultPageTextFmt;
  if (is_null($defaulttext)) $defaulttext=$DefaultPageTextFmt;
  Lock(1);
  foreach ($WikiLibDirs as $dir) {
    $page = $dir->read($pagename);
    if ($page) break;
  }
  if ($page['text']=='') 
    $page['text']=FmtPageName($defaulttext,$pagename);
  if (!$page['time']) $page['time']=$Now;
  return $page;
}

function PageExists($pagename) {
  global $WikiLibDirs;
  foreach((array)$WikiLibDirs as $dir)
    if ($dir->exists($pagename)) return true;
  return false;
}
  
function Abort($msg) {
  # exit pmwiki with an abort message
  echo "<h3>PmWiki can't process your request</h3>
    <p>$msg</p><p>We are sorry for any inconvenience.</p>";
  exit();
}

function Redirect($pagename,$urlfmt='$PageUrl') {
  # redirect the browser to $pagename
  global $RedirectDelay;
  clearstatcache();
  if (!PageExists($pagename)) $pagename=$DefaultPage;
  $pageurl = FmtPageName($urlfmt,$pagename);
  #header("Location: $pageurl");
  #header("Content-type: text/html");
  #echo "<html><head>
  #  <meta http-equiv='Refresh' Content='$RedirectDelay; URL=$pageurl' />
  #  <title>Redirect</title></head><body></body></html>";
  echo "<a href='$pageurl'>Redirect to $pageurl</a>";
  exit;
}
  
function Keep($x) {
  # Keep preserves a string from being processed by wiki markups
  global $KeepToken,$KPV,$KPCount;
  $KPCount++; $KPV[$KPCount]=$x;
  return $KeepToken.$KPCount.$KeepToken;
}

$MarkupPatterns[50]["/\\r/"] = '';
$MarkupPatterns[100]["/\\[([=@])(.*?)\\1\\]/se"] =
  "Keep(\$K0['$1'].PSS('$2').\$K1['$1'])";
$MarkupPatterns[300]["/(\\\\*)\\\\\n/e"] =
  "Keep(' '.str_repeat('<br />',strlen('$1')))";
$MarkupPatterns[2000]["\n"] = 
  '$lines = array_merge($lines,explode("\n",$x)); return NULL;';
$MarkupPatterns[4000]['/\\[\\[#([A-Za-z][-.:\\w]*)\\]\\]/'] =
  "<a name='$1' id='$1'></a>";
$MarkupPatterns[5000]['/^(!{1,6})(.*)$/e'] =
  "'<:block><h'.strlen('$1').'>$2</h'.strlen('$1').'>'";
$MarkupPatterns[5100]['/^(\\*+)/'] = '<:ul,$1>';
$MarkupPatterns[5200]['/^(#+)/'] = '<:ol,$1>';
$MarkupPatterns[5300]['/^(-+)&gt;/'] = '<:indent,$1>';
$MarkupPatterns[5400]['/^\\s*$/'] = '<:vspace>';
$MarkupPatterns[5500]['/^(\\s)/'] = '<:pre,1>';
$MarkupPatterns[5550]['/^\\|\\|.*\\|\\|.*$/e'] =
  "FormatTableRow(PSS('$0'))";
$MarkupPatterns[5555]['/^\\|\\|(.*)$/e'] =
  "substr(\$GLOBALS['BlockMarkups']['table'][0] = PSS('<table $1>'),0,0)";
$MarkupPatterns[5600]['/^(:+)([^:]+):/'] =
  '<:dl,$1><dt>$2</dt><dd>';
$MarkupPatterns[5700]['/^----+/'] = 
  '<:block><hr />';
$MarkupPatterns[5900]['/^(<:([^>]+)>)?/e'] = "Block('$2');";
$MarkupPatterns[7000]["/'''''(.*?)'''''/"] =
  '<strong><em>$1</em></strong>';
$MarkupPatterns[7010]["/'''(.*?)'''/"] =
  '<strong>$1</strong>';
$MarkupPatterns[7020]["/''(.*?)''/"] =
  '<em>$1</em>';
$MarkupPatterns[7030]["/@@(.*?)@@/"] =
  '<code>$1</code>';
$MarkupPatterns[7040]["/\\[(([-+])+)(.*?)\\1\\]/e"] =
  "'<span style=\'font-size:'.(round(pow(1.2,$2strlen('$1'))*100,0)).'%\'>'.PSS('$3</span>')";
$MarkupPatterns[8000]["/$KeepToken(\\d+?)$KeepToken/e"] =
  '$GLOBALS[\'KPV\'][\'$1\']';

$BlockMarkups = array(
  'block' => array('','',''),
  'ul' => array('<ul><li>','</li><li>','</li></ul>'),
  'dl' => array('<dl>','</dd>','</dd></dl>'),
  'ol' => array('<ol><li>','</li><li>','</li></ol>'),
  'p' => array('<p>','','</p>'),
  'indent' => 
     array("<div class='indent'>","</div><div class='indent'>",'</div>'),
  'pre' => array('<pre> ',' ','</pre>'),
  'table' => array("<table width='100%'>",'','</table>')
);

function Block($b) {
  global $BlockMarkups,$HTMLVSpace;
  static $cs,$vspaces;
  if (!$cs) $cs=array();
  $out = '';
  if (!$b) $b='p,1';
  @list($code,$depth) = explode(',',$b);
  if ($code=='vspace') { 
    $vspaces.="\n"; 
    if (@$cs[0]!='p') return; 
  }
  if ($depth==0) $depth=strlen($depth);
  while (count($cs)>$depth) 
    { $c = array_pop($cs); $out .= $BlockMarkups[$c][2]; }
  if ($depth>0 && $depth==count($cs) && $cs[$depth-1]!=$code)
    { $c = array_pop($cs); $out .= $BlockMarkups[$c][2]; }
  if ($vspaces) { 
    $out .= (@$cs[0]=='pre') ? $vspaces : $HTMLVSpace; 
    $vspaces=''; 
  }
  if ($depth==0) { return $out; }
  if ($depth==count($cs)) { return $out.$BlockMarkups[$code][1]; }
  while (count($cs)<$depth-1) 
    { array_push($cs,'dl'); $out .= $BlockMarkups['dl'][0].'<dd>'; }
  if (count($cs)<$depth) {
    array_push($cs,$code);
    $out .= $BlockMarkups[$code][0];
  }
  return $out;
}

function FormatTableRow($x) {
  global $Block,$TableCellAttr;
  $x = preg_replace('/\\|\\|$/','',$x);
  $td = explode('||',$x); $y='';
  for($i=0;$i<count($td);$i++) {
    if ($td[$i]=='') continue;
    if (preg_match('/^\\s+$/',$td[$i])) $td[$i]='&nbsp;';
    $attr = $TableCellAttr;
    if (preg_match('/^\\s.*\\s$/',$td[$i])) { $attr .= " align='center'"; }
    elseif (preg_match('/^\\s/',$td[$i])) { $attr .= " align='right'"; }
    for ($colspan=1;$i+$colspan<count($td);$colspan++) 
      if ($td[$colspan+$i]!='') break;
    if ($colspan>1) { $attr .= " colspan='$colspan'"; }
    $y .= "<td $attr>".$td[$i].'</td>';
  }
  return "<:table,1><tr>$y</tr>";
}


function MarkupToHTML($pagename,$text) {
  # convert wiki markup text to HTML output
  global $MarkupPatterns,$K0,$K1;

  ksort($MarkupPatterns);
  foreach($MarkupPatterns as $n=>$a)
    foreach($a as $p=>$r) $markpats[$p]=$r;
  foreach((array)$text as $l) $lines[] = htmlspecialchars($l,ENT_NOQUOTES);
  while (count($lines)>0) {
    $x = array_shift($lines);
    foreach($markpats as $p=>$r) {
      if (substr($p,0,1)=='/') $x=preg_replace($p,$r,$x); 
      elseif ($p=='' || strstr($x,$p)!==false) $x=eval($r);
      if (is_null($x)) continue 2;
    }
    if ($x>'') $out[] = "$x\n";
  }
  $x = Block('block');
  if ($x>'') $out[] = "$x\n";
  return implode('',(array)$out);
}

function HandleBrowse($pagename) {
  # handle display of a page
  $page = ReadPage($pagename);
  if (!$page) Abort('Invalid page name');
  $PageText = MarkupToHTML($pagename,$page['text']);
  print $PageText;
}

function Diff($oldtext,$newtext) {
  global $TempDir,$SysDiffCmd;
  if (!$SysDiffCmd) return '';
  $tempold = tempnam($TempDir,'old');
  if ($oldfp=fopen($tempold,'w')) { fputs($oldfp,$oldtext); fclose($oldfp); }
  $tempnew = tempnam($TempDir,'new');
  if ($newfp=fopen($tempnew,'w')) { fputs($newfp,$newtext); fclose($newfp); }
  $diff = '';
  $diff_handle = popen("$SysDiffCmd $tempold $tempnew",'r');
  if ($diff_handle) {
    while (!feof($diff_handle)) $diff .= fread($diff_handle,4096);
    pclose($diff_handle);
  }
  @unlink($tempold); @unlink($tempnew);
  return $diff;
} 

function PostPage($pagename,&$page,&$new) {
  global $Now,$WikiDir,$IsPagePosted,$DiffFunction,$DiffKeepDays;
  if (@$_REQUEST['post']) {
    if ($new['text']==$page['text']) { Redirect($pagename); return; }
    $new["author:$Now"] = @$Author;
    $new["host:$Now"] = $_SERVER['REMOTE_ADDR'];
    $diffclass = preg_replace('/\\W/','',@$_POST['diffclass']);
    if ($page["time"]>0) 
      $new["diff:$Now:{$page['time']}:$diffclass"] =
        $DiffFunction($new['text'],$page['text']);
    $keepgmt = $Now-$DiffKeepDays * 86400;
    $keys = array_keys($new);
    foreach($keys as $k)
      if (preg_match("/^\\w+:(\\d+)/",$k,$match) && $match[1]<$keepgmt)
        unset($new[$k]);
    $WikiDir->write($pagename,$new);
    $IsPagePosted=true;
  }
}

function HandleEdit($pagename) {
  global $PageEditFmt,$EditText,$EditFields,$EditFunctions,$IsPagePosted;
  $page = ReadPage($pagename);
  $new = $page;
  foreach((array)$EditFields as $k) 
    if (isset($_POST[$k])) $new[$k]=str_replace("\r",'',stripmagic($_POST[$k]));
  foreach((array)$EditFunctions as $fn) $fn($pagename,$page,$new);
  if ($IsPagePosted) { Redirect($pagename); return; }
  $EditText = htmlspecialchars($new['text'],ENT_NOQUOTES);
  print FmtPageName($PageEditFmt,$pagename);
}

function HandleSource($pagename) {
  header("Content-type: text/plain");
  $page = ReadPage($pagename);
  echo $page['text'];
}

$action = @$_REQUEST['action'];
if ($action=='edit') HandleEdit('PmWiki.TextFormattingRules');
elseif ($action=='source') HandleSource('PmWiki.TextFormattingRules');
elseif ($action=='test') include_once('tests/00test.php');
else HandleBrowse('PmWiki.TextFormattingRules'); 

?> 

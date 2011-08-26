<?php
$LoginRedirectPage = 'mini.php';
$Stylesheet = "mini.css";
include "login.php";

header('Cache-Control: no-cache, must-revalidate'); // HTTP/1.1
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past

require_once 'settings-template.php';
if (file_exists('settings.php')) require_once 'settings.php';
require_once 'functions.php';
require_once 'version.php';

global $action, $id;

$groupmode = !(isset($_COOKIE['c_filemode']) || isset($_COOKIE['c_historymode']));
$historymode = isset($_COOKIE['c_historymode']);
$wantstop = isset($_COOKIE['c_wantstop']);
SetCookie('c_wantstop', '', time() - 10000); // delete cookie

if (isset($_REQUEST['historymode'])) {
	$historymode = True;
	$groupmode = False;
	SetCookie('c_historymode', '1', 0);
	SetCookie('c_filemode', '', time() - 10000); // delete cookie
}
if (isset($_REQUEST['filemode'])) {
	$groupmode = False;
	$historymode = False;
	SetCookie('c_filemode', '1', 0);
	SetCookie('c_historymode', '', time() - 10000); // delete cookie
}
if (isset($_REQUEST['groupmode'])) {
	$groupmode = True;
	$historymode = False;
	SetCookie('c_filemode', '', time() - 10000); // delete cookie
	SetCookie('c_historymode', '', time() - 10000); // delete cookie
}

if (isset ($_REQUEST['action'])) {
	$action = $_REQUEST['action'];
	if ($action == 'shutdown' && $ServerStopCommand != '')
	{
		SetCookie('c_wantstop', '1', 0);
	}
	else if (isset ($_REQUEST['id']))
	{
		$edittext = '';
		if (isset ($_REQUEST['edittext'])) {
			$edittext = $_REQUEST['edittext'];
		}
		
		GetRequest("editqueue", array($action, (int)$_REQUEST['offset'], $edittext, (int)$_REQUEST['id']));
		if ($action == "groupresume")
			GetRequest("editqueue", array("grouppauseextrapars", (int)$_REQUEST['offset'], '', (int)$_REQUEST['id']));
	}
	else
		GetRequest($action, "");
}

if (isset ($_REQUEST['rate'])) {
	//set max download option
	GetRequest("rate", (int)$_REQUEST['rate']); 
}

if (isset ($_FILES['nzbfile'])) {
	$upload_status = upload_file($_FILES['nzbfile']);
	SetCookie("upload_status", $upload_status, time()+30); // expire in 30 seconds
	Redirect($LoginRedirectPage);
}

$page = 0;
if (isset($_REQUEST['page'])) {
	$page = $_REQUEST['page'];
	SetCookie("c_page", $page, 0);
} else if (isset($_COOKIE['c_page'])) {
	$page = (int)$_COOKIE['c_page'];
}

$logpage = 0;
if (isset($_REQUEST['logpage'])) {
	$logpage = $_REQUEST['logpage'];
	SetCookie("c_logpage", $logpage, 0);
} else if (isset($_COOKIE['c_logpage'])) {
	$logpage = (int)$_COOKIE['c_logpage'];
}

$postlogpage = 0;
if (isset($_REQUEST['postlogpage'])) {
	$postlogpage = $_REQUEST['postlogpage'];
	SetCookie("c_postlogpage", $postlogpage, 0);
} else if (isset($_COOKIE['c_postlogpage'])) {
	$postlogpage = (int)$_COOKIE['c_postlogpage'];
}

if (isset($_REQUEST['start']) && $ServerStartCommand != '') {
	SetCookie("c_start", 1, 0);
}

$RpcApi = GetAvailableApi();
//echo "<!-- API: $RpcApi -->\n";
if (!isset($RpcApi)) {
	echo "NZBGetWeb: Could not find required extension or library. Consult README-file for installation instructions.";
	Exit(-1);
}

if (count($_GET) > 0)
{
	Redirect($LoginRedirectPage);
}

$wantstart = false;
$connected = false;
$phpvars = null;

if (isset($_COOKIE['c_start']) && $ServerStartCommand != '') {
	SetCookie("c_start", "", time() - 10000); // delete cookie
	$wantstart = true;
}

if (!$wantstart) {
	$phpvars = GetInfo($groupmode);
	$connected = !IsConnectError($phpvars);
}

function add_category_combo($category, $id, $paused) {
	global $Categories, $MiniJavaScript;
	
	if ($category == '' && count($Categories) == 0) {
		return;
	}
	
	$catfound = false;

  if ($MiniJavaScript) {
		echo '<select class="'.($paused ? 'pausedcategorycombo' : 'categorycombo').'" onchange="location=\'?action=groupsetcategory&edittext=\' + this.options[this.selectedIndex].text + \'&offset=-1&id='.$id.'\'">';
		foreach ($Categories as $cat) {
			if ($cat==$category) {
				echo "<option selected='selected'>$cat</option>";
				$catfound = true;
			} else {
				echo "<option>$cat</option>";
			}
		}
		
		if (!$catfound) {
			echo "<option value ='$category' selected='selected'>$category</option>";
		}
		
		echo "</select> ";
  } else {
		echo '<form action="mini.php" method="get">';
		echo '<input type="hidden" name="action" value="groupsetcategory">';
		echo '<input type="hidden" name="offset" value="-1">';
		echo "<input type='hidden' name='id' value='$id'>";
		echo '<nobr>';
		echo '<select name="edittext" class="'.($paused ? 'pausedcategorycombo' : 'categorycombo').'">';
		foreach ($Categories as $cat) {
			if ($cat==$category) {
				echo "<option selected='selected'>$cat</option>";
				$catfound = true;
			} else {
				echo "<option>$cat</option>";
			}
		}

		if (!$catfound) {
			echo "<option value ='$category' selected='selected'>$category</option>";
		}

		echo "</select> ";

		echo '<input type="submit" class="submit" value="Set">';
		echo '</nobr>';
		echo '</form>';
	}
}

function currently_downloading ($phpvars) {
	echo '<div class="block">';
	if (isset($phpvars['activegroup'])) 
	{
		//Download in progress, display info
		$cur_queued=$phpvars['activegroup'];
		if (!($phpvars['status']['DownloadPaused'] || $phpvars['status']['Download2Paused']))
			echo '<center>Currently downloading</center><br>';
		else
			echo '<center>Currently downloading (pausing)</center><br>';


		echo '<table width="100%">';
		echo '<tr><td colspan="7" align="center">name</td><td align="left">category</td></tr>';
		echo '<tr><td colspan="8"><table><tr><td align="center">&nbsp;&nbsp;speed&nbsp;&nbsp;</td><td align="center">&nbsp;&nbsp;&nbsp;&nbsp;left&nbsp;&nbsp;&nbsp;&nbsp;</td><td align="center">remaining time</td></tr></table></tr>';

		echo '<tr><td colspan="8" width="100%"><table class="tableline" width="100%"><tr><td></td></tr></table></td></tr>';

		$grouppaused=($cur_queued['PausedSizeLo'] != 0) && ($cur_queued['RemainingSizeLo']==$cur_queued['PausedSizeLo']);
		echo ($grouppaused ? '<tr class="pausedgroup">' : '<tr class="unpausedgroup">');
		echo '<td width="1"><a href="?action=groupdelete&offset=0&id='.$cur_queued['LastID'].'"><IMG src=images/cancel.gif width=15 height=15 alt="remove nzb" title="remove nzb"></a></td>';
		echo '<td width="1"><a href="?action=groupmovetop&offset=0&id='.$cur_queued['LastID'].'"><IMG src=images/first.gif width=15 height=15 alt="move nzb to top in queue" title="move nzb to top in queue"></a></td>';
		echo '<td width="1"><a href="?action=groupmoveoffset&offset=-1&id='.$cur_queued['LastID'].'"><IMG src=images/up.gif width=15 height=15 alt="move nzb up" title="move nzb up"></a></td>';
		echo '<td width="1"><a href="?action=groupmoveoffset&offset=1&id='.$cur_queued['LastID'].'"><IMG src=images/down.gif width=15 height=15 alt="move nzb down" title="move nzb down"></a></td>';
		echo '<td width="1"><a href="?action=groupmovebottom&offset=0&id='.$cur_queued['LastID'].'"><IMG src=images/last.gif width=15 height=15 alt="move nzb to bottom in queue" title="move nzb to bottom in queue"></a></td>';
		echo '<td width="1"><a href="?action=grouppause&offset=0&id='.$cur_queued['LastID'].'"><IMG src=images/pause.gif width=15 height=15 alt="pause nzb" title="pause nzb"></a></td>';
		echo '<td width="1"><a href="?action=groupresume&offset=0&id='.$cur_queued['LastID'].'"><IMG src=images/resume.gif width=15 height=15 alt="resume nzb" title="resume nzb"></a></td>';
		echo '<td width="100%">';
		add_category_combo($cur_queued['Category'], $cur_queued['LastID'], $grouppaused);
		echo '</td>';
		echo '</tr>';

		echo '<td width="100%" colspan="8" valign="top">'.namereplace($cur_queued['NZBNicename']).'</td>';

		echo ($grouppaused ? '<tr class="pausedgroup">' : '<tr class="unpausedgroup">');
		echo '<td colspan="8">';
		echo '<table><tr>';
		echo "<td align='center'>".round0($phpvars['status']['DownloadRate']/1024)." KB/s&nbsp;&nbsp;</td>";
		echo "<td align='center'>".formatSizeMB($cur_queued['RemainingSizeMB']-$cur_queued['PausedSizeMB'])."&nbsp;&nbsp;</td>";
		echo "<td align='center'>";
		if ($phpvars['status']['DownloadRate'] > 0)
			echo sec2hms(($cur_queued['RemainingSizeMB']-$cur_queued['PausedSizeMB'])/($phpvars['status']['DownloadRate']/1024/1024));
		echo '</td>';
		echo '</tr></table>';
		echo '</td>';
		echo '</tr>';

		echo '<tr>';
		echo '<td colspan="8">';
		echo '<table width="100%">';
		echo '<tr>';
		echo '<td width="100%" class="progress">';
		$a=$cur_queued['FileSizeMB']-$cur_queued['PausedSizeMB'];
		if ($a > 0)
			$percent_complete=round0(($a-($cur_queued['RemainingSizeMB']-$cur_queued['PausedSizeMB']))*100/$a);
		else
			$percent_complete=100;
		echo "<IMG src=images/pbar.gif height=12 width=$percent_complete%>";
		echo '</td>';
		echo "<td>$percent_complete%";
		echo '</tr>';
		echo '</table>';
		echo '</td>';
		echo '</tr>';

		echo '</table>';
	}
	else {
		if ($phpvars['status']['DownloadPaused'] || $phpvars['status']['Download2Paused']) {
			$pauseregister = $phpvars['status']['DownloadPaused'] && $phpvars['status']['Download2Paused'] ? ' (+2)' :
				($phpvars['status']['Download2Paused'] && !$phpvars['status']['DownloadPaused'] ? ' (2)' : '');
			echo '<center>Server is paused'.$pauseregister.'</center><br>';
			echo '<center>';
			if ($phpvars['status']['DownloadPaused']) {
				echo '<a class="commandlink" href="?action=resumedownload">resume</a>';
			}
			if ($phpvars['status']['Download2Paused']) {
				if ($phpvars['status']['DownloadPaused']) {
					echo '&nbsp;&nbsp;&nbsp;&nbsp;';
				}
				echo '<a class="commandlink" href="?action=resumedownload2">resume (2)</a>';
			}
			echo '</center>';
		} else
			echo '<center>Server is sleeping</center><br>';
	}
	echo '</div><br>';
}

function queued_downloading($phpvars, $page) {
	if (count($phpvars['queuedgroups']) == 0)
		return;

	global $GroupsPerPage;
	$cnt = count($phpvars['queuedgroups']);
	$pagecount = pagecount($cnt, $GroupsPerPage);
	if ($page > $pagecount)
		$page = $pagecount;
	if ($page < 1)
		$page = 1;
	
	echo '<div class="block">';
	echo '<center>Queued</center><br>';

	echo '<table width="100%">';
	echo '<tr><td colspan="7" align="center">name</td><td align="left">category</td></tr>';
	echo '<tr><td colspan="8"><table><tr><td align="center">&nbsp;&nbsp;total&nbsp;&nbsp;</td><td align="center">&nbsp;&nbsp;&nbsp;&nbsp;left&nbsp;&nbsp;&nbsp;&nbsp;</td><td align="center">estimated time</td></tr></table></tr>';

	foreach (array_slice($phpvars['queuedgroups'], ($page - 1) * $GroupsPerPage, $GroupsPerPage) as $cur_queued) 
	{
		echo '<tr><td colspan="8" width="100%"><table class="tableline" width="100%"><tr><td></td></tr></table></td></tr>';

		$grouppaused=($cur_queued['PausedSizeLo'] != 0) && ($cur_queued['RemainingSizeLo']==$cur_queued['PausedSizeLo']);
		echo ($grouppaused ? '<tr class="pausedgroup">' : '<tr class="unpausedgroup">');
		echo '<td width="1"><a href="?action=groupdelete&offset=0&id='.$cur_queued['LastID'].'"><IMG src=images/cancel.gif width=15 height=15 alt="remove nzb" title="remove nzb"></a></td>';
		echo '<td width="1"><a href="?action=groupmovetop&offset=0&id='.$cur_queued['LastID'].'"><IMG src=images/first.gif width=15 height=15 alt="move nzb to top in queue" title="move nzb to top in queue"></a></td>';
		echo '<td width="1"><a href="?action=groupmoveoffset&offset=-1&id='.$cur_queued['LastID'].'"><IMG src=images/up.gif width=15 height=15 alt="move nzb up" title="move nzb up"></a></td>';
		echo '<td width="1"><a href="?action=groupmoveoffset&offset=1&id='.$cur_queued['LastID'].'"><IMG src=images/down.gif width=15 height=15 alt="move nzb down" title="move nzb down"></a></td>';
		echo '<td width="1"><a href="?action=groupmovebottom&offset=0&id='.$cur_queued['LastID'].'"><IMG src=images/last.gif width=15 height=15 alt="move nzb to bottom in queue" title="move nzb to bottom in queue"></a></td>';
		echo '<td width="1"><a href="?action=grouppause&offset=0&id='.$cur_queued['LastID'].'"><IMG src=images/pause.gif width=15 height=15 alt="pause nzb" title="pause nzb"></a></td>';
		echo '<td width="1"><a href="?action=groupresume&offset=0&id='.$cur_queued['LastID'].'"><IMG src=images/resume.gif width=15 height=15 alt="resume nzb" title="resume nzb"></a></td>';
		echo '<td width="100%">';
		add_category_combo($cur_queued['Category'], $cur_queued['LastID'], $grouppaused);
		echo '</td>';
		echo '</tr>';

		echo '<td width="100%" colspan="8" valign="top">'.namereplace($cur_queued['NZBNicename']).'</td>';

		echo ($grouppaused ? '<tr class="pausedgroup">' : '<tr class="unpausedgroup">');
		echo '<td colspan="8">';
		echo '<table><tr>';
		echo "<td align='center'>".formatSizeMB($cur_queued['FileSizeMB'])."&nbsp;&nbsp;</td>";
		echo "<td align='center'>".formatSizeMB($cur_queued['RemainingSizeMB']-$cur_queued['PausedSizeMB'])."&nbsp;&nbsp;</td>";
		echo "<td align='center'>";
		if ($phpvars['status']['DownloadRate'] > 0)
			echo sec2hms(($cur_queued['RemainingSizeMB']-$cur_queued['PausedSizeMB'])/($phpvars['status']['DownloadRate']/1024/1024));
		echo '</td>';
		echo '</tr></table>';
		echo '</td>';
		echo '</tr>';
	}

	echo '</table>';
	
	if ($cnt > $GroupsPerPage) {
		pagelist($cnt, $page, $GroupsPerPage, 'page');
	}
	
	echo '</div><br>';
}

function has_other_postfiles_from_same_nzb($phpvars, $proc){
	foreach ($phpvars['postqueue'] as $cur_proc) {
		if (($cur_proc['InfoName'] != $proc['InfoName']) &&
			($cur_proc['NZBFilename'] == $proc['NZBFilename']))
			return true;
	}
	return false;
}

function currently_processing($phpvars, $page){
	if (count($phpvars['postqueue']) == 0) 
		return;
		
	$cur_proc=$phpvars['postqueue'][0];
	$disptime="";
	$completed = "";
	$remtime=true;

	if ($cur_proc['Stage'] == 'LOADING_PARS') {	
		$stage="loading par-files";
	}
	else if ($cur_proc['Stage'] == 'VERIFYING_SOURCES') {	
		$stage="verifying files";
	}
	else if ($cur_proc['Stage'] == 'REPAIRING') {
		$stage="repairing files";
	}
	else if ($cur_proc['Stage'] == 'VERIFYING_REPAIRED') {
		$stage="verifying repaired files"; 
	}
	else if ($cur_proc['Stage'] == 'EXECUTING_SCRIPT') {
		$stage="executing script"; 
		$remtime=false;
	}
	else {
		$stage="";
	}

	if ($remtime)
	{
		if ($cur_proc['StageTimeSec'] > 0 && $cur_proc['StageProgress'] > 0) {
			$requiredtime = $cur_proc['StageTimeSec'] * 1000 / $cur_proc['StageProgress'] - $cur_proc['StageTimeSec'];
			$disptime = sec2hms($requiredtime);
		}
	}
	else
	{
		$disptime=sec2hms($cur_proc['StageTimeSec']);
	}

	if (($cur_proc['Stage'] == 'REPAIRING') || 
		($cur_proc['Stage'] == 'VERIFYING_SOURCES') || 
		($cur_proc['Stage'] == 'VERIFYING_REPAIRED'))
		$completed = round1($cur_proc['StageProgress'] / 10)."%";
	
	echo '<div class = "block"><center>Currently processing</center><br>';
	echo '<table width="100%">';
	echo '<tr>';
	echo '<td colspan="3" align="center">name</td>';
	echo '</tr>';
	echo '<tr>';
	echo '<td>stage</td><td align="right">%</td><td align="right">'.($remtime ? "remaining time" : "elapsed time").'</td>';
	echo '</tr>';

	echo '<tr><td colspan="3" width="100%"><table class="tableline" width="100%"><tr><td></td></tr></table></td></tr>';

	echo '<tr>';
	echo '<td colspan="3" valign="top">'.namereplace($cur_proc[has_other_postfiles_from_same_nzb($phpvars, $cur_proc) ? 'InfoName' : 'NZBNicename']).'</td>';
	echo '</tr>';
	echo '<tr>';
	echo '<td valign="top">'.$stage.'</td>';
	echo '<td valign="top" align="right">'.$completed.'</td>';
	echo '<td valign="top" align="right">'.$disptime.'</td>';
	echo '</tr>';
	echo '</table>';
	
	if (($cur_proc['Stage'] == 'LOADING_PARS') || 
		($cur_proc['Stage'] == 'VERIFYING_SOURCES') || 
		($cur_proc['Stage'] == 'VERIFYING_REPAIRED')) {
		echo '<table width="100%">';
		echo '<tr>';
		echo '<td><small>'.($cur_proc['ProgressLabel']).' ('.(round1($cur_proc['FileProgress'] / 10)).'%)</small></td>';
		echo '</tr>';
		echo '</table>';
	}
	
// Messages	
	global $NewMessagesFirst, $PostMessagesPerPage, $MiniLogColumns;

	$a=$cur_proc['Log'];
	if ($PostMessagesPerPage > 0 && isset($a) && count($a) > 0) {
		
		if ($NewMessagesFirst)
			$a=array_reverse($a);
		
		$cnt = count($a);
		$pagecount = pagecount($cnt, $PostMessagesPerPage);
		if ($page > $pagecount)
			$page = $pagecount;
		if ($page < 1)
			$page = 1;
		
		$per_page = $PostMessagesPerPage;
		if ($NewMessagesFirst) {
			$start = ($page - 1) * $PostMessagesPerPage;
		} else {
			$start = $cnt - $page * $PostMessagesPerPage;
			if ($start < 0) {
				$per_page = $PostMessagesPerPage + $start;
				$start = 0;
			}
		}
	
		echo '<div class = "postlog"><center>Script-output</center>';
		echo '<table width="100%">';
		foreach (array_slice($a, $start, $per_page) as $info) {
			if ($MiniLogColumns) {
				echo '<tr><td valign="top" class="'.$info['Kind'].'">'.$info['Kind'].'</td><td valign="top">'.FormatLogText($info['Text']).'</td></tr>';
			} else {
				echo '<tr><td><span class="'.$info['Kind'].'">'.$info['Kind'].'</span> '.FormatLogText($info['Text']).'</td></tr>';
			}
		}
		echo '</table>';
		
		if ($cnt > $PostMessagesPerPage) {
			pagelist($cnt, $page, $PostMessagesPerPage, 'postlogpage');
		}
		echo '</div>';
	}

	echo '</div><br>';
}

function queued_processing($phpvars){
	$queue=array_slice($phpvars['postqueue'], 1);
	if (count($queue) == 0) 
		return;

	echo '<div class = "block"><center>Queued</center><br>';
	echo '<table width="100%">';
	//echo '<tr><td>name</td></tr>';
	foreach ($queue as $cur_proc) {
		echo "<tr>";
		echo "<td>".namereplace($cur_proc[has_other_postfiles_from_same_nzb($phpvars, $cur_proc) ? 'InfoName' : 'NZBNicename']).'</td>';
		echo '</tr>';
	}
	echo '</table></div><br>';
}

function logging ($phpvars, $page) {
	global $NewMessagesFirst, $MessagesPerPage, $LogTimeFormat, $MiniLogColumns,
	$TimeZoneCorrection;

	$a=$phpvars['log'];
	if ($NewMessagesFirst)
		$a=array_reverse($a);
	
	$cnt = count($a);
	$pagecount = pagecount($cnt, $MessagesPerPage);
	if ($page > $pagecount)
		$page = $pagecount;
	if ($page < 1)
		$page = 1;
	
	$per_page = $MessagesPerPage;
	if ($NewMessagesFirst) {
		$start = ($page - 1) * $MessagesPerPage;
	} else {
		$start = $cnt - $page * $MessagesPerPage;
		if ($start < 0) {
			$per_page = $MessagesPerPage + $start;
			$start = 0;
		}
	}

	echo '<div class = "block"><center>Messages</center><br>';
	echo '<table width="100%">';
	
	foreach (array_slice($a, $start, $per_page) as $info) {
		if ($MiniLogColumns) {
			echo "<tr><td valign='top' class='".$info['Kind']."'>".$info['Kind'].
			"&nbsp;</td><td valign='top' width='100%'>".
			($LogTimeFormat != '' ? "<span class='date'>".date($LogTimeFormat, $info['Time'] + $TimeZoneCorrection*60*60)."</span><br>" : "")
			.FormatLogText($info['Text'])."</td></tr>";
		} else {
			echo "<tr><td> <span class='".$info['Kind']."'>".$info['Kind'].'&nbsp;</span>'.
			($LogTimeFormat != '' ? '<span class="date">'.date($LogTimeFormat, $info['Time'] + $TimeZoneCorrection*60*60).'</span><br>' : '')
			.FormatLogText($info['Text']).'</td></tr>';
		}
	}
	echo '</table>';
	
	if ($cnt > $MessagesPerPage) {
		pagelist($cnt, $page, $MessagesPerPage, 'logpage');
	}
	
	echo '</div><br>';
}

function filelist($phpvars, $page) {
	global $FilesPerPage;

	$cnt = count($phpvars['files']);
	$pagecount = pagecount($cnt, $FilesPerPage);
	if ($page > $pagecount)
		$page = $pagecount;
	if ($page < 1)
		$page = 1;

	echo '<div class = "block"><center>Files for downloading</center><br>';
	echo '<table width="100%">';
	echo '<tr><td colspan="7">name</td><td align="right">total</td></tr>';
	
	foreach (array_slice($phpvars['files'], ($page - 1) * $FilesPerPage, $FilesPerPage)  as $cur_queued) {
		$paused=$cur_queued['Paused'];

		echo '<tr><td colspan="8" width="100%"><table class="tableline" width="100%"><tr><td></td></tr></table></td></tr>';
		echo ($paused ? '<tr class="pausedgroup">' : '<tr class="unpausedgroup">');
		echo '<td width="1"><a href="?action=filedelete&offset=0&id='.$cur_queued['ID'].'"><IMG src=images/cancel.gif width=15 height=15 alt="remove file" title="remove file"></a></td>';
		echo '<td width="1"><a href="?action=filemovetop&offset=0&id='.$cur_queued['ID'].'"><IMG src=images/first.gif width=15 height=15 alt="move file to top in queue" title="move file to top in queue"></a></td>';
		echo '<td width="1"><a href="?action=filemoveoffset&offset=-1&id='.$cur_queued['ID'].'"><IMG src=images/up.gif width=15 height=15 alt="move file up" title="move file up"></a></td>';
		echo '<td width="1"><a href="?action=filemoveoffset&offset=1&id='.$cur_queued['ID'].'"><IMG src=images/down.gif width=15 height=15 alt="move file down" title="move file down"></a></td>';
		echo '<td width="1"><a href="?action=filemovebottom&offset=0&id='.$cur_queued['ID'].'"><IMG src=images/last.gif width=15 height=15 alt="move file to bottom in queue" title="move file to bottom in queue"></a></td>';
		echo '<td width="1"><a href="?action=filepause&offset=0&id='.$cur_queued['ID'].'"><IMG src=images/pause.gif width=15 height=15 alt="pause file" title="pause file"></a></td>';
		echo '<td width="1"><a href="?action=fileresume&offset=0&id='.$cur_queued['ID'].'"><IMG src=images/resume.gif width=15 height=15 alt="resume file" title="resume file"></a></td>';
		echo '<td align="right" width="100%">'.(round1($cur_queued['FileSizeLo'] / 1024 / 1024)).' MB</td>';
		echo '</tr>';

		echo ($paused ? '<tr class="pausedgroup">' : '<tr class="unpausedgroup">');
		echo '<td colspan="8">'.namereplace($cur_queued['NZBNicename']).'/'.namereplace($cur_queued['Filename']).'</td>';
		echo '</tr>';
	}
	echo '</table>';
	
	if ($cnt > $FilesPerPage) {
		pagelist($cnt, $page, $FilesPerPage, 'page');
	}
	
	echo '</div><br>';
}

function history($phpvars, $page) {
	global $HistoryPerPage, $NewHistoryFirst, $HistoryTimeFormat,
	$TimeZoneCorrection;

	$cnt = count($phpvars['history']);
	$pagecount = pagecount($cnt, $HistoryPerPage);
	if ($page > $pagecount)
		$page = $pagecount;
	if ($page < 1)
		$page = 1;
 
	$history = $phpvars['history'];
	if (!$NewHistoryFirst)
		$history = array_reverse($history);	
		
	echo '<div class = "block"><center>History</center><br>';
	echo '<table width="100%">';
	echo '<tr><td></td><td colspan="3">name</td><td>category</td></tr>';
	echo '<tr><td></td><td width="60" align="right">size</td><td width="40" align="right">files&nbsp;&nbsp;</td><td width="80">Par Status</td><td width="80">Script Status</td></tr>';
	foreach ($history as $hist) {
		echo '<tr><td colspan="8" width="100%"><table class="tableline" width="100%"><tr><td></td></tr></table></td></tr>';
		echo '<tr>';
		echo '<td width="10"><a href="?action=historydelete&offset=0&id='.$hist['NZBID'].'"><IMG src="images/cancel.gif" width="15" height="15" alt="remove from history" title="remove from history"></a></td>';
		echo '<td colspan="4" valign="top" class="date">'.date($HistoryTimeFormat, $hist['HistoryTime'] + $TimeZoneCorrection*60*60).'</td>';
		echo '<td valign="top"><span class="category">'.$hist['Category'].'</span></td>';
		echo '</tr>';
		echo '<tr>';
		echo '<td width="10"></td>';
		echo '<td colspan="4" valign="top">'.$hist['NZBNicename'].'</td>';
		echo '</tr>';
		echo '<tr>';
		echo '<td width="10"></td>';
		echo '<td valign="top" align="right">'.formatSizeMB($hist['FileSizeMB']).'</td>';
		echo '<td valign="top" align="right">'.$hist['FileCount'].'&nbsp;&nbsp;</td>';
		echo '<td valign="top"><span class="'.$hist['ParStatus'].'">'.$hist['ParStatus'].'</span></td>';
		echo '<td valign="top"><span class="'.$hist['ScriptStatus'].'">'.$hist['ScriptStatus'].'</span></td>';
		echo "</tr>";
	}
	echo '</table>';
	
	if ($cnt > $HistoryPerPage) {
		pagelist($cnt, $page, $HistoryPerPage, 'page');
	} else {
		echo '<br>';
	}

	echo '</div><br>';
}

function pagecount($cnt, $per_page) {
	$pagecount = (int)($cnt / $per_page);
	if ($cnt % $per_page > 0)
		$pagecount++;
	return $pagecount;
}

function pagelist($cnt, $page, $per_page, $varname) {
	$pagecount = pagecount($cnt, $per_page);

	echo '<p><small>&nbsp;&nbsp;';
	for ($i = 1; $i <= $pagecount; $i++) {
		if ($i == $page)
			echo "<span class=\"curpage\">$i</span> &nbsp;";
		else
			echo '<span class="page"><a href="?'.$varname.'='.$i.'">'.$i.'</a></span> &nbsp;';
	}
	echo '</small></p>';
}

function download_rate($phpvars) {
	echo '<div class="block">';
	echo '<form action="mini.php" method="get"><center>Max download rate: ';
	echo '<input type="text" class="inputrate" name="rate" id="inputdownloadlimit" ';
	echo 'value='.($phpvars['status']['DownloadLimit'] / 1024).'>';
	echo ' <input type="submit" class="submit" value="Set"></center></form>';
	echo '</div><br>';
}

function serverinfobox($phpvars) {
	global $webversion;

	echo '<div class = "block"><center>';
	echo "NZBGet ".$phpvars['version']."<br/>";
	echo '<center>Web Interface '.$webversion.'</center><br/>';
	echo "<table width='250'>";
	echo "<tr><td>uptime:</td><td align=right><nobr>".sec2hms($phpvars['status']['UpTimeSec'])."</nobr></td></tr>";
	echo "<tr><td>download time:</td><td align=right><nobr>".sec2hms($phpvars['status']['DownloadTimeSec'])."</nobr></td></tr>";
	echo "<tr><td><nobr>average download rate:</nobr></td><td align=right><nobr>".round0($phpvars['status']['AverageDownloadRate']/1024)." KB/s</nobr></td></tr>";
	echo "<tr><td>total downloaded:</td><td align=right><nobr>".formatSizeMB($phpvars['status']['DownloadedSizeMB'])."</nobr></td></tr>";
	echo "<tr><td>free disk space:</td><td align=right><nobr>".formatSizeMB(freediskspace())."</nobr></td></tr>";
	echo '</table>';
	echo '</center></div><br>';
}	

function servercommandbox($phpvars) {
	global $connected, $WebUsername, $ServerStartCommand, $ServerConfigTemplate, 
		$ServerConfigFile, $groupmode, $historymode, $MiniJavaScript;
	
	echo '<div class = "block">';
	echo '<center>';

	echo '<a class="commandlink" href="?">refresh</a>';

	if ($connected) {
		if ($phpvars['status']['DownloadPaused']) {
			echo ' &nbsp;&nbsp;<a class="commandlink" href="?action=resume">resume</a>';
		} else {
			echo ' &nbsp;&nbsp;<a class="commandlink" href="?action=pause">pause</a>';
		}
	
		if ($ServerStartCommand != '') {
			echo ' &nbsp;&nbsp;<a class="commandlink" href="'.($MiniJavaScript ? 'javascript:ShutdownServer()' : '?action=shutdown').'">shutdown</a>';
		}
		
		if ($groupmode) {
			echo ' &nbsp;&nbsp;<a class="commandlink" href="?filemode=1&page=1&logpage=1">files</a>';
			echo ' &nbsp;&nbsp;<a class="commandlink" href="?historymode=1&page=1&logpage=1">history</a>';
		} elseif ($historymode) {
			echo ' &nbsp;&nbsp;<a class="commandlink" href="?filemode=1&page=1&logpage=1">files</a>';
			echo ' &nbsp;&nbsp;<a class="commandlink" href="?groupmode=1&page=1&logpage=1">groups</a>';
		} else {
			echo ' &nbsp;&nbsp;<a class="commandlink" href="?groupmode=1&page=1&logpage=1">groups</a>';
			echo ' &nbsp;&nbsp;<a class="commandlink" href="?historymode=1&page=1&logpage=1">history</a>';
		}
	} else {
		if ($ServerStartCommand != '') {
			echo ' &nbsp;&nbsp;<a class="commandlink" href="?start=1">start</a>';
		}
	}

	echo ' &nbsp;&nbsp;<a class="commandlink" href=config.php>config</a>';

	if (isset($WebUsername) && $WebUsername != '') {
		echo ' &nbsp;&nbsp;<a class="commandlink" href="logout.php">logout</a>';
	}

	echo '</center>';
	echo '</div><br>';
}

function upload_box($phpvars) {
	echo '<div class = "block"><center>Add NZB file to download queue<br>';
	echo '<form enctype="multipart/form-data" action="mini.php" method="post">';
	echo 'Choose a file: <input class="inputfile" name="nzbfile" type="file"/> ';
	echo '<input class="submit" type="submit" value="Submit File" />';
	echo '</form>';
	if (isset($_COOKIE['upload_status'])) {
		echo '<div class="block" id="upload_status">';
		echo '<center>file transfer status</center>';
		echo '<div>'.($_COOKIE['upload_status']).'</div>';
		echo '</div>';
	}
	echo '</center></div>';
}

function connect_error($errormsg) {
	global $ServerStartCommand, $ServerConfigTemplate, $ServerConfigFile;
	
	$connectfailed = !strncmp($errormsg, "ERROR: Connect error:", 21);
	$connectclosed = $errormsg == "ERROR: Server closed connection";
	$connectdecode = !strncmp($errormsg, "ERROR: Could not decode", 23);
	
	echo '<div class = "block">';
	if ($connectfailed) {
		echo '<font color="red">ERROR: NZBGetWeb could not connect to NZBGet-Server.</font><br><br>';
		echo 'Possible reasons include:<br>';
		echo '<list>';
		echo '<li>NZBGet-Server is not running';
		if ($ServerStartCommand != '') {
			echo ' (<a class="commandlink" href="javascript:updatestatus(\'status.php?start=1\')">start</a>)';
		}
		echo ';<il>';
		echo '<li>IP/Port-settings are incorrect. Check <a class="commandlink" href=config.php?section=COMMUNICATION%20WITH%20NZBGET-SERVER>config</a>;<il>';
		echo '<li>Firewall is not properly configured (if nzbget-server and web-interface run on different computer).<il>';
		echo '</list>';
		echo "<br><br>Error-message reported by OS: ".substr($errormsg, 22)."<br>";
	} else if ($connectclosed) {
		echo '<font color="red">ERROR: NZBGetWeb could not receive response from NZBGet-Server (although successfully connected).</font><br><br>';
		echo 'Possible reasons include:<br>';
		echo '<list>';
		echo '<li>Password incorrect. Check option "ServerPassword" in <a class="commandlink" href=config.php?section=COMMUNICATION%20WITH%20NZBGET-SERVER>config</a>;<il>';
		echo '<li>Server too busy, connect timeout too short. Check option "ConnectTimeout" in <a class="commandlink" href=config.php?section=COMMUNICATION%20WITH%20NZBGET-SERVER>config</a>;<il>';
		echo '<li>Not compatible server version.</il>';
		echo '</list>';
	} else if ($connectdecode) {
		echo '<font color="red">ERROR: NZBGetWeb could not process response received from NZBGet-Server (although successfully connected).</font><br><br>';
		echo 'Possible reasons include:<br>';
		echo '<list>';
		echo '<li>Wrong port-settings, NZBGetWeb tries to communicate with a different kind of server (a web-server for example, but not nzbget-server). ';
		echo 'Check option "ServerPort" in <a class="commandlink" href=config.php?section=COMMUNICATION%20WITH%20NZBGET-SERVER>config</a>;<il>';
		echo '<li>Not compatible server version.</il>';
		echo '</list>';
	} else {
		echo '<font color="red">'.$errormsg.'</font><br>';
	}
	echo '</div><br>';
}

function start_server() {
	echo '<div class="block">';
	$output = array();
	$retval = StartServer($output);
	if ($retval != 0) {
		echo "<font color='red'>ERROR: Could not start server. Errorcode: $retval.</font><br><br>";
		if (count($output) > 0) {
			echo 'Output:<br>';
			foreach ($output as $line) {
				echo $line.'<br>';
			}
		}
	} else {
		echo '<font color="#00BB00">INFO: Server started successfully.</font><br><br>';
		echo 'Please give the server few seconds for initialization, then refresh the page.<br>';
	}
	echo '</div><br>';
}

function stop_server() {
	echo '<div class="block">';
	$output = array();
	echo '<center>Shutdown NZBGet server</center><br>';
	echo 'Executing stop-script:<br>';
	$retval = StopServer($output);
	if (count($output) > 0) {
		foreach ($output as $line) {
			echo $line.'<br>';
		}
	} else {
		echo '<font color="#00BB00">Stop-command executed successfully.</font><br><br>';
	}
	echo '</div><br>';
}

?>
<HTML>
<HEAD>
<TITLE>NZBGet Web Interface</TITLE>

<style TYPE="text/css">
<!--
<?php include "mini.css" ?>
-->
</style>

<?php
  $refresh_interval = $groupmode ? $GroupModeRefreshInterval : $FileModeRefreshInterval;
  if ($refresh_interval > 0)
  {
		echo "<META HTTP-EQUIV='REFRESH' content='$refresh_interval'; url=.$LoginRedirectPage.'>";
  }
  
  if ($MiniJavaScript) {
?>
<script type="text/javascript"><!--
	function ShutdownServer()
	{
		var answer = confirm("Shutdown NZBGet-Server?");
		if (answer){
			window.location.href='mini.php?action=shutdown';
		}
	}
//--></script>
<?php
  }
?>

</HEAD>
<BODY >

<?php
	if ($wantstop) {
		stop_server();
		serverinfobox($phpvars);
		servercommandbox($phpvars);
	}
	else if ($connected) {
		serverinfobox($phpvars);
		servercommandbox($phpvars);
		if ($groupmode) {
			currently_downloading($phpvars);
			queued_downloading($phpvars, $page);
			currently_processing($phpvars, $postlogpage);
			queued_processing($phpvars);
			logging($phpvars, $logpage);
		} elseif ($historymode) { 
			history($phpvars, $page);
		} else {
			filelist($phpvars, $page);
			if ($FileModeLog) {
				logging($phpvars, $logpage);
			}
		}
		download_rate($phpvars);
		upload_box($phpvars);
	} else {
		if ($wantstart) {
			start_server();
		} else {
			connect_error($phpvars);
		}
		servercommandbox($phpvars);
	}
?>

</BODY>
</HTML>

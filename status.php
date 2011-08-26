<?php
include 'login.php';

header('Cache-Control: no-cache, must-revalidate'); // HTTP/1.1
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past

require_once 'settings-template.php';
if (file_exists('settings.php')) require_once 'settings.php';
require_once 'functions.php';
require_once 'confgen.php';
require_once 'version.php';
$hasusermenu = false;
if (is_readable('usermenu.php')) {
	require_once 'usermenu.php';
	$hasusermenu = true;
}

global $action, $id;

$groupmode = !(isset($_COOKIE['c_filemode']) || isset($_COOKIE['c_historymode']));
$historymode = isset($_COOKIE['c_historymode']);
$wantstop = false;

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
		$wantstop = true;
	}
	else if (isset ($_REQUEST['id']))
	{
		$edittext = '';
		if (isset ($_REQUEST['edittext'])) {
			$edittext = $_REQUEST['edittext'];
		}
		
		GetRequest('editqueue', array($action, (int)$_REQUEST['offset'], $edittext, (int)$_REQUEST['id']));
		if ($action == 'groupresume')
			GetRequest('editqueue', array('grouppauseextrapars', (int)$_REQUEST['offset'], '', (int)$_REQUEST['id']));
	}
	else
		GetRequest($action, '');
}

if (isset ($_REQUEST['rate'])) {
	//set max download option
	GetRequest('rate', (int)$_REQUEST['rate']); 
}

if (isset ($_FILES['nzbfile'])) {
	$upload_status = upload_file($_FILES['nzbfile']);
	SetCookie('upload_status', $upload_status, time()+30); // expire in 30 seconds
	Redirect('index.php');
}

if (isset ($_REQUEST['newzbinid'])) {
	//get nzb from newzbin
	$newzbin_status = FetchFromNewzbin($_REQUEST['newzbinid']); 
	SetCookie('newzbin_status', $newzbin_status, time()+30); // expire in 30 seconds
	Redirect('index.php');
}

$page = 0;
if (isset($_REQUEST['page'])) {
	$page = $_REQUEST['page'];
	SetCookie("c_page", $page, 0);
} else if (isset($_COOKIE['c_page'])) {
	$page = (int)$_COOKIE['c_page'];
}

$sort = "id";
$sortord = 1; // 1 ASC - 0 DESC
if (isset($_COOKIE['c_sortord'])) {
	$sortord = (int)$_COOKIE['c_sortord'];
}
if (isset($_REQUEST['sort'])) {
	$sort = $_REQUEST['sort'];
	if ( $_COOKIE['c_sort'] == $_REQUEST['sort']) 
		$sortord = (int)!$sortord;
	else 
		$sortord = 1;
	SetCookie("c_sort", $sort, 0);
	SetCookie("c_sortord", $sortord, 0);
} else if (isset($_COOKIE['c_sort'])) {
	$sort = $_COOKIE['c_sort'];
}

$logpage = 0;
if (isset($_REQUEST['logpage'])) {
	$logpage = $_REQUEST['logpage'];
	SetCookie('c_logpage', $logpage, 0);
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

$rpc_api = GetAvailableApi();
//echo "<!-- API: $rpc_api -->\n";
if (!isset($rpc_api)) {
	echo 'NZBGetWeb: Could not find required extension or library. Consult README-file for installation instructions.';
	Exit(-1);
}

$editpostparamid = 0;
if (isset($_REQUEST['editpostparam'])) {
	$editpostparamid = $_REQUEST['id'];
}

$wantstart = false;
$connected = false;
$supportpostparam = ($PostProcessConfigTemplate != '') && ($PostProcessConfigFile != '');
$phpvars = null;

if (isset($_REQUEST['start']) && $ServerStartCommand != '') {
	$wantstart = true;
}

if (!$wantstart) {
	$phpvars = GetInfo($groupmode);
	$connected = !IsConnectError($phpvars);
}

function add_category_combo($category, $id, $paused) {
	global $Categories;
	
	if ($category == '' && count($Categories) == 0) {
		return;
	}
	
	$catfound = false;

	echo '<select class="'.($paused ? 'pausedcategorycombo' : 'categorycombo').'" onchange="javascript:updatestatus(\'status.php?action=groupsetcategory&edittext=\' + this.options[this.selectedIndex].text + \'&offset=-1&id='.$id.'\')">';
	foreach ($Categories as $cat) {
		if ($cat==$category) {
			echo "<option selected='selected'>$cat</option>";
			$catfound = true;
		} else {
			echo "<option>$cat</option>";
		}
	}
	
	if (!$catfound) {
		echo "<option selected='selected'>$category</option>";
	}
	
	echo '</select> ';
}

function format_priority_as_number($priority) {
	if ($priority > 0) 
	  return '+'.$priority;
	else 
	  return $priority;
}

function add_priority_combo($priority, $id, $paused) {

	$Priorities=array('very low'=>-100, 'low'=>-50, 'normal'=>0, 'high'=>50, 'very high'=>100);
	
	$catfound = false;

	echo '<select class="'.($paused ? 'pausedcategorycombo' : 'categorycombo').'" onchange="javascript:updatestatus(\'status.php?action=groupsetpriority&edittext=\' + this.options[this.selectedIndex].value + \'&offset=-1&id='.$id.'\')">';
	foreach ($Priorities as $cat=>$prio) {
		if ($prio==$priority) {
			echo "<option selected='selected' value='$prio'>$cat</option>";
			$catfound = true;
		} else {
			echo "<option value='$prio'>$cat</option>";
		}
	}
	
	if (!$catfound) {
		echo "<option selected='selected'>".format_priority_as_number($priority)."</option>";
	}
	
	echo '</select> ';
}

function currently_downloading ($phpvars) {
	global $supportpostparam, $TimeZoneCorrection;
  
	echo '<div class = "block">';
	if (isset($phpvars['activegroup'])) {
		//Download in progress, display info
		$cur_queued=$phpvars['activegroup'];
		if (!($phpvars['status']['DownloadPaused'] || $phpvars['status']['Download2Paused']))
			echo '<center>Currently downloading</center><br>';
		else
			echo '<center>Currently downloading (pausing)</center><br>';

		echo '<table width="100%">';
		echo '<tr><td colspan="'.($supportpostparam ? 8 : 7).'"></td><td width="20" align="right">priority&nbsp;&nbsp;</td><td>name</td><td width="20">category</td><td width="50" align="right">age</td><td width="100" align="right">download rate</td><td width="60" align="right">left</td><td width="100" align="right">remaining time</td></tr>';

		echo '<tr class="unpausedgroup">';
		echo '<td width="10"><a href="javascript:updatestatus(\'status.php?action=groupdelete&offset=0&id='.$cur_queued['LastID'].'\')"><IMG src=images/cancel.gif width=15 height=15 alt="remove nzb" title="remove nzb"></a></td>';
		echo '<td width="10"><a href="javascript:updatestatus(\'status.php?action=groupmovetop&offset=0&id='.$cur_queued['LastID'].'\')"><IMG src=images/first.gif width=15 height=15 alt="move nzb to top in queue" title="move nzb to top in queue"></a></td>';
		echo '<td width="10"><a href="javascript:updatestatus(\'status.php?action=groupmoveoffset&offset=-1&id='.$cur_queued['LastID'].'\')"><IMG src=images/up.gif width=15 height=15 alt="move nzb up" title="move nzb up"></a></td>';
		echo '<td width="10"><a href="javascript:updatestatus(\'status.php?action=groupmoveoffset&offset=1&id='.$cur_queued['LastID'].'\')"><IMG src=images/down.gif width=15 height=15 alt="move nzb down" title="move nzb down"></a></td>';
		echo '<td width="10"><a href="javascript:updatestatus(\'status.php?action=groupmovebottom&offset=0&id='.$cur_queued['LastID'].'\')"><IMG src=images/last.gif width=15 height=15 alt="move nzb to bottom in queue" title="move nzb to bottom in queue"></a></td>';
		echo '<td width="10"><a href="javascript:updatestatus(\'status.php?action=grouppause&offset=0&id='.$cur_queued['LastID'].'\')"><IMG src=images/pause.gif width=15 height=15 alt="pause nzb" title="pause nzb"></a></td>';
		echo '<td width="10"><a href="javascript:updatestatus(\'status.php?action=groupresume&offset=0&id='.$cur_queued['LastID'].'\')"><IMG src=images/resume.gif width=15 height=15 alt="resume nzb" title="resume nzb"></a></td>';
		if ($supportpostparam) {
			echo '<td width="10"><a href="javascript:updatestatus(\'status.php?editpostparam=1&id='.$cur_queued['LastID'].'\')"><IMG src=images/post'.(postprocess_disabled($cur_queued) ? '-nopp' : '').'.gif width=15 height=15 alt="edit parameters" title="edit post-processing parameters"></a></td>';
		}
		echo '<td width="20" align="right">';
		add_priority_combo($cur_queued['MaxPriority'], $cur_queued['LastID'], $grouppaused);
		echo '</td>';
		echo "<td>".namereplace($cur_queued['NZBNicename'])."</td>";
		echo '<td width="20" align="right">';
		add_category_combo($cur_queued['Category'], $cur_queued['LastID'], false);
		echo '</td>';
		echo '<td align="right">'.formatAge($cur_queued['MinPostTime'] + $TimeZoneCorrection*60*60).'</td>';
		echo "<td align='right'>".round0($phpvars['status']['DownloadRate']/1024)." KB/s</td>";
		echo "<td align='right'>".formatSizeMB($cur_queued['RemainingSizeMB']-$cur_queued['PausedSizeMB'])." </td>";
		if ($phpvars['status']['DownloadRate'] > 0)
			echo "<td align='right'>".sec2hms(($cur_queued['RemainingSizeMB']-$cur_queued['PausedSizeMB'])/($phpvars['status']['DownloadRate']/1024/1024))."</td>";
		else
			echo "<td align='right'></td>";
		echo '</tr>';

		echo '<tr><td colspan="8"><td colspan="3" class="progress">';
		$a=$cur_queued['FileSizeMB']-$cur_queued['PausedSizeMB'];
		if ($a > 0)
			$percent_complete=round0(($a-($cur_queued['RemainingSizeMB']-$cur_queued['PausedSizeMB']))*100/$a);
		else
			$percent_complete=100;
		echo "<IMG src=images/pbar.gif height=12 width=".$percent_complete."%>";
		echo '<td>&nbsp;&nbsp;'.$percent_complete.'%</td>';
		echo '</tr>';

		echo '</table>';
	}
	else {
		echo '<table width="100%">';
		echo '<tr><td>';
		if ($phpvars['status']['DownloadPaused'] || $phpvars['status']['Download2Paused']) {
			$pauseregister = $phpvars['status']['DownloadPaused'] && $phpvars['status']['Download2Paused'] ? ' (+2)' :
				($phpvars['status']['Download2Paused'] && !$phpvars['status']['DownloadPaused'] ? ' (2)' : '');
			echo '<center>Server is paused'.$pauseregister.'</center><br>';
			echo '<center>';
			if ($phpvars['status']['DownloadPaused']) {
				echo '<a class="commandlink" href="javascript:updatestatus(\'status.php?action=resumedownload\')">resume</a>';
			}
			if ($phpvars['status']['Download2Paused']) {
				if ($phpvars['status']['DownloadPaused']) {
					echo '&nbsp;&nbsp;&nbsp;&nbsp;';
				}
				echo '<a class="commandlink" href="javascript:updatestatus(\'status.php?action=resumedownload2\')">resume (2)</a>';
			}
			echo '</center>';
		} else {
			echo '<center>Server is sleeping</center><br>';
		}
		echo '</td></tr>';
		echo '</table>';
	}
	echo '</div>';
}

function queued_downloading($phpvars, $page) {
	if (count($phpvars['queuedgroups']) == 0)
		return;

	global $GroupsPerPage, $supportpostparam, $TimeZoneCorrection, $sort, $sortord, $Categories;

	$i = 0;
	$groups = $phpvars['queuedgroups'];
	foreach($groups as &$aSingleArray) {
	   $aSingleArray['_queueid'] = $i;
	   $i++;
	}
	
	$cnt = count($groups);
	$pagecount = pagecount($cnt, $GroupsPerPage);
	if ($page > $pagecount)
		$page = $pagecount;
	if ($page < 1)
		$page = 1;
	
	echo '<div class = "block"><center>Queued</center><br>';
	echo '<table width="100%">';
	echo '<tr class="nowrap"><td colspan="'.($supportpostparam ? 9 : 8).'"><a href="javascript:updatestatus(\'status.php?page=1&sort=id\');" class="commandlink">queue position</a>'.(($sort == 'id' && !$sortord) ? '<img class="arrow" src="images/desc.gif">':'').'</td>';
	echo '<td width="20" align="right"><a href="javascript:updatestatus(\'status.php?page=1&sort=prio\');" class="'.(( $sort == "prio")?'commandlinkactive">priority</a>  <img class="arrow" src="images/'.($sortord ? 'asc.gif">':'desc.gif">'):'commandlink">priority</a>').'&nbsp;&nbsp;</td>';
	echo '<td><a href="javascript:updatestatus(\'status.php?page=1&sort=name\');" class="'.(( $sort == "name")?'commandlinkactive">name</a>  <img class="arrow" src="images/'.($sortord ? 'asc.gif">':'desc.gif">'):'commandlink">name</a>').'</td>';
	echo '<td width="20" align="right"><a href="javascript:updatestatus(\'status.php?page=1&sort=cat\');" class="'.(( $sort == "cat")?'commandlinkactive">category</a> <img class="arrow" src="images/'.(($sortord)? 'asc.gif">':'desc.gif">'):'commandlink">category</a>').'</td>';
	echo '<td width="50" align="right"><a href="javascript:updatestatus(\'status.php?page=1&sort=age\');" class="'.(( $sort == "age")?'commandlinkactive">age</a> <img class="arrow" src="images/'.(($sortord)? 'asc.gif">':'desc.gif">'):'commandlink">age</a>').'</td>';
	echo '<td width="60" align="right"><a href="javascript:updatestatus(\'status.php?page=1&sort=tot\');" class="'.(( $sort == "tot")?'commandlinkactive">total</a> <img class="arrow" src="images/'.(($sortord)? 'asc.gif">':'desc.gif">'):'commandlink">total</a>').'</td>';
	echo '<td width="60" align="right"><a href="javascript:updatestatus(\'status.php?page=1&sort=left\');" class="'.(( $sort == "left")?'commandlinkactive">left</a> <img class="arrow" src="images/'.(($sortord)? 'asc.gif">':'desc.gif">'):'commandlink">left</a>').'</td>';
	echo '<td width="100" align="right">estimated time</td></tr>';
	
	if ( $sort == "id" ) {
		if ( ! $sortord ) $groups = array_reverse($groups);
	} else {
		$tmp = Array();
		foreach($groups as &$aSingleArray) {		   
		   switch( $sort ) {
			case "age":
				$tmp[] = &$aSingleArray["MinPostTime"];				
				break;
			case "prio":
				$tmp[] = &$aSingleArray["MaxPriority"];				
				break;
			case "name":
				$tmp[] = &$aSingleArray["NZBNicename"];				
				break;
			case "tot":
				$tmp[] = &$aSingleArray["FileSizeMB"];				
				break;
			case "left":
				$aSingleArray['_sizeleft'] = $aSingleArray['RemainingSizeMB']-$aSingleArray['PausedSizeMB'];
				$tmp[] = &$aSingleArray['_sizeleft'];
				break;
			case "cat":
				$tmp[] = &$aSingleArray["Category"];				
				break;
		   }
		}
		switch( $sort ) {
		   case "age":
			array_multisort($tmp, ( $sortord ) ? SORT_DESC : SORT_ASC, SORT_NUMERIC, $groups);
			break;
		   case "tot":
		   case "left":
		   case "prio":
			array_multisort($tmp, ( $sortord ) ? SORT_ASC : SORT_DESC, SORT_NUMERIC, $groups);
			break;
		   case "name":
		   case "cat":
			$tmp = array_map('strtolower', $tmp);
			array_multisort($tmp, ( $sortord ) ? SORT_ASC : SORT_DESC, SORT_STRING, $groups);
			break;
		}
	}

	foreach (array_slice($groups, ($page - 1) * $GroupsPerPage, $GroupsPerPage) as $cur_queued) {
		$grouppaused=($cur_queued['PausedSizeLo'] != 0) && ($cur_queued['RemainingSizeLo']==$cur_queued['PausedSizeLo']);
		if ($grouppaused)
			echo '<tr class="pausedgroup">';
		else
			echo '<tr class="unpausedgroup">';
		echo '<td width="10">'.$cur_queued['_queueid'].'</td>';
		echo '<td width="15"><a href="javascript:updatestatus(\'status.php?action=groupdelete&offset=0&id='.$cur_queued['LastID'].'\')"><IMG src=images/cancel.gif width=15 height=15 alt="remove nzb" title="remove nzb"></a></td>';
		echo '<td width="15"><a href="javascript:updatestatus(\'status.php?action=groupmovetop&offset=0&id='.$cur_queued['LastID'].'\')"><IMG src=images/first.gif width=15 height=15 alt="move nzb to top in queue" title="move nzb to top in queue"></a></td>';
		if ( $sort == "id" ) echo '<td width="10"><a href="javascript:updatestatus(\'status.php?action=groupmoveoffset&offset=-1&id='.$cur_queued['LastID'].'\')"><IMG src=images/up.gif width=15 height=15 alt="move nzb up" title="move nzb up"></a></td>';
		else echo '<td width="15"></td>';
		if ( $sort == "id" ) echo '<td width="10"><a href="javascript:updatestatus(\'status.php?action=groupmoveoffset&offset=1&id='.$cur_queued['LastID'].'\')"><IMG src=images/down.gif width=15 height=15 alt="move nzb down" title="move nzb down"></a></td>';
		else echo '<td width="15"></td>';
		echo '<td width="15"><a href="javascript:updatestatus(\'status.php?action=groupmovebottom&offset=0&id='.$cur_queued['LastID'].'\')"><IMG src=images/last.gif width=15 height=15 alt="move nzb to bottom in queue" title="move nzb to bottom in queue"></a></td>';
		echo '<td width="15"><a href="javascript:updatestatus(\'status.php?action=grouppause&offset=0&id='.$cur_queued['LastID'].'\')"><IMG src=images/pause.gif width=15 height=15 alt="pause nzb" title="pause nzb"></a></td>';
		echo '<td width="15"><a href="javascript:updatestatus(\'status.php?action=groupresume&offset=0&id='.$cur_queued['LastID'].'\')"><IMG src=images/resume.gif width=15 height=15 alt="resume nzb" title="resume nzb"></a></td>';
		if ($supportpostparam) {
			echo '<td width="15"><a href="javascript:updatestatus(\'status.php?editpostparam=1&id='.$cur_queued['LastID'].'\')"><IMG src=images/post'.(postprocess_disabled($cur_queued) ? '-nopp' : '').'.gif width=15 height=15 alt="edit parameters" title="edit post-processing parameters"></a></td>';
		}
		echo '<td width="20" align="right">';
		add_priority_combo($cur_queued['MaxPriority'], $cur_queued['LastID'], $grouppaused);
		echo '</td>';
		echo '<td>'.namereplace($cur_queued['NZBNicename']).'</td>';
		echo '<td width="20" align="right">';
		add_category_combo($cur_queued['Category'], $cur_queued['LastID'], $grouppaused);
		echo '</td>';
		echo '<td align="right">'.formatAge($cur_queued['MinPostTime'] + $TimeZoneCorrection*60*60).'</td>';
		echo '<td align="right">'.formatSizeMB($cur_queued['FileSizeMB']).'</td>';
		echo '<td align="right">'.formatSizeMB($cur_queued['RemainingSizeMB']-$cur_queued['PausedSizeMB']).'</td>';

		if ($phpvars['status']['DownloadRate'] > 0)
			echo '<td align="right">'.sec2hms(($cur_queued['RemainingSizeMB']-$cur_queued['PausedSizeMB'])/($phpvars['status']['DownloadRate']/1024/1024)).'</td>';
		else
			echo '<td align="right"></td>';
		
		echo '</tr>';
	}

	echo '</table>';
	
	if ($cnt > $GroupsPerPage) {
		pagelist($cnt, $page, $GroupsPerPage, 'page');
	}
	
	echo '</div>';
}

function postprocess_disabled($cur_queued) {
	foreach ($cur_queued['Parameters'] as $param) {
		if ($param['Name'] == 'PostProcess') {
			if ($param['Value'] == 'no') {
				return true;
			}
			break;
		}
	}
	return false;
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
		$stagewidth=110;
	}
	else if ($cur_proc['Stage'] == 'VERIFYING_SOURCES') {	
		$stage="verifying files";
		$stagewidth=90;
	}
	else if ($cur_proc['Stage'] == 'REPAIRING') {
		$stage="repairing files";
		$stagewidth=90;
	}
	else if ($cur_proc['Stage'] == 'VERIFYING_REPAIRED') {
		$stage="verifying repaired files"; 
		$stagewidth=145;
	}
	else if ($cur_proc['Stage'] == 'EXECUTING_SCRIPT') {
		$stage="executing script"; 
		$stagewidth=100;
		$remtime=false;
	}
	else {
		$stage="";
		$stagewidth=50;
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
	echo '<td></td><td>name</td><td align="left" width="'.$stagewidth.'">stage</td>';
	echo '<td align="right" width="40">%</td><td width="100" align="right">'.($remtime ? "remaining time" : "elapsed time").'</td>';
	echo '</tr>';
	echo '<tr>';
	echo '<td width="10"><a href="javascript:updatestatus(\'status.php?action=postdelete&offset=0&id='.$cur_proc['ID'].'\')"><IMG src=images/cancel.gif width=15 height=15 alt="terminate job" title="terminate job"></a></td>';
	echo '<td valign="top">'.namereplace($cur_proc[has_other_postfiles_from_same_nzb($phpvars, $cur_proc) ? 'InfoName' : 'NZBNicename']).'</td>';
	echo '<td valign="top" align="left" width="'.$stagewidth.'">'.$stage.'</td>';
	echo '<td valign="top" align="right">'.$completed.'</td>';
	echo '<td valign="top" align="right">'.$disptime.'</td>';
	echo '</tr>';
	echo '</table>';

	if (($cur_proc['Stage'] == 'LOADING_PARS') || 
		($cur_proc['Stage'] == 'VERIFYING_SOURCES') || 
		($cur_proc['Stage'] == 'VERIFYING_REPAIRED')) {
		echo '<table width="100%">';
		echo '<tr height="2"><td></td></tr>';
		echo '<tr>';
		echo '<td><small>'.($cur_proc['ProgressLabel']).' ('.(round1($cur_proc['FileProgress'] / 10)).'%)</small></td>';
		echo '<td align="right" width="40"></td><td align="right" width="100"></td>';
		echo '</tr>';
		echo '</table>';
	}
	
// Messages	
	global $NewMessagesFirst, $PostMessagesPerPage;

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
	
		echo '<div class = "postlog"><center>Script-output</center><br>';
		echo '<table class="postlogtable">';
		
		foreach (array_slice($a, $start, $per_page) as $info) {
			echo "<tr><td valign='top' class='".$info['Kind']."'>".$info['Kind']."</td><td valign='top'>".FormatLogText($info['Text'])."</td></tr>";
		}
		echo '</table>';
		
		if ($cnt > $PostMessagesPerPage) {
			pagelist($cnt, $page, $PostMessagesPerPage, 'postlogpage');
		}
		echo '</div>';
	}

	echo '</div>';
}

function queued_processing($phpvars){
	$queue=array_slice($phpvars['postqueue'], 1);
	if (count($queue) == 0) 
		return;

	echo '<div class = "block"><center>Queued</center><br>';
	echo '<table width="100%">';
	
	echo '<tr><td colspan="5"></td><td>name</td></tr>';

	foreach ($queue as $cur_proc) {
		echo '<tr>';
		echo '<td width="10"><a href="javascript:updatestatus(\'status.php?action=postdelete&offset=0&id='.$cur_proc['ID'].'\')"><IMG src=images/cancel.gif width=15 height=15 alt="delete job" title="delete job"></a></td>';
		echo '<td width="10"><a href="javascript:updatestatus(\'status.php?action=postmovetop&offset=0&id='.$cur_proc['ID'].'\')"><IMG src=images/first.gif width=15 height=15 alt="move job to top in queue" title="move job to top in queue"></a></td>';
		echo '<td width="10"><a href="javascript:updatestatus(\'status.php?action=postmoveoffset&offset=-1&id='.$cur_proc['ID'].'\')"><IMG src=images/up.gif width=15 height=15 alt="move job up" title="move job up"></a></td>';
		echo '<td width="10"><a href="javascript:updatestatus(\'status.php?action=postmoveoffset&offset=1&id='.$cur_proc['ID'].'\')"><IMG src=images/down.gif width=15 height=15 alt="move job down" title="move job down"></a></td>';
		echo '<td width="10"><a href="javascript:updatestatus(\'status.php?action=postmovebottom&offset=0&id='.$cur_proc['ID'].'\')"><IMG src=images/last.gif width=15 height=15 alt="move job to bottom in queue" title="move job to bottom in queue"></a></td>';
		echo '<td>'.namereplace($cur_proc[has_other_postfiles_from_same_nzb($phpvars, $cur_proc) ? 'InfoName' : 'NZBNicename']).'</td>';
		echo '</tr>';
	}

	echo '</table></div>';
}

function logging ($phpvars, $page) {
	global $NewMessagesFirst, $MessagesPerPage, $LogTimeFormat, $TimeZoneCorrection;

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
		echo "<tr><td valign='top' class='".$info['Kind']."'>".$info['Kind'].
		"</td><td valign='top'><span class='date'>".date($LogTimeFormat, $info['Time'] + $TimeZoneCorrection*60*60)."</span> ".FormatLogText($info['Text'])."</td></tr>";
	}
	echo '</table>';
	
	if ($cnt > $MessagesPerPage) {
		pagelist($cnt, $page, $MessagesPerPage, 'logpage');
	}
	
	echo '</div>';
}

function historymain($phpvars) {
	global $HistoryPerMainPage;
	
	$history=$phpvars['history'];
	if (count($history) == 0 || $HistoryPerMainPage == 0) 
		return;

	if (count($history) <= $HistoryPerMainPage)
		$Caption = 'History';
	else
		$Caption = 'History (recent '.$HistoryPerMainPage.' items from total '.count($history).')';
	echo '<div class = "block"><center>'.$Caption.'</center><br>';
	echo '<table width="100%">';	
	historydisplay(array_slice($history, 0, $HistoryPerMainPage), false);

	echo '</table>';
	if (count($history) > $HistoryPerMainPage) {
		echo '<small><br>Further items exist. Click <a class="commandlink" href="javascript:updatestatus(\'status.php?historymode=1&page=1\')">here</a> to display the whole history with extra info.</small>';
	} else {
		echo '<small><br>Click <a class="commandlink" href="javascript:updatestatus(\'status.php?historymode=1&page=1\')">here</a> to display the whole history with extra info.</small>';
	}
	
	echo '</div>';
}

function history($phpvars, $page) {
	global $HistoryPerPage, $NewHistoryFirst;

	$cnt = count($phpvars['history']);
	$pagecount = pagecount($cnt, $HistoryPerPage);
	if ($page > $pagecount)
		$page = $pagecount;
	if ($page < 1)
		$page = 1;

	$h=$phpvars['history'];
	if (!$NewHistoryFirst)
		$h=array_reverse($h);	
	echo '<div class = "block"><center>History</center><br>';
	echo '<table width="100%">';
	historydisplay(array_slice($h, ($page - 1) * $HistoryPerPage, $HistoryPerPage), true);
	echo '</table>';
	
	if ($cnt > $HistoryPerPage) {
		pagelist($cnt, $page, $HistoryPerPage, 'page');
	} else {
		echo '<br>';
	}

	echo '<small>';
	echo 'Return to <a class="commandlink" href="javascript:updatestatus(\'status.php?groupmode=1&page=1&logpage=1\')">groups</a>';
	echo ' or <a class="commandlink" href="javascript:updatestatus(\'status.php?filemode=1&page=1&logpage=1\')">files</a>.';
	echo '</small>';
	
	echo '</div>';
}

function historydisplay($history, $showlog) {
	global $HistoryTimeFormat, $TimeZoneCorrection, $LogTimeFormat;

	echo '<tr><td colspan="3"></td><td>time</td><td>name</td><td>category</td><td width="60" align="right">size</td><td width="40" align="right">files</td><td width="10"></td><td width="80">Par Status</td><td width="80">Script Status</td></tr>';
	foreach ($history as $hist) {
		echo '<tr>';
		echo '<td width="10"><a href="javascript:updatestatus(\'status.php?action=historydelete&offset=0&id='.$hist['NZBID'].'\')"><IMG src="images/cancel.gif" width="15" height="15" alt="remove from history" title="remove from history"></a></td>';
		echo '<td width="10">'.($hist['RemainingFileCount'] > 0 ? '<a href="javascript:updatestatus(\'status.php?action=historyreturn&offset=0&id='.$hist['NZBID'].'\')">' : '').'<IMG src="images/return'.($hist['RemainingFileCount'] > 0 ? '' : '-disabled').
			'.gif" width="15" height="15" alt="return to download queue" title="return to download queue'.
			($hist['RemainingFileCount'] > 0 ? '' : ' (not possible, there are no files left for download)').'">'.($hist['RemainingFileCount'] > 0 ? '</a>' : '').'</td>';
		echo '<td width="10"><a href="javascript:updatestatus(\'status.php?action=historyprocess&offset=0&id='.$hist['NZBID'].'\')"><IMG src="images/reprocess.gif" width="15" height="15" alt="post-process again" title="post-process again"></a></td>';
		echo '<td valign="top" class="date">'.date($HistoryTimeFormat, $hist['HistoryTime'] + $TimeZoneCorrection*60*60).'</td>';
		echo '<td valign="top">'.namereplace($hist['NZBNicename']).'</td>';
		echo '<td valign="top"><span class="category">'.$hist['Category'].'</span></td>';
		echo '<td valign="top" align="right">'.formatSizeMB($hist['FileSizeMB']).'</td>';
		echo '<td valign="top" align="right">'.$hist['FileCount'].'</td>';
		echo '<td></td>';
		echo '<td valign="top"><span class="'.$hist['ParStatus'].'">'.$hist['ParStatus'].'</span></td>';
		echo '<td valign="top"><span class="'.$hist['ScriptStatus'].'">'.$hist['ScriptStatus'].'</span></td>';
		echo '</tr>';

		if ($showlog) {
			$log = $hist['Log'];
			if (count($log) > 0) {
				echo '<tr>';
				echo '<td></td>';
				echo '<td colspan="11"><table width="100%">';
				foreach ($log as $info) {
					echo "<tr class='history-log'><td valign='top' class='".$info['Kind']."'>".$info['Kind'].
						"</td><td valign='top'><span class='date'>".date($LogTimeFormat, $info['Time'] + $TimeZoneCorrection*60*60)."</span> ".FormatLogText($info['Text'])."</td></tr>";
				}
				echo '<small></table></td>';
				echo '</tr>';
			}
		}
	}
}

function filelist($phpvars, $page) {
	global $FilesPerPage, $TimeZoneCorrection;

	$cnt = count($phpvars['files']);
	$pagecount = pagecount($cnt, $FilesPerPage);
	if ($page > $pagecount)
		$page = $pagecount;
	if ($page < 1)
		$page = 1;

	echo '<div class = "block"><center>Files for downloading</center><br>';
	echo '<table width="100%">';
	echo '<tr><td colspan="7"></td><td>name</td><td width="50" align="right">age</td><td width="60" align="right">total</td></tr>';
	
	foreach (array_slice($phpvars['files'], ($page - 1) * $FilesPerPage, $FilesPerPage)  as $cur_queued) {
		$paused=$cur_queued['Paused'];
		if ($paused)
			echo '<tr class="pausedgroup">';
		else
			echo '<tr class="unpausedgroup">';

		echo '<td width="10"><a href="javascript:updatestatus(\'status.php?action=filedelete&offset=0&id='.$cur_queued['ID'].'\')"><IMG src=images/cancel.gif width=15 height=15 alt="remove file" title="remove file"></a></td>';
		echo '<td width="10"><a href="javascript:updatestatus(\'status.php?action=filemovetop&offset=0&id='.$cur_queued['ID'].'\')"><IMG src=images/first.gif width=15 height=15 alt="move file to top in queue" title="move file to top in queue"></a></td>';
		echo '<td width="10"><a href="javascript:updatestatus(\'status.php?action=filemoveoffset&offset=-1&id='.$cur_queued['ID'].'\')"><IMG src=images/up.gif width=15 height=15 alt="move file up" title="move file up"></a></td>';
		echo '<td width="10"><a href="javascript:updatestatus(\'status.php?action=filemoveoffset&offset=1&id='.$cur_queued['ID'].'\')"><IMG src=images/down.gif width=15 height=15 alt="move file down" title="move file down"></a></td>';
		echo '<td width="10"><a href="javascript:updatestatus(\'status.php?action=filemovebottom&offset=0&id='.$cur_queued['ID'].'\')"><IMG src=images/last.gif width=15 height=15 alt="move file to bottom in queue" title="move file to bottom in queue"></a></td>';
		echo '<td width="10"><a href="javascript:updatestatus(\'status.php?action=filepause&offset=0&id='.$cur_queued['ID'].'\')"><IMG src=images/pause.gif width=15 height=15 alt="pause file" title="pause file"></a></td>';
		echo '<td width="10"><a href="javascript:updatestatus(\'status.php?action=fileresume&offset=0&id='.$cur_queued['ID'].'\')"><IMG src=images/resume.gif width=15 height=15 alt="resume file" title="resume file"></a></td>';

		echo "<td>".namereplace($cur_queued['NZBNicename'])."/".namereplace($cur_queued['Filename'])."</td>";
		echo '<td align="right">'.formatAge($cur_queued['PostTime'] + $TimeZoneCorrection*60*60).'</td>';
		echo "<td align=right>".(round1($cur_queued['FileSizeLo'] / 1024 / 1024))." MB</td>";
		echo '</tr>';
	}
	echo '</table>';
	
	if ($cnt > $FilesPerPage) {
		pagelist($cnt, $page, $FilesPerPage, 'page');
	}
	
	echo '</div>';
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
			echo '<span class="page"><a href="javascript:updatestatus(\'status.php?'.$varname.'='.$i.'\')">'.$i.'</a></span> &nbsp;';
	}
	echo '</small></p>';
}

function serverinfobox($phpvars) {
	global $webversion;
	
	echo '<div style="display: none" id="serverinfohidden">';
	echo '<center>NZBGet '.$phpvars['version'].'<br/>';
	echo '<center>Web Interface '.$webversion.'</center><br/>';
	echo '<table width="100%">';
	echo '<tr><td>uptime:</td><td align="right"><nobr>'.sec2hms($phpvars['status']['UpTimeSec']).'</nobr></td></tr>';
	echo '<tr><td>download time:</td><td align="right"><nobr>'.sec2hms($phpvars['status']['DownloadTimeSec']).'</nobr></td></tr>';
	echo '<tr><td><nobr>average download rate:</nobr></td><td align="right"><nobr>'.round0($phpvars['status']['AverageDownloadRate']/1024).' KB/s</nobr></td></tr>';
	echo '<tr><td>total downloaded:</td><td align="right"><nobr>'.formatSizeMB($phpvars['status']['DownloadedSizeMB']).'</nobr></td></tr>';
	echo '<tr><td>free disk space:</td><td align="right"><nobr>'.formatSizeMB(freediskspace()).'</nobr></td></tr>';
	echo '</table>';
	echo '</div>';
}	

function servercommandbox($phpvars) {
	global $connected, $WebUsername, $ServerStartCommand, $ServerConfigTemplate, 
		$ServerConfigFile, $groupmode, $historymode;
	
	echo '<div style="display: none" id="servercommandhidden">';
	echo '<center>Control panel<br><br>';
	echo '<span style="line-height: 150%;">';

	echo '<a class="commandlink" href="javascript:updatestatus(\'status.php\')">refresh</a>';

	if ($connected) {
		if ($phpvars['status']['DownloadPaused']) {
			echo ' &nbsp;&nbsp;<a class="commandlink" href="javascript:updatestatus(\'status.php?action=resumedownload\')">resume</a>';
		} else {
			echo ' &nbsp;&nbsp;<a class="commandlink" href="javascript:updatestatus(\'status.php?action=pausedownload\')">pause</a>';
		}

		echo ' &nbsp;&nbsp;<a class="commandlink" href="javascript:updatestatus(\'status.php?action=scan\')">scan</a>';

		if ($ServerStartCommand != '') {
			echo ' &nbsp;&nbsp;<a class="commandlink" href="javascript:ShutdownServer()">shutdown</a>';
		}
		
		if ($groupmode) {
			echo ' &nbsp;&nbsp;<a class="commandlink" href="javascript:updatestatus(\'status.php?filemode=1&page=1&logpage=1\')">files</a>';
			echo ' &nbsp;&nbsp;<a class="commandlink" href="javascript:updatestatus(\'status.php?historymode=1&page=1&logpage=1\')">history</a>';
		} elseif ($historymode) {
			echo ' &nbsp;&nbsp;<a class="commandlink" href="javascript:updatestatus(\'status.php?groupmode=1&page=1&logpage=1\')">groups</a>';
			echo ' &nbsp;&nbsp;<a class="commandlink" href="javascript:updatestatus(\'status.php?filemode=1&page=1&logpage=1\')">files</a>';
		} else {
			echo ' &nbsp;&nbsp;<a class="commandlink" href="javascript:updatestatus(\'status.php?groupmode=1&page=1&logpage=1\')">groups</a>';
			echo ' &nbsp;&nbsp;<a class="commandlink" href="javascript:updatestatus(\'status.php?historymode=1&page=1&logpage=1\')">history</a>';
		}
	} else {
		if ($ServerStartCommand != '') {
			echo ' &nbsp;&nbsp;<a class="commandlink" href="javascript:updatestatus(\'status.php?start=1\')">start</a>';
		}
	}

	echo ' &nbsp;&nbsp;<a class="commandlink" href=config.php>config</a>';

	if (isset($WebUsername) && $WebUsername != '') {
		echo ' &nbsp;&nbsp;<a class="commandlink" href="logout.php">logout</a>';
	}

	echo '</span>';
	echo '</center>';
	echo '</div>';
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
		echo '<li>IP/Port-settings are incorrect. Check <a class="commandlink" href=config.php?section=W-COMMUNICATION%20WITH%20NZBGET-SERVER>config</a>;<il>';
		echo '<li>Firewall is not properly configured (if nzbget-server and web-interface run on different computer).<il>';
		echo '</list>';
		echo "<br><br>Error-message reported by OS: ".substr($errormsg, 22)."<br>";
	} else if ($connectclosed) {
		echo '<font color="red">ERROR: NZBGetWeb could not receive response from NZBGet-Server (although successfully connected).</font><br><br>';
		echo 'Possible reasons include:<br>';
		echo '<list>';
		echo '<li>Password incorrect. Check option "ServerPassword" in <a class="commandlink" href=config.php?section=W-COMMUNICATION%20WITH%20NZBGET-SERVER>config</a>;<il>';
		echo '<li>Server too busy, connect timeout too short. Check option "ConnectTimeout" in <a class="commandlink" href=config.php?section=W-COMMUNICATION%20WITH%20NZBGET-SERVER>config</a>;<il>';
		echo '<li>Not compatible server version.</il>';
		echo '</list>';
	} else if ($connectdecode) {
		echo '<font color="red">ERROR: NZBGetWeb could not process response received from NZBGet-Server (although successfully connected).</font><br><br>';
		echo 'Possible reasons include:<br>';
		echo '<list>';
		echo '<li>Wrong port-settings, NZBGetWeb tries to communicate with a different kind of server (a web-server for example, but not nzbget-server). ';
		echo 'Check option "ServerPort" in <a class="commandlink" href=config.php?section=W-COMMUNICATION%20WITH%20NZBGET-SERVER>config</a>;<il>';
		echo '<li>Not compatible server version.</il>';
		echo '</list>';
	} else {
		echo '<font color="red">'.$errormsg.'</font><br>';
	}
	echo '</div>';
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
	echo '</div>';
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
	echo '</div>';
}

function BuildContentPage() {
  global $groupmode, $phpvars, $page, $logpage, $postlogpage,
    $GroupModeRefreshInterval, $FileModeRefreshInterval, $FileModeLog, 
	$historymode, $hasusermenu, $wantstop;
  
  if ($groupmode) {
    currently_downloading($phpvars);
    queued_downloading($phpvars, $page);
    currently_processing($phpvars, $postlogpage);
    queued_processing($phpvars);
    historymain($phpvars);
    logging ($phpvars, $logpage);
  } elseif ($historymode) { 
  	history($phpvars, $page);
  } else {
    filelist($phpvars, $page);
    if ($FileModeLog) {
      echo '<br>';
      logging ($phpvars, $logpage);
    }
  }
  
	if ($wantstop) {
		stop_server();
	}
  
  serverinfobox($phpvars);
  servercommandbox($phpvars);
  if ($hasusermenu) {
	usermenu($phpvars);
  }

  echo '<div style="display: none" id="updateinterval">'.($groupmode ? $GroupModeRefreshInterval : $FileModeRefreshInterval).'</div>';
  echo '<div style="display: none" id="downloadlimit">'.($phpvars['status']['DownloadLimit'] / 1024).'</div>';
  if (isset($_COOKIE['upload_status'])) {
    echo '<div style="display: none" id="uploadstatushidden">'.($_COOKIE['upload_status']).'</div>';
  }
  if (isset($_COOKIE['newzbin_status'])) {
    echo '<div style="display: none" id="newzbinstatushidden">'.($_COOKIE['newzbin_status']).'</div>';
  }
}

function BuildErrorPage() {
  global $wantstart, $phpvars, $hasusermenu, $webversion;

  if ($wantstart) {
    start_server();
  } else {
    connect_error($phpvars);
  }
  servercommandbox($phpvars);
  if ($hasusermenu) {
	usermenu($phpvars);
  }
  echo '<div style="display: none" id="serverinfohidden"><center>NZBGet Web Interface '.$webversion.'</center><br></div>';
  echo '<div style="display: none" id="downloadlimit">0</div>';
  echo '<div style="display: none" id="updateinterval">0</div>';
}


//*****************************************************************************
// Postprocessing parameters functions
//

function MergePostValues(&$config, &$params) {

	foreach ($config as $key => $section) {
		if ($section->category == CATEGORY_PPPARAMETERS) {
			foreach ($section->options as $option) {
				foreach ($params as $param) {
					if ($option->name == $param['Name']) {
						$option->value = $param['Value'];
					}
				}
			}
		} else {
			// removing unneeded sections from $config
			unset($config[$key]);
		}
	}
}

function post_params($phpvars) {
	global $config, $editpostparamid, $FormMethod;

	$postprocessconfig = LoadPostProcessConfig($config);
	if (!isset($postprocessconfig)) {
    return;
	}

	echo '<form action="status.php" method='.$FormMethod.'">';
	echo '<input type="hidden" name="save" value="1">';
	echo '<input type="hidden" name="editpostparam" value="1">';
	echo '<input type="hidden" name="id" value="'.$editpostparamid.'">';

	$hasparams = false;
	
	$cur_queued = null;
	if (isset($phpvars['activegroup']) && $phpvars['activegroup']['LastID'] == $editpostparamid) {
    $cur_queued = $phpvars['activegroup'];
	} else {
    foreach ($phpvars['queuedgroups'] as $cur) {
      if ($cur['LastID'] == $editpostparamid) {
        $cur_queued = $cur;
        break;
      }
    }
  }

  if ($cur_queued != null) {
    echo '<div class = "block"><center>Postprocessing parameters</center><br>';
    echo '<table width="100%">';
    echo '<tr><td></td><td width="20" align="right">priority&nbsp;&nbsp;</td><td>name</td><td width="20">category</td><td width="60" align="right">total</td><td width="60" align="right">left</td><td width="100" align="right">estimated time</td></tr>';
    $grouppaused=($cur_queued['PausedSizeLo'] != 0) && ($cur_queued['RemainingSizeLo']==$cur_queued['PausedSizeLo']);
    if ($grouppaused)
      echo '<tr class="pausedgroup">';
    else
      echo '<tr class="unpausedgroup">';
    echo '<td width="10"><a href="javascript:updatestatus(\'status.php?action=groupdelete&offset=0&id='.$cur_queued['LastID'].'\')"><IMG src=images/cancel.gif width=15 height=15 alt="remove nzb" title="remove nzb"></a></td>';
    echo '<td width="20">';
    add_priority_combo($cur_queued['MaxPriority'], $cur_queued['LastID'], false);
    echo '</td>';
    echo '<td>'.namereplace($cur_queued['NZBNicename']).'</td>';
    echo '<td width="20">';
    add_category_combo($cur_queued['Category'], $cur_queued['LastID'], false);
    echo '</td>';
    echo '<td align="right">'.formatSizeMB($cur_queued['FileSizeMB']).'</td>';
    echo '<td align="right">'.formatSizeMB($cur_queued['RemainingSizeMB']-$cur_queued['PausedSizeMB']).'</td>';
    if ($phpvars['status']['DownloadRate'] > 0)
      echo '<td align="right">'.sec2hms(($cur_queued['RemainingSizeMB']-$cur_queued['PausedSizeMB'])/($phpvars['status']['DownloadRate']/1024/1024)).'</td>';
    else
      echo '<td align="right"></td>';
    echo '</tr>';

    echo '<tr><td>&nbsp;</td></tr>';

    echo '<tr><td colspan="6">';
    MergePostValues($config, $cur_queued['Parameters']);
    $hasparams = count($config) > 0;
    BuildOptionsContent($config, null, false);
    echo '</td></tr>';

    echo '</table>';
    echo '</div>';
  }

	if (!$hasparams) {
		echo '<div class="block"><table width="100%"><tr><td>';
		echo '<span class="INFO">INFO</span> Current postprocessing-script does not have any postprocessing parameters.';
		echo '</td></tr></table></div>';
	}
	
	echo '<div class="block"><table width="100%"><tr><td>';
	if ($hasparams) {
		echo '<input type="submit" value="Save changes">&nbsp;&nbsp;';
	}

	echo '<input type="button" value="Cancel" onClick="location=\'?\'">';

	// TIP: uncomment for debug purposes
	//echo '&nbsp;&nbsp;<input type="button" value="Reload (for testing)" onClick="javascript:updatestatus(\'status.php?editpostparam=1&id='.$editpostparamid.'\')">';
	
	echo '</td></tr></table></div>';
	echo '</form><br>';
}

function BuildPostParamPage() {
  global $hasusermenu;
  
  $phpvars = GetInfo(true);

  post_params($phpvars);
  serverinfobox($phpvars);
  servercommandbox($phpvars);
  if ($hasusermenu) {
	usermenu($phpvars);
  }

  echo '<div style="display: none" id="updateinterval">0</div>';
  echo '<div style="display: none" id="downloadlimit">'.($phpvars['status']['DownloadLimit'] / 1024).'</div>';
  if (isset($_COOKIE['upload_status'])) {
    echo '<div style="display: none" id="uploadstatushidden">'.($_COOKIE['upload_status']).'</div>';
  }
  if (isset($_COOKIE['newzbin_status'])) {
    echo '<div style="display: none" id="newzbinstatushidden">'.($_COOKIE['newzbin_status']).'</div>';
  }
}

function SavePostParam() {
	global $_REQUEST, $editpostparamid;

	$postprocessconfig = LoadPostProcessConfig($config);
	if (!isset($postprocessconfig)) {
		$upload_status = '<font color="red">Could not load configuration of post-processing script</font>';
		SetCookie("upload_status", $upload_status, time()+30); // expire in 30 seconds
		Redirect('index.php');
    return;
	}
	
	MergeSettings($config, $_REQUEST);

	foreach ($config as $section) {
		if ($section->category == CATEGORY_PPPARAMETERS) {
			foreach ($section->options as $option) {
				$res = GetRequest('editqueue', array('GroupSetParameter', (int)0, $option->name.'='.$option->value, (int)$editpostparamid));
				if (!$res) {
					$upload_status = '<font color="red">Could not change post-processing parameters</font>';
					SetCookie("upload_status", $upload_status, time()+30); // expire in 30 seconds
					Redirect('index.php');
					return;
				}
			}
		}
	}

	Redirect('index.php');
}

//
// Postprocessing parameters functions - END
//*****************************************************************************

?>
<?php
	if ($editpostparamid > 0) {
		if (isset($_REQUEST['save'])) {
			SavePostParam();
		} else {
			BuildPostParamPage();
		}
	} else if ($connected) {
		BuildContentPage();
	} else {
		BuildErrorPage();
	}
?>

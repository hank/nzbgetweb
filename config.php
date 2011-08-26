<?php
include 'login.php';

header('Cache-Control: no-cache, must-revalidate'); // HTTP/1.1
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past

require_once 'settings-template.php';
if (file_exists('settings.php')) require_once 'settings.php';
require_once 'functions.php';
require_once 'confgen.php';

$reqsection = null;
if (isset($_REQUEST['section'])) {
	$reqsection = $_REQUEST['section'];
}

$config = null;
$skipsections = array('DISPLAY (TERMINAL)');
$ListOptions=array('$Categories', '$LogFilter');
$WebConfigTemplate = 'settings-template.php';
$WebConfigFile = 'settings.php';

function MakeMenu() {
	global $config, $reqsection;

	$server = false;
	$web = false;
	$postprocess = false;
	
	foreach ($config as $section) {
		if ($section->category == CATEGORY_SERVER) {
			$server = true;
		} else if ($section->category == CATEGORY_WEB) {
			$web = true;
		} else if ($section->category == CATEGORY_POSTPROCESS) {
			$postprocess = true;
		}
	}
	
	if ($web) {
		echo '<div class = "block"><center>WEB-INTERFACE</center><br>';
		echo '<table width="100%">';
		foreach ($config as $section) {
			if ($section->category == CATEGORY_WEB) {
				echo '<tr><td class="configmenuitem"><a href="?section='.$section->key.'">'
					.($section->key == $reqsection ? '<span class="menuselectedsection">' : '')
					.$section->name
					.($section->key == $reqsection ? '</span>' : '')
					.'</a></td></tr>';
			}
		}
		echo '</table>';
		echo '</div>';
	}
	
	if ($server) {
		echo '<div class = "block"><center>NZBGET-SERVER</center><br>';
		echo '<table width="'.(msie() ? '90%' : '100%').'%">';
		foreach ($config as $section) {
			if ($section->category == CATEGORY_SERVER) {
				echo '<tr><td class="configmenuitem"><a href="?section='.$section->key.'">'
					.($section->key == $reqsection ? '<span class="menuselectedsection">' : '')
					.$section->name
					.($section->key == $reqsection ? '</span>' : '')
					.'</a></td></tr>';
			}
		}
		echo '</table>';
		echo '</div>';
	}
	
	if ($postprocess) {
		echo '<div class = "block"><center>POSTPROCESSING-SCRIPT</center><br>';
		echo '<table width="'.(msie() ? '90%' : '100%').'%">';
		foreach ($config as $section) {
			if ($section->category == CATEGORY_POSTPROCESS) {
				echo '<tr><td class="configmenuitem"><a href="?section='.$section->key.'">'
					.($section->key == $reqsection ? '<span class="menuselectedsection">' : '')
					.$section->name
					.($section->key == $reqsection ? '</span>' : '')
					.'</a></td></tr>';
			}
		}
		echo '</table>';
		echo '</div>';
	}
}

?>
<HTML>
<HEAD>
<TITLE>NZBGet Web Interface - Settings</TITLE>

<style TYPE="text/css">
<!--
<?php include "style.css" ?>
-->
</style>

</HEAD>
<BODY >

<div class = "top">
	NZBGet Web Interface - Settings
</div>

<?php
	$OK = LoadWebConfig($config);
	if ($ServerConfigFile != '') {
		LoadServerConfig($config, $skipsections);
	}
	if ($PostProcessConfigFile != '') {
		LoadPostProcessConfig($config);
	}

	if ($OK) {
		if (!$reqsection)
		{
			$reqsection = reset($config)->key;
		}

		if (isset($_REQUEST['save'])) {
			MergeSettings($config, $_REQUEST);
			$OK = SaveConfig($config);
			if ($OK) {
				Redirect('config.php?section='.$_REQUEST['section']);
			}
		} else if (isset($_REQUEST['delete'])) {
			DeleteMultiSet($config, $_REQUEST['section'], $_REQUEST['delete'], $_REQUEST['id']);
			$OK = SaveConfig($config);
			if ($OK) {
				Redirect('config.php?section='.$_REQUEST['section']);
			}
		} else if (isset($_REQUEST['add'])) {
			AddMultiSet($config, $_REQUEST['section'], $_REQUEST['add']);
			$OK = SaveConfig($config);
			if ($OK) {
				Redirect('config.php?section='.$_REQUEST['section'].'#'.$_REQUEST['add'].$_REQUEST['id']);
			}
		}
	}
	
	if ($OK) {
?>

<table width="100%">
<tr>
<td valign="top" width="270">
<?php
	MakeMenu();
?>

<div class="block">
<center>
<a class="commandlink" href="index.php">back to main page</a>
</center>
</div>

</td>

<td valign="top">
<?php
	echo '<form action="config.php" method='.$FormMethod.'">';
	echo '<input type="hidden" name="save" value="1">';
	echo '<input type="hidden" name="section" value="'.$reqsection.'">';

	BuildOptionsContent($config, $reqsection, true);

	echo '<div class="block"><table width="100%"><tr><td><input type="submit" value="Save changes"></td></tr></table></div>';

	if (array_key_exists($reqsection, $config)) {
		$section = $config[$reqsection];
		if ($section->category == CATEGORY_SERVER) {
			echo '<br><div class="block"><table width="100%"><tr><td><font color="red"><b>NOTE:</b></font> NZBGet-Server must be restarted for any changes to have effect.</td></tr></table></div>';
		}
	}	

	echo '</form><br>';
}
?>
</td>

</tr>
</table>

</BODY>
</HTML>

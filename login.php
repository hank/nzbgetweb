<?php
require_once 'settings-template.php';
if (file_exists('settings.php')) require_once 'settings.php';

session_start();

global $WebUsername, $FormMethod;

if (!isset($LoginRedirectPage))
{
	$LoginRedirectPage = 'index.php';
}

if (isset($WebUsername) && $WebUsername != '')
{

$auth_valid = false;

if (isset($_REQUEST['username']) || isset($_REQUEST['password'])) {
	$auth_username = $_REQUEST['username'];
	$auth_password = $_REQUEST['password'];
} else {
	if (isset($_SESSION['auth_username']))
		$auth_username = $_SESSION['auth_username'];
	if (isset($_SESSION['auth_password']))
		$auth_password = $_SESSION['auth_password'];
}

if (!isset($auth_username))
	$auth_username='';
if (!isset($auth_password))
	$auth_password='';

$auth_valid = $auth_username==$WebUsername && $auth_password==$WebPassword ;

if ($auth_valid) {
	$_SESSION['auth_username'] = $auth_username;
	$_SESSION['auth_password'] = $auth_password;
} else {
	session_unset();
	session_destroy();
}

if ($auth_valid && (isset($_REQUEST['username']) || isset($_REQUEST['password']))) {
	// redirect and exit: headers not always work, so we use additionally META-tag
	header("Location: $LoginRedirectPage");
	echo "<HTML><HEAD><META HTTP-EQUIV='REFRESH' content='0; url=$LoginRedirectPage'></HEAD></HTML>";
	exit(-1);
}

if (!isset($Stylesheet))
{
  $Stylesheet = "style.css";
}

if(!$auth_valid) {
?>
<HTML>
<HEAD>
<TITLE>NZBGet Web Interface</TITLE>

<style TYPE="text/css">
<!--
<?php include "$Stylesheet" ?>
-->
</style>

</HEAD>
<BODY>
<div class = "top">
	NZBGet Web Interface
</div>
<br>
<div class="block" align="center">
<?php
	echo "<form action='$LoginRedirectPage' method='$FormMethod'>";
?>
<p>Please login</p>
<table>
 <tr>
  <th>
Username:
  </th>
  <th>
<input type="text" name="username">
  </th>
 </tr>
 <tr>
  <th>
Password:
  </th>
  <th>
<input type="password" name="password">
  </th>
 </tr>
 <tr>
  <th colspan="2" align="right">
<input type="submit" value="Login">
</form>
  </th>
 </tr>
</table>
</div>
</BODY>
</HTML>
<script type="text/javascript"><!--
  document.forms[0].username.focus()
//--></script>
<?php
	exit(-1);
}
}
?>
<?php
include 'login.php';
require_once 'version.php';
?>
<HTML>
<HEAD>
<TITLE>NZBGet Web Interface</TITLE>

<style TYPE="text/css">
<!--
<?php include 'style.css' ?>
-->
</style>

<script type="text/javascript"><!--

	var secondsToUpdate = -1
	var timerID = 0
	var xmlhttp
	var firstLoad = true
	var inputdownloadlimit_focused=false
	var inputdownloadlimit_needupdate=false

	function updatestatus(url) {
		clearTimeout(timerID)
		document.getElementById("updateseconds").innerHTML="<center>Loading...</center>"
	
		if (window.XMLHttpRequest) // code for all new browsers
			xmlhttp = new XMLHttpRequest();
		else if (window.ActiveXObject){ // code for IE5 and IE6
			xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
		}
		
		if (xmlhttp==null)
		{
			alert("Your browser does not support XMLHTTP.");
			return;
		}		
			
		xmlhttp.onreadystatechange=state_Change;
		xmlhttp.open('GET', url, true);
		xmlhttp.send(null)
	}

	function state_Change()
	{
		if (xmlhttp.readyState == 4 && xmlhttp.status == 200) 
		{
			if(xmlhttp.responseText.length > 0) {
				document.getElementById('status').innerHTML=xmlhttp.responseText;
				statusLoaded();
			} else {
				// Try again.
				updatestatus('status.php');
			}
		}
	}

	function removeUploadStatus()
	{
		if (document.getElementById("uploadstatus").innerHTML != "")
		{
			document.getElementById("uploadstatus").innerHTML=""
		}
	}

	function removeNewzbinStatus()
	{
		if (document.getElementById('newzbinstatus') &&
			document.getElementById("newzbinstatus").innerHTML != "")
		{
			document.getElementById("newzbinstatus").innerHTML=""
		}
	}

	function countSeconds()
	{
		secondsToUpdate -= 1;
		if (secondsToUpdate <= 0)
		{
			refreshIt()		
		}
		else
		{
			document.getElementById("updateseconds").innerHTML="<center>Next update in " + secondsToUpdate + " seconds</center>"
			timerID = setTimeout('countSeconds()', 1000)
		}
	}

	function refreshIt()
	{
		if (firstLoad)
		{
			firstLoad = false
			updatestatus('status.php?groupmode=1')
		}
		else
			updatestatus('status.php')
	}

	function statusLoaded()
	{
		if (document.getElementById('serverinfohidden')) {
			document.getElementById('serverinfo').innerHTML=document.getElementById('serverinfohidden').innerHTML
		} else {
			document.getElementById('serverinfo').innerHTML='<center>NZBGet Web Interface <?php echo $webversion; ?></center><br>'
		}
		if (document.getElementById('servercommandhidden')) {
			document.getElementById('servercommand').innerHTML=document.getElementById('servercommandhidden').innerHTML
		} else {
			document.getElementById('servercommand').innerHTML='<center>Control panel</center><br>'
		}
		if (document.getElementById('usermenuhidden')) {
			document.getElementById('usermenu').innerHTML=document.getElementById('usermenuhidden').innerHTML
		}
		if (inputdownloadlimit_needupdate || !inputdownloadlimit_focused) 
		{
			if(document.getElementById('downloadlimit') != null) {
				document.getElementById('inputdownloadlimit').value=document.getElementById('downloadlimit').innerHTML;
				inputdownloadlimit_needupdate = false;
			}
		}
		
		if (document.getElementById('uploadstatushidden'))
			document.getElementById('uploadstatus').innerHTML="<br>"+document.getElementById('uploadstatushidden').innerHTML

		if (document.getElementById('newzbinstatushidden'))
			document.getElementById('newzbinstatus').innerHTML="<br>"+document.getElementById('newzbinstatushidden').innerHTML;

		if(document.getElementById('updateinterval') != null) {
			secondsToUpdate=document.getElementById('updateinterval').innerHTML;
		}
		if (secondsToUpdate > 0)
		{
			document.getElementById('updateseconds').innerHTML='<center>Next update in ' + secondsToUpdate + ' seconds</center>'
			timerID = setTimeout('countSeconds()', 1000)
		} else {
			document.getElementById('updateseconds').innerHTML='<center>click <a href="javascript:updatestatus(\'status.php\')">refresh</a> to update</center>'
		}

		setTimeout('removeUploadStatus()', 31000)
		setTimeout('removeNewzbinStatus()', 31000)
	}

	function pageLoaded()
	{
		setTimeout('refreshIt()', 0)
	}
	
	function IsEnterKey(e)
	{
		var keynum;
		if(window.event) // IE
		  keynum = e.keyCode;
		else if(e.which) // Netscape/Firefox/Opera
		  keynum = e.which;
		return keynum == 13;
	}

	function SetRate()	
	{
		inputdownloadlimit_needupdate = true
		updatestatus('status.php?rate='+document.getElementById('inputdownloadlimit').value);
	}
	
	function ShutdownServer()
	{
		var answer = confirm("Shutdown NZBGet-Server?");
		if (answer){
			updatestatus('status.php?action=shutdown');
		}
	}

//--></script>
</HEAD>

<BODY onLoad="pageLoaded()">
<table width="100%">
<tr>
<td valign="top" width="270">

<div class = "block" id="serverinfo">
<center>NZBGet Web Interface <?php echo $webversion; ?><center><br>
</div>

<div class = "block" id="servercommand"><center>Control panel</center><br></div>

<div class = "block">
<center><a class="commandlink" href="mini.php">mobile version</a></center>
</div>

<div class = "block"><center>Add NZB file to download queue</center><br>
<form enctype="multipart/form-data" action="status.php" method="post">
Choose a file: <input class="inputfile" name="nzbfile" type="file"/><br><br>
<input class="submit" type="submit" value="Submit File" />
</form>
<div class="block">
<center>file transfer status</center>
<div id="uploadstatus"></div>
</div>
</div>


<?php
	if (isset($NewzbinUsername) && $NewzbinUsername != '')
	{
?>

<div class="block"><center>Fetch Newzbin Report</center><br>
<form enctype="multipart/form-data" action="status.php" method="post">
Report ID: <input class="inputnewzbin" name="newzbinid" type="text"/>
<input class="submit" type="submit" value="Fetch" />
</form>
<div class="block">
<center>fetch status</center>
<div id="newzbinstatus"></div>
</div>
</div>

<?php
	}
?>

<div class="block"><center>Max download rate</center><br>
New rate: <input type="text" class="inputrate" name="rate" id="inputdownloadlimit" 
value="" onFocus="inputdownloadlimit_focused=true" onBlur="inputdownloadlimit_focused=false"
onkeydown="if (IsEnterKey(event)) SetRate()">
<input type="button" value="Set" onClick="SetRate()">
</div>

<?php
if (is_readable('usermenu.php')) {
?>
<div class = "block" id="usermenu"><center>User menu</center><br></div>
<?php
}
?>

<div class = "block" id="updateseconds">
<center>Loading...</center>
</div>

</td>

<td valign="top">
<div id="status"></div>
</td>

</tr>

</table>

</BODY>
</HTML>

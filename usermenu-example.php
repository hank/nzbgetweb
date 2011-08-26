<?php
/*
Rename this file ("usermenu-example.php") to "usermenu.php" to activate
the inclusion of user menu into the main page.
*/

function usermenu($phpvars) {
?>
<!-- put your content here -->

<div style="display: none" id="usermenuhidden">
<center>User menu<br><br>
<span style="line-height: 150%;">

<a class="commandlink" href="http://nzbget.sourceforge.net">NZBGet Home Page</a><br>

</span>
<br>
<small>To configure the user menu edit  file "usermenu.php" in a text editor.</small>
<small>Delete the file to remove the user menu completely.</small>
</center>
</div>

<!-- end of content -->
<?php
}
?>

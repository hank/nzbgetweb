<?php
session_start();
session_unset();
session_destroy();
?>
<HTML>
<HEAD>
<TITLE>NZBGet Web Interface</TITLE>
<link rel="stylesheet" type="text/css" href="style.css">
</HEAD>
<BODY>
<div class = "top">
	NZBGet Web Interface
</div>
<br>
<div class="block" align="center">
<form action="index.php" method="GET">
<p>You have been successfully logged out.</p>

<p align="center"><a class="commandlink" href="index.php">Log back in</a></p>

</BODY>
</HTML>

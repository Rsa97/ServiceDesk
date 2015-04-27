<?php
session_start();
session_destroy();
echo "<script language='JavaScript'>";
    echo "window.location.href = 'index.php'";
echo "</script>";
?>
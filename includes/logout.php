<?php
session_start();
session_unset();
session_destroy();
if (isset($_SESSION['admin_logged_in'])) {
    header("Location: ../admin_login.php");
} else {
    header("Location: ../login.php");
}

exit();
?>
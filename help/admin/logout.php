<?php
session_start();
// 清除所有session
session_destroy();
header('Location: login.php');
exit; 
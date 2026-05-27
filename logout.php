<?php
require 'config.php';
session_start();
session_destroy();
header('Location: ' . APP_URL . '/login.php');
exit;

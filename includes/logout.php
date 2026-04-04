<?php
require_once __DIR__ . '/auth.php';

logout();
header('Location: ../app.php?view=login');
exit;

<?php

require_once dirname(__DIR__, 2) . '/shared/session.php';

scaims_session_destroy();

header('Location: adminLogin.html');
exit;

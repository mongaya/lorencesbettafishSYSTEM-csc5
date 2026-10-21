<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/cancellation_schema.php';
$viewer=require_user_session($conn);
ensure_cancellation_requests($conn);
respond(false,'Cancellation requests are not yet enabled.',[],503);

<?php
require_once __DIR__ . '/config/db.php';
render_error_page(404, 'Page not found', 'The page you are looking for does not exist or has moved.');

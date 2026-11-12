<?php
require 'koneksi.php';
if (AUTH_AKTIF && hash_equals(csrf_token(), (string)($_GET['t'] ?? ''))) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) { $p = session_get_cookie_params(); setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']); }
    session_destroy();
    ke('login.php');
}
ke('index.php');

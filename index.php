<?php
/**
 * Página de inicio del sistema CRM
 */
require_once __DIR__ . '/config/config.php';

// Si el usuario ya está logueado, redirigir al dashboard
if (isLoggedIn()) {
    redirect('/dashboard.php');
}

// Redirigir a login
redirect('/login.php');

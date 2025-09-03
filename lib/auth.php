<?php

/**
 * Sistema di autenticazione semplice per IPAM
 * Gestisce login, logout e controllo accesso
 */

// Configurazione credenziali predefinite
// IMPORTANTE: Cambia questi valori per la produzione!
define('DEFAULT_USERNAME', 'admin');
define('DEFAULT_PASSWORD', 'admin');

/**
 * Verifica se l'utente è autenticato
 * @return bool True se autenticato, false altrimenti
 */
function isAuthenticated() {
    return isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
}

/**
 * Esegue il login dell'utente
 * @param string $username Nome utente
 * @param string $password Password
 * @return bool True se login riuscito, false altrimenti
 */
function login($username, $password) {
    // Verifica credenziali (in produzione usa hash sicuri)
    if ($username === DEFAULT_USERNAME && $password === DEFAULT_PASSWORD) {
        $_SESSION['authenticated'] = true;
        $_SESSION['username'] = $username;
        $_SESSION['login_time'] = time();
        
        // Rigenera ID sessione per sicurezza
        session_regenerate_id(true);
        
        return true;
    }
    
    return false;
}

/**
 * Esegue il logout dell'utente
 */
function logout() {
    $_SESSION = [];
    
    // Distrugge il cookie di sessione se esiste
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
}

/**
 * Controlla se la sessione è valida e non scaduta
 * @return bool True se sessione valida
 */
function isValidSession() {
    if (!isAuthenticated()) {
        return false;
    }
    
    // Controlla timeout sessione (4 ore)
    $sessionTimeout = 4 * 60 * 60; // 4 ore in secondi
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > $sessionTimeout) {
        logout();
        return false;
    }
    
    return true;
}

/**
 * Reindirizza alla pagina di login se non autenticato
 */
function requireAuth() {
    if (!isValidSession()) {
        // Rileva il base path per hosting web
        $scriptName = $_SERVER['SCRIPT_NAME'];
        $basePath = rtrim(dirname($scriptName), '/');
        if ($basePath === '.') $basePath = '';
        header('Location: ' . $basePath . '/index.php?action=login');
        exit;
    }
}

/**
 * Genera un token CSRF per i form
 * @return string Token CSRF
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verifica un token CSRF
 * @param string $token Token da verificare
 * @return bool True se valido
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Rate limiting semplice per tentativi di login
 * @param string $ip Indirizzo IP
 * @return bool True se può tentare login, false se bloccato
 */
function canAttemptLogin($ip) {
    $maxAttempts = 3;
    $timeWindow = 60 * 60; // 15 minuti
    
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = [];
    }
    
    $now = time();
    $attempts = $_SESSION['login_attempts'];
    
    // Pulisce tentativi vecchi
    $attempts = array_filter($attempts, function($attempt) use ($now, $timeWindow) {
        return ($now - $attempt['time']) < $timeWindow;
    });
    
    // Conta tentativi per questo IP
    $ipAttempts = array_filter($attempts, function($attempt) use ($ip) {
        return $attempt['ip'] === $ip;
    });
    
    $_SESSION['login_attempts'] = $attempts;
    
    return count($ipAttempts) < $maxAttempts;
}

/**
 * Registra un tentativo di login fallito
 * @param string $ip Indirizzo IP
 */
function recordFailedLogin($ip) {
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = [];
    }
    
    $_SESSION['login_attempts'][] = [
        'ip' => $ip,
        'time' => time()
    ];
}

/**
 * Ottiene il nome utente corrente
 * @return string Nome utente o stringa vuota
 */
function getCurrentUser() {
    return $_SESSION['username'] ?? '';
}

/**
 * Ottiene il tempo di login
 * @return int Timestamp di login o 0
 */
function getLoginTime() {
    return $_SESSION['login_time'] ?? 0;
}

?>
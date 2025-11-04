<?php
// Logout page: clears session and shows a message, then redirects to home.
// session is usually already started in includes/functions.php, but ensure it's active.
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// Unset all session variables
$_SESSION = [];

// Delete session cookie if present
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'], $params['secure'], $params['httponly']
    );
}

// Destroy the session
session_destroy();

?>

<div class="card">
  <h2>Logged out</h2>
  <div class="flash">You have been successfully logged out.</div>
  <p><a class="btn" href="/?page=home">Return to home</a></p>
</div>

<script>
  // Redirect to home after a short delay
  setTimeout(function(){ window.location.href = '/?page=home'; }, 900);
</script>

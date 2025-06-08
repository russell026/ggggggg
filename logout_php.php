<?php
require_once 'config.php';

// Clear all session data
session_unset();
session_destroy();

// Start a new session for the success message
session_start();
showAlert('Anda telah berhasil logout.', 'success');

// Redirect to home page
redirect('index.php');
?>
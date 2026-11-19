<?php
/**
 * logout.php — Signs the current user out of the system.
 *
 * PURPOSE:
 *   Completely destroys the active session, which removes all stored
 *   values including the user's ID, name, and role. After this, the
 *   user must log in again to access any protected page.
 *
 * HOW IT WORKS:
 *   1. config.php starts the session (required to access $_SESSION).
 *   2. session_destroy() deletes the session data from the server.
 *   3. The browser is redirected to login.php.
 *
 * The user reaches this by clicking the ↪ sign-out link in the sidebar.
 */

require 'config.php'; // Needed to start the session before we can destroy it

session_destroy(); // Wipes all session data — the user is now logged out

header('Location: login.php'); // Send back to the login page
exit;

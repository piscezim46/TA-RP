<?php
// Database connection wrapper — reads credentials from config.php when available
error_reporting(E_ALL);
ini_set('display_errors', '0'); // don't leak creds to users; we will output a friendly message instead

// Try to load central config if present
if (file_exists(__DIR__ . '/../config.php')) {
  require_once __DIR__ . '/../config.php';
}

$host = defined('DB_HOST') ? DB_HOST : 'localhost';
$user = defined('DB_USER') ? DB_USER : 'root';
$pass = defined('DB_PASS') ? DB_PASS : 'Hayder.2085.root';
$db   = defined('DB_NAME') ? DB_NAME : 'talentarp_db';

// Turn on mysqli exceptions so we can handle and log failures cleanly
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
  $conn = new mysqli($host, $user, $pass, $db);
  $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $ex) {
  // Log the technical details to the server error log for debugging
  error_log('DB connection failed: ' . $ex->getMessage());
  // Show a friendly message and helpful next steps
  http_response_code(500);
  echo "<h2>Database connection error</h2>";
  echo "<p>The application could not connect to the database. Please check your database credentials and ensure the database server is running.</p>";
  echo "<ul>";
  echo "<li>Verify credentials in <code>config.php</code> are correct (DB_HOST, DB_USER, DB_PASS, DB_NAME).</li>";
  echo "<li>Ensure MySQL/MariaDB is running and accessible from this host (check service status or network).</li>";
  echo "<li>Try connecting manually with: <code>mysql -u &lt;user&gt; -p -h &lt;host&gt; -P &lt;port&gt;</code></li>";
  echo "</ul>";
  // Stop further execution to avoid cascading errors
  exit;
}
?>

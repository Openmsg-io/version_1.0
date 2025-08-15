<?

echo "Put this file somewhere secure then disable the exit() in the code"; // below
exit ();
// ##### See: openmsg.io/docs/v1 
// Always check any code before running on your server

// Connect to your database

include ($_SERVER['DOCUMENT_ROOT'].'/includes/main.php');

require ($_SERVER['DOCUMENT_ROOT'].'/openmsg/openmsg_settings.php');

// Table to save User account data. You will need to add to this table 
$query = "CREATE TABLE IF NOT EXISTS openmsg_users (
id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
self_openmsg_address VARCHAR(255) NOT NULL,
self_openmsg_address_name VARCHAR(40) NOT NULL,
password CHAR(255) NOT NULL,
password_salt CHAR(30) NOT NULL, 
timestamp_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$stmt = $db->prepare($query);
$stmt->execute(); // To Do: Un-comment
$stmt->close();


// Create two test accounts... delete rows from table after testing.
$query = "INSERT INTO openmsg_users (
self_openmsg_address, 
self_openmsg_address_name,
password,
password_salt) VALUES (?, ?, ?, ?)";
$stmt = $db->prepare($query);
for($i = 0; $i < 2; $i++){
	$testAcc_address = "100000".$i."*".$my_openmsg_domain;
	$testAcc_address_name = "Test Openmsg Account";
	$testAcc_pw = rand(99999,9999999); // Note this is for the test account only. Not secure.
	$testAcc_salt = rand(99999,9999999); // Note this is for the test account only. Not secure.
	$testAcc_pw_hash = hash('sha256', $testAcc_pw.$testAcc_1_salt); // Note this is for the test account only. Not secure.
	$stmt -> bind_param("ssss", $testAcc_address, $testAcc_address_name, $testAcc_pw_hash, $testAcc_salt);
	$stmt->execute(); 
}
$stmt->close();





// Table to track handshakes that are in progress
$query = "CREATE TABLE IF NOT EXISTS openmsg_handshakes (
id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
other_openmsg_address VARCHAR(255) NOT NULL,
pass_code  VARCHAR(6) NOT NULL,
timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
)";
$stmt = $db->prepare($query);
$stmt->execute(); 
$stmt->close();


// Table to save User data after a sucessful handshake
$query = "CREATE TABLE IF NOT EXISTS openmsg_user_connections (
id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
self_openmsg_address VARCHAR(255) NOT NULL,
other_openmsg_address VARCHAR(255) NOT NULL,
other_openmsg_address_name VARCHAR(40) NOT NULL,
other_acceptsMessages INT(1) NOT NULL,
auth_code VARCHAR(64) NOT NULL,
ident_code VARCHAR(64) NOT NULL,
message_crypt_key VARCHAR(64) NOT NULL,
timestamp_created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
)";
$stmt = $db->prepare($query);
$stmt->execute(); 
$stmt->close();


// Table to temporarily store an outbound message. 
    // The Receiving Node will make a curl request to verify the message came from the correct domain
$query = "CREATE TABLE IF NOT EXISTS openmsg_messages_outbox (
id INT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
self_openmsg_address VARCHAR(255) NOT NULL,
ident_code VARCHAR(64) NOT NULL,
message_hash VARCHAR(64) NOT NULL,
message_nonce VARCHAR(32) NOT NULL,
message_text VARCHAR(2000) NOT NULL,
timestamp_created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
)";
$stmt = $db->prepare($query);
$stmt->execute(); 
$stmt->close();


// Table to save the sent message after the message has been accepted by the Receiving Node
$query = "CREATE TABLE IF NOT EXISTS openmsg_messages_sent (
id INT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
self_openmsg_address VARCHAR(255) NOT NULL,
ident_code VARCHAR(64) NOT NULL,
message_hash VARCHAR(64) NOT NULL,
message_text VARCHAR(2000) NOT NULL,
timestamp_read INT(12),
timestamp_created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
)";
$stmt = $db->prepare($query);
$stmt->execute(); 
$stmt->close();





// Table to save pass_code that a User created // pass codes expire after 1 hour. Pass codes should be deleted after 1 hour or after use.
$query = "CREATE TABLE IF NOT EXISTS openmsg_passCodes (
id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
self_openmsg_address VARCHAR(255) NOT NULL,
pass_code  VARCHAR(6) NOT NULL,
timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
)";
$stmt = $db->prepare($query);
$stmt->execute(); // To Do: Un-comment
$stmt->close();


// Table to save the received message
$query = "CREATE TABLE IF NOT EXISTS openmsg_messages_inbox (
id INT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
self_openmsg_address VARCHAR(255) NOT NULL,
ident_code VARCHAR(64) NOT NULL,
message_hash VARCHAR(64) NOT NULL,
message_text VARCHAR(2000) NOT NULL,
timestamp_created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
)";
$stmt = $db->prepare($query);
$stmt->execute(); // To Do: Un-comment
$stmt->close();

?>





<?


// enter your domain here. This needs to be the domain used in your account addresses and where the /openmsg/ protocol folder is hosted

$my_openmsg_domain = "enter_your_domain_in_settings_file.com"; 
$sandbox = TRUE;
if($sandbox == TRUE) $sandbox_dir = "/sandbox";

// Connect to your database
/*
$DBhost = "localhost";
$DBuser = "username";
$DBpass = "password";
$DBName = "myDB";

$db = new mysqli($DBhost,$DBuser,$DBpass,$DBName);
if ($db -> connect_errno) {
  //echo "Failed to connect to MySQL: " . $db -> connect_error;
  echo "Failed to connect to database ";
  exit();
}

OR:
*/

require ($_SERVER["DOCUMENT_ROOT"]."/includes/test_database_connection.php"); // Delete or replace with database connection file



?>
<?
// ##### See: openmsg.io/docs/v1

echo "Put this file somewhere secure then disable the exit() in the code"; // below
exit();
// This page should be in the User's login protected area

require ($_SERVER["DOCUMENT_ROOT"]."/openmsg/openmsg_settings.php");

$self_openmsg_address = "1000000*".$my_openmsg_domain; // Greb the Openmsg address for the logged in user who wants the code.

function generateRandomString($length = 6) {
	$characters = '0123456789';
	$charactersLength = strlen($characters);
	$randomString = '';

	for ($i = 0; $i < $length; $i++) {
		$randomString .= $characters[random_int(0, $charactersLength - 1)];
	}

	return $randomString;
}

$new_pass_code = generateRandomString(6);

$stmt = $db->prepare("INSERT INTO openmsg_passCodes  (self_openmsg_address, pass_code) VALUES (?, ?)");
$stmt->bind_param("ss", $self_openmsg_address, $new_pass_code);
$stmt->execute();
$stmt->close();

echo "Hi User, here is your Pass Code: ".$new_pass_code.". It expires in 1 hour.";

?>
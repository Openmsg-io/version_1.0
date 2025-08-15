<?php
// ##### See: openmsg.io/docs/v1 

echo "Put this file somewhere secure then disable the exit() in the code"; // below
exit ();
// To Do: !IMPORTANT - This file should be stored somewhere password protected so only logged in users can access it.

require ($_SERVER["DOCUMENT_ROOT"]."/openmsg/openmsg_settings.php");

// Variables from an input form, filled in by an Openmsg User
$other_openmsg_address = $_POST["other_openmsg_address"]; 
$pass_code = $_POST["pass_code"]; 

//To Do: Get $self_openmsg_address, $self_openmsg_address_name, $self_allow_replies from database / logged in user
$self_openmsg_address_name = "John Doe"; // To Do: replace with the name of the person who owns self_openmsg_address, to help the User identify them. This should be set even if replies are not allowed
$self_openmsg_address = "1000001*".$my_openmsg_domain; // To Do: replace with the from/reply address. This should be set even if replies are not allowed
$self_allow_replies = true; // This tells the Receiving Node if replies can be received.

function initiate_handshake($db, $other_openmsg_address, $pass_code, $self_openmsg_address, $self_openmsg_address_name, $self_allow_replies, $my_openmsg_domain, $sandbox_dir){
	
	if(!$other_openmsg_address || !$pass_code || !$self_openmsg_address || !$self_openmsg_address_name || !$my_openmsg_domain) { 
        // If the database doesnt contain an ident_code / openmsg_address_id combo
          // or there are other errors then return error and error_message
        $response = array("error"=>TRUE, "response_code"=>"SM_E000", "error_message"=>"Missing data (8BgrT)"); 
        return($response);
    }
	
	// Check the data is formatted correctly
	// Check that openmsg_address_id is numeric only
	
	if(ctype_digit((string)$pass_code) == false) {
		//return("Please enter a Pass Code");
	}
	
	// Split the Openmsg address into the address ID and the domain 
	$other_openmsg_address_id = explode("*", $other_openmsg_address)[0]; 
	$other_openmsg_address_domain = explode("*", $other_openmsg_address)[1]; 
	
	// Check that openmsg_address_id is numeric only
	if(ctype_digit((string)$other_openmsg_address_id) == false) {
		return("other_openmsg_address_id not valid $other_openmsg_address_i (wD861)");
	}
	
	// Check that openmsg_address_domain only contains valid characters
	if(preg_match("/[^A-Za-z0-9.\-]/", $other_openmsg_address_domain) == true) {
		return("openmsg_address_domain not valid $other_openmsg_address_domain (D8hgB)");
	}
	
	// Store other_openmsg_address and pass_code temporarily in a separate table "openmsg_handshakes" with a short expiry timestamp (60 seconds)
	  // to make note of a pending Authorization Request as it will be used by the Receiving Node to confirm that the request
	  // originated from the same domain as the domain in other_openmsg_address.
	$stmt = $db->prepare("INSERT INTO openmsg_handshakes (other_openmsg_address, pass_code) VALUES (?, ?)");
	$stmt->bind_param("ss", $other_openmsg_address, $pass_code);
	$stmt->execute(); // To Do: Un-comment
	$stmt->close();
	

	
	// ##### Start CURL request ##### 
	// The url is created from the domain in the Openmsg address 
	$url = "https://".$other_openmsg_address_domain."/openmsg".$sandbox_dir."/auth/"; 

	// JSON data
	$data = 
		array(
		  "receiving_openmsg_address_id" => $other_openmsg_address_id,
		  "pass_code" => $pass_code,
		  "sending_openmsg_address" => $self_openmsg_address,
		  "sending_openmsg_address_name" => $self_openmsg_address_name,
		  "sending_allow_replies" => $self_allow_replies,
		  "openmsg_version" => 1.0
	   );
	$data = json_encode($data);
	
	
	$curl = curl_init();  
	curl_setopt($curl, CURLOPT_URL, $url); 
	curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
	curl_setopt($curl, CURLOPT_POST, true); 
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); 
	curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false); 
	curl_setopt($curl, CURLOPT_MAXREDIRS, 3); 
	curl_setopt($curl, CURLOPT_POSTFIELDS, $data); 

	
	$response = curl_exec($curl);
	
	$info = curl_getinfo($curl);
	if ($info["http_code"] == 301 || $info["http_code"] == 302) {
		$redirectUrl = $info["redirect_url"]; // Not always present; you may need to parse headers manually
		curl_setopt($curl, CURLOPT_URL, $redirectUrl);
		$response = curl_exec($curl);
	}
	
	$response = json_decode($response, true);
	
	// Check for errors
	if($err = curl_error($curl)) return("Error. Curl error: ".$err);
	if(($curl_status = curl_getinfo($curl, CURLINFO_HTTP_CODE)) != 200) return("Error. Curl response status: ".$curl_status." (A50m5)");
	if($response["error"]) return("Error: ".$response["error_message"]);
	if(($success = $response["success"]) != TRUE) return("Error: Unsuccessful from initiate-handshake.php (LyoSV) ");
	
	if(!($auth_code = $response["auth_code"])) return("Error: Missing auth_code in response (CxMBc)");
	if(!($ident_code = $response["ident_code"])) return("Error: Missing ident_code in response (BC2Lm)");
	if(!($message_crypt_key = $response["message_crypt_key"])) return("Error: Missing message_crypt_key in response (nEG5M)");
	if(!($other_openmsg_address_name = $response["receiving_openmsg_address_name"])) return("Error: Missing other_openmsg_address_name in response (92Apb)");
	
	curl_close($curl); 
    $other_acceptsMessages = TRUE;
	
	// Delete any previous connections between these two users so they only have one connection at a time.
	$stmt = $db->prepare("DELETE FROM openmsg_user_connections WHERE self_openmsg_address = ? AND other_openmsg_address = ?");
	$stmt->bind_param("ss", $self_openmsg_address, $other_openmsg_address);
	$stmt->execute(); // To Do: Un-comment
	$stmt->close();	
	
	 // Store the openmsg_address, message_crypt_key, auth_code and ident_code in the database as they will be the 
	 	// codes to send messages to the user and to authenticate messages from the User
	$stmt = $db->prepare("INSERT INTO openmsg_user_connections (self_openmsg_address, other_openmsg_address, other_openmsg_address_name, other_acceptsMessages, auth_code, ident_code, message_crypt_key) 
																  VALUES (?, ?, ?, ?, ?, ?, ?)");
	$stmt->bind_param("sssssss", $self_openmsg_address, $other_openmsg_address, $other_openmsg_address_name, $other_acceptsMessages, $auth_code, $ident_code, $message_crypt_key);
	$stmt->execute(); // To Do: Un-comment
	$stmt->close();
	return("Success");
	
}
$response = initiate_handshake($db, $other_openmsg_address, $pass_code, $self_openmsg_address, $self_openmsg_address_name, $self_allow_replies, $my_openmsg_domain, $sandbox_dir);
if($response == "Success"){
	echo "Connected. You can now message us: ".$self_openmsg_address;
	//echo "Connected. You are now subsribed for updates about your order."; // Show an appropriate response
}else{
	echo "Error: ".$response;
}
?>


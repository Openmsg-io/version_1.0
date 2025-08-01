<?php
// ##### See: openmsg.io/docs/v1

echo "Put this file somewhere secure then disable the exit() in the code"; // below
exit();
// This page should be in the User's login protected area

require ($_SERVER["DOCUMENT_ROOT"]."/openmsg/openmsg_settings.php");

// The below values are for testing only
$message_text = "Hello Openmsg user!"; // message to be sent
$sending_openmsg_address = "1000000*".$my_openmsg_domain; // To Do: Enter the from address for the Sending Node
$receiving_openmsg_address = "1000001*".$my_openmsg_domain; // To Do: The address where the message is being sent to

function send_message($db, $message_text, $sending_openmsg_address, $receiving_openmsg_address, $sandbox_dir){
	
	$stmt = $db->prepare("SELECT auth_code, ident_code, message_crypt_key FROM openmsg_user_connections WHERE self_openmsg_address = ? AND other_openmsg_address = ?");
    $stmt->bind_param("ss", $sending_openmsg_address, $receiving_openmsg_address);
    $stmt->execute();  // To Do: Un-comment
    $stmt->store_result();
    $stmt->bind_result($auth_code, $ident_code, $message_crypt_key);
    $stmt->fetch();
    $matching_connections = $stmt->num_rows;
    $stmt->close();
	
	if(!($matching_connections > 0 && $auth_code && $ident_code && $message_crypt_key)){
		$response = array("error"=>TRUE, "response_code"=>"SM_E001", "error_code"=>"ident_code_invalid", "error_message"=>"No matching connection between these two users $sending_openmsg_address, $receiving_openmsg_address (Qmyxm)"); 
        return($response);
	}
	
	$receiving_openmsg_address_id = explode("*", $receiving_openmsg_address)[0]; 
	$receiving_openmsg_address_domain = explode("*", $receiving_openmsg_address)[1]; 
	
	    $message_nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
	    $message_nonce_encoded = sodium_bin2base64(
	        $message_nonce, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING
	    );
	    $message_encrypted = sodium_crypto_secretbox($message_text, $message_nonce, sodium_hex2bin($message_crypt_key));
	    $message_package = sodium_bin2base64(
	        $message_nonce . $message_encrypted,
	        SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING
	    );
	
	$message_salt = bin2hex(random_bytes(16));
	$message_timestamp = time();  
	$message_hash = hash("sha256", $message_package.$auth_code.$message_salt.$message_timestamp);
	
	// Save message_hash and message_nonce to database table "openmsg_messages_outbox". 
		// The Receiving Node will use this in a return curl request to confirm the origin domain
	$stmt = $db->prepare("INSERT INTO openmsg_messages_outbox (self_openmsg_address, ident_code, message_hash, message_nonce, message_text) 
																  VALUES (?, ?, ?, ?, ?)");
	$stmt->bind_param("sssss", $sending_openmsg_address, $ident_code, $message_hash, $message_nonce_encoded, $message_text);
	$stmt->execute(); // To Do: Un-comment
	$stmt->close();
	
	// ##### Start CURL request #####
	// The url is created from the domain in the Openmsg address 
	$url = "https://".$receiving_openmsg_address_domain."/openmsg".$sandbox_dir."/message-receive/"; 
	
	// JSON data
	$data = 
		array(
		  "receiving_openmsg_address_id" => $receiving_openmsg_address_id,
		  "sending_openmsg_address_name" => $sending_openmsg_address_name, // only include this if you want to update the name assosiated with the sender. The Receiving Node should update this on their end.
		   
		  "ident_code" => $ident_code,
		  "message_package" => $message_package,
		  "message_hash" => $message_hash,
		  "message_salt" => $message_salt,
		  "message_timestamp" => $message_timestamp,
	  	  "openmsg_version" => 1.0,
		  // Verified account data can be left blank
		  "verified_account" => array(
			  "verified_account_signature" => $verified_account_signature,
			  "verified_account_name" => $verified_account_name,
			  "verified_account_expires" => $verified_account_timestamp
			)
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
	curl_close($curl); 
	
	// Check for errors
	if($err = curl_error($curl)) return(array("error"=>TRUE, "response_code"=>"SM_E000", "error_message"=>"Error: $err (gcbNI)"));
	if(($curl_status = curl_getinfo($curl, CURLINFO_HTTP_CODE)) != 200) return(array("error"=>TRUE, "response_code"=>$response_code, "error_message"=>"cURL response status: $curl_status (s3mK6)"));
	if($response["error"]) return(array("error"=>TRUE, "response_code"=>$response_code, "error_message"=>"Error: ".$response["error_message"]." (siULi)"));
	if(($success = $response["success"]) != TRUE) return(array("error"=>TRUE, "response_code"=>$response_code, "error_message"=>"Unsuccessful (5Ljkz)"));
	
	// Save message data in "Sent" table
	$stmt = $db->prepare("INSERT INTO openmsg_messages_sent (self_openmsg_address, ident_code, message_hash, message_text) 
																  VALUES (?, ?, ?, ?)");
	$stmt->bind_param("ssss", $sending_openmsg_address, $ident_code, $message_hash, $message_text);
	$stmt->execute(); // To Do: Un-comment
	$stmt->close();
	

    $stmt = $db->prepare("DELETE FROM openmsg_messages_outbox WHERE message_hash = ? AND message_nonce = ?");
    $stmt->bind_param("ss", $message_hash, $message_nonce_encoded);
    $stmt->execute();  // To Do: Un-comment
	
	$response = array("success"=>TRUE, "response_code"=>$response["response_code"]); 
	return($response);

}

$response = send_message($db, $message_text, $sending_openmsg_address, $receiving_openmsg_address, $sandbox_dir);
echo print_r($response);
/*
Errors:
error = TRUE
response_code | error_code | Plain Text
SM_E000 | unknown_error | message_data_missing | unknown error // Other error
SM_E001 | ident_code_invalid | user not known
SM_E002 | message_rejected_byuser | This address does not accept incomming messages // (Can be used for noreply addresses or as a quiet way for a User to block a sender)
SM_E003 | message_unknown | Unknown Message. No outgoing message matching these details or message was sent from an unauthorized domain domain.
SM_E004 | message_hash_invalid | could not recreate hash - auth_code mismatch, assuming message_timestamp and message_package were sent correctly
SM_E005 | message_expired | Message sent over 60 seconds ago. Expired message request. 
SM_E006 | message_decryption_error | The message decryption failed. Encryption key mis-match or corrupt message, assuming nonce was sent correctly
SM_E007 | message_nonce_error | This message encryption nonce has been used before for this connection
SM_E008 | message_rejected_temp | Message rejected temporary - server is being maintained
SM_E009 | message_rejected_perm | Message rejected permanently (service has closed down, messages from sending domain or IP address are blocked)

Success:
success = TRUE
response_code | Plain Text
SM_S888 | Message received successfully and accepted
*/

?>

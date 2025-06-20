<?php
// ##### See: openmsg.io/docs/v1

require ($_SERVER["DOCUMENT_ROOT"]."/openmsg/openmsg_settings.php");


$data = json_decode(file_get_contents("php://input"), true);
$receiving_openmsg_address_id = $data["receiving_openmsg_address_id"]; 
$sending_openmsg_address_name = $data["sending_openmsg_address_name"]; 


$ident_code = $data["ident_code"]; 
$message_package = $data["message_package"]; 
$message_hash = $data["message_hash"]; 
$message_salt = $data["message_salt"]; 
$message_timestamp = $data["message_timestamp"];

//require "account-verify.php"; // this will be added in future versions
$verified_account_signature = $data["verified_account"]["verified_account_signature"]; 
$verified_account_name = $data["verified_account"]["verified_account_name"]; 
$verified_account_expires = $data["verified_account"]["verified_account_expires"]; 


function message_check ($db, $receiving_openmsg_address_id, $ident_code, $message_package, $message_hash, $message_salt, $message_timestamp, $my_openmsg_domain, $sandbox_dir){
    
	 if(!$receiving_openmsg_address_id || !$ident_code || !$message_package || !$message_hash || !$message_salt || !$message_timestamp || !$my_openmsg_domain) { 
        // If the database doesnt contain an ident_code / openmsg_address_id combo
          // or there are other errors then return error and error_message
        $response = array("error"=>TRUE, "response_code"=>"SM_E000", "error_message"=>"Missing data (wMv4J)"); 
        return($response);
    }
	
	$receiving_openmsg_address = $receiving_openmsg_address_id."*".$my_openmsg_domain;
    // Query database to retreive auth_code, message_crypt_key and other_openmsg_address that matches ident_code and openmsg_address_id
	$stmt = $db->prepare("SELECT auth_code, message_crypt_key, other_openmsg_address FROM openmsg_user_connections WHERE self_openmsg_address = ? AND ident_code = ?");
    $stmt->bind_param("ss", $receiving_openmsg_address, $ident_code);
    $stmt->execute();  // To Do: Un-comment
    $stmt->store_result();
    $stmt->bind_result($auth_code, $message_crypt_key, $sending_openmsg_address);
    $stmt->fetch();
    $matching_connections = $stmt->num_rows;
    $stmt->close();
	
	
    if(!$auth_code || !$ident_code || !$message_crypt_key || !$sending_openmsg_address) { 
        // If the database doesnt contain an ident_code / openmsg_address_id combo
          // or there are other errors then return error and error_message
        $response = array("error"=>TRUE, "response_code"=>"SM_E001", "error_message"=>"Could not find user::: $receiving_openmsg_address (rB6Xl)"); 
        return($response);
    }
	
    
    // Split the from Openmsg address to get the the domain 
    $sending_openmsg_address_domain = explode("*", $sending_openmsg_address)[1]; 

    //Check the hash is valid
    $message_hash_test = hash("sha256", $message_package.$auth_code.$message_salt.$message_timestamp);
    if($message_hash!=$message_hash_test) return (array("error"=>TRUE, "response_code"=>"SM_E004", "error_message"=>"There was an error with the authorization (4NxWV)"));
    
	
	
    $message_hash_expiry_seconds = 60;
    if(($message_timestamp+$message_hash_expiry_seconds) < time()) return (array("error"=>TRUE, "response_code"=>"SM_E005", "error_message"=>"Hash is too old (kmqVE)"));
    
	
    // Decode the message package
    $message_package_decoded = base64_decode($message_package);
	
    // Split the package into nonce and ciphertext
    $message_nonce = mb_substr($message_package_decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, "8bit");
	$message_nonce_encoded = base64_encode($message_nonce);
    $message_encrypted = mb_substr($message_package_decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, null, "8bit");
    
    // ##### Start CURL request #####
    // The url is created from the domain in the initiating Openmsg address 
    // The originating domain checks its temporary records for a outgoing message with message_hash and message_nonce
    // This confirms that the request has come from the domain in the sending_openmsg_address
    $url = "https://".$sending_openmsg_address_domain."/openmsg".$sandbox_dir."/message-confirm/"; 
    
    // JSON data
    $data = 
        array(
          "message_hash" => $message_hash,
          "message_nonce" => $message_nonce_encoded
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
    $err = curl_error($curl); 
    $error = $response["error"]; 
    $error_message =  $response["error_message"]; 
    
    curl_close($curl); 
    
	
	if($err = curl_error($curl)) return(array("error"=>TRUE, "response_code"=>"SM_E000", "error_message"=>"Error: $err $sending_openmsg_address (vSiFb)"));
    $curl_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
	if($curl_status == 404) return(array("error"=>TRUE, "response_code"=>"SM_E003", "error_message"=>"No reply from host $sending_openmsg_address_domain - cURL response status: $curl_status (i9ZsS)"));
	if($curl_status != 200) return(array("error"=>TRUE, "response_code"=>"SM_E000", "error_message"=>"$sending_openmsg_address_domain cURL response status: $curl_status (0xOIw)"));
	if($response["error"]) return(array("error"=>TRUE, "response_code"=>$response["response_code"], "error_message"=>"Error: ".$response["error_message"]." (PqN9h)"));
	if(($success = $response["success"]) != TRUE) return(array("error"=>TRUE, "response_code"=>"SM_E000", "error_message"=>"Unsuccessful - unknown reason (x8rXc)"));
	
    

    
    // Now attempt to decrypt the message
    $message_decrypted = sodium_crypto_secretbox_open($message_encrypted, $message_nonce, sodium_hex2bin($message_crypt_key));
    if ($message_decrypted === false) {
        return(array("error"=>TRUE, "response_code"=>"SM_E005", "error_message"=>"Invalid key or corrupt message (QctWn)")); 
    }
    
	$message_text = $message_decrypted;
	// $message_text should be re-encrypted using your own encrytion key before saving:
	// $message_text = sodium_crypto_secretbox($message_text ...

    // Receiving Node saves the message for the User to access
	$stmt = $db->prepare("INSERT INTO openmsg_messages_inbox (self_openmsg_address, ident_code, message_hash, message_text) 
																  VALUES (?, ?, ?, ?)");
	$stmt->bind_param("ssss", $receiving_openmsg_address, $ident_code, $message_hash, $message_text);
	$stmt->execute(); // To Do: Un-comment
	$stmt->close();
	
    // To Do: Receiving Node to update other_openmsg_address_name if new name is provided
	
    $response = array("success"=> TRUE, "response_code"=>"SM_S888"); 
    return($response);

}



$response = message_check ($db, $receiving_openmsg_address_id, $ident_code, $message_package, 
                           $message_hash, $message_salt, $message_timestamp, $my_openmsg_domain, $sandbox_dir); // $db = database connection
echo json_encode($response);  

?>
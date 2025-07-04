<?php
// ##### See: openmsg.io/docs/v1

require "../openmsg_settings.php";

// To Do: $my_openmsg_domain needs to be filled in with your actual domain name in the openmsg_settings.php file

$data = json_decode(file_get_contents("php://input"), true);

$self_openmsg_address_id = $data["receiving_openmsg_address_id"]; 
$pass_code = $data["pass_code"]; 
$other_openmsg_address = $data["sending_openmsg_address"]; 
$other_openmsg_address_name = $data["sending_openmsg_address_name"]; 
$other_allows_replies = $data["sending_allow_replies"];


function auth_check ($db, $self_openmsg_address_id, $pass_code, $other_openmsg_address, $other_openmsg_address_name, $other_allows_replies, $my_openmsg_domain, $sandbox_dir){
    // Verify the data received is present, else return an error
    if($self_openmsg_address_id == "" || $pass_code == "" || $other_openmsg_address == "" || $other_openmsg_address_name == "") {
        $response = array("error"=>TRUE, "error_message"=>"self_openmsg_address_id, pass_code, other_openmsg_address and other_openmsg_address_name cannot be blank :: $self_openmsg_address_id, $pass_code, $other_openmsg_address and $other_openmsg_address_name"); 
        return($response);
    }
    
    $self_openmsg_address = $self_openmsg_address_id."*".$my_openmsg_domain;

	// Query database to get name of user
    $stmt = $db->prepare("SELECT self_openmsg_address_name FROM openmsg_users WHERE self_openmsg_address = ?");
    $stmt->bind_param("s", $self_openmsg_address);
    $stmt->execute(); // To Do: Un-comment
    $stmt->store_result();
    $stmt->bind_result($self_openmsg_address_name);
    $stmt->fetch();
    $matching_passCodes = $stmt->num_rows;
    $stmt->close();
    
    // Query database to check validity of pass_code / $openmsg_address_id combo
    $stmt = $db->prepare("SELECT UNIX_TIMESTAMP(timestamp) FROM openmsg_passCodes WHERE self_openmsg_address = ? AND pass_code = ?");
    $stmt->bind_param("ss", $self_openmsg_address, $pass_code);
    $stmt->execute(); // To Do: Un-comment
    $stmt->store_result();
    $stmt->bind_result($passCode_timestamp);
    $stmt->fetch();
    $matching_passCodes = $stmt->num_rows;
    $stmt->close();
    
    if($matching_passCodes == 0){
        // If there are no matching pass_codes for that address then return error and error_message
        $response = array("error"=>TRUE, "error_message"=>"pass code not valid"); 
        return($response); 
    }
    
    $oneHour = 3600; // 3600 seconds
    if($passCode_timestamp < time()-$oneHour){
        // If the pass_code has expired then return error and error_message
        $response = array("error"=>TRUE, "error_message"=>"expired pass code, over 1 hour old"); 
        return($response); 
    }
    
    // The pass code has been verified once. It should now be deleted at this stage so it cant be re-used
    $stmt = $db->prepare("DELETE FROM openmsg_passCodes WHERE self_openmsg_address = ? AND pass_code = ? LIMIT 1");
    $stmt->bind_param("ss", $self_openmsg_address, $pass_code);
    $stmt->execute(); // To Do: Un-comment
    
    // Split the other_openmsg_address into the address ID and the sending domain 
    $other_openmsg_address_id = explode("*", $other_openmsg_address)[0]; 
    $other_openmsg_address_domain = explode("*", $other_openmsg_address)[1]; 
    
    
    // Check that openmsg_address_id is numeric only
    if(ctype_digit($other_openmsg_address_id) == false) {
        $response = array("error"=>TRUE, "error_message"=>"other_openmsg_address_id should be numeric"); 
        return($response);
    }
    
    // ##### Start CURL request #####
    // The url is created from the domain in the initiating Openmsg address 
    // The domain checks its temporary records for a pending authorization with openmsg_address and pass_code
    // This confirms that the request has come from the domain in the other_openmsg_address
    $url = "https://".$other_openmsg_address_domain."/openmsg".$sandbox_dir."/auth-confirm/"; 
    
    // JSON data
    $data = 
        array(
          "receiving_openmsg_address" => $self_openmsg_address_id."*".$my_openmsg_domain, 
          "pass_code" => $pass_code
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
		//return(array("error"=>TRUE, "error_message"=>"Error: Redirect URL ".$redirectUrl));
	}
	
	$response = json_decode($response, true);
	curl_close($curl);  
	
    // Check for errors
    if($err = curl_error($curl)) return(array("error"=>TRUE, "error_message"=>"Error. Curl error: ".$err));
	if(($curl_status = curl_getinfo($curl, CURLINFO_HTTP_CODE)) != 200) return(array("error"=>TRUE, "error_message"=>"Error. Curl response status -- : ".$curl_status));
	if($response["error"]) return(array("error"=>TRUE, "error_message"=>"Error: ".$response["error_message"]));
	if(($success = $response["success"]) != TRUE) return(array("error"=>TRUE, "error_message"=>"Error: Unsuccessful from /auth/"));
    
    
    if(!$error){
		
        $auth_code = bin2hex(random_bytes(32));  
        $ident_code = bin2hex(random_bytes(32)); 
        $message_crypt_key = sodium_bin2hex(sodium_crypto_secretbox_keygen());
		
		// Delete any previous connections between these two users so they only have one connection at a time.
		$stmt = $db->prepare("DELETE FROM openmsg_user_connections WHERE self_openmsg_address = ? AND other_openmsg_address = ?");
		$stmt->bind_param("ss", $self_openmsg_address, $other_openmsg_address);
		$stmt->execute(); // To Do: Un-comment
		$stmt->close();	
		
        // Store the auth_code, ident_code, message_crypt_key, other_openmsg_address, other_openmsg_address_name in your 
            // database table "openmsg_user_connections" so that the two accounts can now communicate unsing the auth_code etc.
		$stmt = $db->prepare("INSERT INTO openmsg_user_connections (self_openmsg_address, other_openmsg_address, other_openmsg_address_name, other_acceptsMessages, auth_code, ident_code, message_crypt_key) 
																  VALUES (?, ?, ?, ?, ?, ?, ?)");
		$stmt->bind_param("sssssss", $self_openmsg_address, $other_openmsg_address, $other_openmsg_address_name, $other_allows_replies, $auth_code, $ident_code, $message_crypt_key);
		$stmt->execute(); // To Do: Un-comment
		$stmt->close();	
		
        // To Do: Receiving Node: IMPORTANT - Mark the pass_code as used in the database table "openmsg_passCodes" so it cant be used again. 
		$stmt = $db->prepare("DELETE FROM openmsg_passCodes WHERE self_openmsg_address = ? AND pass_code = ? LIMIT 1");
		$stmt->bind_param("ss", $self_openmsg_address, $pass_code);
		$stmt->execute(); // To Do: Un-comment	
		
		
        $response = array("success"=>TRUE, "auth_code"=>$auth_code, "ident_code"=>$ident_code, "message_crypt_key"=>$message_crypt_key, "receiving_openmsg_address_name"=>$self_openmsg_address_name); 
		
        return($response);
    }else{
        $response = array("error"=>TRUE, "error_message"=>"Error"); 
        return($response);
    }
}

$response = auth_check ($db, $self_openmsg_address_id, $pass_code, $other_openmsg_address, $other_openmsg_address_name, $other_allows_replies, $my_openmsg_domain, $sandbox_dir); // $db = database connection
echo json_encode($response);  

?>

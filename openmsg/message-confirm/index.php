<?php
// ##### See: openmsg.io/docs/v1


require "../openmsg_settings.php";

$data = json_decode(file_get_contents("php://input"), true);
$message_hash = $data["message_hash"]; 
$message_nonce = $data["message_nonce"]; 


function message_confirm ($db, $message_hash, $message_nonce){
    // Query database table "outbox" to check for a pending outgoing message that matches message_hash, message_nonce and message_timestamp that hasnt expired. The outbox message should expire after 60 seconds.
	if(!$message_hash || !$message_nonce){
		$response = array("error"=>TRUE, "response_code"=>"SM_E000", "error_code"=>"message_data_missing", "error_message"=>"Missing message_hash or message_nonce (qUfd6)"); 
        return($response);
	}
	
	$stmt = $db->prepare("SELECT * FROM openmsg_messages_outbox WHERE message_hash = ? AND message_nonce = ?");
    $stmt->bind_param("ss", $message_hash, $message_nonce);
    $stmt->execute();  // To Do: Un-comment
    $stmt->store_result();
    $stmt->fetch();
    $matching_messages = $stmt->num_rows;
    $stmt->close();
   	if($matching_messages == 0) { 
        // If there are errors then return error and error_message
        $response = array("error"=>TRUE, "response_code"=>"SM_E003", "error_code"=>"message_unknown", "error_message"=>"Unknown Message. No outgoing message matching these details or message was sent from an unauthorized domain domain. (poyQ2)");  
        return($response);
   	}
	
	
    $response = array("success"=>TRUE); 
    return($response);
}

$response = message_confirm ($db, $message_hash, $message_nonce); // $db = database connection
echo json_encode($response);  

?>

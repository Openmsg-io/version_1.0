<?php
// ##### See: openmsg.io/docs/v1


require "../openmsg_settings.php";

$data = json_decode(file_get_contents("php://input"), true);

$other_openmsg_address = $data["receiving_openmsg_address"]; 
$passcode_hash = $data["passcode_hash"]; 


function auth_confirm ($db, $other_openmsg_address, $passcode_hash){
    // Query database to check for a pending authorization request with openmsg_address and passcode_hash 
    $stmt = $db->prepare("SELECT UNIX_TIMESTAMP(timestamp) FROM openmsg_handshakes WHERE other_openmsg_address = ? AND passcode_hash = ?");
    $stmt->bind_param("ss", $other_openmsg_address, $passcode_hash);
    $stmt->execute();  // To Do: Un-comment
    $stmt->store_result();
    $stmt->bind_result($initation_timestamp);
    $stmt->fetch();
    $matching_handshakes = $stmt->num_rows;
    $stmt->close();
    
    if($matching_handshakes == 0) { 
        // If the passcode_hash isnt present for the openmsg_address then return error and error_message
        $response = array("error"=>TRUE, "error_message"=>"unknown pending authorization with $other_openmsg_address, $passcode_hash"); 
        return($response);
    }
    if($initation_timestamp < time()-60){
        // If the handshake has expired then return error and error_message
        $response = array("error"=>TRUE, "error_message"=>"expired handshake, over 60 seconds old"); 
        return($response);
    }
    
    // The pending handshake has been verified once. It should now be deleted at this stage so it cant be re-used
    $stmt = $db->prepare("DELETE FROM openmsg_handshakes WHERE other_openmsg_address = ? AND passcode_hash = ? LIMIT 1");
    $stmt->bind_param("ss", $other_openmsg_address, $passcode_hash);
    $stmt->execute(); // To Do: Un-comment
     
    $response = array("success"=>TRUE); 
    return($response);
}

$response = auth_confirm ($db, $other_openmsg_address, $passcode_hash); // $db = database connection
echo json_encode($response);  

?>


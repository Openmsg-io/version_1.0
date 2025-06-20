<?php
// ##### See: openmsg.io/docs/v1


require "../openmsg_settings.php";

$data = json_decode(file_get_contents("php://input"), true);

$other_openmsg_address = $data["other_openmsg_address"]; 
$pass_code = $data["pass_code"]; 


function auth_confirm ($db, $other_openmsg_address, $pass_code){
    // Query database to check for a pending authorization request with openmsg_address and pass_code 
    $stmt = $db->prepare("SELECT UNIX_TIMESTAMP(timestamp) FROM openmsg_handshakes WHERE other_openmsg_address = ? AND pass_code = ?");
    $stmt->bind_param("ss", $other_openmsg_address, $pass_code);
    $stmt->execute();  // To Do: Un-comment
    $stmt->store_result();
    $stmt->bind_result($initation_timestamp);
    $stmt->fetch();
    $matching_handshakes = $stmt->num_rows;
    $stmt->close();
    
    if($matching_handshakes == 0) { 
        // If the pass_code isnt present for the openmsg_address then return error and error_message
        $response = array("error"=>TRUE, "error_message"=>"unknown pending authorization with $other_openmsg_address, $pass_code"); 
        return($response);
    }
    if($initation_timestamp < time()-60){
        // If the pass_code has expired then return error and error_message
        $response = array("error"=>TRUE, "error_message"=>"expired handshake, over 60 seconds old"); 
        return($response);
    }
    
    // The pending handshake has been verified once. It should now be deleted at this stage so it cant be re-used
    $stmt = $db->prepare("DELETE FROM openmsg_handshakes WHERE other_openmsg_address = ? AND pass_code = ? LIMIT 1");
    $stmt->bind_param("ss", $other_openmsg_address, $pass_code);
    $stmt->execute(); // To Do: Un-comment
     
    $response = array("success"=>TRUE); 
    return($response);
}

$response = auth_confirm ($db, $other_openmsg_address, $pass_code); // $db = database connection
echo json_encode($response);  

?>

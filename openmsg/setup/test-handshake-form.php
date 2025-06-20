
<?

// This page should be in the User's login protected area

require ($_SERVER["DOCUMENT_ROOT"]."/openmsg/openmsg_settings.php");

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Test Pages - Handshake Form</title>
</head>

<body>

<form id="openmsg_form" name="openmsg_form" method="post" action="initiate-handshake.php">
   <input name="other_openmsg_address" type="text" id="other_openmsg_address" value="1000000*<? echo $my_openmsg_domain; ?>" /> openmsg_address<br />
   <input type="password" name="pass_code" id="pass_code" /> pass_code<br />

   <label>
     <input type="submit" name="button" id="button" value="Submit" />
   </label>
</form>
</body>
</html>
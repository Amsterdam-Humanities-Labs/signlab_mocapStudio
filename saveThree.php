<?
error_reporting(E_ERROR | E_PARSE);

// // Display no errors or warnings
// error_reporting(0);

include('../mysql_config.php');

// Create a connection to the database
$conn = new mysqli($servername, $username, $password, $database);

$glos1 = $_POST['glos1'];
$glos2 = $_POST['glos2'];
$glos3 = $_POST['glos3'];


//look for glos1,2,3 in mocap_files then set pineapple to 1
$stmt = $conn->prepare("UPDATE mocap_data SET pineapple = 1 WHERE glos LIKE ? OR glos LIKE ? OR glos LIKE ?");
$stmt->bind_param("sss", $glos1, $glos2, $glos3);
$stmt->execute();
$stmt->close();

//output success
$response = array("success" => "success");
echo json_encode($response);


?>
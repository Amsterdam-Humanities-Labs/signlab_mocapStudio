<?php
header('Content-Type: application/json');

include('../mysql_config.php');
require_once __DIR__ . '/sc_paths.php';   // loads signcollect-lib, and so sc_env(), when installed

// A setting from the environment, else from the env file signcollect-lib's
// sc_env() reads (/web/.env), else $default. signlab_signcollect-stack#23.
$legacySetting = function ($key, $default) {
    $value = getenv($key);
    if (is_string($value) && $value !== '') {
        return $value;
    }
    if (function_exists('sc_env')) {
        try {
            $vars = sc_env();
            if (isset($vars[$key]) && $vars[$key] !== '') {
                return $vars[$key];
            }
        } catch (RuntimeException $e) {
            // No env file: use the default.
        }
    }
    return $default;
};
// This script was written for the retired leffe host, whose docroot was
// /var/www/html. The default is that old literal, so behaviour is unchanged
// when SC_LEGACY_WEB_ROOT is unset.
$legacyWebRoot = rtrim($legacySetting('SC_LEGACY_WEB_ROOT', '/var/www/html'), '/');
$glos = isset($_POST['glos']) ? $_POST['glos'] : null;
$userId = isset($_POST['userid']) ? $_POST['userid'] : null;
$capturedStatus = isset($_POST['captured_status']) ? intval($_POST['captured_status']) : 1;
$captureId = isset($_POST['capture_id']) ? $_POST['capture_id'] : null;

// Set the response content type to JSON
//disable php warning
error_reporting(E_ERROR | E_PARSE);
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function($e) {
    http_response_code(500); // Set appropriate response code if needed
    echo json_encode(array("success" => false, "error" => $e->getMessage()));
    exit();
});

// Create a connection to the database
$conn = new mysqli($servername, $username, $password, $database);



//get userid and unrealtake from formdata
// $unrealtake = $_POST['unrealTake'];
// $glos = $_POST['glos'];

$dateNow = date('Y-m-d H:i:s');
$glosFbx = $glos.".fbx";

// Update mocap_data table
$stmt = $conn->prepare("UPDATE mocap_data SET take = ?, take_date = ? WHERE glos LIKE ?");
$stmt->bind_param("sss", $glosFbx, $dateNow, $glos);
$stmt->execute();
$stmt->close();

// Update captures table if capture_id is provided
if ($captureId && is_numeric($captureId)) {
    $capturedTime = $capturedStatus == 1 ? $dateNow : null;
    $updateCaptureStmt = $conn->prepare("UPDATE captures SET captured = ?, captured_time = ? WHERE id = ?");
    $updateCaptureStmt->bind_param("isi", $capturedStatus, $capturedTime, $captureId);
    $updateCaptureStmt->execute();
    $updateCaptureStmt->close();
    
    // Also update file availability flags based on actual files
    if ($capturedStatus == 1) {
        $updateFileStmt = $conn->prepare("UPDATE captures SET has_fbx = 1, has_glb = 1 WHERE id = ?");
        $updateFileStmt->bind_param("i", $captureId);
        $updateFileStmt->execute();
        $updateFileStmt->close();
    }
}

$response = array("success" => $glos);


//for now we want to get filelist from /web/gebarenoverleg_media/fbx fbx filelist, from november 1st 2024 on, then get basename and look in form_data for matching glos then update unreal_take

$directory = $legacyWebRoot . '/gebarenoverleg_media/fbx';
$filelist = array_filter(glob($directory . '/*.{glb}', GLOB_BRACE), function($file) {
    return filemtime($file) >= strtotime('2024-11-01');
});
usort($filelist, function($a, $b) {
    return filemtime($a) <=> filemtime($b);
});

//get last 5 items of filelist
$filelist = array_slice($filelist, -5);
// print_r($filelist);

// Prepare the SELECT statement to check for existing records based on filename
$selectStmt = $conn->prepare("SELECT id FROM mocap_files WHERE filename = ?");
if (!$selectStmt) {
    throw new Exception("Prepare SELECT failed: " . $conn->error);
}

// Prepare the INSERT statement for mocap_files, now including 'filename'
$insertStmt = $conn->prepare("INSERT INTO mocap_files (glos, take, datetime, avatarName, filename) VALUES (?, ?, ?, ?, ?)");
if (!$insertStmt) {
    throw new Exception("Prepare INSERT failed: " . $conn->error);
}

// Bind parameters for SELECT
$selectStmt->bind_param("s", $filenameCheck);

// Bind parameters for INSERT, now including $filename
$insertStmt->bind_param("sisss", $glos, $take, $datetime, $avatarName, $filename);

foreach ($filelist as $file) {
    $filename = basename($file); // Raw filename
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    // Initialize variables
    $glos = '';
    $take = 0;
    $datetime = '';
    $avatarName = 'GlassesGuyRecord_C_1'; // Default avatarName

    // Updated Patterns for filename matching
    // Detailed pattern: e.g., "AAP_A_241120_2_GlassesGuyRecord_C_1.fbx" or "22-B_241120_2_GlassesGuyRecord_C_1.fbx"
    //the date is 241120, that means, 2024-11-20
    $detailedPattern = '/^([A-Za-z0-9\-]+)_(\d{6})_(\d+)_([A-Za-z0-9_]+)\.(fbx|glb)$/';

    // Simple pattern: e.g., "AAP.fbx" or "22-B.fbx"
    $simplePattern = '/^([A-Za-z0-9\-]+)\.(fbx|glb)$/';

    if (preg_match($detailedPattern, $filename, $matches)) {
        // Extract information from detailed filename
        $glos = $matches[1]; // e.g., "AAP_A" or "22-B"
        $dateStr = $matches[2]; // e.g., "241120" (YYMMDD)
        $take = intval($matches[3]); // e.g., 2
        $avatarName = $matches[4]; // e.g., "GlassesGuyRecord_C_1"

        // Convert date string to datetime
        $datetime = date('Y-m-d H:i:s', strtotime('20' . substr($dateStr, 0, 2) . '-' . substr($dateStr, 2, 2) . '-' . substr($dateStr, 4, 2)));
        // echo $datetime;

    } elseif (preg_match($simplePattern, $filename, $matches)) {
        // Extract information from simple filename
        $glos = $matches[1]; // e.g., "AAP" or "22-B"
        $take = 0; // Default take

        // Get file modification time for datetime
        $fileMTime = filemtime($file);
        $datetime = date('Y-m-d H:i:s', $fileMTime);

        // avatarName remains default
    } else {
        // If filename doesn't match expected patterns, skip the file
        continue;
    }

    // Check if the filename already exists
    $filenameCheck = $filename;
    $selectStmt->execute();
    $selectStmt->store_result();

    if ($selectStmt->num_rows > 0) {
        //we are going to update the record anyway, i want to update the date only
        $stmt = $conn->prepare("UPDATE mocap_files SET datetime = ? WHERE filename = ?");
        $stmt->bind_param("ss", $datetime, $filename);
        $stmt->execute();
        $stmt->close();
    }
    else
    {
        // Bind the parameters and execute the INSERT statement
        if (!$insertStmt->execute()) {
            // Log or handle the error as needed
            // For now, we'll throw an exception
            throw new Exception("Execute INSERT failed: " . $insertStmt->error);
        }
        
        // Update captures table file availability if we can match by glos name
        $updateCaptureFileStmt = $conn->prepare("UPDATE captures SET has_fbx = 1, has_glb = 1 WHERE name = ?");
        $updateCaptureFileStmt->bind_param("s", $glos);
        $updateCaptureFileStmt->execute();
        $updateCaptureFileStmt->close();
    }
}

// Close prepared statements
$selectStmt->close();
$insertStmt->close();

$count = shell_exec("find " . escapeshellarg($directory) . " -type f -name '*_0_*.fbx' -daystart -mtime 0 | wc -l");
$response['fbx_count'] = (int)trim($count);
$response['success'] = true;
$response['captured_status'] = $capturedStatus;
$response['capture_id'] = $captureId;

echo json_encode($response);
?>

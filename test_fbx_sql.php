<?php

include('../mysql_config.php');
$conn = new mysqli($servername, $username, $password, $database);

//for now we want to get filelist from /web/gebarenoverleg_media/fbx fbx filelist, from november 1st 2024 on, then get basename and look in form_data for matching glos then update unreal_take

$directory = '/web/gebarenoverleg_media/fbx';
$filelist = array_filter(glob($directory . '/*.{glb}', GLOB_BRACE), function($file) {
    return filemtime($file) >= strtotime('2024-11-01');
});
usort($filelist, function($a, $b) {
    return filemtime($a) <=> filemtime($b);
});

//get last 5 items of filelist
$filelist = array_slice($filelist, -5);
print_r($filelist);

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

    // Simple pattern: e.g., Advocat_GlassesGuyRecord_C_1.glb
    $simplePattern = '/^([A-Za-z0-9\-]+)_([A-Za-z0-9_]+)\.(fbx|glb)$/';
    
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
    }

}

$count = shell_exec("find " . escapeshellarg($directory) . " -type f -name '*_0_*.fbx' -daystart -mtime 0 | wc -l");
$response['fbx_count'] = (int)trim($count);
$response['success'] = true;
echo json_encode($response);

?>
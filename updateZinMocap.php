<?php
header('Content-Type: application/json');

// Include database configuration
require_once 'mysql_config.php';

try {
    // Create connection
    $conn = new mysqli($servername, $username, $password, $database);

    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Set charset to UTF-8
    $conn->set_charset("utf8");

    // Get the broadcast name from POST
    $broadcastName = isset($_POST['broadcast_name']) ? $_POST['broadcast_name'] : '';

    if (empty($broadcastName)) {
        throw new Exception("broadcast_name is required");
    }

    // Update matched_transcriptions table where m_file matches the broadcast name
    // The m_file is stored with extension (e.g., "M20250929_4806.wav")
    // But we receive just the filename without extension, so we need to match with LIKE or add extension
    $m_file_wav = $broadcastName . '.wav';
    $m_file_mp4 = $broadcastName . '.mp4';

    // Update query - set has_mocap = 1 for matching files
    $sql = "
        UPDATE matched_transcriptions
        SET has_mocap = 1
        WHERE (m_file = ? OR m_file = ?)
        AND zOg = 'zin'
        AND added = '1'
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("ss", $m_file_wav, $m_file_mp4);

    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $affected_rows = $stmt->affected_rows;
    $stmt->close();
    $conn->close();

    // Return success response
    echo json_encode(array(
        'success' => true,
        'message' => 'Updated has_mocap for broadcast: ' . $broadcastName,
        'affected_rows' => $affected_rows
    ));

} catch (Exception $e) {
    echo json_encode(array(
        'success' => false,
        'error' => $e->getMessage()
    ));
}
?>

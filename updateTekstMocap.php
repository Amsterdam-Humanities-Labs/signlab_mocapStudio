<?php
header('Content-Type: application/json');

require_once 'mysql_config.php';

try {
    $conn = new mysqli($servername, $username, $password, $database);

    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    $conn->set_charset("utf8");

    $broadcastName = isset($_POST['broadcast_name']) ? $_POST['broadcast_name'] : '';

    if (empty($broadcastName)) {
        throw new Exception("broadcast_name is required");
    }

    $m_file_wav = $broadcastName . '.wav';
    $m_file_mp4 = $broadcastName . '.mp4';

    $sql = "
        UPDATE matched_transcriptions
        SET has_mocap = 1
        WHERE (m_file = ? OR m_file = ?)
        AND zOg = 'tekst'
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

    echo json_encode(array(
        'success' => true,
        'message' => 'Updated has_mocap for tekst broadcast: ' . $broadcastName,
        'affected_rows' => $affected_rows
    ));

} catch (Exception $e) {
    echo json_encode(array(
        'success' => false,
        'error' => $e->getMessage()
    ));
}
?>

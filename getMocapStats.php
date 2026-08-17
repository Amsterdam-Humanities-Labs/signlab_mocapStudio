<?php
header('Content-Type: application/json');
require_once 'mysql_config.php';

try {
    $conn = new mysqli($servername, $username, $password, $database);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    $conn->set_charset("utf8");

    $today = date('Y-m-d');

    // Overall stats
    $result = $conn->query("
        SELECT
            COUNT(*) as total_takes,
            COUNT(DISTINCT COALESCE(broadcast_name, glos_name)) as unique_recordings
        FROM mocap_recording_logs
        WHERE DATE(recorded_at) = '$today'
    ");
    $overall = $result->fetch_assoc();

    // Per-mode breakdown
    $result = $conn->query("
        SELECT
            recording_mode,
            COUNT(*) as takes,
            COUNT(DISTINCT COALESCE(broadcast_name, glos_name)) as unique_recordings
        FROM mocap_recording_logs
        WHERE DATE(recorded_at) = '$today'
        GROUP BY recording_mode
    ");
    $by_mode = [];
    while ($row = $result->fetch_assoc()) {
        $by_mode[$row['recording_mode']] = [
            'takes' => intval($row['takes']),
            'unique' => intval($row['unique_recordings'])
        ];
    }

    // Per-user breakdown
    $result = $conn->query("
        SELECT
            user_id,
            COUNT(*) as takes,
            COUNT(DISTINCT COALESCE(broadcast_name, glos_name)) as unique_recordings
        FROM mocap_recording_logs
        WHERE DATE(recorded_at) = '$today' AND user_id IS NOT NULL
        GROUP BY user_id
    ");
    $by_user = [];
    while ($row = $result->fetch_assoc()) {
        $by_user[$row['user_id']] = [
            'takes' => intval($row['takes']),
            'unique' => intval($row['unique_recordings'])
        ];
    }

    $conn->close();

    $total = intval($overall['total_takes']);
    $unique = intval($overall['unique_recordings']);

    echo json_encode([
        'success' => true,
        'stats' => [
            'total_takes' => $total,
            'unique_recordings' => $unique,
            'retakes' => $total - $unique,
            'by_mode' => $by_mode,
            'by_user' => $by_user
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>

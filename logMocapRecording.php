<?php
header('Content-Type: application/json');
require_once 'mysql_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'POST required']);
    exit;
}

try {
    $conn = new mysqli($servername, $username, $password, $database);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    $conn->set_charset("utf8");

    $recording_mode = $_POST['recording_mode'] ?? 'regular';
    $sentence_id = !empty($_POST['sentence_id']) ? intval($_POST['sentence_id']) : null;
    $broadcast_name = !empty($_POST['broadcast_name']) ? $_POST['broadcast_name'] : null;
    $capture_id = !empty($_POST['capture_id']) ? intval($_POST['capture_id']) : null;
    $glos_name = !empty($_POST['glos_name']) ? $_POST['glos_name'] : null;
    $theme = !empty($_POST['theme']) ? $_POST['theme'] : null;
    $user_id = !empty($_POST['user_id']) ? $_POST['user_id'] : null;
    $take_number = isset($_POST['take_number']) ? intval($_POST['take_number']) : 0;

    $stmt = $conn->prepare("INSERT INTO mocap_recording_logs (recording_mode, sentence_id, broadcast_name, capture_id, glos_name, theme, user_id, take_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sisssssi", $recording_mode, $sentence_id, $broadcast_name, $capture_id, $glos_name, $theme, $user_id, $take_number);
    $stmt->execute();
    $stmt->close();

    // Return today's stats
    $today = date('Y-m-d');
    $stats = $conn->query("
        SELECT
            COUNT(*) as total_takes,
            COUNT(DISTINCT COALESCE(broadcast_name, glos_name)) as unique_recordings,
            recording_mode
        FROM mocap_recording_logs
        WHERE DATE(recorded_at) = '$today'
        GROUP BY recording_mode
    ");

    $total = 0;
    $unique = 0;
    $by_mode = [];
    while ($row = $stats->fetch_assoc()) {
        $total += intval($row['total_takes']);
        $unique += intval($row['unique_recordings']);
        $by_mode[$row['recording_mode']] = [
            'takes' => intval($row['total_takes']),
            'unique' => intval($row['unique_recordings'])
        ];
    }

    $conn->close();

    echo json_encode([
        'success' => true,
        'stats' => [
            'total_takes' => $total,
            'unique_recordings' => $unique,
            'retakes' => $total - $unique,
            'by_mode' => $by_mode
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>

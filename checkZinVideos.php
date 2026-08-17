<?php
header('Content-Type: application/json');
set_time_limit(300);

require_once 'mysql_config.php';

try {
    $conn = new mysqli($servername, $username, $password, $database);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    $conn->set_charset("utf8");

    $sql = "
        SELECT
            s.ID as sentence_id,
            s.zinString,
            MAX(mt.m_file) as m_file
        FROM sentences s
        INNER JOIN matched_transcriptions mt
            ON s.ID = mt.m_transcription
            AND mt.zOg = 'zin'
            AND mt.added = '1'
        WHERE s.status_glos = 'Klaar'
            AND s.status_video = 'Klaar'
            AND (mt.has_mocap IS NULL OR mt.has_mocap != 1)
        GROUP BY s.ID, s.zinString
        ORDER BY s.ID
    ";

    $result = $conn->query($sql);
    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }

    $broken = [];
    $working = 0;
    $no_file = [];
    $total = 0;

    while ($row = $result->fetch_assoc()) {
        $total++;
        $m_file = $row['m_file'];

        if (!$m_file) {
            $no_file[] = [
                'sentence_id' => $row['sentence_id'],
                'zinString' => $row['zinString'],
                'reason' => 'no m_file'
            ];
            continue;
        }

        $broadcast_name = pathinfo($m_file, PATHINFO_FILENAME);
        $video_url = "https://media.signcollect.nl/" . $broadcast_name . ".mp4";

        $ch = curl_init($video_url);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200) {
            $broken[] = [
                'sentence_id' => $row['sentence_id'],
                'zinString' => $row['zinString'],
                'm_file' => $m_file,
                'video_url' => $video_url,
                'http_code' => $httpCode,
                'reason' => $error ?: "HTTP $httpCode"
            ];
        } else {
            $working++;
        }
    }

    $conn->close();

    echo json_encode([
        'success' => true,
        'total_checked' => $total,
        'working' => $working,
        'broken_count' => count($broken),
        'no_file_count' => count($no_file),
        'broken' => $broken,
        'no_file' => $no_file
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>

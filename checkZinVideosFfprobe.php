<?php
header('Content-Type: application/json');
set_time_limit(600);

require_once 'mysql_config.php';

$postDir = '/web/gebarenoverleg_media/studioFilesMini/post/';

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
            AND mt.m_file IS NOT NULL
        GROUP BY s.ID, s.zinString
        ORDER BY s.ID
    ";

    $result = $conn->query($sql);
    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }

    $broken = [];
    $working = 0;
    $missing = [];
    $total = 0;

    while ($row = $result->fetch_assoc()) {
        $total++;
        $broadcast_name = pathinfo($row['m_file'], PATHINFO_FILENAME);
        $mp4_path = $postDir . $broadcast_name . '.mp4';

        if (!file_exists($mp4_path)) {
            $missing[] = [
                'sentence_id' => $row['sentence_id'],
                'zinString' => $row['zinString'],
                'm_file' => $row['m_file'],
                'expected_path' => $mp4_path,
                'reason' => 'file not found'
            ];
            continue;
        }

        $cmd = 'ffprobe -v error -select_streams v:0 -show_entries stream=codec_name,width,height,duration -of json ' . escapeshellarg($mp4_path) . ' 2>&1';
        $output = shell_exec($cmd);
        $probe = json_decode($output, true);

        if (!$probe || empty($probe['streams'])) {
            $broken[] = [
                'sentence_id' => $row['sentence_id'],
                'zinString' => $row['zinString'],
                'm_file' => $row['m_file'],
                'path' => $mp4_path,
                'reason' => 'ffprobe failed - no video stream',
                'ffprobe_output' => $output
            ];
        } else {
            $stream = $probe['streams'][0];
            $codec = $stream['codec_name'] ?? 'unknown';

            // h264 is universally browser-compatible, flag anything else
            if ($codec !== 'h264') {
                $broken[] = [
                    'sentence_id' => $row['sentence_id'],
                    'zinString' => $row['zinString'],
                    'm_file' => $row['m_file'],
                    'path' => $mp4_path,
                    'codec' => $codec,
                    'width' => $stream['width'] ?? null,
                    'height' => $stream['height'] ?? null,
                    'duration' => $stream['duration'] ?? null,
                    'reason' => "non-h264 codec: $codec"
                ];
            } else {
                $working++;
            }
        }
    }

    $conn->close();

    echo json_encode([
        'success' => true,
        'total_checked' => $total,
        'working' => $working,
        'broken_count' => count($broken),
        'missing_count' => count($missing),
        'broken' => $broken,
        'missing' => $missing
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>

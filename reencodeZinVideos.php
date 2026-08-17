<?php
header('Content-Type: application/json');
set_time_limit(3600);

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

    $reencoded = [];
    $skipped = [];
    $failed = [];
    $total = 0;

    while ($row = $result->fetch_assoc()) {
        $total++;
        $broadcast_name = pathinfo($row['m_file'], PATHINFO_FILENAME);
        $mp4_path = $postDir . $broadcast_name . '.mp4';

        if (!file_exists($mp4_path)) {
            $skipped[] = ['sentence_id' => $row['sentence_id'], 'reason' => 'file not found'];
            continue;
        }

        // Check codec
        $probeCmd = 'ffprobe -v error -select_streams v:0 -show_entries stream=codec_name -of csv=p=0 ' . escapeshellarg($mp4_path) . ' 2>&1';
        $codec = trim(shell_exec($probeCmd));

        if ($codec === 'h264') {
            $skipped[] = ['sentence_id' => $row['sentence_id'], 'file' => $broadcast_name . '.mp4', 'reason' => 'already h264'];
            continue;
        }

        // Re-encode: write to temp file, then replace original
        $tmpPath = $mp4_path . '.tmp.mp4';
        $ffmpegCmd = 'ffmpeg -y -i ' . escapeshellarg($mp4_path) . ' -c:v libx264 -preset fast -crf 18 -c:a aac -movflags +faststart ' . escapeshellarg($tmpPath) . ' 2>&1';
        $output = shell_exec($ffmpegCmd);

        if (file_exists($tmpPath) && filesize($tmpPath) > 0) {
            // Replace original with re-encoded file
            rename($tmpPath, $mp4_path);
            $reencoded[] = [
                'sentence_id' => $row['sentence_id'],
                'file' => $broadcast_name . '.mp4'
            ];
        } else {
            // Cleanup failed temp file
            if (file_exists($tmpPath)) unlink($tmpPath);
            $failed[] = [
                'sentence_id' => $row['sentence_id'],
                'file' => $broadcast_name . '.mp4',
                'error' => $output
            ];
        }
    }

    $conn->close();

    echo json_encode([
        'success' => true,
        'total' => $total,
        'reencoded_count' => count($reencoded),
        'skipped_count' => count($skipped),
        'failed_count' => count($failed),
        'reencoded' => $reencoded,
        'skipped' => $skipped,
        'failed' => $failed
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>

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

    // Check if we want a random sentence, all sentences, or just the remaining count
    $random = isset($_GET['random']) && $_GET['random'] == '1';
    $countOnly = isset($_GET['count_remaining']) && $_GET['count_remaining'] == '1';

    // Get excluded IDs if provided
    $excludeIds = [];
    if (isset($_GET['exclude']) && !empty($_GET['exclude'])) {
        // Sanitize the excluded IDs
        $excludeStr = $_GET['exclude'];
        $excludeArray = explode(',', $excludeStr);
        foreach ($excludeArray as $id) {
            $cleanId = intval(trim($id));
            if ($cleanId > 0) {
                $excludeIds[] = $cleanId;
            }
        }
    }

    // Build exclusion clause
    $exclusionClause = "";
    if (!empty($excludeIds)) {
        $exclusionClause = " AND s.ID NOT IN (" . implode(',', $excludeIds) . ")";
    }

    if ($countOnly) {
        // Count all matched_transcription rows needing mocap
        $sql = "
            SELECT COUNT(mt.id) as remaining
            FROM sentences s
            INNER JOIN matched_transcriptions mt
                ON s.ID = mt.m_transcription
                AND mt.zOg = 'zin'
                AND mt.added = '1'
            WHERE s.status_video = 'Klaar'
                AND NOT (mt.has_mocap <=> 1)
        ";
        $result = $conn->query($sql);
        if (!$result) {
            throw new Exception("Query failed: " . $conn->error);
        }
        $row = $result->fetch_assoc();
        $conn->close();
        echo json_encode(array(
            'success' => true,
            'remaining' => intval($row['remaining'])
        ));
        exit;
    }

    if ($random) {
        $sql = "
            SELECT
                s.ID as sentence_id,
                s.zinString,
                mt.m_file
            FROM sentences s
            INNER JOIN matched_transcriptions mt
                ON s.ID = mt.m_transcription
                AND mt.zOg = 'zin'
                AND mt.added = '1'
                AND mt.m_file IS NOT NULL
            WHERE s.status_video = 'Klaar'
                AND NOT (mt.has_mocap <=> 1)
                $exclusionClause
            ORDER BY RAND()
            LIMIT 1
        ";
    } else {
        $sql = "
            SELECT
                s.ID as sentence_id,
                s.zinString,
                mt.m_file,
                mt.has_mocap
            FROM sentences s
            INNER JOIN matched_transcriptions mt
                ON s.ID = mt.m_transcription
                AND mt.zOg = 'zin'
                AND mt.added = '1'
            WHERE s.status_video = 'Klaar'
                AND NOT (mt.has_mocap <=> 1)
            ORDER BY s.ID DESC
        ";
    }

    $result = $conn->query($sql);

    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }

    $zinnen = array();

    while ($row = $result->fetch_assoc()) {
        // Convert m_file (e.g., "M20250929_4806.wav") to video URL and broadcast name
        $m_file = $row['m_file'];
        $broadcast_name = "";
        $video_url = "";

        if ($m_file) {
            // Remove file extension to get broadcast name
            $broadcast_name = pathinfo($m_file, PATHINFO_FILENAME);

            // Convert .wav to .mp4 URL
            $video_url = "https://media.signcollect.nl/" . $broadcast_name . ".mp4";
        }

        $zinnen[] = array(
            'sentence_id' => $row['sentence_id'],
            'zinString' => $row['zinString'],
            'm_file' => $m_file,
            'video_url' => $video_url,
            'broadcast_name' => $broadcast_name
        );
    }

    // Close connection
    $conn->close();

    // Return JSON response
    echo json_encode(array(
        'success' => true,
        'data' => $zinnen,
        'count' => count($zinnen)
    ));

} catch (Exception $e) {
    echo json_encode(array(
        'success' => false,
        'error' => $e->getMessage()
    ));
}
?>

<?php
header('Content-Type: application/json');

require_once 'mysql_config.php';

try {
    $conn = new mysqli($servername, $username, $password, $database);

    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    $conn->set_charset("utf8");

    $countOnly = isset($_GET['count_only']) && $_GET['count_only'] == '1';
    $random = isset($_GET['random']) && $_GET['random'] == '1';

    // Get excluded IDs if provided
    $excludeIds = [];
    if (isset($_GET['exclude']) && !empty($_GET['exclude'])) {
        $excludeStr = $_GET['exclude'];
        $excludeArray = explode(',', $excludeStr);
        foreach ($excludeArray as $id) {
            $cleanId = intval(trim($id));
            if ($cleanId > 0) {
                $excludeIds[] = $cleanId;
            }
        }
    }

    $exclusionClause = "";
    if (!empty($excludeIds)) {
        $exclusionClause = " AND h.id NOT IN (" . implode(',', $excludeIds) . ")";
    }

    if ($countOnly) {
        // Return counts: has_mocap=1, has_mocap IS NULL, and total
        $sql = "
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN mt.has_mocap = 1 THEN 1 ELSE 0 END) as has_mocap_count,
                SUM(CASE WHEN mt.has_mocap IS NULL THEN 1 ELSE 0 END) as no_mocap_count
            FROM hh_index h
            INNER JOIN matched_transcriptions mt
                ON h.id = mt.m_transcription
                AND mt.zOg = 'tekst'
                AND mt.added = '1'
            WHERE h.status_video = 'Klaar'
        ";
        $result = $conn->query($sql);
        if (!$result) {
            throw new Exception("Query failed: " . $conn->error);
        }
        $row = $result->fetch_assoc();
        $conn->close();
        echo json_encode(array(
            'success' => true,
            'total' => intval($row['total']),
            'has_mocap_count' => intval($row['has_mocap_count']),
            'no_mocap_count' => intval($row['no_mocap_count'])
        ));
        exit;
    }

    if ($random) {
        $sql = "
            SELECT
                h.id as sentence_id,
                REPLACE(SUBSTRING_INDEX(h.url, '/', -1), '-', ' ') as zinString,
                mt.m_file,
                mt.has_mocap
            FROM hh_index h
            INNER JOIN matched_transcriptions mt
                ON h.id = mt.m_transcription
                AND mt.zOg = 'tekst'
                AND mt.added = '1'
                AND mt.m_file IS NOT NULL
            WHERE h.status_video = 'Klaar'
                AND NOT (mt.has_mocap <=> 1)
                $exclusionClause
            ORDER BY RAND()
            LIMIT 1
        ";
    } else {
        $sql = "
            SELECT
                h.id as sentence_id,
                REPLACE(SUBSTRING_INDEX(h.url, '/', -1), '-', ' ') as zinString,
                mt.m_file,
                mt.has_mocap
            FROM hh_index h
            INNER JOIN matched_transcriptions mt
                ON h.id = mt.m_transcription
                AND mt.zOg = 'tekst'
                AND mt.added = '1'
            WHERE h.status_video = 'Klaar'
            ORDER BY h.id DESC
        ";
    }

    $result = $conn->query($sql);

    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }

    $teksten = array();

    while ($row = $result->fetch_assoc()) {
        $m_file = $row['m_file'];
        $broadcast_name = "";
        $video_url = "";

        if ($m_file) {
            $broadcast_name = pathinfo($m_file, PATHINFO_FILENAME);
            $video_url = "https://media.signcollect.nl/" . $broadcast_name . ".mp4";
        }

        $teksten[] = array(
            'sentence_id' => $row['sentence_id'],
            'zinString' => urldecode($row['zinString']),
            'm_file' => $m_file,
            'video_url' => $video_url,
            'broadcast_name' => $broadcast_name,
            'has_mocap' => $row['has_mocap']
        );
    }

    $conn->close();

    echo json_encode(array(
        'success' => true,
        'data' => $teksten,
        'count' => count($teksten)
    ));

} catch (Exception $e) {
    echo json_encode(array(
        'success' => false,
        'error' => $e->getMessage()
    ));
}
?>

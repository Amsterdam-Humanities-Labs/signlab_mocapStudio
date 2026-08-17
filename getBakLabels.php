<?php
header('Content-Type: application/json');

require_once 'mysql_config.php';

try {
    $conn = new mysqli($servername, $username, $password, $database);

    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    $conn->set_charset("utf8");

    $action = isset($_GET['action']) ? $_GET['action'] : 'labels';
    $label = isset($_GET['label']) ? $_GET['label'] : '';
    $random = isset($_GET['random']) && $_GET['random'] == '1';
    $countOnly = isset($_GET['count_only']) && $_GET['count_only'] == '1';

    // Get excluded IDs if provided
    $excludeIds = [];
    if (isset($_GET['exclude']) && !empty($_GET['exclude'])) {
        $excludeArray = explode(',', $_GET['exclude']);
        foreach ($excludeArray as $id) {
            $cleanId = intval(trim($id));
            if ($cleanId > 0) {
                $excludeIds[] = $cleanId;
            }
        }
    }

    $exclusionClause = "";
    if (!empty($excludeIds)) {
        $exclusionClause = " AND fd.id NOT IN (" . implode(',', $excludeIds) . ")";
    }

    if ($action === 'labels') {
        // Return all distinct labels
        $sql = "
            SELECT DISTINCT TRIM(BOTH '\"' FROM jt.label) as label
            FROM form_data fd,
            JSON_TABLE(fd.labels, '$[*]' COLUMNS (label VARCHAR(255) PATH '$')) AS jt
            WHERE fd.labels IS NOT NULL AND fd.labels != ''
            ORDER BY label
        ";
        $result = $conn->query($sql);
        if (!$result) {
            throw new Exception("Query failed: " . $conn->error);
        }

        $labels = [];
        while ($row = $result->fetch_assoc()) {
            $labels[] = $row['label'];
        }

        $conn->close();
        echo json_encode(['success' => true, 'data' => $labels]);
        exit;
    }

    if (empty($label)) {
        throw new Exception("label parameter is required");
    }

    // Escape label for use in JSON search
    $escapedLabel = $conn->real_escape_string($label);

    if ($countOnly) {
        $sql = "
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN mt.has_mocap = 1 THEN 1 ELSE 0 END) as has_mocap_count,
                SUM(CASE WHEN mt.has_mocap IS NULL THEN 1 ELSE 0 END) as no_mocap_count
            FROM form_data fd
            INNER JOIN (
                SELECT mt.*, ROW_NUMBER() OVER (PARTITION BY mt.m_transcription ORDER BY mt.date DESC, mt.time DESC, mt.id DESC) as rn
                FROM matched_transcriptions mt
                WHERE mt.zOg = 'labels' AND mt.added = '1'
            ) mt ON fd.id = mt.m_transcription AND mt.rn = 1
            WHERE JSON_CONTAINS(fd.labels, '\"$escapedLabel\"')
        ";
        $result = $conn->query($sql);
        if (!$result) {
            throw new Exception("Query failed: " . $conn->error);
        }
        $row = $result->fetch_assoc();
        $conn->close();
        echo json_encode([
            'success' => true,
            'total' => intval($row['total']),
            'has_mocap_count' => intval($row['has_mocap_count']),
            'no_mocap_count' => intval($row['no_mocap_count'])
        ]);
        exit;
    }

    if ($random) {
        $sql = "
            SELECT
                fd.id as sentence_id,
                fd.glos as zinString,
                mt.m_file,
                mt.has_mocap
            FROM form_data fd
            INNER JOIN (
                SELECT mt.*, ROW_NUMBER() OVER (PARTITION BY mt.m_transcription ORDER BY mt.date DESC, mt.time DESC, mt.id DESC) as rn
                FROM matched_transcriptions mt
                WHERE mt.zOg = 'labels' AND mt.added = '1' AND mt.m_file IS NOT NULL
            ) mt ON fd.id = mt.m_transcription AND mt.rn = 1
            WHERE JSON_CONTAINS(fd.labels, '\"$escapedLabel\"')
                AND NOT (mt.has_mocap <=> 1)
                $exclusionClause
            ORDER BY RAND()
            LIMIT 1
        ";
    } else {
        $sql = "
            SELECT
                fd.id as sentence_id,
                fd.glos as zinString,
                mt.m_file,
                mt.has_mocap
            FROM form_data fd
            INNER JOIN (
                SELECT mt.*, ROW_NUMBER() OVER (PARTITION BY mt.m_transcription ORDER BY mt.date DESC, mt.time DESC, mt.id DESC) as rn
                FROM matched_transcriptions mt
                WHERE mt.zOg = 'labels' AND mt.added = '1'
            ) mt ON fd.id = mt.m_transcription AND mt.rn = 1
            WHERE JSON_CONTAINS(fd.labels, '\"$escapedLabel\"')
            ORDER BY fd.id DESC
        ";
    }

    $result = $conn->query($sql);

    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }

    $items = [];

    while ($row = $result->fetch_assoc()) {
        $m_file = $row['m_file'];
        $broadcast_name = "";
        $video_url = "";

        if ($m_file) {
            $broadcast_name = pathinfo($m_file, PATHINFO_FILENAME);
            $video_url = "https://media.signcollect.nl/" . $broadcast_name . ".mp4";
        }

        $items[] = [
            'sentence_id' => $row['sentence_id'],
            'zinString' => $row['zinString'],
            'm_file' => $m_file,
            'video_url' => $video_url,
            'broadcast_name' => $broadcast_name,
            'has_mocap' => $row['has_mocap']
        ];
    }

    $conn->close();

    echo json_encode([
        'success' => true,
        'data' => $items,
        'count' => count($items)
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>

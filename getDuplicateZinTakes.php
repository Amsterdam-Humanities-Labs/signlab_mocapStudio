<?php
header('Content-Type: application/json');

// Include database configuration
require_once 'mysql_config.php';

try {
    // Create connection
    $conn = new mysqli($servername, $username, $password, $database);

    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    $conn->set_charset("utf8");

    // Find sentences with multiple matched_transcriptions
    $sql = "
        SELECT
            s.ID as sentence_id,
            s.zinString,
            COUNT(*) as match_count,
            GROUP_CONCAT(mt.m_file ORDER BY mt.m_file ASC SEPARATOR ', ') as all_files,
            MIN(mt.m_file) as first_file,
            MAX(mt.m_file) as last_file
        FROM sentences s
        INNER JOIN matched_transcriptions mt
            ON s.ID = mt.m_transcription
            AND mt.zOg = 'zin'
            AND mt.added = '1'
        WHERE s.status_glos = 'Klaar'
            AND mt.m_file IS NOT NULL
            AND (mt.has_mocap IS NULL OR mt.has_mocap = 0)
        GROUP BY s.ID, s.zinString
        HAVING COUNT(*) > 1
        ORDER BY match_count DESC
        LIMIT 10
    ";

    $result = $conn->query($sql);

    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }

    $sentences = array();
    while ($row = $result->fetch_assoc()) {
        $sentences[] = array(
            'sentence_id' => $row['sentence_id'],
            'zinString' => $row['zinString'],
            'match_count' => $row['match_count'],
            'all_files' => $row['all_files'],
            'first_file' => $row['first_file'],
            'last_file' => $row['last_file'],
            'note' => 'OLD code would use FIRST, NEW code uses LAST (highlighted)'
        );
    }

    $conn->close();

    echo json_encode(array(
        'success' => true,
        'message' => 'Sentences with multiple matched_transcriptions (showing which file is selected)',
        'data' => $sentences,
        'count' => count($sentences)
    ), JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode(array(
        'success' => false,
        'error' => $e->getMessage()
    ));
}
?>

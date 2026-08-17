<?php
header('Content-Type: application/json');

// Read topics from topics.json
$json = file_get_contents('topics.json');
$topics = json_decode($json, true);

// Output the JSON
echo json_encode($topics);
?>

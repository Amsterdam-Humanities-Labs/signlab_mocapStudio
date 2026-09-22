<?php
header('Content-Type: application/json');

// Define the directory where your video files are stored
$videoDirectory = 'lsc_videos/'; // Update this path accordingly

// Initialize an array to hold video file names
$videoFiles = [];

// Open the directory
if (is_dir($videoDirectory)) {
    if ($dh = opendir($videoDirectory)) {
        while (($file = readdir($dh)) !== false) {
            // Check for .mp4 files
            if (in_array(pathinfo($file, PATHINFO_EXTENSION), ['mp4', 'mov'])) {
                $videoFiles[] = $file;
            }
        }
        closedir($dh);
    }
} else {
    echo json_encode(['error' => 'Directory not found.']);
    exit;
}

// Output the JSON
echo json_encode($videoFiles);
?>

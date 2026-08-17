<?php
header('Content-Type: application/json');

// Define the directory where your image files are stored
$imageDirectory = 'meta_images/'; // Update this path accordingly

// Initialize an array to hold image file names
$imageFiles = [];

// Open the directory
if (is_dir($imageDirectory)) {
    if ($dh = opendir($imageDirectory)) {
        while (($file = readdir($dh)) !== false) {
            // Check for image files (e.g., .jpg, .png, .gif)
            if (in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif'])) {
                $imageFiles[] = $file;
            }
        }
        closedir($dh);
    }
} else {
    echo json_encode(['error' => 'Image directory not found.']);
    exit;
}

// Output the JSON
echo json_encode($imageFiles);
?>

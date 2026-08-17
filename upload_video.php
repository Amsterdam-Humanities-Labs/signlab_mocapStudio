<?php
// Set the base directory for storing videos
$baseDir = '../gebarenoverleg_media/mocap_video/';

// Define allowed file extensions
$allowedExtensions = ['mp4', 'mov', 'avi'];

// Function to send JSON responses
function sendResponse($status, $message) {
    header('Content-Type: application/json');
    echo json_encode(['status' => $status, 'message' => $message]);
    exit;
}

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if 'camera', 'filename', and the file are set
    if (isset($_POST['camera'], $_POST['filename']) && isset($_FILES['video'])) {
        // Retrieve and sanitize POST data
        $camera = trim($_POST['camera']);
        $filename = trim($_POST['filename']);
        $file = $_FILES['video'];

        // Validate 'camera' (allow only letters, numbers, underscores, and hyphens)
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $camera)) {
            sendResponse('error', 'Invalid camera name. Only letters, numbers, underscores, and hyphens are allowed.');
        }

        // Validate 'filename' (allow only letters, numbers, underscores, and hyphens)
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $filename)) {
            sendResponse('error', 'Invalid filename. Only letters, numbers, underscores, and hyphens are allowed.');
        }

        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            // Handle different upload errors
            $uploadErrors = [
                UPLOAD_ERR_INI_SIZE   => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
                UPLOAD_ERR_FORM_SIZE  => 'The uploaded file exceeds the MAX_FILE_SIZE directive specified in the HTML form.',
                UPLOAD_ERR_PARTIAL    => 'The uploaded file was only partially uploaded.',
                UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the file upload.',
            ];
            $errorMessage = isset($uploadErrors[$file['error']]) ? $uploadErrors[$file['error']] : 'Unknown upload error.';
            sendResponse('error', 'File upload error: ' . $errorMessage);
        }

        // Extract the file extension
        $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        // Validate the file extension
        if (!in_array($fileExt, $allowedExtensions)) {
            sendResponse('error', 'Invalid file type. Only MP4, MOV, and AVI files are allowed.');
        }

        // Construct the target directory path
        $targetDir = $baseDir . $camera . '/';

        // Create the target directory if it doesn't exist
        if (!is_dir($targetDir)) {
            if (!mkdir($targetDir, 0755, true)) {
                sendResponse('error', 'Failed to create target directory.');
            }
        }

        // Sanitize the filename to prevent directory traversal
        $safeFilename = basename($filename);

        // Construct the full path for the uploaded file
        $targetFile = $targetDir . $safeFilename . '.' . $fileExt;

        // Check if a file with the same name already exists
        if (file_exists($targetFile)) {
            sendResponse('error', 'A file with the same name already exists.');
        }

        // Move the uploaded file to the target directory
        if (move_uploaded_file($file['tmp_name'], $targetFile)) {
            sendResponse('success', 'File uploaded successfully.');
        } else {
            sendResponse('error', 'Failed to move the uploaded file.');
        }
    } else {
        sendResponse('error', 'Missing required POST data.');
    }
} else {
    sendResponse('error', 'Invalid request method. Please use POST.');
}
?>

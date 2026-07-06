<?php
require_once dirname(__DIR__, 3) . '/conn.php';

try {
    $conn = scaims_db_mysqli();
} catch (RuntimeException $e) {
    die($e->getMessage());
}

// In create_post.php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $content = htmlspecialchars($_POST['post_content']);
    $image_paths = array();

    if (isset($_FILES['post_images'])) {
        $total = count($_FILES['post_images']['name']);
        
        // Allowed file types
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        
        for ($i = 0; $i < $total; $i++) {
            // Debugging: Log the file name and size
            error_log("Processing file: " . $_FILES['post_images']['name'][$i] . " Size: " . $_FILES['post_images']['size'][$i]);
    
            if ($_FILES['post_images']['error'][$i] == UPLOAD_ERR_OK) {
                // Check file type
                $file_type = mime_content_type($_FILES['post_images']['tmp_name'][$i]);
                if (!in_array($file_type, $allowed_types)) {
                    echo "Error: File type not allowed for " . $_FILES['post_images']['name'][$i] . "<br>";
                    error_log("File type not allowed for " . $_FILES['post_images']['name'][$i]);
                    continue; // Skip this file
                }
    
                // Create uploads directory if it doesn't exist
                $target_dir = "../../../uploads/posts/"; 
                if (!file_exists($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }
                
                $temp_name = $_FILES["post_images"]["tmp_name"][$i];
                $name = basename($_FILES["post_images"]["name"][$i]);
                $target_file = $target_dir . time() . '_' . $name;
                
                if (move_uploaded_file($temp_name, $target_file)) {
                    // Store the relative path
                    $relative_path = "uploads/posts/" . time() . '_' . $name;
                    $image_paths[] = $relative_path;
                    error_log("File uploaded successfully: " . $relative_path);
                } else {
                    echo "Error: Could not move uploaded file " . $_FILES['post_images']['name'][$i] . "<br>";
                    error_log("Error moving uploaded file: " . $_FILES['post_images']['name'][$i]);
                }
            } else {
                // Log the error code for debugging
                error_log("Error uploading file " . $_FILES['post_images']['name'][$i] . ": " . $_FILES['post_images']['error'][$i]);
                // Handle specific upload errors
                if ($_FILES['post_images']['error'][$i] == UPLOAD_ERR_INI_SIZE) {
                    echo "Error: File size exceeds the maximum limit for " . $_FILES['post_images']['name'][$i] . "<br>";
                    error_log("File size exceeds the maximum limit for " . $_FILES['post_images']['name'][$i]);
                }
            }
        }
    }

    // Convert image paths array to JSON for storage
    $image_paths_json = !empty($image_paths) ? json_encode($image_paths) : null;

    // Insert post into database
    $sql = "INSERT INTO posts (content, image_paths, created_at) VALUES (?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $content, $image_paths_json);
    
    if ($stmt->execute()) {
        header("Location: post.php");
        exit();
        error_log("Post created successfully");
    } else {
        echo "Error: " . $sql . "<br>" . $conn->error;
        error_log("Error creating post: " . $sql . " - " . $conn->error);
    }

    $stmt->close();
}

$conn->close();

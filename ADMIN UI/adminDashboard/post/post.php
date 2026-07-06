<?php
require_once dirname(__DIR__, 3) . '/conn.php';

try {
    $conn = scaims_db_mysqli();
} catch (RuntimeException $e) {
    die($e->getMessage());
}

// Fetch posts
$sql = "SELECT * FROM posts ORDER BY created_at DESC";
$result = $conn->query($sql);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Post Updates</title>
    <link rel="stylesheet" href="p.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&family=Poppins:wght@400;600&display=swap" rel="stylesheet">
</head>
<body>
<div class="container">
        <?php include '../../shared/admin-sidebar.php'; ?>

        <div class="main-content">
    <h1>POST UPDATES</h1>
    
    <!-- Post Creation Form -->
    <!-- In the post form section of post.php -->
<div class="post-form">
    <form action="create_post.php" method="POST" enctype="multipart/form-data">
        <textarea 
            name="post_content" 
            placeholder="What would you like to share with the farmers today?"
            rows="4"
            required
        ></textarea>
        
        <div class="post-actions">
            <div class="image-upload">
                <label for="image-input">
                    <img src="../../assets/upload.png" alt="Upload Image" class="upload-icon">
                    Add Images
                </label>
                <input 
                    type="file" 
                    id="image-input" 
                    name="post_images[]" 
                    accept="image/*"
                    multiple
                >
            </div>
            <button type="submit" class="post-button">Post Update</button>
        </div>
    </form>
</div>

    <!-- Posts Display Section -->
<!-- Posts Display Section -->
<div class="posts-container">
    <?php
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            echo "<div class='post'>";
            // Convert line breaks to <br> tags only when displaying
            echo "<div class='post-content'>" . nl2br(html_entity_decode($row['content'], ENT_QUOTES, 'UTF-8')) . "</div>";
            // Display images
            if ($row['image_paths']) {
                $images = json_decode($row['image_paths']);
                if($images) {
                    echo "<div class='post-images'>";
                    foreach($images as $image) {
                        echo "<div class='image-container'>"; // Add container for each image
                        echo "<img src='../../../" . htmlspecialchars($image) . "' alt='Post image'>";
                        echo "</div>";
                    }
                    echo "</div>";
                }
            }
            
            echo "<div class='post-footer'>";
            echo "<small>Posted on: " . $row['created_at'] . "</small>";
            echo "<button onclick='deletePost(" . $row['id'] . ")' class='delete-btn'>Delete</button>";
            echo "</div>";
            echo "</div>";
        }
    } else {
        echo "<p>No posts yet.</p>";
    }
    ?>
</div>

<script>
document.getElementById('image-input').addEventListener('change', function(event) {
    var file = event.target.files[0];
    var reader = new FileReader();
    
    reader.onload = function(e) {
        var img = document.createElement('img');
        img.src = e.target.result;
        img.style.maxWidth = '200px';
        img.style.maxHeight = '200px';
        
        var previewContainer = document.createElement('div');
        previewContainer.id = 'image-preview';
        previewContainer.appendChild(img);
        
        var existingPreview = document.getElementById('image-preview');
        if (existingPreview) {
            existingPreview.parentNode.removeChild(existingPreview);
        }
        
        document.querySelector('.post-actions').appendChild(previewContainer);
    }
    
    reader.readAsDataURL(file);
});

function deletePost(postId) {
    if(confirm('Are you sure you want to delete this post?')) {
        fetch('delete_post.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'post_id=' + postId
        })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                location.reload();
            }
        });
    }
}

// Image preview
document.getElementById('image-input').addEventListener('change', function(event) {
    const previewContainer = document.getElementById('image-preview') || document.createElement('div');
    previewContainer.id = 'image-preview';
    previewContainer.innerHTML = '';
    
    Array.from(event.target.files).forEach(file => {
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = document.createElement('img');
            img.src = e.target.result;
            img.style.maxWidth = '200px';
            img.style.maxHeight = '200px';
            img.style.margin = '5px';
            previewContainer.appendChild(img);
        }
        reader.readAsDataURL(file);
    });
    
    document.querySelector('.post-actions').appendChild(previewContainer);
});

// Add this to your existing JavaScript
document.getElementById('image-input').addEventListener('change', function(event) {
    const previewContainer = document.getElementById('image-preview') || document.createElement('div');
    previewContainer.id = 'image-preview';
    previewContainer.innerHTML = '';
    previewContainer.style.cssText = `
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
        gap: 10px;
        margin-top: 10px;
    `;
    
    Array.from(event.target.files).forEach(file => {
        const reader = new FileReader();
        reader.onload = function(e) {
            const previewDiv = document.createElement('div');
            previewDiv.style.cssText = `
                position: relative;
                padding-bottom: 100%;
                overflow: hidden;
            `;
            
            const img = document.createElement('img');
            img.src = e.target.result;
            img.style.cssText = `
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                object-fit: contain;
                border-radius: 4px;
            `;
            
            previewDiv.appendChild(img);
            previewContainer.appendChild(previewDiv);
        }
        reader.readAsDataURL(file);
    });
    
    document.querySelector('.post-actions').appendChild(previewContainer);
});

</script>

</body>
</html>

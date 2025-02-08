<?php
session_start();

// Initialize data files if they don't exist
$data_files = [
    'users' => 'data/users.json',
    'posts' => 'data/posts.json',
    'comments' => 'data/comments.json'
];

// Create data directory if it doesn't exist
if (!file_exists('data')) {
    mkdir('data', 0777, true);
}

// Initialize JSON files if they don't exist
foreach ($data_files as $file) {
    if (!file_exists($file)) {
        file_put_contents($file, json_encode([]));
    }
}

// Helper functions for data management
function readJSON($file) {
    return json_decode(file_get_contents($file), true) ?? [];
}

function writeJSON($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

function generateId($data) {
    return empty($data) ? 1 : max(array_column($data, 'id')) + 1;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'register':
                $users = readJSON($data_files['users']);

                // Handle profile picture upload
                $profile_pic_path = '';
                if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === 0) {
                    $target_dir = "uploads/profile_pics/";
                    if (!file_exists($target_dir)) {
                        mkdir($target_dir, 0777, true);
                    }
                    $profile_pic_path = $target_dir . time() . '_' . basename($_FILES['profile_pic']['name']);
                    move_uploaded_file($_FILES['profile_pic']['tmp_name'], $profile_pic_path);
                }

                // Check if username exists
                if (!array_filter($users, fn($u) => $u['username'] === $_POST['username'])) {
                    $users[] = [
                        'id' => generateId($users),
                        'username' => $_POST['username'],
                        'password' => password_hash($_POST['password'], PASSWORD_DEFAULT),
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                    writeJSON($data_files['users'], $users);
                    header('Location: ?page=login');
                    exit;
                }
                break;

                case 'update_profile_pic':
                    if (isset($_SESSION['user_id'])) {
                        $users = readJSON($data_files['users']);
                        $user_key = array_search($_SESSION['user_id'], array_column($users, 'id'));
                        
                        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === 0) {
                            $target_dir = "uploads/profile_pics/";
                            if (!file_exists($target_dir)) {
                                mkdir($target_dir, 0777, true);
                            }
                            $profile_pic_path = $target_dir . time() . '_' . basename($_FILES['profile_pic']['name']);
                            move_uploaded_file($_FILES['profile_pic']['tmp_name'], $profile_pic_path);
                            
                            // Remove old profile picture if exists
                            if (!empty($users[$user_key]['profile_pic']) && file_exists($users[$user_key]['profile_pic'])) {
                                unlink($users[$user_key]['profile_pic']);
                            }
                            
                            $users[$user_key]['profile_pic'] = $profile_pic_path;
                            writeJSON($data_files['users'], $users);
                        }
                    }
                    break;
                    
                case 'search_users':
                    if (isset($_POST['search_query'])) {
                        $users = readJSON($data_files['users']);
                        $search_results = array_filter($users, function($user) {
                            return stripos($user['username'], $_POST['search_query']) !== false;
                        });
                        $_SESSION['search_results'] = $search_results;
                    }
                    break;
                
            case 'login':
                $users = readJSON($data_files['users']);
                $user = array_filter($users, fn($u) => $u['username'] === $_POST['username']);
                if ($user) {
                    $user = reset($user);
                    if (password_verify($_POST['password'], $user['password'])) {
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                    }
                }
                break;
                
            case 'logout':
                session_destroy();
                header('Location: ?page=login');
                exit;
                
            case 'create_post':
                if (isset($_SESSION['user_id'])) {
                    $posts = readJSON($data_files['posts']);
                    $image_path = '';
                    
                    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
                        $target_dir = "uploads/";
                        if (!file_exists($target_dir)) {
                            mkdir($target_dir, 0777, true);
                        }
                        $image_path = $target_dir . time() . '_' . basename($_FILES['image']['name']);
                        move_uploaded_file($_FILES['image']['tmp_name'], $image_path);
                    }
                    
                    $posts[] = [
                        'id' => generateId($posts),
                        'user_id' => $_SESSION['user_id'],
                        'content' => $_POST['content'],
                        'image_path' => $image_path,
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                    writeJSON($data_files['posts'], $posts);
                }
                break;
                
            case 'delete_post':
                if (isset($_SESSION['user_id'])) {
                    $posts = readJSON($data_files['posts']);
                    $comments = readJSON($data_files['comments']);
                    
                    // Remove post
                    $posts = array_filter($posts, function($post) {
                        return !($post['id'] == $_POST['post_id'] && $post['user_id'] == $_SESSION['user_id']);
                    });
                    
                    // Remove associated comments
                    $comments = array_filter($comments, function($comment) {
                        return $comment['post_id'] != $_POST['post_id'];
                    });
                    
                    writeJSON($data_files['posts'], array_values($posts));
                    writeJSON($data_files['comments'], array_values($comments));
                }
                break;
                
            case 'add_comment':
                if (isset($_SESSION['user_id'])) {
                    $comments = readJSON($data_files['comments']);
                    $comments[] = [
                        'id' => generateId($comments),
                        'post_id' => $_POST['post_id'],
                        'user_id' => $_SESSION['user_id'],
                        'content' => $_POST['content'],
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                    writeJSON($data_files['comments'], $comments);
                }
                break;
        }
    }
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}

// HTML header
?>
<!DOCTYPE html>
<html>
<head>
    <title>E-Merge</title>
    <link rel="icon" type="image" href="/images/favicon.ico">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
         :root {
    /* Teal Color Palette */
    --primary-light: #4ECDC4;    /* Teal blue */
    --primary-dark: #45B7AA;     /* Slightly darker teal */
    --secondary-light: #87CEEB;  /* Light blue */
    --secondary-dark: #5F9EA0;   /* Muted teal blue */
    --background-light: #F0F4F8; /* Light gray background */
    --background-dark: #1A2634;  /* Dark background */
    --text-light: #333;          /* Dark text for light mode */
    --text-dark: #E0E0E0;        /* Light text for dark mode */
    --card-bg-light: #FFFFFF;    /* White card background */
    --card-bg-dark: #2C3E50;     /* Dark card background */
    
    /* Default to light mode */
    --bg: var(--background-light);
    --text: var(--text-light);
    --card-bg: var(--card-bg-light);
    --primary: var(--primary-light);
    --secondary: var(--secondary-light);
    --border-color: rgba(0, 0, 0, 0.1);
}

/* Dark mode styles */
body.dark-mode {
    --bg: var(--background-dark);
    --text: var(--text-dark);
    --card-bg: var(--card-bg-dark);
    --primary: var(--primary-dark);
    --secondary: var(--secondary-dark);
    --border-color: rgba(255, 255, 255, 0.1);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    transition: background-color 0.3s, color 0.3s;
}

body {
    font-family: 'SF Pro Display', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    background-color: var(--bg);
    color: var(--text);
    line-height: 1.6;
}

/* Mode Toggle */
/* Mode Toggle - Desktop */
.mode-toggle {
    position: fixed;
    top: 20px;
    right: 20px;
    background: var(--card-bg);
    border-radius: 50%;
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    border: 1px solid var(--border-color);
    z-index: 1001;
}

.mode-toggle i {
    color: var(--primary);
    font-size: 1.2rem;
}

/* Mode Toggle - Mobile */
@media (max-width: 768px) {
    .mode-toggle {
        position: fixed;
        top: auto;
        bottom: 20px;
        right: 20px;
        width: 40px;
        height: 40px;
    }
    
    .mode-toggle i {
        font-size: 1rem;
    }
}

/* Additional padding for content to prevent overlap with fixed toggle */
@media (max-width: 768px) {
    .container {
        padding-bottom: 80px;
    }
}

/* Typography */
h1, h2, h3 {
    font-weight: 800;
    background: linear-gradient(45deg, var(--primary), var(--secondary));
    -webkit-background-clip: text;
    background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 1rem;
}

/* Navigation */
nav {
    background-color: var(--card-bg);
    backdrop-filter: blur(10px);
    padding: 1rem;
    position: fixed;
    top: 0;
    width: 100%;
    z-index: 1000;
    border-bottom: 1px solid var(--border-color);
}

.nav-container {
    max-width: 1200px;
    margin: 0 auto;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0 20px;
}

.nav-brand {
    font-size: 1.8rem;
    font-weight: 800;
    background: linear-gradient(45deg, var(--primary), var(--secondary));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    text-decoration: none;
    position: relative;
}

.nav-links {
    display: flex;
    gap: 2rem;
    align-items: center;
}

.nav-links a {
    color: var(--text);
    text-decoration: none;
    font-weight: 500;
    transition: color 0.3s ease;
    position: relative;
}

.nav-links a:hover {
    color: var(--primary);
}

.nav-links a::after {
    content: '';
    position: absolute;
    width: 0;
    height: 2px;
    bottom: -4px;
    left: 0;
    background: linear-gradient(45deg, var(--primary), var(--secondary));
    transition: width 0.3s ease;
}

.nav-links a:hover::after {
    width: 100%;
}

/* Main Content */
.container {
    max-width: 1200px;
    margin: 100px auto 0;
    padding: 20px;
}

/* Cards and Forms */
.auth-container, .create-post, .post {
    background: var(--card-bg);
    border-radius: 24px;
    padding: 2rem;
    margin-bottom: 2rem;
    border: 1px solid var(--border-color);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.auth-container:hover, .create-post:hover, .post:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 48px rgba(0, 0, 0, 0.2);
}

/* Forms */
input[type="text"],
input[type="password"],
textarea {
    width: 100%;
    padding: 1rem;
    background: var(--bg);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    color: var(--text);
    font-size: 1rem;
    transition: all 0.3s ease;
}

input[type="text"]:focus,
input[type="password"]:focus,
textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 2px rgba(78, 205, 196, 0.2);
}

button {
    background: linear-gradient(45deg, var(--primary), var(--secondary));
    color: white;
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 12px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

button:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(78, 205, 196, 0.3);
}

button::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(45deg, var(--secondary), var(--primary));
    opacity: 0;
    transition: opacity 0.3s ease;
}

button:hover::after {
    opacity: 1;
}

/* Posts */
.post-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.post-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: linear-gradient(45deg, var(--primary), var(--secondary));
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 1.2rem;
}

.post-content {
    margin: 1.5rem 0;
    font-size: 1.1rem;
}

.post img {
    border-radius: 16px;
    margin: 1rem 0;
    object-fit: cover;
}

/* Comments */
.comments-section {
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid var(--border-color);
}

.comment {
    background: var(--bg);
    padding: 1rem;
    border-radius: 12px;
    margin: 0.5rem 0;
    border: 1px solid var(--border-color);
}

.comment-form {
    display: flex;
    gap: 1rem;
    margin-top: 1rem;
}

/* Profile */
.profile-header {
    text-align: center;
    padding: 3rem 2rem;
    background: var(--card-bg);
    border-radius: 24px;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
    border: 1px solid var(--border-color);
}

.profile-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(45deg, var(--primary), var(--secondary));
    opacity: 0.1;
    z-index: 0;
}

.profile-avatar {
    width: 150px;
    height: 150px;
    margin: 0 auto 1.5rem;
    border-radius: 50%;
    background: linear-gradient(45deg, var(--primary), var(--secondary));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3rem;
    color: white;
    position: relative;
    z-index: 1;
    background-size: cover;
    background-position: center;
}

/* Custom Scrollbar */
::-webkit-scrollbar {
    width: 8px;
}

::-webkit-scrollbar-track {
    background: var(--bg);
}

::-webkit-scrollbar-thumb {
    background: linear-gradient(var(--primary), var(--secondary));
    border-radius: 4px;
}

/* Existing root variables and dark mode styles remain the same */

/* Base responsive adjustments */
html {
    font-size: 16px;
}

@media (max-width: 768px) {
    html {
        font-size: 14px;
    }
}

@media (max-width: 768px) {
    .mode-toggle {
        position: fixed;
        top: auto;
        bottom: 20px;
        left: 20px; /* Changed from right to left */
        width: 40px;
        height: 40px;
    }

    .nav-container {
        display: grid;
        grid-template-columns: auto 1fr auto;
        align-items: center;
        gap: 2rem;
        padding: 0 0.5rem;
    }

    .nav-brand {
        grid-column: 2;
        text-align: center;
        margin: 0 auto;
    }

    .nav-links {
        grid-column: 3;
        justify-content: flex-end;
    }

}

/* Improved Mobile Container */
@media (max-width: 768px) {
    .container {
        margin: 70px auto 0;
        padding: 10px;
        width: 100%;
    }
    
    .auth-container {
        width: 95%;
        max-width: none;
        margin: 80px auto 20px;
        padding: 1.25rem;
    }
}

/* Enhanced Mobile Forms */
@media (max-width: 768px) {
    input[type="text"],
    input[type="password"],
    textarea {
        padding: 0.75rem;
        font-size: 16px; /* Prevents iOS zoom on focus */
    }
    
    button {
        padding: 0.75rem 1rem;
        width: 100%;
        font-size: 1rem;
        min-height: 44px;
    }
    
    .comment-form {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .comment-form button {
        width: 100%;
    }
}

/* Improved Mobile Posts */
@media (max-width: 768px) {
    .create-post, .post {
        padding: 1rem;
        margin-bottom: 1rem;
        border-radius: 12px;
    }
    
    .post-header {
        gap: 0.75rem;
        margin-bottom: 1rem;
    }
    
    .post-avatar {
        width: 40px;
        height: 40px;
        font-size: 1rem;
    }
    
    .post-content {
        margin: 1rem 0;
        font-size: 1rem;
    }
    
    .post img {
        border-radius: 8px;
        margin: 0.5rem 0;
        max-height: 300px;
    }
}

/* Better Mobile Comments */
@media (max-width: 768px) {
    .comments-section {
        margin-top: 1rem;
        padding-top: 1rem;
    }
    
    .comment {
        padding: 0.75rem;
        border-radius: 8px;
        margin: 0.5rem 0;
        font-size: 0.95rem;
    }
}

/* Improved Mobile Profile */
@media (max-width: 768px) {
    .profile-header {
        padding: 2rem 1rem;
        border-radius: 12px;
    }
    
    .profile-avatar {
        width: 100px;
        height: 100px;
        font-size: 2rem;
        margin: 0 auto 1rem;
    }
}

/* Small Screen Optimizations */
@media (max-width: 480px) {
    .container {
        padding: 8px;
    }
    
    .nav-brand {
        font-size: 1.2rem;
    }
    
    .nav-links {
        gap: 0.5rem;
    }
    
    .post-content {
        font-size: 0.95rem;
    }
    
    h1 { font-size: 1.5rem; }
    h2 { font-size: 1.25rem; }
    h3 { font-size: 1.1rem; }
}

/* Fix button text visibility on hover */
button {
    position: relative;
    z-index: 1;
}

button::after {
    z-index: -1;
}

/* Fix form layout on mobile */
.form-group {
    margin-bottom: 1rem;
    width: 100%;
}

/* Prevent horizontal scroll */
body {
    overflow-x: hidden;
    width: 100%;
}

/* Better touch scrolling */
.container, body {
    -webkit-overflow-scrolling: touch;
}

/* Improved file input styling for mobile */
input[type="file"] {
    width: 100%;
    padding: 0.5rem;
    margin-bottom: 1rem;
}

/* Fix content overflow */
img, video, iframe {
    max-width: 100%;
    height: auto;
}

/* Animations */
@keyframes gradient {
    0% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
}
    </style>
    <script>
        // Dark mode toggle
        document.addEventListener('DOMContentLoaded', () => {
            const modeToggle = document.querySelector('.mode-toggle');
            const body = document.body;

            // Check for saved preference
            if (localStorage.getItem('mode') === 'dark') {
                body.classList.add('dark-mode');
            }

            modeToggle.addEventListener('click', () => {
                body.classList.toggle('dark-mode');
                localStorage.setItem('mode', 
                    body.classList.contains('dark-mode') ? 'dark' : 'light'
                );
            });
        });
    </script>
</head>

<body>
<div id="imageViewer" class="image-viewer">
    <div class="image-viewer-content">
        <span class="close-button">&times;</span>
        <button class="nav-button prev-button">
            <i class="fas fa-chevron-left"></i>
        </button>
        <img id="fullScreenImage" src="" alt="Full screen image">
        <button class="nav-button next-button">
            <i class="fas fa-chevron-right"></i>
        </button>
        <div class="comment-counter">
            <i class="fas fa-comment"></i>
            <span id="commentCount">0</span> comments
        </div>
    </div>
</div>

<style>
.image-viewer {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(218, 213, 213, 0.4);
    z-index: 1000;
}

.image-viewer-content {
    position: relative;
    width: 100%;
    height: 100%;
    display: flex;
    justify-content: center;
    align-items: center;
}

.image-viewer-content img {
    max-width: 90%;
    max-height: 90%;
    object-fit: contain;
    pointer-events: none;
}

.close-button {
    position: absolute;
    top: 15px;
    right: 35px;
    color:rgb(0, 0, 0);
    font-size: 40px;
    font-weight: bold;
    cursor: pointer;
    width: 20px;
    height: 20px;
}

.nav-button {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(255, 255, 255, 0.1);
    border: none;
    color: white;
    padding: 20px;
    cursor: pointer;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background-color 0.3s;
    width: 20px;
    height: 20px;
}

.nav-button:hover {
    background: rgba(255, 255, 255, 0.2);
    width: 20px;
    height: 20px;
}

.prev-button {
    left: 20px;
    width: 20px;
    height: 20px;
}

.next-button {
    right: 20px;
    width: 20px;
    height: 20px;
}

.nav-button i {
    font-size: 24px;
}

.comment-counter {
    position: absolute;
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%);
    background-color: rgba(0, 0, 0, 0.7);
    color: white;
    padding: 10px 20px;
    border-radius: 20px;
    font-size: 16px;
}

.comment-counter i {
    margin-right: 8px;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const imageViewer = document.getElementById('imageViewer');
    const fullScreenImage = document.getElementById('fullScreenImage');
    const commentCount = document.getElementById('commentCount');
    const closeButton = document.querySelector('.close-button');
    const prevButton = document.querySelector('.prev-button');
    const nextButton = document.querySelector('.next-button');
    
    let currentImageIndex = 0;
    let allImages = [];

    // Add click event to all post images
    document.querySelectorAll('.post-content img').forEach((img, index) => {
        img.style.cursor = 'pointer';
        img.addEventListener('click', function() {
            allImages = Array.from(document.querySelectorAll('.post-content img'));
            currentImageIndex = index;
            showImage(currentImageIndex);
        });
    });

    function showImage(index) {
        const img = allImages[index];
        const post = img.closest('.post');
        const comments = post.querySelectorAll('.comment').length;
        
        fullScreenImage.src = img.src;
        commentCount.textContent = comments;
        imageViewer.style.display = 'block';
        
        prevButton.style.display = index === 0 ? 'none' : 'flex';
        nextButton.style.display = index === allImages.length - 1 ? 'none' : 'flex';
    }

    // Navigation handlers
    prevButton.addEventListener('click', function(e) {
        e.stopPropagation();
        if (currentImageIndex > 0) {
            currentImageIndex--;
            showImage(currentImageIndex);
        }
    });

    nextButton.addEventListener('click', function(e) {
        e.stopPropagation();
        if (currentImageIndex < allImages.length - 1) {
            currentImageIndex++;
            showImage(currentImageIndex);
        }
    });

    // Close on background click
    imageViewer.addEventListener('click', function(e) {
        imageViewer.style.display = 'none';
    });

    // Prevent clicks on controls from closing
    document.querySelector('.image-viewer-content').addEventListener('click', function(e) {
        if (e.target.closest('.nav-button') || e.target.closest('.close-button') || e.target.closest('.comment-counter')) {
            e.stopPropagation();
        }
    });

    // Keyboard navigation
    document.addEventListener('keydown', function(e) {
        if (imageViewer.style.display === 'block') {
            if (e.key === 'ArrowLeft' && currentImageIndex > 0) {
                currentImageIndex--;
                showImage(currentImageIndex);
            } else if (e.key === 'ArrowRight' && currentImageIndex < allImages.length - 1) {
                currentImageIndex++;
                showImage(currentImageIndex);
            } else if (e.key === 'Escape') {
                imageViewer.style.display = 'none';
            }
        }
    });

    // Close button handler
    closeButton.addEventListener('click', function(e) {
        e.stopPropagation();
        imageViewer.style.display = 'none';
    });
});
</script>
    <nav>
        <div class="nav-container">
        <div class="mode-toggle">
        <i class="fas fa-adjust"></i>
    </div>
            <a href="?page=home" class="nav-brand">E-Merge</a>
            <div class="nav-links">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="?page=home"><i class="fas fa-home"></i></a>
                    <a href="?page=profile"><i class="fas fa-user"></i></a>
                    <form method="post" style="display: inline">
                        <input type="hidden" name="action" value="logout">
                        <button type="submit"><i class="fas fa-sign-out-alt"></i></button>
                    </form>
                <?php else: ?>
                    <a href="?page=login">Login</a>
                    <a href="?page=register">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="container">
    <?php
    $page = $_GET['page'] ?? 'home';
    switch ($page) {
        case 'register':
            if (!isset($_SESSION['user_id'])) {
                ?>
                <div class="auth-container">
                    <h2>Create Account</h2>
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="register">
                        <div class="form-group">
                            <input type="text" name="username" placeholder="Username" required>
                        </div>
                        <div class="form-group">
                            <input type="password" name="password" placeholder="Password" required>
                        </div>
                        <div class="form-group">
                            <input type="file" name="profile_pic" accept="image/*">
                        </div>
                        <button type="submit">Register</button>
                    </form>
                </div>
                <?php
            }
            break;

        case 'login':
            if (!isset($_SESSION['user_id'])) {
                ?>
                <div class="auth-container">
                    <h2>Welcome Back</h2>
                    <form method="post">
                        <input type="hidden" name="action" value="login">
                        <div class="form-group">
                            <input type="text" name="username" placeholder="Username" required>
                        </div>
                        <div class="form-group">
                            <input type="password" name="password" placeholder="Password" required>
                        </div>
                        <button type="submit">Login</button>
                    </form>
                </div>
                <?php
            }
            break;

        case 'profile':
            if (isset($_SESSION['user_id'])) {
                $users = readJSON($data_files['users']);
                $current_user = array_filter($users, fn($u) => $u['id'] === $_SESSION['user_id']);
                $current_user = reset($current_user);
                ?>
                <div class="profile-header">
                    <div class="post-avatar">
                        <?= strtoupper(substr($_SESSION['username'], 0, 1)) ?>
                    </div>
                    <h2><?= htmlspecialchars($_SESSION['username']) ?></h2>
                </div>
                
                <?php if (!empty($current_user['profile_pic'])): ?>
                    <div class="profile-picture">
                    </div>
                <?php endif; ?>

                <div class="profile-picture-upload">
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="update_profile_pic">
                        <input type="file" name="profile_pic" accept="image/*">
                        <button type="submit">Update Profile Picture</button>
                    </form>
                </div>
                
                <h3>Your Posts</h3>
                <?php
                $posts = readJSON($data_files['posts']);
                $user_posts = array_filter($posts, fn($post) => $post['user_id'] === $_SESSION['user_id']);
                
                foreach ($user_posts as $post) {
                    ?>
                    <div class="post">
                        <div class="post-header">
                            <div class="post-avatar">
                                <?= strtoupper(substr($_SESSION['username'], 0, 1)) ?>
                            </div>
                            <strong><?= htmlspecialchars($_SESSION['username']) ?></strong>
                        </div>
                        <div class="post-content">
                            <p><?= htmlspecialchars($post['content']) ?></p>
                            <?php if ($post['image_path']): ?>
                                <img src="<?= htmlspecialchars($post['image_path']) ?>" alt="Post image">
                            <?php endif; ?>
                        </div>
                        <div class="post-actions">
                            <form method="post">
                                <input type="hidden" name="action" value="delete_post">
                                <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                                <button type="submit"><i class="fas fa-trash"></i> Delete</button>
                            </form>
                        </div>
                    </div>
                    <?php
                }
            }
            break;

        default: // home page
            if (isset($_SESSION['user_id'])) {
                ?>
                <div class="create-post">
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="create_post">
                        <div class="form-group">
                            <textarea name="content" placeholder="What's on your mind?" required></textarea>
                        </div>
                        <div class="form-group">
                            <input type="file" name="image" accept="image/*">
                        </div>
                        <button type="submit"><i class="fas fa-paper-plane"></i> Post</button>
                    </form>
                </div>
                <?php
            }

            $posts = array_reverse(readJSON($data_files['posts']));
            $users = readJSON($data_files['users']);
            $comments = readJSON($data_files['comments']);
            
            foreach ($posts as $post) {
                $post_user = array_filter($users, fn($u) => $u['id'] === $post['user_id']);
                $post_user = reset($post_user);
                ?>
                <div class="post">
                    <div class="post-header">
                        <div class="post-avatar">
                            <?php 
                            if (!empty($post_user['profile_pic'])) {
                                echo '<img src="' . htmlspecialchars($post_user['profile_pic']) . '" alt="Profile" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">';
                            } else {
                                echo strtoupper(substr($post_user['username'], 0, 1));
                            }
                            ?>
                        </div>
                        <strong><?= htmlspecialchars($post_user['username']) ?></strong>
                    </div>
                    
                    <div class="post-content">
                        <p><?= htmlspecialchars($post['content']) ?></p>
                        <?php if ($post['image_path']): ?>
                            <img src="<?= htmlspecialchars($post['image_path']) ?>" alt="Post image">
                        <?php endif; ?>
                    </div>
                    
                    <?php if (isset($_SESSION['user_id']) && $post['user_id'] === $_SESSION['user_id']): ?>
                        <div class="post-actions">
                            <form method="post">
                                <input type="hidden" name="action" value="delete_post">
                                <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                                <button type="submit"><i class="fas fa-trash"></i> Delete</button>
                            </form>
                        </div>
                    <?php endif; ?>

                    <div class="comments-section">
                        <?php
                        $post_comments = array_filter($comments, fn($c) => $c['post_id'] == $post['id']);
                        foreach ($post_comments as $comment) {
                            $comment_user = array_filter($users, fn($u) => $u['id'] === $comment['user_id']);
                            $comment_user = reset($comment_user);
                            ?>
                            <div class="comment">
                                <strong><?= htmlspecialchars($comment_user['username']) ?></strong>:
                                <?= htmlspecialchars($comment['content']) ?>
                            </div>
                            <?php
                        }

                        if (isset($_SESSION['user_id'])) {
                            ?>
                            <form method="post" class="comment-form">
                                <input type="hidden" name="action" value="add_comment">
                                <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                                <input type="text" name="content" placeholder="Write a comment..." required>
                                <button type="submit"><i class="fas fa-paper-plane"></i></button>
                            </form>
                            <?php
                        }
                        ?>
                    </div>
                </div>
                <?php
            }
    }
    ?>
    </div>
</body>
</html>

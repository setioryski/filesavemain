<?php
session_start();

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

function handleUpload() {
    $initial_directory = 'Uploads/';
    $current_directory = $initial_directory;

    // Determine the current directory
    if (isset($_GET['file'])) {
        $current_directory = realpath($_GET['file']);
        if ($current_directory === false || strpos($current_directory, realpath($initial_directory)) !== 0) {
            die("Error: Invalid directory path");
        }
    } elseif (isset($_GET['directory'])) {
        $current_directory = realpath($_GET['directory']);
        if ($current_directory === false || strpos($current_directory, realpath($initial_directory)) !== 0) {
            die("Error: Invalid directory path");
        }
    }

    $response = array('success' => false, 'messages' => array());

    // Handle file upload
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['files'])) {
        if (realpath($current_directory) == realpath($initial_directory)) {
            $response['messages'][] = "Error: You cannot upload files directly to the base 'uploads' folder.";
        } else {
            $files = $_FILES['files'];
            $total_files = count($files['name']);

            for ($i = 0; $i < $total_files; $i++) {
                $filename = basename($files['name'][$i]);
                $targetFile = rtrim($current_directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

                if (file_exists($targetFile)) {
                    $response['messages'][] = "Sorry, file $filename already exists.";
                } else {
                    if (move_uploaded_file($files['tmp_name'][$i], $targetFile)) {
                        $response['messages'][] = "The file $filename has been uploaded.";
                    } else {
                        $response['messages'][] = "Sorry, there was an error uploading $filename.";
                        $response['messages'][] = "Error code: " . $_FILES['files']['error'][$i];
                    }
                }
            }

            $response['success'] = true;
        }

        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    return $current_directory;
}

$current_directory = handleUpload();
$last_directory = basename($current_directory);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Upload</title>
    <link href="styleupload.css" rel="stylesheet" type="text/css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <style>
        .drop-zone {
            width: 100%;
            height: 200px;
            border: 2px dashed #cccccc;
            display: flex;
            justify-content: center;
            align-items: center;
            text-align: center;
            color: #cccccc;
            margin-top: 20px;
        }
        .drop-zone.dragover {
            background-color: #f0f0f0;
            border-color: #333333;
            color: #333333;
        }
    </style>
</head>
<body>
<div class="upload-container">
    <h1>Upload Files to <?= htmlspecialchars($last_directory) ?></h1>
    <form id="uploadForm" action="?directory=<?= urlencode($current_directory) ?>" method="post" enctype="multipart/form-data">
        <label for="fileToUpload">Select files to upload:</label>
        <input type="file" name="files[]" id="fileToUpload" multiple required>
        <div class="drop-zone" id="drop-zone">
            Drag & Drop Files Here
        </div>
        <input type="submit" value="Upload Files" name="submit">
    </form>
</div>
<div id="loading" class="hidden">Uploading...</div>
<div class="progress-bar-container hidden" id="progress-bar-container">
    <div class="progress-bar" id="progress-bar">0%</div>
</div>
<div id="result" class="hidden"></div>
<a href="index.php?file=<?= urlencode($current_directory) ?>" class="back-link">Back to File Manager</a>

<script>
    $(document).ready(function() {
        function handleFiles(files) {
            var formData = new FormData();
            for (var i = 0; i < files.length; i++) {
                formData.append('files[]', files[i]);
            }
            uploadFiles(formData);
        }

        function uploadFiles(formData) {
            var xhr = new XMLHttpRequest();

            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable) {
                    var percentComplete = (e.loaded / e.total) * 100;
                    $('#progress-bar').css('width', percentComplete + '%').text(Math.round(percentComplete) + '%');
                }
            });

            xhr.upload.addEventListener('loadstart', function() {
                $('#loading').removeClass('hidden');
                $('#progress-bar-container').removeClass('hidden');
                $('#result').addClass('hidden').text('');
            });

            xhr.upload.addEventListener('loadend', function() {
                $('#loading').addClass('hidden');
            });

            xhr.onreadystatechange = function() {
                if (xhr.readyState === XMLHttpRequest.DONE) {
                    if (xhr.status === 200) {
                        var response = JSON.parse(xhr.responseText);
                        response.messages.forEach(function(message) {
                            if (response.success) {
                                $('#result').css('color', 'green').append('<p>' + message + '</p>');
                            } else {
                                $('#result').css('color', 'red').append('<p>' + message + '</p>');
                            }
                        });

                        if (response.success) {
                            $('#progress-bar-container').addClass('hidden');
                            $('#result').removeClass('hidden');
                        }
                    } else {
                        console.error('Error: ' + xhr.statusText);
                        $('#result').css('color', 'red').text('Error: ' + xhr.statusText).removeClass('hidden');
                    }
                }
            };

            xhr.onerror = function() {
                console.error('Network Error');
                $('#result').css('color', 'red').text('Network Error').removeClass('hidden');
            };

            xhr.ontimeout = function() {
                console.error('Request Timed Out');
                $('#result').css('color', 'red').text('Request Timed Out').removeClass('hidden');
            };

            xhr.open('POST', $('#uploadForm').attr('action'), true);
            xhr.send(formData);
        }

        $('#uploadForm').on('submit', function(e) {
            e.preventDefault();  // Prevent the default form submission
            var files = $('#fileToUpload')[0].files;
            handleFiles(files);
        });

        var dropZone = $('#drop-zone');

        dropZone.on('dragover', function(e) {
            e.preventDefault();
            dropZone.addClass('dragover');
        });

        dropZone.on('dragleave', function(e) {
            e.preventDefault();
            dropZone.removeClass('dragover');
        });

        dropZone.on('drop', function(e) {
            e.preventDefault();
            dropZone.removeClass('dragover');
            var files = e.originalEvent.dataTransfer.files;
            handleFiles(files);
        });
    });
</script>
</body>
</html>

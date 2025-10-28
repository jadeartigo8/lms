<?php
session_start();
include('../connection/db.php'); 
include('includes/header.php'); 
include 'includes/logger.php';
date_default_timezone_set('Asia/Manila');

$logger = new Logger();

$error = "";
$success = "";
$isbnError = "";

// ---------------------------------------------------------------------
//  FETCH BOOK DATA: Open Library → Google Books fallback
// ---------------------------------------------------------------------
function fetchBookDataFromOpenLibrary(string $isbn): ?array
{
    $isbn = trim(preg_replace('/[^0-9X]/i', '', $isbn));
    if ($isbn === '') return null;

    // 1. Try Open Library
    $url = 'https://openlibrary.org/api/books?' . http_build_query([
        'bibkeys' => "ISBN:{$isbn}",
        'format'  => 'json',
        'jscmd'   => 'data'
    ]);

    $result = curlGet($url);
    if ($result['success']) {
        $json = json_decode($result['body'], true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $key = "ISBN:{$isbn}";
            if (isset($json[$key])) {
                return parseOpenLibrary($json[$key]);
            }
        }
    }

    // 2. Fallback: Google Books API
    $gUrl = 'https://www.googleapis.com/books/v1/volumes?' . http_build_query([
        'q' => "isbn:{$isbn}"
    ]);

    $gResult = curlGet($gUrl);
    if ($gResult['success']) {
        $gJson = json_decode($gResult['body'], true);
        if (json_last_error() === JSON_ERROR_NONE && !empty($gJson['items'])) {
            return parseGoogleBooks($gJson['items'][0]['volumeInfo']);
        }
    }

    return null;
}

// Helper: Safe cURL GET
function curlGet(string $url, int $timeout = 12): array
{
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT      => 'Library-System/1.0 (+http://yourdomain.com)'
    ]);

    $body = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    return [
        'success' => ($body !== false && $http >= 200 && $http < 400),
        'http'    => $http,
        'body'    => $body,
        'error'   => $err
    ];
}

// Parse Open Library
function parseOpenLibrary(array $data): array
{
    $authors = $data['authors'] ?? [];
    $publishers = $data['publishers'] ?? [];
    $subjects   = $data['subjects'] ?? [];

    return [
        'title'       => $data['title'] ?? '',
        'authors'     => array_column($authors, 'name'),
        'publish_date'=> $data['publish_date'] ?? '',
        'publishers'  => array_column($publishers, 'name'),
        'subjects'    => array_column($subjects, 'name'),
        'cover'       => $data['cover']['large'] ?? $data['cover']['medium'] ?? $data['cover']['small'] ?? ''
    ];
}

// Parse Google Books
function parseGoogleBooks(array $info): array
{
    $authors = $info['authors'] ?? [];
    $cats    = $info['categories'] ?? [];

    return [
        'title'       => $info['title'] ?? '',
        'authors'     => $authors,
        'publish_date'=> $info['publishedDate'] ?? '',
        'publishers'  => [$info['publisher'] ?? ''],
        'subjects'    => $cats,
        'cover'       => $info['imageLinks']['thumbnail'] ?? $info['imageLinks']['smallThumbnail'] ?? ''
    ];
}

// ---------------------------------------------------------------------
//  AJAX: ISBN Lookup
// ---------------------------------------------------------------------
if (isset($_GET['lookup_isbn']) && $_GET['lookup_isbn'] !== '') {
    // Clear any previous output
    if (ob_get_length()) ob_clean();
    
    $isbn = trim($_GET['lookup_isbn']);
    $book = fetchBookDataFromOpenLibrary($isbn);

    header('Content-Type: application/json; charset=utf-8');
    
    $response = [];
    
    if ($book) {
        $response = [
            'success' => true,
            'data'    => $book
        ];
    } else {
        $response = [
            'success' => false,
            'message' => 'Book not found in Open Library or Google Books.'
        ];
        
        // Only include debug in development
        if (isset($_GET['debug'])) {
            $response['debug'] = [
                'openlibrary' => curlGet('https://openlibrary.org/api/books?' . http_build_query(['bibkeys'=>"ISBN:{$isbn}",'format'=>'json','jscmd'=>'data'])),
                'google'      => curlGet('https://www.googleapis.com/books/v1/volumes?' . http_build_query(['q'=>"isbn:{$isbn}"]))
            ];
        }
    }
    
    echo json_encode($response);
    exit;
}

// ---------------------------------------------------------------------
//  FORM SUBMISSION
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title    = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $author   = trim($_POST['author'] ?? '');
    $isbn     = trim(preg_replace('/[^0-9X]/i', '', $_POST['isbn'] ?? ''));
    $quantity = (int)($_POST['quantity'] ?? 0);
    $imageName = null;
    $coverUrl = trim($_POST['cover_url'] ?? '');

    // File upload handling - takes priority over API URL
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array(strtolower($ext), $allowed)) {
            $imageName = 'book_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $targetDir = "uploads/";
            if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
            $targetFilePath = $targetDir . $imageName;
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $targetFilePath)) {
                $imageName = null;
                $error = "Failed to upload image.";
            }
        } else {
            $error = "Invalid image format. Only JPG, PNG, GIF allowed.";
        }
    }
    // If no file uploaded but we have a cover URL from API, use the URL
    elseif (!empty($coverUrl) && filter_var($coverUrl, FILTER_VALIDATE_URL)) {
        $imageName = $coverUrl;
    }

    // Check duplicate ISBN
    if (empty($error)) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM books WHERE isbn = ?");
        $stmt->bind_param("s", $isbn);
        $stmt->execute();
        $stmt->bind_result($isbnExists);
        $stmt->fetch();
        $stmt->close();

        if ($isbnExists > 0) {
            $isbnError = "ISBN already exists. Please use a unique ISBN.";
        }
    }

    // Insert book
    if (empty($error) && empty($isbnError)) {
        $stmt = $conn->prepare("
            INSERT INTO books 
            (title, category, author, isbn, image, isIssued, quantity, registration_date, update_date) 
            VALUES (?, ?, ?, ?, ?, 0, ?, NOW(), NOW())
        ");

        if ($stmt) {
            $stmt->bind_param("sssssi", $title, $category, $author, $isbn, $imageName, $quantity);
            if ($stmt->execute()) {
                $success = "Book added successfully!";
                $logger->write("Book added: [$title] | ISBN: $isbn");
                $_POST = [];
            } else {
                $error = "Database error: " . $stmt->error;
                $logger->write("Failed to add book: " . $stmt->error);
            }
            $stmt->close();
        } else {
            $error = "SQL Error: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Book</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <style>
        .isbn-lookup { 
            display: flex; 
            align-items: center; 
            gap: 10px; 
            margin-bottom: 10px; 
        }
        .isbn-lookup input { flex: 1; }
        
        .action-btn { 
            background: #4a6fa5; 
            color: white; 
            border: none; 
            padding: 10px 16px; 
            border-radius: 4px; 
            cursor: pointer; 
            font-size: 14px;
            transition: background 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .action-btn:hover { background: #3a5a80; }
        .action-btn:disabled { background: #ccc; cursor: not-allowed; }
        
        .scan-btn {
            background: #28a745;
        }
        .scan-btn:hover {
            background: #218838;
        }
        .scan-btn.scanning {
            background: #dc3545;
        }
        
        .loading { 
            display: none; 
            color: #4a6fa5; 
            font-size: 14px; 
            margin-top: 5px;
        }
        .api-result { 
            margin-top: 15px; 
            padding: 10px; 
            border-radius: 4px; 
            display: none; 
        }
        .api-success { 
            background-color: #d4edda; 
            color: #155724; 
            border: 1px solid #c3e6cb; 
        }
        .api-error { 
            background-color: #f8d7da; 
            color: #721c24; 
            border: 1px solid #f5c6cb; 
        }
        .book-cover-preview { 
            max-width: 150px; 
            margin-top: 10px; 
            border: 1px solid #ddd; 
            border-radius: 4px; 
        }
        .fill-data-btn { 
            background: #28a745; 
            color: white; 
            border: none; 
            padding: 8px 15px; 
            border-radius: 4px; 
            cursor: pointer; 
            font-size: 14px; 
            margin-top: 10px; 
        }
        .fill-data-btn:hover { background: #218838; }
        .custom-error { 
            padding: 10px; 
            margin: 10px 0; 
            border-radius: 4px; 
            font-size: 14px; 
        }
        .cover-info { 
            font-size: 12px; 
            color: #666; 
            margin-top: 5px; 
        }
        
        /* Scanner Modal */
        .scanner-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.9);
        }
        .scanner-content {
            position: relative;
            margin: 2% auto;
            width: 90%;
            max-width: 640px;
        }
        .scanner-header {
            background: #fff;
            padding: 15px;
            border-radius: 8px 8px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .scanner-header h3 {
            margin: 0;
            color: #333;
        }
        .close-scanner {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        .close-scanner:hover {
            background: #c82333;
        }
        #scanner-container {
            position: relative;
            width: 100%;
            background: #000;
            border-radius: 0 0 8px 8px;
            overflow: hidden;
        }
        #scanner-video {
            width: 100%;
            height: auto;
            display: block;
        }
        .scanner-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 80%;
            max-width: 300px;
            height: 150px;
            border: 3px solid #28a745;
            border-radius: 8px;
            box-shadow: 0 0 0 9999px rgba(0,0,0,0.5);
        }
        .scanner-line {
            position: absolute;
            width: 100%;
            height: 2px;
            background: #28a745;
            animation: scan 2s linear infinite;
        }
        @keyframes scan {
            0%, 100% { top: 0; }
            50% { top: 100%; }
        }
        .scanner-instructions {
            position: absolute;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 10px 20px;
            border-radius: 4px;
            text-align: center;
        }
    </style>
</head>
<body>

<div class="signup-container">
    <h3>Add New Book</h3>

    <?php if ($error): ?>
        <div class="custom-error" style="background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="custom-error" style="background:#d4edda;color:#155724;border:1px solid #c3e6cb;">
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" id="bookForm">
        <input type="hidden" name="cover_url" id="cover_url" value="">
        
        <div class="form-group">
            <label>ISBN <span style="color:red;">*</span></label>
            <div class="isbn-lookup">
                <input type="text" name="isbn" id="isbn" value="<?php echo isset($_POST['isbn']) ? htmlentities($_POST['isbn']) : ''; ?>" required placeholder="Enter or scan ISBN">
                <button type="button" id="scanBtn" class="action-btn scan-btn">
                    <i class="fas fa-barcode"></i> Scan
                </button>
                <button type="button" id="lookupBtn" class="action-btn">
                    <i class="fas fa-search"></i> Lookup
                </button>
            </div>
            <div class="loading" id="loadingIndicator">
                <i class="fas fa-spinner fa-spin"></i> Searching Open Library & Google Books...
            </div>
            <div id="apiResult" class="api-result"></div>
            <?php if (!empty($isbnError)): ?>
                <span style="color: red; font-size: 12px;"><?php echo htmlentities($isbnError); ?></span>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label>Title <span style="color:red;">*</span></label>
            <input type="text" name="title" id="title" value="<?php echo isset($_POST['title']) ? htmlentities($_POST['title']) : ''; ?>" required>
        </div>

        <div class="form-group">
            <label>Category <span style="color:red;">*</span></label>
            <input type="text" name="category" id="category" value="<?php echo isset($_POST['category']) ? htmlentities($_POST['category']) : ''; ?>" required>
        </div>

        <div class="form-group">
            <label>Author <span style="color:red;">*</span></label>
            <input type="text" name="author" id="author" value="<?php echo isset($_POST['author']) ? htmlentities($_POST['author']) : ''; ?>" required>
        </div>

        <div class="form-group">
            <label>Quantity <span style="color:red;">*</span></label>
            <input type="number" name="quantity" min="1" value="<?php echo isset($_POST['quantity']) ? (int)$_POST['quantity'] : '1'; ?>" required>
        </div>

        <div class="form-group">
            <label>Book Image (Optional)</label>
            <input type="file" name="image" accept="image/*" id="imageUpload">
            <div class="cover-info">
                <small>Upload an image or use the cover from API search</small>
            </div>
            <div id="coverPreview"></div>
            <div id="coverSourceInfo" style="display: none; font-size: 12px; color: #28a745; margin-top: 5px;">
                <i class="fas fa-check-circle"></i> Cover will be saved from API
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn">Add Book</button>
        </div>
    </form>
</div>

<!-- Barcode Scanner Modal -->
<div id="scannerModal" class="scanner-modal">
    <div class="scanner-content">
        <div class="scanner-header">
            <h3><i class="fas fa-barcode"></i> Scan Book Barcode</h3>
            <button type="button" class="close-scanner" id="closeScanner">
                <i class="fas fa-times"></i> Close
            </button>
        </div>
        <div id="scanner-container">
            <video id="scanner-video" playsinline></video>
            <div class="scanner-overlay">
                <div class="scanner-line"></div>
            </div>
            <div class="scanner-instructions">
                Position the barcode within the green frame
            </div>
        </div>
    </div>
</div>

<!-- Include QuaggaJS for barcode scanning -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/quagga/0.12.1/quagga.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const isbnInput = document.getElementById('isbn');
    const lookupBtn = document.getElementById('lookupBtn');
    const scanBtn = document.getElementById('scanBtn');
    const loading = document.getElementById('loadingIndicator');
    const apiResult = document.getElementById('apiResult');
    const title = document.getElementById('title');
    const category = document.getElementById('category');
    const author = document.getElementById('author');
    const coverPreview = document.getElementById('coverPreview');
    const coverUrlInput = document.getElementById('cover_url');
    const coverSourceInfo = document.getElementById('coverSourceInfo');
    const imageUpload = document.getElementById('imageUpload');
    
    const scannerModal = document.getElementById('scannerModal');
    const closeScanner = document.getElementById('closeScanner');

    let bookData = null;
    let scannerActive = false;

    // Lookup button click
    lookupBtn.addEventListener('click', function() {
        const isbn = isbnInput.value.trim();
        if (!isbn) return alert('Enter an ISBN');
        performLookup(isbn);
    });

    // Scan button click
    scanBtn.addEventListener('click', function() {
        if (scannerActive) {
            stopScanner();
        } else {
            startScanner();
        }
    });

    // Close scanner
    closeScanner.addEventListener('click', stopScanner);

    // Start barcode scanner
    function startScanner() {
        scannerModal.style.display = 'block';
        scanBtn.classList.add('scanning');
        scanBtn.innerHTML = '<i class="fas fa-stop"></i> Stop';
        scannerActive = true;

        Quagga.init({
            inputStream: {
                name: "Live",
                type: "LiveStream",
                target: document.querySelector('#scanner-video'),
                constraints: {
                    facingMode: "environment"
                }
            },
            decoder: {
                readers: ["ean_reader", "ean_8_reader", "upc_reader", "upc_e_reader"]
            }
        }, function(err) {
            if (err) {
                console.error(err);
                alert('Camera access denied or not available');
                stopScanner();
                return;
            }
            Quagga.start();
        });

        Quagga.onDetected(function(result) {
            const code = result.codeResult.code;
            if (code) {
                isbnInput.value = code;
                stopScanner();
                performLookup(code);
            }
        });
    }

    // Stop barcode scanner
    function stopScanner() {
        if (scannerActive) {
            Quagga.stop();
            scannerActive = false;
        }
        scannerModal.style.display = 'none';
        scanBtn.classList.remove('scanning');
        scanBtn.innerHTML = '<i class="fas fa-barcode"></i> Scan';
    }

    // Perform ISBN lookup
    function performLookup(isbn) {
        loading.style.display = 'block';
        apiResult.style.display = 'none';
        lookupBtn.disabled = true;
        coverSourceInfo.style.display = 'none';

        fetch(`?lookup_isbn=${encodeURIComponent(isbn)}`)
            .then(r => r.json())
            .then(data => {
                loading.style.display = 'none';
                lookupBtn.disabled = false;

                if (data.success) {
                    bookData = data.data;
                    apiResult.className = 'api-result api-success';
                    apiResult.innerHTML = `
                        <strong><i class="fas fa-check-circle"></i> Book found!</strong> 
                        <button type="button" class="fill-data-btn" id="fillBtn">Fill Form</button>
                    `;
                    apiResult.style.display = 'block';
                    document.getElementById('fillBtn').onclick = fillForm;
                } else {
                    apiResult.className = 'api-result api-error';
                    apiResult.innerHTML = `<strong><i class="fas fa-exclamation-circle"></i> Error:</strong> ${data.message}`;
                    apiResult.style.display = 'block';
                }
            })
            .catch(err => {
                loading.style.display = 'none';
                lookupBtn.disabled = false;
                apiResult.className = 'api-result api-error';
                apiResult.innerHTML = '<strong><i class="fas fa-exclamation-circle"></i> Error:</strong> Network or server issue.';
                apiResult.style.display = 'block';
                console.error(err);
            });
    }

    // Fill form with book data
    function fillForm() {
        if (!bookData) return;

        if (bookData.title) title.value = bookData.title;
        if (bookData.authors?.length) author.value = bookData.authors.join(', ');
        if (bookData.subjects?.length) category.value = bookData.subjects[0];
        else if (bookData.publishers?.length) category.value = bookData.publishers[0];

        if (bookData.cover) {
            coverPreview.innerHTML = `<img src="${bookData.cover}" class="book-cover-preview" alt="Cover">`;
            coverUrlInput.value = bookData.cover;
            coverSourceInfo.style.display = 'block';
        } else {
            coverPreview.innerHTML = '<div style="color: #666; margin-top: 10px;">No cover image available</div>';
            coverUrlInput.value = '';
            coverSourceInfo.style.display = 'none';
        }

        apiResult.style.display = 'none';
    }

    // Reset cover source info when user selects a file
    imageUpload.addEventListener('change', function() {
        if (this.files.length > 0) {
            coverSourceInfo.style.display = 'none';
            coverUrlInput.value = '';
        }
    });

    // Enter key in ISBN field
    isbnInput.addEventListener('keypress', e => {
        if (e.key === 'Enter') {
            e.preventDefault();
            lookupBtn.click();
        }
    });

    // Close scanner on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && scannerActive) {
            stopScanner();
        }
    });
});
</script>

</body>
</html>
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
//  SMART CATEGORY CLASSIFIER: Fiction / Non-Fiction / Special
// ---------------------------------------------------------------------
function classifyBookCategory(array $subjects, string $title = '', array $authors = []): array
{
    $titleLower   = strtolower($title);
    $subjectStr   = strtolower(implode(' ', $subjects));
    $authorStr    = strtolower(implode(' ', $authors));

    // -------------------------------
    // 1. SPECIAL COLLECTIONS (highest priority)
    // -------------------------------
    if (preg_match('/\b(picture book|board book|early reader|illustrated|picture|children\'s picture)\b/', $subjectStr . ' ' . $titleLower)) {
        return ['type' => 'Special', 'category' => 'Picture Books', 'sort' => 'author'];
    }
    if (preg_match('/\b(poetry|poem|verse|haiku|sonnet|rhyme)\b/', $subjectStr . ' ' . $titleLower)) {
        return ['type' => 'Special', 'category' => 'Poetry', 'sort' => 'author'];
    }
    if (preg_match('/\b(graphic novel|comic|manga|webtoon|comic book)\b/', $subjectStr . ' ' . $titleLower)) {
        return ['type' => 'Special', 'category' => 'Graphic Novels', 'sort' => 'author'];
    }
    if (preg_match('/\b(diverse|multicultural|lgbt|inclusive|representation|diversity|black voices|asian stories)\b/', $subjectStr . ' ' . $titleLower)) {
        return ['type' => 'Special', 'category' => 'Diverse Stories', 'sort' => 'author'];
    }

    // -------------------------------
    // 2. FICTION GENRES
    // -------------------------------
    $fictionMap = [
        'Fantasy'          => '/\b(fantasy|magic|wizard|dragon|elf|witch|harry potter|lord of the rings|game of thrones|percy jackson|narnia)\b/',
        'Mystery/Thriller' => '/\b(mystery|detective|crime|thriller|suspense|whodunit|sherlock|agatha christie|murder)\b/',
        'Romance'          => '/\b(romance|love story|romantic|twilight|pride and prejudice)\b/',
        'Science Fiction'  => '/\b(sci-?fi|science fiction|space|alien|dystopia|dune|hunger games|ender\'s game)\b/',
        'Horror'           => '/\b(horror|ghost|zombie|vampire|stephen king|haunted)\b/',
        'Adventure'        => '/\b(adventure|quest|journey|treasure|exploration|pirate)\b/',
        'Historical Fiction' => '/\b(historical fiction|historical novel|world war|civil war|victorian|regency)\b/',
    ];

    foreach ($fictionMap as $genre => $pattern) {
        if (preg_match($pattern, $subjectStr . ' ' . $titleLower . ' ' . $authorStr)) {
            return ['type' => 'Fiction', 'category' => $genre, 'sort' => 'author'];
        }
    }

    // Default Fiction
    if (preg_match('/\b(fiction|novel|story|literature|tale)\b/', $subjectStr . ' ' . $titleLower)) {
        return ['type' => 'Fiction', 'category' => 'Fiction', 'sort' => 'author'];
    }

    // -------------------------------
    // 3. NON-FICTION: Dewey Decimal
    // -------------------------------
    $deweyMap = [
        '000' => '/\b(computer|programming|software|internet|encyclopedia|information|library|data|ai)\b/',
        '100' => '/\b(philosophy|psychology|ethics|logic|mind|consciousness|self-help|emotion)\b/',
        '200' => '/\b(religion|bible|christian|islam|buddhism|hinduism|spirituality|theology|god|faith)\b/',
        '300' => '/\b(social|sociology|economics|law|education|government|politics|anthropology|community)\b/',
        '400' => '/\b(language|linguistics|dictionary|grammar|english|spanish|french|japanese|chinese)\b/',
        '500' => '/\b(science|mathematics|physics|chemistry|biology|astronomy|geology|ecology|evolution)\b/',
        '600' => '/\b(technology|medicine|engineering|agriculture|cooking|health|management|business|invention)\b/',
        '700' => '/\b(art|music|painting|photography|film|dance|architecture|sports|games|recreation)\b/',
        '800' => '/\b(literature|poetry|drama|essay|criticism|rhetoric|writing)\b/',
        '900' => '/\b(history|geography|biography|travel|ancient|medieval|world war|civilization|country)\b/',
    ];

    foreach ($deweyMap as $code => $pattern) {
        if (preg_match($pattern, $subjectStr . ' ' . $titleLower)) {
            $names = [
                '000' => 'Computer Science (000)',
                '100' => 'Philosophy & Psychology (100)',
                '200' => 'Religion (200)',
                '300' => 'Social Sciences (300)',
                '400' => 'Language (400)',
                '500' => 'Science (500)',
                '600' => 'Technology (600)',
                '700' => 'Arts & Recreation (700)',
                '800' => 'Literature (800)',
                '900' => 'History & Geography (900)'
            ];
            return ['type' => 'Non-Fiction', 'category' => $names[$code], 'sort' => 'dewey'];
        }
    }

    // Biographies are Non-Fiction under 920
    if (preg_match('/\b(biography|autobiography|memoir|life story)\b/', $subjectStr . ' ' . $titleLower)) {
        return ['type' => 'Non-Fiction', 'category' => 'Biography (920)', 'sort' => 'author'];
    }

    // -------------------------------
    // DEFAULT
    // -------------------------------
    return ['type' => 'General', 'category' => 'General Works', 'sort' => 'author'];
}

// ---------------------------------------------------------------------
//  FETCH BOOK DATA: Open Library → Google Books fallback
// ---------------------------------------------------------------------
function fetchBookDataFromOpenLibrary(string $isbn): ?array
{
    $isbn = trim(preg_replace('/[^0-9X]/i', '', $isbn));
    if ($isbn === '') return null;

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

function curlGet(string $url, int $timeout = 12): array
{
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT      => 'Library-System/1.0'
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
    if (ob_get_length()) ob_clean();
    
    $isbn = trim(preg_replace('/[^0-9X]/i', '', $_GET['lookup_isbn']));
    $book = fetchBookDataFromOpenLibrary($isbn);

    header('Content-Type: application/json; charset=utf-8');
    
    if ($book) {
        $classification = classifyBookCategory($book['subjects'], $book['title'], $book['authors']);
        $book = array_merge($book, $classification);
        
        $response = ['success' => true, 'data' => $book];
    } else {
        $response = ['success' => false, 'message' => 'Book not found in Open Library or Google Books.'];
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
    } elseif (!empty($coverUrl) && filter_var($coverUrl, FILTER_VALIDATE_URL)) {
        $imageName = $coverUrl;
    }

    if (empty($error)) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM books WHERE isbn = ?");
        $stmt->bind_param("s", $isbn);
        $stmt->execute();
        $stmt->bind_result($isbnExists);
        $stmt->fetch();
        $stmt->close();
        if ($isbnExists > 0) $isbnError = "ISBN already exists.";
    }

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
                $logger->write("Book added: [$title] | ISBN: $isbn | Category: $category");
                $_POST = [];
            } else {
                $error = "Database error: " . $stmt->error;
            }
            $stmt->close();
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
        .entry-mode-selector { display: flex; gap: 15px; margin-bottom: 30px; border-bottom: 2px solid #e0e0e0; }
        .mode-tab { padding: 12px 24px; background: none; border: none; border-bottom: 3px solid transparent; cursor: pointer; font-size: 16px; font-weight: 500; color: #666; transition: all .3s; }
        .mode-tab:hover { color: #4a6fa5; }
        .mode-tab.active { color: #4a6fa5; border-bottom-color: #4a6fa5; }
        .entry-section { display: none; }
        .entry-section.active { display: block; }
        .search-section { background: #f8f9fa; padding: 25px; border-radius: 8px; margin-bottom: 25px; }
        .search-title { font-size: 18px; font-weight: 600; margin-bottom: 15px; color: #333; }
        .isbn-lookup { display: flex; align-items: center; gap: 10px; }
        .isbn-lookup input { flex: 1; }
        .action-btn { background: #4a6fa5; color: white; border: none; padding: 12px 20px; border-radius: 4px; cursor: pointer; font-size: 14px; transition: background .3s; display: inline-flex; align-items: center; gap: 8px; font-weight: 500; }
        .action-btn:hover { background: #3a5a80; }
        .action-btn:disabled { background: #ccc; cursor: not-allowed; }
        .scan-btn { background: #28a745; }
        .scan-btn:hover { background: #218838; }
        .scan-btn.scanning { background: #dc3545; }
        .loading { display: none; color: #4a6fa5; font-size: 14px; margin-top: 10px; padding: 10px; background: #e3f2fd; border-radius: 4px; }
        .api-result { margin-top: 15px; padding: 15px; border-radius: 6px; display: none; }
        .api-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .api-error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .book-preview-card { background: white; border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin-top: 20px; display: none; }
        .book-preview-card.show { display: block; }
        .preview-grid { display: grid; grid-template-columns: 150px 1fr; gap: 20px; }
        .preview-cover img { width: 100%; border-radius: 4px; box-shadow: 0 2px 8px rgba(0,0,0,.1); }
        .preview-details h4 { margin: 0 0 15px 0; color: #333; font-size: 18px; }
        .preview-info { margin-bottom: 10px; color: #555; }
        .preview-info strong { display: inline-block; width: 100px; color: #333; }
        .type-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; color: white; margin-left: 10px; }
        .confirm-add-btn { background: #28a745; color: white; border: none; padding: 12px 30px; border-radius: 4px; cursor: pointer; font-size: 16px; font-weight: 500; margin-top: 20px; display: flex; align-items: center; gap: 8px; }
        .confirm-add-btn:hover { background: #218838; }
        .custom-error { padding: 12px; margin: 15px 0; border-radius: 6px; font-size: 14px; }
        .scanner-modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,.9); }
        .scanner-content { position: relative; margin: 2% auto; width: 90%; max-width: 640px; }
        .scanner-header { background: #fff; padding: 15px; border-radius: 8px 8px 0 0; display: flex; justify-content: space-between; align-items: center; }
        .scanner-header h3 { margin: 0; color: #333; }
        .close-scanner { background: #dc3545; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-size: 14px; }
        .close-scanner:hover { background: #c82333; }
        #scanner-container { position: relative; width: 100%; background: #000; border-radius: 0 0 8px 8px; overflow: hidden; }
        #scanner-video { width: 100%; height: auto; display: block; }
        .scanner-overlay { position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%); width: 80%; max-width: 300px; height: 150px; border: 3px solid #28a745; border-radius: 8px; box-shadow: 0 0 0 9999px rgba(0,0,0,.5); }
        .scanner-line { position: absolute; width: 100%; height: 2px; background: #28a745; animation: scan 2s linear infinite; }
        @keyframes scan { 0%,100% { top:0; } 50% { top:100%; } }
        .scanner-instructions { position: absolute; bottom: 20px; left: 50%; transform: translateX(-50%); background: rgba(0,0,0,.7); color: white; padding: 10px 20px; border-radius: 4px; text-align: center; }
    </style>
</head>
<body>

<div class="signup-container">
    <h3>Add New Book</h3>

    <?php if ($error): ?>
        <div class="custom-error" style="background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="custom-error" style="background:#d4edda;color:#155724;border:1px solid #c3e6cb;">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <!-- Mode Selector -->
    <div class="entry-mode-selector">
        <button class="mode-tab active" data-mode="search"><i class="fas fa-search"></i> Search by ISBN</button>
        <button class="mode-tab" data-mode="manual"><i class="fas fa-keyboard"></i> Manual Entry</button>
    </div>

    <!-- Search Mode -->
    <div class="entry-section active" id="searchMode">
        <div class="search-section">
            <div class="search-title"><i class="fas fa-barcode"></i> Find Book by ISBN</div>
            <div class="isbn-lookup">
                <input type="text" id="searchIsbn" placeholder="Enter or scan ISBN number" />
                <button type="button" id="scanBtn" class="action-btn scan-btn"><i class="fas fa-barcode"></i> Scan</button>
                <button type="button" id="lookupBtn" class="action-btn"><i class="fas fa-search"></i> Search</button>
            </div>
            <div class="loading" id="loadingIndicator"><i class="fas fa-spinner fa-spin"></i> Searching Open Library & Google Books...</div>
            <div id="apiResult" class="api-result"></div>
        </div>

        <div class="book-preview-card" id="bookPreview">
            <div class="preview-grid">
                <div class="preview-cover" id="previewCover"><img src="" alt="Book Cover" id="previewCoverImg"></div>
                <div class="preview-details">
                    <h4 id="previewTitle"></h4>
                    <div class="preview-info"><strong>Author:</strong> <span id="previewAuthor"></span></div>
                    <div class="preview-info">
                        <strong>Category:</strong> 
                        <span id="previewCategory"></span>
                        <span class="type-badge" id="typeBadge"></span>
                    </div>
                    <div class="preview-info"><strong>ISBN:</strong> <span id="previewIsbn"></span></div>
                    <div class="preview-info"><strong>Publisher:</strong> <span id="previewPublisher"></span></div>
                    <div class="preview-info"><strong>Published:</strong> <span id="previewDate"></span></div>
                </div>
            </div>

            <form method="POST" id="searchForm" style="margin-top:20px;">
                <input type="hidden" name="title" id="hiddenTitle">
                <input type="hidden" name="author" id="hiddenAuthor">
                <input type="hidden" name="category" id="hiddenCategory">
                <input type="hidden" name="isbn" id="hiddenIsbn">
                <input type="hidden" name="cover_url" id="hiddenCover">
                <div class="form-group">
                    <label>Quantity <span style="color:red;">*</span></label>
                    <input type="number" name="quantity" min="1" value="1" required style="max-width:200px;">
                </div>
                <button type="submit" class="confirm-add-btn"><i class="fas fa-plus-circle"></i> Add This Book to Library</button>
            </form>
        </div>
    </div>

    <!-- Manual Mode -->
    <div class="entry-section" id="manualMode">
        <form method="POST" enctype="multipart/form-data" id="manualForm">
            <div class="form-group">
                <label>ISBN <span style="color:red;">*</span></label>
                <input type="text" name="isbn" required placeholder="Enter ISBN">
                <?php if ($isbnError): ?>
                    <span style="color:red;font-size:12px;"><?php echo htmlentities($isbnError); ?></span>
                <?php endif; ?>
            </div>
            <div class="form-group"><label>Title <span style="color:red;">*</span></label><input type="text" name="title" required></div>
            <div class="form-group">
                <label>Category <span style="color:red;">*</span></label>
                <select name="category" required style="padding:10px;border:1px solid #ddd;border-radius:4px;width:100%;">
                    <option value="">Select Category</option>
                    <optgroup label="Fiction">
                        <option value="Fiction">Fiction</option>
                        <option value="Fantasy">Fantasy</option>
                        <option value="Mystery/Thriller">Mystery/Thriller</option>
                        <option value="Romance">Romance</option>
                        <option value="Science Fiction">Science Fiction</option>
                        <option value="Horror">Horror</option>
                        <option value="Adventure">Adventure</option>
                        <option value="Historical Fiction">Historical Fiction</option>
                    </optgroup>
                    <optgroup label="Non-Fiction">
                        <option value="Computer Science (000)">Computer Science (000)</option>
                        <option value="Philosophy & Psychology (100)">Philosophy & Psychology (100)</option>
                        <option value="Religion (200)">Religion (200)</option>
                        <option value="Social Sciences (300)">Social Sciences (300)</option>
                        <option value="Language (400)">Language (400)</option>
                        <option value="Science (500)">Science (500)</option>
                        <option value="Technology (600)">Technology (600)</option>
                        <option value="Arts & Recreation (700)">Arts & Recreation (700)</option>
                        <option value="Biography (920)">Biography (920)</option>
                        <option value="History & Geography (900)">History & Geography (900)</option>
                    </optgroup>
                    <optgroup label="Special Collections">
                        <option value="Picture Books">Picture Books</option>
                        <option value="Poetry">Poetry</option>
                        <option value="Graphic Novels">Graphic Novels</option>
                        <option value="Diverse Stories">Diverse Stories</option>
                    </optgroup>
                </select>
            </div>
            <div class="form-group"><label>Author <span style="color:red;">*</span></label><input type="text" name="author" required></div>
            <div class="form-group"><label>Quantity <span style="color:red;">*</span></label><input type="number" name="quantity" min="1" value="1" required></div>
            <div class="form-group"><label>Book Image (Optional)</label><input type="file" name="image" accept="image/*"></div>
            <div class="form-actions"><button type="submit" class="btn">Add Book</button></div>
        </form>
    </div>
</div>

<!-- Barcode Scanner Modal -->
<div id="scannerModal" class="scanner-modal">
    <div class="scanner-content">
        <div class="scanner-header">
            <h3><i class="fas fa-barcode"></i> Scan Book Barcode</h3>
            <button type="button" class="close-scanner" id="closeScanner"><i class="fas fa-times"></i> Close</button>
        </div>
        <div id="scanner-container">
            <video id="scanner-video" playsinline></video>
            <div class="scanner-overlay"><div class="scanner-line"></div></div>
            <div class="scanner-instructions">Position the barcode within the green frame</div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/quagga/0.12.1/quagga.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modeTabs = document.querySelectorAll('.mode-tab');
    const sections = document.querySelectorAll('.entry-section');
    const searchIsbn = document.getElementById('searchIsbn');
    const lookupBtn = document.getElementById('lookupBtn');
    const scanBtn = document.getElementById('scanBtn');
    const loading = document.getElementById('loadingIndicator');
    const apiResult = document.getElementById('apiResult');
    const bookPreview = document.getElementById('bookPreview');
    const scannerModal = document.getElementById('scannerModal');
    const closeScanner = document.getElementById('closeScanner');

    let scannerActive = false;

    // Mode switching
    modeTabs.forEach(tab => {
        tab.addEventListener('click', function() {
            modeTabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            sections.forEach(s => s.classList.remove('active'));
            document.getElementById(this.dataset.mode + 'Mode').classList.add('active');
        });
    });

    // Lookup
    lookupBtn.addEventListener('click', () => {
        const isbn = searchIsbn.value.trim();
        if (!isbn) return alert('Please enter an ISBN');
        performLookup(isbn);
    });

    // Scan
    scanBtn.addEventListener('click', () => {
        if (scannerActive) stopScanner();
        else startScanner();
    });
    closeScanner.addEventListener('click', stopScanner);

    // Enter key
    searchIsbn.addEventListener('keypress', e => {
        if (e.key === 'Enter') { e.preventDefault(); lookupBtn.click(); }
    });

    function performLookup(isbn) {
        loading.style.display = 'block';
        apiResult.style.display = 'none';
        bookPreview.classList.remove('show');
        lookupBtn.disabled = true;

        fetch(`?lookup_isbn=${encodeURIComponent(isbn)}`)
            .then(r => r.json())
            .then(data => {
                loading.style.display = 'none';
                lookupBtn.disabled = false;

                if (data.success) {
                    displayBookPreview(data.data, isbn);
                } else {
                    apiResult.className = 'api-result api-error';
                    apiResult.innerHTML = `<strong>Error:</strong> ${data.message}`;
                    apiResult.style.display = 'block';
                }
            })
            .catch(err => {
                loading.style.display = 'none';
                lookupBtn.disabled = false;
                apiResult.className = 'api-result api-error';
                apiResult.innerHTML = '<strong>Error:</strong> Network issue.';
                apiResult.style.display = 'block';
            });
    }

    function displayBookPreview(book, isbn) {
        apiResult.className = 'api-result api-success';
        apiResult.innerHTML = '<strong>Book found!</strong> Review and add to library.';
        apiResult.style.display = 'block';

        document.getElementById('previewTitle').textContent = book.title || 'N/A';
        document.getElementById('previewAuthor').textContent = book.authors?.join(', ') || 'Unknown';
        document.getElementById('previewCategory').textContent = book.category || 'General';
        document.getElementById('previewIsbn').textContent = isbn;
        document.getElementById('previewPublisher').textContent = book.publishers?.join(', ') || 'N/A';
        document.getElementById('previewDate').textContent = book.publish_date || 'N/A';

        const coverImg = document.getElementById('previewCoverImg');
        if (book.cover) {
            coverImg.src = book.cover;
            coverImg.style.display = 'block';
        } else {
            coverImg.style.display = 'none';
        }

        const badge = document.getElementById('typeBadge');
        const type = book.type;
        badge.textContent = type;
        badge.style.background = 
            type === 'Fiction' ? '#e91e63' :
            type === 'Non-Fiction' ? '#2196f3' :
            type === 'Special' ? '#9c27b0' : '#607d8b';

        document.getElementById('hiddenTitle').value = book.title || '';
        document.getElementById('hiddenAuthor').value = book.authors?.join(', ') || '';
        document.getElementById('hiddenCategory').value = book.category || '';
        document.getElementById('hiddenIsbn').value = isbn;
        document.getElementById('hiddenCover').value = book.cover || '';

        bookPreview.classList.add('show');
    }

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
                constraints: { facingMode: "environment" }
            },
            decoder: { readers: ["ean_reader","ean_8_reader","upc_reader","upc_e_reader"] }
        }, err => {
            if (err) {
                alert('Camera access denied.');
                stopScanner();
                return;
            }
            Quagga.start();
        });

        Quagga.onDetected(result => {
            const code = result.codeResult.code;
            if (code) {
                searchIsbn.value = code;
                stopScanner();
                performLookup(code);
            }
        });
    }

    function stopScanner() {
        if (scannerActive) Quagga.stop();
        scannerActive = false;
        scannerModal.style.display = 'none';
        scanBtn.classList.remove('scanning');
        scanBtn.innerHTML = '<i class="fas fa-barcode"></i> Scan';
    }

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && scannerActive) stopScanner();
    });
});
</script>

</body>
</html>
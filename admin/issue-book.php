<?php
session_start();
date_default_timezone_set('Asia/Manila');

include('../connection/db.php'); 

include('includes/logger.php');

$logger = new Logger();

if (strlen($_SESSION['alogin']) == 0) {
    header('location:index.php');
    exit;
}

$error = "";
$success = "";

// Fetch books and students
$bookList = [];
$bookQuery = $conn->query("SELECT book_id, title, isbn FROM books WHERE quantity > 0");
while ($row = $bookQuery->fetch_assoc()) {
    $bookList[] = $row;
}

$studentList = [];
$studentQuery = $conn->query("SELECT student_id, CONCAT(first_name, ' ', middle_name, ' ', last_name) AS full_name FROM students");
while ($row = $studentQuery->fetch_assoc()) {
    $studentList[] = $row;
}

// AJAX: ISBN Lookup for book details
if (isset($_GET['lookup_isbn']) && $_GET['lookup_isbn'] !== '') {
    if (ob_get_length()) ob_clean();
    
    $isbn = trim(preg_replace('/[^0-9X]/i', '', $_GET['lookup_isbn']));
    
    $stmt = $conn->prepare("SELECT book_id, title, author, isbn, category, image, quantity FROM books WHERE isbn = ?");
    $stmt->bind_param("s", $isbn);
    $stmt->execute();
    $result = $stmt->get_result();
    
    header('Content-Type: application/json; charset=utf-8');
    
    if ($book = $result->fetch_assoc()) {
        if ($book['quantity'] > 0) {
            echo json_encode(['success' => true, 'data' => $book]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Book is out of stock.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Book not found.']);
    }
    
    $stmt->close();
    exit;
}

// AJAX: Student ID Lookup for student details
if (isset($_GET['lookup_student']) && $_GET['lookup_student'] !== '') {
    if (ob_get_length()) ob_clean();
    
    $studentId = trim($_GET['lookup_student']);
    
    // Check if columns exist, if not use NULL
    $checkColumns = $conn->query("SHOW COLUMNS FROM students LIKE 'year_level'");
    $hasYearLevel = $checkColumns->num_rows > 0;
    
    $checkColumns2 = $conn->query("SHOW COLUMNS FROM students LIKE 'profile_image'");
    $hasProfileImage = $checkColumns2->num_rows > 0;
    
    if ($hasYearLevel && $hasProfileImage) {
        $stmt = $conn->prepare("SELECT student_id, first_name, middle_name, last_name, email, mobile_no, course, specialization, year_level, profile_image FROM students WHERE student_id = ?");
    } else if ($hasYearLevel) {
        $stmt = $conn->prepare("SELECT student_id, first_name, middle_name, last_name, email, mobile_no, course, specialization, year_level, NULL as profile_image FROM students WHERE student_id = ?");
    } else if ($hasProfileImage) {
        $stmt = $conn->prepare("SELECT student_id, first_name, middle_name, last_name, email, mobile_no, course, specialization, NULL as year_level, profile_image FROM students WHERE student_id = ?");
    } else {
        $stmt = $conn->prepare("SELECT student_id, first_name, middle_name, last_name, email, mobile_no, course, specialization, NULL as year_level, NULL as profile_image FROM students WHERE student_id = ?");
    }
    
    $stmt->bind_param("s", $studentId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    header('Content-Type: application/json; charset=utf-8');
    
    if ($student = $result->fetch_assoc()) {
        $student['full_name'] = trim($student['first_name'] . ' ' . $student['middle_name'] . ' ' . $student['last_name']);
        echo json_encode(['success' => true, 'data' => $student, 'debug' => ['hasYearLevel' => $hasYearLevel, 'hasProfileImage' => $hasProfileImage]]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Student not found.']);
    }
    
    $stmt->close();
    exit;
}

// Form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $book_title = trim($_POST['book_title'] ?? '');
    $student_name = trim($_POST['student_name'] ?? '');
    $issued_date_raw = $_POST['issued_date'] ?? date('Y-m-d H:i:s');
    $return_date_raw = $_POST['return_date'] ?? date('Y-m-d H:i:s', strtotime('+7 days'));
    $remarks = $_POST['remarks'] ?? '';

    $issued_date = date('Y-m-d H:i:s', strtotime($issued_date_raw));
    $return_date = date('Y-m-d H:i:s', strtotime($return_date_raw));

    if (empty($book_title)) {
        $error = "Please select a book by entering ISBN.";
    } elseif (empty($student_name)) {
        $error = "Please select a student by entering Student ID.";
    } elseif (strtotime($return_date) <= strtotime($issued_date)) {
        $error = "Return date must be later than issued date.";
    } else {
        $bookStmt = $conn->prepare("SELECT book_id, quantity FROM books WHERE title = ?");
        $bookStmt->bind_param("s", $book_title);
        $bookStmt->execute();
        $bookStmt->bind_result($book_id, $quantity);
        $bookStmt->fetch();
        $bookStmt->close();

        $studentStmt = $conn->prepare("SELECT student_id FROM students WHERE CONCAT(first_name, ' ', middle_name, ' ', last_name) = ?");
        $studentStmt->bind_param("s", $student_name);
        $studentStmt->execute();
        $studentStmt->bind_result($student_id);
        $studentStmt->fetch();
        $studentStmt->close();

        if (!$book_id || !$student_id) {
            $error = "Invalid book title or student name.";
        } elseif ($quantity <= 0) {
            $error = "Book is out of stock.";
        } else {
            $stmt = $conn->prepare("INSERT INTO issued_books (book_id, student_id, issued_date, due_date, return_status, fine, remarks)
                                    VALUES (?, ?, ?, ?, 0, '', ?)");
            $stmt->bind_param("issss", $book_id, $student_id, $issued_date, $return_date, $remarks);

            if ($stmt->execute()) {
                $conn->query("UPDATE books SET quantity = quantity - 1 WHERE book_id = $book_id");
                $success = "Book issued successfully!";
                $logger->write("Issued book: $book_title to student: $student_name");
                $_POST = []; 
            } else {
                $error = "Error issuing book.";
            }
            $stmt->close();
        }
    }
}

include('includes/header.php'); 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Issue Book</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <style>
        .input-group { position: relative; display: flex; gap: 10px; align-items: center; }
        .input-group input { flex: 1; }
        .scan-btn { background: #28a745; color: white; border: none; padding: 10px 16px; border-radius: 4px; cursor: pointer; font-size: 14px; transition: background .3s; display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; }
        .scan-btn:hover { background: #218838; }
        .scan-btn.scanning { background: #dc3545; }
        .scan-btn.scanning:hover { background: #c82333; }
        .scanner-modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,.9); }
        .scanner-content { position: relative; margin: 2% auto; width: 90%; max-width: 640px; }
        .scanner-header { background: #fff; padding: 15px; border-radius: 8px 8px 0 0; display: flex; justify-content: space-between; align-items: center; }
        .scanner-header h3 { margin: 0; color: #333; font-size: 18px; }
        .close-scanner { background: #dc3545; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-size: 14px; }
        .close-scanner:hover { background: #c82333; }
        #scanner-container { position: relative; width: 100%; background: #000; border-radius: 0 0 8px 8px; overflow: hidden; }
        #scanner-video { width: 100%; height: auto; display: block; }
        .scanner-overlay { position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%); width: 80%; max-width: 300px; height: 150px; border: 3px solid #28a745; border-radius: 8px; box-shadow: 0 0 0 9999px rgba(0,0,0,.5); }
        .scanner-line { position: absolute; width: 100%; height: 2px; background: #28a745; animation: scan 2s linear infinite; }
        @keyframes scan { 0%,100% { top:0; } 50% { top:100%; } }
        .scanner-instructions { position: absolute; bottom: 20px; left: 50%; transform: translateX(-50%); background: rgba(0,0,0,.7); color: white; padding: 10px 20px; border-radius: 4px; text-align: center; font-size: 14px; }
        .input-hint { font-size: 12px; color: #666; margin-top: 5px; }
        .info-card { background: #fff; padding: 15px; border-radius: 8px; margin-top: 10px; display: none; border: 2px solid #28a745; box-shadow: 0 2px 8px rgba(0,0,0,.1); }
        .info-card.show { display: block; }
        .preview-grid { display: grid; grid-template-columns: 120px 1fr; gap: 15px; align-items: start; }
        .preview-cover { width: 120px; }
        .preview-cover img { width: 100%; border-radius: 4px; box-shadow: 0 2px 6px rgba(0,0,0,.15); object-fit: cover; }
        .preview-cover .no-image, .preview-avatar .no-image { width: 100%; height: 160px; background: #e9ecef; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: #6c757d; font-size: 14px; text-align: center; flex-direction: column; gap: 5px; }
        .preview-avatar { width: 120px; }
        .preview-avatar img { width: 100%; height: 160px; border-radius: 4px; box-shadow: 0 2px 6px rgba(0,0,0,.15); object-fit: cover; }
        .preview-avatar .no-image { height: 160px; }
        .preview-details { flex: 1; }
        .preview-title { font-size: 16px; font-weight: 600; color: #2c3e50; margin: 0 0 10px 0; }
        .preview-info { margin-bottom: 8px; color: #555; font-size: 14px; }
        .preview-info strong { display: inline-block; width: 110px; color: #333; }
        .stock-badge { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; color: white; margin-left: 8px; }
        .stock-available { background: #28a745; }
        .stock-low { background: #ffc107; color: #000; }
        .stock-out { background: #dc3545; }
        
        /* Message fade out animation */
        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }
        .fade-out {
            animation: fadeOut 0.5s ease-in-out forwards;
        }
    </style>
</head>
<body>

<div class="signup-container">
    <h3>Issue Book</h3>

    <?php if ($error): ?>
        <div class="custom-error" id="errorMessage"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="custom-error" id="successMessage" style="background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb;">
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <!-- Book Selection -->
        <div class="form-group">
            <label>Book (ISBN)</label>
            <div class="input-group">
                <input type="text" name="book_isbn" id="bookIsbn" placeholder="Enter or scan ISBN number" required>
                <button type="button" id="scanBookBtn" class="scan-btn">
                    <i class="fas fa-barcode"></i> Scan
                </button>
            </div>
            <div class="input-hint">💡 Type ISBN or scan barcode to find book</div>
            <input type="hidden" name="book_title" id="bookTitleHidden" required>
            
            <div id="bookInfoDisplay" class="info-card">
                <div class="preview-grid">
                    <div class="preview-cover" id="bookCoverContainer">
                        <div class="no-image">
                            <i class="fas fa-book" style="font-size: 24px;"></i>
                            <span>No Image</span>
                        </div>
                    </div>
                    <div class="preview-details">
                        <h4 class="preview-title" id="bookTitle"></h4>
                        <div class="preview-info">
                            <strong>Author:</strong> <span id="bookAuthor"></span>
                        </div>
                        <div class="preview-info">
                            <strong>Category:</strong> <span id="bookCategory"></span>
                        </div>
                        <div class="preview-info">
                            <strong>ISBN:</strong> <span id="bookIsbnDisplay"></span>
                        </div>
                        <div class="preview-info">
                            <strong>Available:</strong> 
                            <span id="bookQuantity"></span>
                            <span id="stockBadge" class="stock-badge"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Student Selection -->
        <div class="form-group">
            <label>Student (ID)</label>
            <div class="input-group">
                <input type="text" name="student_id_input" id="studentId" placeholder="Enter student ID and press Enter" required>
                <button type="button" id="searchStudentBtn" class="scan-btn" style="background: #007bff;">
                    <i class="fas fa-search"></i> Search
                </button>
            </div>
            <div class="input-hint">💡 Type student ID and press Enter or click Search</div>
            <input type="hidden" name="student_name" id="studentNameHidden" required>
            
            <div id="studentInfoDisplay" class="info-card">
                <div class="preview-grid">
                    <div class="preview-avatar" id="studentAvatarContainer">
                        <div class="no-image">
                            <i class="fas fa-user" style="font-size: 24px;"></i>
                            <span>No Photo</span>
                        </div>
                    </div>
                    <div class="preview-details">
                        <h4 class="preview-title" id="studentName"></h4>
                        <div class="preview-info">
                            <strong>Student ID:</strong> <span id="studentIdDisplay"></span>
                        </div>
                        <div class="preview-info">
                            <strong>Course:</strong> <span id="studentCourse"></span>
                        </div>
                        <div class="preview-info">
                            <strong>Specialization:</strong> <span id="studentSpecialization"></span>
                        </div>
                        <div class="preview-info">
                            <strong>Year Level:</strong> <span id="studentYear"></span>
                        </div>
                        <div class="preview-info">
                            <strong>Email:</strong> <span id="studentEmail"></span>
                        </div>
                        <div class="preview-info">
                            <strong>Mobile:</strong> <span id="studentMobile"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label>Issued Date</label>
            <input type="datetime-local" name="issued_date" value="<?php echo $_POST['issued_date'] ?? date('Y-m-d\TH:i'); ?>" required>
        </div>

        <div class="form-group">
            <label>Return Date</label>
            <input type="datetime-local" name="return_date" value="<?php echo $_POST['return_date'] ?? date('Y-m-d\TH:i', strtotime('+7 days')); ?>" required>
        </div>

        <div class="form-group">
            <label>Remarks</label>
            <input type="text" name="remarks" value="<?php echo $_POST['remarks'] ?? ''; ?>">
        </div>

        <div class="form-actions">
            <button type="submit" class="btn">Issue Book</button>
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
            <div class="scanner-instructions">Position the barcode within the green frame</div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/quagga/0.12.1/quagga.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const scanBookBtn = document.getElementById('scanBookBtn');
    const searchStudentBtn = document.getElementById('searchStudentBtn');
    const bookIsbn = document.getElementById('bookIsbn');
    const studentId = document.getElementById('studentId');
    const scannerModal = document.getElementById('scannerModal');
    const closeScanner = document.getElementById('closeScanner');
    const bookInfoDisplay = document.getElementById('bookInfoDisplay');
    const studentInfoDisplay = document.getElementById('studentInfoDisplay');
    
    let scannerActive = false;
    let bookTypingTimer;
    const typingDelay = 800;

    // Auto-hide success/error messages after 5 seconds
    const errorMessage = document.getElementById('errorMessage');
    const successMessage = document.getElementById('successMessage');
    
    if (errorMessage) {
        setTimeout(() => {
            errorMessage.classList.add('fade-out');
            setTimeout(() => errorMessage.remove(), 500);
        }, 5000);
    }
    
    if (successMessage) {
        setTimeout(() => {
            successMessage.classList.add('fade-out');
            setTimeout(() => successMessage.remove(), 500);
        }, 5000);
    }

    // Book ISBN input detection
    bookIsbn.addEventListener('input', function() {
        clearTimeout(bookTypingTimer);
        const input = this.value.trim();
        
        const isbnPattern = /^[\d\-X]{10,17}$/i;
        if (isbnPattern.test(input)) {
            bookTypingTimer = setTimeout(() => {
                const cleanISBN = input.replace(/[^0-9X]/gi, '');
                if (cleanISBN.length >= 10) {
                    lookupBookByISBN(cleanISBN);
                }
            }, typingDelay);
        } else {
            bookInfoDisplay.classList.remove('show');
        }
    });

    // Student ID search on Enter key
    studentId.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const input = this.value.trim();
            if (input) {
                lookupStudent(input);
            }
        }
    });

    // Student ID search button
    searchStudentBtn.addEventListener('click', function() {
        const input = studentId.value.trim();
        if (input) {
            lookupStudent(input);
        } else {
            alert('Please enter a student ID');
        }
    });

    // Scan button
    scanBookBtn.addEventListener('click', () => {
        if (scannerActive) {
            stopScanner();
        } else {
            startScanner();
        }
    });

    closeScanner.addEventListener('click', stopScanner);

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && scannerActive) {
            stopScanner();
        }
    });

    function startScanner() {
        scannerModal.style.display = 'block';
        scanBookBtn.classList.add('scanning');
        scanBookBtn.innerHTML = '<i class="fas fa-stop"></i> Stop';
        scannerActive = true;

        Quagga.init({
            inputStream: {
                name: "Live",
                type: "LiveStream",
                target: document.querySelector('#scanner-video'),
                constraints: { 
                    facingMode: "environment",
                    width: { ideal: 1280 },
                    height: { ideal: 720 }
                }
            },
            decoder: { 
                readers: [
                    "ean_reader",
                    "ean_8_reader",
                    "upc_reader",
                    "upc_e_reader",
                    "code_128_reader",
                    "code_39_reader"
                ]
            },
            locate: true
        }, err => {
            if (err) {
                alert('Camera access denied or unavailable.');
                stopScanner();
                return;
            }
            Quagga.start();
        });

        Quagga.onDetected(result => {
            const code = result.codeResult.code;
            if (code && code.length >= 10) {
                stopScanner();
                bookIsbn.value = code;
                lookupBookByISBN(code);
            }
        });
    }

    function stopScanner() {
        if (scannerActive) {
            Quagga.stop();
        }
        scannerActive = false;
        scannerModal.style.display = 'none';
        scanBookBtn.classList.remove('scanning');
        scanBookBtn.innerHTML = '<i class="fas fa-barcode"></i> Scan';
    }

    function lookupBookByISBN(isbn) {
        bookInfoDisplay.classList.remove('show');
        
        fetch(`?lookup_isbn=${encodeURIComponent(isbn)}`)
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const book = data.data;
                    displayBookInfo(book);
                    document.getElementById('bookTitleHidden').value = book.title;
                    
                    bookIsbn.style.borderColor = '#28a745';
                    setTimeout(() => {
                        bookIsbn.style.borderColor = '';
                    }, 2000);
                } else {
                    bookInfoDisplay.classList.remove('show');
                    alert(data.message);
                }
            })
            .catch(err => {
                console.error('Lookup error:', err);
                alert('Error looking up book. Please try again.');
            });
    }

    function lookupStudent(studentIdValue) {
        studentInfoDisplay.classList.remove('show');
        
        fetch(`?lookup_student=${encodeURIComponent(studentIdValue)}`)
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const student = data.data;
                    displayStudentInfo(student);
                    document.getElementById('studentNameHidden').value = student.full_name;
                    
                    studentId.style.borderColor = '#28a745';
                    setTimeout(() => {
                        studentId.style.borderColor = '';
                    }, 2000);
                } else {
                    studentInfoDisplay.classList.remove('show');
                    alert(data.message);
                }
            })
            .catch(err => {
                console.error('Lookup error:', err);
                alert('Error looking up student. Please try again.');
            });
    }

    function displayBookInfo(book) {
        const coverContainer = document.getElementById('bookCoverContainer');
        if (book.image) {
            const imageSrc = book.image.startsWith('http') 
                ? book.image 
                : 'uploads/' + book.image;
            
            coverContainer.innerHTML = `<img src="${imageSrc}" alt="${book.title}" onerror="this.parentElement.innerHTML='<div class=\\'no-image\\'><i class=\\'fas fa-book\\' style=\\'font-size:24px;\\'></i><span>No Image</span></div>'">`;
        } else {
            coverContainer.innerHTML = '<div class="no-image"><i class="fas fa-book" style="font-size:24px;"></i><span>No Image</span></div>';
        }
        
        document.getElementById('bookTitle').textContent = book.title;
        document.getElementById('bookAuthor').textContent = book.author || 'Unknown';
        document.getElementById('bookCategory').textContent = book.category || 'N/A';
        document.getElementById('bookIsbnDisplay').textContent = book.isbn;
        document.getElementById('bookQuantity').textContent = book.quantity + ' copies';
        
        const stockBadge = document.getElementById('stockBadge');
        if (book.quantity > 5) {
            stockBadge.textContent = 'In Stock';
            stockBadge.className = 'stock-badge stock-available';
        } else if (book.quantity > 0) {
            stockBadge.textContent = 'Low Stock';
            stockBadge.className = 'stock-badge stock-low';
        } else {
            stockBadge.textContent = 'Out of Stock';
            stockBadge.className = 'stock-badge stock-out';
        }
        
        bookInfoDisplay.classList.add('show');
    }

    function displayStudentInfo(student) {
        console.log('Student data received:', student); // Debug log
        
        const avatarContainer = document.getElementById('studentAvatarContainer');
        if (student.profile_image && student.profile_image !== 'null' && student.profile_image !== '') {
            const imageSrc = student.profile_image.startsWith('http') 
                ? student.profile_image 
                : 'uploads/students/' + student.profile_image;
            
            console.log('Loading image from:', imageSrc); // Debug log
            
            avatarContainer.innerHTML = `<img src="${imageSrc}" alt="${student.full_name}" onerror="console.error('Image failed to load'); this.parentElement.innerHTML='<div class=\\'no-image\\'><i class=\\'fas fa-user\\' style=\\'font-size:24px;\\'></i><span>No Photo</span></div>'">`;
        } else {
            console.log('No profile image found'); // Debug log
            avatarContainer.innerHTML = '<div class="no-image"><i class="fas fa-user" style="font-size:24px;"></i><span>No Photo</span></div>';
        }
        
        document.getElementById('studentName').textContent = student.full_name;
        document.getElementById('studentIdDisplay').textContent = student.student_id;
        document.getElementById('studentCourse').textContent = student.course || 'N/A';
        document.getElementById('studentSpecialization').textContent = student.specialization || 'N/A';
        document.getElementById('studentYear').textContent = student.year_level || 'N/A';
        document.getElementById('studentEmail').textContent = student.email || 'N/A';
        document.getElementById('studentMobile').textContent = student.mobile_no || 'N/A';
        
        console.log('Year level displayed:', student.year_level); // Debug log
        
        studentInfoDisplay.classList.add('show');
    }
});
</script>

</body>
</html>
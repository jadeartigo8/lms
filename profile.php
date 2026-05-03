<?php
session_start();
error_reporting(E_ALL);
include('connection/db.php');

if (strlen($_SESSION['login']) == 0) {
    header('location:index.php');
    exit();
}

$studentID = $_SESSION['stdid'];

// Fetch student information
$studentQuery = "SELECT * FROM students WHERE student_id = ?";
$stmt = $conn->prepare($studentQuery);
$stmt->bind_param("s", $studentID);
$stmt->execute();
$studentResult = $stmt->get_result();
$student = $studentResult->fetch_assoc();

// Borrowing Statistics
$totalBorrowed = $conn->query("
    SELECT COUNT(*) AS count 
    FROM issued_books 
    WHERE student_id = '$studentID'
")->fetch_assoc()['count'] ?? 0;

$currentlyBorrowed = $conn->query("
    SELECT COUNT(*) AS count 
    FROM issued_books 
    WHERE student_id = '$studentID' AND return_status = 0
")->fetch_assoc()['count'] ?? 0;

$overdueBooks = $conn->query("
    SELECT COUNT(*) AS count 
    FROM issued_books 
    WHERE student_id = '$studentID' 
      AND return_status = 0 
      AND due_date < CURDATE()
")->fetch_assoc()['count'] ?? 0;

$totalFines = $conn->query("
    SELECT COALESCE(SUM(CAST(fine AS DECIMAL(10,2))), 0) AS total
    FROM issued_books
    WHERE student_id = '$studentID' AND return_status = 1
")->fetch_assoc()['total'] ?? 0;

// Last borrowed book
$lastBorrowedQuery = "
    SELECT b.title, ib.issued_date 
    FROM issued_books ib
    JOIN books b ON ib.book_id = b.book_id
    WHERE ib.student_id = '$studentID'
    ORDER BY ib.issued_date DESC
    LIMIT 1
";
$lastBorrowed = $conn->query($lastBorrowedQuery)->fetch_assoc();

// Most borrowed category
$mostBorrowedCategoryQuery = "
    SELECT b.category, COUNT(*) AS count
    FROM issued_books ib
    JOIN books b ON ib.book_id = b.book_id
    WHERE ib.student_id = '$studentID'
    GROUP BY b.category
    ORDER BY count DESC
    LIMIT 1
";
$mostBorrowedCategory = $conn->query($mostBorrowedCategoryQuery)->fetch_assoc();

// Current borrowings
$currentBorrowingsQuery = "
    SELECT ib.*, b.title, b.author, b.isbn
    FROM issued_books ib
    JOIN books b ON ib.book_id = b.book_id
    WHERE ib.student_id = ? AND ib.return_status = 0
    ORDER BY ib.issued_date DESC
";
$stmt = $conn->prepare($currentBorrowingsQuery);
$stmt->bind_param("s", $studentID);
$stmt->execute();
$currentBorrowings = $stmt->get_result();

// Borrowing history
$historyQuery = "
    SELECT ib.*, b.title, b.author, b.isbn
    FROM issued_books ib
    JOIN books b ON ib.book_id = b.book_id
    WHERE ib.student_id = ? AND ib.return_status = 1
    ORDER BY ib.actual_return DESC
    LIMIT 10
";
$stmt = $conn->prepare($historyQuery);
$stmt->bind_param("s", $studentID);
$stmt->execute();
$borrowingHistory = $stmt->get_result();

// Account status text
$statusText  = $student['status'] == 1 ? 'Active' : 'Inactive';
$statusClass = $student['status'] == 1 ? 'status-active' : 'status-inactive';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --navy: #000435;
            --gold: #ffde59;
            --light-bg: #f8f9fa;
        }

        body {
            background: var(--light-bg);
            font-family: 'Montserrat', sans-serif;
        }

        .profile-container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 1.25rem;
            box-sizing: border-box;
            width: 100%;
        }

        .profile-header {
            background: linear-gradient(135deg, var(--navy), #001a52);
            color: white;
            padding: 1.5rem 2rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            box-shadow: 0 8px 25px rgba(0,0,0,.2);
            box-sizing: border-box;
        }

        .profile-header h1 {
            margin: 0;
            font-size: 1.8rem;
            color: white;
        }

        .profile-layout {
            display: block;
            grid-template-columns: 320px 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
            width: 100%;
            box-sizing: border-box;
        }

        /* Left Column */
        .profile-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,.1);
            height: fit-content;
            width: 100%;
            box-sizing: border-box;
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .profile-photo-container {
            text-align: center;
            margin-bottom: 1.5rem;
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .profile-photo {
            width: 130px;
            height: 130px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--gold);
            margin: 0 auto 1rem auto;
            display: block;
        }

        .profile-photo-placeholder {
            width: 130px;
            height: 130px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--navy), #001a52);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem auto;
            border: 4px solid var(--gold);
        }

        .profile-photo-placeholder i {
            font-size: 3.5rem;
            color: var(--gold);
        }

        .profile-name {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--navy);
            margin-bottom: 0.4rem;
        }

        .profile-id {
            color: #666;
            font-size: 0.95rem;
            margin-bottom: 0.75rem;
        }

        .profile-info-item {
            padding: 0.85rem 0;
            border-bottom: 1px solid #eee;
            width: 100%;
            box-sizing: border-box;
            word-break: break-word;
        }

        .profile-info-item:last-child {
            border-bottom: none;
        }

        .profile-info-label {
            font-size: 0.8rem;
            color: #888;
            margin-bottom: 0.2rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .profile-info-label i { color: var(--navy); }

        .profile-info-value {
            font-size: 0.95rem;
            color: #333;
            font-weight: 500;
        }

        .status-badge {
            display: inline-block;
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-active   { background: #d1e7dd; color: #0f5132; }
        .status-inactive { background: #f8d7da; color: #842029; }

        /* Action Buttons — scoped to avoid styles.css override */
        .action-buttons {
            display: flex;
            gap: 0.75rem;
            margin-top: 1.5rem;
            flex-wrap: wrap;
        }

        .profile-card .btn {
            padding: 0.65rem 1.25rem !important;
            border: none !important;
            border-radius: 8px !important;
            font-weight: 600 !important;
            cursor: pointer !important;
            transition: all 0.3s ease !important;
            text-decoration: none !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 0.4rem !important;
            font-size: 0.875rem !important;
            width: auto !important;
            max-width: none !important;
            box-sizing: border-box !important;
        }

        .profile-card .btn-primary  { background: var(--navy) !important; color: white !important; }
        .profile-card .btn-primary:hover  { background: #001a52 !important; }
        .profile-card .btn-secondary { background: #6c757d !important; color: white !important; }
        .profile-card .btn-secondary:hover { background: #5a6268 !important; }

        /* Right Column - Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: var(--gold);
            color: var(--navy);
            padding: 1.25rem;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,.1);
            transition: transform 0.3s ease;
        }

        .stat-card:hover { transform: translateY(-4px); }
        .stat-card.danger  { background: #f8d7da; color: #842029; }
        .stat-card.warning { background: #fff3cd; color: #856404; }
        .stat-card.info    { background: #cfe2ff; color: #084298; }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.4rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .stat-label {
            font-size: 0.85rem;
            font-weight: 600;
        }

        /* Info Cards */
        .info-card {
            background: white;
            border-radius: 12px;
            padding: 1.25rem 1.5rem;
            box-shadow: 0 4px 12px rgba(0,0,0,.08);
            margin-bottom: 1rem;
        }

        .info-card .profile-info-label {
            font-size: 0.8rem;
            color: #888;
            margin-bottom: 0.4rem;
        }

        .info-card .profile-info-value {
            font-size: 1rem;
            font-weight: 600;
            color: var(--navy);
        }

        .info-card small {
            font-size: 0.8rem;
            color: #888;
            display: block;
            margin-top: 0.2rem;
        }

        /* Tabs */
        .tabs-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,.1);
            overflow: hidden;
            margin-top: 1rem;
        }

        .tabs-header {
            display: flex;
            border-bottom: 2px solid #e0e0e0;
            background: #f8f9fa;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .tab-button {
            flex: 1;
            min-width: 140px;
            padding: 1rem 0.75rem;
            border: none;
            background: transparent;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.875rem;
            color: #666;
            transition: all 0.3s ease;
            position: relative;
            white-space: nowrap;
            font-family: 'Montserrat', sans-serif;
        }

        .tab-button:hover { background: #e9ecef; }

        .tab-button.active {
            color: var(--navy);
            background: white;
        }

        .tab-button.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--navy);
        }

        .tab-content {
            padding: 1.5rem;
            display: none;
        }

        .tab-content.active { display: block; }

        /* Table */
        .borrowing-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 0.5rem;
        }

        .borrowing-table th {
            background: var(--navy);
            color: white;
            padding: 0.85rem 1rem;
            text-align: left;
            font-size: 0.875rem;
        }

        .borrowing-table td {
            padding: 0.85rem 1rem;
            border-bottom: 1px solid #e0e0e0;
            font-size: 0.875rem;
        }

        .borrowing-table tr:hover { background: #f8f9fa; }

        .book-status {
            display: inline-block;
            padding: 0.3rem 0.7rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .book-status.borrowed { background: #cfe2ff; color: #084298; }
        .book-status.overdue  { background: #f8d7da; color: #842029; }
        .book-status.returned { background: #d1e7dd; color: #0f5132; }

        .empty-state {
            text-align: center;
            padding: 2.5rem;
            color: #666;
        }

        .empty-state i {
            font-size: 3.5rem;
            color: #ddd;
            margin-bottom: 1rem;
            display: block;
        }

        /* TABLET */
        @media (max-width: 1024px) {
            .profile-layout {
                grid-template-columns: 1fr;
            }
        }

        /* MOBILE */
        @media (max-width: 768px) {
            .profile-container {
                padding: 0 1rem;
                margin: 1rem auto;
            }

            .profile-header {
                padding: 1.1rem 1.25rem;
                border-radius: 10px;
                margin-bottom: 1rem;
            }

            .profile-header h1 { font-size: 1.3rem; }

            .profile-card {
                padding: 1.25rem;
                border-radius: 10px;
            }

            .stats-grid {
                grid-template-columns: 1fr 1fr;
                gap: 0.75rem;
            }

            .stat-card { padding: 1rem; }
            .stat-value { font-size: 1.6rem; }
            .stat-label { font-size: 0.8rem; }

            .tabs-header { flex-direction: row; }

            .tab-button {
                min-width: 120px;
                font-size: 0.8rem;
                padding: 0.85rem 0.5rem;
            }

            .tab-content { padding: 1rem 0.75rem; }

            /* Scrollable table on mobile */
            .tab-content {
                overflow-x: auto;
            }

            .borrowing-table th,
            .borrowing-table td {
                padding: 0.65rem 0.75rem;
                font-size: 0.8rem;
                white-space: nowrap;
            }

            .action-buttons {
                flex-direction: column;
                gap: 0.6rem;
            }

            .profile-card .btn {
                width: 100% !important;
                justify-content: center !important;
            }
        }

        /* EXTRA SMALL */
        @media (max-width: 480px) {
            .profile-container { padding: 0 0.875rem; }

            .stats-grid {
                grid-template-columns: 1fr 1fr;
                gap: 0.6rem;
            }

            .stat-card {
                padding: 0.875rem 0.75rem;
            }

            .stat-value { font-size: 1.4rem; }

            .profile-name { font-size: 1.15rem; }

            .tab-button {
                min-width: 100px;
                font-size: 0.75rem;
                padding: 0.75rem 0.4rem;
            }
        }
    </style>
</head>
<body>
    <?php include('includes/header.php'); ?>

    <div class="profile-container">
        <div class="profile-header">
            <h1><i class="fas fa-user-circle"></i> My Profile</h1>
        </div>

        <div class="profile-layout">
            <!-- Left Column: Profile Summary -->
            <div class="profile-card">
                <div class="profile-photo-container">
                    <?php if (!empty($student['profile_image']) && file_exists('admin/uploads/students/' . $student['profile_image'])): ?>
                        <img src="admin/uploads/students/<?= htmlspecialchars($student['profile_image']) ?>" 
                             alt="Profile Photo" class="profile-photo">
                    <?php else: ?>
                        <div class="profile-photo-placeholder">
                            <i class="fas fa-user"></i>
                        </div>
                    <?php endif; ?>
                    
                    <div class="profile-name">
                        <?= htmlspecialchars($student['first_name'] . ' ' . 
                            ($student['middle_name'] ? $student['middle_name'] . ' ' : '') . 
                            $student['last_name']) ?>
                    </div>
                    <div class="profile-id"><?= htmlspecialchars($student['student_id']) ?></div>
                    <span class="status-badge <?= $statusClass ?>"><?= $statusText ?></span>
                </div>

                <div class="profile-info-item">
                    <div class="profile-info-label"><i class="fas fa-graduation-cap"></i> Course</div>
                    <div class="profile-info-value"><?= htmlspecialchars($student['course']) ?></div>
                </div>

                <?php if (!empty($student['specialization'])): ?>
                <div class="profile-info-item">
                    <div class="profile-info-label"><i class="fas fa-book-open"></i> Specialization</div>
                    <div class="profile-info-value"><?= htmlspecialchars($student['specialization']) ?></div>
                </div>
                <?php endif; ?>

                <div class="profile-info-item">
                    <div class="profile-info-label"><i class="fas fa-layer-group"></i> Year Level</div>
                    <div class="profile-info-value"><?= htmlspecialchars($student['year_level']) ?></div>
                </div>

                <div class="profile-info-item">
                    <div class="profile-info-label"><i class="fas fa-envelope"></i> Email</div>
                    <div class="profile-info-value"><?= htmlspecialchars($student['email']) ?></div>
                </div>

                <div class="profile-info-item">
                    <div class="profile-info-label"><i class="fas fa-phone"></i> Mobile</div>
                    <div class="profile-info-value"><?= htmlspecialchars($student['mobile_no']) ?></div>
                </div>

                <div class="profile-info-item">
                    <div class="profile-info-label"><i class="fas fa-calendar-plus"></i> Member Since</div>
                    <div class="profile-info-value"><?= date('F j, Y', strtotime($student['registration_date'])) ?></div>
                </div>

                <div class="profile-info-item">
                    <div class="profile-info-label"><i class="fas fa-clock"></i> Last Updated</div>
                    <div class="profile-info-value"><?= date('F j, Y', strtotime($student['update_date'])) ?></div>
                </div>

                <div class="action-buttons">
                    <a href="edit-profile.php" class="btn btn-primary">
                        <i class="fas fa-edit"></i> Edit Profile
                    </a>
                    <a href="change-password.php" class="btn btn-secondary">
                        <i class="fas fa-key"></i> Change Password
                    </a>
                </div>
            </div>

            <!-- Right Column: Stats & Activity -->
            <div>
                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><i class="fas fa-book"></i> <?= $totalBorrowed ?></div>
                        <div class="stat-label">Total Borrowed</div>
                    </div>
                    <div class="stat-card info">
                        <div class="stat-value"><i class="fas fa-bookmark"></i> <?= $currentlyBorrowed ?></div>
                        <div class="stat-label">Currently Borrowed</div>
                    </div>
                    <div class="stat-card danger">
                        <div class="stat-value"><i class="fas fa-exclamation-triangle"></i> <?= $overdueBooks ?></div>
                        <div class="stat-label">Overdue Books</div>
                    </div>
                    <div class="stat-card warning">
                        <div class="stat-value"><i class="fas fa-coins"></i> ₱<?= number_format((float)$totalFines, 2) ?></div>
                        <div class="stat-label">Total Fines</div>
                    </div>
                </div>

                <!-- Additional Info Cards -->
                <?php if ($lastBorrowed): ?>
                <div class="info-card">
                    <div class="profile-info-label"><i class="fas fa-history"></i> Last Borrowed Book</div>
                    <div class="profile-info-value"><?= htmlspecialchars($lastBorrowed['title']) ?></div>
                    <small><?= date('F j, Y', strtotime($lastBorrowed['issued_date'])) ?></small>
                </div>
                <?php endif; ?>

                <?php if ($mostBorrowedCategory): ?>
                <div class="info-card">
                    <div class="profile-info-label"><i class="fas fa-star"></i> Favorite Category</div>
                    <div class="profile-info-value"><?= htmlspecialchars($mostBorrowedCategory['category']) ?></div>
                    <small><?= $mostBorrowedCategory['count'] ?> books borrowed</small>
                </div>
                <?php endif; ?>

                <!-- Tabs Section -->
                <div class="tabs-container">
                    <div class="tabs-header">
                        <button class="tab-button active" onclick="switchTab(event, 'current')">
                            <i class="fas fa-book-reader"></i> Current Borrowings
                        </button>
                        <button class="tab-button" onclick="switchTab(event, 'history')">
                            <i class="fas fa-history"></i> Borrowing History
                        </button>
                    </div>

                    <!-- Current Borrowings Tab -->
                    <div id="current-tab" class="tab-content active">
                        <?php if ($currentBorrowings->num_rows > 0): ?>
                            <table class="borrowing-table">
                                <thead>
                                    <tr>
                                        <th>Book Title</th>
                                        <th>Author</th>
                                        <th>Issued Date</th>
                                        <th>Due Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($book = $currentBorrowings->fetch_assoc()): 
                                        $isOverdue   = $book['due_date'] < date('Y-m-d');
                                        $bStatusClass = $isOverdue ? 'overdue' : 'borrowed';
                                        $bStatusText  = $isOverdue ? 'Overdue' : 'Borrowed';
                                    ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($book['title']) ?></strong></td>
                                        <td><?= htmlspecialchars($book['author']) ?></td>
                                        <td><?= date('M j, Y', strtotime($book['issued_date'])) ?></td>
                                        <td><?= date('M j, Y', strtotime($book['due_date'])) ?></td>
                                        <td><span class="book-status <?= $bStatusClass ?>"><?= $bStatusText ?></span></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-book-open"></i>
                                <h3>No Current Borrowings</h3>
                                <p>You don't have any books borrowed at the moment.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Borrowing History Tab -->
                    <div id="history-tab" class="tab-content">
                        <?php if ($borrowingHistory->num_rows > 0): ?>
                            <table class="borrowing-table">
                                <thead>
                                    <tr>
                                        <th>Book Title</th>
                                        <th>Author</th>
                                        <th>Borrowed</th>
                                        <th>Returned</th>
                                        <th>Fine</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($book = $borrowingHistory->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($book['title']) ?></strong></td>
                                        <td><?= htmlspecialchars($book['author']) ?></td>
                                        <td><?= date('M j, Y', strtotime($book['issued_date'])) ?></td>
                                        <td><?= date('M j, Y', strtotime($book['actual_return'])) ?></td>
                                        <td>₱<?= number_format((float)$book['fine'], 2) ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-history"></i>
                                <h3>No Borrowing History</h3>
                                <p>You haven't returned any books yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function switchTab(event, tabName) {
            // Use currentTarget so clicking the icon still works
            const btn = event.currentTarget;

            document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.tab-button').forEach(b => b.classList.remove('active'));

            document.getElementById(tabName + '-tab').classList.add('active');
            btn.classList.add('active');
        }
    </script>
</body>
</html>
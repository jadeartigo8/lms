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
$statusText = $student['status'] == 1 ? 'Active' : 'Inactive';
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
            padding: 0 1rem;
        }

        .profile-header {
            background: linear-gradient(135deg, var(--navy), #001a52);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: 0 8px 25px rgba(0,0,0,.2);
        }

        .profile-header h1 {
            margin: 0;
            font-size: 2rem;
            color: white;
        }

        .profile-layout {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        /* Left Column - Profile Card */
        .profile-card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 15px rgba(0,0,0,.1);
            height: fit-content;
        }

        .profile-photo-container {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .profile-photo {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--gold);
            margin-bottom: 1rem;
        }

        .profile-photo-placeholder {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--navy), #001a52);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            border: 4px solid var(--gold);
        }

        .profile-photo-placeholder i {
            font-size: 4rem;
            color: var(--gold);
        }

        .profile-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--navy);
            margin-bottom: 0.5rem;
        }

        .profile-id {
            color: #666;
            font-size: 1rem;
            margin-bottom: 1rem;
        }

        .profile-info-item {
            padding: 1rem 0;
            border-bottom: 1px solid #eee;
        }

        .profile-info-item:last-child {
            border-bottom: none;
        }

        .profile-info-label {
            font-size: 0.85rem;
            color: #888;
            margin-bottom: 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .profile-info-label i {
            color: var(--navy);
        }

        .profile-info-value {
            font-size: 1rem;
            color: #333;
            font-weight: 500;
        }

        .status-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-active {
            background: #d1e7dd;
            color: #0f5132;
        }

        .status-inactive {
            background: #f8d7da;
            color: #842029;
        }

        /* Right Column - Stats & Activity */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--gold);
            color: var(--navy);
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,.1);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card.danger {
            background: #f8d7da;
            color: #842029;
        }

        .stat-card.warning {
            background: #fff3cd;
            color: #856404;
        }

        .stat-card.info {
            background: #cfe2ff;
            color: #084298;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .stat-label {
            font-size: 0.9rem;
            font-weight: 600;
        }

        /* Tabs */
        .tabs-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,.1);
            overflow: hidden;
        }

        .tabs-header {
            display: flex;
            border-bottom: 2px solid #e0e0e0;
            background: #f8f9fa;
        }

        .tab-button {
            flex: 1;
            padding: 1.25rem;
            border: none;
            background: transparent;
            cursor: pointer;
            font-weight: 600;
            color: #666;
            transition: all 0.3s ease;
            position: relative;
        }

        .tab-button:hover {
            background: #e9ecef;
        }

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
            padding: 2rem;
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Table Styles */
        .borrowing-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .borrowing-table th {
            background: var(--navy);
            color: white;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
        }

        .borrowing-table td {
            padding: 1rem;
            border-bottom: 1px solid #e0e0e0;
        }

        .borrowing-table tr:hover {
            background: #f8f9fa;
        }

        .book-status {
            display: inline-block;
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .book-status.borrowed {
            background: #cfe2ff;
            color: #084298;
        }

        .book-status.overdue {
            background: #f8d7da;
            color: #842029;
        }

        .book-status.returned {
            background: #d1e7dd;
            color: #0f5132;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #666;
        }

        .empty-state i {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 1rem;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: var(--navy);
            color: white;
        }

        .btn-primary:hover {
            background: #001a52;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        @media (max-width: 1024px) {
            .profile-layout {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .tabs-header {
                flex-direction: column;
            }

            .borrowing-table {
                font-size: 0.85rem;
            }

            .borrowing-table th,
            .borrowing-table td {
                padding: 0.5rem;
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
                    <div class="profile-info-label">
                        <i class="fas fa-graduation-cap"></i> Course
                    </div>
                    <div class="profile-info-value"><?= htmlspecialchars($student['course']) ?></div>
                </div>

                <?php if (!empty($student['specialization'])): ?>
                <div class="profile-info-item">
                    <div class="profile-info-label">
                        <i class="fas fa-book-open"></i> Specialization
                    </div>
                    <div class="profile-info-value"><?= htmlspecialchars($student['specialization']) ?></div>
                </div>
                <?php endif; ?>

                <div class="profile-info-item">
                    <div class="profile-info-label">
                        <i class="fas fa-layer-group"></i> Year Level
                    </div>
                    <div class="profile-info-value"><?= htmlspecialchars($student['year_level']) ?></div>
                </div>

                <div class="profile-info-item">
                    <div class="profile-info-label">
                        <i class="fas fa-envelope"></i> Email
                    </div>
                    <div class="profile-info-value"><?= htmlspecialchars($student['email']) ?></div>
                </div>

                <div class="profile-info-item">
                    <div class="profile-info-label">
                        <i class="fas fa-phone"></i> Mobile
                    </div>
                    <div class="profile-info-value"><?= htmlspecialchars($student['mobile_no']) ?></div>
                </div>

                <div class="profile-info-item">
                    <div class="profile-info-label">
                        <i class="fas fa-calendar-plus"></i> Member Since
                    </div>
                    <div class="profile-info-value">
                        <?= date('F j, Y', strtotime($student['registration_date'])) ?>
                    </div>
                </div>

                <div class="profile-info-item">
                    <div class="profile-info-label">
                        <i class="fas fa-clock"></i> Last Updated
                    </div>
                    <div class="profile-info-value">
                        <?= date('F j, Y', strtotime($student['update_date'])) ?>
                    </div>
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
                        <div class="stat-value">
                            <i class="fas fa-book"></i>
                            <?= $totalBorrowed ?>
                        </div>
                        <div class="stat-label">Total Borrowed</div>
                    </div>

                    <div class="stat-card info">
                        <div class="stat-value">
                            <i class="fas fa-bookmark"></i>
                            <?= $currentlyBorrowed ?>
                        </div>
                        <div class="stat-label">Currently Borrowed</div>
                    </div>

                    <div class="stat-card danger">
                        <div class="stat-value">
                            <i class="fas fa-exclamation-triangle"></i>
                            <?= $overdueBooks ?>
                        </div>
                        <div class="stat-label">Overdue Books</div>
                    </div>

                    <div class="stat-card warning">
                        <div class="stat-value">
                            <i class="fas fa-coins"></i>
                            ₱<?= number_format((float)$totalFines, 2) ?>
                        </div>
                        <div class="stat-label">Total Fines</div>
                    </div>
                </div>

                <!-- Additional Info Cards -->
                <?php if ($lastBorrowed): ?>
                <div class="stat-card" style="margin-bottom: 1.5rem;">
                    <div class="profile-info-label">
                        <i class="fas fa-history"></i> Last Borrowed Book
                    </div>
                    <div class="profile-info-value">
                        <?= htmlspecialchars($lastBorrowed['title']) ?>
                    </div>
                    <small style="color: #666;">
                        <?= date('F j, Y', strtotime($lastBorrowed['issued_date'])) ?>
                    </small>
                </div>
                <?php endif; ?>

                <?php if ($mostBorrowedCategory): ?>
                <div class="stat-card">
                    <div class="profile-info-label">
                        <i class="fas fa-star"></i> Favorite Category
                    </div>
                    <div class="profile-info-value">
                        <?= htmlspecialchars($mostBorrowedCategory['category']) ?>
                    </div>
                    <small style="color: #666;">
                        <?= $mostBorrowedCategory['count'] ?> books borrowed
                    </small>
                </div>
                <?php endif; ?>

                <!-- Tabs Section -->
                <div class="tabs-container">
                    <div class="tabs-header">
                        <button class="tab-button active" onclick="switchTab('current')">
                            <i class="fas fa-book-reader"></i> Current Borrowings
                        </button>
                        <button class="tab-button" onclick="switchTab('history')">
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
                                        $isOverdue = $book['due_date'] < date('Y-m-d');
                                        $statusClass = $isOverdue ? 'overdue' : 'borrowed';
                                        $statusText = $isOverdue ? 'Overdue' : 'Borrowed';
                                    ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($book['title']) ?></strong></td>
                                        <td><?= htmlspecialchars($book['author']) ?></td>
                                        <td><?= date('M j, Y', strtotime($book['issued_date'])) ?></td>
                                        <td><?= date('M j, Y', strtotime($book['due_date'])) ?></td>
                                        <td><span class="book-status <?= $statusClass ?>"><?= $statusText ?></span></td>
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
        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('active');
            });

            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');
            event.target.classList.add('active');
        }
    </script>
</body>
</html>
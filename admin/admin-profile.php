<?php
    session_start();
    error_reporting(E_ALL);
    include('../connection/db.php');

    if (strlen($_SESSION['alogin']) == 0) {
        header('location:../index.php');
        exit();
    }

    $adminEmail = $_SESSION['alogin'];

    // Fetch admin information
    $adminQuery = "SELECT * FROM admin WHERE email = ?";
    $stmt = $conn->prepare($adminQuery);
    $stmt->bind_param("s", $adminEmail);
    $stmt->execute();
    $adminResult = $stmt->get_result();
    $admin = $adminResult->fetch_assoc();

    // Build full name
    $fullName = trim(($admin['first_name'] ?? '') . ' ' . ($admin['last_name'] ?? ''));
    if (empty($fullName)) {
        $fullName = 'Administrator';
    }

    // Library Statistics
    $totalBooks = $conn->query("SELECT COUNT(*) AS count FROM books")->fetch_assoc()['count'] ?? 0;
    $totalStudents = $conn->query("SELECT COUNT(*) AS count FROM students WHERE status = 1")->fetch_assoc()['count'] ?? 0;
    $totalIssued = $conn->query("SELECT COUNT(*) AS count FROM issued_books")->fetch_assoc()['count'] ?? 0;
    $currentlyBorrowed = $conn->query("SELECT COUNT(*) AS count FROM issued_books WHERE return_status = 0")->fetch_assoc()['count'] ?? 0;
    $overdueBooks = $conn->query("SELECT COUNT(*) AS count FROM issued_books WHERE return_status = 0 AND due_date < CURDATE()")->fetch_assoc()['count'] ?? 0;
    $totalFinesCollected = $conn->query("SELECT COALESCE(SUM(CAST(fine AS DECIMAL(10,2))), 0) AS total FROM issued_books WHERE return_status = 1")->fetch_assoc()['total'] ?? 0;

    // Account status - always active if logged in
    $statusText = 'Active';
    $statusClass = 'status-active';

    // Recent Activities - Last 10 issued books
    $recentIssuesQuery = "
        SELECT ib.*, b.title, s.first_name, s.last_name, s.profile_image
        FROM issued_books ib
        JOIN books b ON ib.book_id = b.book_id
        JOIN students s ON ib.student_id = s.student_id
        ORDER BY ib.issued_date DESC
        LIMIT 10
    ";
    $recentIssues = $conn->query($recentIssuesQuery);

    // Recent Returns - Last 10 returned books
    $recentReturnsQuery = "
        SELECT ib.*, b.title, s.first_name, s.last_name, s.profile_image
        FROM issued_books ib
        JOIN books b ON ib.book_id = b.book_id
        JOIN students s ON ib.student_id = s.student_id
        WHERE ib.return_status = 1
        ORDER BY ib.actual_return DESC
        LIMIT 10
    ";
    $recentReturns = $conn->query($recentReturnsQuery);

    // Most Active Students
    $activeStudentsQuery = "
        SELECT s.student_id, s.first_name, s.last_name, s.profile_image, s.course,
            COUNT(ib.issued_books_id) AS borrow_count
        FROM students s
        JOIN issued_books ib ON s.student_id = ib.student_id
        GROUP BY s.student_id
        ORDER BY borrow_count DESC
        LIMIT 5
    ";
    $activeStudents = $conn->query($activeStudentsQuery);

    // Most Borrowed Books
    $popularBooksQuery = "
        SELECT b.book_id, b.title, b.author, b.isbn,
            COUNT(ib.issued_books_id) AS times_borrowed
        FROM books b
        JOIN issued_books ib ON b.book_id = ib.book_id
        GROUP BY b.book_id
        ORDER BY times_borrowed DESC
        LIMIT 5
    ";
    $popularBooks = $conn->query($popularBooksQuery);

    // // Account status
    // $statusText = $admin['status'] == 1 ? 'Active' : 'Inactive';
    // $statusClass = $admin['status'] == 1 ? 'status-active' : 'status-inactive';
    ?>

    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Profile</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link rel="stylesheet" href="../css/styles.css">
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
                padding: 0 2rem;
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

            .profile-role {
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
                background: white;
                padding: 1.5rem;
                border-radius: 12px;
                box-shadow: 0 4px 12px rgba(0,0,0,.1);
                transition: transform 0.3s ease;
                display: flex;
                align-items: center;
                gap: 1rem;
            }

            .stat-card:hover {
                transform: translateY(-5px);
            }

            .stat-icon {
                width: 60px;
                height: 60px;
                border-radius: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 1.5rem;
                color: white;
                flex-shrink: 0;
            }

            .stat-icon.books {
                background: linear-gradient(135deg, var(--navy), #001a52);
            }

            .stat-icon.students {
                background: linear-gradient(135deg, #28a745, #20c997);
            }

            .stat-icon.issued {
                background: linear-gradient(135deg, #ffc107, #ff9800);
            }

            .stat-icon.borrowed {
                background: linear-gradient(135deg, #17a2b8, #138496);
            }

            .stat-icon.overdue {
                background: linear-gradient(135deg, #dc3545, #e74c3c);
            }

            .stat-icon.fines {
                background: linear-gradient(135deg, #6f42c1, #563d7c);
            }

            .stat-details {
                flex: 1;
                min-width: 0;
            }

            .stat-value {
                font-size: 1.8rem;
                font-weight: 700;
                color: var(--navy);
                margin-bottom: 0.25rem;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .stat-label {
                font-size: 0.85rem;
                color: #666;
                font-weight: 600;
            }

            /* Tabs */
            .tabs-container {
                background: white;
                border-radius: 12px;
                box-shadow: 0 4px 15px rgba(0,0,0,.1);
                overflow: hidden;
                margin-bottom: 2rem;
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

            /* Activity List */
            .activity-list {
                list-style: none;
                padding: 0;
                margin: 0;
            }

            .activity-item {
                display: flex;
                align-items: center;
                padding: 1rem;
                border-bottom: 1px solid #eee;
                transition: background 0.3s ease;
            }

            .activity-item:hover {
                background: #f8f9fa;
            }

            .activity-item:last-child {
                border-bottom: none;
            }

            .activity-avatar {
                width: 50px;
                height: 50px;
                border-radius: 50%;
                object-fit: cover;
                border: 2px solid var(--gold);
                margin-right: 1rem;
                flex-shrink: 0;
            }

            .activity-avatar-placeholder {
                width: 50px;
                height: 50px;
                border-radius: 50%;
                background: linear-gradient(135deg, var(--navy), #001a52);
                display: flex;
                align-items: center;
                justify-content: center;
                margin-right: 1rem;
                border: 2px solid var(--gold);
                flex-shrink: 0;
            }

            .activity-avatar-placeholder i {
                color: var(--gold);
                font-size: 1.2rem;
            }

            .activity-details {
                flex: 1;
                min-width: 0;
            }

            .activity-title {
                font-weight: 600;
                color: var(--navy);
                margin-bottom: 0.25rem;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .activity-meta {
                font-size: 0.85rem;
                color: #666;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .activity-date {
                font-size: 0.8rem;
                color: #999;
                text-align: right;
                flex-shrink: 0;
                margin-left: 0.5rem;
            }

            /* Ranking Cards */
            .ranking-card {
                display: flex;
                align-items: center;
                padding: 1rem;
                border: 2px solid #e0e0e0;
                border-radius: 8px;
                margin-bottom: 1rem;
                transition: all 0.3s ease;
            }

            .ranking-card:hover {
                border-color: var(--gold);
                box-shadow: 0 4px 12px rgba(0,0,0,.1);
            }

            .rank-number {
                width: 40px;
                height: 40px;
                border-radius: 50%;
                background: var(--navy);
                color: var(--gold);
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: 700;
                font-size: 1.1rem;
                margin-right: 1rem;
                flex-shrink: 0;
            }

            .rank-number.gold {
                background: linear-gradient(135deg, #ffd700, #ffed4e);
                color: var(--navy);
            }

            .rank-number.silver {
                background: linear-gradient(135deg, #c0c0c0, #e8e8e8);
                color: var(--navy);
            }

            .rank-number.bronze {
                background: linear-gradient(135deg, #cd7f32, #e8a87c);
                color: white;
            }

            .rank-content {
                flex: 1;
                min-width: 0;
            }

            .rank-title {
                font-weight: 600;
                color: var(--navy);
                margin-bottom: 0.25rem;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .rank-subtitle {
                font-size: 0.85rem;
                color: #666;
            }

            .rank-value {
                font-size: 1.2rem;
                font-weight: 700;
                color: var(--navy);
                text-align: right;
                flex-shrink: 0;
                margin-left: 0.5rem;
            }

            .action-buttons {
                display: flex;
                gap: 1rem;
                margin-top: 2rem;
            }

            .profile-card .btn {
                padding: 0.75rem 1.5rem !important;
                border: none !important;
                border-radius: 8px !important;
                font-weight: 600 !important;
                cursor: pointer !important;
                transition: all 0.3s ease !important;
                text-decoration: none !important;
                display: inline-flex !important;
                align-items: center !important;
                gap: 0.5rem !important;
                width: auto !important;
                max-width: none !important;
                font-size: 14px !important;
                box-sizing: border-box !important;
            }

            .profile-card .btn-primary {
                background: var(--navy) !important;
                color: white !important;
            }

            .profile-card .btn-primary:hover {
                background: #001a52 !important;
                transform: translateY(-2px);
            }

            .profile-card .btn-secondary {
                background: #6c757d !important;
                color: white !important;
            }

            .profile-card .btn-secondary:hover {
                background: #5a6268 !important;
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

            /* TABLET */
            @media (max-width: 1024px) {
                .profile-layout {
                    grid-template-columns: 1fr;
                }
            }

            /* MOBILE */
            @media (max-width: 768px) {
                .profile-container {
                    padding: 0 1.25rem;
                    margin: 1rem auto;
                }

                .profile-header {
                    padding: 1.25rem;
                    border-radius: 10px;
                    margin-bottom: 1.25rem;
                }

                .profile-header h1 {
                    font-size: 1.4rem;
                }

                .profile-card {
                    margin: 0;
                    padding: 1.5rem 1.25rem;
                    border-radius: 10px;
                }

                .stats-grid {
                    grid-template-columns: 1fr 1fr;
                    gap: 1rem;
                }

                .stat-card {
                    padding: 1rem;
                    gap: 0.75rem;
                }

                .stat-icon {
                    width: 48px;
                    height: 48px;
                    font-size: 1.2rem;
                }

                .stat-value {
                    font-size: 1.4rem;
                }

                .stat-label {
                    font-size: 0.75rem;
                }

                .tabs-header {
                    flex-direction: column;
                }

                .tab-content {
                    padding: 1rem;
                }

                .activity-item {
                    gap: 0.75rem;
                }

                .ranking-card {
                    padding: 0.75rem;
                }

                .rank-value {
                    font-size: 1rem;
                }

                .action-buttons {
                    flex-direction: column;
                    gap: 0.75rem;
                }

                .profile-card .btn {
                    width: 100% !important;
                    justify-content: center !important;
                }
            }

            /* EXTRA SMALL MOBILE */
            @media (max-width: 480px) {
                .profile-container {
                    padding: 0 1rem;
                }

                .stats-grid {
                    grid-template-columns: 1fr 1fr;
                    gap: 0.75rem;
                }

                .stat-card {
                    flex-direction: column;
                    text-align: center;
                    padding: 1rem 0.75rem;
                    gap: 0.5rem;
                }

                .stat-value {
                    font-size: 1.3rem;
                }

                .profile-name {
                    font-size: 1.25rem;
                }

                .activity-item {
                    flex-direction: column;
                    text-align: center;
                }

                .activity-avatar,
                .activity-avatar-placeholder {
                    margin: 0 0 0.75rem 0;
                }

                .activity-date {
                    margin-left: 0;
                    margin-top: 0.5rem;
                    text-align: center;
                }

                .activity-title,
                .activity-meta {
                    white-space: normal;
                }
            }
        </style>
    </head>
    <body>
        <?php include('includes/header.php'); ?>

        <div class="profile-container">
            <div class="profile-header">
                <h1><i class="fas fa-user-shield"></i> Admin Profile</h1>
            </div>

            <div class="profile-layout">
                <!-- Left Column: Profile Summary -->
                <div class="profile-card">
                    <div class="profile-photo-container">
                        <?php if (!empty($admin['profile_image']) && file_exists('uploads/admin/' . $admin['profile_image'])): ?>
                            <img src="uploads/admin/<?= htmlspecialchars($admin['profile_image']) ?>" 
                                alt="Profile Photo" class="profile-photo">
                        <?php else: ?>
                            <div class="profile-photo-placeholder">
                                <i class="fas fa-user-shield"></i>
                            </div>
                        <?php endif; ?>
                        
                        <div class="profile-name">
                            <?= htmlspecialchars($fullName) ?>
                        </div>
                        <div class="profile-role">System Administrator</div>
                        <span class="status-badge status-active">Active</span>
                    </div>


                    <div class="profile-info-item">
                        <div class="profile-info-label">
                            <i class="fas fa-envelope"></i> Email
                        </div>
                        <div class="profile-info-value"><?= htmlspecialchars($admin['email']) ?></div>
                    </div>

                    <div class="profile-info-item">
                        <div class="profile-info-label">
                            <i class="fas fa-clock"></i> Last Updated
                        </div>
                        <div class="profile-info-value">
                            <?= date('F j, Y g:i A', strtotime($admin['update_date'])) ?>
                        </div>
                    </div>

                    <div class="action-buttons">
                        <a href="edit-admin-profile.php" class="btn btn-primary">
                            <i class="fas fa-edit"></i> Edit Profile
                        </a>
                        <a href="change-password.php" class="btn btn-secondary">
                            <i class="fas fa-key"></i> Change Password
                        </a>
                    </div>
                </div>

                <!-- Right Column: Stats & Activity -->
                <div>
                    <!-- Library Statistics Grid -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon books">
                                <i class="fas fa-book"></i>
                            </div>
                            <div class="stat-details">
                                <div class="stat-value"><?= $totalBooks ?></div>
                                <div class="stat-label">Total Books</div>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon students">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-details">
                                <div class="stat-value"><?= $totalStudents ?></div>
                                <div class="stat-label">Active Students</div>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon issued">
                                <i class="fas fa-book-reader"></i>
                            </div>
                            <div class="stat-details">
                                <div class="stat-value"><?= $totalIssued ?></div>
                                <div class="stat-label">Total Issued</div>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon borrowed">
                                <i class="fas fa-bookmark"></i>
                            </div>
                            <div class="stat-details">
                                <div class="stat-value"><?= $currentlyBorrowed ?></div>
                                <div class="stat-label">Currently Borrowed</div>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon overdue">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="stat-details">
                                <div class="stat-value"><?= $overdueBooks ?></div>
                                <div class="stat-label">Overdue Books</div>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon fines">
                                <i class="fas fa-coins"></i>
                            </div>
                            <div class="stat-details">
                                <div class="stat-value">₱<?= number_format((float)$totalFinesCollected, 2) ?></div>
                                <div class="stat-label">Fines Collected</div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabs Section -->
                    <div class="tabs-container">
                        <div class="tabs-header">
                            <button class="tab-button active" onclick="switchTab('recent', this)">
                                <i class="fas fa-clock"></i> Recent Issues
                            </button>
                            <button class="tab-button" onclick="switchTab('returns', this)">
                                <i class="fas fa-undo"></i> Recent Returns
                            </button>
                            <button class="tab-button" onclick="switchTab('students', this)">
                                <i class="fas fa-star"></i> Top Students
                            </button>
                            <button class="tab-button" onclick="switchTab('books', this)">
                                <i class="fas fa-fire"></i> Popular Books
                            </button>
                        </div>

                        <!-- Recent Issues Tab -->
                        <div id="recent-tab" class="tab-content active">
                            <?php if ($recentIssues->num_rows > 0): ?>
                                <ul class="activity-list">
                                    <?php while ($issue = $recentIssues->fetch_assoc()): ?>
                                    <li class="activity-item">
                                        <?php if (!empty($issue['profile_image']) && file_exists('uploads/students/' . $issue['profile_image'])): ?>
                                            <img src="uploads/students/<?= htmlspecialchars($issue['profile_image']) ?>" 
                                                alt="Student" class="activity-avatar">
                                        <?php else: ?>
                                            <div class="activity-avatar-placeholder">
                                                <i class="fas fa-user"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="activity-details">
                                            <div class="activity-title">
                                                <?= htmlspecialchars($issue['first_name'] . ' ' . $issue['last_name']) ?>
                                            </div>
                                            <div class="activity-meta">
                                                Borrowed: <strong><?= htmlspecialchars($issue['title']) ?></strong>
                                            </div>
                                        </div>
                                        <div class="activity-date">
                                            <?= date('M j, Y', strtotime($issue['issued_date'])) ?><br>
                                            <small><?= date('g:i A', strtotime($issue['issued_date'])) ?></small>
                                        </div>
                                    </li>
                                    <?php endwhile; ?>
                                </ul>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-book-reader"></i>
                                    <h3>No Recent Issues</h3>
                                    <p>No books have been issued recently.</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Recent Returns Tab -->
                        <div id="returns-tab" class="tab-content">
                            <?php if ($recentReturns->num_rows > 0): ?>
                                <ul class="activity-list">
                                    <?php while ($return = $recentReturns->fetch_assoc()): ?>
                                    <li class="activity-item">
                                        <?php if (!empty($return['profile_image']) && file_exists('uploads/students/' . $return['profile_image'])): ?>
                                            <img src="uploads/students/<?= htmlspecialchars($return['profile_image']) ?>" 
                                                alt="Student" class="activity-avatar">
                                        <?php else: ?>
                                            <div class="activity-avatar-placeholder">
                                                <i class="fas fa-user"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="activity-details">
                                            <div class="activity-title">
                                                <?= htmlspecialchars($return['first_name'] . ' ' . $return['last_name']) ?>
                                            </div>
                                            <div class="activity-meta">
                                                Returned: <strong><?= htmlspecialchars($return['title']) ?></strong>
                                            </div>
                                        </div>
                                        <div class="activity-date">
                                            <?= date('M j, Y', strtotime($return['actual_return'])) ?><br>
                                            <small><?= date('g:i A', strtotime($return['actual_return'])) ?></small>
                                        </div>
                                    </li>
                                    <?php endwhile; ?>
                                </ul>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-undo"></i>
                                    <h3>No Recent Returns</h3>
                                    <p>No books have been returned recently.</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Top Students Tab -->
                        <div id="students-tab" class="tab-content">
                            <?php if ($activeStudents->num_rows > 0): ?>
                                <ul class="activity-list">
                                <?php 
                                $rank = 1;
                                while ($student = $activeStudents->fetch_assoc()): 
                                    $rankClass = '';
                                    if ($rank == 1) $rankClass = 'gold';
                                    elseif ($rank == 2) $rankClass = 'silver';
                                    elseif ($rank == 3) $rankClass = 'bronze';
                                ?>
                                <li class="activity-item">
                                    <div class="rank-number <?= $rankClass ?>">
                                        <?= $rank ?>
                                    </div>
                                    <?php if (!empty($student['profile_image']) && file_exists('uploads/students/' . $student['profile_image'])): ?>
                                        <img src="uploads/students/<?= htmlspecialchars($student['profile_image']) ?>" 
                                            alt="Student" class="activity-avatar">
                                    <?php else: ?>
                                        <div class="activity-avatar-placeholder">
                                            <i class="fas fa-user"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="rank-content">
                                        <div class="rank-title">
                                            <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?>
                                        </div>
                                        <div class="rank-subtitle">
                                            <?= htmlspecialchars($student['course']) ?>
                                        </div>
                                    </div>
                                    <div class="rank-value">
                                        <?= $student['borrow_count'] ?> books
                                    </div>
                                </li>
                                <?php 
                                $rank++;
                                endwhile; 
                                ?>
                                </ul>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-user-graduate"></i>
                                    <h3>No Active Students</h3>
                                    <p>No students have borrowed books yet.</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Popular Books Tab -->
                        <div id="books-tab" class="tab-content">
                            <?php if ($popularBooks->num_rows > 0): ?>
                                <ul class="activity-list">
                                <?php 
                                $rank = 1;
                                while ($book = $popularBooks->fetch_assoc()): 
                                    $rankClass = '';
                                    if ($rank == 1) $rankClass = 'gold';
                                    elseif ($rank == 2) $rankClass = 'silver';
                                    elseif ($rank == 3) $rankClass = 'bronze';
                                ?>
                                <li class="activity-item">
                                    <div class="rank-number <?= $rankClass ?>">
                                        <?= $rank ?>
                                    </div>
                                    <div class="activity-avatar-placeholder">
                                        <i class="fas fa-book"></i>
                                    </div>
                                    <div class="rank-content">
                                        <div class="rank-title">
                                            <?= htmlspecialchars($book['title']) ?>
                                        </div>
                                        <div class="rank-subtitle">
                                            by <?= htmlspecialchars($book['author']) ?>
                                        </div>
                                    </div>
                                    <div class="rank-value">
                                        <?= $book['times_borrowed'] ?> times
                                    </div>
                                </li>
                                <?php 
                                $rank++;
                                endwhile; 
                                ?>
                                </ul>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-book"></i>
                                    <h3>No Popular Books</h3>
                                    <p>No books have been borrowed yet.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
            function switchTab(tabName, btn) {
                // Hide all tabs
                document.querySelectorAll('.tab-content').forEach(tab => {
                    tab.classList.remove('active');
                });
                document.querySelectorAll('.tab-button').forEach(b => {
                    b.classList.remove('active');
                });

                // Show selected tab
                document.getElementById(tabName + '-tab').classList.add('active');
                btn.classList.add('active');
            }
        </script>
    </body>
    </html>
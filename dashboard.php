<?php 

session_start();
error_reporting(E_ALL);
include('connection/db.php');
include 'includes/functions.php';

//checking if user ba ang login if dili, redirects sa index
if (strlen($_SESSION['login']) == 0) {
  header('location:index.php');
  exit();
}

// student id from session after login. used for querrying that needs student id as parameter
$studentID = $_SESSION['stdid'];

/* ---------- Library Stats ---------- */
$totalBooks = $conn->query("SELECT SUM(quantity) AS total FROM books")->fetch_assoc()['total'] ?? 0;
$availableBooks = $conn->query("SELECT COUNT(*) AS available FROM books WHERE quantity != 0")->fetch_assoc()['available'] ?? 0;
$issuedBooks = $conn->query("SELECT COUNT(*) AS issued FROM issued_books WHERE student_id = '$studentID'")->fetch_assoc()['issued'] ?? 0;

/* ---------- My Borrowed Books Stats ---------- */
// Currently Borrowed (not yet returned)
$currentlyBorrowed = $conn->query("
    SELECT COUNT(*) AS count 
    FROM issued_books 
    WHERE student_id = '$studentID' AND return_status = 0
")->fetch_assoc()['count'] ?? 0;

// Due Soon (within 7 days, not returned yet)
$dueSoon = $conn->query("
    SELECT COUNT(*) AS count 
    FROM issued_books 
    WHERE student_id = '$studentID' 
      AND return_status = 0 
      AND DATEDIFF(due_date, CURDATE()) BETWEEN 0 AND 7
")->fetch_assoc()['count'] ?? 0;

// Overdue (past due date, not returned yet)
$overdue = $conn->query("
    SELECT COUNT(*) AS count 
    FROM issued_books 
    WHERE student_id = '$studentID' 
      AND return_status = 0 
      AND due_date < CURDATE()
")->fetch_assoc()['count'] ?? 0;

// Returned Books
$returned = $conn->query("
    SELECT COUNT(*) AS count 
    FROM issued_books 
    WHERE student_id = '$studentID' AND return_status = 1
")->fetch_assoc()['count'] ?? 0;

/* ---------- My Fines ---------- */
$fineToday = $conn->query("
    SELECT COALESCE(SUM(fine),0) AS fine
    FROM issued_books
    WHERE student_id = '$studentID' 
      AND return_status = 1 
      AND DATE(actual_return) = CURDATE()
")->fetch_assoc()['fine'];

$fineMonth = $conn->query("
    SELECT COALESCE(SUM(fine),0) AS fine
    FROM issued_books
    WHERE student_id = '$studentID' 
      AND return_status = 1
      AND YEAR(actual_return) = YEAR(CURDATE())
      AND MONTH(actual_return) = MONTH(CURDATE())
")->fetch_assoc()['fine'];

$fineYear = $conn->query("
    SELECT COALESCE(SUM(fine),0) AS fine
    FROM issued_books
    WHERE student_id = '$studentID' 
      AND return_status = 1 
      AND YEAR(actual_return) = YEAR(CURDATE())
")->fetch_assoc()['fine'];

/* ---------- Weather ---------- */
$weatherData = null;
$weatherError = "";
$apiKey = 'c631f725e69b24910ecb6c1e7958c78a';
$lat = $_GET['lat'] ?? 14.5995;
$lon = $_GET['lon'] ?? 120.9842;

$url = "https://api.openweathermap.org/data/2.5/weather?lat={$lat}&lon={$lon}&appid={$apiKey}&units=metric";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
if ($response !== false && curl_getinfo($ch, CURLINFO_HTTP_CODE) == 200) {
    $weatherData = json_decode($response, true);
} else {
    $weatherError = "Weather unavailable";
}
curl_close($ch);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>User Dashboard</title>

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="css/styles.css">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
        --navy: #000435;
        --gold: #ffde59;
    }
    body { background:#f8f9fa; font-family:'Montserrat',sans-serif; margin:0; padding:0; }
    .dashboard-container { max-width:1400px; margin:2rem auto; padding:0 1rem; }

    /* ----- Welcome Header ----- */
    .dashboard-header {
        background:linear-gradient(135deg,var(--navy),#001a52);
        color:#fff;
        padding:2rem;
        border-radius:12px;
        box-shadow:0 8px 25px rgba(0,0,0,.2);
        margin-bottom:2rem;
        display:flex;
        justify-content:space-between;
        align-items:center;
        gap:2rem;
    }
    .dashboard-header .welcome-section { flex:1; }
    .dashboard-header h1 { color: #fff; margin:0 0 .5rem 0; font-size:2.5rem; }
    .dashboard-header p { margin:0; font-size:1.2rem; color:var(--gold); }
    .dashboard-header .weather-section { 
        display:flex; 
        align-items:center; 
        gap:1rem;
        background:rgba(255,255,255,0.1);
        padding:1rem 1.5rem;
        border-radius:10px;
        backdrop-filter:blur(10px);
    }
    .dashboard-header .weather-section img { width:50px; height:50px; }
    .dashboard-header .weather-section .temp { font-size:1.8rem; font-weight:700; color:var(--gold); }
    .dashboard-header .weather-section .location { font-weight:600; font-size:0.9rem; margin-bottom:0.3rem; }
    .dashboard-header .weather-section .details { font-size:0.85rem; opacity:0.9; }
    .dashboard-header .weather-section .error { color:#ff6b6b; font-style:italic; }

    /* ----- Section Header ----- */
    .section-header {
        background:var(--navy);
        color:#fff;
        padding:1rem 1.5rem;
        border-radius:12px 12px 0 0;
        font-weight:600;
        font-size:1.3rem;
        display:flex;
        align-items:center;
        gap:12px;
        box-shadow:0 4px 15px rgba(0,0,67,.2);
    }
    .section-header i { color:var(--gold); }

    /* ----- Cards Grid ----- */
    .cards-grid {
        display:grid;
        grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
        gap:1.5rem;
        padding:1.5rem;
        background:#fff;
        border-radius:0 0 12px 12px;
        box-shadow:0 8px 25px rgba(0,0,0,.1);
        margin-bottom:2rem;
    }

    /* ----- GOLD CARD ----- */
    .dashboard-card {
        background:var(--gold);
        color:var(--navy);
        border-radius:12px;
        padding:1.5rem;
        text-align:center;
        transition:all .3s ease;
        position:relative;
        overflow:hidden;
        box-shadow:0 4px 12px rgba(0,0,0,.1);
    }
    .dashboard-card::before {
        content:'';
        position:absolute;
        top:0; left:0; right:0;
        height:4px;
        background:var(--navy);
        transform:scaleX(0);
        transition:transform .3s ease;
    }
    .dashboard-card:hover {
        transform:translateY(-6px);
        box-shadow:0 12px 25px rgba(0,0,67,.2);
    }
    .dashboard-card:hover::before { transform:scaleX(1); }

    .dashboard-card h3 {
        font-size:2rem;
        margin:.5rem 0;
        font-weight:700;
        display:flex;
        align-items:center;
        justify-content:center;
        gap:8px;
    }
    .dashboard-card i { color:var(--navy); font-size:1.4rem; }
    .dashboard-card p { margin:0; font-weight:500; font-size:.95rem; }

    /* ----- Special Cards ----- */
    .dashboard-card.warning { background:#fff3cd; border-left:4px solid #ffc107; }
    .dashboard-card.danger { background:#f8d7da; border-left:4px solid #dc3545; }
    .dashboard-card.success { background:#d1e7dd; border-left:4px solid #198754; }

    /* ----- Note styling ----- */
    .note {
        background:#e7f3ff;
        border-left:4px solid #0066cc;
        padding:1rem 1.5rem;
        border-radius:8px;
        margin-top:1rem;
        font-size:0.9rem;
        color:#003d7a;
    }
    .note i { color:#0066cc; margin-right:8px; }

    /* ----- Weather Bar ----- */
    .weather-bar {
        background:linear-gradient(135deg,var(--navy),#001a52);
        color:#fff;
        padding:1rem 2rem;
        border-radius:12px;
        display:none; /* Hidden since weather is now in header */
        align-items:center;
        justify-content:center;
        gap:1.5rem;
        font-size:1.1rem;
        box-shadow:0 6px 20px rgba(0,0,0,.2);
        flex-wrap:wrap;
        margin-bottom:2rem;
    }
    .weather-bar img { width:60px; height:60px; }
    .weather-bar .temp { font-size:2rem; font-weight:700; color:var(--gold); }
    .weather-bar .location { font-weight:600; }
    .weather-bar .error { color:#ff6b6b; font-style:italic; }

    @media (max-width:768px){
        .dashboard-header { flex-direction:column; text-align:center; }
        .dashboard-header h1 { font-size:1.8rem; }
        .dashboard-header .weather-section { width:100%; justify-content:center; }
        .cards-grid{grid-template-columns:1fr;}
    }
  </style>
</head>
<body>
<?php include('includes/header.php'); ?>

  <div class="dashboard-container">

    <!-- Welcome Header with Weather -->
    <div class="dashboard-header">
      <div class="welcome-section">
        <h1><strong>User Dashboard</strong></h1>
        <p>Welcome, <?php echo htmlentities($_SESSION['login']); ?>!</p>
      </div>
      
      <div class="weather-section">
        <?php if (!empty($weatherData)): ?>
            <img src="https://openweathermap.org/img/wn/<?=$weatherData['weather'][0]['icon']?>@2x.png" alt="Weather">
            <div>
                <div class="location"><i class="fas fa-map-marker-alt"></i> <?=htmlspecialchars($weatherData['name'])?></div>
                <div class="temp"><?=round($weatherData['main']['temp'])?>°C</div>
                <div class="details"><?=ucfirst($weatherData['weather'][0]['description'])?></div>
            </div>
        <?php else: ?>
            <div class="error"><i class="fas fa-exclamation-triangle"></i> <?=$weatherError?></div>
        <?php endif; ?>
      </div>
    </div>

    <!-- 2. LIBRARY OVERVIEW -->
    <div>
        <div class="section-header"><i class="fas fa-book-open"></i> Library Overview</div>
        <div class="cards-grid">
            <div class="dashboard-card"><h3><?=$totalBooks?> <i class="fas fa-book"></i></h3><p>Total Books</p></div>
            <div class="dashboard-card"><h3><?=$availableBooks?> <i class="fas fa-check-circle"></i></h3><p>Books Available</p></div>
            <div class="dashboard-card"><h3><?=$issuedBooks?> <i class="fas fa-book-reader"></i></h3><p>My Issued Books</p></div>
        </div>
    </div>

    <!-- 3. MY BORROWED BOOKS STATUS -->
    <div>
        <div class="section-header"><i class="fas fa-bookmark"></i> My Borrowed Books Status</div>
        <div class="cards-grid">
            <div class="dashboard-card"><h3><?=$currentlyBorrowed?> <i class="fas fa-book-open"></i></h3><p>Currently Borrowed</p></div>
            <div class="dashboard-card warning"><h3><?=$dueSoon?> <i class="fas fa-clock"></i></h3><p>Due Soon (7 Days)</p></div>
            <div class="dashboard-card danger"><h3><?=$overdue?> <i class="fas fa-exclamation-triangle"></i></h3><p>Overdue</p></div>
            <div class="dashboard-card success"><h3><?=$returned?> <i class="fas fa-check-double"></i></h3><p>Returned</p></div>
        </div>
        <div class="note">
            <i class="fas fa-info-circle"></i>
            <strong>Note:</strong> Please return your books on time to avoid late fines. Books marked as "Due Soon" should be returned within the next 7 days.
        </div>
    </div>

    <!-- 4. MY FINES CONTRIBUTED -->
    <div>
        <div class="section-header"><i class="fas fa-coins"></i> My Fines Contributed</div>
        <div class="cards-grid">
            <div class="dashboard-card"><h3>₱<?=number_format((float)$fineToday,2)?> <i class="fas fa-calendar-day"></i></h3><p>Paid Today</p></div>
            <div class="dashboard-card"><h3>₱<?=number_format((float)$fineMonth,2)?> <i class="fas fa-calendar-alt"></i></h3><p>This Month</p></div>
            <div class="dashboard-card"><h3>₱<?=number_format((float)$fineYear,2)?> <i class="fas fa-calendar"></i></h3><p>This Year</p></div>
        </div>
    </div>

  </div>

  <!-- Geolocation -->
  <script>
    document.addEventListener('DOMContentLoaded',()=>{const p=new URLSearchParams(location.search);if(!p.get('lat')||!p.get('lon')){if(navigator.geolocation){navigator.geolocation.getCurrentPosition(pos=>location.href=`?lat=${pos.coords.latitude}&lon=${pos.coords.longitude}`,()=>location.href='?lat=14.5995&lon=120.9842',{timeout:10000});}else location.href='?lat=14.5995&lon=120.9842';}});
  </script>
</body>
</html>
<?php
session_start();
include('../connection/db.php');

if (strlen($_SESSION['alogin']) == 0) {
    header('location:index.php');
    exit;
}

/* ---------- Stats ---------- */
$totalBooks     = $conn->query("SELECT SUM(quantity) AS total FROM books")->fetch_assoc()['total'] ?? 0;
$availableBooks = $conn->query("SELECT COUNT(*) AS available FROM books WHERE quantity != 0")->fetch_assoc()['available'] ?? 0;
$issuedBooks    = $conn->query("SELECT COUNT(*) AS issued FROM issued_books")->fetch_assoc()['issued'] ?? 0;
$totalStudents  = $conn->query("SELECT COUNT(*) AS students FROM students")->fetch_assoc()['students'] ?? 0;

/* ---------- Fines ---------- */
$fineToday = $conn->query("
    SELECT COALESCE(SUM(fine),0) AS fine
    FROM issued_books
    WHERE return_status = 1 AND DATE(actual_return) = CURDATE()
")->fetch_assoc()['fine'];

$fineMonth = $conn->query("
    SELECT COALESCE(SUM(fine),0) AS fine
    FROM issued_books
    WHERE return_status = 1
      AND YEAR(actual_return) = YEAR(CURDATE())
      AND MONTH(actual_return) = MONTH(CURDATE())
")->fetch_assoc()['fine'];

$fineYear = $conn->query("
    SELECT COALESCE(SUM(fine),0) AS fine
    FROM issued_books
    WHERE return_status = 1 AND YEAR(actual_return) = YEAR(CURDATE())
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
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --navy: #000435;
            --gold: #ffde59;
        }
        body { background:#f8f9fa; font-family:'Montserrat',sans-serif; }
        .dashboard-container { max-width:1400px; margin:2rem auto; padding:0 1rem; }

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

        /* ----- Weather Bar ----- */
        .weather-bar {
            background:linear-gradient(135deg,var(--navy),#001a52);
            color:#fff;
            padding:1rem 2rem;
            border-radius:12px;
            display:flex;
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
            .weather-bar{flex-direction:column;text-align:center;padding:1.5rem;}
            .weather-bar img{width:50px;height:50px;}
            .cards-grid{grid-template-columns:1fr;}
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="dashboard-container">

        <!-- 1. WEATHER TODAY -->
        <div class="weather-bar">
            <?php if (!empty($weatherData)): ?>
                <div class="location"><i class="fas fa-map-marker-alt"></i> <?=htmlspecialchars($weatherData['name'])?></div>
                <img src="https://openweathermap.org/img/wn/<?=$weatherData['weather'][0]['icon']?>@2x.png" alt="Weather">
                <div class="temp"><?=round($weatherData['main']['temp'])?>°C</div>
                <div><?=ucfirst($weatherData['weather'][0]['description'])?></div>
                <div>Feels <?=round($weatherData['main']['feels_like'])?>°C • Hum <?=$weatherData['main']['humidity']?>%</div>
            <?php else: ?>
                <div class="error"><i class="fas fa-exclamation-triangle"></i> <?=$weatherError?></div>
            <?php endif; ?>
        </div>

        <!-- 2. BOOKS INFORMATION -->
        <div>
            <div class="section-header"><i class="fas fa-book-open"></i> Books Information</div>
            <div class="cards-grid">
                <div class="dashboard-card"><h3><?=$totalBooks?> <i class="fas fa-book"></i></h3><p>Total Books</p></div>
                <div class="dashboard-card"><h3><?=$availableBooks?> <i class="fas fa-check-circle"></i></h3><p>Books Available</p></div>
                <div class="dashboard-card"><h3><?=$issuedBooks?> <i class="fas fa-book-reader"></i></h3><p>Books Issued</p></div>
                <div class="dashboard-card"><h3><?=$totalStudents?> <i class="fas fa-users"></i></h3><p>Registered Students</p></div>
            </div>
        </div>

        <!-- 3. FINES COLLECTED -->
        <div>
            <div class="section-header"><i class="fas fa-coins"></i> Fines Collected</div>
            <div class="cards-grid">
                <div class="dashboard-card"><h3>₱<?=number_format((float)$fineToday,2)?> <i class="fas fa-clock"></i></h3><p>Collected Today</p></div>
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
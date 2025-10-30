<?php
session_start();
include('../connection/db.php');

// Session validation
if (strlen($_SESSION['alogin']) == 0) {
    header('location:index.php');
    exit;
}

// Dashboard statistics
$totalBooks = $conn->query("SELECT SUM(quantity) AS total FROM books")->fetch_assoc()['total'] ?? 0;
$availableBooks = $conn->query("SELECT COUNT(*) AS available FROM books WHERE quantity != 0")->fetch_assoc()['available'] ?? 0;
$issuedBooks = $conn->query("SELECT COUNT(*) AS issued FROM issued_books")->fetch_assoc()['issued'] ?? 0;
$totalStudents = $conn->query("SELECT COUNT(*) AS students FROM students")->fetch_assoc()['students'] ?? 0;

// Weather App Integration with Location
$weatherData = null;
$weatherError = "";
$apiKey = 'c631f725e69b24910ecb6c1e7958c78a'; // Your API key
$lat = isset($_GET['lat']) ? floatval($_GET['lat']) : null;
$lon = isset($_GET['lon']) ? floatval($_GET['lon']) : null;

// Default to Manila if no location data
$lat = $lat ?: 14.5995;
$lon = $lon ?: 120.9842;

if (!empty($apiKey)) {
    $url = "https://api.openweathermap.org/data/2.5/weather?lat={$lat}&lon={$lon}&appid=" . $apiKey . "&units=metric";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For testing; remove in production
    $response = curl_exec($ch);
    if ($response === false) {
        $weatherError = "cURL error: " . curl_error($ch);
    } else {
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode == 200) {
            $weatherData = json_decode($response, true);
        } else {
            $weatherError = "Weather data unavailable (API error: " . $httpCode . " - " . $response . ")";
        }
    }
    curl_close($ch);
    // Debugging: Uncomment to see raw response
    // echo "<pre>"; print_r($response); echo "</pre>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
</head>
<style>
    .dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    gap: 30px;
}

.dashboard-header > div:first-child {
    flex: 1;
}

.weather-card {
    
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
    text-align: center;
    min-width: 250px;
    flex-shrink: 0;
}

.weather-card h2 {
    margin: 0 0 10px 0;
    font-size: 1.2rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.weather-card img {
    width: 80px;
    height: 80px;
    margin: -10px auto;
}

.weather-card .temp {
    font-size: 2.5rem;
    margin: 10px 0 5px 0;
    font-weight: 700;
}

.weather-card p {
    margin: 5px 0;
    font-size: 0.95rem;
    text-transform: capitalize;
}

.weather-card .error {
    color: #ffebee;
    font-style: italic;
}

/* Responsive: stack on smaller screens */
@media (max-width: 768px) {
    .dashboard-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .weather-card {
        width: 100%;
        min-width: auto;
    }
}
</style>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1><strong>Admin Dashboard</strong></h1>
            <p>Welcome, <?php echo htmlentities($_SESSION['alogin']); ?>!</p>

             <div class="weather-card">
            <?php if (!empty($weatherData)): ?>
                <h2><i class="fas fa-cloud-sun"></i> <?php echo htmlspecialchars($weatherData['name']); ?></h2>
                <img src="https://openweathermap.org/img/wn/<?php echo $weatherData['weather'][0]['icon']; ?>@2x.png" alt="Weather Icon">
                <p class="temp"><strong><?php echo round($weatherData['main']['temp']); ?>°C</strong></p>
                <p><?php echo htmlspecialchars($weatherData['weather'][0]['description']); ?></p>
                <p>Feels: <?php echo round($weatherData['main']['feels_like']); ?>°C | Hum: <?php echo $weatherData['main']['humidity']; ?>%</p>
            <?php else: ?>
                <p class="error"><?php echo $weatherError ?: 'Loading weather...'; ?></p>
            <?php endif; ?>
        </div>

        </div>

        <!-- Weather Widget -->
       

        <div class="cards-grid">
            <div class="dashboard-card">
                <h3><?php echo $totalBooks; ?> <i class="fas fa-book"></i></h3>
                <p>Total Books</p>
            </div>
            <div class="dashboard-card">
                <h3><?php echo $availableBooks; ?> <i class="fas fa-check-circle"></i></h3>
                <p>Books Available</p>
            </div>
            <div class="dashboard-card">
                <h3><?php echo $issuedBooks; ?> <i class="fas fa-book-reader"></i></h3>
                <p>Books Issued</p>
            </div>
            <div class="dashboard-card">
                <h3><?php echo $totalStudents; ?> <i class="fas fa-users"></i></h3>
                <p>Registered Students</p>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const lat = urlParams.get('lat');
            const lon = urlParams.get('lon');

            // Only fetch location if not already set
            if (!lat || !lon) {
                if (navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition(
                        function(position) {
                            const newLat = position.coords.latitude;
                            const newLon = position.coords.longitude;
                            // Redirect with new coordinates
                            window.location.href = `?lat=${newLat}&lon=${newLon}`;
                        },
                        function(error) {
                            console.error("Geolocation error: ", error.message);
                            // Fallback to Manila if geolocation fails
                            window.location.href = '?lat=14.5995&lon=120.9842';
                        },
                        { timeout: 10000, maximumAge: 60000 } // 10s timeout, 1min cache
                    );
                } else {
                    window.location.href = '?lat=14.5995&lon=120.9842';
                }
            } else {
                // Log current coordinates for debugging
                console.log("Using coordinates: lat=", lat, "lon=", lon);
            }
        });
    </script>
</body>
</html>
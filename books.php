<?php
session_start();
error_reporting(E_ALL);

include 'connection/db.php';
include 'security/crypt.php';
date_default_timezone_set('Asia/Manila');



if (strlen($_SESSION['login']) == 0) {
    header('location:index.php');
    exit;
}




function getBookDetails($conn)
{
    $books = []; // preparing array para mag-store sa result unja
    $search = $_GET['search'] ?? '';  // kuhaon nija ang search from the form if wala then default '' string , then i-store sa search variable 
    $sort = $_GET['sort'] ?? '';  // same lang with search

    $sql = "SELECT * FROM books WHERE title LIKE ?"; // base sql statement
    $order = ""; 

    switch ($sort) {
        case 'title_asc':  $order = " ORDER BY title ASC"; break;
        case 'title_desc': $order = " ORDER BY title DESC"; break;
        case 'qty_asc':    $order = " ORDER BY quantity ASC"; break;
        case 'qty_desc':   $order = " ORDER BY quantity DESC"; break;
    }

    $stmt = $conn->prepare($sql . $order);
    $like = "%$search%";
    $stmt->bind_param("s", $like);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $books[] = $row;
    }

    return $books;

    
}





?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Browse Books</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/tables.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap" rel="stylesheet">

    <style>
        
    </style>
</head>

<body>
    <?php include('includes/header.php'); ?>

   

    <div class="container">


        <div class="container-header">
            <h1>Browse Books</h1>
        </div>

        <div class="filter-toolbar">
    <form method="get" class="form-inline">
        <div class="form-left">
            <select name="sort" class="form-control">
                <option value="">Sort by</option>
                <option value="title_asc" <?= ($_GET['sort'] ?? '') == 'title_asc' ? 'selected' : '' ?>>Title A-Z</option>
                <option value="title_desc" <?= ($_GET['sort'] ?? '') == 'title_desc' ? 'selected' : '' ?>>Title Z-A</option>
                <option value="qty_asc" <?= ($_GET['sort'] ?? '') == 'qty_asc' ? 'selected' : '' ?>>Quantity Low-High</option>
                <option value="qty_desc" <?= ($_GET['sort'] ?? '') == 'qty_desc' ? 'selected' : '' ?>>Quantity High-Low</option>
            </select>
            <button type="submit" class="btn-apply">Apply</button>
        </div>

        <div class="form-right">
            <input type="text" name="search" placeholder="Search by title..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" class="form-control" />
        </div>
    </form>
</div>






        <?php
        $alerts = ['error', 'msg', 'updatemsg', 'delmsg'];
        foreach ($alerts as $alert) {
            if (!empty($_SESSION[$alert])) {
                $type = ($alert == 'error') ? 'danger' : 'success';
                echo '<div class="alert alert-' . $type . '">';
                echo '<strong>' . ucfirst($type) . ':</strong> ' . htmlentities($_SESSION[$alert]);
                echo '</div>';
                $_SESSION[$alert] = "";
            }
        }
        ?>

        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Title</th>
                    <th>Category</th>
                    <th>Author</th>
                    <th>ISBN</th>
                    <th>Quantity</th>
                    
                </tr>
            </thead>
            <tbody>
                <?php
                $books = getBookDetails($conn);
                $cnt = 1;

                if (count($books) > 0) {
                    foreach ($books as $row) {
                        $encryptedID = encrypt($row['book_id']);
                        $issuedStatus = $row['isIssued'] == 1 ? 'Yes' : 'No';

                        echo "<tr>
                            <td>{$cnt}</td>
                            <td style='text-align: center;'>
                                ";

                        if (!empty($row['image'])) {
                            echo "<img src='admin/uploads/" . htmlentities($row['image']) . "' width='80' style='display:block; margin: 0 auto 5px;'>";
                        } else {
                            echo "<div style='margin-bottom:5px;'>No Image</div>";
                        }

                        echo "<div style='margin-top:3px; font-weight:bold; font-size:13px;'>" . htmlentities($row['title']) . "</div>
                            </td>
                            <td>" . htmlentities($row['category']) . "</td>
                            <td>" . htmlentities($row['author']) . "</td>
                            <td>" . htmlentities($row['isbn']) . "</td>
                            <td>" . htmlentities($row['quantity']) . "</td>
                        </tr>";


                        $cnt++;
                    }
                } else {
                    echo '<tr><td colspan="6" style="text-align:center;">No books available yet.</td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>

</body>

</html>
<?php
session_start();
error_reporting(E_ALL);

include '../connection/db.php';
include '../security/crypt.php';
include 'includes/logger.php';
date_default_timezone_set('Asia/Manila');

$logger = new Logger();

if (strlen($_SESSION['alogin']) == 0) {
    header('location:index.php');
    exit;
}


function deleteBook($conn, $bookID)
{
    $stmt = $conn->prepare("DELETE FROM books WHERE book_id = ?");
    $stmt->bind_param("i", $bookID);
    return $stmt->execute();
}



if (isset($_GET['del'])) {
    $encryptedID = $_GET['del'];
    $decryptedID = decrypt($encryptedID);

    if (!$decryptedID) {
        $_SESSION['delmsg'] = "Invalid book ID.";
        header("location: books.php");
        exit;
    }

    if (deleteBook($conn, $decryptedID)) {
        $_SESSION['delmsg'] = "Book deleted successfully.";
        $logger->write("Book deleted.");
    } else {
        $_SESSION['delmsg'] = "Failed to delete book.";
    }

    header("location: books.php");
    exit;
}

function getBookDetails($conn)
{
    $books = [];
    $search = $_GET['search'] ?? '';
    $sort = $_GET['sort'] ?? '';

    $sql = "SELECT * FROM books WHERE title LIKE ?";
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



if (isset($_GET['download']) && $_GET['download'] == 1) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="books.csv"');
    
    $output = fopen("php://output", "w");
    fputcsv($output, ['#', 'Title', 'Category', 'Author', 'ISBN', 'Quantity']);

    $books = getBookDetails($conn);
    $cnt = 1;
    foreach ($books as $book) {
        fputcsv($output, [$cnt++, $book['title'], $book['category'], $book['author'], $book['isbn'], $book['quantity']]);
    }

    fclose($output);
    exit;
}


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Manage Books</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/tables.css">

    <style>
        
    </style>
</head>

<body>
    <?php include('includes/header.php'); ?>

   

    <div class="container">


        <div class="container-header">
            <h2>Manage Books</h2>
            <div style="text-align: right; margin-bottom: 10px;">
                <a href="add-book.php" class="btn btn-primary"
                    style="padding: 8px 14px; background-color:rgb(32, 142, 58); border: none; border-radius: 5px; color: #fff; text-decoration: none; font-weight: bold;">
                    <i class="fas fa-plus"></i> Add Book
                </a>
            </div>
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
            <a href="books.php?download=1" class="download-btn"><i class="fas fa-file-csv"></i> Download CSV</a>
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
                    <th>Actions</th>
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
                            echo "<img src='uploads/" . htmlentities($row['image']) . "' width='80' style='display:block; margin: 0 auto 5px;'>";
                        } else {
                            echo "<div style='margin-bottom:5px;'>No Image</div>";
                        }

                        echo "<div style='margin-top:3px; font-weight:bold; font-size:13px;'>" . htmlentities($row['title']) . "</div>
                            </td>
                            <td>" . htmlentities($row['category']) . "</td>
                            <td>" . htmlentities($row['author']) . "</td>
                            <td>" . htmlentities($row['isbn']) . "</td>
                            <td>" . htmlentities($row['quantity']) . "</td>
                            <td style='text-align: left; width: 200px;'>
                                <a href=\"edit-book.php?id=" . urlencode($encryptedID) . "\" class=\"btn-apply\" style=\"margin-bottom:5px; display:inline-block;\">
                                    <i class=\"fas fa-edit\"></i> Edit
                                </a>
                                <a href=\"books.php?del=" . urlencode($encryptedID) . "\" class=\"btn-danger\" style=\"margin-top:5px; display:inline-block;\" onclick=\"return confirm('Are you sure you want to delete this book?')\">
                                    <i class=\"fas fa-trash-alt\"></i> Delete
                                </a>
                            </td>
                        </tr>";


                        $cnt++;
                    }
                } else {
                    echo '<tr><td colspan="7" style="text-align:center;">No books available yet.</td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>

</body>

</html>
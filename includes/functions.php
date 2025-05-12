<?php


// get issued books nga gi-reference ni dashboard.php ug issued_books.php
// kapoy duplicate anion na lang
function getIssuedBooksByID($studentID, $conn){
  $issuedBooks = [];
  $sql = "SELECT * FROM issued_books ib 
          JOIN books b on ib.book_id = b.book_id
          WHERE student_id = ?";

  $stmt = $conn->prepare($sql);
  $stmt->bind_param('s',$studentID);
  $stmt-> execute();

  $result = $stmt->get_result();

  if($result){
    while($row = $result->fetch_assoc()){
      $issuedBooks[]= $row;
    }
  }

  return $issuedBooks;

}

?>
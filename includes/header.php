

<nav class="custom-navbar">
  <div class="navbar-container">
    <a class="navbar-brand" href="#">
      <i class="fas fa-book"></i> OmniReads
    </a>

    <button class="navbar-toggle" id="navbarToggle">
      <span class="navbar-toggle-icon">&#9776;</span>
    </button>

    <div class="navbar-links" id="navbarLinks">
      <ul class="nav-list">
        <?php if (isset($_SESSION['login']) && $_SESSION['login']) { ?>
          <li><a href="dashboard.php">Dashboard</a></li>
          <li><a href="issued-books.php">Issued Books</a></li>
          <li><a class="logout-btn" href="index.php">Log Out</a></li>
        <?php } else { ?>
          <li><a href="index.php#ulogin">User Login</a></li>
          <li><a href="adminlogin.php">Admin Login</a></li>
        <?php } ?>
      </ul>
    </div>
  </div>
</nav>

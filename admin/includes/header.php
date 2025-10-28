<nav class="custom-navbar">
  <div class="navbar-container">
    <a class="navbar-brand" href="#">
      <img src="https://upload.wikimedia.org/wikipedia/en/c/c9/Seal_of_the_International_State_College_of_the_Philippines.png" alt="ISCP Library Logo" style="height:50px; vertical-align:middle; margin-right:8px;">
      ISCP Library
    </a>

    <button class="navbar-toggle" id="navbarToggle">
      <span class="navbar-toggle-icon">&#9776;</span>
    </button>

    <div class="navbar-links" id="navbarLinks">
      <ul class="nav-list">
          <li>
            <a href="dashboard.php" title="Go to Dashboard">
              <i class="fas fa-tachometer-alt"></i> 
            </a>
          </li>
          <li>
            <a href="reg-students.php" title="View Registered Students">
              <i class="fas fa-user-graduate"></i>
            </a>
          </li>
          <li>
            <a href="books.php" title="Manage Books">
              <i class="fas fa-book"></i>
            </a>
          </li>
          <li>
            <a href="issued-books.php" title="View Issued Books">
              <i class="fas fa-book-reader"></i> 
            </a>
          </li>

          <li>
            <a href="notepad.php" title="Notepad">
              <i class="fas fa-note-sticky"></i> 
            </a>
          </li>

          <li>
            <a href="change-password.php" title="Change Your Password">
              <i class="fas fa-key"></i> 
            </a>
          </li>
          <li>
            <a href="logs.php" title="View System Logs">
              <i class="fas fa-clipboard-list"></i>
            </a>
            
          </li>


          
            
          </li>

          <li>
            <form action="logout.php" method="post" style="display:inline;">
              <button type="submit" class="logout-btn" title="Log Out">
                <i class="fas fa-sign-out-alt"></i> Log Out
              </button>
            </form>
          </li>

          
      </ul>
    </div>
  </div>
</nav>
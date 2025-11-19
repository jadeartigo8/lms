<nav class="custom-navbar">
  <div class="navbar-container">
    <a class="navbar-brand" href="#">
      <img
        src="https://upload.wikimedia.org/wikipedia/en/c/c9/Seal_of_the_International_State_College_of_the_Philippines.png"
        alt="ISCP Library Logo" style="height:50px; vertical-align:middle; margin-right:8px;">
      ISCP Library
    </a>

    <button class="navbar-toggle" id="navbarToggle">
      <span class="navbar-toggle-icon"></span>
    </button>

    <div class="navbar-links" id="navbarLinks">
      <ul class="nav-list">
        <li>
          <a href="dashboard.php" title="Go to Dashboard">
            <i class="fas fa-tachometer-alt"></i>
            <span class="nav-text">Dashboard</span>
          </a>
        </li>
        <li>
          <a href="reg-students.php" title="View Registered Students">
            <i class="fas fa-user-graduate"></i>
            <span class="nav-text">Students</span>
          </a>
        </li>
        <li>
          <a href="books.php" title="Manage Books">
            <i class="fas fa-book"></i>
            <span class="nav-text">Books</span>
          </a>
        </li>
        <li>
          <a href="issued-books.php" title="View Issued Books">
            <i class="fas fa-book-reader"></i>
            <span class="nav-text">Issued Books</span>
          </a>
        </li>

        <li>
          <a href="notepad.php" title="Notepad">
            <i class="fas fa-note-sticky"></i>
            <span class="nav-text">Notepad</span>
          </a>
        </li>

        <li>
          <a href="manage-admins.php" title="Manage Admins">
            <i class="fas fa-users-cog"></i>
            <span class="nav-text">Manage Admins</span>
          </a>
        </li>

        <li>
          <a href="admin-profile.php" title="Admin Profile">
            <i class="fas fa-user-shield"></i>
            <span class="nav-text">Profile</span>
          </a>
        </li>

        <li>
          <a href="logs.php" title="View System Logs">
            <i class="fas fa-clipboard-list"></i>
            <span class="nav-text">Logs</span>
          </a>
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

<script>
  // Mobile Navigation Toggle and Active State Management
  document.addEventListener('DOMContentLoaded', function () {
    const navbarToggle = document.getElementById('navbarToggle');
    const navbarLinks = document.getElementById('navbarLinks');

    // Set active link based on current page
    function setActiveLink() {
      const currentPage = window.location.pathname.split('/').pop() || 'dashboard.php';
      const navLinks = document.querySelectorAll('.nav-list li a:not(.logout-btn)');

      navLinks.forEach(link => {
        const linkPage = link.getAttribute('href');

        // Remove active class from all links
        link.classList.remove('active');

        // Add active class to current page link
        if (linkPage === currentPage) {
          link.classList.add('active');
        }
      });
    }

    // Call on page load
    setActiveLink();

    if (navbarToggle && navbarLinks) {
      // Toggle menu when burger icon is clicked
      navbarToggle.addEventListener('click', function (e) {
        e.stopPropagation();
        navbarToggle.classList.toggle('active');
        navbarLinks.classList.toggle('active');

        // Prevent body scroll when menu is open
        if (navbarLinks.classList.contains('active')) {
          document.body.style.overflow = 'hidden';
        } else {
          document.body.style.overflow = '';
        }
      });

      // Close menu when clicking outside
      document.addEventListener('click', function (e) {
        if (navbarLinks.classList.contains('active') &&
          !navbarLinks.contains(e.target) &&
          !navbarToggle.contains(e.target)) {
          navbarToggle.classList.remove('active');
          navbarLinks.classList.remove('active');
          document.body.style.overflow = '';
        }
      });

      // Handle link clicks
      const navLinks = navbarLinks.querySelectorAll('a:not(.logout-btn)');
      navLinks.forEach(link => {
        link.addEventListener('click', function (e) {
          // Close menu on mobile
          if (window.innerWidth <= 768) {
            navbarToggle.classList.remove('active');
            navbarLinks.classList.remove('active');
            document.body.style.overflow = '';
          }

          // Update active state
          navLinks.forEach(l => l.classList.remove('active'));
          this.classList.add('active');
        });
      });

      // Handle window resize
      window.addEventListener('resize', function () {
        if (window.innerWidth > 768) {
          navbarToggle.classList.remove('active');
          navbarLinks.classList.remove('active');
          document.body.style.overflow = '';
        }
      });
    }
  });
</script>
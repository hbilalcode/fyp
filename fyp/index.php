<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BS Academic System</title>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <link rel="stylesheet" href="assets/css/index.css">
</head>
<body>
  <header>
    <div class="container header-container">
      <div class="logo">
        <span class="logo-icon">🎓</span>
        <span>Academic System</span>
      </div>
      <nav>
        <ul>
          <li><a href="#features">Features</a></li>
          <li><a href="#programs">Programs</a></li>
          <li><a href="login.php" class="btn btn-outline">Login</a></li>
        </ul>
      </nav>
    </div>
  </header>

  <section class="hero">
    <div class="hero-slider">
      <ul class="items">
        <li class="item current"><img src="assets/images/1.jpg" alt="College campus"></li>
        <li class="item"><img src="assets/images/2.jpg" alt="Students learning"></li>
        <li class="item"><img src="assets/images/3.jpg" alt="Classroom lecture"></li>
        <li class="item"><img src="assets/images/4.jpg" alt="Graduation ceremony"></li>
      </ul>
      <div class="buttons">
        <button type="button" id="prev" class="button prev"></button>
        <button type="button" id="next" class="button next"></button>
      </div>
      <div class="dots">
        <button type="button" class="dot current"></button>
        <button type="button" class="dot"></button>
        <button type="button" class="dot"></button>
        <button type="button" class="dot"></button>
      </div>
    </div>
    
    <div class="container">
      <h1>Empowering Academic Excellence</h1>
      <p>A modern platform designed to streamline university operations and enhance the learning experience for students and faculty alike.</p>
      <div class="hero-buttons">
        <a href="login.php" class="btn">Get Started</a>
        <a href="#features" class="btn btn-outline">Learn More</a>
      </div>
    </div>
  </section>

  <section id="features" class="features">
    <div class="container">
      <h2>Key Features</h2>
      <p>Our system provides comprehensive tools to manage all aspects of academic life</p>
      
      <div class="features-grid">
        <div class="feature-card">
          <div class="feature-icon">📚</div>
          <h3>Course Management</h3>
          <p>Easily organize and access course materials, assignments, and resources in one centralized location.</p>
        </div>
        
        <div class="feature-card">
          <div class="feature-icon">📊</div>
          <h3>Attendance</h3>
          <p>Real-time grade updates help students stay on track.</p>
        </div>
        
        <div class="feature-card">
          <div class="feature-icon">♾️</div>
          <h3>Materials Sharing</h3>
          <p>Integrated platforms for students and faculty to connect, collaborate, and share academic content.</p>
        </div>
        <div class="feature-card">
          <div class="feature-icon">📆</div>
          <h3>Timetable Management</h3>
          <p>Create, manage, and view class schedules efficiently in one centralized dashboard.</p>
        </div>
        <div class="feature-card">
          <div class="feature-icon">🙍</div>
          <h3>User Management</h3>
          <p>Easily add, update, or remove admin, faculty, and student accounts from a single control panel.</p>
        </div>
        <div class="feature-card">
          <div class="feature-icon">📄</div>
          <h3>Reports</h3>
          <p>Generate and view attendance, assignments, and academic activity reports quickly and accurately.</p>
        </div>
      </div>
    </div>
  </section>

  <section id="programs" class="programs">
    <div class="container">
      <h2>Academic Programs in GDC Thana</h2>
      <p>Explore our diverse range of academic offerings</p>
      
      <div class="program-cards">
        <div class="program-card">
          <div class="program-image" style="background-image: url('assets/images/computer-science-building.jpg');"></div>
          <div class="program-content">
            <h3>Computer Science</h3>
            <p>Offers quality education and practical learning in the field of computer science.</p>
            <a href="#">Learn more →</a>
          </div>
        </div>
        
        <div class="program-card">
          <div class="program-image" style="background-image: url('assets/images/English.jpg');"></div>
          <div class="program-content">
            <h3>Department of English</h3>
            <p>Focuses on language, literature, and communication skills to enhance academic and professional growth.</p>
            <a href="#">Learn more →</a>
          </div>
        </div>
        
        <div class="program-card">
          <div class="program-image" style="background-image: url('assets/images/Physics.jpg');"></div>
          <div class="program-content">
            <h3>Department of Physics</h3>
            <p>Provides a strong foundation in physical sciences through theoretical and practical learning.</p>
            <a href="#">Learn more →</a>
          </div>
        </div>
      </div>
      <div class="program-cards">
        <div class="program-card">
          <div class="program-image" style="background-image: url('assets/images/Chemistry.jpg');"></div>
          <div class="program-content">
            <h3>Department of Chemistry</h3>
            <p>Delivers in-depth knowledge of chemical sciences with a focus on theory, experimentation, and real-world applications.</p>
            <a href="#">Learn more →</a>
          </div>
        </div>
        
        <div class="program-card">
          <div class="program-image" style="background-image: url('assets/images/Zoology.jpg');"></div>
          <div class="program-content">
            <h3>Department of Zoology</h3>
            <p>Focuses on the study of animal life, behavior, and biology through both theoretical and practical approaches.</p>
            <a href="#">Learn more →</a>
          </div>
        </div>
        
        <div class="program-card">
          <div class="program-image" style="background-image: url('assets/images/Maths.jpg');"></div>
          <div class="program-content">
            <h3>Department of Mathematics</h3>
            <p>Offers in-depth knowledge of mathematical concepts, theories, and problem-solving techniques essential for academic and practical applications.</p>
            <a href="#">Learn more →</a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="cta">
    <div class="container">
      <h2>Ready to Transform Your Academic Experience?</h2>
      <p>Join thousands of students and faculty who are already benefiting from our platform.</p>
      <a href="login.php" class="btn">Access Your Account</a>
    </div>
  </section>

  <footer>
    <div class="container">
      <div class="footer-content">
        <div class="footer-column">
          <h3>BS Academic System</h3>
          <p>Empowering education through innovative technology solutions.</p>
        </div>
        
        <div class="footer-column">
          <h3>Quick Links</h3>
          <ul>
            <li><a href="#features">Features</a></li>
            <li><a href="#programs">Programs</a></li>
            <li><a href="login.php">Login</a></li>
          </ul>
        </div>
        
        <div class="footer-column">
          <h3>Support</h3>
          <ul>
            <li><a href="#">Help Center</a></li>
            <li><a href="#">Contact Us</a></li>
            <li><a href="#">Privacy Policy</a></li>
          </ul>
        </div>
      </div>
      
      <div class="copyright">
        &copy; 2025 BS Academic System. All rights reserved.
      </div>
    </div>
  </footer>

  <script>
    function slider(flag, num) {
      var current = $(".item.current"),
          next,
          index;
      if (!flag) {
        next = current.is(":last-child") ? $(".item").first() : current.next();
        index = next.index();
      } else if (flag === 'dot') {
        next = $(".item").eq(num);
        index = num;
      } else {
        next = current.is(":first-child") ? $(".item").last() : current.prev();
        index = next.index();
      }
      next.addClass("current");
      current.removeClass("current");
      $(".dot").eq(index).addClass("current").siblings().removeClass("current");
    }
    var setSlider = setInterval(slider, 3000);

    $(".button").on("click", function() {
      clearInterval(setSlider);
      var flag = $(this).is(".prev") ? true : false;
      slider(flag);
      setSlider = setInterval(slider, 3000);
    });

    $(".dot").on("click", function() {
      if ($(this).is(".current")) return;
      clearInterval(setSlider);
      var num = $(this).index();
      slider('dot', num);
      setSlider = setInterval(slider, 3000);
    });
  </script>
</body>
</html>
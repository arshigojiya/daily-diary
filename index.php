<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Daily Diary | Home</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="style.css">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
</head>
<body>

  <?php include 'header.php'; ?>

  <section class="hero-slider">
    <div class="slider">
      <div class="slides">
        <img src="images/slide1.jpeg" class="slide active" alt="Slide 1">
        <img src="images/slide2.jpg" class="slide" alt="Slide 2">
        <img src="images/slide3.jpg" class="slide" alt="Slide 3">
        <img src="images/slide4.jpg" class="slide" alt="Slide 4">
      </div>

      <button class="nav prev">&#10094;</button>
      <button class="nav next">&#10095;</button>

      <div class="dots">
        <span class="dot active" onclick="goToSlide(0)"></span>
        <span class="dot" onclick="goToSlide(1)"></span>
        <span class="dot" onclick="goToSlide(2)"></span>
        <span class="dot" onclick="goToSlide(3)"></span>
      </div>
    </div>
  </section>

  <section class="info-section">
  <div class="container">
    <h2> About Cows & Milk Products</h2>
    <p>
      Cows have been an integral part of Indian culture, tradition, and rural livelihood. They provide not only milk but also ghee, curd, butter, and other nourishing products that sustain millions.
    </p>
    <p>
      From morning chai to festivals and rituals, dairy items are at the heart of Indian kitchens. Respecting and caring for cows means preserving a way of life rooted in simplicity and sustainability.
    </p>
  </div>
</section>

<section class="products-section">
  <div class="container">
    <h2 class="section-title">🧈 Our Dairy Products</h2>

    <div class="product-filters">
      <div class="categories">
        <button class="filter-btn active">All</button>
        <button class="filter-btn">Milk</button>
        <button class="filter-btn">Ghee</button>
        <button class="filter-btn">Curd</button>
        <button class="filter-btn">Paneer</button>
      </div>
      <input type="text" class="search-bar" placeholder="Search products...">
    </div>

    <div class="product-grid">
      <div class="product-row-card">
        <img src="images/milk.jpg" alt="Milk">
        <div class="product-info">
          <h3>Fresh Cow Milk</h3>
          <p>Pure farm-fresh cow milk delivered every morning — rich in protein and nutrients.</p>
          <div class="product-bottom">
            <span class="price">₹45 / litre</span>
            <button class="buy-btn">Buy Now</button>
          </div>
        </div>
      </div>

      <div class="product-row-card">
        <img src="images/ghee.jpg" alt="Ghee">
        <div class="product-info">
          <h3>Desi Ghee</h3>
          <p>Traditional hand-churned ghee made from A2 milk — full of aroma and flavor.</p>
          <div class="product-bottom">
            <span class="price">₹499 / 500g</span>
            <button class="buy-btn">Buy Now</button>
          </div>
        </div>
      </div>

      <div class="product-row-card">
        <img src="images/curd.jpg" alt="Curd">
        <div class="product-info">
          <h3>Homemade Curd</h3>
          <p>Thick and creamy curd set in clay pots — a perfect probiotic addition to your meals.</p>
          <div class="product-bottom">
            <span class="price">₹35 / 500ml</span>
            <button class="buy-btn">Buy Now</button>
          </div>
        </div>
      </div>

      <div class="product-row-card">
        <img src="images/paneer.jpg" alt="Paneer">
        <div class="product-info">
          <h3>Fresh Paneer</h3>
          <p>Soft, fresh paneer made every day — ideal for curries, tikka, and snacks.</p>
          <div class="product-bottom">
            <span class="price">₹250 / kg</span>
            <button class="buy-btn">Buy Now</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="why-us">
  <div class="container">
    <h2 class="section-title">🌿 Why Choose Us</h2>
    <div class="features-grid">

      <div class="feature-card">
        <i class="ph ph-leaf"></i>
        <h4>100% Organic Feed</h4>
        <p>Our cows are fed natural, chemical-free fodder grown on local farms.</p>
      </div>

      <div class="feature-card">
        <i class="ph ph-cow"></i>
        <h4>Ethical Cow Care</h4>
        <p>We prioritize the well-being of our cows with open shelters & vet care.</p>
      </div>

      <div class="feature-card">
        <i class="ph ph-flask"></i>
        <h4>Lab-Tested Quality</h4>
        <p>Every batch of milk and ghee is lab-tested to ensure safety & purity.</p>
      </div>

      <div class="feature-card">
        <i class="ph ph-truck"></i>
        <h4>Fresh Daily Delivery</h4>
        <p>We deliver fresh dairy every morning directly from the farm to your home.</p>
      </div>

    </div>
  </div>
</section>


<section class="gallery">
  <div class="container">
    <h2 class="section-title">📷 Our Farm & Dairy Moments</h2>
    <p class="gallery-subtext">Take a look at where your milk comes from — clean farms, happy cows, and fresh dairy processing.</p>

    <div class="gallery-grid">
      <img src="images/farm1.jpg" alt="Cow shed">
      <img src="images/farm2.jpg" alt="Milking process">
      <img src="images/farm3.jpg" alt="Fresh milk collection">
      <img src="images/farm4.jpg" alt="Ghee preparation">
      <img src="images/farm5.jpg" alt="Dairy lab testing">
      <img src="images/farm6.jpg" alt="Farm delivery van">
      <img src="images/farm7.jpg" alt="Cows grazing in field">
<img src="images/farm8.jpg" alt="Traditional paneer cutting">

    </div>
  </div>
</section>

<section class="testimonials">
  <div class="container">
    <h2 class="section-title">💬 What Our Customers Say</h2>
    <div class="testimonial-grid">

      <div class="testimonial-card">
        <img src="images/user3.jpg" alt="User 1">
        <h4>Arshi Gojiya</h4>
        <p class="stars">★★★★★</p>
        <p class="quote">“Best milk I've ever tasted! Pure, fresh and delivered on time every morning.”</p>
      </div>

      <div class="testimonial-card">
        <img src="images/user2.jpg" alt="User 2">
        <h4>Mahesh bhtaiya</h4>
        <p class="stars">★★★★☆</p>
        <p class="quote">“Desi ghee reminded me of my grandma’s cooking. Superb quality and flavor.”</p>
      </div>

      <div class="testimonial-card">
        <img src="images/user1.jpg" alt="User 3">
        <h4>Kana varotaria</h4>
        <p class="stars">★★★★★</p>
        <p class="quote">“Amazing paneer, so soft and fresh! I love how natural everything feels.”</p>
      </div>

    </div>
  </div>
</section>
<footer class="site-footer">
  <div class="footer-container">
    <div class="footer-about">
      <h4>🧈 Daily Dairy</h4>
      <p>Pure, farm-fresh dairy products delivered with love and tradition — straight from our cows to your kitchen.</p>
    </div>

    <div class="footer-links">
      <h4>Quick Links</h4>
      <ul>
        <li><a href="#">Home</a></li>
        <li><a href="#">Products</a></li>
        <li><a href="#">Gallery</a></li>
        <li><a href="#">Testimonials</a></li>
        <li><a href="#">Contact</a></li>
      </ul>
    </div>

    <div class="footer-contact">
      <h4>Get in Touch</h4>
      <p><i class="ph ph-map-pin"></i> Rajkot, Gujarat, India</p>
      <p><i class="ph ph-phone-call"></i> +91 98765 43210</p>
      <p><i class="ph ph-envelope"></i> hello@dailydairy.in</p>
      <div class="footer-socials">
        <a href="#"><i class="ph ph-facebook-logo"></i></a>
        <a href="#"><i class="ph ph-instagram-logo"></i></a>
        <a href="#"><i class="ph ph-whatsapp-logo"></i></a>
      </div>
    </div>
  </div>

  <div class="footer-bottom">
    <p>&copy; 2025 Daily Dairy. All rights reserved.</p>
  </div>
</footer>

  <script src="script.js"></script>
</body>
</html>

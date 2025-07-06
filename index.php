<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Daily Diary | Home</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="style.css">
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


  <script src="script.js"></script>
</body>
</html>

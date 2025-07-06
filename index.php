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

  <script src="script.js"></script>
</body>
</html>

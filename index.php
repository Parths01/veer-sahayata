<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Veer Sahayata - Welfare & Support App for Indian Defence Personnel & Families</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
            <div class="container">
                <a class="navbar-brand" href="index.php">
                    <img src="./assets/images/logo.jpg" alt="Veer Sahayata" height="50">
                    <span class="ms-2">Veer Sahayata</span>
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="#home">Home</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#about">About</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#services">Services</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#contact">Contact</a>
                        </li>
                        <li class="nav-item">
                            <a class="btn btn-warning ms-2" href="login.php">Login</a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <!-- Hero Section with Slideshow -->
    <section id="home" class="hero-section">
        <div id="carouselExampleAutoplaying" class="carousel slide" data-bs-ride="carousel">
            <div class="carousel-inner">
                <div class="carousel-item active">
                    <img src="./assets/images/army.jpg" class="d-block w-100" alt="Indian Army">
                    <div class="carousel-caption d-none d-md-block">
                        <h1>Supporting Our Heroes</h1>
                        <p>Welfare & Support for Indian Defence Personnel & Families</p>
                        <a href="login.php" class="btn btn-warning btn-lg">Get Started</a>
                    </div>
                </div>
                <div class="carousel-item">
                    <img src="./assets/images/navy.jpg" class="d-block w-100" alt="Indian Navy">
                    <div class="carousel-caption d-none d-md-block">
                        <h1>Honoring Our Navy</h1>
                        <p>Comprehensive support for naval personnel and their families</p>
                        <a href="login.php" class="btn btn-warning btn-lg">Join Now</a>
                    </div>
                </div>
                <div class="carousel-item">
                    <img src="./assets/images/airforce.jpg" class="d-block w-100" alt="Indian Air Force">
                    <div class="carousel-caption d-none d-md-block">
                        <h1>Saluting Our Air Warriors</h1>
                        <p>Dedicated welfare services for air force personnel</p>
                        <a href="login.php" class="btn btn-warning btn-lg">Access Services</a>
                    </div>
                </div>
            </div>
            <button class="carousel-control-prev" type="button" data-bs-target="#carouselExampleAutoplaying" data-bs-slide="prev">
                <span class="carousel-control-prev-icon"></span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#carouselExampleAutoplaying" data-bs-slide="next">
                <span class="carousel-control-next-icon"></span>
            </button>
        </div>
    </section>

    <!-- News Updates Section -->
    <section class="news-section py-5">
        <div class="container">
            <h2 class="text-center mb-5">Latest Defence News</h2>
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="news-card">
                        <img src="./assets/images/news1.jpg" alt="Defence News" class="img-fluid">
                        <div class="card-body">
                            <h5>New Defence Policy Updates</h5>
                            <p>Latest updates on defence policies affecting personnel welfare...</p>
                            <small class="text-muted">2 days ago</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="news-card">
                        <img src="./assets/images/news2.jpg" alt="Defence News" class="img-fluid">
                        <div class="card-body">
                            <h5>Pension Scheme Enhancement</h5>
                            <p>Government announces new benefits for retired defence personnel...</p>
                            <small class="text-muted">1 week ago</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="news-card">
                        <img src="./assets/images/news3.jpg" alt="Defence News" class="img-fluid">
                        <div class="card-body">
                            <h5>Health Care Improvements</h5>
                            <p>New ECHS centers opening across the country for better healthcare...</p>
                            <small class="text-muted">3 days ago</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section id="services" class="services-section py-5 bg-light">
        <div class="container">
            <h2 class="text-center mb-5">Our Services</h2>
            <div class="row">
                <div class="col-md-3 mb-4">
                    <div class="service-card text-center">
                        <i class="fas fa-money-bill-wave fa-3x mb-3 text-primary"></i>
                        <h5>Pension Management</h5>
                        <p>Track and manage your pension disbursements</p>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="service-card text-center">
                        <i class="fas fa-chart-line fa-3x mb-3 text-success"></i>
                        <h5>Loan Tracker</h5>
                        <p>Monitor your loans and get business suggestions</p>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="service-card text-center">
                        <i class="fas fa-graduation-cap fa-3x mb-3 text-info"></i>
                        <h5>Education Support</h5>
                        <p>Find colleges with defence quota and scholarships</p>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="service-card text-center">
                        <i class="fas fa-heartbeat fa-3x mb-3 text-danger"></i>
                        <h5>Healthcare</h5>
                        <p>Access ECHS centers and health records</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="contact-section py-5">
        <div class="container">
            <h2 class="text-center mb-5">Contact Information</h2>
            <div class="row">
                <div class="col-md-4">
                    <div class="contact-info text-center">
                        <i class="fas fa-map-marker-alt fa-2x mb-3"></i>
                        <h5>Address</h5>
                        <p>Defence Welfare Office<br>New Delhi, India</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="contact-info text-center">
                        <i class="fas fa-phone fa-2x mb-3"></i>
                        <h5>Phone</h5>
                        <p>+91-11-1234-5678<br>Toll Free: 1800-XXX-XXXX</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="contact-info text-center">
                        <i class="fas fa-envelope fa-2x mb-3"></i>
                        <h5>Email</h5>
                        <p>support@veersahayata.gov.in<br>welfare@defence.gov.in</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer bg-dark text-white py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <p>&copy; 2025 Veer Sahayata. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-end">
                    <p>Developed for Indian Defence Personnel & Families</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/script.js"></script>
</body>
</html>

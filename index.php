<?php
require_once 'db_connection.php';
$page_title = "Welcome to FriendFinder";
include 'header.php';
?>

<div class="container-fluid p-0">
    <!-- Hero Section -->
    <div class="hero-section text-center text-white py-5" style="background: linear-gradient(135deg, #6e45e2 0%, #88d3ce 100%);">
        <div class="container">
            <h1 class="display-4 fw-bold mb-4">Discover Connections Through YouTube</h1>
            <p class="lead mb-4">Find friends who share your passion for the same YouTube channels and content</p>
            <a href="google_auth_youtube.php" class="btn btn-light btn-lg px-4 me-2">Connect with YouTube</a>
            <a href="#features" class="btn btn-outline-light btn-lg px-4">How It Works</a>
        </div>
    </div>

    <!-- Features Section -->
    <div id="features" class="py-5 bg-light">
        <div class="container">
            <h2 class="text-center mb-5">Why Use FriendFinder?</h2>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body text-center">
                            <i class="fas fa-user-friends fa-3x text-primary mb-3"></i>
                            <h5 class="card-title">Find YouTube Friends</h5>
                            <p class="card-text">Connect with people who love the same YouTube channels as you do</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body text-center">
                            <i class="fas fa-chart-line fa-3x text-primary mb-3"></i>
                            <h5 class="card-title">Discover New Content</h5>
                            <p class="card-text">Explore new channels and videos based on your friends' interests</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body text-center">
                            <i class="fas fa-shield-alt fa-3x text-primary mb-3"></i>
                            <h5 class="card-title">Safe and Secure</h5>
                            <p class="card-text">Your data is protected, and you control what you share</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- How It Works Section -->
    <div class="py-5">
        <div class="container">
            <h2 class="text-center mb-5">How FriendFinder Works</h2>
            <div class="row align-items-center">
                <div class="col-md-6">
                    <div class="steps-container">
                        <div class="step">
                            <div class="step-icon">1</div>
                            <div class="step-content">
                                <h5>Connect Your YouTube Account</h5>
                                <p>Securely link your YouTube account to FriendFinder</p>
                            </div>
                        </div>
                        <div class="step">
                            <div class="step-icon">2</div>
                            <div class="step-content">
                                <h5>Analyze Subscriptions</h5>
                                <p>We analyze your YouTube subscriptions to understand your interests</p>
                            </div>
                        </div>
                        <div class="step">
                            <div class="step-icon">3</div>
                            <div class="step-content">
                                <h5>Discover Matches</h5>
                                <p>Find people with similar YouTube interests and subscriptions</p>
                            </div>
                        </div>
                        <div class="step">
                            <div class="step-icon">4</div>
                            <div class="step-content">
                                <h5>Connect and Chat</h5>
                                <p>Start conversations and make new friends who share your passions</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="text-center">
                        <i class="fas fa-laptop-code fa-10x text-primary"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Call to Action -->
    <div class="py-5 bg-primary text-white">
        <div class="container text-center">
            <h2 class="mb-4">Ready to Find YouTube Friends?</h2>
            <a href="google_auth_youtube.php" class="btn btn-light btn-lg px-4">Get Started Now</a>
        </div>
    </div>
</div>

<style>
.steps-container {
    position: relative;
}
.step {
    display: flex;
    align-items: flex-start;
    margin-bottom: 2rem;
}
.step-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background-color: #6e45e2;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    margin-right: 1rem;
    flex-shrink: 0;
}
.step-content {
    flex-grow: 1;
}
.steps-container::before {
    content: '';
    position: absolute;
    top: 0;
    bottom: 0;
    left: 20px;
    width: 2px;
    background-color: #e0e0e0;
    z-index: -1;
}
</style>

<?php include 'footer.php'; ?>
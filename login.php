<?php
require_once 'config/db.php';

if (is_logged_in()) {
    header('Location: ' . login_redirect_for_role(current_user()['role']));
    exit;
}

$page_title = 'Disaster Relief Net — Official School Readiness Portal';
$public_layout = true;
include_once 'includes/header.php';
?>

<main class="landing-page">
    <section class="hero-banner">
        <div class="hero-banner-content">
            <span class="hero-badge">Official School Disaster Portal</span>
            <h1>Protecting Campuses.<br><span>Empowering Communities.</span></h1>
            <p class="hero-lead">
                Monitor emergency alerts, report incidents, request assistance, and document
                school preparedness activities — all in one trusted platform.
            </p>
            <div class="hero-cta">
                <a href="register.php" class="btn btn-light">Create Account</a>
                <a href="signin.php" class="btn btn-outline-light">Sign In</a>
                <a href="report_incident.php" class="btn btn-danger">Report an Incident</a>
            </div>
        </div>
        <div class="hero-banner-visual" aria-hidden="true">
            <div class="hero-stat-cards">
                <div class="hero-stat-card">
                    <strong>24/7</strong>
                    <span>Alert Monitoring</span>
                </div>
                <div class="hero-stat-card">
                    <strong>Live</strong>
                    <span>Incident Mapping</span>
                </div>
                <div class="hero-stat-card">
                    <strong>100%</strong>
                    <span>Campus Ready</span>
                </div>
            </div>
        </div>
    </section>

    <section class="section-block">
        <div class="about-grid">
            <div class="about-copy">
                <span class="section-label">About Us</span>
                <h2>Built for schools that take disaster readiness seriously</h2>
                <p>
                    Disaster Relief Net connects students, faculty, and response teams through
                    a centralized system for alerts, situation reports, help requests, and
                    preparedness documentation.
                </p>
                <p>
                    From earthquake drills to flood response, our platform helps your school
                    stay organized, informed, and ready when every second counts.
                </p>
                <div class="about-actions">
                    <a href="signin.php" class="btn btn-primary">Staff Sign In &rarr;</a>
                    <a href="register.php" class="btn btn-secondary">Create Civilian Account</a>
                </div>
            </div>
            <div class="about-visual">
                <div class="about-visual-inner">
                    <div class="about-icon">&#127979;</div>
                    <h3>School Disaster Readiness</h3>
                    <p>Coordinate drills, share files, and track incidents in real time.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="section-block section-muted">
        <div class="section-head">
            <span class="section-label">Our Services</span>
            <h2>Everything your community needs in one place</h2>
        </div>
        <div class="feature-grid">
            <article class="feature-card">
                <div class="feature-icon feature-icon-red">&#128680;</div>
                <h3>Incident Reporting</h3>
                <p>Submit situation reports with location details so response teams can act immediately.</p>
                <a href="report_incident.php">Report now &rarr;</a>
            </article>
            <article class="feature-card">
                <div class="feature-icon feature-icon-blue">&#128657;</div>
                <h3>Emergency Assistance</h3>
                <p>Request help for food, shelter, medical aid, or evacuation support during crises.</p>
                <a href="get_help.php">Get help &rarr;</a>
            </article>
            <article class="feature-card">
                <div class="feature-icon feature-icon-green">&#128193;</div>
                <h3>Activity Drive</h3>
                <p>Upload and share drill documents, photos, and preparedness files by activity folder.</p>
                <a href="activities.php">Browse files &rarr;</a>
            </article>
        </div>
    </section>

    <section class="cta-band">
        <div class="cta-band-inner">
            <div>
                <h2>Join the community or sign in</h2>
                <p>Create a free civilian account for the public dashboard, or sign in as staff to manage operations.</p>
            </div>
            <div class="cta-band-actions">
                <a href="register.php" class="btn btn-light">Create Account</a>
                <a href="signin.php" class="btn btn-outline-light">Sign In</a>
            </div>
        </div>
    </section>
</main>

<?php include_once 'includes/footer.php'; ?>

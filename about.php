<?php
require_once 'config/config.php';
require_once 'classes/Helpers.php';
require_once 'classes/Database.php'; // if later you want stats
include 'includes/header.php';
include 'includes/nav.php';

/* (Optional) Basic stats – safe fallback if tables exist */
$pdo = null;
$totalJobs = $activeWFH = $approvedEmployers = $jobSeekers = null;
try {
    $pdo = Database::getConnection();
    $totalJobs = (int)$pdo->query("SELECT COUNT(*) FROM jobs WHERE moderation_status='Approved'")->fetchColumn();
    $activeWFH = (int)$pdo->query("SELECT COUNT(*) FROM jobs WHERE remote_option='Work From Home' AND moderation_status='Approved'")->fetchColumn();
    $approvedEmployers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='employer' AND employer_status='Approved'")->fetchColumn();
    $jobSeekers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='job_seeker'")->fetchColumn();
} catch (Throwable $e) {
    // silent
}

?>
<style>
    /* Modern Color Palette (aligned with global theme) */
    :root {
        --primary-blue: #1E3A8A;
        /* Deep blue */
        --primary-purple: #14B8A6;
        /* Alias to teal for compatibility */
        --secondary-teal: #14B8A6;
        /* Explicit secondary */
        --accent-yellow: #FACC15;
        /* Bright yellow */
        --neutral-light: #F9FAFB;
        /* Light gray */
        --neutral-white: #ffffff;
        --text-dark: #111827;
        /* Near-black */
        --text-body: #374151;
        /* Gray-700 */
        --success-green: #198754;
        --warning-orange: #fd7e14;
    }

    /* Enhanced Hero Section */
    .about-hero {
        background: linear-gradient(135deg, var(--primary-blue) 0%, var(--primary-purple) 100%);
        color: var(--neutral-white);
        position: relative;
        overflow: hidden;
        display: flex;
        align-items: center;
        width: 100%;
    }

    /* Optional background image (add class about-hero-img and set --hero-img:url('...')) */
    .about-hero.about-hero-img {
        background: linear-gradient(135deg, rgba(var(--primary-blue-rgb), .88), rgba(var(--secondary-teal-rgb), .88)), var(--hero-img, radial-gradient(circle at 40% 40%, var(--primary-blue), var(--primary-purple)));
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
    }

    .about-hero.about-hero-img::before {
        display: none;
    }

    .about-hero.about-hero-img::after {
        opacity: .15;
    }

    /* Text shadow for readability when image present */
    .about-hero.about-hero-img h1,
    .about-hero.about-hero-img p,
    .about-hero.about-hero-img .btn-modern {
        text-shadow: 0 2px 4px rgba(0, 0, 0, .3);
    }

    /* Height control with clamp */
    .about-hero {
        min-height: clamp(420px, 58vh, 620px);
    }

    /* Fullscreen variant: occupy entire viewport so next section is hidden until scroll */
    .about-hero.hero-full {
        min-height: 100vh;
    }

    /* Overlay strength variants */
    .about-hero.overlay-light.about-hero-img {
        background: linear-gradient(135deg, rgba(var(--primary-blue-rgb), .55), rgba(var(--secondary-teal-rgb), .55)), var(--hero-img) center/cover no-repeat;
    }

    .about-hero.overlay-medium.about-hero-img {
        background: linear-gradient(135deg, rgba(var(--primary-blue-rgb), .72), rgba(var(--secondary-teal-rgb), .72)), var(--hero-img) center/cover no-repeat;
    }

    .about-hero.overlay-strong.about-hero-img {
        background: linear-gradient(135deg, rgba(var(--primary-blue-rgb), .90), rgba(var(--secondary-teal-rgb), .90)), var(--hero-img) center/cover no-repeat;
    }

    /* Parallax (desktop only to avoid mobile repaint jank) */
    @media (min-width: 992px) {
        .about-hero.parallax {
            background-attachment: fixed;
        }
    }

    /* Mobile crop adjustments */
    @media (max-width: 767.98px) {
        .about-hero.about-hero-img {
            background-position: center top;
        }
    }

    /* Wave divider at bottom */
    .about-hero .hero-inner {
        position: relative;
        z-index: 3;
    }

    @media (min-width: 992px) {
        .about-hero {
            border-radius: 0;
        }
    }

    .about-hero::before {
        content: '';
        position: absolute;
        inset: 0;
        background:
            radial-gradient(circle at 20% 30%, rgba(var(--accent-yellow-rgb), .15), transparent 50%),
            radial-gradient(circle at 80% 20%, rgba(255, 255, 255, .1), transparent 60%),
            radial-gradient(circle at 30% 80%, rgba(var(--secondary-teal-rgb), .2), transparent 70%);
        animation: heroGlow 8s ease-in-out infinite alternate;
    }

    .about-hero::after {
        content: '';
        position: absolute;
        top: -50%;
        right: -20%;
        width: 100%;
        height: 200%;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="1"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>') repeat;
        opacity: 0.3;
        animation: gridMove 20s linear infinite;
    }

    @keyframes heroGlow {
        0% {
            opacity: 0.8;
        }

        100% {
            opacity: 1;
        }
    }

    @keyframes gridMove {
        0% {
            transform: translateX(0) translateY(0);
        }

        100% {
            transform: translateX(10px) translateY(10px);
        }
    }

    /* Fade-in Animation */
    .fade-in {
        opacity: 0;
        transform: translateY(30px);
        transition: all 0.8s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    }

    .fade-in.visible {
        opacity: 1;
        transform: translateY(0);
    }

    /* Feature Cards */
    .feature-card {
        background: var(--neutral-white) !important;
        border: 1px solid rgba(30, 58, 138, 0.12) !important;
        border-radius: 1.25rem !important;
        padding: 2.5rem !important;
        height: 100% !important;
        transition: all 0.35s cubic-bezier(0.25, 0.46, 0.45, 0.94) !important;
        position: relative !important;
        overflow: hidden !important;
        box-shadow: 0 4px 16px -4px rgba(30, 58, 138, 0.08) !important;
    }

    .feature-card::before {
        content: '' !important;
        position: absolute !important;
        top: 0 !important;
        left: 0 !important;
        right: 0 !important;
        height: 5px !important;
        background: linear-gradient(90deg, var(--primary-blue), var(--primary-purple)) !important;
        transform: scaleX(0) !important;
        transform-origin: left !important;
        transition: transform 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94) !important;
    }

    .feature-card:hover {
        transform: translateY(-8px) !important;
        box-shadow: 0 24px 48px -12px rgba(30, 58, 138, 0.2), 0 12px 24px -8px rgba(20, 184, 166, 0.12) !important;
        border-color: rgba(20, 184, 166, 0.3) !important;
    }

    .feature-card:hover::before {
        transform: scaleX(1) !important;
    }

    .feature-icon {
        width: 4.5rem !important;
        height: 4.5rem !important;
        border-radius: 1.25rem !important;
        background: linear-gradient(135deg, var(--primary-blue) 0%, var(--primary-purple) 100%) !important;
        color: var(--neutral-white) !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        font-size: 1.75rem !important;
        margin-bottom: 2rem !important;
        transition: all 0.35s cubic-bezier(0.25, 0.46, 0.45, 0.94) !important;
        box-shadow: 0 6px 20px -6px rgba(30, 58, 138, 0.4) !important;
    }

    .feature-card:hover .feature-icon {
        transform: scale(1.12) rotate(5deg) !important;
        box-shadow: 0 10px 28px -8px rgba(30, 58, 138, 0.5) !important;
    }

    .feature-card h3,
    .feature-card h4,
    .feature-card h5 {
        margin-bottom: 1.25rem !important;
        font-weight: 700 !important;
        letter-spacing: -0.01em !important;
        line-height: 1.3 !important;
    }

    .feature-card p {
        line-height: 1.7 !important;
        margin-bottom: 1rem !important;
    }

    /* Inverse variant (improved contrast vs gradient) */
    .feature-card-inverse {
        background: linear-gradient(135deg, var(--primary-blue) 0%, var(--primary-purple) 100%);
        color: #ffffff;
        border: 1px solid rgba(255, 255, 255, .25);
        box-shadow: 0 12px 32px -8px rgba(var(--primary-blue-rgb), .55), 0 6px 20px -6px rgba(var(--secondary-teal-rgb), .45);
        position: relative;
    }

    .feature-card-inverse::before {
        content: '';
        position: absolute;
        inset: 0;
        background: radial-gradient(circle at 30% 25%, rgba(255, 255, 255, .25), transparent 60%),
            radial-gradient(circle at 80% 70%, rgba(255, 255, 255, .18), transparent 65%);
        pointer-events: none;
    }

    .feature-icon-inverse {
        width: 4rem;
        height: 4rem;
        border-radius: 1rem;
        background: #fff;
        color: var(--primary-blue);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        box-shadow: 0 6px 18px -4px rgba(0, 0, 0, .25);
    }

    .text-inverse-heading {
        color: #ffffff !important;
        text-shadow: 0 1px 3px rgba(0, 0, 0, .55), 0 0 2px rgba(0, 0, 0, .35);
    }

    .text-inverse-body {
        color: #ffffff !important;
        font-size: .98rem;
        line-height: 1.55;
        font-weight: 500;
        text-shadow: 0 1px 3px rgba(0, 0, 0, .55), 0 0 2px rgba(0, 0, 0, .35);
    }

    .feature-card-inverse:hover {
        box-shadow: 0 20px 42px -8px rgba(var(--primary-blue-rgb), .55), 0 10px 28px -6px rgba(var(--secondary-teal-rgb), .45);
    }

    .feature-card-inverse:hover .feature-icon-inverse {
        transform: scale(1.1) rotate(4deg);
    }

    /* Stats Cards */
    .stat-card {
        background: rgba(255, 255, 255, 0.98) !important;
        backdrop-filter: blur(12px) !important;
        border-radius: 1.25rem !important;
        padding: 2rem !important;
        text-align: center !important;
        border: 1px solid rgba(30, 58, 138, 0.15) !important;
        transition: all 0.35s cubic-bezier(0.25, 0.46, 0.45, 0.94) !important;
        box-shadow: 0 4px 16px -4px rgba(0, 0, 0, 0.08) !important;
    }

    .stat-card:hover {
        transform: translateY(-6px) !important;
        box-shadow: 0 12px 36px -8px rgba(30, 58, 138, 0.2) !important;
        border-color: rgba(20, 184, 166, 0.3) !important;
    }

    .stat-number {
        font-size: 2.5rem !important;
        font-weight: 800 !important;
        color: var(--primary-blue) !important;
        display: block !important;
        margin-bottom: 0.75rem !important;
        letter-spacing: -0.02em !important;
    }

    .stat-card small {
        font-size: 0.9rem !important;
        font-weight: 600 !important;
        color: #6B7280 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.5px !important;
    }

    /* About hero nav pills (improved contrast) */
    .about-hero .btn-modern {
        background: rgba(255, 255, 255, .12) !important;
        color: var(--neutral-white) !important;
        border: 1px solid rgba(255, 255, 255, .45) !important;
        backdrop-filter: saturate(160%) blur(4px) !important;
    }

    .about-hero .btn-modern:hover,
    .about-hero .btn-modern:focus {
        background: var(--neutral-white) !important;
        color: var(--primary-blue) !important;
        border-color: var(--neutral-white) !important;
        box-shadow: 0 6px 18px -6px rgba(0, 0, 0, .35), 0 0 0 3px rgba(255, 255, 255, .25) !important;
    }

    .about-hero .btn-modern.active {
        background: var(--neutral-white) !important;
        color: var(--primary-blue) !important;
        border-color: var(--neutral-white) !important;
        font-weight: 700 !important;
    }

    .about-hero .btn-modern:not(.active) {
        opacity: .9 !important;
    }

    .about-hero .btn-modern:not(.active):hover {
        opacity: 1 !important;
    }

    /* Enhanced Buttons */
    .btn-modern {
        border-radius: 50px !important;
        padding: 0.85rem 1.75rem !important;
        font-weight: 600 !important;
        text-transform: uppercase !important;
        letter-spacing: 0.5px !important;
        font-size: 0.875rem !important;
        transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94) !important;
        position: relative !important;
        overflow: hidden !important;
        border: 1px solid transparent !important;
    }

    .btn-modern::before {
        content: '' !important;
        position: absolute !important;
        inset: 0 !important;
        background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.25), transparent) !important;
        transform: translateX(-100%) !important;
        transition: transform 0.6s ease !important;
    }

    .btn-modern:hover::before {
        transform: translateX(100%) !important;
    }

    .btn-primary-modern {
        background: linear-gradient(135deg, var(--primary-blue), var(--primary-purple)) !important;
        border: none !important;
        color: white !important;
    }

    .btn-primary-modern:hover {
        transform: translateY(-3px) !important;
        box-shadow: 0 12px 32px -8px rgba(30, 58, 138, 0.5) !important;
    }

    /* Timeline Enhancement */
    .timeline-container {
        position: relative !important;
        padding-left: 3.5rem !important;
        margin-top: 2rem !important;
    }

    .timeline-line {
        position: absolute !important;
        left: 1.5rem !important;
        top: 0 !important;
        bottom: 0 !important;
        width: 4px !important;
        background: linear-gradient(to bottom, var(--primary-blue), var(--primary-purple)) !important;
        border-radius: 3px !important;
    }

    .timeline-item {
        position: relative !important;
        margin-bottom: 3rem !important;
        padding: 2rem !important;
        background: var(--neutral-white) !important;
        border-radius: 1.25rem !important;
        border: 1px solid rgba(30, 58, 138, 0.12) !important;
        box-shadow: 0 6px 20px -6px rgba(30, 58, 138, 0.1) !important;
        transition: all 0.35s cubic-bezier(0.25, 0.46, 0.45, 0.94) !important;
    }

    .timeline-item:hover {
        transform: translateX(12px) !important;
        box-shadow: 0 12px 36px -10px rgba(30, 58, 138, 0.2), 0 6px 18px -6px rgba(20, 184, 166, 0.12) !important;
        border-color: rgba(20, 184, 166, 0.3) !important;
    }

    .timeline-dot {
        position: absolute !important;
        left: -3.5rem !important;
        top: 2rem !important;
        width: 24px !important;
        height: 24px !important;
        border: 5px solid var(--primary-blue) !important;
        border-radius: 50% !important;
        background: var(--neutral-white) !important;
        box-shadow: 0 0 0 6px rgba(30, 58, 138, 0.15) !important;
        animation: pulse 2.5s infinite !important;
        z-index: 2 !important;
    }

    @keyframes pulse {
        0% {
            box-shadow: 0 0 0 0 rgba(30, 58, 138, 0.4);
        }

        70% {
            box-shadow: 0 0 0 12px rgba(30, 58, 138, 0);
        }

        100% {
            box-shadow: 0 0 0 0 rgba(30, 58, 138, 0);
        }
    }

    /* Timeline Item Content Spacing */
    .timeline-item h3,
    .timeline-item h4 {
        margin-bottom: 1rem !important;
        font-weight: 700 !important;
    }

    .timeline-item .text-muted {
        margin-bottom: 1.5rem !important;
        font-size: 0.95rem !important;
    }

    .timeline-item .row {
        margin-top: 1rem !important;
    }

    .timeline-item .d-flex {
        padding: 0.25rem 0 !important;
    }

    .timeline-item .badge {
        margin-left: 0.75rem !important;
    }

    /* Section Headers */
    .section-header {
        position: relative;
        padding-bottom: 1.5rem !important;
        margin-bottom: 3.5rem !important;
    }

    .section-header::after {
        content: '' !important;
        position: absolute !important;
        bottom: 0 !important;
        left: 0 !important;
        width: 80px !important;
        height: 5px !important;
        background: linear-gradient(90deg, var(--primary-blue), var(--accent-yellow)) !important;
        border-radius: 3px !important;
    }

    .section-header h2 {
        color: var(--text-dark) !important;
        font-weight: 800 !important;
        margin-bottom: 0.75rem !important;
        font-size: 2.25rem !important;
        letter-spacing: -0.02em !important;
    }

    .section-header .lead {
        font-size: 1.15rem !important;
        line-height: 1.6 !important;
        color: #6B7280 !important;
    }

    /* Badges */
    .badge-modern {
        font-size: 0.75rem !important;
        font-weight: 700 !important;
        padding: 0.6rem 1.25rem !important;
        border-radius: 50px !important;
        text-transform: uppercase !important;
        letter-spacing: 0.6px !important;
        box-shadow: 0 2px 8px -2px rgba(0, 0, 0, 0.15) !important;
        border: 1px solid rgba(255, 255, 255, 0.2) !important;
    }

    /* List Styling Improvements */
    .feature-card ul {
        padding-left: 0 !important;
        list-style: none !important;
    }

    .feature-card ul li {
        padding: 0.5rem 0 !important;
        line-height: 1.6 !important;
    }

    .feature-card ul li small {
        font-size: 0.95rem !important;
        line-height: 1.6 !important;
    }

    /* Section Spacing */
    .section-anchor {
        scroll-margin-top: 100px !important;
        padding-top: 4rem !important;
        padding-bottom: 4rem !important;
        position: relative !important;
    }

    /* Add subtle separator between sections */
    .section-anchor::before {
        content: '' !important;
        position: absolute !important;
        top: 0 !important;
        left: 50% !important;
        transform: translateX(-50%) !important;
        width: 60% !important;
        max-width: 800px !important;
        height: 1px !important;
        background: linear-gradient(90deg, transparent 0%, rgba(30, 58, 138, 0.1) 20%, rgba(30, 58, 138, 0.2) 50%, rgba(30, 58, 138, 0.1) 80%, transparent 100%) !important;
    }

    .section-anchor:first-of-type::before {
        display: none !important;
    }

    /* Main Container Spacing */
    #main-content>.container {
        padding-top: 5rem !important;
        padding-bottom: 5rem !important;
    }

    /* Row Spacing */
    .row.g-4 {
        --bs-gutter-y: 2rem !important;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .about-hero {
            min-height: 400px !important;
            border-radius: 1rem !important;
        }

        .about-hero h1 {
            font-size: 2rem !important;
        }

        .about-hero .lead {
            font-size: 1rem !important;
        }

        .feature-card {
            margin-bottom: 2rem !important;
            padding: 2rem !important;
        }

        .timeline-container {
            padding-left: 2.5rem !important;
        }

        .timeline-dot {
            left: -2.75rem !important;
            width: 20px !important;
            height: 20px !important;
        }

        .stat-number {
            font-size: 1.5rem !important;
        }

        .section-header h2 {
            font-size: 1.75rem !important;
        }

        .section-anchor {
            padding-top: 3rem !important;
            padding-bottom: 3rem !important;
        }

        #main-content>.container {
            padding-top: 3rem !important;
            padding-bottom: 3rem !important;
        }
    }

    @media (min-width: 769px) and (max-width: 991px) {
        .feature-card {
            margin-bottom: 1.75rem !important;
        }
    }

    /* Alert/Info Box Improvements */
    .alert {
        border-radius: 1rem !important;
        padding: 1.5rem !important;
        border: 1px solid transparent !important;
        margin-bottom: 1.5rem !important;
    }

    .alert i {
        font-size: 1.1rem !important;
    }

    .alert small,
    .alert p {
        line-height: 1.6 !important;
    }

    /* Icon Spacing */
    .bi {
        vertical-align: -0.125em !important;
    }

    /* Improved Text Colors */
    .text-body {
        color: #4B5563 !important;
    }

    .text-muted {
        color: #6B7280 !important;
    }

    /* Border improvements */
    .border-start {
        border-left-width: 4px !important;
    }

    .border-4 {
        border-width: 4px !important;
    }

    /* Background variants */
    .bg-light {
        background-color: #F9FAFB !important;
    }

    .bg-gradient {
        background: linear-gradient(135deg, #F9FAFB 0%, #F3F4F6 100%) !important;
    }

    /* Custom scrollbar for webkit browsers */
    ::-webkit-scrollbar {
        width: 10px;
    }

    ::-webkit-scrollbar-track {
        background: var(--neutral-light);
        border-radius: 5px;
    }

    ::-webkit-scrollbar-thumb {
        background: linear-gradient(var(--primary-blue), var(--primary-purple));
        border-radius: 5px;
    }

    ::-webkit-scrollbar-thumb:hover {
        background: linear-gradient(var(--primary-purple), var(--primary-blue));
    }
</style>

<main id="main-content" class="flex-grow-1 mb-5">
    <!-- Full-width hero wrapper -->
    <div class="about-hero hero-full about-hero-img overlay-medium parallax shadow-lg fade-in mb-0" style="border-radius:0; --hero-img: url('assets/images/hero/bg1.jpg');">
        <div class="container p-4 p-lg-5 hero-inner">
            <div class="row align-items-center">
                <div class="col-12 text-center text-lg-start">
                    <h1 class="display-4 fw-bold mb-4 text-white">About the PWD Employment & Skills Portal</h1>
                    <p class="lead mb-4 text-white-75 fs-5">A focused platform connecting Persons with Disabilities (PWDs) to inclusive, remote‑friendly job opportunities and helping employers build truly accessible teams.</p>
                    <div class="d-flex flex-wrap gap-3 mb-1 justify-content-center justify-content-lg-start">
                        <a href="#mission" class="btn btn-modern active"><i class="bi bi-bullseye me-2"></i>Our Mission</a>
                        <a href="#features" class="btn btn-modern"><i class="bi bi-stars me-2"></i>Key Features</a>
                        <a href="#accessibility" class="btn btn-modern"><i class="bi bi-universal-access me-2"></i>Accessibility</a>
                        <a href="#roadmap" class="btn btn-modern"><i class="bi bi-map me-2"></i>Roadmap</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container py-5">

        <!-- MISSION SECTION -->
        <section id="mission" class="section-anchor fade-in">
            <div class="section-header">
                <h2 class="h2 fw-bold">
                    <i class="bi bi-bullseye me-3 text-primary"></i>Our Mission
                </h2>
                <p class="lead text-muted">Empowering PWD professionals through inclusive opportunities</p>
            </div>

            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="bi bi-bullseye" aria-hidden="true"></i>
                            <span class="visually-hidden">Focus Icon</span>
                        </div>
                        <h3 class="h4 fw-semibold mb-3 text-dark">Breaking Traditional Barriers</h3>
                        <p class="mb-3 text-body lh-lg">
                            Empower PWD professionals by removing traditional barriers—geography, bias, and inaccessible hiring processes—while
                            enabling employers to discover skilled, motivated talent in a structured, data‑assisted way.
                        </p>
                        <div class="bg-primary bg-opacity-10 p-3 rounded-3">
                            <p class="mb-0 fst-italic text-primary fw-semibold">
                                In short: <em>Faster and fairer hiring for the PWD community.</em>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="feature-card h-100 bg-gradient">
                        <div class="feature-icon" style="background: linear-gradient(135deg, var(--secondary-teal), var(--success-green)) !important;">
                            <i class="bi bi-people-fill"></i>
                        </div>
                        <h4 class="h5 fw-semibold mb-3">Impact Focus</h4>
                        <ul class="list-unstyled">
                            <li class="d-flex align-items-center mb-2">
                                <i class="bi bi-check-circle-fill text-success me-2"></i>
                                <small>Inclusive hiring processes</small>
                            </li>
                            <li class="d-flex align-items-center mb-2">
                                <i class="bi bi-check-circle-fill text-success me-2"></i>
                                <small>Skills-based matching</small>
                            </li>
                            <li class="d-flex align-items-center mb-2">
                                <i class="bi bi-check-circle-fill text-success me-2"></i>
                                <small>Remote-first opportunities</small>
                            </li>
                            <li class="d-flex align-items-center">
                                <i class="bi bi-check-circle-fill text-success me-2"></i>
                                <small>Bias-free evaluations</small>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <!-- WHY WE BUILT THIS -->
        <section class="section-anchor fade-in">
            <div class="section-header">
                <h2 class="h2 fw-bold">
                    <i class="bi bi-lightbulb me-3 text-warning"></i>Why We Built This
                </h2>
                <p class="lead text-muted">The challenges we're addressing</p>
            </div>

            <div class="row g-4">
                <div class="col-md-6">
                    <div class="feature-card border-start border-warning border-4">
                        <div class="feature-icon bg-warning">
                            <i class="bi bi-exclamation-triangle"></i>
                        </div>
                        <h4 class="h5 fw-semibold mb-3">Current Challenges</h4>
                        <ul class="list-unstyled">
                            <li class="d-flex align-items-start mb-3">
                                <i class="bi bi-arrow-right text-warning me-2 mt-1"></i>
                                <small>Many highly skilled PWD candidates never reach interviews because of filtering bias.</small>
                            </li>
                            <li class="d-flex align-items-start mb-3">
                                <i class="bi bi-arrow-right text-warning me-2 mt-1"></i>
                                <small>There is insufficient structured data linking accessibility needs to job requirements.</small>
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="feature-card border-start border-success border-4">
                        <div class="feature-icon bg-success">
                            <i class="bi bi-lightbulb-fill"></i>
                        </div>
                        <h4 class="h5 fw-semibold mb-3">Our Solution</h4>
                        <ul class="list-unstyled">
                            <li class="d-flex align-items-start mb-3">
                                <i class="bi bi-arrow-right text-success me-2 mt-1"></i>
                                <small>WFH at flexible roles are increasing — perfect opportunity to widen inclusion.</small>
                            </li>
                            <li class="d-flex align-items-start mb-3">
                                <i class="bi bi-arrow-right text-success me-2 mt-1"></i>
                                <small>Employers need lightweight tools to verify & manage inclusive hiring without heavy HR software.</small>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <!-- KEY FEATURES -->
        <section id="features" class="section-anchor fade-in">
            <div class="section-header">
                <h2 class="h2 fw-bold">
                    <i class="bi bi-stars me-3 text-primary"></i>Key Features
                </h2>
                <p class="lead text-muted">Powerful tools for inclusive hiring</p>
            </div>

            <div class="row g-4">
                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="bi bi-person-vcard"></i>
                        </div>
                        <h3 class="h5 fw-semibold mb-3">Rich Candidate Profiles</h3>
                        <p class="text-body mb-3">Skills, experience, education, accessibility tags, optional resume & video intro.</p>
                        <span class="badge badge-modern text-bg-primary">PWD Focused</span>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="bi bi-diagram-3"></i>
                        </div>
                        <h3 class="h5 fw-semibold mb-3">Matching Criteria Lock</h3>
                        <p class="text-body mb-3">Once applicants exist, core fields (skills/exp/education) lock to preserve fairness.</p>
                        <span class="badge badge-modern text-bg-warning">Integrity</span>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="bi bi-building-check"></i>
                        </div>
                        <h3 class="h5 fw-semibold mb-3">Employer Verification</h3>
                        <p class="text-body mb-3">Document upload & status control to reduce fake or exploitative listings.</p>
                        <span class="badge badge-modern text-bg-success">Trust</span>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="bi bi-clipboard2-data"></i>
                        </div>
                        <h3 class="h5 fw-semibold mb-3">Structured Filtering</h3>
                        <p class="text-body mb-3">Education, max experience, region/city, min pay, accessibility tags.</p>
                        <span class="badge badge-modern text-bg-secondary">Efficiency</span>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="bi bi-speedometer2"></i>
                        </div>
                        <h3 class="h5 fw-semibold mb-3">Match Scoring</h3>
                        <p class="text-body mb-3">Weighted evaluation of required vs general skills & experience.</p>
                        <span class="badge badge-modern text-bg-info">Upcoming</span>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="bi bi-lock"></i>
                        </div>
                        <h3 class="h5 fw-semibold mb-3">Data Privacy Respect</h3>
                        <p class="text-body mb-3">Only essential fields stored; optional uploads; future consent controls.</p>
                        <span class="badge badge-modern text-bg-dark">Privacy</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- DEVELOPMENT ROADMAP -->
        <section id="roadmap" class="section-anchor fade-in">
            <div class="section-header">
                <h2 class="h2 fw-bold">
                    <i class="bi bi-map me-3 text-primary"></i>Development Roadmap
                </h2>
                <p class="lead text-muted">Our journey toward inclusive employment</p>
            </div>

            <div class="timeline-container">
                <div class="timeline-line"></div>

                <div class="timeline-item">
                    <div class="timeline-dot"></div>
                    <div class="d-flex align-items-center mb-2">
                        <h3 class="h4 fw-semibold mb-0 me-3">Phase 1</h3>
                        <span class="badge badge-modern text-bg-success">Current</span>
                    </div>
                    <p class="text-muted mb-3">Foundation & Core Features</p>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-check-circle-fill text-success me-2"></i>
                                <small>Core job posting & application flow</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-check-circle-fill text-success me-2"></i>
                                <small>Employer verification & status gating</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-check-circle-fill text-success me-2"></i>
                                <small>Basic filtering (education, exp, region, pay)</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="timeline-item">
                    <div class="timeline-dot"></div>
                    <div class="d-flex align-items-center mb-2">
                        <h3 class="h4 fw-semibold mb-0 me-3">Phase 2</h3>
                        <span class="badge badge-modern text-bg-primary">In Development</span>
                    </div>
                    <p class="text-muted mb-3">Enhanced Matching & User Experience</p>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-gear-fill text-primary me-2"></i>
                                <small>Weighted match scoring UI (progress bars)</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-gear-fill text-primary me-2"></i>
                                <small>In‑place applicant moderation (AJAX)</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-gear-fill text-primary me-2"></i>
                                <small>Profile field for accommodation preferences</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="timeline-item">
                    <div class="timeline-dot"></div>
                    <div class="d-flex align-items-center mb-2">
                        <h3 class="h4 fw-semibold mb-0 me-3">Phase 3</h3>
                        <span class="badge badge-modern text-bg-info">Planned</span>
                    </div>
                    <p class="text-muted mb-3">Communication & Analytics</p>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-clock text-info me-2"></i>
                                <small>Email or in‑app notification triggers</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-clock text-info me-2"></i>
                                <small>Analytics: hires per skill, time‑to‑fill</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-clock text-info me-2"></i>
                                <small>Admin reporting dashboard refinement</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="timeline-item">
                    <div class="timeline-dot"></div>
                    <div class="d-flex align-items-center mb-2">
                        <h3 class="h4 fw-semibold mb-0 me-3">Phase 4+</h3>
                        <span class="badge badge-modern text-bg-secondary">Future</span>
                    </div>
                    <p class="text-muted mb-3">Advanced Features & Scaling</p>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-lightbulb text-secondary me-2"></i>
                                <small>Accessibility compliance checklist for each job</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-lightbulb text-secondary me-2"></i>
                                <small>AI skill suggestion & resume parsing</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-lightbulb text-secondary me-2"></i>
                                <small>Multi‑language interface</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="alert alert-info border-0 bg-primary bg-opacity-10 mt-4">
                <div class="d-flex align-items-center">
                    <i class="bi bi-info-circle me-2 text-primary"></i>
                    <small class="text-primary"><strong>Note:</strong> Sequence subject to change based on user feedback and community needs.</small>
                </div>
            </div>
        </section>

        <!-- CONTACT & FEEDBACK -->
        <section id="contact" class="section-anchor fade-in">
            <div class="section-header">
                <h2 class="h2 fw-bold">
                    <i class="bi bi-chat-dots me-3 text-primary"></i>Contact & Feedback
                </h2>
                <p class="lead text-muted">Help us improve the platform</p>
            </div>

            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="feature-card">
                        <div class="feature-icon bg-info mb-3">
                            <i class="bi bi-megaphone"></i>
                        </div>
                        <h3 class="h4 fw-semibold mb-3">We're Always Listening</h3>
                        <p class="mb-4 text-body">
                            This portal is evolving. If you have a suggestion (feature, accessibility improvement, bug, refinement),
                            please reach out via the support / feedback channel inside the system (or the upcoming support form).
                        </p>

                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="d-flex align-items-center p-3 bg-light rounded-3">
                                    <i class="bi bi-lightbulb text-warning me-2"></i>
                                    <small><strong>Feature Ideas</strong><br>Weighted skill matching improvements</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex align-items-center p-3 bg-light rounded-3">
                                    <i class="bi bi-flag text-danger me-2"></i>
                                    <small><strong>Report Issues</strong><br>Inaccurate job or suspicious employer</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex align-items-center p-3 bg-light rounded-3">
                                    <i class="bi bi-universal-access text-success me-2"></i>
                                    <small><strong>Accessibility</strong><br>Screen reader or contrast issue</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="feature-card feature-card-inverse" aria-labelledby="feat-community-title" aria-describedby="feat-community-desc">
                        <div class="feature-icon feature-icon-inverse mb-3">
                            <i class="bi bi-heart-fill" aria-hidden="true"></i>
                        </div>
                        <h4 id="feat-community-title" class="h5 fw-semibold mb-3 text-inverse-heading">Community Driven</h4>
                        <p id="feat-community-desc" class="mb-0 text-inverse-body">
                            Your feedback shapes our roadmap. Together we build a more inclusive future for accessible employment.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- DISCLAIMER -->
        <section class="fade-in">
            <div class="feature-card border-start border-warning border-4 bg-light">
                <div class="d-flex align-items-start">
                    <i class="bi bi-exclamation-triangle text-warning me-3 mt-1 fs-5"></i>
                    <div>
                        <h4 class="h6 fw-semibold mb-2 text-dark">Important Disclaimer</h4>
                        <p class="small mb-0 text-body">
                            All data provided by employers and applicants are self‑reported. Always exercise standard diligence.
                            The platform aims to assist, not replace, responsible hiring judgment.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- FOOTER -->
        <div class="text-center py-4 mt-5 border-top">
            <p class="small text-muted mb-0">
                &copy; <?php echo date('Y'); ?> PWD Employment & Skills Portal ·
                <span class="text-primary fw-semibold">Inclusive opportunities start here.</span>
            </p>
        </div>

    </div>
</main>

<!-- JavaScript for Animations -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Intersection Observer for fade-in animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                }
            });
        }, observerOptions);

        // Observe all elements with fade-in class
        document.querySelectorAll('.fade-in').forEach(function(element) {
            observer.observe(element);
        });

        // Enhanced button interactions
        document.querySelectorAll('.btn-modern').forEach(function(button) {
            button.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px)';
            });

            button.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(function(anchor) {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('href');
                const targetElement = document.querySelector(targetId);

                if (targetElement) {
                    targetElement.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Add loading animation to feature cards
        setTimeout(function() {
            document.querySelectorAll('.feature-card').forEach(function(card, index) {
                setTimeout(function() {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(20px)';
                    card.style.transition = 'all 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94)';

                    requestAnimationFrame(function() {
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    });
                }, index * 100);
            });
        }, 500);

        // Stats counter animation
        function animateCounter(element, target) {
            let current = 0;
            const increment = target / 50;
            const timer = setInterval(function() {
                current += increment;
                if (current >= target) {
                    element.textContent = target.toLocaleString();
                    clearInterval(timer);
                } else {
                    element.textContent = Math.floor(current).toLocaleString();
                }
            }, 40);
        }

        // Animate stats when they come into view
        const statsObserver = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    const statNumber = entry.target.querySelector('.stat-number');
                    if (statNumber && !statNumber.dataset.animated) {
                        const targetText = statNumber.textContent.replace(/[,\s]/g, '');
                        const target = parseInt(targetText) || 0;
                        if (target > 0) {
                            statNumber.dataset.animated = 'true';
                            animateCounter(statNumber, target);
                        }
                    }
                }
            });
        });

        document.querySelectorAll('.stat-card').forEach(function(card) {
            statsObserver.observe(card);
        });
    });
</script>

<?php include 'includes/footer.php'; ?>
<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/app.php';
session_start_once();

if (is_logged_in()) {
    $role = current_user()['role'] ?? '';
    if ($role === 'admin') {
        redirect(BASE_URL . '/modules/admin/dashboard.php');
    }
    if ($role === 'adopter') {
        redirect(BASE_URL . '/modules/adopter/dashboard.php');
    }
}

$avail   = (int) db()->query("SELECT COUNT(*) FROM pets WHERE status = 'Available'")->fetchColumn();
$adopted = (int) db()->query("SELECT COUNT(*) FROM pets WHERE status = 'Adopted'")->fetchColumn();
$featured = db()->query(
    "SELECT id, name, breed, age_label, gender, status, primary_image
      FROM pets
      WHERE status = 'Available'
      ORDER BY created_at DESC
      LIMIT 3"
)->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Find Your Forever Companion';
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=DM+Sans:wght@300;400;500;600&display=swap');

/* Reset & Tokens */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --sand-50:  #faf8f4;
    --sand-100: #f3ede3;
    --sand-200: #e8dece;
    --sand-400: #c9b99a;
    --sand-600: #9c8469;
    --sand-800: #5c4d3a;
    --sand-900: #2e261c;

    --forest-50:  #eef5ee;
    --forest-100: #d0e5d0;
    --forest-300: #85ba86;
    --forest-500: #3d7c3e;
    --forest-600: #2a5e2b;
    --forest-700: #1c4320;
    --forest-900: #0d1f0e;

    --rust-400: #c0623a;
    --rust-500: #a3472a;

    --text-primary:   #1c1410;
    --text-secondary: #5c4d3a;
    --text-muted:     #8a7660;
    --border:         rgba(92, 77, 58, .12);
    --border-md:      rgba(92, 77, 58, .22);

    --radius-sm: 6px;
    --radius-md: 10px;
    --radius-lg: 16px;
    --radius-xl: 24px;

    --transition: 220ms cubic-bezier(.4, 0, .2, 1);
}

body { font-family: 'DM Sans', sans-serif; color: var(--text-primary); background: var(--sand-50); }

.lp-section { padding: 5rem 0; }
.lp-section--alt { background: var(--sand-100); padding: 5rem 0; }

.lp-container { max-width: 1320px; margin: 0 auto; padding: 0 2rem; }

.lp-label {
    display: inline-block;
    font-size: .7rem;
    font-weight: 600;
    letter-spacing: .12em;
    text-transform: uppercase;
    color: var(--forest-600);
    margin-bottom: 1rem;
}

.lp-h1 {
    font-family: 'Playfair Display', serif;
    font-size: clamp(2.6rem, 6vw, 4.4rem);
    font-weight: 700;
    line-height: 1.1;
    color: var(--text-primary);
    letter-spacing: -.02em;
}

.lp-h2 {
    font-family: 'Playfair Display', serif;
    font-size: clamp(1.9rem, 3.5vw, 2.8rem);
    font-weight: 700;
    line-height: 1.2;
    letter-spacing: -.015em;
    color: var(--text-primary);
}

.lp-body {
    font-size: 1.05rem;
    line-height: 1.75;
    color: var(--text-secondary);
}

/* Buttons */
.btn-lp {
    display: inline-flex;
    align-items: center;
    gap: .55rem;
    font-family: 'DM Sans', sans-serif;
    font-size: .9rem;
    font-weight: 600;
    padding: .8rem 1.65rem;
    border-radius: 999px;
    border: none;
    cursor: pointer;
    text-decoration: none;
    transition: background var(--transition), transform var(--transition), box-shadow var(--transition);
    white-space: nowrap;
}
.btn-lp--primary {
    background: var(--forest-600);
    color: #fff;
}
.btn-lp--primary:hover {
    background: var(--forest-700);
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(42, 94, 43, .25);
}
.btn-lp--outline {
    background: transparent;
    color: var(--text-primary);
    border: 1.5px solid var(--border-md);
}
.btn-lp--outline:hover {
    background: var(--sand-100);
    border-color: var(--sand-400);
}
.btn-lp--ghost-white {
    background: rgba(255,255,255,.12);
    color: #fff;
    border: 1.5px solid rgba(255,255,255,.3);
}
.btn-lp--ghost-white:hover {
    background: rgba(255,255,255,.22);
}

/* Hero */
.lp-hero {
    position: relative;
    overflow: hidden;
    background: var(--sand-50);
    padding: 5rem 0 4rem;
    border-bottom: 1px solid var(--border);
}

.lp-hero__bg {
    position: absolute;
    inset: 0;
    background:
        radial-gradient(ellipse 70% 60% at 80% 50%, rgba(61,124,62,.07) 0%, transparent 70%),
        radial-gradient(ellipse 50% 40% at 10% 80%, rgba(201,185,154,.18) 0%, transparent 60%);
    pointer-events: none;
}

.lp-hero__grid {
    display: grid;
    grid-template-columns: 1fr 1.1fr;
    gap: 5rem;
    align-items: center;
}

.lp-hero__badge {
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    font-size: .75rem;
    font-weight: 600;
    color: var(--forest-600);
    background: var(--forest-50);
    border: 1px solid rgba(61,124,62,.2);
    border-radius: 999px;
    padding: .35rem 1rem;
    margin-bottom: 1.5rem;
    letter-spacing: .04em;
}
.lp-hero__badge-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--forest-500);
    flex-shrink: 0;
    animation: pulse-dot 2s ease-in-out infinite;
}
@keyframes pulse-dot {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: .5; transform: scale(.75); }
}

.lp-hero__ctas { display: flex; gap: .75rem; flex-wrap: wrap; margin-top: 2rem; }

.lp-hero__visual {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: .75rem;
    position: relative;
}

.lp-hero__img-card {
    border-radius: var(--radius-lg);
    overflow: hidden;
    background: var(--sand-200);
    border: 1px solid var(--border);
}
.lp-hero__img-card:first-child {
    grid-row: 1 / 3;
    min-height: 420px;
}
.lp-hero__img-card:not(:first-child) { min-height: 200px; }

.lp-hero__img-card img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transition: transform 500ms ease;
}
.lp-hero__img-card:hover img { transform: scale(1.04); }

.lp-hero__float-card {
    position: absolute;
    bottom: -1.25rem;
    left: -1.25rem;
    background: #fff;
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    padding: .85rem 1.1rem;
    display: flex;
    align-items: center;
    gap: .7rem;
    box-shadow: 0 4px 24px rgba(0,0,0,.08);
    font-size: .82rem;
    z-index: 1;
}
.lp-hero__float-ic {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: var(--forest-50);
    display: grid;
    place-items: center;
    color: var(--forest-600);
    flex-shrink: 0;
    font-size: 1rem;
}
.lp-hero__float-num {
    font-family: 'Playfair Display', serif;
    font-size: 1.3rem;
    font-weight: 700;
    line-height: 1;
    color: var(--text-primary);
}
.lp-hero__float-lbl {
    font-size: .72rem;
    color: var(--text-muted);
    margin-top: .1rem;
}

/* Stats bar */
.lp-stats {
    background: var(--forest-700);
    padding: 2.5rem 0;
}
.lp-stats__grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
}
.lp-stat {
    text-align: center;
    padding: 0 1.5rem;
    border-right: 1px solid rgba(255,255,255,.1);
}
.lp-stat:last-child { border-right: none; }
.lp-stat__num {
    font-family: 'Playfair Display', serif;
    font-size: 2.4rem;
    font-weight: 700;
    color: #fff;
    line-height: 1;
}
.lp-stat__lbl {
    font-size: .7rem;
    font-weight: 500;
    letter-spacing: .1em;
    text-transform: uppercase;
    color: rgba(255,255,255,.5);
    margin-top: .4rem;
}

/* How it works */
.lp-steps {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1px;
    background: var(--border);
    border: 1px solid var(--border);
    border-radius: var(--radius-xl);
    overflow: hidden;
    margin-top: 3rem;
}
.lp-step {
    background: var(--sand-50);
    padding: 2.75rem 2.5rem;
    transition: background var(--transition);
}
.lp-step:hover { background: #fff; }

.lp-step__num {
    font-size: .65rem;
    font-weight: 700;
    letter-spacing: .14em;
    text-transform: uppercase;
    color: var(--text-muted);
    margin-bottom: 1.5rem;
}
.lp-step__icon {
    width: 48px;
    height: 48px;
    border-radius: var(--radius-md);
    display: grid;
    place-items: center;
    font-size: 1.2rem;
    margin-bottom: 1.25rem;
}
.lp-step__icon--g { background: rgba(61,124,62,.1); color: var(--forest-600); }
.lp-step__icon--o { background: rgba(192,98,58,.1);  color: var(--rust-400); }
.lp-step__icon--s { background: rgba(138,118,96,.1); color: var(--text-secondary); }

.lp-step h3 {
    font-family: 'Playfair Display', serif;
    font-size: 1.15rem;
    font-weight: 700;
    margin-bottom: .6rem;
}
.lp-step p {
    font-size: .875rem;
    color: var(--text-muted);
    line-height: 1.7;
}

/* Featured pets */
.lp-featured__head {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 2.5rem;
    flex-wrap: wrap;
}

.lp-pets-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.5rem;
}

/* Pet card */
.pet-card {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    transition: transform var(--transition), box-shadow var(--transition);
}
.pet-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 32px rgba(0,0,0,.09);
}
.pet-card__img {
    position: relative;
    height: 240px;
    overflow: hidden;
    background: var(--sand-200);
}
.pet-card__img img {
    width: 100%; height: 100%;
    object-fit: cover;
    display: block;
    transition: transform 400ms ease;
}
.pet-card:hover .pet-card__img img { transform: scale(1.05); }
.pet-card__status-overlay {
    position: absolute;
    top: .75rem; left: .75rem;
    font-size: .65rem; font-weight: 700;
    letter-spacing: .08em; text-transform: uppercase;
    padding: .28rem .75rem;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    width: fit-content;
    box-shadow: 0 2px 6px rgba(0,0,0,.15);
}
.pet-card__status-overlay--available {
    background: rgba(255,255,255,.92);
    color: var(--forest-600);
    border: 1px solid rgba(61,124,62,.25);
}
.pet-card__status-overlay--adopted {
    background: rgba(255,255,255,.92);
    color: var(--sand-800);
    border: 1px solid rgba(92,77,58,.2);
}
.pet-card__status-overlay--pending {
    background: rgba(255,255,255,.92);
    color: var(--rust-400);
    border: 1px solid rgba(192,98,58,.25);
}
.pet-card__body {
    padding: 1.25rem 1.4rem 1.4rem;
    flex: 1; display: flex; flex-direction: column;
}
.pet-card__name {
    font-family: 'Playfair Display', serif;
    font-size: 1.1rem; font-weight: 700;
    color: var(--text-primary); margin-bottom: .3rem;
}
.pet-card__meta {
    font-size: .8rem;
    color: var(--text-muted);
    margin-bottom: 1rem; flex: 1;
}
.pet-card__cta {
    display: inline-flex; align-items: center; gap: .4rem;
    font-size: .83rem; font-weight: 600;
    color: var(--forest-600);
    text-decoration: none;
    padding: .6rem 1.1rem;
    border: 1.5px solid rgba(61,124,62,.25);
    border-radius: 999px;
    transition: background var(--transition), border-color var(--transition);
    width: fit-content;
}
.pet-card__cta:hover {
    background: var(--forest-50);
    border-color: var(--forest-300);
}

/* Testimonials */
.lp-tgrid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.5rem;
    margin-top: 3rem;
}

.lp-tcard {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 1.75rem;
    position: relative;
}
.lp-tcard::before {
    content: '\201C';
    position: absolute;
    top: 1rem;
    right: 1.5rem;
    font-family: 'Playfair Display', serif;
    font-size: 4.5rem;
    line-height: 1;
    color: var(--sand-200);
    pointer-events: none;
}
.lp-tcard__quote {
    font-size: .9rem;
    line-height: 1.75;
    color: var(--text-secondary);
    margin-bottom: 1.25rem;
    position: relative;
}
.lp-tcard__author { display: flex; align-items: center; gap: .75rem; }
.lp-tcard__av {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    font-weight: 700;
    font-size: .85rem;
    flex-shrink: 0;
}
.lp-tcard__av--g { background: var(--forest-50); color: var(--forest-600); }
.lp-tcard__av--o { background: rgba(192,98,58,.1); color: var(--rust-400); }
.lp-tcard__av--s { background: var(--sand-100); color: var(--sand-800); }
.lp-tcard__name { font-size: .88rem; font-weight: 600; color: var(--text-primary); }
.lp-tcard__pet  { font-size: .73rem; color: var(--text-muted); margin-top: .1rem; }

/* Bottom CTA */
.lp-cta-band {
    background: linear-gradient(160deg, var(--forest-900) 0%, var(--forest-700) 100%);
    padding: 6rem 0;
    position: relative;
    overflow: hidden;
}
.lp-cta-band__bg {
    position: absolute;
    inset: 0;
    background:
        radial-gradient(ellipse 55% 70% at 95% 50%, rgba(61,124,62,.25) 0%, transparent 65%),
        radial-gradient(ellipse 40% 50% at 5%  60%, rgba(201,185,154,.08) 0%, transparent 60%);
    pointer-events: none;
}
.lp-cta-band__inner {
    position: relative;
    text-align: center;
    max-width: 42rem;
    margin: 0 auto;
}
.lp-cta-band h2 {
    font-family: 'Playfair Display', serif;
    font-size: clamp(1.9rem, 4vw, 2.9rem);
    font-weight: 700;
    color: #fff;
    margin-bottom: .85rem;
    letter-spacing: -.015em;
}
.lp-cta-band p {
    font-size: 1rem;
    line-height: 1.7;
    color: rgba(255,255,255,.6);
    margin-bottom: 2.25rem;
}
.lp-cta-band__actions { display: flex; gap: .75rem; justify-content: center; flex-wrap: wrap; }

/* Thin gold/sand separator between CTA band and footer */
.lp-cta-band::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, transparent, hsl(38 40% 60% / .5), transparent);
}

/* Responsive */

/* Tablet / small desktop  ≤ 1100px */
@media (max-width: 1100px) {
    .lp-hero__grid { gap: 3rem; }
    .lp-hero__img-card:first-child { min-height: 320px; }
    .lp-hero__img-card:not(:first-child) { min-height: 150px; }
}

/* Tablet portrait  ≤ 900px */
@media (max-width: 900px) {
    /* Hero: stack text above, hide visual mosaic */
    .lp-hero__grid { grid-template-columns: 1fr; gap: 0; }
    .lp-hero__visual { display: none; }
    .lp-hero { padding: 5rem 0 3.5rem; text-align: center; }
    .lp-hero__badge { margin-left: auto; margin-right: auto; }
    .lp-hero__ctas { justify-content: center; }
    .lp-body[style] { max-width: 100% !important; margin-left: auto; margin-right: auto; }

    /* Stats: 2-column grid */
    .lp-stats__grid { grid-template-columns: repeat(2, 1fr); }
    .lp-stat {
        border-right: none;
        border-bottom: 1px solid rgba(255,255,255,.1);
        padding: 1.25rem;
    }
    .lp-stat:nth-child(odd) { border-right: 1px solid rgba(255,255,255,.1); }
    .lp-stat:nth-last-child(-n+2) { border-bottom: none; }

    /* Steps: single column */
    .lp-steps { grid-template-columns: 1fr; border-radius: var(--radius-lg); }

    /* Pets grid: 2 columns */
    .lp-pets-grid { grid-template-columns: repeat(2, 1fr); }

    /* Testimonials: single column */
    .lp-tgrid { grid-template-columns: 1fr; }

    /* Sections: tighter padding */
    .lp-section, .lp-section--alt { padding: 4rem 0; }
    .lp-cta-band { padding: 4rem 0; }
}

/* Large mobile  ≤ 640px */
@media (max-width: 640px) {
    /* Container padding */
    .lp-container { padding: 0 1rem; }

    /* Hero */
    .lp-hero { padding: 4rem 0 3rem; }
    .lp-hero__ctas { flex-direction: column; align-items: center; gap: .6rem; }
    .lp-hero__ctas .btn-lp { width: 100%; max-width: 18rem; justify-content: center; }

    /* Stats: still 2-col but tighter */
    .lp-stats { padding: 2rem 0; }
    .lp-stat__num { font-size: 1.9rem; }

    /* Steps */
    .lp-step { padding: 1.75rem 1.5rem; }

    /* Featured pets: single column */
    .lp-pets-grid { grid-template-columns: 1fr; }

    /* Featured head: stack vertically */
    .lp-featured__head { flex-direction: column; align-items: flex-start; gap: .75rem; }

    /* Sections */
    .lp-section, .lp-section--alt { padding: 3.5rem 0; }
    .lp-cta-band { padding: 3.5rem 0; }

    /* CTA band buttons */
    .lp-cta-band__actions { flex-direction: column; align-items: center; gap: .6rem; }
    .lp-cta-band__actions .btn-lp { width: 100%; max-width: 18rem; justify-content: center; }

    /* Testimonials */
    .lp-tcard { padding: 1.35rem; }
}

/* Small mobile  ≤ 400px */
@media (max-width: 400px) {
    .lp-container { padding: 0 .85rem; }
    .lp-hero { padding: 3.5rem 0 2.5rem; }
    .lp-stats__grid { grid-template-columns: 1fr; }
    .lp-stat { border-right: none !important; border-bottom: 1px solid rgba(255,255,255,.1); }
    .lp-stat:last-child { border-bottom: none; }
    .lp-stat__num { font-size: 1.7rem; }
    .lp-step { padding: 1.5rem 1.1rem; }
    .lp-section, .lp-section--alt { padding: 3rem 0; }
    .lp-cta-band { padding: 3rem 0; }
}

.lp-divider {
    height: 1px;
    background: var(--border);
    margin: 0;
}

.lp-hero__lead {
    max-width: 28rem;
    margin-top: 1.25rem;
}

.nav-hamburger {
    display: none;
    background: none;
    border: none;
    cursor: pointer;
    padding: .4rem;
    font-size: 1.2rem;
    color: var(--text-primary);
    border-radius: var(--radius-sm);
    transition: background var(--transition);
    line-height: 1;
}
.nav-hamburger:hover { background: var(--sand-100); }
@media (max-width: 700px) {
    .nav-hamburger { display: flex; align-items: center; justify-content: center; }
}
</style>

<!-- HERO -->
<section class="lp-hero">
    <div class="lp-hero__bg"></div>
    <div class="lp-container">
        <div class="lp-hero__grid">
            <div>
                <h1 class="lp-h1">Give a rescued<br>pet their<br><em>forever home.</em></h1>
                <p class="lp-body lp-hero__lead">
                    Browse adoptable animals filtered by breed, age, and temperament. Every profile includes photos, personality notes, and full medical history.
                </p>
                <div class="lp-hero__ctas">
                    <a class="btn-lp btn-lp--primary" href="<?= BASE_URL ?>/pages/pets.php">
                        <i class="fa-solid fa-paw"></i> Meet Our Pets
                    </a>
                    <a class="btn-lp btn-lp--outline" href="<?= BASE_URL ?>/pages/about.php">
                        Our Story
                    </a>
                </div>
            </div>

            <div class="lp-hero__visual">
                <div class="lp-hero__img-card">
                    <img src="https://images.unsplash.com/photo-1587300003388-59208cc962cb?w=600&auto=format&fit=crop&q=80"
                            alt="Golden retriever looking up with bright eyes" loading="eager">
                </div>
                <div class="lp-hero__img-card">
                    <img src="https://images.unsplash.com/photo-1574158622682-e40e69881006?w=400&auto=format&fit=crop&q=80"
                            alt="Tabby cat sitting comfortably" loading="lazy">
                </div>
                <div class="lp-hero__img-card">
                    <img src="https://images.unsplash.com/photo-1552053831-71594a27632d?w=400&auto=format&fit=crop&q=80"
                            alt="Playful puppy outdoors" loading="lazy">
                </div>
            </div>
        </div>
    </div>
</section>

<!-- STATS BAR -->
<div class="lp-stats">
    <div class="lp-container">
        <div class="lp-stats__grid">
            <div class="lp-stat">
                <div class="lp-stat__num"><?= $adopted ?>+</div>
                <div class="lp-stat__lbl">Pets Rehomed</div>
            </div>
            <div class="lp-stat">
                <div class="lp-stat__num"><?= $avail ?></div>
                <div class="lp-stat__lbl">Available Now</div>
            </div>
            <div class="lp-stat">
                <div class="lp-stat__num">98%</div>
                <div class="lp-stat__lbl">Satisfaction Rate</div>
            </div>
            <div class="lp-stat">
                <div class="lp-stat__num">2014</div>
                <div class="lp-stat__lbl">Est. Year</div>
            </div>
        </div>
    </div>
</div>

<!-- HOW IT WORKS -->
<section class="lp-section--alt">
    <div class="lp-container">
        <div style="max-width:34rem">
            <span class="lp-label">Process</span>
            <h2 class="lp-h2">Three steps to bring<br>a pet home.</h2>
        </div>
        <div class="lp-steps">
            <div class="lp-step">
                <div class="lp-step__num">01</div>
                <div class="lp-step__icon lp-step__icon--g">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </div>
                <h3>Browse and filter</h3>
                <p>Search by type, breed, age, or size. Every pet has a full profile with photos, personality notes, and medical history.</p>
            </div>
            <div class="lp-step">
                <div class="lp-step__num">02</div>
                <div class="lp-step__icon lp-step__icon--o">
                    <i class="fa-solid fa-file-lines"></i>
                </div>
                <h3>Submit an application</h3>
                <p>Fill out a short form about your home and lifestyle. It takes under five minutes and helps us make the best match.</p>
            </div>
            <div class="lp-step">
                <div class="lp-step__num">03</div>
                <div class="lp-step__icon lp-step__icon--s">
                    <i class="fa-solid fa-house"></i>
                </div>
                <h3>Welcome them home</h3>
                <p>Once approved, we coordinate pickup and provide everything you need for a smooth, happy transition.</p>
            </div>
        </div>
    </div>
</section>

<!-- FEATURED PETS -->
<?php if (!empty($featured)): ?>
<section class="lp-section">
    <div class="lp-container">
        <div class="lp-featured__head">
            <div>
                <span class="lp-label">Recently Added</span>
                <h2 class="lp-h2">Fresh arrivals.</h2>
            </div>
            <a class="btn-lp btn-lp--outline" href="<?= BASE_URL ?>/pages/pets.php">
                View all <?= $avail ?> pets
                <i class="fa-solid fa-arrow-right"></i>
            </a>
        </div>
        <div class="lp-pets-grid">
            <?php foreach ($featured as $pet):
                $overlay_class = match(strtolower($pet['status'])) {
                    'available' => 'pet-card__status-overlay--available',
                    'adopted'   => 'pet-card__status-overlay--adopted',
                    'pending'   => 'pet-card__status-overlay--pending',
                    default     => 'pet-card__status-overlay--available',
                };
            ?>
            <article class="pet-card">
                <div class="pet-card__img">
                    <img
                        src="<?= e(pet_image_url($pet['primary_image'])) ?>"
                        alt="Photo of <?= e($pet['name']) ?>"
                        loading="lazy"
                    >
                    <span class="pet-card__status-overlay <?= $overlay_class ?>">
                        <?= e($pet['status']) ?>
                    </span>
                </div>
                <div class="pet-card__body">
                    <h3 class="pet-card__name"><?= e($pet['name']) ?></h3>
                    <p class="pet-card__meta">
                        <?= e($pet['breed']) ?> &middot; <?= e($pet['age_label']) ?> &middot; <?= e($pet['gender']) ?>
                    </p>
                    <a class="pet-card__cta" href="<?= BASE_URL ?>/pet-details.php?id=<?= (int) $pet['id'] ?>">
                        Meet <?= e($pet['name']) ?>
                        <i class="fa-solid fa-arrow-right" style="font-size:.78rem"></i>
                    </a>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<div class="lp-divider"></div>
<?php endif; ?>

<!-- TESTIMONIALS -->
<section class="lp-section--alt">
    <div class="lp-container">
        <div style="max-width:34rem">
            <span class="lp-label">Happy Tails</span>
            <h2 class="lp-h2">Stories from families<br>we have matched.</h2>
        </div>
        <div class="lp-tgrid">
            <div class="lp-tcard">
                <p class="lp-tcard__quote">
                    Daisy went from a scared shelter pup to the heart of our home in just three months. She greets us at the door every single day.
                </p>
                <div class="lp-tcard__author">
                    <div class="lp-tcard__av lp-tcard__av--g">M</div>
                    <div>
                        <div class="lp-tcard__name">The Martinez Family</div>
                        <div class="lp-tcard__pet">Adopted Daisy</div>
                    </div>
                </div>
            </div>
            <div class="lp-tcard">
                <p class="lp-tcard__quote">
                    Adopting Whiskers was the best decision of my year. He is my little shadow, my coworker, and the best alarm clock I have ever had.
                </p>
                <div class="lp-tcard__author">
                    <div class="lp-tcard__av lp-tcard__av--o">J</div>
                    <div>
                        <div class="lp-tcard__name">Jordan P.</div>
                        <div class="lp-tcard__pet">Adopted Whiskers</div>
                    </div>
                </div>
            </div>
            <div class="lp-tcard">
                <p class="lp-tcard__quote">
                    Bandit was labeled too shy at the shelter. Today he runs trails with our kids and snores on the couch like he has always been ours.
                </p>
                <div class="lp-tcard__author">
                    <div class="lp-tcard__av lp-tcard__av--s">O</div>
                    <div>
                        <div class="lp-tcard__name">The Okafor-Lee Family</div>
                        <div class="lp-tcard__pet">Adopted Bandit</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- BOTTOM CTA BAND -->
<section class="lp-cta-band">
    <div class="lp-cta-band__bg"></div>
    <div class="lp-container">
        <div class="lp-cta-band__inner">
            <h2>Every pet deserves a loving home.</h2>
            <p>Browse our available animals and start the adoption process today. It only takes a few minutes to give a rescued pet the life they deserve.</p>
            <div class="lp-cta-band__actions">
                <a class="btn-lp btn-lp--primary" href="<?= BASE_URL ?>/pages/pets.php"
                    style="background:#fff;color:var(--forest-700)">
                    <i class="fa-solid fa-paw"></i> Browse Available Pets
                </a>
                <?php if (!is_logged_in()): ?>
                <a class="btn-lp btn-lp--ghost-white" href="<?= BASE_URL ?>/register.php">
                    <i class="fa-solid fa-user-plus"></i> Create an Account
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
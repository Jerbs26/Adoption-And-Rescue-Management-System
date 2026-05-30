<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/app.php';
session_start_once();
$pageTitle = 'About Us';

$stories = [
  ['petName'=>'Daisy','adopterName'=>'The Martinez Family','story'=>'Daisy went from a scared shelter pup to the heart of our home in just three months. She greets us at the door every day with wagging joy.','imageUrl'=>'https://images.unsplash.com/photo-1530281700549-e82e7bf110d6?w=500&h=400&fit=crop&auto=format'],
  ['petName'=>'Whiskers','adopterName'=>'Jordan P.','story'=>'Adopting Whiskers was the best decision of my year. He is my little shadow, my coworker, and the best alarm clock I have ever had.','imageUrl'=>'https://images.unsplash.com/photo-1495360010541-f48722b34f7d?w=500&h=400&fit=crop&auto=format'],
  ['petName'=>'Bandit','adopterName'=>'The Okafor-Lee Family','story'=>'Bandit was labeled too shy at the shelter. Today he runs trails with our kids and snores on the couch like he has always been ours.','imageUrl'=>'https://images.unsplash.com/photo-1507146426996-ef05306b995a?w=500&h=400&fit=crop&auto=format'],
];

include __DIR__ . '/../includes/head.php';
include __DIR__ . '/../includes/header.php';
?>

<section class="hero">
  <div class="container" style="padding-top:2rem;padding-bottom:2rem;max-width:52rem">
    <h1>About Adoptly Rescue</h1>
    <p class="hero__lead">We are a small, volunteer-run rescue tucked between two rolling hills. Since 2014 we have helped over a thousand animals find loving homes &mdash; one careful match at a time.</p>
    <p class="muted" style="max-width:38rem">Our mission is simple: treat every animal like the individual they are, and trust that the right family is out there for each one. We rehabilitate, foster, train, and educate so every adoption sticks.</p>
  </div>
</section>

<section class="section-alt" style="padding:3rem 0">
  <div class="container">
    <div class="grid grid-3" style="max-width:800px;margin:0 auto">
      <div class="card" style="padding:1.75rem;text-align:center">
        <div class="stat-icon green" style="margin:0 auto 1rem"><i class="fa-solid fa-calendar-check"></i></div>
        <div class="stat-num">2014</div>
        <div class="stat-label">Founded</div>
      </div>
      <div class="card" style="padding:1.75rem;text-align:center">
        <div class="stat-icon orange" style="margin:0 auto 1rem"><i class="fa-solid fa-heart"></i></div>
        <div class="stat-num">1,200+</div>
        <div class="stat-label">Happy Adoptions</div>
      </div>
      <div class="card" style="padding:1.75rem;text-align:center">
        <div class="stat-icon blue" style="margin:0 auto 1rem"><i class="fa-solid fa-users"></i></div>
        <div class="stat-num">30+</div>
        <div class="stat-label">Volunteers</div>
      </div>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="center" style="max-width:36rem;margin:0 auto 2.5rem">
      <h2>Happy tails</h2>
      <p class="muted">Stories from families we have matched.</p>
    </div>
    <div class="grid grid-3">
      <?php foreach ($stories as $s): ?>
      <article class="card">
        <div class="pet-card__img">
          <img src="<?= e($s['imageUrl']) ?>" alt="<?= e($s['petName']) ?> with <?= e($s['adopterName']) ?>" loading="lazy">
        </div>
        <div class="pet-card__body">
          <div class="tag-row" style="color:var(--accent);font-weight:700;font-size:.9rem">
            <i class="fa-solid fa-heart"></i>
            <span><?= e($s['petName']) ?> &amp; <?= e($s['adopterName']) ?></span>
          </div>
          <p class="muted small" style="margin-top:.65rem">"<?= e($s['story']) ?>"</p>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section-alt" style="padding:3rem 0">
  <div class="container center">
    <h2>Ready to open your home?</h2>
    <p class="muted" style="max-width:30rem;margin:0 auto 1.5rem">Browse our current residents and start the adoption process today.</p>
    <a class="btn btn-primary" href="<?= BASE_URL ?>/pages/pets.php"><i class="fa-solid fa-paw"></i> Meet Our Pets</a>
  </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
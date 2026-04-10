<?php
session_start();

// ── Simple flat-file "database" for demo users ──────────────────────────────
$users_file = __DIR__ . '/users.json';
if (!file_exists($users_file)) file_put_contents($users_file, json_encode([]));

function load_users(string $file): array {
    return json_decode(file_get_contents($file), true) ?? [];
}
function save_users(string $file, array $users): void {
    file_put_contents($file, json_encode($users, JSON_PRETTY_PRINT));
}

$message = '';
$message_type = '';

// ── Handle POST actions ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Register
    if ($action === 'register') {
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (!$username || !$email || !$password) {
            $message = 'Please fill in all fields.';
            $message_type = 'error';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Please enter a valid email address.';
            $message_type = 'error';
        } else {
            $users = load_users($users_file);
            if (isset($users[$username])) {
                $message = 'Username already taken. Please choose another.';
                $message_type = 'error';
            } else {
                $users[$username] = [
                    'email'    => $email,
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                    'joined'   => date('Y-m-d'),
                ];
                save_users($users_file, $users);
                $message = "Welcome, $username! Your account has been created. Please log in.";
                $message_type = 'success';
            }
        }
    }

    // Login
    if ($action === 'login') {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        $users = load_users($users_file);
        if (!$username || !$password) {
            $message = 'Please enter your username and password.';
            $message_type = 'error';
        } elseif (!isset($users[$username]) || !password_verify($password, $users[$username]['password'])) {
            $message = 'Invalid credentials. Please try again.';
            $message_type = 'error';
        } else {
            $_SESSION['user'] = $username;
            header('Location: index.php?page=home');
            exit;
        }
    }

    // Logout
    if ($action === 'logout') {
        session_destroy();
        header('Location: index.php?page=home');
        exit;
    }
}

// ── Cart (session-based) ─────────────────────────────────────────────────────
if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

if (isset($_GET['add_to_cart'])) {
    $item = htmlspecialchars($_GET['add_to_cart']);
    $_SESSION['cart'][] = $item;
    header('Location: index.php?page=products&cart_added=1');
    exit;
}
if (isset($_GET['clear_cart'])) {
    $_SESSION['cart'] = [];
    header('Location: index.php?page=products');
    exit;
}
if (isset($_GET['remove_item'])) {
    $idx = (int)$_GET['remove_item'];
    array_splice($_SESSION['cart'], $idx, 1);
    header('Location: index.php?page=cart');
    exit;
}

// ── Current page ─────────────────────────────────────────────────────────────
$page = $_GET['page'] ?? 'home';
$allowed_pages = ['home','about','products','contact','cart','login','register'];
if (!in_array($page, $allowed_pages)) $page = 'home';

// ── Product catalogue ─────────────────────────────────────────────────────────
$products = [
    ['name'=>'Gold Necklace',    'desc'=>'Elegant 22K gold necklace for special occasions.', 'price'=>'₹85,000',  'tag'=>'Bestseller', 'emoji'=>'📿'],
    ['name'=>'Diamond Ring',     'desc'=>'Brilliant-cut diamond ring to make moments shine.', 'price'=>'₹1,20,000','tag'=>'Premium',    'emoji'=>'💍'],
    ['name'=>'Silver Bracelet',  'desc'=>'Stylish 925 silver bracelet for every occasion.',   'price'=>'₹4,500',   'tag'=>'Trending',   'emoji'=>'⚡'],
    ['name'=>'Pearl Earrings',   'desc'=>'Classic Akoya pearl earrings for timeless elegance.','price'=>'₹12,000',  'tag'=>'Classic',    'emoji'=>'✨'],
    ['name'=>'Gold Bangles',     'desc'=>'Traditional 22K bangles with intricate filigree.',  'price'=>'₹62,000',  'tag'=>'Heritage',   'emoji'=>'🌸'],
    ['name'=>'Sapphire Pendant', 'desc'=>'Royal blue sapphire set in white gold.',            'price'=>'₹95,000',  'tag'=>'Exclusive',  'emoji'=>'💎'],
];

$logged_in = isset($_SESSION['user']);
$cart_count = count($_SESSION['cart']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Niranjan Jewellery — Timeless Elegance</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
<style>
/* ─── Design Tokens ─────────────────────────────────── */
:root {
  --gold:       #c9952a;
  --gold-light: #e8c46a;
  --gold-pale:  #f7edd6;
  --dark:       #1a110a;
  --mid:        #3d2b1a;
  --cream:      #faf6ee;
  --text:       #2e1d0e;
  --muted:      #7a6348;
  --border:     #e2d3b8;
  --white:      #ffffff;
  --red:        #c0392b;
  --green:      #1e7e5a;
  --radius:     4px;
  --font-head:  'Cormorant Garamond', Georgia, serif;
  --font-body:  'Jost', system-ui, sans-serif;
  --shadow:     0 8px 40px rgba(26,17,10,.10);
  --shadow-lg:  0 24px 80px rgba(26,17,10,.18);
}

/* ─── Reset ─────────────────────────────────────────── */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{
  font-family:var(--font-body);
  background:var(--cream);
  color:var(--text);
  line-height:1.6;
  min-height:100vh;
}

/* ─── Scrollbar ──────────────────────────────────────── */
::-webkit-scrollbar{width:6px}
::-webkit-scrollbar-track{background:var(--cream)}
::-webkit-scrollbar-thumb{background:var(--gold);border-radius:3px}

/* ─── Topbar ─────────────────────────────────────────── */
.topbar{
  background:var(--dark);
  color:var(--gold-light);
  text-align:center;
  font-size:.78rem;
  letter-spacing:.12em;
  padding:.5rem 1rem;
  font-weight:300;
}

/* ─── Header ─────────────────────────────────────────── */
header{
  background:var(--white);
  border-bottom:1px solid var(--border);
  position:sticky;top:0;z-index:100;
  box-shadow:0 2px 16px rgba(26,17,10,.08);
}
.header-inner{
  max-width:1200px;margin:0 auto;
  display:flex;align-items:center;justify-content:space-between;
  padding:.9rem 2rem;
  gap:1rem;
}
.logo{
  display:flex;align-items:center;gap:.9rem;text-decoration:none;
}
.logo-icon{
  width:44px;height:44px;border-radius:50%;
  background:linear-gradient(135deg,var(--gold),var(--gold-light));
  display:flex;align-items:center;justify-content:center;
  font-size:1.3rem;flex-shrink:0;
}
.logo-text{
  font-family:var(--font-head);
  font-size:1.55rem;font-weight:600;
  color:var(--dark);letter-spacing:.02em;line-height:1;
}
.logo-sub{
  font-size:.65rem;letter-spacing:.2em;color:var(--muted);
  font-family:var(--font-body);font-weight:300;text-transform:uppercase;
}

/* ─── Nav ────────────────────────────────────────────── */
nav{display:flex;align-items:center;gap:.2rem;flex-wrap:wrap}
nav a{
  color:var(--text);text-decoration:none;
  font-size:.82rem;letter-spacing:.1em;text-transform:uppercase;font-weight:500;
  padding:.5rem .9rem;border-radius:var(--radius);
  transition:color .2s,background .2s;
}
nav a:hover,nav a.active{color:var(--gold);background:var(--gold-pale)}
.nav-cart{
  position:relative;display:inline-flex;align-items:center;gap:.4rem;
}
.cart-badge{
  position:absolute;top:-6px;right:-8px;
  background:var(--gold);color:var(--dark);
  border-radius:50%;width:18px;height:18px;
  font-size:.62rem;font-weight:700;
  display:flex;align-items:center;justify-content:center;
}
.nav-user{
  display:flex;align-items:center;gap:.5rem;
  font-size:.8rem;color:var(--muted);
}
.nav-user strong{color:var(--gold)}

/* ─── Page wrapper ───────────────────────────────────── */
main{max-width:1200px;margin:0 auto;padding:3rem 2rem 5rem}

/* ─── Hero ───────────────────────────────────────────── */
.hero{
  position:relative;overflow:hidden;
  background:linear-gradient(135deg,var(--dark) 0%,var(--mid) 100%);
  border-radius:12px;padding:6rem 4rem;
  margin-bottom:3rem;
}
.hero::before{
  content:'';position:absolute;inset:0;
  background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23c9952a' fill-opacity='0.06'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
}
.hero-content{position:relative;z-index:1;max-width:560px}
.hero-eyebrow{
  font-size:.72rem;letter-spacing:.25em;text-transform:uppercase;
  color:var(--gold-light);font-weight:400;margin-bottom:1.2rem;
  display:flex;align-items:center;gap:.7rem;
}
.hero-eyebrow::before{content:'';flex:1;max-width:40px;height:1px;background:var(--gold)}
.hero h1{
  font-family:var(--font-head);font-size:clamp(2.4rem,5vw,3.8rem);
  font-weight:300;line-height:1.15;color:var(--white);
  margin-bottom:1.4rem;
}
.hero h1 em{color:var(--gold-light);font-style:italic}
.hero p{font-size:1rem;color:rgba(255,255,255,.68);line-height:1.8;margin-bottom:2.5rem;font-weight:300}
.btn{
  display:inline-flex;align-items:center;gap:.6rem;
  padding:.85rem 2.2rem;border-radius:var(--radius);
  font-family:var(--font-body);font-size:.82rem;
  letter-spacing:.12em;text-transform:uppercase;font-weight:500;
  text-decoration:none;cursor:pointer;border:none;
  transition:transform .2s,box-shadow .2s,background .2s;
}
.btn:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(0,0,0,.2)}
.btn-gold{background:linear-gradient(135deg,var(--gold),var(--gold-light));color:var(--dark)}
.btn-outline{background:transparent;border:1px solid rgba(255,255,255,.3);color:var(--white)}
.btn-outline:hover{border-color:var(--gold-light);color:var(--gold-light)}
.btn-dark{background:var(--dark);color:var(--white)}
.btn-ghost{background:var(--gold-pale);color:var(--dark)}
.hero-badges{
  position:absolute;right:4rem;top:50%;transform:translateY(-50%);
  display:flex;flex-direction:column;gap:1rem;
}
.hero-badge{
  background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.12);
  backdrop-filter:blur(10px);border-radius:8px;
  padding:1.1rem 1.4rem;text-align:center;color:var(--white);
}
.hero-badge-num{font-family:var(--font-head);font-size:2rem;font-weight:600;color:var(--gold-light);line-height:1}
.hero-badge-label{font-size:.68rem;letter-spacing:.15em;text-transform:uppercase;color:rgba(255,255,255,.5);margin-top:.3rem}

/* ─── Section heading ────────────────────────────────── */
.section-head{text-align:center;margin-bottom:3rem}
.section-head .eyebrow{
  font-size:.7rem;letter-spacing:.25em;text-transform:uppercase;
  color:var(--gold);margin-bottom:.8rem;display:block;
}
.section-head h2{
  font-family:var(--font-head);font-size:clamp(1.8rem,3vw,2.8rem);
  font-weight:400;color:var(--dark);line-height:1.2;
}
.section-head p{color:var(--muted);margin-top:.8rem;font-size:.95rem;font-weight:300}
.divider{width:48px;height:2px;background:var(--gold);margin:1rem auto 0}

/* ─── Products grid ─────────────────────────────────── */
.product-grid{
  display:grid;
  grid-template-columns:repeat(auto-fill,minmax(280px,1fr));
  gap:1.8rem;
}
.product-card{
  background:var(--white);border:1px solid var(--border);
  border-radius:10px;overflow:hidden;
  transition:transform .3s,box-shadow .3s;
  display:flex;flex-direction:column;
}
.product-card:hover{transform:translateY(-6px);box-shadow:var(--shadow-lg)}
.product-thumb{
  background:linear-gradient(135deg,var(--gold-pale),#f0e6ce);
  height:220px;display:flex;align-items:center;justify-content:center;
  font-size:5rem;position:relative;
}
.product-tag{
  position:absolute;top:.8rem;left:.8rem;
  background:var(--dark);color:var(--gold-light);
  font-size:.62rem;letter-spacing:.12em;text-transform:uppercase;
  padding:.3rem .7rem;border-radius:2px;
}
.product-body{padding:1.4rem;flex:1;display:flex;flex-direction:column}
.product-body h3{
  font-family:var(--font-head);font-size:1.25rem;
  font-weight:600;color:var(--dark);margin-bottom:.4rem;
}
.product-body p{font-size:.85rem;color:var(--muted);font-weight:300;flex:1;line-height:1.6}
.product-footer{
  display:flex;align-items:center;justify-content:space-between;
  margin-top:1.2rem;padding-top:1rem;border-top:1px solid var(--border);
}
.product-price{
  font-family:var(--font-head);font-size:1.35rem;
  font-weight:600;color:var(--gold);
}
.btn-cart{
  display:inline-flex;align-items:center;gap:.4rem;
  background:var(--dark);color:var(--white);
  padding:.55rem 1.1rem;border-radius:var(--radius);
  font-size:.76rem;letter-spacing:.1em;text-transform:uppercase;font-weight:500;
  text-decoration:none;transition:background .2s,transform .2s;
}
.btn-cart:hover{background:var(--gold);color:var(--dark);transform:scale(1.04)}

/* ─── Features strip ─────────────────────────────────── */
.features{
  display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));
  gap:1px;background:var(--border);
  border:1px solid var(--border);border-radius:10px;overflow:hidden;
  margin-bottom:4rem;
}
.feature{background:var(--white);padding:2rem 1.5rem;text-align:center}
.feature-icon{font-size:2rem;margin-bottom:.8rem}
.feature h4{font-family:var(--font-head);font-size:1.1rem;font-weight:600;color:var(--dark);margin-bottom:.4rem}
.feature p{font-size:.82rem;color:var(--muted);font-weight:300}

/* ─── About ──────────────────────────────────────────── */
.about-grid{display:grid;grid-template-columns:1fr 1fr;gap:4rem;align-items:center}
.about-visual{
  background:linear-gradient(135deg,var(--gold-pale),#ecd9b0);
  border-radius:10px;height:440px;
  display:flex;align-items:center;justify-content:center;font-size:8rem;
  position:relative;overflow:hidden;
}
.about-visual::after{
  content:'';position:absolute;inset:0;
  background:radial-gradient(circle at 70% 30%,rgba(201,149,42,.25),transparent 60%);
}
.about-text .eyebrow{
  font-size:.7rem;letter-spacing:.25em;text-transform:uppercase;
  color:var(--gold);margin-bottom:1rem;display:block;
}
.about-text h2{
  font-family:var(--font-head);font-size:2.4rem;font-weight:400;
  line-height:1.2;color:var(--dark);margin-bottom:1.4rem;
}
.about-text p{color:var(--muted);font-weight:300;line-height:1.9;margin-bottom:1rem}
.stats{display:flex;gap:2.5rem;margin-top:2rem}
.stat-num{font-family:var(--font-head);font-size:2.2rem;font-weight:600;color:var(--gold);line-height:1}
.stat-label{font-size:.72rem;letter-spacing:.12em;text-transform:uppercase;color:var(--muted)}

/* ─── Contact ────────────────────────────────────────── */
.contact-grid{display:grid;grid-template-columns:1fr 1fr;gap:3rem}
.contact-info{padding-right:2rem;border-right:1px solid var(--border)}
.contact-item{display:flex;gap:1rem;margin-bottom:1.8rem;align-items:flex-start}
.contact-icon{
  width:40px;height:40px;border-radius:50%;
  background:var(--gold-pale);color:var(--gold);
  display:flex;align-items:center;justify-content:center;
  font-size:1.1rem;flex-shrink:0;
}
.contact-item h4{font-size:.8rem;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);margin-bottom:.3rem}
.contact-item p,.contact-item a{color:var(--dark);text-decoration:none;font-weight:400}
.contact-item a:hover{color:var(--gold)}

/* ─── Forms (login / register / contact) ────────────── */
.form-card{
  background:var(--white);border:1px solid var(--border);
  border-radius:10px;padding:2.5rem;
}
.form-card h2{
  font-family:var(--font-head);font-size:2rem;font-weight:400;
  color:var(--dark);margin-bottom:.4rem;
}
.form-card .sub{font-size:.85rem;color:var(--muted);font-weight:300;margin-bottom:2rem}
.form-row{margin-bottom:1.2rem}
.form-row label{
  display:block;font-size:.75rem;letter-spacing:.1em;
  text-transform:uppercase;color:var(--muted);margin-bottom:.5rem;
}
.form-row input{
  width:100%;padding:.8rem 1rem;
  border:1px solid var(--border);border-radius:var(--radius);
  font-family:var(--font-body);font-size:.9rem;color:var(--text);
  background:var(--white);transition:border-color .2s,box-shadow .2s;
}
.form-row input:focus{
  outline:none;border-color:var(--gold);
  box-shadow:0 0 0 3px rgba(201,149,42,.12);
}
.form-card .btn{width:100%;justify-content:center;margin-top:.5rem}
.form-alt{text-align:center;margin-top:1.2rem;font-size:.85rem;color:var(--muted)}
.form-alt a{color:var(--gold);text-decoration:none;font-weight:500}

/* ─── Messages ───────────────────────────────────────── */
.alert{
  padding:1rem 1.4rem;border-radius:var(--radius);
  margin-bottom:1.5rem;font-size:.9rem;font-weight:400;
  display:flex;align-items:flex-start;gap:.8rem;
}
.alert-error{background:#fef2f2;border:1px solid #fecaca;color:var(--red)}
.alert-success{background:#f0fdf4;border:1px solid #bbf7d0;color:var(--green)}
.toast{
  position:fixed;top:1.5rem;right:1.5rem;z-index:999;
  background:var(--dark);color:var(--gold-light);
  padding:.9rem 1.6rem;border-radius:6px;font-size:.85rem;
  box-shadow:var(--shadow-lg);letter-spacing:.03em;
  animation:slideIn .3s ease;
}
@keyframes slideIn{from{opacity:0;transform:translateX(20px)}to{opacity:1;transform:translateX(0)}}

/* ─── Cart ───────────────────────────────────────────── */
.cart-table{width:100%;border-collapse:collapse;margin-bottom:1.5rem}
.cart-table th{
  text-align:left;font-size:.72rem;letter-spacing:.15em;
  text-transform:uppercase;color:var(--muted);font-weight:500;
  padding:.8rem 1rem;border-bottom:2px solid var(--border);
}
.cart-table td{padding:1rem;border-bottom:1px solid var(--border);vertical-align:middle}
.cart-table .remove-btn{
  background:none;border:none;cursor:pointer;color:#ccc;font-size:1.1rem;
  transition:color .2s;
}
.cart-table .remove-btn:hover{color:var(--red)}
.cart-empty{
  text-align:center;padding:4rem 2rem;
  font-family:var(--font-head);color:var(--muted);
}
.cart-empty .empty-icon{font-size:4rem;margin-bottom:1rem}
.cart-empty h3{font-size:1.6rem;font-weight:400;margin-bottom:.8rem}

/* ─── Footer ─────────────────────────────────────────── */
footer{
  background:var(--dark);color:rgba(255,255,255,.5);
  text-align:center;padding:2rem;
  font-size:.8rem;letter-spacing:.05em;
}
footer strong{color:var(--gold-light)}

/* ─── Responsive ─────────────────────────────────────── */
@media(max-width:900px){
  .hero{padding:4rem 2rem}
  .hero-badges{display:none}
  .about-grid,.contact-grid{grid-template-columns:1fr}
  .contact-info{border-right:none;padding-right:0;border-bottom:1px solid var(--border);padding-bottom:2rem}
}
@media(max-width:600px){
  .header-inner{flex-wrap:wrap}
  nav{gap:.1rem}
  nav a{padding:.4rem .5rem;font-size:.72rem}
  main{padding:2rem 1rem 4rem}
}
</style>
</head>
<body>

<div class="topbar">✦ Free shipping on orders above ₹50,000 &nbsp;·&nbsp; Hallmark Certified Gold &nbsp;·&nbsp; 30-Day Easy Returns ✦</div>

<header>
  <div class="header-inner">
    <a href="index.php?page=home" class="logo">
      <div class="logo-icon">💎</div>
      <div>
        <div class="logo-text">Niranjan</div>
        <div class="logo-sub">Jewellery &amp; Co.</div>
      </div>
    </a>
    <nav>
      <a href="index.php?page=home"     class="<?= $page==='home'     ?'active':'' ?>">Home</a>
      <a href="index.php?page=about"    class="<?= $page==='about'    ?'active':'' ?>">About</a>
      <a href="index.php?page=products" class="<?= $page==='products' ?'active':'' ?>">Products</a>
      <a href="index.php?page=contact"  class="<?= $page==='contact'  ?'active':'' ?>">Contact</a>
      <a href="index.php?page=cart" class="nav-cart <?= $page==='cart'?'active':'' ?>">
        🛒 Cart <?php if($cart_count>0):?><span class="cart-badge"><?= $cart_count ?></span><?php endif;?>
      </a>
      <?php if($logged_in): ?>
        <span class="nav-user">Hi, <strong><?= htmlspecialchars($_SESSION['user']) ?></strong></span>
        <form method="post" style="display:inline">
          <input type="hidden" name="action" value="logout">
          <button type="submit" class="btn btn-ghost" style="font-size:.72rem;padding:.45rem .9rem">Logout</button>
        </form>
      <?php else: ?>
        <a href="index.php?page=login"    class="<?= $page==='login'   ?'active':'' ?>">Login</a>
        <a href="index.php?page=register" class="btn btn-gold" style="font-size:.75rem;padding:.5rem 1.2rem;margin-left:.4rem">Register</a>
      <?php endif; ?>
    </nav>
  </div>
</header>

<?php if(isset($_GET['cart_added'])): ?>
<div class="toast">✓ Item added to cart!</div>
<script>setTimeout(()=>document.querySelector('.toast').remove(),2800)</script>
<?php endif; ?>

<main>

<?php if($page==='home'): ?>
<!-- ── HOME ──────────────────────────────────────────── -->
<div class="hero">
  <div class="hero-content">
    <div class="hero-eyebrow">Est. 1987 · Bengaluru, India</div>
    <h1>Crafted for<br><em>Timeless</em><br>Moments</h1>
    <p>Where tradition meets contemporary elegance. Each piece tells a story of mastery, heritage and the pursuit of beauty.</p>
    <div style="display:flex;gap:1rem;flex-wrap:wrap">
      <a href="index.php?page=products" class="btn btn-gold">Explore Collection →</a>
      <a href="index.php?page=about"    class="btn btn-outline">Our Story</a>
    </div>
  </div>
  <div class="hero-badges">
    <div class="hero-badge">
      <div class="hero-badge-num">35+</div>
      <div class="hero-badge-label">Years of craft</div>
    </div>
    <div class="hero-badge">
      <div class="hero-badge-num">12K</div>
      <div class="hero-badge-label">Happy clients</div>
    </div>
    <div class="hero-badge">
      <div class="hero-badge-num">500+</div>
      <div class="hero-badge-label">Designs</div>
    </div>
  </div>
</div>

<div class="features">
  <div class="feature"><div class="feature-icon">🏅</div><h4>Hallmark Certified</h4><p>Every gold piece BIS certified for guaranteed purity.</p></div>
  <div class="feature"><div class="feature-icon">✋</div><h4>Handcrafted</h4><p>Skilled artisans, generational techniques.</p></div>
  <div class="feature"><div class="feature-icon">🔄</div><h4>Easy Exchange</h4><p>Lifetime exchange policy on all jewellery.</p></div>
  <div class="feature"><div class="feature-icon">🚚</div><h4>Insured Delivery</h4><p>Fully insured, discrete packaging.</p></div>
</div>

<div class="section-head">
  <span class="eyebrow">Our Catalogue</span>
  <h2>Featured Pieces</h2>
  <p>A curated selection from our latest collection</p>
  <div class="divider"></div>
</div>
<div class="product-grid">
  <?php foreach(array_slice($products,0,3) as $p): ?>
  <div class="product-card">
    <div class="product-thumb">
      <?= $p['emoji'] ?>
      <span class="product-tag"><?= $p['tag'] ?></span>
    </div>
    <div class="product-body">
      <h3><?= $p['name'] ?></h3>
      <p><?= $p['desc'] ?></p>
      <div class="product-footer">
        <span class="product-price"><?= $p['price'] ?></span>
        <a href="index.php?add_to_cart=<?= urlencode($p['name']) ?>&page=products" class="btn-cart">🛒 Add</a>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<div style="text-align:center;margin-top:2.5rem">
  <a href="index.php?page=products" class="btn btn-dark">View Full Collection →</a>
</div>

<?php elseif($page==='about'): ?>
<!-- ── ABOUT ──────────────────────────────────────────── -->
<div class="about-grid">
  <div class="about-visual">💍</div>
  <div class="about-text">
    <span class="eyebrow">Our Story</span>
    <h2>Three Decades of Brilliance</h2>
    <p>Founded in 1987 by master goldsmith Niranjan Rao, our house was built on a singular belief: that jewellery should be a lasting heirloom, not merely an accessory.</p>
    <p>From a single workshop in Bengaluru, we have grown into a beloved jewellery house serving thousands of families across Karnataka — each piece still crafted with the same devotion and precision that defined our very first creation.</p>
    <p>We source ethically, craft honestly, and design fearlessly — blending temple traditions with contemporary sensibility.</p>
    <div class="stats">
      <div><div class="stat-num">35+</div><div class="stat-label">Years</div></div>
      <div><div class="stat-num">12K+</div><div class="stat-label">Clients</div></div>
      <div><div class="stat-num">500+</div><div class="stat-label">Designs</div></div>
    </div>
  </div>
</div>

<?php elseif($page==='products'): ?>
<!-- ── PRODUCTS ───────────────────────────────────────── -->
<div class="section-head">
  <span class="eyebrow">Our Collection</span>
  <h2>Exquisite Jewellery</h2>
  <p>Gold, diamond, silver and gemstone pieces for every occasion</p>
  <div class="divider"></div>
</div>
<div class="product-grid">
  <?php foreach($products as $p): ?>
  <div class="product-card">
    <div class="product-thumb">
      <?= $p['emoji'] ?>
      <span class="product-tag"><?= $p['tag'] ?></span>
    </div>
    <div class="product-body">
      <h3><?= $p['name'] ?></h3>
      <p><?= $p['desc'] ?></p>
      <div class="product-footer">
        <span class="product-price"><?= $p['price'] ?></span>
        <a href="index.php?add_to_cart=<?= urlencode($p['name']) ?>&page=products" class="btn-cart">🛒 Add</a>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<?php elseif($page==='cart'): ?>
<!-- ── CART ───────────────────────────────────────────── -->
<div class="section-head">
  <span class="eyebrow">Your Selection</span>
  <h2>Shopping Cart</h2>
  <div class="divider"></div>
</div>
<?php if(empty($_SESSION['cart'])): ?>
  <div class="cart-empty">
    <div class="empty-icon">🛒</div>
    <h3>Your cart is empty</h3>
    <p style="color:var(--muted);margin-bottom:2rem">Discover our exquisite collection and add your favourites.</p>
    <a href="index.php?page=products" class="btn btn-gold">Browse Collection →</a>
  </div>
<?php else:
  $counts = array_count_values($_SESSION['cart']);
  $unique = array_unique($_SESSION['cart']);
?>
  <div style="max-width:700px;margin:0 auto">
  <table class="cart-table">
    <thead>
      <tr>
        <th>#</th><th>Product</th><th>Qty</th><th></th>
      </tr>
    </thead>
    <tbody>
    <?php $i=0; foreach($counts as $name=>$qty): $i++; ?>
      <tr>
        <td style="color:var(--muted);font-size:.85rem"><?= $i ?></td>
        <td>
          <strong style="font-family:var(--font-head);font-size:1.05rem"><?= htmlspecialchars($name) ?></strong>
        </td>
        <td><span style="background:var(--gold-pale);padding:.3rem .8rem;border-radius:20px;font-size:.85rem"><?= $qty ?>×</span></td>
        <td>
          <?php
            // find first index of this item
            $idx = array_search($name, $_SESSION['cart']);
          ?>
          <a href="index.php?remove_item=<?= $idx ?>" class="remove-btn" title="Remove">✕</a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem">
    <a href="index.php?clear_cart=1" class="btn btn-ghost" style="font-size:.78rem">🗑 Clear Cart</a>
    <a href="index.php?page=contact" class="btn btn-gold">Enquire to Purchase →</a>
  </div>
  </div>
<?php endif; ?>

<?php elseif($page==='contact'): ?>
<!-- ── CONTACT ────────────────────────────────────────── -->
<div class="section-head">
  <span class="eyebrow">Get in Touch</span>
  <h2>Visit or Contact Us</h2>
  <p>We'd love to help you find your perfect piece</p>
  <div class="divider"></div>
</div>
<div class="contact-grid">
  <div class="contact-info">
    <div class="contact-item">
      <div class="contact-icon">📧</div>
      <div>
        <h4>Email</h4>
        <a href="mailto:info@niranjanjewellery.com">info@niranjanjewellery.com</a>
      </div>
    </div>
    <div class="contact-item">
      <div class="contact-icon">📞</div>
      <div>
        <h4>Phone</h4>
        <p>+91-9876543210</p>
      </div>
    </div>
    <div class="contact-item">
      <div class="contact-icon">📍</div>
      <div>
        <h4>Store Address</h4>
        <p>12, Jewellery Lane, Commercial Street<br>Bengaluru, Karnataka 560 001</p>
      </div>
    </div>
    <div class="contact-item">
      <div class="contact-icon">🕐</div>
      <div>
        <h4>Store Hours</h4>
        <p>Mon – Sat: 10 AM – 8 PM<br>Sunday: 11 AM – 6 PM</p>
      </div>
    </div>
  </div>
  <div>
    <div class="form-card">
      <h2>Send a Message</h2>
      <p class="sub">We'll get back to you within 24 hours.</p>
      <div class="form-row"><label>Your Name</label><input type="text" placeholder="Ravi Kumar"></div>
      <div class="form-row"><label>Email</label><input type="email" placeholder="you@email.com"></div>
      <div class="form-row"><label>Message</label>
        <textarea placeholder="Tell us what you're looking for…" rows="4"
          style="width:100%;padding:.8rem 1rem;border:1px solid var(--border);border-radius:var(--radius);font-family:var(--font-body);font-size:.9rem;resize:vertical"></textarea>
      </div>
      <button class="btn btn-gold" style="width:100%;justify-content:center">Send Message →</button>
    </div>
  </div>
</div>

<?php elseif($page==='login'): ?>
<!-- ── LOGIN ──────────────────────────────────────────── -->
<div style="max-width:440px;margin:2rem auto">
  <?php if($message): ?>
  <div class="alert alert-<?= $message_type ?>"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>
  <div class="form-card">
    <h2>Welcome Back</h2>
    <p class="sub">Sign in to your Niranjan account</p>
    <form method="post">
      <input type="hidden" name="action" value="login">
      <div class="form-row"><label>Username</label><input type="text" name="username" placeholder="your_username" required></div>
      <div class="form-row"><label>Password</label><input type="password" name="password" placeholder="••••••••" required></div>
      <button type="submit" class="btn btn-gold">Sign In →</button>
    </form>
    <p class="form-alt">Don't have an account? <a href="index.php?page=register">Create one here</a></p>
  </div>
</div>

<?php elseif($page==='register'): ?>
<!-- ── REGISTER ───────────────────────────────────────── -->
<div style="max-width:440px;margin:2rem auto">
  <?php if($message): ?>
  <div class="alert alert-<?= $message_type ?>"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>
  <div class="form-card">
    <h2>Create Account</h2>
    <p class="sub">Join the Niranjan Jewellery family</p>
    <form method="post">
      <input type="hidden" name="action" value="register">
      <div class="form-row"><label>Username</label><input type="text" name="username" placeholder="your_username" required></div>
      <div class="form-row"><label>Email</label><input type="email" name="email" placeholder="you@email.com" required></div>
      <div class="form-row"><label>Password</label><input type="password" name="password" placeholder="min. 8 characters" required></div>
      <button type="submit" class="btn btn-gold">Create Account →</button>
    </form>
    <p class="form-alt">Already have an account? <a href="index.php?page=login">Sign in</a></p>
  </div>
</div>
<?php endif; ?>

</main>

<footer>
  <p>© 2025 <strong>Niranjan Jewellery &amp; Co.</strong> · Bengaluru, India · All rights reserved.</p>
</footer>

</body>
</html>
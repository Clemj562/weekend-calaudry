<?php
// --- CONFIGURATION DE LA BASE DE DONNÉES ---
$host = 'localhost';
$db   = 'gestion_perso';
$user = 'root';
$pass = ''; // Mets 'root' si tu es sur Mac/MAMP
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     die("Erreur de connexion à la base de données : " . $e->getMessage());
}

// --- TRAITEMENT DES REQUÊTES (AJAX & FORMULAIRES) ---
if (isset($_GET['action'])) {
    
    // [AJAX] Sauvegarde progression Watchlist
    if ($_GET['action'] === 'update_anime') {
        header('Content-Type: application/json');
        $slug = $_POST['slug'] ?? '';
        $ep = intval($_POST['ep'] ?? 0);
        $stmt = $pdo->prepare("UPDATE watchlist SET nb_episodes_vus = ? WHERE slug = ?");
        $stmt->execute([$ep, $slug]);
        echo json_encode(['success' => true]);
        exit;
    }
    
    // [AJAX] Cocher/Décocher Caddie
    if ($_GET['action'] === 'update_caddie') {
        header('Content-Type: application/json');
        $id = intval($_POST['id'] ?? 0);
        $checked = intval($_POST['checked'] ?? 0);
        $stmt = $pdo->prepare("UPDATE courses SET est_achete = ? WHERE id = ?");
        $stmt->execute([$checked, $id]);
        echo json_encode(['success' => true]);
        exit;
    }

    // [POST] Ajouter une nouvelle liste/catégorie de courses
    if ($_GET['action'] === 'add_caddie_category') {
        $cat = trim($_POST['new_category'] ?? '');
        $item = trim($_POST['item_name'] ?? '');
        if (!empty($cat)) {
            $stmt = $pdo->prepare("INSERT INTO courses (categorie, item_name, est_achete) VALUES (?, ?, 0)");
            $stmt->execute([$cat, !empty($item) ? $item : 'Exemple d\'article']);
        }
        header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
        exit;
    }

    // [POST] Ajouter un article dans une liste existante
    if ($_GET['action'] === 'add_caddie_item') {
        $cat = trim($_POST['categorie'] ?? '');
        $item = trim($_POST['item_name'] ?? '');
        if (!empty($cat) && !empty($item)) {
            $stmt = $pdo->prepare("INSERT INTO courses (categorie, item_name, est_achete) VALUES (?, ?, 0)");
            $stmt->execute([$cat, $item]);
        }
        header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
        exit;
    }

    // [GET] Supprimer un article spécifique du caddie
    if ($_GET['action'] === 'delete_caddie_item') {
        $id = intval($_GET['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM courses WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
        exit;
    }

    // [GET] Supprimer tout un rayon / une liste complète
    if ($_GET['action'] === 'delete_caddie_category') {
        $cat = urldecode($_GET['categorie'] ?? '');
        $stmt = $pdo->prepare("DELETE FROM courses WHERE categorie = ?");
        $stmt->execute([$cat]);
        header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
        exit;
    }

    // [POST] Ajouter un anime à la Watchlist
    if ($_GET['action'] === 'add_anime') {
        $titre = trim($_POST['titre'] ?? '');
        $max = intval($_POST['max_episodes'] ?? 12);
        if (!empty($titre)) {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $titre)));
            $stmt = $pdo->prepare("INSERT INTO watchlist (slug, titre, nb_episodes_vus, max_episodes) VALUES (?, ?, 0, ?) ON DUPLICATE KEY UPDATE max_episodes = ?");
            $stmt->execute([$slug, $titre, $max, $max]);
        }
        header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
        exit;
    }

    // [GET] Supprimer un anime de la Watchlist
    if ($_GET['action'] === 'delete_anime') {
        $slug = $_GET['slug'] ?? '';
        $stmt = $pdo->prepare("DELETE FROM watchlist WHERE slug = ?");
        $stmt->execute([$slug]);
        header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
        exit;
    }
}

// --- RÉCUPÉRATION DES DONNÉES EN BD ---
$watchlist_data = $pdo->query("SELECT * FROM watchlist")->fetchAll();

$caddie_raw = $pdo->query("SELECT * FROM courses ORDER BY id ASC")->fetchAll();
$caddie_grouped = [];
foreach ($caddie_raw as $row) {
    $caddie_grouped[$row['categorie']][] = $row;
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notre Paradis - V3.5 Dynamic</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;600;700&family=DM+Sans:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --bg-color: #Fdfbf7;
            --text-main: #141414;
            --border-color: #141414;
            --color-pink: #FF6B8B;
            --color-yellow: #FFD93D;
            --color-blue: #4D96FF;
            --color-green: #6BCB77;
            --color-purple: #B28DFF;
            --white: #FFFFFF;

            --border-width: 3px;
            --shadow-brutal: 6px 6px 0px var(--text-main);
            --shadow-brutal-hover: 10px 10px 0px var(--text-main);
            --radius: 16px;
            --radius-pill: 50px;
            --transition: all 0.25s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'DM Sans', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            line-height: 1.6;
            overflow-x: hidden;
            background-image:
                linear-gradient(var(--border-color) 1px, transparent 1px),
                linear-gradient(90deg, var(--border-color) 1px, transparent 1px);
            background-size: 40px 40px;
        }

        body::before {
            content: ''; position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: var(--bg-color); opacity: 0.92; z-index: -2;
        }

        .bg-blob { position: fixed; border-radius: 50%; filter: blur(80px); z-index: -1; opacity: 0.5; animation: float 10s infinite alternate ease-in-out; }
        .blob-1 { top: -10%; left: -10%; width: 40vw; height: 40vw; background: var(--color-pink); }
        .blob-2 { bottom: -10%; right: -10%; width: 50vw; height: 50vw; background: var(--color-blue); animation-delay: -5s; }
        .blob-3 { top: 40%; left: 40%; width: 30vw; height: 30vw; background: var(--color-yellow); animation-duration: 15s; }

        @keyframes float { 0% { transform: translate(0, 0) scale(1); } 100% { transform: translate(30px, -50px) scale(1.1); } }

        h1, h2, h3, h4, .font-display { font-family: 'Space Grotesk', sans-serif; font-weight: 700; }
        a { text-decoration: none; color: inherit; }

        /* REUSABLE BRUTALIST INPUTS & BUTTONS */
        .brutal-input {
            padding: 10px 15px;
            border: var(--border-width) solid var(--border-color);
            border-radius: var(--radius);
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 600;
            outline: none;
            background: var(--white);
            box-shadow: 3px 3px 0px var(--border-color);
            transition: 0.1s;
        }
        .brutal-input:focus { transform: translate(-2px, -2px); box-shadow: 5px 5px 0px var(--border-color); }
        
        .brutal-btn-small {
            padding: 8px 12px;
            background: var(--color-yellow);
            border: 2px solid var(--border-color);
            border-radius: var(--radius);
            font-family: 'Space Grotesk';
            font-weight: 700;
            cursor: pointer;
            box-shadow: 2px 2px 0px var(--border-color);
        }
        .brutal-btn-small:active { transform: translate(1px, 1px); box-shadow: 0px 0px 0px; }

        .delete-btn {
            background: var(--color-pink);
            color: var(--white);
            border: 2px solid var(--border-color);
            cursor: pointer;
            border-radius: 8px;
            width: 28px;
            height: 28px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            box-shadow: 2px 2px 0px var(--border-color);
            transition: 0.1s;
        }
        .delete-btn:hover { background: #ff4770; transform: translate(-1px, -1px); box-shadow: 3px 3px 0px var(--border-color); }
        .delete-btn:active { transform: translate(1px, 1px); box-shadow: 0px 0px 0px; }

        /* NAVIGATION FLOTTANTE */
        nav {
            position: fixed; top: 20px; left: 50%; transform: translateX(-50%);
            background: var(--white); border: var(--border-width) solid var(--border-color);
            border-radius: var(--radius-pill); display: flex; align-items: center;
            padding: 10px 25px; z-index: 1000; box-shadow: var(--shadow-brutal); gap: 20px; width: max-content; max-width: 95%;
        }

        .nav-links { display: flex; gap: 8px; list-style: none; }
        .nav-links li a {
            font-family: 'Space Grotesk', sans-serif; font-weight: 700; font-size: 15px;
            padding: 10px 20px; border-radius: var(--radius-pill); border: 2px solid transparent;
            transition: var(--transition); cursor: pointer; display: block; white-space: nowrap;
        }
        .nav-links li a:hover { background: var(--color-pink); color: var(--white); border-color: var(--border-color); }
        .nav-links li a.active {
            background: var(--color-blue); color: var(--white); border-color: var(--border-color);
            box-shadow: 3px 3px 0px var(--border-color); transform: translateY(-2px);
        }

        .hamburger-menu {
            display: none; flex-direction: column; gap: 6px; cursor: pointer;
            border: var(--border-width) solid var(--border-color); padding: 10px;
            border-radius: 12px; background: var(--color-yellow); box-shadow: 2px 2px 0px var(--border-color);
        }
        .hamburger-menu span { display: block; width: 22px; height: 3px; background: var(--text-main); border-radius: 2px; }

        /* CONTENEUR PRINCIPAL */
        .container { max-width: 1200px; margin: 120px auto 60px; padding: 0 20px; }
        .page-section { display: none; animation: slideUp 0.4s ease-out forwards; opacity: 0; transform: translateY(20px); }
        .page-section.active { display: block; }

        @keyframes slideUp { to { opacity: 1; transform: translateY(0); } }

        .brutal-card {
            background: var(--white); border: var(--border-width) solid var(--border-color);
            border-radius: var(--radius); padding: 30px; box-shadow: var(--shadow-brutal); position: relative;
        }

        /* ACCUEIL HERO */
        .hero { display: flex; flex-direction: column; align-items: center; text-align: center; margin-top: 150px; position: relative; }
        .hero h1 { font-size: clamp(1.8rem, 5.5vw, 3.5rem); padding: 12px 30px; margin: 6px 0; color: var(--white); line-height: 1.2; }
        .home-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 25px; margin-top: 50px; }
        .nav-card { cursor: pointer; text-align: center; padding: 30px 20px; }
        .nav-card:hover { transform: translate(-4px, -4px); box-shadow: var(--shadow-brutal-hover); }
        .nav-card.food { background: #ffeaa7; }
        .nav-card.anime { background: #81ecec; }
        .nav-card.caddie { background: #ffb8b8; }
        .nav-card.watchlist { background: #dff9fb; }

        .nav-card i {
            font-size: 40px; margin-bottom: 15px; background: var(--white); width: 80px; height: 80px;
            line-height: 74px; border-radius: 50%; border: var(--border-width) solid var(--border-color);
            box-shadow: 4px 4px 0px var(--border-color); display: inline-block;
        }

        .section-title {
            font-size: clamp(1.5rem, 4.5vw, 2.8rem); text-transform: uppercase; margin-bottom: 40px;
            display: inline-block; background: var(--color-yellow); padding: 10px 30px;
            border: var(--border-width) solid var(--border-color); box-shadow: var(--shadow-brutal); transform: rotate(-2deg);
        }
        .section-title.anime-title { background: var(--color-blue); color: var(--white); transform: rotate(2deg); }
        .section-title.caddie-title { background: var(--color-pink); color: var(--white); transform: rotate(1deg); }
        .section-title.watchlist-title { background: var(--color-purple); color: var(--white); transform: rotate(-1deg); }
        .section-title.accueil-title { background: var(--color-purple); color: var(--white); transform: rotate(2deg); }
        .section-title.accueil-title2 { background: var(--color-yellow); color: var(--text-main); transform: rotate(-2deg); }

        /* MIAM MIAM */
        .timeline-grid { display: flex; flex-direction: column; gap: 50px; }
        .day-badge { font-family: 'Space Grotesk'; font-size: 22px; font-weight: 700; background: var(--color-pink); color: var(--white); padding: 10px 25px; border: var(--border-width) solid var(--border-color); border-radius: var(--radius-pill); display: inline-block; margin-bottom: 20px; box-shadow: 4px 4px 0px var(--border-color); }
        .meals { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px; }
        .meal-card { background: var(--white); border: var(--border-width) solid var(--border-color); border-radius: var(--radius); box-shadow: var(--shadow-brutal); overflow: hidden; }
        .meal-header { background: var(--border-color); color: var(--white); padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; }
        .time-tag { background: var(--color-yellow); color: var(--border-color); font-weight: 700; padding: 4px 12px; border-radius: var(--radius-pill); font-size: 13px; border: 2px solid var(--border-color); }
        .time-tag.midi { background: var(--color-green); color: var(--white); }
        .meal-body { padding: 20px; }
        .info-pill { display: inline-flex; background: #f1f2f6; padding: 6px 12px; border-radius: var(--radius-pill); font-weight: 700; font-size: 13px; border: 2px solid var(--border-color); margin-right: 8px; margin-bottom: 12px; }
        .meal-label { font-family: 'Space Grotesk'; font-size: 16px; font-weight: 700; color: var(--color-pink); margin-top: 15px; margin-bottom: 5px; text-transform: uppercase; }
        .meal-content { font-size: 14px; font-weight: 600; background: #fdfdfd; padding: 12px; border: 2px dashed var(--border-color); border-radius: 8px; white-space: pre-line; margin-bottom: 15px; }
        .post-it { background: var(--color-yellow); padding: 15px; border: var(--border-width) solid var(--border-color); border-radius: 0 15px 15px 15px; margin-top: 15px; font-weight: 700; transform: rotate(-1deg); box-shadow: 3px 3px 0px var(--border-color); }
        .btn-link { display: inline-block; background: var(--color-purple); color: var(--white); font-family: 'Space Grotesk'; font-weight: 700; padding: 10px 20px; border: var(--border-width) solid var(--border-color); border-radius: var(--radius-pill); margin-top: 15px; box-shadow: 4px 4px 0px var(--border-color); transition: var(--transition); }
        .btn-link:hover { transform: translate(-2px, -2px); box-shadow: 6px 6px 0px var(--border-color); }

        .extras-section { background: var(--color-green); padding: 40px; border: var(--border-width) solid var(--border-color); border-radius: var(--radius); box-shadow: var(--shadow-brutal); margin-top: 60px; }
        .extras-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-top: 20px; }
        .extra-box { background: var(--white); padding: 20px; border: var(--border-width) solid var(--border-color); border-radius: var(--radius); box-shadow: 4px 4px 0px var(--border-color); }
        .extra-box ul { list-style: none; font-weight: 600; }
        .extra-box ul li::before { content: '👉 '; }

        /* CADDIE CRÉATION & GRID */
        .caddie-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 30px; }
        .caddie-card { background: var(--white); border: var(--border-width) solid var(--border-color); border-radius: var(--radius); overflow: hidden; box-shadow: var(--shadow-brutal); display: flex; flex-direction: column; }
        .caddie-header { background: var(--text-main); color: var(--white); padding: 15px 20px; font-size: 18px; text-transform: uppercase; display: flex; justify-content: space-between; align-items: center; }
        .caddie-body { padding: 20px; display: flex; flex-direction: column; gap: 12px; flex-grow: 1; }
        .check-item { display: flex; align-items: flex-start; gap: 12px; font-weight: 700; cursor: pointer; user-select: none; font-size: 15px; }
        .check-item input { width: 20px; height: 20px; accent-color: var(--color-pink); cursor: pointer; border: 2px solid var(--border-color); margin-top: 2px; }
        .check-item.checked span { text-decoration: line-through; opacity: 0.4; }

        /* WATCHLIST CRÉATION & GRID */
        .watchlist-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 30px; }
        .tracker-card { background: var(--white); border: var(--border-width) solid var(--border-color); border-radius: var(--radius); padding: 25px; box-shadow: var(--shadow-brutal); display: flex; flex-direction: column; gap: 15px; }
        .tracker-card h3 { font-size: 20px; border-bottom: 3px solid var(--border-color); padding-bottom: 8px; }
        .tracker-control { display: flex; align-items: center; justify-content: space-between; background: #f1f2f6; padding: 10px 15px; border: 2px solid var(--border-color); border-radius: var(--radius-pill); }
        .tracker-status { font-weight: 700; font-size: 14px; }
        .btn-counter { background: var(--color-yellow); border: 2px solid var(--border-color); width: 35px; height: 35px; border-radius: 50%; font-weight: 700; font-size: 18px; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 2px 2px 0px var(--border-color); }
        .btn-counter:active { transform: translate(2px, 2px); box-shadow: 0px 0px 0px; }
        .progress-container { background: #e0e0e0; border: 2px solid var(--border-color); height: 20px; border-radius: var(--radius-pill); overflow: hidden; }
        .progress-bar { background: var(--color-green); height: 100%; width: 0%; transition: width 0.3s ease; }

        /* DICE MACHINE */
        .dice-machine { background: var(--color-purple); border: var(--border-width) solid var(--border-color); border-radius: var(--radius); padding: 40px 20px; box-shadow: var(--shadow-brutal); text-align: center; margin-bottom: 60px; position: relative; }
        .dice-container { display: flex; align-items: center; justify-content: center; gap: 25px; flex-wrap: wrap; }
        .dice-cube { width: 90px; height: 90px; background: var(--white); border: 4px solid var(--border-color); border-radius: 20px; display: flex; align-items: center; justify-content: center; font-size: 40px; font-family: 'Space Grotesk'; font-weight: 700; box-shadow: 6px 6px 0px var(--border-color); }
        .rolling { animation: shake 0.4s infinite; }
        @keyframes shake { 0% { transform: translate(2px, 1px) rotate(0deg); } 25% { transform: translate(-1px, -2px) rotate(-10deg); } 50% { transform: translate(-3px, 0px) rotate(10deg); } 100% { transform: translate(1px, -1px) rotate(0deg); } }
        .btn-roll { background: var(--color-yellow); border: var(--border-width) solid var(--border-color); border-radius: var(--radius-pill); padding: 15px 35px; font-size: 20px; font-family: 'Space Grotesk'; font-weight: 700; cursor: pointer; box-shadow: 6px 6px 0px var(--border-color); }
        .result-box { display: none; background: var(--white); padding: 15px 25px; border: var(--border-width) solid var(--border-color); border-radius: var(--radius-pill); font-size: 18px; font-weight: 700; box-shadow: 4px 4px 0px var(--border-color); animation: slideUp 0.3s ease-out; }

        @media (max-width: 768px) {
            nav { width: 92%; justify-content: space-between; padding: 10px 20px; border-radius: 20px; }
            .nav-links { display: none; flex-direction: column; width: 100%; gap: 8px; padding-top: 12px; }
            .nav-links.open { display: flex; }
            .meals, .watchlist-grid, .caddie-grid, .home-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>

<body>

    <div class="bg-blob blob-1"></div>
    <div class="bg-blob blob-2"></div>
    <div class="bg-blob blob-3"></div>

    <nav>
        <div class="hamburger-menu" id="burger-btn" onclick="toggleMenu()">
            <span></span><span></span><span></span>
        </div>
        <ul class="nav-links" id="menu-links">
            <li><a id="nav-home" class="active" onclick="switchTab('home')">Accueil</a></li>
            <li><a id="nav-repas" onclick="switchTab('repas')">Miam</a></li>
            <li><a id="nav-anime" onclick="switchTab('anime')">Anime</a></li>
            <li><a id="nav-caddie" onclick="switchTab('caddie')">Caddie</a></li>
            <li><a id="nav-watchlist" onclick="switchTab('watchlist')">Watchlist</a></li>
        </ul>
    </nav>

    <div class="container">

        <section id="home" class="page-section active">
            <div class="hero">
                <h1 class="section-title accueil-title">CALAUDRY'S</h1>
                <h1 class="section-title accueil-title2">WEEK-END OTAKU</h1>
            </div>
            <div class="home-grid">
                <div class="brutal-card nav-card food" onclick="switchTab('repas')">
                    <i class="fa-solid fa-burger"></i>
                    <h2>Planning Repas</h2>
                    <p>Menu détaillé de vendredi à lundi pour survivre avec classe.</p>
                </div>
                <div class="brutal-card nav-card anime" onclick="switchTab('anime')">
                    <i class="fa-solid fa-gamepad"></i>
                    <h2>Univers Anime</h2>
                    <p>Stats complètes et machine à dé pour choisir vos épisodes.</p>
                </div>
                <div class="brutal-card nav-card caddie" onclick="switchTab('caddie')">
                    <i class="fa-solid fa-shopping-cart"></i>
                    <h2>Caddie</h2>
                    <p>Liste de courses interactive triée par rayons.</p>
                </div>
                <div class="brutal-card nav-card watchlist" onclick="switchTab('watchlist')">
                    <i class="fa-solid fa-circle-play"></i>
                    <h2>Watchlist</h2>
                    <p>Tracker de binge-watching interactif dynamique.</p>
                </div>
            </div>
        </section>

        <section id="repas" class="page-section">
            <h2 class="section-title">Le Menu 🍕</h2>
            <div class="timeline-grid">
                <div class="day-block">
                    <div class="day-badge">VENDREDI</div>
                    <div class="meals">
                        <div class="meal-card">
                            <div class="meal-header">
                                <h3>Chili con carne</h3>
                                <span class="time-tag soir">SOIR</span>
                            </div>
                            <div class="meal-body">
                                <div class="info-pill">Prép: 10m</div>
                                <div class="info-pill">Cuisson: 25m</div>
                                <div class="meal-label">Ingrédients</div>
                                <div class="meal-content">1 grande conserve de tomates pelées, 1 oignon et demi, 1 ou 2 gousses d’ail, 1 càc de sel, 2 càc de cumin...</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="anime" class="page-section">
            <h2 class="section-title anime-title">L'Antre Otaku 📺</h2>
            <div class="dice-machine">
                <h3 class="font-display" style="color:white; margin-bottom:15px;">Sélection Ultra Rapide</h3>
                <div class="dice-container">
                    <div class="dice-cube" id="dice">?</div>
                    <button class="btn-roll" onclick="rollDice()">Tirer au sort !</button>
                    <div class="result-box" id="dice-result"><span id="anime-chosen" style="color: var(--color-pink);"></span></div>
                </div>
            </div>
        </section>

        <section id="caddie" class="page-section">
            <h2 class="section-title caddie-title">Le Caddie 🛒</h2>
            
            <div class="brutal-card" style="margin-bottom: 40px; background: #fff;">
                <h3 class="font-display" style="margin-bottom: 15px; font-size: 20px;">📦 Créer un nouveau Rayon / Liste</h3>
                <form method="POST" action="?action=add_caddie_category" style="display: flex; gap: 15px; flex-wrap: wrap;">
                    <input type="text" name="new_category" placeholder="Nom du rayon (ex: 🍇 Fruits, 🥤 Boissons)..." required class="brutal-input" style="flex: 1; min-width: 250px;">
                    <input type="text" name="item_name" placeholder="Premier article (optionnel)..." class="brutal-input" style="flex: 1; min-width: 200px;">
                    <button type="submit" class="brutal-btn-small" style="background: var(--color-green); padding: 10px 20px;">Créer la liste</button>
                </form>
            </div>

            <div class="caddie-grid">
                <?php foreach ($caddie_grouped as $categorie => $items): ?>
                    <div class="caddie-card">
                        <div class="caddie-header">
                            <span><?php echo htmlspecialchars($categorie); ?></span>
                            <a href="?action=delete_caddie_category&categorie=<?php echo urlencode($categorie); ?>" class="delete-btn" title="Supprimer tout ce rayon" onclick="return confirm('Supprimer tout le rayon ?');">
                                <i class="fa-solid fa-trash"></i>
                            </a>
                        </div>
                        <div class="caddie-body">
                            <?php foreach ($items as $item): ?>
                                <div style="display: flex; justify-content: space-between; align-items: center; gap: 10px;">
                                    <label class="check-item <?php echo $item['est_achete'] ? 'checked' : ''; ?>">
                                        <input type="checkbox" data-id="<?php echo $item['id']; ?>" <?php echo $item['est_achete'] ? 'checked' : ''; ?> onclick="toggleCheck(this)">
                                        <span><?php echo htmlspecialchars($item['item_name']); ?></span>
                                    </label>
                                    <a href="?action=delete_caddie_item&id=<?php echo $item['id']; ?>" class="delete-btn" style="width:22px; height:22px; font-size:10px;">
                                        <i class="fa-solid fa-xmark"></i>
                                    </a>
                                </div>
                            <?php endforeach; ?>

                            <form method="POST" action="?action=add_caddie_item" style="display: flex; gap: 8px; margin-top: auto; padding-top: 15px; border-top: 2px dashed var(--border-color);">
                                <input type="hidden" name="categorie" value="<?php echo htmlspecialchars($categorie); ?>">
                                <input type="text" name="item_name" placeholder="Ajouter un article..." required class="brutal-input" style="flex: 1; padding: 6px 12px; font-size: 14px;">
                                <button type="submit" class="brutal-btn-small" style="padding: 6px 12px;">+</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section id="watchlist" class="page-section">
            <h2 class="section-title watchlist-title">La Watchlist 🍿</h2>

            <div class="brutal-card" style="margin-bottom: 40px; background: #fff;">
                <h3 class="font-display" style="margin-bottom: 15px; font-size: 20px;">🎬 Ajouter une série à binge-watcher</h3>
                <form method="POST" action="?action=add_anime" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: center;">
                    <input type="text" name="titre" placeholder="Nom de l'anime..." required class="brutal-input" style="flex: 2; min-width: 220px;">
                    <input type="number" name="max_episodes" placeholder="Nb épisodes max..." required min="1" class="brutal-input" style="flex: 1; min-width: 100px;">
                    <button type="submit" class="brutal-btn-small" style="background: var(--color-purple); color: white; padding: 10px 20px;">Ajouter à la liste</button>
                </form>
            </div>

            <div class="watchlist-grid">
                <?php foreach ($watchlist_data as $info): 
                    $slug = $info['slug'];
                    $current_ep = $info['nb_episodes_vus'];
                    $max_ep = $info['max_episodes'];
                    $percent = ($max_ep > 0) ? ($current_ep / $max_ep) * 100 : 0;
                ?>
                    <div class="tracker-card">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 10px;">
                            <h3><?php echo htmlspecialchars($info['titre']); ?></h3>
                            <a href="?action=delete_anime&slug=<?php echo $slug; ?>" class="delete-btn" title="Retirer de la watchlist" onclick="return confirm('Retirer cet anime ?');">
                                <i class="fa-solid fa-trash"></i>
                            </a>
                        </div>
                        <div class="tracker-control">
                            <button class="btn-counter" onclick="updateEp('<?php echo $slug; ?>', -1, <?php echo $max_ep; ?>)">-</button>
                            <div class="tracker-status">Épisode <span id="ep-<?php echo $slug; ?>"><?php echo $current_ep; ?></span> / <?php echo $max_ep; ?></div>
                            <button class="btn-counter" onclick="updateEp('<?php echo $slug; ?>', 1, <?php echo $max_ep; ?>)">+</button>
                        </div>
                        <div class="progress-container">
                            <div class="progress-bar" id="bar-<?php echo $slug; ?>" style="width: <?php echo $percent; ?>%;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

    </div>

    <script>
        // Extraction dynamique de la liste des animes enregistrés en BDD pour la machine à dé
        const dynamicAnimes = <?php echo json_encode(array_column($watchlist_data, 'titre')); ?>;

        // Gestion globale de la persistance de l'onglet actif
        function toggleMenu() {
            document.getElementById('burger-btn').classList.toggle('open');
            document.getElementById('menu-links').classList.toggle('open');
        }

        function switchTab(tabId) {
            document.querySelectorAll('.page-section').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.nav-links li a').forEach(el => el.classList.remove('active'));

            document.getElementById(tabId).classList.add('active');
            const targetNav = document.getElementById('nav-' + tabId);
            if(targetNav) targetNav.classList.add('active');

            document.getElementById('burger-btn').classList.remove('open');
            document.getElementById('menu-links').classList.remove('open');
            
            // Sauvegarde de l'état
            localStorage.setItem('active_tab_weekend', tabId);
        }

        // Restauration de l'onglet au rechargement
        window.addEventListener('DOMContentLoaded', () => {
            const activeTab = localStorage.getItem('active_tab_weekend') || 'home';
            switchTab(activeTab);
        });

        // Dé dynamique basé sur la BDD
        function rollDice() {
            const dice = document.getElementById('dice');
            const resultBox = document.getElementById('dice-result');
            const animeChosen = document.getElementById('anime-chosen');

            resultBox.style.display = 'none';
            dice.classList.add('rolling');
            dice.innerText = '🎲';

            setTimeout(() => {
                dice.classList.remove('rolling');
                if (dynamicAnimes.length === 0) {
                    dice.innerText = 'X';
                    animeChosen.innerText = "Aucun anime dans la Watchlist !";
                } else {
                    const randomIndex = Math.floor(Math.random() * dynamicAnimes.length);
                    dice.innerText = randomIndex + 1;
                    animeChosen.innerText = dynamicAnimes[randomIndex];
                }
                resultBox.style.display = 'block';
            }, 800);
        }

        // AJAX: Sauvegarde asynchrone des cases cochées (Caddie)
        function toggleCheck(checkbox) {
            const id = checkbox.getAttribute('data-id');
            const isChecked = checkbox.checked ? 1 : 0;
            
            if (checkbox.checked) {
                checkbox.parentElement.classList.add('checked');
            } else {
                checkbox.parentElement.classList.remove('checked');
            }

            const formData = new FormData();
            formData.append('id', id);
            formData.append('checked', isChecked);

            fetch('?action=update_caddie', {
                method: 'POST',
                body: formData
            }).catch(err => console.error("Erreur Caddie:", err));
        }

        // AJAX: Sauvegarde asynchrone des épisodes (Watchlist)
        function updateEp(anime, change, maxEpisodes) {
            const epSpan = document.getElementById('ep-' + anime);
            let currentEp = parseInt(epSpan.innerText);
            let newEp = currentEp + change;

            if (newEp >= 0 && newEp <= maxEpisodes) {
                epSpan.innerText = newEp;
                const percent = (newEp / maxEpisodes) * 100;
                document.getElementById('bar-' + anime).style.width = percent + '%';

                const formData = new FormData();
                formData.append('slug', anime);
                formData.append('ep', newEp);

                fetch('?action=update_anime', {
                    method: 'POST',
                    body: formData
                }).catch(err => console.error("Erreur Anime:", err));
            }
        }
    </script>
</body>
</html>
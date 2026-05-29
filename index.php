<?php
require_once __DIR__ . '/config.php';
function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
$classes = file(__DIR__ . '/models/class_names.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
$history = [];
$historyFile = __DIR__ . '/logs/history.json';
if (is_file($historyFile)) $history = json_decode(file_get_contents($historyFile), true) ?: [];

if (isset($_GET['hapus_history'])) {
    $index = (int) $_GET['hapus_history'];
    if (isset($history[$index])) {
        unset($history[$index]);
        $history = array_values($history);
        file_put_contents($historyFile, json_encode($history, JSON_PRETTY_PRINT));
    }
    header('Location: index.php');
    exit;
}

$result = null; $error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode = $_POST['model_mode'] ?? DEFAULT_MODEL_MODE;
    if (!in_array($mode, ['ensemble','final','best'], true)) $mode = DEFAULT_MODEL_MODE;
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Gambar belum dipilih atau gagal diupload.';
    } else {
        $maxBytes = MAX_UPLOAD_MB * 1024 * 1024;
        if ($_FILES['image']['size'] > $maxBytes) {
            $error = 'Ukuran gambar maksimal ' . MAX_UPLOAD_MB . 'MB.';
        } else {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($_FILES['image']['tmp_name']);
            $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
            if (!isset($allowed[$mime])) {
                $error = 'Format harus JPG, PNG, atau WEBP.';
            } else {
                if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0777, true);
                $name = 'batik_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
                $dest = UPLOAD_DIR . $name;
                move_uploaded_file($_FILES['image']['tmp_name'], $dest);
                $cmd = escapeshellcmd(PYTHON_BIN) . ' ' . escapeshellarg(__DIR__ . '/python/predict.py') . ' ' . escapeshellarg($dest) . ' ' . escapeshellarg($mode);
                $out = shell_exec($cmd . ' 2>&1');
                $json = json_decode($out, true);
                if (!$json) {
                    // Jika TensorFlow mencetak warning sebelum JSON, ambil JSON terakhir dari output.
                    if (preg_match_all('/\{.*\}/sU', $out, $matches) && !empty($matches[0])) {
                        $json = json_decode(end($matches[0]), true);
                    }
                }
                if (!$json || empty($json['success'])) {
                    $detail = is_array($json) && isset($json['error']) ? $json['error'] : $out;
                    $error = 'Prediksi gagal. Pastikan Python, TensorFlow, Pillow, dan NumPy sudah terinstall. Detail: ' . $detail;
                } else {
                    $result = $json;
                    $result['image_url'] = 'uploads/' . $name;
                    array_unshift($history, $result);
                    $history = array_slice($history, 0, 8);
                    file_put_contents($historyFile, json_encode($history, JSON_PRETTY_PRINT));
                }
            }
        }
    }
}
?>
<!doctype html><html lang="id" data-theme="dark"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(APP_NAME) ?></title><link rel="stylesheet" href="assets/css/style.css">
</head><body>
<div class="bg-orb one"></div><div class="bg-orb two"></div><div class="bg-grid"></div>
<nav class="nav"><div class="brand"><div class="logo">AI</div><div><b><?= e(APP_NAME) ?></b><span>Native PHP · TensorFlow Model</span></div></div><button id="themeBtn" class="theme-btn">🌙 Gelap</button></nav>
<main class="wrap">
<section class="hero card">
 <div class="hero-text"><span class="badge">Premium Batik Classifier</span><h1>Prediksi Motif Batik Otomatis</h1><p>Upload gambar batik, sistem akan menampilkan nama batik, confidence, dan top prediksi tanpa mengetik nama kelas.</p>
 <div class="stats"><div><b><?= count($classes) ?></b><span>Kelas Batik</span></div><div><b>2</b><span>Model Aktif</span></div><div><b>Dark/Light</b><span>Mode</span></div></div></div>
 <form method="post" enctype="multipart/form-data" class="upload-box">
   <label class="drop" id="drop"><input type="file" name="image" id="image" accept="image/*" required><div id="previewIcon">🖼️</div><b>Klik / tarik gambar ke sini</b><span>JPG, PNG, WEBP · Maks <?= MAX_UPLOAD_MB ?>MB</span><img id="preview" alt="preview"></label>
   <div class="row"><select name="model_mode"><option value="ensemble">Ensemble Final + Best</option><option value="final">Final Model</option><option value="best">Best Model</option></select><button type="submit">Prediksi Sekarang</button></div>
 </form>
</section>
<?php if ($error): ?><section class="alert error card"><?= e($error) ?></section><?php endif; ?>
<?php if ($result): ?>
<section class="result-grid">
 <div class="card result-main"><img src="<?= e($result['image_url']) ?>" alt="hasil"><div><span class="badge ok">Hasil Prediksi</span><h2><?= e(str_replace('_',' ', $result['prediksi'])) ?></h2><p class="conf"><?= number_format($result['confidence']*100,2) ?>%</p><p>Mode: <b><?= e(strtoupper($result['mode'])) ?></b></p><p>Input model: <b><?= e($result['input_size'] ?? '-') ?></b></p></div></div>
 <div class="card"><h3>Top Prediksi</h3><?php foreach(($result['top_predictions'] ?? []) as $p): $pct=$p['confidence']*100; ?><div class="bar-row"><div><b><?= e(str_replace('_',' ', $p['class'])) ?></b><span><?= number_format($pct,2) ?>%</span></div><div class="bar"><i style="width:<?= min(100,$pct) ?>%"></i></div></div><?php endforeach; ?></div>
</section>
<?php endif; ?>
<section class="columns">
 <div class="card"><h3>Daftar Kelas Batik</h3><div class="chips"><?php foreach($classes as $c): ?><span><?= e(str_replace('_',' ', $c)) ?></span><?php endforeach; ?></div></div>
 <div class="card">
<h3>Riwayat Terakhir</h3>
<?php if(!$history): ?>
<p class="muted">Belum ada prediksi.</p>
<?php endif; ?>

<div class="history-list">
<?php foreach($history as $i => $h): ?>
<div class="history">
    <img src="<?= e($h['image_url'] ?? '') ?>">
    <div class="history-info">
        <b><?= e(str_replace('_',' ', $h['prediksi'] ?? '-')) ?></b>
        <span><?= number_format(($h['confidence'] ?? 0)*100,2) ?>% · <?= e($h['mode'] ?? '') ?></span>
    </div>
    <a class="delete-btn" href="?hapus_history=<?= $i ?>" onclick="return confirm('Hapus riwayat ini?')">Hapus</a>
</div>
<?php endforeach; ?>
</div>
</div>
</section>
</main><footer>© <?= date('Y') ?> Batik AI Premium · PHP Native</footer><script src="assets/js/app.js"></script></body></html>

<?php
// Database connection (Update with your credentials)
$host = 'localhost';
$db   = 'sugar_db';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $data = $pdo->query("SELECT * FROM sugar_sales_comparison")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Logic for Summary Cards (using the first row which is usually the total estimate)
$total_tcsc = $data[0]['tcsc_usd'] ?? 0;
$total_kbs = $data[0]['kbs_usd'] ?? 0;
$variance_usd = $total_kbs - $total_tcsc;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sugar Sales Executive Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f4f7f6; }
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .metric-card { transition: transform 0.2s; }
        .metric-card:hover { transform: translateY(-5px); }
        .table-container { background: white; border-radius: 12px; padding: 20px; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold text-dark">Sugar Sales Performance</h2>
        <span class="badge bg-primary px-3 py-2">Season 2025/2026</span>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card metric-card p-3 bg-white">
                <small class="text-muted text-uppercase fw-bold">Total Est. TCSC</small>
                <h3 class="text-primary mb-0">$<?= number_format($total_tcsc / 1000000, 2) ?>M</h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card metric-card p-3 bg-white border-start border-info border-4">
                <small class="text-muted text-uppercase fw-bold">Total Est. KBS</small>
                <h3 class="text-info mb-0">$<?= number_format($total_kbs / 1000000, 2) ?>M</h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card metric-card p-3 <?= $variance_usd >= 0 ? 'bg-success text-white' : 'bg-danger text-white' ?>">
                <small class="text-uppercase fw-bold">Variance (KBS vs TCSC)</small>
                <h3 class="mb-0">$<?= number_format(abs($variance_usd) / 1000000, 2) ?>M</h3>
            </div>
        </div>
    </div>

    <div class="table-container shadow-sm">
        <h5 class="mb-3 fw-bold">Detailed Comparison Table</h5>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Category</th>
                        <th class="text-end">TCSC (USD)</th>
                        <th class="text-center">TCSC %</th>
                        <th class="text-end">KBS (USD)</th>
                        <th class="text-center">KBS %</th>
                        <th class="text-center">Diff %</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data as $row): ?>
                    <tr>
                        <td class="fw-semibold text-secondary"><?= htmlspecialchars($row['category']) ?></td>
                        <td class="text-end"><?= number_format($row['tcsc_usd'], 0) ?></td>
                        <td class="text-center"><span class="badge bg-light text-dark"><?= $row['tcsc_pct'] ?>%</span></td>
                        <td class="text-end"><?= number_format($row['kbs_usd'], 0) ?></td>
                        <td class="text-center"><span class="badge bg-light text-dark"><?= $row['kbs_pct'] ?>%</span></td>
                        <td class="text-center fw-bold <?= $row['var_pct'] >= 0 ? 'text-success' : 'text-danger' ?>">
                            <?= $row['var_pct'] ?>%
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>
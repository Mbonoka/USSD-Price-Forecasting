<?php
require_once '../config.php';

// Check user authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Crop Price Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8">
                <h2>Price Trends</h2>
                <canvas id="priceChart"></canvas>
            </div>
            <div class="col-md-4">
                <h2>My Alerts</h2>
                <?php
                $stmt = getDBConnection()->prepare("
                    SELECT * FROM price_alerts 
                    WHERE user_id = ? AND is_active = 1
                ");
                $stmt->execute([$userId]);
                $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($alerts as $alert) {
                    echo "<div class='alert alert-info'>";
                    echo "Crop: " . $alert['crop_name'] . "<br>";
                    echo "Target Price: " . $alert['target_price'];
                    echo "</div>";
                }
                ?>
            </div>
        </div>
    </div>

    <script>
        // Price chart initialization
        const ctx = document.getElementById('priceChart').getContext('2d');
        fetch('/api/prices')
            .then(response => response.json())
            .then(data => {
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: data.map(item => item.price_date),
                        datasets: [{
                            label: 'Price Trends',
                            data: data.map(item => item.price_value)
                        }]
                    }
                });
            });
    </script>
</body>
</html>
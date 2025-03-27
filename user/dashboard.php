<?php
require_once '../config.php';

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
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">Crop Price Dashboard</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Price Trends</h5>
                        <select id="timeRange" class="form-select" style="width: auto;">
                            <option value="7">Last 7 days</option>
                            <option value="30" selected>Last 30 days</option>
                            <option value="90">Last 90 days</option>
                        </select>
                    </div>
                    <div class="card-body">
                        <canvas id="priceChart"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">My Alerts</h5>
                    </div>
                    <div class="card-body">
                        <div id="alertsContainer">
                            <?php
                            try {
                                $stmt = Database::getInstance()->getConnection()->prepare("
                                    SELECT 
                                        pa.*,
                                        c.name as crop_name,
                                        m.name as market_name
                                    FROM price_alerts pa
                                    JOIN crops c ON pa.crop_id = c.crop_id
                                    JOIN markets m ON pa.market_id = m.market_id
                                    WHERE pa.user_id = ? AND pa.is_active = 1
                                ");
                                $stmt->execute([$userId]);
                                $alerts = $stmt->fetchAll();
                                
                                foreach ($alerts as $alert) {
                                    echo "<div class='alert alert-info'>";
                                    echo "<strong>{$alert['crop_name']}</strong> - {$alert['market_name']}<br>";
                                    echo "Target Price: ₹{$alert['target_price']}<br>";
                                    echo "<button class='btn btn-sm btn-danger mt-2' 
                                          onclick='deleteAlert({$alert['alert_id']})'>Delete</button>";
                                    echo "</div>";
                                }
                            } catch (Exception $e) {
                                echo "<div class='alert alert-danger'>Unable to load alerts</div>";
                            }
                            ?>
                        </div>
                        <button class="btn btn-primary mt-3" data-bs-toggle="modal" 
                                data-bs-target="#newAlertModal">
                            Add New Alert
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let priceChart = null;
        
        async function loadPriceData(days) {
            try {
                const response = await fetch(`/api/prices?days=${days}`);
                const data = await response.json();
                
                if (priceChart) {
                    priceChart.destroy();
                }
                
                const ctx = document.getElementById('priceChart').getContext('2d');
                priceChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: data.data.map(item => item.price_date),
                        datasets: [{
                            label: 'Price Trends',
                            data: data.data.map(item => item.price_value),
                            borderColor: 'rgb(75, 192, 192)',
                            tension: 0.1
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'top',
                            }
                        }
                    }
                });
            } catch (error) {
                console.error('Error loading price data:', error);
            }
        }
        
        document.getElementById('timeRange').addEventListener('change', (e) => {
            loadPriceData(e.target.value);
        });
        
        // Initial load
        loadPriceData(30);
    </script>
</body>
</html>
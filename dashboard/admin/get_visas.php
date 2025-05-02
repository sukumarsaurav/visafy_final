
<?php
require_once '../../config/db_connect.php';
header('Content-Type: application/json');

if (!isset($_GET['country_id']) || empty($_GET['country_id'])) {
    echo json_encode([]);
    exit;
}

$country_id = intval($_GET['country_id']);

// Get visa types for the selected country
$query = "SELECT visa_id, visa_type FROM visas WHERE country_id = ? AND is_active = 1";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $country_id);
$stmt->execute();
$result = $stmt->get_result();
$visas = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $visas[] = $row;
    }
}
$stmt->close();

echo json_encode($visas);
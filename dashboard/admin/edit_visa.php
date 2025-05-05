<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

$page_title = "Edit Visa";
$page_specific_css = "assets/css/visa.css";
require_once 'includes/header.php';

// Check if visa ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: visa.php");
    exit;
}

$visa_id = intval($_GET['id']);

// Get visa details
$query = "SELECT v.*, c.country_name, c.country_code 
          FROM visas v 
          JOIN countries c ON v.country_id = c.country_id 
          WHERE v.visa_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $visa_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: visa.php");
    exit;
}

$visa = $result->fetch_assoc();
$stmt->close();

// Get all countries for the country dropdown
$countries_query = "SELECT country_id, country_name 
                   FROM countries 
                   WHERE is_active = 1 
                   ORDER BY country_name ASC";
$stmt = $conn->prepare($countries_query);
$stmt->execute();
$countries_result = $stmt->get_result();
$countries = [];

if ($countries_result && $countries_result->num_rows > 0) {
    while ($row = $countries_result->fetch_assoc()) {
        $countries[] = $row;
    }
}
$stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_visa'])) {
    $country_id = intval($_POST['country_id']);
    $visa_type = trim($_POST['visa_type']);
    $description = trim($_POST['description']);
    $validity_period = !empty($_POST['validity_period']) ? intval($_POST['validity_period']) : null;
    $fee = !empty($_POST['fee']) ? floatval($_POST['fee']) : null;
    $requirements = trim($_POST['requirements']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validate inputs
    $errors = [];
    if (empty($country_id)) {
        $errors[] = "Please select a country";
    }
    if (empty($visa_type)) {
        $errors[] = "Visa type is required";
    }
    
    if (empty($errors)) {
        // Update visa
        $update_query = "UPDATE visas SET 
                        country_id = ?,
                        visa_type = ?, 
                        description = ?, 
                        validity_period = ?, 
                        fee = ?, 
                        requirements = ?, 
                        is_active = ? 
                        WHERE visa_id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param('issiisii', $country_id, $visa_type, $description, $validity_period, $fee, $requirements, $is_active, $visa_id);
        
        if ($stmt->execute()) {
            $success_message = "Visa updated successfully";
            $stmt->close();
            
            // Redirect to visa details page after successful update
            header("Location: visa_details.php?id={$visa_id}&success=1");
            exit;
        } else {
            $error_message = "Error updating visa: " . $conn->error;
            $stmt->close();
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}
?>

<div class="content">
    <div class="header-container">
        <div>
            <h1>Edit Visa: <?php echo htmlspecialchars($visa['visa_type']); ?></h1>
            <p>Country: <?php echo htmlspecialchars($visa['country_name']); ?> (<?php echo htmlspecialchars($visa['country_code']); ?>)</p>
        </div>
        <div class="action-buttons">
            <a href="visa_details.php?id=<?php echo $visa_id; ?>" class="btn secondary-btn">
                <i class="fas fa-arrow-left"></i> Back to Visa Details
            </a>
        </div>
    </div>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <div class="country-card">
        <div class="country-header">
            <div class="country-info">
                <h3>Edit Visa Information</h3>
            </div>
        </div>
        <div class="visas-table-container">
            <form action="edit_visa.php?id=<?php echo $visa_id; ?>" method="POST">
                <div class="form-group">
                    <label for="country_id">Country*</label>
                    <select name="country_id" id="country_id" class="form-control" required>
                        <?php foreach ($countries as $country): ?>
                            <option value="<?php echo $country['country_id']; ?>" <?php echo ($country['country_id'] == $visa['country_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($country['country_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="visa_type">Visa Type*</label>
                    <input type="text" name="visa_type" id="visa_type" class="form-control" value="<?php echo htmlspecialchars($visa['visa_type']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea name="description" id="description" class="form-control" rows="3"><?php echo htmlspecialchars($visa['description']); ?></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="validity_period">Validity Period (days)</label>
                        <input type="number" name="validity_period" id="validity_period" class="form-control" min="1" value="<?php echo $visa['validity_period']; ?>">
                    </div>
                    <div class="form-group">
                        <label for="fee">Fee ($)</label>
                        <input type="number" name="fee" id="fee" class="form-control" min="0" step="0.01" value="<?php echo $visa['fee']; ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="requirements">Requirements</label>
                    <textarea name="requirements" id="requirements" class="form-control" rows="4"><?php echo htmlspecialchars($visa['requirements']); ?></textarea>
                </div>
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" name="is_active" id="visa_is_active" value="1" <?php echo $visa['is_active'] ? 'checked' : ''; ?>>
                        <label for="visa_is_active">Active</label>
                    </div>
                </div>
                <div class="form-buttons">
                    <a href="visa_details.php?id=<?php echo $visa_id; ?>" class="btn cancel-btn">Cancel</a>
                    <button type="submit" name="update_visa" class="btn submit-btn">Update Visa</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
:root {
    --primary-color: #042167;
    --secondary-color: #858796;
    --success-color: #1cc88a;
    --danger-color: #e74a3b;
    --light-color: #f8f9fc;
    --dark-color: #5a5c69;
    --border-color: #e3e6f0;
    --warning-color: #f6c23e;
}

.content {
    padding: 20px;
}

.header-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.header-container h1 {
    margin: 0;
    color: var(--primary-color);
    font-size: 1.8rem;
}

.header-container p {
    margin: 5px 0 0;
    color: var(--secondary-color);
}

.action-buttons {
    display: flex;
    gap: 10px;
}

.primary-btn {
    background-color: var(--primary-color);
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 4px;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
}

.primary-btn:hover {
    background-color: #031c56;
}

.secondary-btn {
    background-color: var(--secondary-color);
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 4px;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
}

.secondary-btn:hover {
    background-color: #7d7f88;
    color: white;
    text-decoration: none;
}

.country-card {
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.country-header {
    padding: 15px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid var(--border-color);
    background-color: var(--light-color);
}

.country-info {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.country-info h3 {
    margin: 0;
    color: var(--primary-color);
    font-size: 1.3rem;
}

.visas-table-container {
    padding: 20px;
}

.alert {
    padding: 12px 15px;
    border-radius: 4px;
    margin-bottom: 20px;
}

.alert-danger {
    background-color: rgba(231, 74, 59, 0.1);
    color: var(--danger-color);
    border: 1px solid rgba(231, 74, 59, 0.2);
}

.alert-success {
    background-color: rgba(28, 200, 138, 0.1);
    color: var(--success-color);
    border: 1px solid rgba(28, 200, 138, 0.2);
}

.form-row {
    display: flex;
    gap: 15px;
    margin-bottom: 15px;
}

.form-group {
    flex: 1;
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
    color: var(--dark-color);
}

.form-control {
    width: 100%;
    padding: 10px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    font-size: 14px;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 2px rgba(4, 33, 103, 0.1);
}

textarea.form-control {
    resize: vertical;
    min-height: 80px;
}

.checkbox-group {
    display: flex;
    align-items: center;
}

.checkbox-group input[type="checkbox"] {
    margin-right: 10px;
}

.form-buttons {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 20px;
}

.cancel-btn {
    background-color: white;
    color: var(--secondary-color);
    border: 1px solid var(--border-color);
    padding: 10px 20px;
    border-radius: 4px;
    cursor: pointer;
    text-decoration: none;
}

.cancel-btn:hover {
    background-color: #f8f9fc;
}

.submit-btn {
    background-color: var(--primary-color);
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 4px;
    cursor: pointer;
}

.submit-btn:hover {
    background-color: #031c56;
}

@media (max-width: 768px) {
    .header-container {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .action-buttons {
        width: 100%;
    }
    
    .primary-btn, .secondary-btn {
        flex: 1;
        justify-content: center;
    }
    
    .form-row {
        flex-direction: column;
        gap: 0;
    }
}
</style>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>

<?php require_once 'includes/footer.php'; ?>

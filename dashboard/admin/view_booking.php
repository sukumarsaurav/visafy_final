<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

$page_title = "Booking Details";
$page_specific_css = "assets/css/view_booking.css";
require_once 'includes/header.php';

// Check if booking ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: bookings.php");
    exit;
}

$booking_id = (int)$_GET['id'];

// Get booking details
$query = "SELECT b.*, bs.name as status_name, bs.color as status_color,
          CONCAT(u.first_name, ' ', u.last_name) as client_name, u.email as client_email, u.id as client_id,
          vs.visa_service_id, v.visa_type, c.country_name, st.service_name,
          cm.mode_name as consultation_mode,
          CONCAT(team_u.first_name, ' ', team_u.last_name) as consultant_name,
          tm.id as team_member_id, tm.role as consultant_role,
          vs.base_price, scm.additional_fee,
          (vs.base_price + IFNULL(scm.additional_fee, 0)) as total_price,
          bp.payment_status, bp.id as payment_id, bp.payment_method,
          b.meeting_link, b.location, b.time_zone
          FROM bookings b
          JOIN booking_statuses bs ON b.status_id = bs.id
          JOIN users u ON b.user_id = u.id
          JOIN visa_services vs ON b.visa_service_id = vs.visa_service_id
          JOIN service_consultation_modes scm ON b.service_consultation_id = scm.service_consultation_id
          JOIN consultation_modes cm ON scm.consultation_mode_id = cm.consultation_mode_id
          JOIN visas v ON vs.visa_id = v.visa_id
          JOIN countries c ON v.country_id = c.country_id
          JOIN service_types st ON vs.service_type_id = st.service_type_id
          LEFT JOIN team_members tm ON b.team_member_id = tm.id
          LEFT JOIN users team_u ON tm.user_id = team_u.id
          LEFT JOIN booking_payments bp ON b.id = bp.booking_id
          WHERE b.id = ? AND b.deleted_at IS NULL";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $booking_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Booking not found, redirect to bookings list
    $stmt->close();
    header("Location: bookings.php");
    exit;
}

$booking = $result->fetch_assoc();
$stmt->close();

// Get all booking statuses
$query = "SELECT * FROM booking_statuses ORDER BY id";
$stmt = $conn->prepare($query);
$stmt->execute();
$statuses_result = $stmt->get_result();
$booking_statuses = [];

if ($statuses_result && $statuses_result->num_rows > 0) {
    while ($row = $statuses_result->fetch_assoc()) {
        $booking_statuses[$row['id']] = $row;
    }
}
$stmt->close();

// Get all team members
$query = "SELECT tm.id, tm.role, tm.custom_role_name, 
          u.id as user_id, u.first_name, u.last_name, u.email 
          FROM team_members tm 
          JOIN users u ON tm.user_id = u.id 
          WHERE tm.deleted_at IS NULL AND u.status = 'active'
          ORDER BY u.first_name, u.last_name";
$stmt = $conn->prepare($query);
$stmt->execute();
$team_members_result = $stmt->get_result();
$team_members = [];

if ($team_members_result && $team_members_result->num_rows > 0) {
    while ($row = $team_members_result->fetch_assoc()) {
        $team_members[$row['id']] = $row;
    }
}
$stmt->close();

// Get activity logs
$query = "SELECT bal.*, CONCAT(u.first_name, ' ', u.last_name) as user_name
          FROM booking_activity_logs bal
          JOIN users u ON bal.user_id = u.id
          WHERE bal.booking_id = ?
          ORDER BY bal.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $booking_id);
$stmt->execute();
$activity_result = $stmt->get_result();
$activity_logs = [];

if ($activity_result && $activity_result->num_rows > 0) {
    while ($row = $activity_result->fetch_assoc()) {
        $activity_logs[] = $row;
    }
}
$stmt->close();

// Get documents
$query = "SELECT bd.*, CONCAT(u.first_name, ' ', u.last_name) as uploaded_by_name
          FROM booking_documents bd
          JOIN users u ON bd.uploaded_by = u.id
          WHERE bd.booking_id = ?
          ORDER BY bd.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $booking_id);
$stmt->execute();
$documents_result = $stmt->get_result();
$documents = [];

if ($documents_result && $documents_result->num_rows > 0) {
    while ($row = $documents_result->fetch_assoc()) {
        $documents[] = $row;
    }
}
$stmt->close();

// Handle booking status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $new_status_id = $_POST['status_id'];
    $admin_notes = isset($_POST['admin_notes']) ? trim($_POST['admin_notes']) : '';
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Update booking status
        $update_query = "UPDATE bookings SET status_id = ?, admin_notes = CONCAT(IFNULL(admin_notes, ''), '\n[', NOW(), '] ', ?) WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param('isi', $new_status_id, $admin_notes, $booking_id);
        $stmt->execute();
        $stmt->close();
        
        // Add activity log
        $status_name = $booking_statuses[$new_status_id]['name'];
        $log_query = "INSERT INTO booking_activity_logs 
                     (booking_id, user_id, activity_type, description) 
                     VALUES (?, ?, 'status_changed', ?)";
        $description = "Status changed to '{$status_name}'";
        if (!empty($admin_notes)) {
            $description .= " with notes: {$admin_notes}";
        }
        
        $stmt = $conn->prepare($log_query);
        $stmt->bind_param('iis', $booking_id, $_SESSION['id'], $description);
        $stmt->execute();
        $stmt->close();
        
        // Commit transaction
        $conn->commit();
        
        $success_message = "Booking status updated successfully";
        header("Location: view_booking.php?id=$booking_id&success=1");
        exit;
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        $error_message = "Error updating booking status: " . $e->getMessage();
    }
}

// Handle booking assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_consultant'])) {
    $consultant_id = $_POST['consultant_id'];
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Update booking
        $update_query = "UPDATE bookings SET team_member_id = ? WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param('ii', $consultant_id, $booking_id);
        $stmt->execute();
        $stmt->close();
        
        // Add activity log
        $consultant_name = $team_members[$consultant_id]['first_name'] . ' ' . $team_members[$consultant_id]['last_name'];
        $log_query = "INSERT INTO booking_activity_logs 
                     (booking_id, user_id, activity_type, description) 
                     VALUES (?, ?, 'assigned', ?)";
        $description = "Booking assigned to {$consultant_name}";
        
        $stmt = $conn->prepare($log_query);
        $stmt->bind_param('iis', $booking_id, $_SESSION['id'], $description);
        $stmt->execute();
        $stmt->close();
        
        // Commit transaction
        $conn->commit();
        
        $success_message = "Booking assigned successfully";
        header("Location: view_booking.php?id=$booking_id&success=2");
        exit;
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        $error_message = "Error assigning booking: " . $e->getMessage();
    }
}

// Handle cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_booking'])) {
    $cancellation_reason = trim($_POST['cancellation_reason']);
    
    // Get the cancellation status ID
    $cancel_status_query = "SELECT id FROM booking_statuses WHERE name = 'cancelled_by_admin'";
    $stmt = $conn->prepare($cancel_status_query);
    $stmt->execute();
    $cancel_status = $stmt->get_result()->fetch_assoc();
    $cancel_status_id = $cancel_status['id'];
    $stmt->close();
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Update booking
        $update_query = "UPDATE bookings SET status_id = ?, cancelled_by = ?, cancellation_reason = ?, cancelled_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param('iisi', $cancel_status_id, $_SESSION['id'], $cancellation_reason, $booking_id);
        $stmt->execute();
        $stmt->close();
        
        // Add activity log
        $log_query = "INSERT INTO booking_activity_logs 
                     (booking_id, user_id, activity_type, description) 
                     VALUES (?, ?, 'cancelled', ?)";
        $description = "Booking cancelled by admin with reason: {$cancellation_reason}";
        
        $stmt = $conn->prepare($log_query);
        $stmt->bind_param('iis', $booking_id, $_SESSION['id'], $description);
        $stmt->execute();
        $stmt->close();
        
        // Commit transaction
        $conn->commit();
        
        $success_message = "Booking cancelled successfully";
        header("Location: view_booking.php?id=$booking_id&success=3");
        exit;
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        $error_message = "Error cancelling booking: " . $e->getMessage();
    }
}

// Handle rescheduling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reschedule_booking'])) {
    $new_datetime = $_POST['new_datetime'];
    $duration_minutes = $_POST['duration_minutes'];
    $reschedule_notes = trim($_POST['reschedule_notes']);
    
    // Get the rescheduled status ID
    $status_query = "SELECT id FROM booking_statuses WHERE name = 'rescheduled'";
    $stmt = $conn->prepare($status_query);
    $stmt->execute();
    $status = $stmt->get_result()->fetch_assoc();
    $status_id = $status['id'];
    $stmt->close();
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Update booking
        $update_query = "UPDATE bookings SET 
                        status_id = ?,
                        booking_datetime = ?,
                        duration_minutes = ?,
                        end_datetime = DATE_ADD(?, INTERVAL ? MINUTE),
                        reschedule_count = reschedule_count + 1,
                        admin_notes = CONCAT(IFNULL(admin_notes, ''), '\n[', NOW(), '] Rescheduled: ', ?)
                        WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param('ississi', $status_id, $new_datetime, $duration_minutes, $new_datetime, $duration_minutes, $reschedule_notes, $booking_id);
        $stmt->execute();
        $stmt->close();
        
        // Add activity log
        $log_query = "INSERT INTO booking_activity_logs 
                     (booking_id, user_id, activity_type, description) 
                     VALUES (?, ?, 'rescheduled', ?)";
        $description = "Booking rescheduled to {$new_datetime} with notes: {$reschedule_notes}";
        
        $stmt = $conn->prepare($log_query);
        $stmt->bind_param('iis', $booking_id, $_SESSION['id'], $description);
        $stmt->execute();
        $stmt->close();
        
        // Commit transaction
        $conn->commit();
        
        $success_message = "Booking rescheduled successfully";
        header("Location: view_booking.php?id=$booking_id&success=4");
        exit;
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        $error_message = "Error rescheduling booking: " . $e->getMessage();
    }
}

// Handle booking completion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_booking'])) {
    $completion_notes = trim($_POST['completion_notes']);
    
    // Get the completed status ID
    $status_query = "SELECT id FROM booking_statuses WHERE name = 'completed'";
    $stmt = $conn->prepare($status_query);
    $stmt->execute();
    $status = $stmt->get_result()->fetch_assoc();
    $status_id = $status['id'];
    $stmt->close();
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Update booking
        $update_query = "UPDATE bookings SET 
                        status_id = ?,
                        completed_by = ?,
                        completion_notes = ?
                        WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param('iisi', $status_id, $_SESSION['id'], $completion_notes, $booking_id);
        $stmt->execute();
        $stmt->close();
        
        // Add activity log
        $log_query = "INSERT INTO booking_activity_logs 
                     (booking_id, user_id, activity_type, description) 
                     VALUES (?, ?, 'completed', ?)";
        $description = "Booking marked as completed with notes: {$completion_notes}";
        
        $stmt = $conn->prepare($log_query);
        $stmt->bind_param('iis', $booking_id, $_SESSION['id'], $description);
        $stmt->execute();
        $stmt->close();
        
        // Commit transaction
        $conn->commit();
        
        $success_message = "Booking marked as completed";
        header("Location: view_booking.php?id=$booking_id&success=5");
        exit;
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        $error_message = "Error completing booking: " . $e->getMessage();
    }
}

// Handle document upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_document'])) {
    $document_name = trim($_POST['document_name']);
    $document_type = trim($_POST['document_type']);
    $is_private = isset($_POST['is_private']) ? 1 : 0;
    $notes = trim($_POST['document_notes']);
    
    $target_dir = "../../uploads/documents/";
    $file_extension = pathinfo($_FILES["document_file"]["name"], PATHINFO_EXTENSION);
    $file_name = $booking['reference_number'] . '_' . time() . '.' . $file_extension;
    $target_file = $target_dir . $file_name;
    $file_size = $_FILES["document_file"]["size"];
    
    // Check if directory exists, create it if not
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0755, true);
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Upload file
        if (move_uploaded_file($_FILES["document_file"]["tmp_name"], $target_file)) {
            // Save document record
            $query = "INSERT INTO booking_documents 
                     (booking_id, uploaded_by, document_name, document_path, document_type, file_size, is_private, notes) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $document_path = 'uploads/documents/' . $file_name;
            $stmt->bind_param('iisssiis', $booking_id, $_SESSION['id'], $document_name, $document_path, $document_type, $file_size, $is_private, $notes);
            $stmt->execute();
            $document_id = $conn->insert_id;
            $stmt->close();
            
            // Add activity log
            $log_query = "INSERT INTO booking_activity_logs 
                         (booking_id, user_id, activity_type, description) 
                         VALUES (?, ?, 'document_added', ?)";
            $description = "Document '{$document_name}' uploaded";
            
            $stmt = $conn->prepare($log_query);
            $stmt->bind_param('iis', $booking_id, $_SESSION['id'], $description);
            $stmt->execute();
            $stmt->close();
            
            // Commit transaction
            $conn->commit();
            
            $success_message = "Document uploaded successfully";
            header("Location: view_booking.php?id=$booking_id&success=6");
            exit;
        } else {
            throw new Exception("Error uploading file.");
        }
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        $error_message = "Error uploading document: " . $e->getMessage();
    }
}

// Handle success messages
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 1:
            $success_message = "Booking status updated successfully";
            break;
        case 2:
            $success_message = "Booking assigned successfully";
            break;
        case 3:
            $success_message = "Booking cancelled successfully";
            break;
        case 4:
            $success_message = "Booking rescheduled successfully";
            break;
        case 5:
            $success_message = "Booking marked as completed";
            break;
        case 6:
            $success_message = "Document uploaded successfully";
            break;
    }
}
?>

<style>
:root {
    --primary-color: #042167;
    --secondary-color: #858796;
    --success-color: #1cc88a;
    --danger-color: #e74a3b;
    --warning-color: #f6c23e;
    --light-color: #f8f9fc;
    --dark-color: #5a5c69;
    --border-color: #e3e6f0;
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
    flex-wrap: wrap;
}

.btn-back, .btn-edit, .btn-assign, .btn-reschedule, .btn-cancel, .btn-complete, .btn-upload {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    border-radius: 4px;
    font-weight: 500;
    text-decoration: none;
    cursor: pointer;
    border: none;
    font-size: 14px;
}

.btn-back {
    background-color: var(--light-color);
    color: var(--secondary-color);
    border: 1px solid var(--border-color);
}

.btn-edit, .btn-assign, .btn-upload {
    background-color: var(--primary-color);
    color: white;
}

.btn-reschedule {
    background-color: var(--warning-color);
    color: white;
}

.btn-cancel {
    background-color: var(--danger-color);
    color: white;
}

.btn-complete {
    background-color: var(--success-color);
    color: white;
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

.booking-info-grid {
    display: grid;
    grid-template-columns: repeat(12, 1fr);
    gap: 20px;
    margin-bottom: 20px;
}

.card {
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    padding: 0;
    overflow: hidden;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid var(--border-color);
    background-color: var(--light-color);
}

.card-header h3 {
    margin: 0;
    color: var(--primary-color);
    font-size: 1.3rem;
}

.card-body {
    padding: 20px;
}

.booking-details {
    grid-column: span 6;
}

.client-info {
    grid-column: span 3;
}

.consultant-info {
    grid-column: span 3;
}

.documents, .activity-logs {
    grid-column: span 12;
    margin-bottom: 20px;
}

.status-banner {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 15px;
    border-radius: 4px;
    margin-bottom: 20px;
    font-weight: 500;
}

.detail-group {
    margin-bottom: 20px;
}

.detail-row {
    display: flex;
    margin-bottom: 10px;
    font-size: 14px;
}

.detail-label {
    width: 150px;
    font-weight: 600;
    color: var(--dark-color);
}

.detail-value {
    flex: 1;
    color: var(--secondary-color);
}

.detail-divider {
    height: 1px;
    background-color: var(--border-color);
    margin: 20px 0;
}

.duration {
    font-size: 12px;
    color: var(--secondary-color);
    margin-left: 5px;
}

.payment-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}

.payment-badge.pending {
    background-color: rgba(246, 194, 62, 0.1);
    color: var(--warning-color);
}

.payment-badge.paid {
    background-color: rgba(28, 200, 138, 0.1);
    color: var(--success-color);
}

.payment-badge.failed {
    background-color: rgba(231, 74, 59, 0.1);
    color: var(--danger-color);
}

.meeting-link {
    color: var(--primary-color);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.not-set {
    color: var(--secondary-color);
    font-style: italic;
}

.note-text {
    white-space: pre-line;
}

.client-header, .consultant-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 20px;
}

.client-avatar, .consultant-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background-color: var(--primary-color);
    display: flex;
    align-items: center;
    justify-content: center;
}

.initials {
    color: white;
    font-weight: 600;
    font-size: 20px;
}

.client-name-email h4, .consultant-name-email h4 {
    margin: 0 0 5px;
    color: var(--dark-color);
    font-size: 16px;
}

.client-name-email p, .consultant-name-email p {
    margin: 0;
    color: var(--secondary-color);
    font-size: 14px;
}

.client-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.btn-client-action {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 8px 16px;
    border-radius: 4px;
    font-weight: 500;
    text-decoration: none;
    background-color: var(--light-color);
    color: var(--primary-color);
    border: 1px solid var(--border-color);
    text-align: center;
    font-size: 14px;
}

.view-link {
    color: var(--primary-color);
    text-decoration: none;
    font-size: 14px;
}

.no-documents, .no-activity {
    text-align: center;
    padding: 30px 20px;
    color: var(--secondary-color);
}

.no-documents i, .no-activity i {
    font-size: 36px;
    margin-bottom: 10px;
    opacity: 0.5;
}

.no-documents p, .no-activity p {
    margin: 0;
}

.documents-table {
    width: 100%;
    border-collapse: collapse;
}

.documents-table th, .documents-table td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid var(--border-color);
}

.documents-table th {
    font-weight: 600;
    color: var(--dark-color);
    background-color: var(--light-color);
}

.documents-table tr:last-child td {
    border-bottom: none;
}

.btn-view, .btn-download {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 4px;
    color: white;
    text-decoration: none;
    margin-right: 5px;
}

.btn-view {
    background-color: var(--primary-color);
}

.btn-download {
    background-color: var(--success-color);
}

.timeline {
    position: relative;
}

.timeline::before {
    content: '';
    position: absolute;
    top: 0;
    bottom: 0;
    left: 16px;
    width: 2px;
    background-color: var(--border-color);
}

.timeline-item {
    display: flex;
    margin-bottom: 20px;
    position: relative;
}

.timeline-marker {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: var(--primary-color);
    color: white;
    margin-right: 15px;
    z-index: 1;
}

.timeline-marker.status-changed {
    background-color: var(--primary-color);
}

.timeline-marker.assigned {
    background-color: var(--success-color);
}

.timeline-marker.cancelled {
    background-color: var(--danger-color);
}

.timeline-marker.rescheduled {
    background-color: var(--warning-color);
}

.timeline-marker.completed {
    background-color: var(--success-color);
}

.timeline-marker.document {
    background-color: var(--secondary-color);
}

.timeline-content {
    flex: 1;
    background-color: var(--light-color);
    border-radius: 4px;
    padding: 15px;
}

.timeline-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
}

.user-name {
    font-weight: 600;
    color: var(--dark-color);
}

.timestamp {
    color: var(--secondary-color);
    font-size: 12px;
}

.timeline-description {
    margin: 0;
    color: var(--secondary-color);
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0, 0, 0, 0.5);
}

.modal-content {
    background-color: white;
    margin: 10% auto;
    border-radius: 5px;
    width: 500px;
    max-width: 90%;
    animation: modalFadeIn 0.3s;
}

@keyframes modalFadeIn {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid var(--border-color);
}

.modal-header h3 {
    margin: 0;
    color: var(--primary-color);
}

.close-modal {
    font-size: 24px;
    font-weight: 700;
    color: var(--secondary-color);
    cursor: pointer;
}

.modal-body {
    padding: 20px;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
    color: var(--dark-color);
}

.form-group input, .form-group select, .form-group textarea {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    font-size: 14px;
}

.checkbox-group {
    display: flex;
    align-items: center;
}

.checkbox-group input {
    width: auto;
    margin-right: 8px;
}

.checkbox-group label {
    margin-bottom: 0;
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 20px;
}

.btn-cancel, .btn-submit {
    padding: 8px 16px;
    border-radius: 4px;
    font-weight: 500;
    cursor: pointer;
    border: none;
    font-size: 14px;
}

.btn-cancel {
    background-color: var(--light-color);
    color: var(--secondary-color);
    border: 1px solid var(--border-color);
}

.btn-submit {
    background-color: var(--primary-color);
    color: white;
}

.warning-message {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 15px;
    background-color: rgba(231, 74, 59, 0.1);
    border-radius: 4px;
    margin-bottom: 20px;
}

.warning-message i {
    color: var(--danger-color);
    font-size: 18px;
}

.warning-message p {
    margin: 0;
    color: var(--danger-color);
}

@media (max-width: 992px) {
    .booking-info-grid {
        grid-template-columns: 1fr;
    }
    
    .booking-details, .client-info, .consultant-info, .documents, .activity-logs {
        grid-column: 1;
    }
    
    .header-container {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .action-buttons {
        width: 100%;
        justify-content: flex-start;
    }
    
    .modal-content {
        width: 90%;
        margin: 20% auto;
    }
}

@media (max-width: 576px) {
    .detail-row {
        flex-direction: column;
        margin-bottom: 15px;
    }
    
    .detail-label {
        width: 100%;
        margin-bottom: 5px;
    }
    
    .documents-table {
        display: block;
        overflow-x: auto;
    }
    
    .client-header, .consultant-header {
        flex-direction: column;
        text-align: center;
    }
    
    .client-name-email, .consultant-name-email {
        text-align: center;
    }
}
</style>

<div class="content">
    <div class="header-container">
        <div>
            <h1>Booking Details</h1>
            <p>Reference: <?php echo htmlspecialchars($booking['reference_number']); ?></p>
        </div>
        <div class="action-buttons">
            <a href="bookings.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Back to Bookings
            </a>
            
            <?php if (in_array($booking['status_name'], ['pending', 'confirmed'])): ?>
                <button type="button" class="btn-edit" onclick="openStatusModal()">
                    <i class="fas fa-edit"></i> Update Status
                </button>
                
                <?php if (empty($booking['consultant_name'])): ?>
                    <button type="button" class="btn-assign" onclick="openAssignModal()">
                        <i class="fas fa-user-plus"></i> Assign Consultant
                    </button>
                <?php endif; ?>
                
                <button type="button" class="btn-reschedule" onclick="openRescheduleModal()">
                    <i class="fas fa-calendar-alt"></i> Reschedule
                </button>
                
                <button type="button" class="btn-cancel" onclick="openCancelModal()">
                    <i class="fas fa-times"></i> Cancel Booking
                </button>
            <?php endif; ?>
            
            <?php if ($booking['status_name'] === 'confirmed'): ?>
                <button type="button" class="btn-complete" onclick="openCompleteModal()">
                    <i class="fas fa-check"></i> Mark as Completed
                </button>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <div class="booking-info-grid">
        <!-- Booking Details Section -->
        <div class="card booking-details">
            <div class="card-header">
                <h3>Booking Information</h3>
            </div>
            <div class="card-body">
                <div class="status-banner" style="background-color: <?php echo $booking['status_color']; ?>20; color: <?php echo $booking['status_color']; ?>;">
                    <i class="fas fa-info-circle"></i>
                    <span>Current Status: <?php echo ucfirst(str_replace('_', ' ', $booking['status_name'])); ?></span>
                </div>
                
                <div class="detail-group">
                    <div class="detail-row">
                        <div class="detail-label">Created On:</div>
                        <div class="detail-value"><?php echo date('F d, Y \a\t h:i A', strtotime($booking['created_at'])); ?></div>
                    </div>
                    
                    <div class="detail-row">
                        <div class="detail-label">Appointment Date:</div>
                        <div class="detail-value"><?php echo date('F d, Y', strtotime($booking['booking_datetime'])); ?></div>
                    </div>
                    
                    <div class="detail-row">
                        <div class="detail-label">Appointment Time:</div>
                        <div class="detail-value">
                            <?php echo date('h:i A', strtotime($booking['booking_datetime'])); ?> - 
                            <?php echo date('h:i A', strtotime($booking['end_datetime'])); ?>
                            <span class="duration">(<?php echo $booking['duration_minutes']; ?> minutes)</span>
                        </div>
                    </div>
                    
                    <div class="detail-row">
                        <div class="detail-label">Timezone:</div>
                        <div class="detail-value"><?php echo htmlspecialchars($booking['time_zone']); ?></div>
                    </div>
                    
                    <?php if ($booking['reschedule_count'] > 0): ?>
                        <div class="detail-row">
                            <div class="detail-label">Rescheduled:</div>
                            <div class="detail-value"><?php echo $booking['reschedule_count']; ?> time(s)</div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="detail-divider"></div>
                
                <div class="detail-group">
                    <div class="detail-row">
                        <div class="detail-label">Visa Type:</div>
                        <div class="detail-value"><?php echo htmlspecialchars($booking['visa_type']); ?></div>
                    </div>
                    
                    <div class="detail-row">
                        <div class="detail-label">Country:</div>
                        <div class="detail-value"><?php echo htmlspecialchars($booking['country_name']); ?></div>
                    </div>
                    
                    <div class="detail-row">
                        <div class="detail-label">Service:</div>
                        <div class="detail-value"><?php echo htmlspecialchars($booking['service_name']); ?></div>
                    </div>
                    
                    <div class="detail-row">
                        <div class="detail-label">Consultation Mode:</div>
                        <div class="detail-value"><?php echo htmlspecialchars($booking['consultation_mode']); ?></div>
                    </div>
                    
                    <?php if ($booking['consultation_mode'] === 'Online'): ?>
                        <div class="detail-row">
                            <div class="detail-label">Meeting Link:</div>
                            <div class="detail-value">
                                <?php if (!empty($booking['meeting_link'])): ?>
                                    <a href="<?php echo htmlspecialchars($booking['meeting_link']); ?>" target="_blank" class="meeting-link">
                                        <i class="fas fa-video"></i> Join Meeting
                                    </a>
                                <?php else: ?>
                                    <span class="not-set">Not set yet</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php elseif ($booking['consultation_mode'] === 'In-Person'): ?>
                        <div class="detail-row">
                            <div class="detail-label">Location:</div>
                            <div class="detail-value">
                                <?php if (!empty($booking['location'])): ?>
                                    <?php echo nl2br(htmlspecialchars($booking['location'])); ?>
                                <?php else: ?>
                                    <span class="not-set">Not set yet</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="detail-divider"></div>
                
                <div class="detail-group">
                    <div class="detail-row">
                        <div class="detail-label">Price:</div>
                        <div class="detail-value">
                            $<?php echo number_format($booking['base_price'], 2); ?>
                            <?php if (!empty($booking['additional_fee']) && $booking['additional_fee'] > 0): ?>
                                + $<?php echo number_format($booking['additional_fee'], 2); ?> (additional fee)
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="detail-row">
                        <div class="detail-label">Total:</div>
                        <div class="detail-value">$<?php echo number_format($booking['total_price'], 2); ?></div>
                    </div>
                    
                    <div class="detail-row">
                        <div class="detail-label">Payment Status:</div>
                        <div class="detail-value">
                            <?php if (empty($booking['payment_status'])): ?>
                                <span class="payment-badge pending">No Payment</span>
                            <?php else: ?>
                                <span class="payment-badge <?php echo strtolower($booking['payment_status']); ?>">
                                    <?php echo ucfirst($booking['payment_status']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if (!empty($booking['payment_status'])): ?>
                        <div class="detail-row">
                            <div class="detail-label">Payment Method:</div>
                            <div class="detail-value"><?php echo ucfirst(str_replace('_', ' ', $booking['payment_method'])); ?></div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($booking['client_notes'])): ?>
                    <div class="detail-divider"></div>
                    
                    <div class="detail-group">
                        <div class="detail-row">
                            <div class="detail-label">Client Notes:</div>
                            <div class="detail-value note-text"><?php echo nl2br(htmlspecialchars($booking['client_notes'])); ?></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Client Info Section -->
        <div class="card client-info">
            <div class="card-header">
                <h3>Client Information</h3>
                <a href="view_client.php?id=<?php echo $booking['client_id']; ?>" class="view-link">View Profile</a>
            </div>
            <div class="card-body">
                <div class="client-header">
                    <div class="client-avatar">
                        <div class="initials">
                            <?php
                            $name_parts = explode(' ', $booking['client_name']);
                            echo substr($name_parts[0], 0, 1) . (isset($name_parts[1]) ? substr($name_parts[1], 0, 1) : '');
                            ?>
                        </div>
                    </div>
                    <div class="client-name-email">
                        <h4><?php echo htmlspecialchars($booking['client_name']); ?></h4>
                        <p><?php echo htmlspecialchars($booking['client_email']); ?></p>
                    </div>
                </div>
                
                <div class="client-actions">
                    <a href="client_bookings.php?client_id=<?php echo $booking['client_id']; ?>" class="btn-client-action">
                        <i class="fas fa-calendar-alt"></i> View All Bookings
                    </a>
                    <a href="send_message.php?client_id=<?php echo $booking['client_id']; ?>" class="btn-client-action">
                        <i class="fas fa-envelope"></i> Send Message
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Consultant Info Section -->
        <div class="card consultant-info">
            <div class="card-header">
                <h3>Consultant Information</h3>
                <?php if (!empty($booking['team_member_id'])): ?>
                    <a href="view_team_member.php?id=<?php echo $booking['team_member_id']; ?>" class="view-link">View Profile</a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="consultant-header">
                    <div class="consultant-avatar">
                        <div class="initials">
                            <?php
                            $name_parts = explode(' ', $booking['consultant_name']);
                            echo substr($name_parts[0], 0, 1) . (isset($name_parts[1]) ? substr($name_parts[1], 0, 1) : '');
                            ?>
                        </div>
                    </div>
                    <div class="consultant-name-email">
                        <h4><?php echo htmlspecialchars($booking['consultant_name']); ?></h4>
                        <p><?php echo htmlspecialchars($booking['consultant_role']); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Documents Section -->
    <div class="card documents">
        <div class="card-header">
            <h3>Documents</h3>
            <button type="button" class="btn-upload" onclick="openUploadModal()">
                <i class="fas fa-upload"></i> Upload Document
            </button>
        </div>
        <div class="card-body">
            <?php if (empty($documents)): ?>
                <div class="no-documents">
                    <i class="fas fa-file-alt"></i>
                    <p>No documents have been uploaded yet</p>
                </div>
            <?php else: ?>
                <table class="documents-table">
                    <thead>
                        <tr>
                            <th>Document Name</th>
                            <th>Type</th>
                            <th>Uploaded By</th>
                            <th>Date</th>
                            <th>Size</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($documents as $doc): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($doc['document_name']); ?></td>
                                <td><?php echo htmlspecialchars($doc['document_type']); ?></td>
                                <td><?php echo htmlspecialchars($doc['uploaded_by_name']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($doc['created_at'])); ?></td>
                                <td><?php echo formatFileSize($doc['file_size']); ?></td>
                                <td>
                                    <a href="../../<?php echo $doc['document_path']; ?>" target="_blank" class="btn-view">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="../../<?php echo $doc['document_path']; ?>" download class="btn-download">
                                        <i class="fas fa-download"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Activity Logs Section -->
    <div class="card activity-logs">
        <div class="card-header">
            <h3>Activity Log</h3>
        </div>
        <div class="card-body">
            <?php if (empty($activity_logs)): ?>
                <div class="no-activity">
                    <i class="fas fa-history"></i>
                    <p>No activity recorded yet</p>
                </div>
            <?php else: ?>
                <div class="timeline">
                    <?php foreach ($activity_logs as $log): ?>
                        <div class="timeline-item">
                            <div class="timeline-marker 
                                <?php
                                switch ($log['activity_type']) {
                                    case 'status_changed': echo 'status-changed'; break;
                                    case 'assigned': echo 'assigned'; break;
                                    case 'cancelled': echo 'cancelled'; break;
                                    case 'rescheduled': echo 'rescheduled'; break;
                                    case 'completed': echo 'completed'; break;
                                    case 'document_added': echo 'document'; break;
                                    default: echo ''; break;
                                }
                                ?>">
                                <i class="fas 
                                <?php
                                switch ($log['activity_type']) {
                                    case 'status_changed': echo 'fa-sync-alt'; break;
                                    case 'assigned': echo 'fa-user-check'; break;
                                    case 'cancelled': echo 'fa-ban'; break;
                                    case 'rescheduled': echo 'fa-calendar-alt'; break;
                                    case 'completed': echo 'fa-check-circle'; break;
                                    case 'document_added': echo 'fa-file-upload'; break;
                                    default: echo 'fa-history'; break;
                                }
                                ?>"></i>
                            </div>
                            <div class="timeline-content">
                                <div class="timeline-header">
                                    <span class="user-name"><?php echo htmlspecialchars($log['user_name']); ?></span>
                                    <span class="timestamp"><?php echo date('M d, Y h:i A', strtotime($log['created_at'])); ?></span>
                                </div>
                                <p class="timeline-description"><?php echo htmlspecialchars($log['description']); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modals -->
<!-- Status Update Modal -->
<div id="statusModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Update Booking Status</h3>
            <span class="close-modal" onclick="closeStatusModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form action="" method="post">
                <div class="form-group">
                    <label for="status_id">Status:</label>
                    <select name="status_id" id="status_id" required>
                        <?php foreach ($booking_statuses as $status): ?>
                            <option value="<?php echo $status['id']; ?>" 
                                <?php if ($status['id'] == $booking['status_id']) echo 'selected'; ?>>
                                <?php echo ucfirst(str_replace('_', ' ', $status['name'])); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="admin_notes">Notes:</label>
                    <textarea name="admin_notes" id="admin_notes" rows="3" placeholder="Add notes about this status change"></textarea>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeStatusModal()">Cancel</button>
                    <button type="submit" name="update_status" class="btn-submit">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Assign Consultant Modal -->
<div id="assignModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Assign Consultant</h3>
            <span class="close-modal" onclick="closeAssignModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form action="" method="post">
                <div class="form-group">
                    <label for="consultant_id">Select Consultant:</label>
                    <select name="consultant_id" id="consultant_id" required>
                        <option value="">-- Select Consultant --</option>
                        <?php foreach ($team_members as $member): ?>
                            <option value="<?php echo $member['id']; ?>">
                                <?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name'] . ' (' . $member['role'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeAssignModal()">Cancel</button>
                    <button type="submit" name="assign_consultant" class="btn-submit">Assign Consultant</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Cancel Booking Modal -->
<div id="cancelModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Cancel Booking</h3>
            <span class="close-modal" onclick="closeCancelModal()">&times;</span>
        </div>
        <div class="modal-body">
            <div class="warning-message">
                <i class="fas fa-exclamation-triangle"></i>
                <p>Are you sure you want to cancel this booking? This action cannot be undone.</p>
            </div>
            <form action="" method="post">
                <div class="form-group">
                    <label for="cancellation_reason">Cancellation Reason:</label>
                    <textarea name="cancellation_reason" id="cancellation_reason" rows="3" required placeholder="Please provide a reason for cancellation"></textarea>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeCancelModal()">No, Keep It</button>
                    <button type="submit" name="cancel_booking" class="btn-submit">Yes, Cancel Booking</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reschedule Modal -->
<div id="rescheduleModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Reschedule Booking</h3>
            <span class="close-modal" onclick="closeRescheduleModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form action="" method="post">
                <div class="form-group">
                    <label for="new_datetime">New Date and Time:</label>
                    <input type="datetime-local" name="new_datetime" id="new_datetime" required>
                </div>
                <div class="form-group">
                    <label for="duration_minutes">Duration (minutes):</label>
                    <input type="number" name="duration_minutes" id="duration_minutes" value="<?php echo $booking['duration_minutes']; ?>" min="15" step="15" required>
                </div>
                <div class="form-group">
                    <label for="reschedule_notes">Reschedule Notes:</label>
                    <textarea name="reschedule_notes" id="reschedule_notes" rows="3" required placeholder="Add notes about this reschedule"></textarea>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeRescheduleModal()">Cancel</button>
                    <button type="submit" name="reschedule_booking" class="btn-submit">Reschedule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Complete Booking Modal -->
<div id="completeModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Mark Booking as Completed</h3>
            <span class="close-modal" onclick="closeCompleteModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form action="" method="post">
                <div class="form-group">
                    <label for="completion_notes">Completion Notes:</label>
                    <textarea name="completion_notes" id="completion_notes" rows="3" required placeholder="Add notes about this completion"></textarea>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeCompleteModal()">Cancel</button>
                    <button type="submit" name="complete_booking" class="btn-submit">Mark as Completed</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Upload Document Modal -->
<div id="uploadModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Upload Document</h3>
            <span class="close-modal" onclick="closeUploadModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form action="" method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="document_name">Document Name:</label>
                    <input type="text" name="document_name" id="document_name" required>
                </div>
                <div class="form-group">
                    <label for="document_type">Document Type:</label>
                    <select name="document_type" id="document_type" required>
                        <option value="">-- Select Type --</option>
                        <option value="passport">Passport</option>
                        <option value="visa">Visa</option>
                        <option value="application_form">Application Form</option>
                        <option value="supporting_document">Supporting Document</option>
                        <option value="payment_receipt">Payment Receipt</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="document_file">Select File:</label>
                    <input type="file" name="document_file" id="document_file" required>
                </div>
                <div class="form-group checkbox-group">
                    <input type="checkbox" name="is_private" id="is_private">
                    <label for="is_private">Private (only visible to admin and team)</label>
                </div>
                <div class="form-group">
                    <label for="document_notes">Notes:</label>
                    <textarea name="document_notes" id="document_notes" rows="3" placeholder="Add notes about this document"></textarea>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeUploadModal()">Cancel</button>
                    <button type="submit" name="upload_document" class="btn-submit">Upload Document</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Helper Functions -->
<?php
// Helper function to format file size
function formatFileSize($bytes) {
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 2) . " MB";
    } elseif ($bytes >= 1024) {
        return round($bytes / 1024, 2) . " KB";
    } else {
        return $bytes . " bytes";
    }
}
?>

<!-- JavaScript for Modals -->
<script>
    // Status Modal
    function openStatusModal() {
        document.getElementById('statusModal').style.display = 'block';
    }
    function closeStatusModal() {
        document.getElementById('statusModal').style.display = 'none';
    }
    
    // Assign Modal
    function openAssignModal() {
        document.getElementById('assignModal').style.display = 'block';
    }
    function closeAssignModal() {
        document.getElementById('assignModal').style.display = 'none';
    }
    
    // Cancel Modal
    function openCancelModal() {
        document.getElementById('cancelModal').style.display = 'block';
    }
    function closeCancelModal() {
        document.getElementById('cancelModal').style.display = 'none';
    }
    
    // Reschedule Modal
    function openRescheduleModal() {
        document.getElementById('rescheduleModal').style.display = 'block';
        // Set default value to current datetime
        const currentDate = new Date('<?php echo $booking['booking_datetime']; ?>');
        const localDateTime = new Date(currentDate.getTime() - currentDate.getTimezoneOffset() * 60000)
            .toISOString()
            .slice(0, 16);
        document.getElementById('new_datetime').value = localDateTime;
    }
    function closeRescheduleModal() {
        document.getElementById('rescheduleModal').style.display = 'none';
    }
    
    // Complete Modal
    function openCompleteModal() {
        document.getElementById('completeModal').style.display = 'block';
    }
    function closeCompleteModal() {
        document.getElementById('completeModal').style.display = 'none';
    }
    
    // Upload Modal
    function openUploadModal() {
        document.getElementById('uploadModal').style.display = 'block';
    }
    function closeUploadModal() {
        document.getElementById('uploadModal').style.display = 'none';
    }
    
    // Close modal when clicking outside of it
    window.onclick = function(event) {
        const modals = document.getElementsByClassName('modal');
        for (let i = 0; i < modals.length; i++) {
            if (event.target == modals[i]) {
                modals[i].style.display = 'none';
            }
        }
    }
</script>

<?php require_once 'includes/footer.php'; ?>

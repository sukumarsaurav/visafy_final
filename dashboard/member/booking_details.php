<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

$page_title = "Booking Details";
$page_specific_css = "../assets/css/booking_details.css";
require_once 'includes/header.php';

// Check if booking ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$booking_id = (int)$_GET['id'];

// Get the current team member id
$team_member_id = null;
$team_role_query = "SELECT tm.id FROM team_members tm WHERE tm.user_id = ?";
$stmt = $conn->prepare($team_role_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$team_result = $stmt->get_result();
if ($team_result->num_rows > 0) {
    $team_member_id = $team_result->fetch_assoc()['id'];
}
$stmt->close();

if (!$team_member_id) {
    // Not a team member
    header("Location: index.php");
    exit;
}

// Get booking details - ensure it's assigned to this team member
$query = "SELECT b.*, bs.name as status_name, bs.color as status_color,
          CONCAT(u.first_name, ' ', u.last_name) as client_name, u.email as client_email, 
          u.id as client_id,
          vs.visa_service_id, v.visa_type, c.country_name, st.service_name,
          cm.mode_name as consultation_mode,
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
          LEFT JOIN booking_payments bp ON b.id = bp.booking_id
          WHERE b.id = ? AND b.team_member_id = ? AND b.deleted_at IS NULL";
$stmt = $conn->prepare($query);
$stmt->bind_param('ii', $booking_id, $team_member_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Booking not found or not assigned to this team member
    $stmt->close();
    header("Location: index.php");
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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update Meeting Link
    if (isset($_POST['update_meeting_link'])) {
        $meeting_link = trim($_POST['meeting_link']);
        
        $update_query = "UPDATE bookings SET meeting_link = ? WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param('si', $meeting_link, $booking_id);
        
        if ($stmt->execute()) {
            // Add activity log
            $log_query = "INSERT INTO booking_activity_logs 
                         (booking_id, user_id, activity_type, description) 
                         VALUES (?, ?, 'updated', 'Updated meeting link')";
            $log_stmt = $conn->prepare($log_query);
            $log_stmt->bind_param('ii', $booking_id, $user_id);
            $log_stmt->execute();
            $log_stmt->close();
            
            $success_message = "Meeting link updated successfully.";
            // Refresh booking data
            $booking['meeting_link'] = $meeting_link;
        } else {
            $error_message = "Error updating meeting link: " . $stmt->error;
        }
        $stmt->close();
    }
    
    // Update Status
    if (isset($_POST['update_status'])) {
        $status_id = (int)$_POST['status_id'];
        
        // Validate status exists
        if (!isset($booking_statuses[$status_id])) {
            $error_message = "Invalid status selected.";
        } else {
            $update_query = "UPDATE bookings SET status_id = ? WHERE id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param('ii', $status_id, $booking_id);
            
            if ($stmt->execute()) {
                // Add activity log
                $new_status_name = $booking_statuses[$status_id]['name'];
                $log_query = "INSERT INTO booking_activity_logs 
                             (booking_id, user_id, activity_type, description) 
                             VALUES (?, ?, 'status_changed', ?)";
                $description = "Changed status to " . ucfirst(str_replace('_', ' ', $new_status_name));
                $log_stmt = $conn->prepare($log_query);
                $log_stmt->bind_param('iis', $booking_id, $user_id, $description);
                $log_stmt->execute();
                $log_stmt->close();
                
                $success_message = "Booking status updated successfully.";
                
                // If status is 'completed', update the completed_by field
                if ($new_status_name === 'completed') {
                    $complete_query = "UPDATE bookings SET completed_by = ?, completion_notes = ? WHERE id = ?";
                    $completion_notes = isset($_POST['completion_notes']) ? trim($_POST['completion_notes']) : '';
                    $complete_stmt = $conn->prepare($complete_query);
                    $complete_stmt->bind_param('isi', $user_id, $completion_notes, $booking_id);
                    $complete_stmt->execute();
                    $complete_stmt->close();
                }
                
                // Refresh booking data
                $booking['status_id'] = $status_id;
                $booking['status_name'] = $new_status_name;
                $booking['status_color'] = $booking_statuses[$status_id]['color'];
            } else {
                $error_message = "Error updating status: " . $stmt->error;
            }
            $stmt->close();
        }
    }
    
    // Add Notes
    if (isset($_POST['add_notes'])) {
        $admin_notes = trim($_POST['admin_notes']);
        
        $update_query = "UPDATE bookings SET admin_notes = CONCAT(IFNULL(admin_notes, ''), ?) WHERE id = ?";
        $notes_with_timestamp = "\n\n[" . date('Y-m-d H:i:s') . " - " . $_SESSION['first_name'] . " " . $_SESSION['last_name'] . "]\n" . $admin_notes;
        
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param('si', $notes_with_timestamp, $booking_id);
        
        if ($stmt->execute()) {
            // Add activity log
            $log_query = "INSERT INTO booking_activity_logs 
                         (booking_id, user_id, activity_type, description) 
                         VALUES (?, ?, 'updated', 'Added notes to booking')";
            $log_stmt = $conn->prepare($log_query);
            $log_stmt->bind_param('ii', $booking_id, $user_id);
            $log_stmt->execute();
            $log_stmt->close();
            
            $success_message = "Notes added successfully.";
            
            // Refresh booking data to include new notes
            $refresh_query = "SELECT admin_notes FROM bookings WHERE id = ?";
            $refresh_stmt = $conn->prepare($refresh_query);
            $refresh_stmt->bind_param('i', $booking_id);
            $refresh_stmt->execute();
            $refresh_result = $refresh_stmt->get_result();
            if ($refresh_row = $refresh_result->fetch_assoc()) {
                $booking['admin_notes'] = $refresh_row['admin_notes'];
            }
            $refresh_stmt->close();
        } else {
            $error_message = "Error adding notes: " . $stmt->error;
        }
        $stmt->close();
    }
    
    // Upload Document
    if (isset($_POST['upload_document']) && isset($_FILES['document'])) {
        $document = $_FILES['document'];
        $document_name = trim($_POST['document_name']);
        $document_type = trim($_POST['document_type']);
        $is_private = isset($_POST['is_private']) ? 1 : 0;
        $notes = trim($_POST['document_notes']);
        
        // Validate file upload
        if ($document['error'] === 0) {
            $file_size = $document['size'];
            $file_tmp = $document['tmp_name'];
            $file_name = basename($document['name']);
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            // Define allowed file extensions
            $allowed_extensions = array('pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png');
            
            if (in_array($file_ext, $allowed_extensions)) {
                // Generate unique file name
                $new_file_name = 'booking_' . $booking_id . '_' . time() . '.' . $file_ext;
                $upload_path = '../../uploads/documents/' . $new_file_name;
                
                if (move_uploaded_file($file_tmp, $upload_path)) {
                    // Save document record in database
                    $doc_query = "INSERT INTO booking_documents 
                                 (booking_id, uploaded_by, document_name, document_path, document_type, file_size, is_private, notes) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    $doc_stmt = $conn->prepare($doc_query);
                    $doc_stmt->bind_param('issssiis', $booking_id, $user_id, $document_name, $new_file_name, $document_type, $file_size, $is_private, $notes);
                    
                    if ($doc_stmt->execute()) {
                        // Add activity log
                        $log_query = "INSERT INTO booking_activity_logs 
                                     (booking_id, user_id, activity_type, description) 
                                     VALUES (?, ?, 'document_added', ?)";
                        $description = "Uploaded document: " . $document_name;
                        $log_stmt = $conn->prepare($log_query);
                        $log_stmt->bind_param('iis', $booking_id, $user_id, $description);
                        $log_stmt->execute();
                        $log_stmt->close();
                        
                        $success_message = "Document uploaded successfully.";
                        
                        // Refresh documents list
                        $refresh_query = "SELECT bd.*, CONCAT(u.first_name, ' ', u.last_name) as uploaded_by_name
                                         FROM booking_documents bd
                                         JOIN users u ON bd.uploaded_by = u.id
                                         WHERE bd.booking_id = ?
                                         ORDER BY bd.created_at DESC";
                        $refresh_stmt = $conn->prepare($refresh_query);
                        $refresh_stmt->bind_param('i', $booking_id);
                        $refresh_stmt->execute();
                        $documents_result = $refresh_stmt->get_result();
                        $documents = [];
                        
                        if ($documents_result && $documents_result->num_rows > 0) {
                            while ($row = $documents_result->fetch_assoc()) {
                                $documents[] = $row;
                            }
                        }
                        $refresh_stmt->close();
                    } else {
                        $error_message = "Error saving document record: " . $doc_stmt->error;
                    }
                    $doc_stmt->close();
                } else {
                    $error_message = "Error uploading document.";
                }
            } else {
                $error_message = "Invalid file type. Allowed types: " . implode(', ', $allowed_extensions);
            }
        } else {
            $error_message = "Error uploading document: " . $document['error'];
        }
    }
}
?>

<div class="content">
    <div class="header-container">
        <div>
            <h1>Booking Details</h1>
            <p>Reference: <?php echo htmlspecialchars($booking['reference_number']); ?></p>
        </div>
        <div class="action-buttons">
            <a href="index.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            
            <?php if (in_array($booking['status_name'], ['pending', 'confirmed'])): ?>
                <button type="button" class="btn-edit" onclick="openStatusModal()">
                    <i class="fas fa-edit"></i> Update Status
                </button>
                
                <button type="button" class="btn-add-note" onclick="openNotesModal()">
                    <i class="fas fa-sticky-note"></i> Add Notes
                </button>
                
                <?php if ($booking['consultation_mode'] === 'Virtual'): ?>
                    <button type="button" class="btn-meeting" onclick="openMeetingLinkModal()">
                        <i class="fas fa-video"></i> Update Meeting Link
                    </button>
                <?php endif; ?>
            <?php endif; ?>
            
            <button type="button" class="btn-document" onclick="openDocumentModal()">
                <i class="fas fa-file-upload"></i> Upload Document
            </button>
            
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
                    
                    <?php if ($booking['consultation_mode'] === 'Virtual' && !empty($booking['meeting_link'])): ?>
                        <div class="detail-row meeting-link-row">
                            <div class="detail-label">Meeting Link:</div>
                            <div class="detail-value">
                                <a href="<?php echo htmlspecialchars($booking['meeting_link']); ?>" target="_blank" class="meeting-link">
                                    <?php echo htmlspecialchars($booking['meeting_link']); ?>
                                    <i class="fas fa-external-link-alt"></i>
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($booking['consultation_mode'] === 'In-Person' && !empty($booking['location'])): ?>
                        <div class="detail-row">
                            <div class="detail-label">Location:</div>
                            <div class="detail-value"><?php echo nl2br(htmlspecialchars($booking['location'])); ?></div>
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
                
                <?php if (!empty($booking['admin_notes'])): ?>
                    <div class="detail-divider"></div>
                    
                    <div class="detail-group">
                        <div class="detail-row">
                            <div class="detail-label">Team Notes:</div>
                            <div class="detail-value note-text"><?php echo nl2br(htmlspecialchars($booking['admin_notes'])); ?></div>
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
                            $name_parts = explode(' ', $booking['client_name'] ?? '');
                            echo substr($name_parts[0] ?? '', 0, 1) . (isset($name_parts[1]) ? substr($name_parts[1], 0, 1) : '');
                            ?>
                        </div>
                    </div>
                    <div class="client-name-email">
                        <h4><?php echo htmlspecialchars($booking['client_name'] ?? ''); ?></h4>
                        <p><?php echo htmlspecialchars($booking['client_email'] ?? ''); ?></p>
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
        
        <!-- Documents Section -->
        <div class="card documents-section">
            <div class="card-header">
                <h3>Documents</h3>
                <button type="button" class="btn-add-document" onclick="openDocumentModal()">
                    <i class="fas fa-plus"></i> Add Document
                </button>
            </div>
            <div class="card-body">
                <?php if (empty($documents)): ?>
                    <div class="empty-state">
                        <i class="fas fa-file-alt"></i>
                        <p>No documents attached to this booking</p>
                    </div>
                <?php else: ?>
                    <div class="documents-list">
                        <?php foreach ($documents as $document): ?>
                            <div class="document-item">
                                <div class="document-icon">
                                    <?php
                                    $file_ext = pathinfo($document['document_path'], PATHINFO_EXTENSION);
                                    $icon_class = 'fa-file';
                                    
                                    if (in_array($file_ext, ['pdf'])) {
                                        $icon_class = 'fa-file-pdf';
                                    } elseif (in_array($file_ext, ['doc', 'docx'])) {
                                        $icon_class = 'fa-file-word';
                                    } elseif (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                                        $icon_class = 'fa-file-image';
                                    }
                                    ?>
                                    <i class="fas <?php echo $icon_class; ?>"></i>
                                </div>
                                <div class="document-details">
                                    <div class="document-name">
                                        <?php echo htmlspecialchars($document['document_name']); ?>
                                        <?php if ($document['is_private']): ?>
                                            <span class="private-badge" title="Only visible to team members">Private</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="document-meta">
                                        <span class="document-type"><?php echo htmlspecialchars($document['document_type']); ?></span>
                                        <span class="upload-date">Uploaded: <?php echo date('M d, Y', strtotime($document['created_at'])); ?></span>
                                        <span class="uploader">By: <?php echo htmlspecialchars($document['uploaded_by_name']); ?></span>
                                    </div>
                                    <?php if (!empty($document['notes'])): ?>
                                        <div class="document-notes">
                                            <?php echo nl2br(htmlspecialchars($document['notes'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="document-actions">
                                    <a href="../../uploads/documents/<?php echo $document['document_path']; ?>" target="_blank" class="document-action view">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <a href="../../uploads/documents/<?php echo $document['document_path']; ?>" download class="document-action download">
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Activity Logs Section -->
        <div class="card activity-logs">
            <div class="card-header">
                <h3>Activity Logs</h3>
            </div>
            <div class="card-body">
                <?php if (empty($activity_logs)): ?>
                    <div class="empty-state">
                        <i class="fas fa-history"></i>
                        <p>No activity recorded for this booking</p>
                    </div>
                <?php else: ?>
                    <div class="activity-list">
                        <?php foreach ($activity_logs as $log): ?>
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <?php
                                    $icon_class = 'fa-info-circle';
                                    
                                    switch ($log['activity_type']) {
                                        case 'created':
                                            $icon_class = 'fa-plus-circle';
                                            break;
                                        case 'updated':
                                            $icon_class = 'fa-edit';
                                            break;
                                        case 'status_changed':
                                            $icon_class = 'fa-exchange-alt';
                                            break;
                                        case 'document_added':
                                            $icon_class = 'fa-file-upload';
                                            break;
                                    }
                                    ?>
                                    <i class="fas <?php echo $icon_class; ?>"></i>
                                </div>
                                <div class="activity-details">
                                    <div class="activity-type"><?php echo ucfirst(str_replace('_', ' ', $log['activity_type'])); ?></div>
                                    <div class="activity-description"><?php echo htmlspecialchars($log['description']); ?></div>
                                    <div class="activity-user">By: <?php echo htmlspecialchars($log['user_name']); ?></div>
                                    <div class="activity-date"><?php echo date('F d, Y \a\t h:i A', strtotime($log['created_at'])); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Meeting Link Modal -->
<div class="modal" id="meetingLinkModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Update Meeting Link</h3>
                <button type="button" class="close" onclick="closeModal('meetingLinkModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form action="" method="POST">
                    <div class="form-group">
                        <label for="meeting_link">Meeting Link</label>
                        <input type="url" name="meeting_link" id="meeting_link" class="form-control" 
                               value="<?php echo htmlspecialchars($booking['meeting_link'] ?? ''); ?>" 
                               placeholder="https://zoom.us/j/123456789">
                        <small class="form-text">Enter the full URL for the virtual meeting (Zoom, Teams, etc.)</small>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="button" class="btn cancel-btn" onclick="closeModal('meetingLinkModal')">Cancel</button>
                        <button type="submit" name="update_meeting_link" class="btn submit-btn">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Update Status Modal -->
<div class="modal" id="statusModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Update Booking Status</h3>
                <button type="button" class="close" onclick="closeModal('statusModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form action="" method="POST">
                    <div class="form-group">
                        <label for="status_id">Select New Status</label>
                        <select name="status_id" id="status_id" class="form-control" required>
                            <?php foreach ($booking_statuses as $status): ?>
                                <option value="<?php echo $status['id']; ?>" 
                                        <?php echo ($status['id'] == $booking['status_id']) ? 'selected' : ''; ?>>
                                    <?php echo ucfirst(str_replace('_', ' ', $status['name'])); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="button" class="btn cancel-btn" onclick="closeModal('statusModal')">Cancel</button>
                        <button type="submit" name="update_status" class="btn submit-btn">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Complete Booking Modal -->
<div class="modal" id="completeModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Mark Booking as Completed</h3>
                <button type="button" class="close" onclick="closeModal('completeModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form action="" method="POST">
                    <div class="form-group">
                        <label for="completion_notes">Completion Notes</label>
                        <textarea name="completion_notes" id="completion_notes" class="form-control" rows="4"
                                  placeholder="Enter any notes about the consultation session"><?php echo htmlspecialchars($booking['completion_notes'] ?? ''); ?></textarea>
                    </div>
                    
                    <input type="hidden" name="status_id" value="<?php 
                        // Find completed status ID
                        foreach ($booking_statuses as $status) {
                            if ($status['name'] === 'completed') {
                                echo $status['id'];
                                break;
                            }
                        }
                    ?>">
                    
                    <div class="form-buttons">
                        <button type="button" class="btn cancel-btn" onclick="closeModal('completeModal')">Cancel</button>
                        <button type="submit" name="update_status" class="btn submit-btn">Mark as Completed</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Add Notes Modal -->
<div class="modal" id="notesModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Add Notes</h3>
                <button type="button" class="close" onclick="closeModal('notesModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form action="" method="POST">
                    <div class="form-group">
                        <label for="admin_notes">Notes</label>
                        <textarea name="admin_notes" id="admin_notes" class="form-control" rows="6"
                                  placeholder="Enter notes about this booking (only visible to team members)" required></textarea>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="button" class="btn cancel-btn" onclick="closeModal('notesModal')">Cancel</button>
                        <button type="submit" name="add_notes" class="btn submit-btn">Add Notes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Upload Document Modal -->
<div class="modal" id="documentModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Upload Document</h3>
                <button type="button" class="close" onclick="closeModal('documentModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form action="" method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="document_name">Document Name</label>
                        <input type="text" name="document_name" id="document_name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="document_type">Document Type</label>
                        <select name="document_type" id="document_type" class="form-control" required>
                            <option value="Consultation Summary">Consultation Summary</option>
                            <option value="Client Document">Client Document</option>
                            <option value="Visa Application">Visa Application</option>
                            <option value="Supporting Document">Supporting Document</option>
                            <option value="Invoice">Invoice</option>
                            <option value="Receipt">Receipt</option>
                            <option value="Contract">Contract</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="document">Select File</label>
                        <input type="file" name="document" id="document" class="form-control-file" required>
                        <small class="form-text">Allowed formats: PDF, DOC, DOCX, JPG, JPEG, PNG</small>
                    </div>
                    
                    <div class="form-group">
                        <div class="checkbox">
                            <input type="checkbox" name="is_private" id="is_private" checked>
                            <label for="is_private">Private (only visible to team members)</label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="document_notes">Notes</label>
                        <textarea name="document_notes" id="document_notes" class="form-control" rows="3"
                                  placeholder="Optional notes about this document"></textarea>
                    </div>
                    
                    <div class="form-buttons">
                        <button type="button" class="btn cancel-btn" onclick="closeModal('documentModal')">Cancel</button>
                        <button type="submit" name="upload_document" class="btn submit-btn">Upload Document</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
:root {
    --primary-color: #042167;
    --secondary-color: #858796;
    --success-color: #1cc88a;
    --warning-color: #f6c23e;
    --danger-color: #e74a3b;
    --info-color: #36b9cc;
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

.btn-back, .btn-edit, .btn-add-note, .btn-meeting, .btn-document, .btn-complete {
    padding: 8px 12px;
    border-radius: 4px;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 14px;
    border: none;
    cursor: pointer;
}

.btn-back {
    background-color: var(--light-color);
    color: var(--secondary-color);
    border: 1px solid var(--border-color);
}

.btn-edit {
    background-color: var(--info-color);
    color: white;
}

.btn-add-note {
    background-color: var(--warning-color);
    color: white;
}

.btn-meeting {
    background-color: var(--primary-color);
    color: white;
}

.btn-document {
    background-color: var(--secondary-color);
    color: white;
}

.btn-complete {
    background-color: var(--success-color);
    color: white;
}

.alert {
    padding: 15px;
    border-radius: 4px;
    margin-bottom: 20px;
}

.alert-success {
    background-color: rgba(28, 200, 138, 0.1);
    border: 1px solid rgba(28, 200, 138, 0.25);
    color: var(--success-color);
}

.alert-danger {
    background-color: rgba(231, 74, 59, 0.1);
    border: 1px solid rgba(231, 74, 59, 0.25);
    color: var(--danger-color);
}

.booking-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 20px;
}

.card {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.card-header {
    padding: 15px 20px;
    background-color: var(--light-color);
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-header h3 {
    margin: 0;
    color: var(--primary-color);
    font-size: 1.1rem;
}

.view-link, .btn-add-document {
    color: var(--primary-color);
    text-decoration: none;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 5px;
    background: none;
    border: none;
    cursor: pointer;
}

.view-link:hover, .btn-add-document:hover {
    text-decoration: underline;
}

.card-body {
    padding: 20px;
}

.status-banner {
    padding: 10px 15px;
    border-radius: 4px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.detail-group {
    margin-bottom: 20px;
}

.detail-row {
    display: flex;
    margin-bottom: 10px;
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

.duration {
    color: var(--dark-color);
    font-size: 13px;
    margin-left: 5px;
}

.detail-divider {
    height: 1px;
    background-color: var(--border-color);
    margin: 20px 0;
}

.meeting-link-row {
    align-items: flex-start;
}

.meeting-link {
    color: var(--info-color);
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 5px;
    word-break: break-all;
}

.meeting-link:hover {
    text-decoration: underline;
}

.payment-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 12px;
}

.payment-badge.completed {
    background-color: rgba(28, 200, 138, 0.1);
    color: var(--success-color);
}

.payment-badge.pending {
    background-color: rgba(246, 194, 62, 0.1);
    color: var(--warning-color);
}

.payment-badge.failed {
    background-color: rgba(231, 74, 59, 0.1);
    color: var(--danger-color);
}

.note-text {
    white-space: pre-line;
    font-size: 14px;
}

.client-header {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
}

.client-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background-color: var(--primary-color);
    display: flex;
    justify-content: center;
    align-items: center;
    color: white;
    font-size: 24px;
    font-weight: 600;
}

.client-name-email h4 {
    margin: 0 0 5px;
    color: var(--dark-color);
    font-size: 18px;
}

.client-name-email p {
    margin: 0 0 5px;
    color: var(--secondary-color);
    font-size: 14px;
}

.client-actions {
    display: flex;
    gap: 10px;
    margin-top: 15px;
}

.btn-client-action {
    padding: 8px 12px;
    background-color: var(--light-color);
    color: var(--primary-color);
    border: 1px solid var(--border-color);
    border-radius: 4px;
    display: flex;
    align-items: center;
    gap: 5px;
    text-decoration: none;
    font-size: 14px;
}

.btn-client-action:hover {
    background-color: rgba(4, 33, 103, 0.05);
}

.empty-state {
    text-align: center;
    padding: 30px;
    color: var(--secondary-color);
}

.empty-state i {
    font-size: 36px;
    margin-bottom: 15px;
    opacity: 0.6;
}

.documents-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.document-item {
    display: flex;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    overflow: hidden;
}

.document-icon {
    background-color: var(--light-color);
    padding: 15px;
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 24px;
    color: var(--primary-color);
    width: 60px;
}

.document-details {
    flex: 1;
    padding: 15px;
}

.document-name {
    font-weight: 600;
    margin-bottom: 5px;
    color: var(--dark-color);
    display: flex;
    align-items: center;
    gap: 8px;
}

.private-badge {
    background-color: rgba(231, 74, 59, 0.1);
    color: var(--danger-color);
    font-size: 11px;
    padding: 2px 6px;
    border-radius: 3px;
    font-weight: normal;
}

.document-meta {
    font-size: 12px;
    color: var(--secondary-color);
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.document-notes {
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px dashed var(--border-color);
    font-size: 13px;
    color: var(--secondary-color);
}

.document-actions {
    display: flex;
    flex-direction: column;
    gap: 5px;
    padding: 15px;
    border-left: 1px solid var(--border-color);
}

.document-action {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 6px 10px;
    border-radius: 4px;
    text-decoration: none;
    font-size: 13px;
}

.document-action.view {
    background-color: var(--light-color);
    color: var(--primary-color);
}

.document-action.download {
    background-color: var(--primary-color);
    color: white;
}

.activity-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.activity-item {
    display: flex;
    gap: 15px;
    padding-bottom: 15px;
    border-bottom: 1px solid var(--border-color);
}

.activity-item:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.activity-icon {
    background-color: var(--light-color);
    color: var(--primary-color);
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 16px;
}

.activity-details {
    flex: 1;
}

.activity-type {
    font-weight: 600;
    color: var(--dark-color);
    margin-bottom: 3px;
}

.activity-description {
    margin-bottom: 5px;
    color: var(--secondary-color);
}

.activity-user {
    font-size: 13px;
    color: var(--primary-color);
    margin-bottom: 3px;
}

.activity-date {
    font-size: 12px;
    color: var(--secondary-color);
}

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    overflow: auto;
}

.modal-dialog {
    margin: 60px auto;
    max-width: 500px;
    width: 90%;
}

.modal-content {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
}

.modal-header {
    padding: 15px 20px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-title {
    margin: 0;
    color: var(--primary-color);
}

.close {
    font-size: 24px;
    background: none;
    border: none;
    cursor: pointer;
    color: var(--secondary-color);
}

.modal-body {
    padding: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
    color: var(--dark-color);
}

.form-control {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    font-size: 14px;
}

.form-control-file {
    display: block;
    margin-top: 5px;
}

.form-text {
    font-size: 12px;
    color: var(--secondary-color);
    margin-top: 5px;
    display: block;
}

.checkbox {
    display: flex;
    align-items: center;
    gap: 8px;
}

.checkbox input {
    margin: 0;
}

.form-buttons {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 20px;
}

.cancel-btn, .submit-btn {
    padding: 8px 16px;
    border-radius: 4px;
    font-size: 14px;
    cursor: pointer;
}

.cancel-btn {
    background-color: var(--light-color);
    color: var(--secondary-color);
    border: 1px solid var(--border-color);
}

.submit-btn {
    background-color: var(--primary-color);
    color: white;
    border: none;
}

@media (max-width: 768px) {
    .booking-info-grid {
        grid-template-columns: 1fr;
    }
    
    .header-container {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .action-buttons {
        flex-direction: column;
        width: 100%;
    }
    
    .btn-back, .btn-edit, .btn-add-note, .btn-meeting, .btn-document, .btn-complete {
        width: 100%;
        justify-content: center;
    }
    
    .detail-row {
        flex-direction: column;
    }
    
    .detail-label {
        width: 100%;
        margin-bottom: 5px;
    }
}
</style>

<script>
function openModal(modalId) {
    document.getElementById(modalId).style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

function openMeetingLinkModal() {
    openModal('meetingLinkModal');
}

function openStatusModal() {
    openModal('statusModal');
}

function openCompleteModal() {
    openModal('completeModal');
}

function openNotesModal() {
    openModal('notesModal');
}

function openDocumentModal() {
    openModal('documentModal');
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
};
</script>

<?php require_once 'includes/footer.php'; ?>

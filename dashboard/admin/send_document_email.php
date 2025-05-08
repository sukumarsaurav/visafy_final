<?php
// Start output buffering to prevent 'headers already sent' errors
ob_start();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure user is logged in and has a valid user_id
if (!isset($_SESSION['id']) || empty($_SESSION['id'])) {
    // Redirect to login if no user_id is set
    header("Location: login.php");
    exit;
}

// Assign user_id from session['id'] to be consistent with header.php
$_SESSION['user_id'] = $_SESSION['id'];

// Check if document ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: documents.php?error=no_document_specified");
    exit;
}

$document_id = intval($_GET['id']);
$page_title = "Send Document Email";
require_once 'includes/header.php';

// Get document details
$query = "SELECT gd.*, dt.name as document_type_name, 
          CONCAT(u.first_name, ' ', u.last_name) as client_name,
          u.email as client_email,
          tmpl.name as template_name 
          FROM generated_documents gd
          JOIN document_types dt ON gd.document_type_id = dt.id
          JOIN document_templates tmpl ON gd.template_id = tmpl.id
          JOIN users u ON gd.client_id = u.id
          WHERE gd.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $document_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: documents.php?error=document_not_found");
    exit;
}

$document = $result->fetch_assoc();
$stmt->close();

// Check if document has already been emailed
if ($document['email_sent']) {
    header("Location: documents.php?error=document_already_emailed");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email_subject = trim($_POST['email_subject']);
    $email_body = trim($_POST['email_body']);
    $recipient_email = trim($_POST['recipient_email']);
    
    // Validate inputs
    $errors = [];
    if (empty($email_subject)) {
        $errors[] = "Email subject is required";
    }
    if (empty($email_body)) {
        $errors[] = "Email body is required";
    }
    if (empty($recipient_email)) {
        $errors[] = "Recipient email is required";
    } elseif (!filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if (empty($errors)) {
        // Create email headers
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= 'From: Visafy <noreply@visafy.com>' . "\r\n";
        
        // Prepare attachment
        $file_path = '../../' . $document['file_path'];
        $file_name = basename($document['file_path']);
        
        // Check if file exists
        if (!file_exists($file_path)) {
            $errors[] = "Document file not found. Please ensure the document was generated correctly.";
        } else {
            // For simple email with link to download
            $document_url = "https://" . $_SERVER['HTTP_HOST'] . "/" . $document['file_path'];
            
            $email_content = $email_body;
            $email_content .= "<br><br>";
            $email_content .= "You can download your document by clicking this link: ";
            $email_content .= "<a href='" . $document_url . "'>Download " . htmlspecialchars($document['name']) . "</a>";
            $email_content .= "<br><br>";
            $email_content .= "If you have any questions, please don't hesitate to contact us.";
            $email_content .= "<br><br>";
            $email_content .= "Regards,<br>The Visafy Team";
            
            // Send email
            $mail_sent = mail($recipient_email, $email_subject, $email_content, $headers);
            
            if ($mail_sent) {
                // Update document record to mark as emailed
                $update_query = "UPDATE generated_documents SET email_sent = 1, email_sent_date = NOW() WHERE id = ?";
                $stmt = $conn->prepare($update_query);
                $stmt->bind_param('i', $document_id);
                
                if ($stmt->execute()) {
                    $stmt->close();
                    header("Location: documents.php?success=email_sent");
                    exit;
                } else {
                    $errors[] = "Error updating document record: " . $conn->error;
                    $stmt->close();
                }
            } else {
                $errors[] = "Failed to send email. Please try again later.";
            }
        }
    }
    
    if (!empty($errors)) {
        $error_message = implode("<br>", $errors);
    }
}
?>

<div class="content">
    <div class="header-container">
        <div>
            <h1>Send Document Email</h1>
            <p>Send the document "<?php echo htmlspecialchars($document['name']); ?>" to client</p>
        </div>
        <div class="header-actions">
            <a href="documents.php" class="btn secondary-btn">
                <i class="fas fa-arrow-left"></i> Back to Documents
            </a>
        </div>
    </div>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <div class="email-form-container">
        <form action="send_document_email.php?id=<?php echo $document_id; ?>" method="POST" class="email-form">
            <div class="form-section">
                <h3>Document Information</h3>
                <div class="document-info">
                    <p><strong>Document Name:</strong> <?php echo htmlspecialchars($document['name']); ?></p>
                    <p><strong>Document Type:</strong> <?php echo htmlspecialchars($document['document_type_name']); ?></p>
                    <p><strong>Template:</strong> <?php echo htmlspecialchars($document['template_name']); ?></p>
                    <p><strong>Client:</strong> <?php echo htmlspecialchars($document['client_name']); ?></p>
                    <p><strong>Generated Date:</strong> <?php echo date('F j, Y, g:i a', strtotime($document['generated_date'])); ?></p>
                </div>
            </div>
            
            <div class="form-section">
                <h3>Email Details</h3>
                <div class="form-group">
                    <label for="recipient_email">Recipient Email*</label>
                    <input type="email" name="recipient_email" id="recipient_email" class="form-control" 
                           value="<?php echo htmlspecialchars($document['client_email']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="email_subject">Email Subject*</label>
                    <input type="text" name="email_subject" id="email_subject" class="form-control" 
                           value="Your Document: <?php echo htmlspecialchars($document['name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="email_body">Email Body*</label>
                    <textarea name="email_body" id="email_body" class="form-control" rows="8" required>Dear <?php echo htmlspecialchars($document['client_name']); ?>,

We have prepared the document "<?php echo htmlspecialchars($document['name']); ?>" for you.

Please find the document attached to this email. You can also download it using the link below.</textarea>
                </div>
            </div>
            
            <div class="form-actions">
                <a href="documents.php" class="btn cancel-btn">Cancel</a>
                <button type="submit" class="btn submit-btn">
                    <i class="fas fa-paper-plane"></i> Send Email
                </button>
            </div>
        </form>
    </div>
</div>

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

.header-actions {
    display: flex;
    gap: 10px;
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

.email-form-container {
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.email-form {
    padding: 20px;
}

.form-section {
    margin-bottom: 30px;
}

.form-section h3 {
    margin: 0 0 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border-color);
    color: var(--primary-color);
    font-size: 1.2rem;
}

.document-info {
    background-color: var(--light-color);
    padding: 15px;
    border-radius: 4px;
    border: 1px solid var(--border-color);
}

.document-info p {
    margin: 8px 0;
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

.form-actions {
    padding-top: 15px;
    border-top: 1px solid var(--border-color);
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px 20px;
    border-radius: 4px;
    font-weight: 500;
    cursor: pointer;
    text-decoration: none;
    transition: background-color 0.2s;
    border: none;
}

.secondary-btn {
    background-color: white;
    color: var(--primary-color);
    border: 1px solid var(--primary-color);
}

.secondary-btn:hover {
    background-color: var(--light-color);
}

.cancel-btn {
    background-color: white;
    color: var(--secondary-color);
    border: 1px solid var(--border-color);
}

.submit-btn {
    background-color: var(--primary-color);
    color: white;
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
    
    .header-actions {
        width: 100%;
    }
}
</style>

<?php
// End output buffering and send content to browser
ob_end_flush();
?>

<?php require_once 'includes/footer.php'; ?>

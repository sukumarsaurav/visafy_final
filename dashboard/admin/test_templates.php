<?php
// Start session
session_start();

// Ensure we have a user ID for testing
if (!isset($_SESSION['id'])) {
    $_SESSION['id'] = 1; // Assuming admin user ID is 1
}

// Page header
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Template AJAX Handler</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        h1 {
            color: #042167;
        }
        .container {
            background: #f8f9fc;
            border-radius: 5px;
            padding: 20px;
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        select {
            width: 100%;
            padding: 8px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        button {
            background: #042167;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background: #031c56;
        }
        pre {
            background: #f1f1f1;
            padding: 15px;
            border-radius: 4px;
            overflow: auto;
        }
        .success {
            color: green;
        }
        .error {
            color: red;
        }
    </style>
</head>
<body>
    <h1>Test Template AJAX Handler</h1>
    
    <div class="container">
        <h2>Test fetch templates by document type</h2>
        <form id="test-form">
            <label for="document_type_id">Select Document Type ID:</label>
            <select id="document_type_id" name="document_type_id">
                <option value="">Select Document Type</option>
                <?php
                // List document type IDs from 1 to 10 for testing
                for ($i = 1; $i <= 10; $i++) {
                    echo "<option value=\"$i\">Document Type ID: $i</option>";
                }
                ?>
            </select>
            
            <button type="submit">Fetch Templates</button>
        </form>
    </div>
    
    <div class="container">
        <h2>Results</h2>
        <div id="request-info"></div>
        <pre id="result"></pre>
    </div>
    
    <script>
        document.getElementById('test-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const docTypeId = document.getElementById('document_type_id').value;
            if (!docTypeId) {
                alert('Please select a document type ID');
                return;
            }
            
            // Show request info
            document.getElementById('request-info').innerHTML = 
                `<p>Requesting templates for document_type_id: <strong>${docTypeId}</strong>...</p>`;
            
            // Clear previous results
            document.getElementById('result').innerHTML = 'Loading...';
            
            // Make AJAX request
            fetch('ajax/get_templates.php?document_type_id=' + docTypeId)
                .then(response => response.json())
                .then(data => {
                    // Format and display the response
                    let resultHtml;
                    if (data.error) {
                        resultHtml = `<span class="error">Error: ${data.error}</span>`;
                    } else if (Array.isArray(data) && data.length > 0) {
                        resultHtml = `<span class="success">Found ${data.length} template(s):</span>\n\n`;
                        resultHtml += JSON.stringify(data, null, 2);
                    } else {
                        resultHtml = '<span class="error">No templates found for this document type.</span>';
                    }
                    
                    document.getElementById('result').innerHTML = resultHtml;
                })
                .catch(error => {
                    document.getElementById('result').innerHTML = 
                        `<span class="error">Error fetching templates: ${error.message}</span>`;
                });
        });
    </script>
</body>
</html> 
<?php
include_once 'includes/header.php';

// Fetch basic stats
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM decision_tree_questions");
$stmt->execute();
$questions_count = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM user_assessments");
$stmt->execute();
$assessments_count = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM user_assessments WHERE is_complete = 1");
$stmt->execute();
$completed_count = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();
?>

<div class="content">
    <div class="header-container">
        <div>
            <h1><i class="fas fa-sitemap"></i> Eligibility Checker Management</h1>
            <p>Create and manage decision trees for eligibility checking.</p>
        </div>
    </div>

    <div class="stats-container">
        <div class="stats-card">
            <div class="stats-card-body">
                <div class="stats-card-icon bg-primary">
                    <i class="fas fa-question-circle"></i>
                </div>
                <div class="stats-card-content">
                    <h3><?php echo $questions_count; ?></h3>
                    <p>Total Questions</p>
                </div>
            </div>
        </div>
        <div class="stats-card">
            <div class="stats-card-body">
                <div class="stats-card-icon bg-info">
                    <i class="fas fa-clipboard-check"></i>
                </div>
                <div class="stats-card-content">
                    <h3><?php echo $assessments_count; ?></h3>
                    <p>Total Assessments</p>
                </div>
            </div>
        </div>
        <div class="stats-card">
            <div class="stats-card-body">
                <div class="stats-card-icon bg-success">
                    <i class="fas fa-check-double"></i>
                </div>
                <div class="stats-card-content">
                    <h3><?php echo $completed_count; ?></h3>
                    <p>Completed Assessments</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card-container">
        <div class="action-card">
            <div class="action-card-header">
                <h5>Manage Questions</h5>
            </div>
            <div class="action-card-body">
                <p>Create and manage questions in your decision tree.</p>
                <a href="manage_questions.php" class="btn primary-btn">Manage Questions</a>
            </div>
        </div>
        <div class="action-card">
            <div class="action-card-header">
                <h5>View Assessment Results</h5>
            </div>
            <div class="action-card-body">
                <p>View and analyze user assessment results.</p>
                <a href="assessment_results.php" class="btn primary-btn">View Results</a>
            </div>
        </div>
    </div>
    
    <div class="tree-container">
        <div class="tree-card">
            <div class="tree-card-header">
                <h5>Decision Tree Visualization</h5>
            </div>
            <div class="tree-card-body">
                <div id="decision-tree-container"></div>
            </div>
        </div>
    </div>
</div>

<!-- Vis.js CSS and JavaScript -->
<link href="https://unpkg.com/vis-network/dist/dist/vis-network.min.css" rel="stylesheet" type="text/css" />
<script src="https://unpkg.com/vis-network/standalone/umd/vis-network.min.js"></script>

<style>
:root {
    --primary-color: #042167;
    --secondary-color: #858796;
    --success-color: #1cc88a;
    --danger-color: #e74a3b;
    --info-color: #36b9cc;
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

.stats-container {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 20px;
}

.stats-card {
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.stats-card-body {
    display: flex;
    padding: 20px;
    align-items: center;
}

.stats-card-icon {
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 5px;
    margin-right: 15px;
    color: white;
    font-size: 24px;
}

.bg-primary {
    background-color: var(--primary-color);
}

.bg-info {
    background-color: var(--info-color);
}

.bg-success {
    background-color: var(--success-color);
}

.stats-card-content h3 {
    margin: 0;
    font-size: 24px;
    font-weight: 600;
    color: var(--dark-color);
}

.stats-card-content p {
    margin: 5px 0 0;
    color: var(--secondary-color);
    font-size: 14px;
}

.card-container {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    margin-bottom: 20px;
}

.action-card {
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.action-card-header {
    padding: 15px 20px;
    border-bottom: 1px solid var(--border-color);
    background-color: var(--light-color);
}

.action-card-header h5 {
    margin: 0;
    color: var(--primary-color);
    font-size: 16px;
    font-weight: 600;
}

.action-card-body {
    padding: 20px;
}

.action-card-body p {
    margin-bottom: 15px;
    color: var(--dark-color);
}

.tree-container {
    margin-top: 20px;
}

.tree-card {
    background-color: white;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    overflow: hidden;
}

.tree-card-header {
    padding: 15px 20px;
    border-bottom: 1px solid var(--border-color);
    background-color: var(--light-color);
}

.tree-card-header h5 {
    margin: 0;
    color: var(--primary-color);
    font-size: 16px;
    font-weight: 600;
}

.tree-card-body {
    padding: 20px;
    overflow: hidden; /* Prevent scrollbars from appearing unnecessarily */
}

#decision-tree-container {
    height: 600px;
    border: 1px solid var(--border-color);
    background-color: #fafafa;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 10px 15px;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 500;
    text-decoration: none;
    cursor: pointer;
    transition: background-color 0.2s;
}

.primary-btn {
    background-color: var(--primary-color);
    color: white;
    border: none;
}

.primary-btn:hover {
    background-color: #031c56;
}

@media (max-width: 768px) {
    .stats-container, .card-container {
        grid-template-columns: 1fr;
    }
}

/* Add a loading indicator */
.loading-overlay {
    display: none;
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.8);
    justify-content: center;
    align-items: center;
    z-index: 1000;
}

.loading-spinner {
    border: 4px solid #f3f3f3;
    border-top: 4px solid var(--primary-color);
    border-radius: 50%;
    width: 40px;
    height: 40px;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('decision-tree-container');
    
    // Add loading overlay
    const loadingOverlay = document.createElement('div');
    loadingOverlay.className = 'loading-overlay';
    loadingOverlay.innerHTML = '<div class="loading-spinner"></div>';
    container.parentNode.appendChild(loadingOverlay);
    
    // Show loading
    loadingOverlay.style.display = 'flex';
    
    // Network visualization options
    const options = {
        layout: {
            hierarchical: {
                direction: 'UD',
                sortMethod: 'directed',
                levelSeparation: 150,
                nodeSpacing: 200,
                treeSpacing: 200
            }
        },
        nodes: {
            shape: 'box',
            font: {
                size: 14,
                face: 'Arial'
            },
            margin: 10,
            shadow: true,
            borderWidth: 2
        },
        edges: {
            smooth: {
                type: 'cubicBezier',
                forceDirection: 'vertical'
            },
            width: 2
        },
        physics: {
            enabled: false
        },
        interaction: {
            dragNodes: true,
            dragView: true,
            zoomView: true,
            hover: true
        }
    };

    // Fetch tree data
    fetch('ajax/get_decision_tree.php')
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                throw new Error(data.message || 'Error loading decision tree data');
            }
            
            // Create the network with proper data structure
            const network = new vis.Network(container, {
                nodes: new vis.DataSet(data.nodes),
                edges: new vis.DataSet(data.edges)
            }, options);
            
            // Add event listeners
            network.on('click', function(params) {
                if (params.nodes.length > 0) {
                    const nodeId = params.nodes[0];
                    console.log('Clicked node:', nodeId);
                }
            });
            
            // Center the network once it's stabilized
            network.once('stabilized', function() {
                network.fit();
            });
            
            // Hide loading overlay when done
            loadingOverlay.style.display = 'none';
        })
        .catch(error => {
            console.error('Error fetching tree data:', error);
            container.innerHTML = `
                <div style="text-align: center; padding: 20px; color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px;">
                    <h4>Error Loading Decision Tree</h4>
                    <p>${error.message || 'Failed to load the decision tree visualization. Please try again later.'}</p>
                </div>
            `;
            loadingOverlay.style.display = 'none';
        });
});
</script>

<?php include_once 'includes/footer.php'; ?>

<?php
require_once '../../../config/db_connect.php';
require_once '../../../includes/functions.php';

// Check if user is logged in as admin
session_start();
if (!isset($_SESSION["loggedin"]) || !isset($_SESSION["user_type"]) || $_SESSION["user_type"] != 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Fetch all questions
$questions = [];
$stmt = $conn->prepare("SELECT id, question_text, is_active FROM decision_tree_questions ORDER BY id");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $questions[$row['id']] = $row;
}
$stmt->close();

// Fetch all options
$options = [];
$stmt = $conn->prepare("SELECT id, question_id, option_text, next_question_id, is_endpoint, endpoint_eligible 
                       FROM decision_tree_options ORDER BY id");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    if (!isset($options[$row['question_id']])) {
        $options[$row['question_id']] = [];
    }
    $options[$row['question_id']][] = $row;
}
$stmt->close();

// Prepare nodes and edges for visualization
$nodes = [];
$edges = [];

// Add question nodes
foreach ($questions as $id => $question) {
    $color = $question['is_active'] ? '#4CAF50' : '#9E9E9E';
    $nodes[] = [
        'id' => 'q' . $id,
        'label' => 'Q: ' . substr($question['question_text'], 0, 40) . (strlen($question['question_text']) > 40 ? '...' : ''),
        'color' => $color,
        'shape' => 'box',
        'font' => ['color' => 'white']
    ];
}

// Add option nodes and edges
foreach ($options as $question_id => $question_options) {
    foreach ($question_options as $option) {
        $option_id = 'o' . $option['id'];
        
        // Add option node
        if ($option['is_endpoint']) {
            $color = $option['endpoint_eligible'] ? '#2196F3' : '#F44336';
            $nodes[] = [
                'id' => $option_id,
                'label' => 'A: ' . substr($option['option_text'], 0, 30) . (strlen($option['option_text']) > 30 ? '...' : '') . '\n[ENDPOINT]',
                'color' => $color,
                'shape' => 'ellipse'
            ];
        } else {
            $nodes[] = [
                'id' => $option_id,
                'label' => 'A: ' . substr($option['option_text'], 0, 30) . (strlen($option['option_text']) > 30 ? '...' : ''),
                'color' => '#FFC107',
                'shape' => 'ellipse'
            ];
        }
        
        // Add edge from question to option
        $edges[] = [
            'from' => 'q' . $question_id,
            'to' => $option_id
        ];
        
        // Add edge from option to next question if applicable
        if (!$option['is_endpoint'] && !empty($option['next_question_id'])) {
            $edges[] = [
                'from' => $option_id,
                'to' => 'q' . $option['next_question_id'],
                'arrows' => 'to'
            ];
        }
    }
}

// Return as vis.js compatible data
$response = [
    'nodes' => $nodes,
    'edges' => $edges
];

header('Content-Type: application/json');
echo json_encode($response);

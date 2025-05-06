<?php
// Prevent any whitespace or output before this point
require_once '../../../config/db_connect.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set headers
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Helper function to darken colors for borders
function darkenColor($hex) {
    $hex = ltrim($hex, '#');
    $r = max(0, hexdec(substr($hex, 0, 2)) - 32);
    $g = max(0, hexdec(substr($hex, 2, 2)) - 32);
    $b = max(0, hexdec(substr($hex, 4, 2)) - 32);
    return sprintf("#%02x%02x%02x", $r, $g, $b);
}

try {
    // Initialize empty arrays
    $data = ['nodes' => [], 'edges' => []];
    
    // Fetch all questions
    $questions = [];
    $stmt = $conn->prepare("SELECT id, question_text, is_active FROM decision_tree_questions ORDER BY id");
    if (!$stmt) {
        throw new Exception("Error preparing question query: " . $conn->error);
    }
    
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
    if (!$stmt) {
        throw new Exception("Error preparing options query: " . $conn->error);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        if (!isset($options[$row['question_id']])) {
            $options[$row['question_id']] = [];
        }
        $options[$row['question_id']][] = $row;
    }
    $stmt->close();

    // Add question nodes
    foreach ($questions as $id => $question) {
        $color = $question['is_active'] ? '#4CAF50' : '#9E9E9E';
        $data['nodes'][] = [
            'id' => 'q' . $id,
            'label' => 'Q: ' . substr($question['question_text'], 0, 40) . (strlen($question['question_text']) > 40 ? '...' : ''),
            'color' => [
                'background' => $color,
                'border' => darkenColor($color)
            ],
            'shape' => 'box',
            'font' => [
                'color' => 'white',
                'size' => 14
            ]
        ];
    }

    // Add option nodes and edges
    foreach ($options as $question_id => $question_options) {
        foreach ($question_options as $option) {
            $option_id = 'o' . $option['id'];
            
            // Add option node
            if ($option['is_endpoint']) {
                $color = $option['endpoint_eligible'] ? '#2196F3' : '#F44336';
                $data['nodes'][] = [
                    'id' => $option_id,
                    'label' => 'A: ' . substr($option['option_text'], 0, 30) . (strlen($option['option_text']) > 30 ? '...' : '') . '\n[ENDPOINT]',
                    'color' => [
                        'background' => $color,
                        'border' => darkenColor($color)
                    ],
                    'shape' => 'ellipse'
                ];
            } else {
                $data['nodes'][] = [
                    'id' => $option_id,
                    'label' => 'A: ' . substr($option['option_text'], 0, 30) . (strlen($option['option_text']) > 30 ? '...' : ''),
                    'color' => [
                        'background' => '#FFC107',
                        'border' => '#FFA000'
                    ],
                    'shape' => 'ellipse'
                ];
            }
            
            // Add edge from question to option
            $data['edges'][] = [
                'from' => 'q' . $question_id,
                'to' => $option_id,
                'arrows' => 'to',
                'color' => '#666666'
            ];
            
            // Add edge from option to next question if applicable
            if (!$option['is_endpoint'] && !empty($option['next_question_id'])) {
                $data['edges'][] = [
                    'from' => $option_id,
                    'to' => 'q' . $option['next_question_id'],
                    'arrows' => 'to',
                    'color' => '#666666'
                ];
            }
        }
    }

    // Check if we have any data
    if (empty($data['nodes'])) {
        echo json_encode([
            'error' => true,
            'message' => 'No decision tree data found. Please add questions and options first.'
        ]);
    } else {
        echo json_encode($data);
    }

} catch (Exception $e) {
    echo json_encode([
        'error' => true,
        'message' => 'Error fetching decision tree data: ' . $e->getMessage()
    ]);
}
?>

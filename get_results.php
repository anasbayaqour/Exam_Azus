<?php
session_start();

// Strict authentication check
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit;
}

// Include database configuration
require_once 'config.php';

// Validate and sanitize student ID
$student_id = filter_input(INPUT_GET, 'student_id', FILTER_VALIDATE_INT);

if (!$student_id) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid student ID'
    ]);
    exit;
}

try {
    // Comprehensive results query with additional details
    $sql = "SELECT 
                r.id AS result_id,
                s.name AS subject_name, 
                r.grade,
                r.exam_date,
                s.coefficient,
                CASE 
                    WHEN r.grade >= 90 THEN 'Excellent'
                    WHEN r.grade >= 80 THEN 'Very Good'
                    WHEN r.grade >= 70 THEN 'Good'
                    WHEN r.grade >= 60 THEN 'Satisfactory'
                    ELSE 'Needs Improvement'
                END AS performance_level
            FROM results r
            JOIN subjects s ON r.subject_id = s.id
            WHERE r.user_id = :student_id
            ORDER BY r.exam_date DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['student_id' => $student_id]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get student information for context
    $student_sql = "SELECT first_name, last_name, email FROM users WHERE user_id = :student_id";
    $student_stmt = $pdo->prepare($student_sql);
    $student_stmt->execute(['student_id' => $student_id]);
    $student_info = $student_stmt->fetch(PDO::FETCH_ASSOC);

    // Calculate overall statistics
    $overall_stats = [
        'total_subjects' => count($results),
        'average_grade' => count($results) > 0 ? 
            round(array_sum(array_column($results, 'grade')) / count($results), 2) : 0,
        'highest_grade' => count($results) > 0 ? 
            max(array_column($results, 'grade')) : 0,
        'lowest_grade' => count($results) > 0 ? 
            min(array_column($results, 'grade')) : 0
    ];

    // Prepare response
    $response = [
        'success' => true,
        'student' => $student_info,
        'results' => $results,
        'overall_stats' => $overall_stats
    ];

    // Set proper content type and output JSON
    header('Content-Type: application/json');
    echo json_encode($response, JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    // Log the full error for server-side debugging
    error_log('Database Error in get_results.php: ' . $e->getMessage());

    // Return a generic error to the client
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error. Please try again later.'
    ]);
} catch (Exception $e) {
    // Catch any other unexpected errors
    error_log('Unexpected Error in get_results.php: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred.'
    ]);
}
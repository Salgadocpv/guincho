<?php
/**
 * Create questionnaire answers table
 */

include_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    // Create questionnaire_answers table
    $sql = "CREATE TABLE IF NOT EXISTS questionnaire_answers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        trip_request_id INT NOT NULL,
        question_id VARCHAR(100) NOT NULL,
        question_text TEXT NOT NULL,
        option_id VARCHAR(100) NOT NULL,
        option_text TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        FOREIGN KEY (trip_request_id) REFERENCES trip_requests(id) ON DELETE CASCADE,
        INDEX idx_trip_request (trip_request_id),
        INDEX idx_question (question_id),
        UNIQUE KEY unique_answer (trip_request_id, question_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $stmt = $db->prepare($sql);
    $stmt->execute();

    echo json_encode([
        'success' => true,
        'message' => 'Tabela questionnaire_answers criada com sucesso!'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao criar tabela: ' . $e->getMessage()
    ]);
}
?>
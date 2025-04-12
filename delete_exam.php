<?php
// delete_exam.php

// Inclure le fichier de configuration de la base de données
require_once 'config.php';


// Vérifier si l'ID de l'examen est passé en paramètre
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $exam_id = $_GET['id'];

    try {
        // Préparer la requête SQL pour supprimer l'examen
        $stmt = $pdo->prepare("DELETE FROM exams WHERE id = :id");
        $stmt->bindParam(':id', $exam_id, PDO::PARAM_INT);

        // Exécuter la requête
        $stmt->execute();

        // Rediriger l'utilisateur vers la page précédente avec un message de succès
        header('Location: teacher.php?message=Exam deleted successfully');
        exit();
    } catch (PDOException $e) {
        // En cas d'erreur, rediriger avec un message d'erreur
        header('Location: teacher.php?message=Error deleting exam: ' . urlencode($e->getMessage()));
        exit();
    }
} else {
    // Si aucun ID n'est fourni, rediriger avec un message d'erreur
    header('Location: teacher.php?message=No exam ID provided');
    exit();
}
?>
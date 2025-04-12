<?php
session_start();

// Vérifier si l'étudiant est connecté
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Examen Soumis - ExamOnline</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
    <div class="container mx-auto p-4 max-w-4xl">
        <div class="bg-white rounded-xl shadow-md p-8 text-center">
            <h1 class="text-3xl font-bold text-gray-800 mb-4">Examen Soumis avec Succès</h1>
            <p class="text-gray-600 mb-6">Vos réponses ont été enregistrées. L'enseignant corrigera votre examen et vous informera des résultats.</p>
            <a href="student.php" class="inline-block bg-blue-500 text-white px-6 py-2 rounded-lg hover:bg-blue-600 transition duration-200">
                Retour au tableau de bord
            </a>
        </div>
    </div>
</body>
</html>
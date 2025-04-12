<?php
session_start();

// Vérifier si l'utilisateur est connecté et est un étudiant
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    header("Location: login.php");
    exit();
}

require_once 'config.php'; // Inclure la configuration de la base de données

// Récupérer l'ID de l'utilisateur connecté
$user_id = $_SESSION['user_id'];

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer les données du formulaire
    $email = $_POST['email'];
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    try {
        // Récupérer les informations actuelles de l'utilisateur
        $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            throw new Exception("User not found.");
        }

        // Vérifier si le mot de passe actuel est correct (sans hachage)
        if ($current_password !== $user['password']) {
            throw new Exception("Current password is incorrect.");
        }

        // Valider le nouveau mot de passe (si fourni)
        if (!empty($new_password)) {
            if ($new_password !== $confirm_password) {
                throw new Exception("New password and confirmation do not match.");
            }
            // Stocker le nouveau mot de passe en texte brut
            $updated_password = $new_password;
        } else {
            // Garder l'ancien mot de passe si aucun nouveau n'est fourni
            $updated_password = $user['password'];
        }

        // Mettre à jour l'e-mail et/ou le mot de passe dans la base de données
        $stmt = $pdo->prepare("
            UPDATE users 
            SET email = :email, password = :password 
            WHERE user_id = :user_id
        ");
        $stmt->execute([
            'email' => $email,
            'password' => $updated_password,
            'user_id' => $user_id
        ]);

        // Rediriger avec un message de succès
        $_SESSION['success_message'] = "Profile updated successfully!";
        header("Location: student.php");
        exit();

    } catch (Exception $e) {
        // En cas d'erreur, rediriger avec un message d'erreur
        $_SESSION['error_message'] = $e->getMessage();
        header("Location: student.php#profile");
        exit();
    }
} else {
    // Si le formulaire n'a pas été soumis, rediriger vers le tableau de bord
    header("Location: student.php");
    exit();
}
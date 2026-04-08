<!-- Developed by Danie Brits -->

<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit('Unauthorized entry');
}

if (isset($_POST['email']) && isset($_POST['password'])) {
    // Stored credentials
    $email = $_POST['email'];
	$password = $_POST['password'];
//    $hashedPassword = //Retrieve password from database;

//TestHash
	$hashedPassword = password_hash($password,PASSWORD_DEFAULT);

    // Verify
    if (password_verify($password, $hashedPassword)) {
        $_SESSION['admin'] = 'Authorized';
        header('Location: ../index.php');
        exit();
    } else {
        $_SESSION['error'] = 'Invalid credentials';
        header('Location: ../login.html');
        exit();
    }
} else {
    header('Location: ../login.html');
    exit();
}
?>

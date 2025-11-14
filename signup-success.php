<?php
session_start();

// Check if user came from successful registration
if (!isset($_SESSION['pending_approval'])) {
    header('Location: ../index.php');
    exit;
}

$studentName = isset($_SESSION['student_name']) ? $_SESSION['student_name'] : 'Student';

// Clear the session variables
unset($_SESSION['pending_approval']);
unset($_SESSION['student_name']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <meta name="description" content="" />
    <meta name="author" content="" />
    <title>Registration Successful</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            background: #000435;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Montserrat', sans-serif;
        }
        .success-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            max-width: 600px;
            padding: 50px;
            text-align: center;
            margin: 20px;
        }
        .success-icon {
            width: 100px;
            height: 100px;
            background: #4CAF50;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            animation: scaleIn 0.5s ease-out;
        }
        .success-icon i {
            color: white;
            font-size: 50px;
        }
        @keyframes scaleIn {
            from {
                transform: scale(0);
                opacity: 0;
            }
            to {
                transform: scale(1);
                opacity: 1;
            }
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
            font-size: 32px;
        }
        .message {
            color: #666;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        .info-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 20px;
            margin: 30px 0;
            text-align: left;
            border-radius: 5px;
        }
        .info-box h3 {
            color: #856404;
            margin-top: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .info-box p {
            color: #856404;
            margin: 10px 0 0 0;
        }
        .info-box ul {
            color: #856404;
            margin: 15px 0 0 20px;
            text-align: left;
        }
        .info-box li {
            margin: 8px 0;
        }
        .btn {
            display: inline-block;
            padding: 15px 40px;
            background: #000435;
            color: white;
            text-decoration: none;
            border-radius: 25px;
            font-weight: 600;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            margin-top: 20px;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        .contact-info {
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid #eee;
            color: #888;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="success-icon">
            <i class="fas fa-check"></i>
        </div>
        
        <h1>Registration Submitted Successfully!</h1>
        
        <p class="message">
            Thank you, <strong><?php echo htmlspecialchars($studentName); ?></strong>! 
            Your registration has been received.
        </p>
        
        <div class="info-box">
            <h3>
                <i class="fas fa-hourglass-half"></i>
                Account Pending Approval
            </h3>
            <p>Your account is currently under review by our administrators. Here's what happens next:</p>
            <ul>
                <li><strong>Review Process:</strong> An administrator will verify your information</li>
                <li><strong>Approval Time:</strong> This typically takes 1-2 business days</li>
                <li><strong>Access:</strong> After approval, you can log in with your credentials</li>
            </ul>
        </div>
        
        <p class="message">
            <i class="fas fa-lock"></i> 
            Your account is currently <strong>blocked</strong> and will be activated once approved.
        </p>
        
        <a href="index.php" class="btn">
            <i class="fas fa-home"></i> Return to Login Page
        </a>
        
        <div class="contact-info">
            <p>
                <i class="fas fa-question-circle"></i> 
                Have questions? Contact the library administration for assistance.
            </p>
        </div>
    </div>
</body>
</html>
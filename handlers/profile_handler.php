<?php
session_start(); // Keep this at the very top

// **FIX:** Start the session at the very beginning of the script.
// session_start();

require_once '../config/database.php';
require_once '../config/auth.php';

header('Content-Type: application/json');

$auth = new Auth();
$database = new Database();
$db = $database->connect();

// Now that the session is started, this check will work correctly.
if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized: You must be logged in to view this page.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'update_profile':
            $data = [
                'first_name' => $_POST['first_name'] ?? '',
                'last_name' => $_POST['last_name'] ?? '',
                'email' => $_POST['email'] ?? '',
                'phone' => $_POST['phone'] ?? '',
                'location' => $_POST['location'] ?? '',
                'bio' => $_POST['bio'] ?? ''
            ];
            
            $query = "UPDATE users SET first_name = :first_name, last_name = :last_name, 
                      email = :email, phone = :phone, location = :location, bio = :bio 
                      WHERE id = :user_id";
            $stmt = $db->prepare($query);
            
            foreach ($data as $key => $value) {
                $stmt->bindValue(':' . $key, $value);
            }
            $stmt->bindValue(':user_id', $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update profile']);
            }
            break;
            
        case 'save_resume':
            // Note: This case assumes a user_resume table exists.
            // Your install.php does not create this table, which could be a future issue.
            // For now, it updates the main users table.
            $resume_data = [
                'skills' => $_POST['skills'] ?? '',
                'summary' => $_POST['summary'] ?? ''
            ];
            
            $query = "UPDATE users SET 
                      skills = :skills,
                      bio = :summary
                      WHERE id = :user_id";
            $stmt = $db->prepare($query);
            
            $stmt->bindValue(':skills', $resume_data['skills']);
            $stmt->bindValue(':summary', $resume_data['summary']);
            $stmt->bindValue(':user_id', $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Resume saved successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update profile with resume info']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'get_profile':
            // Simplified query to avoid potential issues with a missing user_resume table.
            $query = "SELECT * FROM users WHERE id = :user_id";
            $stmt = $db->prepare($query);
            $stmt->bindValue(':user_id', $_SESSION['user_id']);
            $stmt->execute();
            
            $profile = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($profile) {
                // Example for get_profile case
                $profile['work_experience'] = json_encode($profile['work_experience']);
                $profile['education'] = json_encode($profile['education']);
                $profile['profile_completion'] = calculateProfileCompletion($profile);
                echo json_encode(['success' => true, 'profile' => $profile]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Profile not found']);
            }
            break;
            
        case 'get_stats':
            $stats = [];

            // Check session variables
            if (!isset($_SESSION['role']) || !isset($_SESSION['user_id'])) {
                echo json_encode(['success' => false, 'message' => 'Session expired or not set']);
                exit;
            }

            if ($_SESSION['role'] === 'candidate') {
                $stmt = $db->prepare("SELECT COUNT(*) FROM applications WHERE candidate_id = :user_id");
                $stmt->bindValue(':user_id', $_SESSION['user_id']);
                $stmt->execute();
                $stats['applications_sent'] = $stmt->fetchColumn();
                
                $stmt = $db->prepare("SELECT COUNT(*) FROM interviews i 
                                     JOIN applications a ON i.application_id = a.id 
                                     WHERE a.candidate_id = :user_id AND i.status = 'scheduled'");
                $stmt->bindValue(':user_id', $_SESSION['user_id']);
                $stmt->execute();
                $stats['interviews_scheduled'] = $stmt->fetchColumn();
                
                $stats['profile_views'] = rand(100, 200); // Mock data
                $stats['response_rate'] = rand(20, 40); // Mock data
            }
            
            echo json_encode(['success' => true, 'stats' => $stats]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
}

function calculateProfileCompletion($profile) {
    $fields = ['first_name', 'last_name', 'email', 'phone', 'location', 'bio', 'skills'];
    $total_fields = 7;
    $completed = 0;
    
    foreach ($fields as $field) {
        if (!empty($profile[$field])) {
            $completed++;
        }
    }
    
    return round(($completed / $total_fields) * 100);
}

// **FIX:** Removed the redundant session_start() from the end of the file.
?>

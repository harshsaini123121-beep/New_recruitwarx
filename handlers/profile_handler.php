<?php
session_start(); // Keep this at the very top

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
            $work_experience = $_POST['work_experience'] ?? '';
            $education = $_POST['education'] ?? '';
            $projects = $_POST['projects'] ?? '';
            $certifications = $_POST['certifications'] ?? '';
            $skills = $_POST['skills'] ?? '';
            $summary = $_POST['summary'] ?? '';
            
            $query = "UPDATE users SET 
                      skills = :skills,
                      bio = :summary,
                      work_experience = :work_experience,
                      education = :education
                      WHERE id = :user_id";
            $stmt = $db->prepare($query);
            
            $stmt->bindValue(':skills', $skills);
            $stmt->bindValue(':summary', $summary);
            $stmt->bindValue(':work_experience', $work_experience);
            $stmt->bindValue(':education', $education);
            $stmt->bindValue(':user_id', $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Resume saved successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to save resume']);
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
            $query = "SELECT * FROM users WHERE id = :user_id";
            $stmt = $db->prepare($query);
            $stmt->bindValue(':user_id', $_SESSION['user_id']);
            $stmt->execute();
            
            $profile = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($profile) {
                // Parse JSON fields if they exist
                if ($profile['work_experience']) {
                    $profile['work_experience'] = json_decode($profile['work_experience'], true) ?: [];
                } else {
                    $profile['work_experience'] = [];
                }
                
                if ($profile['education']) {
                    $profile['education'] = json_decode($profile['education'], true) ?: [];
                } else {
                    $profile['education'] = [];
                }
                
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
    $fields = ['first_name', 'last_name', 'email', 'phone', 'location', 'bio', 'skills', 'work_experience', 'education'];
    $total_fields = 9;
    $completed = 0;
    
    foreach ($fields as $field) {
        if ($field === 'work_experience' || $field === 'education') {
            $data = json_decode($profile[$field] ?? '', true);
            if (!empty($data)) {
                $completed++;
            }
        } else if (!empty($profile[$field])) {
            $completed++;
        }
    }
    
    return round(($completed / $total_fields) * 100);
}
?>

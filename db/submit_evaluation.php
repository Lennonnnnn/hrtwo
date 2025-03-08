<?php
session_start();
include '../db/db_conn.php';

if (!isset($_SESSION['a_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'You are not AUTHORIZED.']);
    exit();
}

$adminId = $_SESSION['a_id'];

$adminSql = "SELECT firstname, lastname FROM admin_register WHERE a_id = ?";
$adminStmt = $conn->prepare($adminSql);
$adminStmt->bind_param('i', $adminId);
$adminStmt->execute();
$adminStmt->bind_result($adminFirstName, $adminLastName);

if ($adminStmt->fetch()) {
    $adminName = $adminFirstName . ' ' . $adminLastName;
} else {
    echo json_encode(['status' => 'error', 'message' => 'Admin not found.']);
    exit();
}

$adminStmt->close();

$employeeId = $_POST['e_id'];
$categoryAverages = json_decode($_POST['categoryAverages'], true);
$department = $_POST['department'];

$employeeSql = "SELECT firstname, lastname FROM employee_register WHERE e_id = ?";
$employeeStmt = $conn->prepare($employeeSql);
$employeeStmt->bind_param('i', $employeeId);
$employeeStmt->execute();
$employeeStmt->bind_result($employeeFirstName, $employeeLastName);

if ($employeeStmt->fetch()) {
    $employeeName = $employeeFirstName . ' ' . $employeeLastName;
} else {
    echo json_encode(['status' => 'error', 'message' => 'Employee not found.']);
    exit();
}
$employeeStmt->close();

$checkSql = "SELECT * FROM admin_evaluations WHERE a_id = ? AND e_id = ?";
$checkStmt = $conn->prepare($checkSql);
$checkStmt->bind_param('ii', $adminId, $employeeId);
$checkStmt->execute();
$checkStmt->store_result();

if ($checkStmt->num_rows > 0) {
    echo json_encode(['status' => 'error', 'message' => 'You have already evaluated this employee.']);
} else {
    $sql = "INSERT INTO admin_evaluations (
                a_id, admin_name, e_id, employee_name, department, quality, communication_skills, teamwork, punctuality, initiative
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        'isissddddd',
        $adminId,
        $adminName,
        $employeeId,
        $employeeName,
        $department,
        $categoryAverages['QualityOfWork'],
        $categoryAverages['CommunicationSkills'],
        $categoryAverages['Teamwork'],
        $categoryAverages['Punctuality'],
        $categoryAverages['Initiative']
    );

    if ($stmt->execute()) {
        $actionType = "Employee Evaluation";
        $affectedFeature = "Evaluation";
        $details = "Admin ($adminName) evaluated employee Name: $employeeName in $department.";
        $ipAddress = $_SERVER['REMOTE_ADDR'];

        $logQuery = "INSERT INTO activity_logs (admin_id, admin_name, action_type, affected_feature, details, ip_address)
                     VALUES (?, ?, ?, ?, ?, ?)";
        $logStmt = $conn->prepare($logQuery);
        $logStmt->bind_param("isssss", $adminId, $adminName, $actionType, $affectedFeature, $details, $ipAddress);

        if ($logStmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Evaluation saved and activity logged successfully.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error logging the activity.']);
        }

        $logStmt->close();
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error saving the evaluation.']);
    }
    $stmt->close();
}

$checkStmt->close();
$conn->close();
?>

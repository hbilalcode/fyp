<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}
include '../includes/db.php';

$student_id = $_SESSION['user_id'];

// Get registered semesters
$semesters_sql = "SELECT semester_id FROM student_semester_registration WHERE student_id = ? AND status = 'registered'";
$stmt = $conn->prepare($semesters_sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$semester_result = $stmt->get_result();

$semester_ids = [];
while ($row = $semester_result->fetch_assoc()) {
    $semester_ids[] = $row['semester_id'];
}

if (empty($semester_ids)) {
    $timetable = [];
    $time_slots = [];
} else {
    $placeholders = implode(',', array_fill(0, count($semester_ids), '?'));
    $sql = "
        SELECT cs.schedule_id, s.subject_name, s.subject_code, cs.day_of_week, cs.start_time, cs.end_time,
               cr.room_number, u.first_name AS faculty_first, u.last_name AS faculty_last
        FROM class_schedule cs
        JOIN subjects s ON cs.subject_id = s.subject_id
        JOIN classrooms cr ON cs.classroom_id = cr.classroom_id
        JOIN users u ON cs.faculty_id = u.user_id
        WHERE cs.semester_id IN ($placeholders)
        ORDER BY cs.start_time, FIELD(cs.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')
    ";
    $stmt = $conn->prepare($sql);
    $types = str_repeat('i', count($semester_ids));
    $stmt->bind_param($types, ...$semester_ids);
    $stmt->execute();
    $result = $stmt->get_result();

    $timetable = []; // [time][day] = class info
    $time_slots = [];

    while ($row = $result->fetch_assoc()) {
        $start = $row['start_time'];
        $end = $row['end_time'];
        $day = $row['day_of_week'];

        // Create a key for the time slot, e.g. "09:00-10:00"
        $slot_key = $start . '-' . $end;

        // Collect unique time slots
        if (!in_array($slot_key, $time_slots)) {
            $time_slots[] = $slot_key;
        }

        // Store class info in timetable array
        $timetable[$slot_key][$day] = $row;
    }

    // Sort time slots chronologically
    usort($time_slots, function($a, $b) {
        return strcmp(explode('-', $a)[0], explode('-', $b)[0]);
    });
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>My Timetable | BS Academic System</title>
<link rel="stylesheet" href="../assets/css/dashboard.css" />
</head>
<body>
<div class="dashboard">
  <?php include 'sidebar.php'; ?>

  <div class="main-content">
    <div class="std-timetable-container">
      <h2>My Weekly Timetable</h2>
      <?php if (empty($timetable)): ?>
        <p>You are not registered in any semester or no timetable available.</p>
      <?php else: ?>
        <table class="timetable">
          <thead>
            <tr>
              <th>Time</th>
              <th>Monday</th>
              <th>Tuesday</th>
              <th>Wednesday</th>
              <th>Thursday</th>
              <th>Friday</th>
              <th>Saturday</th>
              <th>Sunday</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($time_slots as $slot): 
              list($start, $end) = explode('-', $slot);
              $start_fmt = date('h:i A', strtotime($start));
              $end_fmt = date('h:i A', strtotime($end));
            ?>
              <tr>
                <td><?= $start_fmt ?> - <?= $end_fmt ?></td>
                <?php foreach (['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $day): ?>
                  <td>
                    <?php if (isset($timetable[$slot][$day])): 
                      $cls = $timetable[$slot][$day];
                    ?>
                      <div class="class-info">
                        <div class="class-subject"><?= htmlspecialchars($cls['subject_code']) ?>: <?= htmlspecialchars($cls['subject_name']) ?></div>
                        <div class="class-faculty"><?= htmlspecialchars($cls['faculty_first'] . ' ' . $cls['faculty_last']) ?></div>
                        <div class="class-room">Room: <?= htmlspecialchars($cls['room_number']) ?></div>
                      </div>
                    <?php else: ?>
                      &mdash;
                    <?php endif; ?>
                  </td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>

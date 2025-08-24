<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

include '../includes/db.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_classroom'])) {
        // Add new classroom
        $room_number = $_POST['room_number'];
        $building = $_POST['building'];
        $capacity = $_POST['capacity'];
        $description = $_POST['description'];

        $sql = "INSERT INTO classrooms (room_number, building, capacity, description)
                VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssis", $room_number, $building, $capacity, $description);
        $stmt->execute();
    }
    elseif (isset($_POST['add_schedule'])) {
        // Add new class schedule
        $subject_id = $_POST['subject_id'];
        $faculty_id = $_POST['faculty_id'];
        $classroom_id = $_POST['classroom_id'];
        $semester_id = $_POST['semester_id'];
        $days_of_week = isset($_POST['days_of_week']) ? $_POST['days_of_week'] : [];
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];

        $error = '';
        $success = '';
        
        foreach ($days_of_week as $day_of_week) {
            // Check for conflicts
            $conflict_sql = "SELECT COUNT(*) as conflict FROM class_schedule
                            WHERE classroom_id = ? AND day_of_week = ?
                            AND (
                                (start_time <= ? AND end_time > ?) OR
                                (start_time < ? AND end_time >= ?) OR
                                (start_time >= ? AND end_time <= ?)
                            )";
            $stmt = $conn->prepare($conflict_sql);
            $stmt->bind_param("isssssss", $classroom_id, $day_of_week, 
                             $start_time, $start_time, $end_time, $end_time,
                             $start_time, $end_time);
            $stmt->execute();
            $conflict = $stmt->get_result()->fetch_assoc()['conflict'];

            if ($conflict > 0) {
                $error .= "Classroom is already booked on $day_of_week during this time slot!<br>";
            } else {
                $insert_sql = "INSERT INTO class_schedule 
                              (subject_id, faculty_id, classroom_id, semester_id, 
                               day_of_week, start_time, end_time, start_date, end_date)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($insert_sql);
                $stmt->bind_param("iiiisssss", $subject_id, $faculty_id, $classroom_id, 
                                 $semester_id, $day_of_week, $start_time, $end_time, 
                                 $start_date, $end_date);
                if ($stmt->execute()) {
                    $success .= "Class schedule added successfully for $day_of_week!<br>";
                } else {
                    $error .= "Error adding schedule for $day_of_week: " . $conn->error . "<br>";
                }
            }
        }
    }
    elseif (isset($_POST['delete_schedule'])) {
        // Delete schedule
        $schedule_id = $_POST['schedule_id'];
        $sql = "DELETE FROM class_schedule WHERE schedule_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $schedule_id);
        $stmt->execute();
    }
}

// Fetch data for forms
$subjects = $conn->query("SELECT subject_id, subject_name FROM subjects ORDER BY subject_name");
$faculty = $conn->query("SELECT f.faculty_id, u.first_name, u.last_name 
                        FROM faculty f JOIN users u ON f.faculty_id = u.user_id
                        ORDER BY u.last_name");
$classrooms = $conn->query("SELECT * FROM classrooms ORDER BY building, room_number");
$semesters = $conn->query("SELECT * FROM semesters ORDER BY semester_number");

// Fetch current schedules with joins for display
$schedules_sql = "SELECT cs.*, s.subject_name, 
                 CONCAT(u.first_name, ' ', u.last_name) as faculty_name,
                 CONCAT(c.building, ' ', c.room_number) as classroom,
                 sem.semester_number
                 FROM class_schedule cs
                 JOIN subjects s ON cs.subject_id = s.subject_id
                 JOIN faculty f ON cs.faculty_id = f.faculty_id
                 JOIN users u ON f.faculty_id = u.user_id
                 JOIN classrooms c ON cs.classroom_id = c.classroom_id
                 JOIN semesters sem ON cs.semester_id = sem.semester_id
                 ORDER BY cs.day_of_week, cs.start_time";
$schedules = $conn->query($schedules_sql);

// Generate timetable view
$timetable = [];
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

// Create time slots based on college schedule
$time_slots = [
    '08:40:00', // 8:40 AM - 9:40 AM
    '09:40:00', // 9:40 AM - 10:40 AM
    '10:40:00', // 10:40 AM - 11:40 AM
    '12:00:00', // 12:00 PM - 1:00 PM (after 20 min break)
    '13:00:00'  // 1:00 PM - 2:00 PM
];

// Initialize timetable structure
foreach ($days as $day) {
    $timetable[$day] = [];
    foreach ($time_slots as $time) {
        $timetable[$day][$time] = null;
    }
}

// Populate timetable with classes
while ($schedule = $schedules->fetch_assoc()) {
    $day = $schedule['day_of_week'];
    $start = $schedule['start_time'];
    $end = $schedule['end_time'];
    
    // Find all time slots this class occupies
    foreach ($time_slots as $time) {
        if ($time >= $start && $time < $end) {
            $timetable[$day][$time] = $schedule;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Timetable Management | BS Academic System</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h3>BS Academic System</h3>
                <p>Admin Dashboard</p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">Dashboard</a></li>
                <li><a href="manage_users.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'manage_users.php' ? 'active' : ''; ?>">Manage Users</a></li>
                <li><a href="manage_subjects.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'manage_subjects.php' ? 'active' : ''; ?>">Manage Subjects</a></li>
                <li><a href="manage_semesters.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'manage_semesters.php' ? 'active' : ''; ?>">Manage Semesters</a></li>
                <li><a href="manage_courses.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'manage_courses.php' ? 'active' : ''; ?>">Manage Courses</a></li>
                <li><a href="timetable_management.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'timetable_management.php' ? 'active' : ''; ?>">Manage Timetable</a></li>
                <li><a href="reports.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">Reports</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>

        <div class="main-content">
            <div class="header">
                <h2>Timetable Management</h2>
            </div>

            <div class="card">
                <?php if (!empty($success)): ?>
                <div class="alert success"><?php echo $success; ?></div>
                <?php endif; ?>
                <?php if (!empty($error)): ?>
                <div class="alert error"><?php echo $error; ?></div>
                <?php endif; ?>
                <div class="management-sections">
                    <div>
                        <div class="management-section">
                            <h3>Add New Classroom</h3>
                            <form method="POST" class="classroom-form">
                                <div class="form-group">
                                    <label for="building">Building</label>
                                    <input type="text" id="building" name="building" required>
                                </div>
                                <div class="form-group">
                                    <label for="room_number">Room Number</label>
                                    <input type="text" id="room_number" name="room_number" required>
                                </div>
                                <div class="form-group">
                                    <label for="capacity">Capacity</label>
                                    <input type="number" id="capacity" name="capacity" min="1" required>
                                </div>
                                <div class="form-group">
                                    <label for="description">Description</label>
                                    <textarea id="description" name="description" rows="2"></textarea>
                                </div>
                                <button type="submit" name="add_classroom" class="btn">Add Classroom</button>
                            </form>
                        </div>

                        <div class="management-section">
                            <h3>Add Class Schedule</h3>
                            <form method="POST">
                                <div class="form-group">
                                    <label for="subject_id">Subject</label>
                                    <select id="subject_id" name="subject_id" required>
                                        <option value="">-- Select Subject --</option>
                                        <?php while ($subject = $subjects->fetch_assoc()): ?>
                                            <option value="<?php echo $subject['subject_id']; ?>">
                                                <?php echo $subject['subject_name']; ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="faculty_id">Faculty</label>
                                    <select id="faculty_id" name="faculty_id" required>
                                        <option value="">-- Select Faculty --</option>
                                        <?php while ($fac = $faculty->fetch_assoc()): ?>
                                            <option value="<?php echo $fac['faculty_id']; ?>">
                                                <?php echo $fac['first_name'] . ' ' . $fac['last_name']; ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="classroom_id">Classroom</label>
                                    <select id="classroom_id" name="classroom_id" required>
                                        <option value="">-- Select Classroom --</option>
                                        <?php while ($classroom = $classrooms->fetch_assoc()): ?>
                                            <option value="<?php echo $classroom['classroom_id']; ?>">
                                                <?php echo $classroom['building'] . ' ' . $classroom['room_number']; ?>
                                                (Capacity: <?php echo $classroom['capacity']; ?>)
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="semester_id">Semester</label>
                                    <select id="semester_id" name="semester_id" required>
                                        <option value="">-- Select Semester --</option>
                                        <?php while ($semester = $semesters->fetch_assoc()): ?>
                                            <option value="<?php echo $semester['semester_id']; ?>">
                                                Semester <?php echo $semester['semester_number']; ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Days of Week</label>
                                    <div class="days-checkboxes">
                                        <label class="day-checkbox"><input type="checkbox" name="days_of_week[]" value="Monday"> Monday</label>
                                        <label class="day-checkbox"><input type="checkbox" name="days_of_week[]" value="Tuesday"> Tuesday</label>
                                        <label class="day-checkbox"><input type="checkbox" name="days_of_week[]" value="Wednesday"> Wednesday</label>
                                        <label class="day-checkbox"><input type="checkbox" name="days_of_week[]" value="Thursday"> Thursday</label>
                                        <label class="day-checkbox"><input type="checkbox" name="days_of_week[]" value="Friday"> Friday</label>
                                        <label class="day-checkbox"><input type="checkbox" name="days_of_week[]" value="Saturday"> Saturday</label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="start_time">Start Time</label>
                                    <select id="start_time" name="start_time" required>
                                        <option value="08:40:00">8:40 AM</option>
                                        <option value="09:40:00">9:40 AM</option>
                                        <option value="10:40:00">10:40 AM</option>
                                        <option value="12:00:00">12:00 PM</option>
                                        <option value="13:00:00">1:00 PM</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="end_time">End Time</label>
                                    <select id="end_time" name="end_time" required>
                                        <option value="09:40:00">9:40 AM</option>
                                        <option value="10:40:00">10:40 AM</option>
                                        <option value="11:40:00">11:40 AM</option>
                                        <option value="13:00:00">1:00 PM</option>
                                        <option value="14:00:00">2:00 PM</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="start_date">Start Date</label>
                                    <input type="date" id="start_date" name="start_date" required>
                                </div>
                                <div class="form-group">
                                    <label for="end_date">End Date</label>
                                    <input type="date" id="end_date" name="end_date" required>
                                </div>
                                <button type="submit" name="add_schedule" class="btn">Add Schedule</button>
                            </form>
                        </div>
                    </div>

                    <div class="management-section">
                        <h3>Current Schedules</h3>
                        <table style="width:100%; border-collapse:collapse;">
                            <thead>
                                <tr>
                                    <th>Subject</th>
                                    <th>Faculty</th>
                                    <th>Classroom</th>
                                    <th>Day/Time</th>
                                    <th>Semester</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                // Reset pointer for schedules result
                                $schedules->data_seek(0);
                                while ($schedule = $schedules->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $schedule['subject_name']; ?></td>
                                        <td><?php echo $schedule['faculty_name']; ?></td>
                                        <td><?php echo $schedule['classroom']; ?></td>
                                        <td>
                                            <?php echo $schedule['day_of_week']; ?><br>
                                            <?php echo date('h:i A', strtotime($schedule['start_time'])); ?> - 
                                            <?php echo date('h:i A', strtotime($schedule['end_time'])); ?>
                                        </td>
                                        <td>Sem <?php echo $schedule['semester_number']; ?></td>
                                        <td>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="schedule_id" value="<?php echo $schedule['schedule_id']; ?>">
                                                <button type="submit" name="delete_schedule" class="action-btn btn-delete" 
                                                        onclick="return confirm('Are you sure you want to delete this schedule?')">
                                                    Delete
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="management-section">
                    
                    <h3>Weekly Timetable Overview</h3>
                    <div id="timetable-to-export">
                        <div class="print-only">
                            <h2>College Timetable</h2>
                            <p>Generated on: <?php echo date('F j, Y'); ?></p>
                        </div>
                        <div class="timetable-container">
                            <table class="timetable">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <?php foreach ($days as $day): ?>
                                            <th><?php echo $day; ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    // Define time slot durations
                                    $time_slot_durations = [
                                        '08:40:00' => '09:40:00',
                                        '09:40:00' => '10:40:00',
                                        '10:40:00' => '11:40:00',
                                        '12:00:00' => '13:00:00',
                                        '13:00:00' => '14:00:00'
                                    ];
                                    
                                    foreach ($time_slot_durations as $start_time => $end_time): 
                                        $start_display = date('h:i A', strtotime($start_time));
                                        $end_display = date('h:i A', strtotime($end_time));
                                    ?>
                                        <tr>
                                            <td class="time-col">
                                                <span class="time-label"><?php echo $start_display; ?></span>
                                                <span class="time-label">to</span>
                                                <span class="time-label"><?php echo $end_display; ?></span>
                                            </td>
                                            <?php foreach ($days as $day): 
                                                $class = $timetable[$day][$start_time];
                                            ?>
                                                <td>
                                                    <?php if ($start_time == '11:40:00'): ?>
                                                        <!-- Break time slot -->
                                                        <div class="break-slot">Break (20 mins)</div>
                                                    <?php elseif ($class && $class['start_time'] == $start_time): ?>
                                                        <div class="class-slot">
                                                            <span><strong><?php echo $class['subject_name']; ?></strong></span>
                                                            <span><?php echo $class['faculty_name']; ?></span>
                                                            <span><?php echo $class['classroom']; ?></span>
                                                            <span>Sem <?php echo $class['semester_number']; ?></span>
                                                        </div>
                                                    <?php elseif ($class): ?>
                                                        <!-- This cell is part of a multi-slot class, already displayed -->
                                                    <?php endif; ?>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="export-buttons no-print">
                        <button class="export-btn" onclick="exportToPDF()">
                            <i class="fas fa-file-pdf"></i> Export to PDF
                        </button>
                    </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        // Set default dates for schedule form
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date();
            const nextMonday = new Date();
            nextMonday.setDate(today.getDate() + (1 + 7 - today.getDay()) % 7);
            
            // Format as YYYY-MM-DD
            const formatDate = (date) => date.toISOString().split('T')[0];
            
            document.getElementById('start_date').value = formatDate(nextMonday);
            document.getElementById('end_date').value = formatDate(new Date(nextMonday.getFullYear(), nextMonday.getMonth(), nextMonday.getDate() + 90));
        });

        // Export to PDF function
        function exportToPDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF('l', 'mm', 'a4');
            
            // Add title
            doc.setFontSize(16);
            doc.text('College Timetable', 105, 15, { align: 'center' });
            doc.setFontSize(10);
            doc.text(`Generated on: ${new Date().toLocaleDateString()}`, 105, 22, { align: 'center' });
            
            // Prepare data for the table
            const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            const timeSlots = [
                { start: '08:40:00', end: '09:40:00' },
                { start: '09:40:00', end: '10:40:00' },
                { start: '10:40:00', end: '11:40:00' },
                { start: '12:00:00', end: '13:00:00' },
                { start: '13:00:00', end: '14:00:00' }
            ];
            
            const headers = ['Time', ...days];
            const rows = [];
            
            timeSlots.forEach(slot => {
                const startTime = formatTime(slot.start);
                const endTime = formatTime(slot.end);
                const row = [`${startTime} - ${endTime}`];
                
                days.forEach(day => {
                    const classInfo = <?php echo json_encode($timetable); ?>[day][slot.start];
                    if (slot.start === '11:40:00') {
                        row.push('Break (20 mins)');
                    } else if (classInfo && classInfo.start_time === slot.start) {
                        row.push(
                            `${classInfo.subject_name}\n` +
                            `${classInfo.faculty_name}\n` +
                            `${classInfo.classroom}\n` +
                            `Sem ${classInfo.semester_number}`
                        );
                    } else if (classInfo) {
                        // Multi-slot class, already displayed
                        row.push('');
                    } else {
                        row.push('');
                    }
                });
                
                rows.push(row);
            });
            
            // Generate the table
            doc.autoTable({
                head: [headers],
                body: rows,
                startY: 25,
                styles: {
                    fontSize: 8,
                    cellPadding: 2,
                    valign: 'middle'
                },
                columnStyles: {
                    0: { fontStyle: 'bold', cellWidth: 25 }
                },
                didDrawPage: function(data) {
                    // Footer
                    doc.setFontSize(8);
                    doc.setTextColor(150);
                    doc.text('Generated by Academic System', data.settings.margin.left, doc.internal.pageSize.height - 10);
                }
            });
            
            // Save the PDF
            doc.save('College_Timetable.pdf');
        }
        
        function formatTime(timeString) {
            const time = new Date(`1970-01-01T${timeString}`);
            return time.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: true });
        }
    </script>
</body>
</html>
<?php
require "db.php";

$students = [
    ["Aarav Patel", "aarav@example.com", "BCA"],
    ["Diya Shah", "diya@example.com", "BSc IT"],
    ["Rohan Mehta", "rohan@example.com", "BCA"],
    ["Ananya Desai", "ananya@example.com", "MCA"],
    ["Krish Joshi", "krish@example.com", "BSc IT"],
    ["Isha Trivedi", "isha@example.com", "BCA"],
    ["Arjun Parmar", "arjun@example.com", "MCA"],
    ["Neha Patel", "neha@example.com", "BSc IT"],
    ["Vivaan Shah", "vivaan@example.com", "BCA"],
    ["Sara Mehta", "sara@example.com", "MCA"],
    ["Dev Shah", "dev@example.com", "BSc IT"],
    ["Mira Desai", "mira@example.com", "BCA"],
    ["Kabir Joshi", "kabir@example.com", "MCA"],
    ["Riya Patel", "riya@example.com", "BSc IT"],
    ["Yash Mehta", "yash@example.com", "BCA"],
    ["Pooja Shah", "pooja@example.com", "MCA"],
    ["Aditya Desai", "aditya@example.com", "BSc IT"],
    ["Kavya Joshi", "kavya@example.com", "BCA"],
    ["Manav Trivedi", "manav@example.com", "MCA"],
    ["Nisha Parmar", "nisha@example.com", "BSc IT"]
];

$stmt = $conn->prepare(
    "INSERT INTO students (name, email, course)
     VALUES (?, ?, ?)
     ON DUPLICATE KEY UPDATE
     name = VALUES(name), course = VALUES(course)"
);

foreach ($students as $student) {
    $stmt->bind_param(
        "sss",
        $student[0],
        $student[1],
        $student[2]
    );
    $stmt->execute();
}

echo "20 sample student records inserted successfully.";